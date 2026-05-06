<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BikeModel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * bike_modelsテーブルのslugがNULLのレコードにスラッグを自動生成する
 *
 * Anthropic Claude APIを使用してカタカナ/日本語モデル名を正式な英語名スラッグに変換
 */
final class GenerateBikeModelSlugs extends Command
{
    protected $signature = 'bike-model:generate-slugs
                            {--dry-run : 実行せず変換結果を一覧表示}
                            {--execute : DB更新+SQL出力を実行}';

    protected $description = 'Claude APIを使用してslug未設定のbike_modelsに英語名スラッグを自動生成';

    private const API_ENDPOINT = 'https://api.anthropic.com/v1/messages';
    private const MODEL_ID = 'claude-sonnet-4-20250514';
    private const BATCH_SIZE = 50;
    private const SLEEP_SECONDS = 2;
    private const MAX_TOKENS = 4096;

    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $isExecute = (bool) $this->option('execute');

        if (!$isDryRun && !$isExecute) {
            $this->error('--dry-run または --execute を指定してください。');
            $this->line('  --dry-run  : 変換結果を一覧表示（DB変更なし）');
            $this->line('  --execute  : DB更新 + SQLファイル出力');
            return self::FAILURE;
        }

        $apiKey = config('services.anthropic.api_key');
        if (!$apiKey) {
            $this->error('ANTHROPIC_API_KEY が .env に設定されていません。');
            return self::FAILURE;
        }

        $models = BikeModel::with('manufacturer')
            ->whereNull('slug')
            ->orderBy('manufacturer_id')
            ->orderBy('name')
            ->get();

        if ($models->isEmpty()) {
            $this->info('slugがNULLのレコードはありません。');
            return self::SUCCESS;
        }

        $this->info("対象レコード: {$models->count()}件（slugがNULL）");
        $this->newLine();

        // バッチに分割して処理
        $batches = $models->chunk(self::BATCH_SIZE);
        $totalBatches = $batches->count();

        $generated = 0;
        $skipped = 0;
        $duplicates = 0;
        $errors = 0;
        $sqlStatements = [];
        $skippedItems = [];

        foreach ($batches as $batchIndex => $batch) {
            $batchNum = $batchIndex + 1;
            $this->info("━━━ バッチ {$batchNum}/{$totalBatches} ({$batch->count()}件) ━━━");

            // バッチ内のモデル情報をまとめる
            $items = $batch->map(fn ($m) => [
                'id' => $m->id,
                'name' => $m->name,
                'manufacturer' => $m->manufacturer?->name ?? '不明',
                'manufacturer_id' => $m->manufacturer_id,
            ])->values()->all();

            // Claude APIで変換
            $slugMap = $this->callClaudeApi($apiKey, $items);

            if ($slugMap === null) {
                $this->error("  バッチ {$batchNum}: API呼び出し失敗 → スキップ");
                $errors += $batch->count();
                if ($batchNum < $totalBatches) {
                    sleep(self::SLEEP_SECONDS);
                }
                continue;
            }

            // 結果を処理
            foreach ($batch as $model) {
                $idKey = (string) $model->id;
                $slug = $slugMap[$idKey] ?? $slugMap[$model->id] ?? null;

                if ($slug === null || $slug === '' || $slug === 'null' || $slug === 'skip') {
                    $skipped++;
                    $skippedItems[] = [
                        'id' => $model->id,
                        'name' => $model->name,
                        'manufacturer' => $model->manufacturer?->name ?? '不明',
                        'reason' => 'API変換不可',
                    ];
                    continue;
                }

                // スラッグを正規化
                $slug = Str::slug($slug);

                if (empty($slug) || mb_strlen($slug) <= 2) {
                    $skipped++;
                    $skippedItems[] = [
                        'id' => $model->id,
                        'name' => $model->name,
                        'manufacturer' => $model->manufacturer?->name ?? '不明',
                        'reason' => "スラッグ短すぎ: {$slug}",
                    ];
                    continue;
                }

                // 同一メーカー内での重複チェック
                $exists = BikeModel::where('slug', $slug)
                    ->where('manufacturer_id', $model->manufacturer_id)
                    ->where('id', '!=', $model->id)
                    ->exists();

                if ($exists) {
                    $duplicates++;
                    $skippedItems[] = [
                        'id' => $model->id,
                        'name' => $model->name,
                        'manufacturer' => $model->manufacturer?->name ?? '不明',
                        'reason' => "既存と重複: {$slug}",
                    ];
                    continue;
                }

                // 今回のバッチ内での重複チェック（同一メーカーで同じslugが複数生成された場合）
                $duplicateInBatch = collect($sqlStatements)->contains(function ($stmt) use ($slug, $model) {
                    return $stmt['slug'] === $slug && $stmt['manufacturer_id'] === $model->manufacturer_id;
                });

                if ($duplicateInBatch) {
                    $duplicates++;
                    $skippedItems[] = [
                        'id' => $model->id,
                        'name' => $model->name,
                        'manufacturer' => $model->manufacturer?->name ?? '不明',
                        'reason' => "バッチ内重複: {$slug}",
                    ];
                    continue;
                }

                $mfrName = $model->manufacturer?->name ?? '??';

                if ($isDryRun) {
                    $this->line("  [{$mfrName}] {$model->name} → <fg=green>{$slug}</>");
                }

                if ($isExecute) {
                    $model->update(['slug' => $slug]);
                    $this->line("  [{$mfrName}] {$model->name} → <fg=green>{$slug}</>");
                }

                $sqlStatements[] = [
                    'id' => $model->id,
                    'slug' => $slug,
                    'manufacturer_id' => $model->manufacturer_id,
                    'name' => $model->name,
                ];
                $generated++;
            }

            // バッチ間のスリープ
            if ($batchNum < $totalBatches) {
                $this->line("  (次バッチまで" . self::SLEEP_SECONDS . "秒待機...)");
                sleep(self::SLEEP_SECONDS);
            }
        }

