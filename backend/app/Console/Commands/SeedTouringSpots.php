<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\TouringSpot;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class SeedTouringSpots extends Command
{
    protected $signature = 'touring:seed-spots
        {--pref= : 特定の都道府県のみ処理（例: 北海道）}
        {--dry-run : APIを呼ばずプロンプト確認のみ}';

    protected $description = 'Claude APIを使って都道府県別ツーリングスポットを生成';

    private const API_ENDPOINT = 'https://api.anthropic.com/v1/messages';
    private const MODEL_ID = 'claude-sonnet-4-20250514';
    private const MAX_TOKENS = 4000;
    private const SLEEP_SECONDS = 2;

    public function handle(): int
    {
        $apiKey = config('services.anthropic.api_key');
        $isDryRun = $this->option('dry-run');
        $prefFilter = $this->option('pref');

        if (!$isDryRun && !$apiKey) {
            $this->error('ANTHROPIC_API_KEY が .env に設定されていません。');
            return self::FAILURE;
        }

        $prefectures = TouringSpot::PREFECTURE_SLUG_MAP;

        if ($prefFilter) {
            $found = false;
            foreach ($prefectures as $slug => $name) {
                if ($name === $prefFilter) {
                    $prefectures = [$slug => $name];
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $this->error("都道府県 '{$prefFilter}' が見つかりません。");
                return self::FAILURE;
            }
        }

        $total = count($prefectures);
        $completed = 0;
        $errors = 0;

        foreach ($prefectures as $slug => $name) {
            $num = $completed + $errors + 1;
            $prefix = "[{$num}/{$total}] {$name}";

            if ($isDryRun) {
                $this->info("{$prefix}: プロンプト確認モード");
                $this->line($this->buildUserPrompt($name));
                $completed++;
                continue;
            }

            $this->info("{$prefix}: API呼び出し中...");

            try {
                $spots = $this->callClaudeApi($apiKey, $name);
            } catch (\Throwable $e) {
                $this->error("{$prefix}: API呼び出しエラー - {$e->getMessage()}");
                Log::error('SeedTouringSpots: API呼び出し失敗', [
                    'prefecture' => $name,
                    'error' => $e->getMessage(),
                ]);
                $errors++;
                continue;
            }

            if ($spots === null) {
                $this->error("{$prefix}: レスポンスのパースに失敗");
                $errors++;
                continue;
            }

            $savedCount = 0;
            foreach ($spots as $spot) {
                $spotSlug = Str::slug($spot['slug'] ?? $spot['name']);

                TouringSpot::updateOrCreate(
                    ['slug' => $spotSlug],
                    [
                        'prefecture' => $name,
                        'name' => $spot['name'],
                        'lat' => $spot['lat'],
                        'lng' => $spot['lng'],
                        'description' => $spot['description'] ?? null,
                        'content' => $spot['content'] ?? null,
                        'highlights' => $spot['highlights'] ?? null,
                        'recommended_season' => $spot['recommended_season'] ?? null,
                        'difficulty' => $spot['difficulty'] ?? null,
                        'distance_km' => $spot['distance_km'] ?? null,
                    ],
                );
                $savedCount++;
            }

            $this->info("{$prefix}: {$savedCount}件のスポットを保存しました");
            $completed++;

            if ($num < $total) {
                sleep(self::SLEEP_SECONDS);
            }
        }

        $this->newLine();
        $this->info("完了: {$completed}都道府県を処理しました（エラー: {$errors}件）");

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function buildUserPrompt(string $prefecture): string
    {
        return <<<PROMPT
{$prefecture}のバイクツーリングで人気のスポットを5〜8箇所、JSON配列で出力してください。

条件：
- ライダーに人気の実在する場所のみ（架空の場所は不可）
- 景色が良い道路・峠・海沿いルート・山岳ルートを優先
- 道の駅やSA/PA単体ではなく、ツーリングルートや絶景ポイントを選ぶ
- 各スポットの緯度経度は正確に

以下のJSON配列形式で出力してください。他の文字は一切含めず、JSONのみを出力してください。

[
  {
    "name": "スポット名（日本語）",
    "slug": "spot-name-in-english",
    "lat": 43.0621,
    "lng": 141.3544,
    "description": "（80〜120字）このスポットの概要。どんなルート・景色が楽しめるか。",
    "content": "（300〜500字）詳細な紹介文。アクセス方法、見どころ、注意点、おすすめの走り方を含む。HTMLタグなし。",
    "highlights": ["見どころ1", "見どころ2", "見どころ3"],
    "recommended_season": "おすすめの時期（例: 4月〜10月）",
    "difficulty": "初級 or 中級 or 上級",
    "distance_km": 走行距離の目安（整数、nullでも可）
  }
]
PROMPT;
    }

    private function callClaudeApi(string $apiKey, string $prefecture): ?array
    {
        $systemPrompt = <<<'PROMPT'
あなたは日本全国のツーリングロードに詳しいベテランライダーです。
実在する場所のみを、正確な緯度経度とともに紹介してください。
出力はJSON配列のみ。説明文やマークダウンは不要です。
PROMPT;

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->timeout(60)->post(self::API_ENDPOINT, [
            'model' => self::MODEL_ID,
            'max_tokens' => self::MAX_TOKENS,
            'system' => $systemPrompt,
            'messages' => [
                ['role' => 'user', 'content' => $this->buildUserPrompt($prefecture)],
            ],
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException("API error: {$response->status()} - {$response->body()}");
        }

        $body = $response->json();
        $text = $body['content'][0]['text'] ?? null;

        if (!$text) {
            Log::error('SeedTouringSpots: API応答にtextなし', ['body' => $body]);
            return null;
        }

        return $this->parseJsonResponse($text);
    }

    private function parseJsonResponse(string $text): ?array
    {
        $text = preg_replace('/^```(?:json)?\s*/i', '', trim($text));
        $text = preg_replace('/\s*```$/i', '', $text);

        $decoded = json_decode(trim($text), true);

        if (!is_array($decoded)) {
            Log::error('SeedTouringSpots: JSONパース失敗', ['raw' => $text]);
            return null;
        }

        // 配列の配列であることを確認
        foreach ($decoded as $item) {
            if (!is_array($item) || empty($item['name']) || !isset($item['lat'], $item['lng'])) {
                Log::error('SeedTouringSpots: 不正なスポットデータ', ['item' => $item]);
                return null;
            }
        }

        return $decoded;
    }
}
