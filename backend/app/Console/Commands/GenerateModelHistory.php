<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BikeModel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class GenerateModelHistory extends Command
{
    protected $signature = 'models:generate-history
        {--chunk=10 : 処理する車種数}
        {--dry-run : APIを呼ばずデータ確認のみ}';

    protected $description = '車種モデルのモデル履歴データをAI生成';

    private const API_ENDPOINT = 'https://api.anthropic.com/v1/messages';

    private const MAX_TOKENS = 2000;

    private const SLEEP_SECONDS = 2;

    private const MIN_ACTIVE_COUNT = 5;

    public function handle(): int
    {
        $apiKey = config('services.anthropic.api_key');
        $isDryRun = $this->option('dry-run');
        $chunk = (int) $this->option('chunk');

        if (! $isDryRun && ! $apiKey) {
            $this->error('ANTHROPIC_API_KEY が .env に設定されていません。');

            return self::FAILURE;
        }

        $models = BikeModel::with(['manufacturer', 'categoryData'])
            ->whereNull('model_history')
            ->withCount(['listings' => fn ($q) => $q->where('is_sold_out', false)])
            ->having('listings_count', '>=', self::MIN_ACTIVE_COUNT)
            ->where('name', '!=', '他車種')
            ->orderByDesc('listings_count')
            ->limit($chunk)
            ->get();

        if ($models->isEmpty()) {
            $this->info('処理対象の車種がありません。');

            return self::SUCCESS;
        }

        $total = $models->count();
        $completed = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($models as $index => $model) {
            $num = $index + 1;
            $prefix = "[{$num}/{$total}] {$model->name}";

            if ($model->listings_count < self::MIN_ACTIVE_COUNT) {
                $this->warn("{$prefix}: 販売中 {$model->listings_count}台 → スキップ");
                $skipped++;

                continue;
            }

            $this->info("{$prefix}: 処理開始（販売中 {$model->listings_count}台）");

            if ($isDryRun) {
                $this->line("  メーカー: {$model->manufacturer?->name}");
                $this->line("  排気量: {$model->displacement}cc");
                $this->line("  カテゴリ: {$model->categoryData?->name}");
                $completed++;

                continue;
            }

            try {
                $history = $this->callClaudeApi($apiKey, $model);
            } catch (\Throwable $e) {
                $this->error("{$prefix}: API呼び出しエラー - {$e->getMessage()}");
                Log::error('GenerateModelHistory: API呼び出し失敗', [
                    'model_id' => $model->id,
                    'error' => $e->getMessage(),
                ]);
                $errors++;

                continue;
            }

            if ($history === null) {
                $this->error("{$prefix}: レスポンスのパースに失敗");
                $errors++;

                continue;
            }

            $model->update([
                'model_history' => $history,
                'history_generated_at' => now(),
            ]);

            $genCount = count($history['generations'] ?? []);
            $this->info("{$prefix}: 保存完了（{$genCount}世代）");
            $completed++;

            if ($index < $total - 1) {
                sleep(self::SLEEP_SECONDS);
            }
        }

        $this->newLine();
        $this->info("完了: {$completed}車種の履歴を生成しました（スキップ: {$skipped}件、エラー: {$errors}件）");

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function callClaudeApi(string $apiKey, BikeModel $model): ?array
    {
        $systemPrompt = <<<'PROMPT'
あなたはバイクの歴史に詳しい専門家です。
バイク車種のモデル履歴データをJSON形式で提供してください。

ルール：
- 確実に知っている事実のみを書く
- 不確かな場合はそのフィールドをnullにする
- 年号や価格は正確に。推測で書かない
- 日本市場での情報を優先する
PROMPT;

        $name = $model->name;
        $manufacturer = $model->manufacturer?->name ?? '不明';
        $displacement = $model->displacement ?? '不明';
        $category = $model->categoryData?->name ?? '不明';

        $userPrompt = <<<PROMPT
以下のバイク車種のモデル履歴をJSON形式で出力してください。
他の文字は一切含めず、JSONのみを出力してください。

車種名: {$name}
メーカー: {$manufacturer}
排気量: {$displacement}cc
カテゴリ: {$category}

{
  "first_year": 1958,
  "last_year": null,
  "is_current": true,
  "current_new_price_min": 23,
  "current_new_price_max": 25,
  "generations": [
    {
      "name": "初代（AA01）",
      "start_year": 1958,
      "end_year": 2007,
      "changes": "角目ヘッドライト、キャブレター仕様",
      "new_price": null
    },
    {
      "name": "2代目（AA04/AA09）",
      "start_year": 2012,
      "end_year": 2021,
      "changes": "FI化（燃料噴射）、丸目ヘッドライトに回帰",
      "new_price": 20
    },
    {
      "name": "現行（AA12）",
      "start_year": 2022,
      "end_year": null,
      "changes": "新設計フレーム、LEDヘッドライト、前後キャストホイール",
      "new_price": 24
    }
  ]
}

フィールド説明：
- first_year: 初代の発売年（わからなければnull）
- last_year: 生産終了年（現行販売中ならnull）
- is_current: 現在新車で購入可能か（true/false）
- current_new_price_min/max: 現行モデルの新車価格（万円）。生産終了ならnull
- generations: モデルチェンジの世代一覧（わかる範囲で）
  - name: 世代名（型式がわかれば含める）
  - start_year/end_year: 販売期間
  - changes: その世代の主な変更点（1文で）
  - new_price: 当時の新車価格（万円、わからなければnull）

不明な車種やマイナー車種の場合：
- generationsを空配列にする
- first_year等もnullにする
- 無理にデータを作らない
PROMPT;

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->timeout(60)->post(self::API_ENDPOINT, [
            'model' => config('services.anthropic.model'),
            'max_tokens' => self::MAX_TOKENS,
            'system' => $systemPrompt,
            'messages' => [
                ['role' => 'user', 'content' => $userPrompt],
            ],
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException("API error: {$response->status()} - {$response->body()}");
        }

        $body = $response->json();
        $text = $body['content'][0]['text'] ?? null;

        if (! $text) {
            Log::error('GenerateModelHistory: API応答にtextなし', ['body' => $body]);

            return null;
        }

        return $this->parseJsonResponse($text);
    }

    private function parseJsonResponse(string $text): ?array
    {
        // ```json ... ``` フェンスを除去
        $text = preg_replace('/^```(?:json)?\s*/i', '', trim($text));
        $text = preg_replace('/\s*```$/i', '', $text);

        $decoded = json_decode(trim($text), true);

        if (! is_array($decoded)) {
            Log::error('GenerateModelHistory: JSONパース失敗', ['raw' => $text]);

            return null;
        }

        // generationsキーが存在することを確認
        if (! array_key_exists('generations', $decoded)) {
            Log::error('GenerateModelHistory: generationsキーなし', ['decoded' => $decoded]);

            return null;
        }

        return $decoded;
    }
}
