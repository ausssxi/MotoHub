<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\DiscussionReply;
use App\Models\DiscussionThread;
use App\Models\ThreadPushSubscription;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/**
 * 統合スレッドに「人間の返信」が付いたら、スレ購読者（質問者）へプッシュ1通。
 * MotoHub必答(公式/AI)では撃たない（自分が作ったスレの自動回答で通知が飛ぶのは冗長）。
 * キューワーカー非常駐のため DiscussionReplyObserver から dispatchAfterResponse で実行。
 */
final class SendThreadReplyNotification
{
    use Dispatchable;

    public function __construct(public int $replyId) {}

    public function handle(): void
    {
        $reply = DiscussionReply::with('thread.bikeModel.manufacturer')->find($this->replyId);
        if ($reply === null || $reply->is_official || $reply->status !== 'published') {
            return; // 公式(必答)・非公開は通知しない
        }
        $thread = $reply->thread;
        if ($thread === null || $thread->status !== 'published') {
            return;
        }

        $subscriptions = $thread->pushSubscriptions()->get();
        if ($subscriptions->isEmpty()) {
            return;
        }
        if (! config('webpush.vapid.public_key') || ! config('webpush.vapid.private_key')) {
            return;
        }

        $payload = json_encode($this->buildPayload($thread));

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
            Log::warning('スレ返信プッシュ送信失敗: '.$report->getReason(), ['status' => $statusCode]);
        }
        if (! empty($expired)) {
            $hashes = array_map(fn (string $ep) => hash('sha256', $ep), $expired);
            ThreadPushSubscription::whereIn('endpoint_hash', $hashes)->delete();
        }
    }

    /**
     * @return array<string, string>
     */
    private function buildPayload(DiscussionThread $thread): array
    {
        $model = $thread->bikeModel;
        $label = trim(($model?->manufacturer?->name ?? '').' '.($model?->name ?? ''));

        $url = 'https://www.motohub.jp'.route('bikes.thread', [
            'mfrSlug' => $model?->manufacturer?->slug ?? $model?->manufacturer_id,
            'modelSlug' => $model?->slug ?? $model?->id,
            'id' => $thread->id,
        ], absolute: false);
        $url .= (str_contains($url, '?') ? '&' : '?').'utm_source=push&utm_medium=webpush#replies';

        return [
            'title' => '💬 あなたの質問に返信が付きました',
            'body' => ($label !== '' ? $label.'「'.$thread->title.'」' : $thread->title).' に新しい返信が届きました。',
            'url' => $url,
            'icon' => 'https://www.motohub.jp/favicon-96x96.png',
            'badge' => 'https://www.motohub.jp/favicon-96x96.png',
            'tag' => 'thread-reply-'.$thread->id,
        ];
    }
}
