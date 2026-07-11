<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ModelAnswer;
use App\Models\ModelQuestion;
use App\Models\PushQuestionSubscription;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/**
 * 車種Q&A: 回答が付いた質問の購読者へ「回答が付きました」を1通送る。
 * トリガーは ModelAnswerObserver（即反映 or キル解除で is_approved=true）。
 *
 * ★キューワーカー非依存：Observer は dispatchAfterResponse() で投入し、HTTPレスポンス送出後に
 *   php-fpm 同プロセスで実行する（本番に queue:work 常駐が無いため。既存 SendNewStockPush も
 *   同期送信でキューを使わない方針に合わせる）。送信済み判定は model_answers.answer_pushed_at 側で担保。
 */
final class SendQaAnswerNotification
{
    use Dispatchable;

    public function __construct(public int $answerId) {}

    public function handle(): void
    {
        $answer = ModelAnswer::with('question.bikeModel.manufacturer')->find($this->answerId);

        // キル/非承認は送らない（approvedAnswers 経由の既存ガードに合わせる）
        if ($answer === null || ! $answer->is_approved) {
            return;
        }

        $question = $answer->question;
        if ($question === null || ! $question->is_approved) {
            return;
        }

        $subscriptions = $question->pushSubscriptions()->get();
        if ($subscriptions->isEmpty()) {
            return;
        }

        // VAPID未設定の環境（開発等）ではライブラリが例外を投げるため何もしない
        if (! config('webpush.vapid.public_key') || ! config('webpush.vapid.private_key')) {
            return;
        }

        $payload = json_encode($this->buildPayload($question));

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
                    'keys' => [
                        'p256dh' => $sub->p256dh,
                        'auth' => $sub->auth,
                    ],
                    // 既存 SendNewStockPush と同じエンコーディング（配信検証後の切替方針も共通）
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

            Log::warning('Q&A回答プッシュ送信失敗: '.$report->getReason(), [
                'endpoint' => substr($report->getEndpoint(), 0, 80),
                'status' => $statusCode,
            ]);
        }

        // 期限切れ購読を削除（このテーブルからのみ）
        if (! empty($expired)) {
            $hashes = array_map(fn (string $ep) => hash('sha256', $ep), $expired);
            PushQuestionSubscription::whereIn('endpoint_hash', $hashes)->delete();
        }
    }

    /**
     * @return array<string, string>
     */
    private function buildPayload(ModelQuestion $question): array
    {
        $model = $question->bikeModel;
        $maker = $model?->manufacturer?->name ?? '';
        $name = $model?->name ?? '';

        $url = 'https://www.motohub.jp'.$this->questionPath($question);
        $url .= (str_contains($url, '?') ? '&' : '?').'utm_source=push&utm_medium=webpush';
        $url .= '#answers';

        $label = trim($maker.' '.$name);

        return [
            'title' => '💬 あなたの質問に回答が付きました',
            'body' => ($label !== '' ? $label.'「'.$question->title.'」' : $question->title).' に回答が届きました。',
            'url' => $url,
            'icon' => 'https://www.motohub.jp/favicon-96x96.png',
            'badge' => 'https://www.motohub.jp/favicon-96x96.png',
            'tag' => 'qa-answer-'.$question->id,
        ];
    }

    private function questionPath(ModelQuestion $question): string
    {
        $model = $question->bikeModel;

        return route('bikes.model_question', [
            'mfrSlug' => $model?->manufacturer?->slug ?? $model?->manufacturer_id,
            'modelSlug' => $model?->slug ?? $model?->id,
            'id' => $question->id,
        ], absolute: false);
    }
}
