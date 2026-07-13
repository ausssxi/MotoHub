<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\BikeModel;
use App\Models\DiscussionReply;
use App\Models\DiscussionThread;
use App\Models\Listing;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * MotoHub必答（過疎対策の核）。質問スレの公式プレースホルダ返信を、モデルの実データを根拠に
 * AI（Claude）で本文生成して差し替える。ワーカー非常駐のため Observer から dispatchAfterResponse で実行。
 *
 * - 根拠は enriched_content ＋ スペック ＋ 相場のみ。データの無い項目は断定しない（ハルシネーション防止）。
 * - APIキー未設定/生成失敗でも、実データからの構造化フォールバックで必ずスレを成立させる（返信0にしない）。
 * - answer_generated_at で冪等（同じ回答を二重生成しない）。人を装わず「MotoHub」公式ラベルで表示される。
 */
final class GenerateMotoHubAnswer
{
    use Dispatchable;

    public function __construct(public int $replyId) {}

    public function handle(): void
    {
        $reply = DiscussionReply::with('thread.bikeModel.manufacturer')->find($this->replyId);

        // 冪等: 存在しない/非公式/生成済みならスキップ
        if ($reply === null || ! $reply->is_official || $reply->answer_generated_at !== null) {
            return;
        }
        $thread = $reply->thread;
        if ($thread === null || $thread->status !== 'published') {
            return;
        }
        $model = $thread->bikeModel;
        if ($model === null) {
            return;
        }

        $facts = $this->collectFacts($model);
        $body = $this->generate($thread, $model, $facts);

        $reply->forceFill([
            'body' => $body,
            'source' => 'ai',
            'answer_generated_at' => now(),
        ])->saveQuietly();
    }

    /**
     * 根拠データ（存在するものだけ）。無い項目はキー自体を持たない＝断定させない。
     *
     * @return array<string, mixed>
     */
    private function collectFacts(BikeModel $model): array
    {
        $facts = [];
        $facts['車種名'] = trim(($model->manufacturer->name ?? '').' '.$model->name);
        if ($model->displacement) {
            $facts['排気量cc'] = (int) $model->displacement;
        }

        $activeCount = Listing::where('bike_model_id', $model->id)->where('is_sold_out', false)->count();
        if ($activeCount > 0) {
            $facts['掲載台数'] = $activeCount;
            $avg = Listing::where('bike_model_id', $model->id)->where('is_sold_out', false)
                ->where('total_price', '>', 0)->avg('total_price');
            if ($avg) {
                $facts['中古相場平均万円'] = round(((float) $avg) / 10000, 1);
            }
        }

        // enriched_content（Claude生成の要約テキスト）を根拠テキストとして同梱
        $ec = $model->enriched_content;
        if (is_array($ec)) {
            foreach (['introduction', 'market_insight', 'buying_tips'] as $k) {
                if (! empty($ec[$k])) {
                    $facts[$k] = $ec[$k];
                }
            }
        }

        return $facts;
    }

    /**
     * @param  array<string, mixed>  $facts
     */
    private function generate(DiscussionThread $thread, BikeModel $model, array $facts): string
    {
        $apiKey = config('services.anthropic.api_key');

        // AI必答のコスト上限: 10/日/IP相当。超過（または失敗）時は生成せず構造化フォールバック＝
        // スレは必ず成立させつつ Claude 呼び出し費用の暴走を防ぐ。キーは thread の submitter_ip_hash。
        $rateKey = 'motohub-answer:'.($thread->submitter_ip_hash ?: 'anon');

        if (! empty($apiKey) && ! RateLimiter::tooManyAttempts($rateKey, 10)) {
            RateLimiter::hit($rateKey, 86400); // 1日
            try {
                $ai = $this->askClaude((string) $apiKey, (string) $thread->title, (string) $thread->body, $facts);
                if ($ai !== null && trim($ai) !== '') {
                    return trim($ai);
                }
            } catch (\Throwable $e) {
                Log::warning('MotoHub必答の生成に失敗（フォールバック使用）: '.$e->getMessage(), ['reply_id' => $this->replyId]);
            }
        }

        return $this->fallback($model, $facts);
    }

    /**
     * @param  array<string, mixed>  $facts
     */
    private function askClaude(string $apiKey, string $title, string $body, array $facts): ?string
    {
        $system = 'あなたは中古バイク情報サイトMotoHubの公式アシスタントです。以下の「根拠データ」に書かれている事実のみを使って、'
            .'質問に150〜250字で簡潔・丁寧に回答してください。根拠に無い数値・仕様・体験は絶対に創作せず、その点は「MotoHubのデータでは確認できません」と述べること。'
            .'一人称は「MotoHub」。人間のオーナーを装わないこと。';

        $user = "質問タイトル: {$title}\n質問本文: {$body}\n\n根拠データ(JSON):\n"
            .json_encode($facts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $res = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->timeout(30)->post('https://api.anthropic.com/v1/messages', [
            'model' => config('services.anthropic.model', 'claude-sonnet-4-6'),
            'max_tokens' => 700,
            'system' => $system,
            'messages' => [
                ['role' => 'user', 'content' => $user],
            ],
        ]);

        if (! $res->successful()) {
            Log::warning('MotoHub必答 Claude API 非成功: '.$res->status(), ['reply_id' => $this->replyId]);

            return null;
        }

        return $res->json('content.0.text');
    }

    /**
     * 実データからの構造化フォールバック（AI不使用でも成立させる・創作なし）。
     *
     * @param  array<string, mixed>  $facts
     */
    private function fallback(BikeModel $model, array $facts): string
    {
        $name = $facts['車種名'] ?? $model->name;
        $parts = ["【MotoHubのデータより】{$name}について、現時点のデータからお答えします。"];

        if (isset($facts['中古相場平均万円'], $facts['掲載台数'])) {
            $parts[] = "中古相場は平均{$facts['中古相場平均万円']}万円（{$facts['掲載台数']}台掲載）です。";
        }
        if (isset($facts['排気量cc'])) {
            $parts[] = "排気量は{$facts['排気量cc']}ccです。";
        }
        if (! empty($facts['introduction'])) {
            $parts[] = (string) $facts['introduction'];
        }

        if (count($parts) === 1) {
            $parts[] = 'このモデルの実データは順次拡充しています。他のライダーの回答もお待ちください。';
        }

        return implode(' ', $parts);
    }
}
