<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\MyBike;
use App\Models\PushSubscription;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/**
 * 公開ガレージにコメント/いいねが付いたら、オーナー（登録ユーザー）へプッシュ1通＝再訪フック。
 * 宛先は汎用 push_subscriptions（user_id キー・会員がブラウザ購読）。
 * 自己通知抑制は呼び出し側（actor==owner なら dispatch しない）。ワーカー非常駐のため dispatchAfterResponse。
 */
final class SendGarageActivityNotification
{
    use Dispatchable;

    /** @param 'comment'|'like' $kind */
    public function __construct(
        public int $myBikeId,
        public string $kind,
        public string $actorName,
    ) {}

    public function handle(): void
    {
        $myBike = MyBike::with('user')->find($this->myBikeId);
        if ($myBike === null || ! $myBike->is_public) {
            return; // 非公開/削除済みには通知しない
        }
        $ownerId = $myBike->user_id;
        if ($ownerId === null) {
            return;
        }

        $subscriptions = PushSubscription::where('user_id', $ownerId)->get();
        if ($subscriptions->isEmpty()) {
            return; // オーナーがブラウザ購読していなければ何もしない（正常）
        }
        if (! config('webpush.vapid.public_key') || ! config('webpush.vapid.private_key')) {
            return;
        }

        $payload = json_encode($this->buildPayload($myBike));

        $webPush = new WebPush([
            'VAPID' => [
                'subject' => config('webpush.vapid.subject'),
                'publicKey' => config('webpush.vapid.public_key'),
                'privateKey' => config('webpush.vapid.private_key'),
            ],
        ]);

        foreach ($subscriptions as $sub) {
            $webPush->queueNotification(
                Subscription::create([
                    'endpoint' => $sub->endpoint,
                    'keys' => ['p256dh' => $sub->p256dh, 'auth' => $sub->auth],
                    'contentEncoding' => 'aesgcm',
                ]),
                $payload,
            );
        }

        $expired = [];
        foreach ($webPush->flush() as $report) {
            if ($report->isSuccess()) {
                continue;
            }
            $statusCode = $report->getResponse()?->getStatusCode();
            if (in_array($statusCode, [404, 410], true)) {
                $expired[] = $report->getEndpoint();
            }
            Log::warning('ガレージ通知プッシュ送信失敗: '.$report->getReason(), ['status' => $statusCode]);
        }
        if (! empty($expired)) {
            $hashes = array_map(fn (string $ep) => hash('sha256', $ep), $expired);
            PushSubscription::whereIn('endpoint_hash', $hashes)->delete();
        }
    }

    /** @return array<string, string> */
    private function buildPayload(MyBike $myBike): array
    {
        $bikeName = $myBike->bikeModel?->name ?? '愛車';
        $verb = $this->kind === 'comment' ? 'コメントしました' : 'いいねしました';

        return [
            'title' => 'あなたのガレージに反応がありました',
            'body' => "{$this->actorName}さんが「{$bikeName}」のガレージに{$verb}",
            'url' => route('garage.public.show', $myBike->id),
        ];
    }
}