        // SQLファイル出力
        $sqlPath = storage_path('app/slug-migration.sql');
        $this->writeSqlFile($sqlPath, $sqlStatements);

        // サマリー
        $this->newLine();
        $this->info('━━━ 結果サマリー ━━━');
        $this->info("  生成成功: {$generated}件");
        $this->info("  スキップ（変換不可）: {$skipped}件");
        $this->info("  スキップ（重複）: {$duplicates}件");
        $this->info("  エラー: {$errors}件");
        $this->info("  SQL出力: {$sqlPath}");

        // スキップ一覧
        if (count($skippedItems) > 0) {
            $this->newLine();
            $this->warn('━━━ スキップ一覧 ━━━');
            foreach ($skippedItems as $item) {
                $this->line("  ID:{$item['id']} [{$item['manufacturer']}] {$item['name']} - {$item['reason']}");
            }
        }

        if ($isDryRun) {
            $this->newLine();
            $this->comment('実行するには --execute を指定してください:');
            $this->comment('  php artisan bike-model:generate-slugs --execute');
        }

        return self::SUCCESS;
    }

    /**
     * Claude APIを呼び出してモデル名→スラッグの変換を取得
     *
     * @param array<array{id: int, name: string, manufacturer: string}> $items
     * @return array<string, string>|null  id => slug のマップ
     */
    private function callClaudeApi(string $apiKey, array $items): ?array
    {
        $itemList = collect($items)->map(function ($item) {
            return "{$item['id']}: [{$item['manufacturer']}] {$item['name']}";
        })->implode("\n");

        $systemPrompt = <<<'PROMPT'
あなたはバイク（オートバイ）の車種名に精通したエキスパートです。
日本語のバイクモデル名を、正式な英語名のスラッグ（小文字、ハイフン区切り、記号なし）に変換してください。

ルール：
1. カタカナの車種名は正式な英語名に変換する（音訳ではなく正式名称）
   例: アフリカツイン → africa-twin, シャドウ → shadow, ゼファー → zephyr
2. 数字はそのまま保持する
   例: ニンジャ 250 → ninja-250, ゼファー1100 → zephyr-1100
3. 型式や世代を示す括弧内の情報もスラッグに含める
   例: フォルツァ(mf13) → forza-mf13
4. 「スーパー」「カスタム」「クラシック」等の修飾語も英語化する
   例: スーパーカブ110 → super-cub-110, バルカンクラシック400 → vulcan-classic-400
5. 漢字のみで英語名が不明な場合は "null" と回答する
6. 1つのIDにつき1つのスラッグを返す
7. スラッグは全て小文字、単語間はハイフンで区切る

JSON形式で返答してください。キーはID（文字列）、値はスラッグです。
例: {"123": "africa-twin", "456": "shadow-400-custom", "789": "null"}
PROMPT;

        $userMessage = "以下のバイクモデル名をスラッグに変換してください:\n\n{$itemList}";

        try {
            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->timeout(60)->post(self::API_ENDPOINT, [
                'model' => self::MODEL_ID,
                'max_tokens' => self::MAX_TOKENS,
                'system' => $systemPrompt,
                'messages' => [
                    ['role' => 'user', 'content' => $userMessage],
                ],
            ]);

            if (!$response->successful()) {
                Log::error('GenerateBikeModelSlugs: API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                $this->error("  API HTTP {$response->status()}: {$response->body()}");
                return null;
            }

            $body = $response->json();
            $text = $body['content'][0]['text'] ?? '';

            // JSONを抽出（```json ... ``` で囲まれている場合も対応）
            if (preg_match('/\{[\s\S]*\}/u', $text, $matches)) {
                $json = json_decode($matches[0], true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
                    return $json;
                }
            }

            Log::error('GenerateBikeModelSlugs: JSON parse failed', ['text' => $text]);
            $this->error('  JSONパースに失敗しました');
            return null;

        } catch (\Throwable $e) {
            Log::error('GenerateBikeModelSlugs: Exception', ['error' => $e->getMessage()]);
            $this->error("  例外: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * SQLファイルを出力
     *
     * @param array<array{id: int, slug: string, name: string}> $statements
     */
    private function writeSqlFile(string $path, array $statements): void
    {
        $lines = [
            '-- bike_models slug migration',
            '-- Generated: ' . now()->format('Y-m-d H:i:s'),
            '-- Records: ' . count($statements),
            '',
            'START TRANSACTION;',
            '',
        ];

        foreach ($statements as $stmt) {
            $escapedSlug = addslashes($stmt['slug']);
            $lines[] = "UPDATE bike_models SET slug = '{$escapedSlug}' WHERE id = {$stmt['id']}; -- {$stmt['name']}";
        }

        $lines[] = '';
        $lines[] = 'COMMIT;';
        $lines[] = '';

        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($path, implode("\n", $lines));
    }
}
