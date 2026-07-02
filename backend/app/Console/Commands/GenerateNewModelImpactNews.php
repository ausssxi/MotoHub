<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BikeModel;
use App\Models\BikeNews;
use App\Models\Listing;
use App\Models\MarketPriceLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class GenerateNewModelImpactNews extends Command
{
    protected $signature = 'news:generate-new-model-impact
                            {--publish : 即時公開（デフォルトはdraft）}
                            {--force : 重複チェックをスキップ}
                            {--dry-run : APIを呼ばずデータ確認のみ}
                            {--limit=3 : 1回の最大生成記事数}';

    protected $description = '新車発表ニュースから中古相場への影響分析記事を自動生成';

    private const API_ENDPOINT = 'https://api.anthropic.com/v1/messages';

    private const MAX_TOKENS = 2500;

    private const SLEEP_SECONDS = 3;

    private const KEYWORDS = [
        '新型', 'モデルチェンジ', 'フルモデルチェンジ', 'マイナーチェンジ',
        '新モデル', 'ニューモデル', '新車',
        '2025年モデル', '2026年モデル', '2027年モデル',
        '発表', '発売',
    ];

    private const EXCLUDE_KEYWORDS = [
        'ヘルメット', 'インカム', 'ジャケット', 'グローブ', 'ブーツ',
        'ウェア', '用品', 'パーツ', 'アクセサリー', 'レプリカ',
        '自転車', 'サイクル', 'MTB', 'ロードバイク',
        'リコール', 'リコール対応',
    ];

    public function handle(): int
    {
        $apiKey = config('services.anthropic.api_key');
        $isDryRun = $this->option('dry-run');
        $limit = (int) $this->option('limit');

        if (! $isDryRun && ! $apiKey) {
            $this->error('ANTHROPIC_API_KEY が .env に設定されていません。');

            return self::FAILURE;
        }

        $this->info('新車関連ニュースを検索中...');

        // 過去7日間のRSSニュースから新車関連を抽出
        $candidates = $this->findNewModelNews();

        if ($candidates->isEmpty()) {
            $this->info('新車関連ニュースは見つかりませんでした。');

            return self::SUCCESS;
        }

        $this->info($candidates->count().'件の候補ニュースを検出');

        $generated = 0;
        $processedModelNames = []; // 同一cron実行内の車種重複防止

        foreach ($candidates as $sourceNews) {
            if ($generated >= $limit) {
                $this->info("生成上限（{$limit}件）に達しました。");
                break;
            }

            $this->info("処理中: {$sourceNews->title}");

            // 重複チェック: 元ニュースのタイトルを含む分析記事が既にあるか
            if (! $this->option('force')) {
                $titleFragment = mb_substr($sourceNews->title, 0, 30);
                if (BikeNews::where('source', 'MotoHub')
                    ->where('content', 'like', "%{$titleFragment}%")
                    ->exists()
                ) {
                    $this->warn('  → 既に分析記事生成済み。スキップ');

                    continue;
                }
            }

            // 車種名を抽出
            $modelNames = $this->extractModelNames($apiKey, $sourceNews->title, $isDryRun);

            if (empty($modelNames)) {
                $this->warn('  → 車種名を抽出できませんでした。スキップ');

                continue;
            }

            $this->info('  車種名候補: '.implode(', ', $modelNames));

            // bike_modelsから該当車種を検索
            $bikeModel = $this->findBikeModel($modelNames);

            if (! $bikeModel) {
                $this->warn("  → 車種マッチなし: {$sourceNews->title}");

                continue;
            }

            $this->info("  マッチ車種: {$bikeModel->name}（ID: {$bikeModel->id}）");

            // 同一cron実行内の車種重複チェック
            if (in_array($bikeModel->name, $processedModelNames, true)) {
                $this->warn("  → {$bikeModel->name}: 同一実行内で処理済み、スキップ");

                continue;
            }

            // 過去7日以内に同車種の記事がないかチェック
            if (! $this->option('force')) {
                $recentExists = BikeNews::where('source', 'MotoHub')
                    ->where('title', 'like', "%{$bikeModel->name}%")
                    ->where('created_at', '>=', now()->subDays(7))
                    ->exists();

                if ($recentExists) {
                    $this->warn("  → {$bikeModel->name}: 7日以内に記事済み、スキップ");

                    continue;
                }
            }

            // 中古相場データを取得
            $marketData = $this->collectMarketData($bikeModel);

            if ($marketData['listing_count'] === 0) {
                $this->warn('  → 掲載台数0件。スキップ');

                continue;
            }

            if ($isDryRun) {
                $this->printDryRun($sourceNews, $bikeModel, $marketData);
                $processedModelNames[] = $bikeModel->name;
                $generated++;

                continue;
            }

            // Claude APIで分析記事を生成
            try {
                $result = $this->callClaudeApi($apiKey, $sourceNews, $bikeModel, $marketData);
            } catch (\Throwable $e) {
                $this->error("  API呼び出しエラー: {$e->getMessage()}");
                Log::error('GenerateNewModelImpactNews: API呼び出し失敗', [
                    'news_id' => $sourceNews->id,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }

            if ($result === null) {
                $this->error('  レスポンスパース失敗。スキップ');

                continue;
            }

            // 保存
            $publishedAt = $this->option('publish') ? now() : null;

            $news = BikeNews::create([
                'title' => $result['title'],
                'url' => '',
                'source' => 'MotoHub',
                'content' => $result['body'],
                'thumbnail_url' => $this->resolveImageUrl($bikeModel),
                'published_at' => $publishedAt,
                'bike_model_id' => $bikeModel->id,
                'manufacturer_id' => $bikeModel->manufacturer_id,
                'is_featured' => true,
            ]);

            $news->update(['url' => route('news.show', $news->id)]);

            $status = $publishedAt ? '公開' : '下書き';
            $this->info("  記事生成完了（{$status}）: {$result['title']}");
            $processedModelNames[] = $bikeModel->name;
            $generated++;

            if ($generated < $limit) {
                sleep(self::SLEEP_SECONDS);
            }
        }

        $this->info("完了: {$generated}件の記事を生成しました。");

        return self::SUCCESS;
    }

    private function findNewModelNews()
    {
        $query = BikeNews::where('source', '!=', 'MotoHub')
            ->where('published_at', '>=', now()->subDays(7))
            ->orderByDesc('published_at');

        // キーワードフィルタ
        $query->where(function ($q) {
            foreach (self::KEYWORDS as $i => $keyword) {
                $method = $i === 0 ? 'where' : 'orWhere';
                $q->{$method}('title', 'like', "%{$keyword}%");
            }
        });

        // 除外キーワード
        foreach (self::EXCLUDE_KEYWORDS as $exclude) {
            $query->where('title', 'not like', "%{$exclude}%");
        }

        // タイトル重複除去（同じタイトルは最新1件のみ）
        return $query->get()->unique('title')->values();
    }

    /**
     * Claude APIでニュースタイトルから車種名を抽出
     */
    private function extractModelNames(string $apiKey, string $title, bool $isDryRun): array
    {
        if ($isDryRun) {
            // dry-runではタイトルから簡易抽出（APIを呼ばない）
            return $this->simpleExtract($title);
        }

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->timeout(30)->post(self::API_ENDPOINT, [
            'model' => config('services.anthropic.model'),
            'max_tokens' => 200,
            'system' => 'バイクのニュースタイトルから車種名（モデル名）を抽出してください。JSON配列のみ返してください。車種名が見つからない場合は空配列[]を返してください。',
            'messages' => [
                ['role' => 'user', 'content' => "タイトル: {$title}"],
            ],
        ]);

        if (! $response->successful()) {
            Log::error('extractModelNames: API error', ['status' => $response->status()]);

            return $this->simpleExtract($title);
        }

        $text = $response->json('content.0.text');
        if (! $text) {
            return $this->simpleExtract($title);
        }

        $clean = preg_replace('/```json|```/', '', $text);
        $names = json_decode(trim($clean), true);

        return is_array($names) ? $names : $this->simpleExtract($title);
    }

    /**
     * タイトルから簡易的に車種名候補を抽出（フォールバック用）
     */
    private function simpleExtract(string $title): array
    {
        // 「新型XXX」「XXX 発表」などから車種名部分を取得
        $names = [];

        if (preg_match('/新型\s*([A-Za-z0-9\-]+(?:\s*[A-Za-z0-9\-]+)*)/u', $title, $m)) {
            $names[] = $m[1];
        }
        if (preg_match('/([A-Za-z][A-Za-z0-9\-]{2,}(?:\s*[0-9]+[A-Za-z]*)?)/u', $title, $m)) {
            $names[] = $m[1];
        }

        return array_unique(array_filter($names));
    }

    /**
     * 車種名候補からbike_modelsを検索
     */
    private function findBikeModel(array $modelNames): ?BikeModel
    {
        $candidates = collect();

        foreach ($modelNames as $name) {
            $name = trim($name);
            if (mb_strlen($name) <= 2) {
                continue;
            }

            $found = BikeModel::with('manufacturer')
                ->where('name', 'like', "%{$name}%")
                ->where(fn ($q) => $q->whereNotNull('displacement')->where('displacement', '>', 0))
                ->withCount(['listings' => fn ($q) => $q->where('is_sold_out', false)])
                ->having('listings_count', '>=', 3)
                ->get();

            $candidates = $candidates->merge($found);
        }

        if ($candidates->isEmpty()) {
            return null;
        }

        // 掲載台数最多のモデルを採用
        return $candidates->sortByDesc('listings_count')->first();
    }

    /**
     * 該当車種の中古相場データを収集
     */
    private function collectMarketData(BikeModel $model): array
    {
        // 現在の掲載データ
        $currentListings = Listing::where('listings.bike_model_id', $model->id)
            ->where('listings.is_sold_out', false);

        $listingCount = $currentListings->count();
        $avgPrice = (int) (clone $currentListings)->whereNotNull('listings.total_price')->avg('listings.total_price');
        $minPrice = (int) (clone $currentListings)->whereNotNull('listings.total_price')->min('listings.total_price');
        $maxPrice = (int) (clone $currentListings)->whereNotNull('listings.total_price')->max('listings.total_price');

        // MarketPriceLogから3ヶ月分の推移
        $logs = MarketPriceLog::where('bike_model_id', $model->id)
            ->where('recorded_at', '>=', now()->subMonths(3))
            ->orderBy('recorded_at')
            ->get();

        $threeMonthsAgoPrice = $logs->first()?->avg_price ?? 0;
        $latestLogPrice = $logs->last()?->avg_price ?? $avgPrice;
        $priceChangeRate = $threeMonthsAgoPrice > 0
            ? round(($latestLogPrice - $threeMonthsAgoPrice) / $threeMonthsAgoPrice * 100, 1)
            : 0;

        return [
            'listing_count' => $listingCount,
            'avg_price' => $avgPrice,
            'min_price' => $minPrice,
            'max_price' => $maxPrice,
            'three_months_ago_price' => $threeMonthsAgoPrice,
            'price_change_rate' => $priceChangeRate,
            'monthly_prices' => $logs->groupBy(fn ($l) => $l->recorded_at->format('Y-m'))
                ->map(fn ($group) => (int) $group->avg('avg_price'))
                ->toArray(),
        ];
    }

    private function callClaudeApi(
        string $apiKey,
        BikeNews $sourceNews,
        BikeModel $bikeModel,
        array $marketData,
    ): ?array {
        $modelName = $bikeModel->name;
        $makerName = $bikeModel->manufacturer?->name ?? '不明';
        $modelUrl = route('bikes.model_detail.fallback', $bikeModel->id);
        $newsExcerpt = mb_substr(strip_tags($sourceNews->content ?? $sourceNews->title), 0, 200);

        $avgMan = round($marketData['avg_price'] / 10000, 1);
        $minMan = round($marketData['min_price'] / 10000, 1);
        $maxMan = round($marketData['max_price'] / 10000, 1);
        $threeMonthAgoMan = $marketData['three_months_ago_price'] > 0
            ? round($marketData['three_months_ago_price'] / 10000, 1)
            : '不明';

        $systemPrompt = <<<'PROMPT'
あなたはMotoHubの中古バイク市場アナリストです。
新車発表ニュースと旧型の中古相場データを元に、中古市場への影響分析記事を書いてください。

ルール：
- 文体: ですます調、バイク初心者にもわかりやすく
- HTMLタグで出力（h3, p, strong, a を使用）
- 本文は500〜800文字程度
- JSONで以下の形式のみ返してください。他のテキストは不要です:
{"title": "記事タイトル", "body": "記事本文HTML", "meta_description": "120文字以内の要約"}
PROMPT;

        $userPrompt = <<<PROMPT
以下の新車発表ニュースと、旧型の中古相場データを元に、中古市場への影響分析記事を書いてください。

## 新車ニュース
タイトル: {$sourceNews->title}
概要: {$newsExcerpt}

## 旧型の中古相場データ
車種名: {$modelName}（{$makerName}）
車種ページ: {$modelUrl}
現在の掲載台数: {$marketData['listing_count']}台
現在の平均価格: {$avgMan}万円
3ヶ月前の平均価格: {$threeMonthAgoMan}万円
価格変動: {$marketData['price_change_rate']}%
最安値: {$minMan}万円
最高値: {$maxMan}万円

## 記事の要件
- タイトル: {$modelName}の新型発表で旧型中古相場はどう動く？｜データで予測
- 本文構成:
  1. 新型モデルの概要（ニュースから要約、2〜3行）
  2. 旧型の現在の中古相場（データを元に）
  3. 過去のモデルチェンジ時のパターン（一般論として）
     - 発表直後: 旧型が3〜5%下落する傾向
     - 新型発売後: さらに5〜10%下落
     - 半年後: 下げ止まり、旧型ファンの需要で安定
  4. 今が買い時か？（結論と推薦）
- 車種名「{$modelName}」には <a href="{$modelUrl}">{$modelName}</a> のリンクを含める
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

        $result = $response->json();
        $text = $result['content'][0]['text'] ?? null;

        if (! $text) {
            $this->error('  Claude APIからのレスポンスが空です');
            $this->error('  Response: '.json_encode($result));
            Log::error('GenerateNewModelImpactNews: API応答にtextなし', ['body' => $result]);

            return null;
        }

        $clean = preg_replace('/```json|```/', '', $text);
        $data = json_decode(trim($clean), true);

        if (! $data || ! isset($data['title'])) {
            $this->error('  レスポンスのJSONパースに失敗');
            $this->error('  Text: '.$text);
            Log::error('GenerateNewModelImpactNews: JSONパース失敗', ['raw' => $text]);

            return null;
        }

        return $data;
    }

    private function resolveImageUrl(?BikeModel $model): ?string
    {
        if (! $model) {
            return null;
        }

        if (is_array($model->local_image_path) && ! empty($model->local_image_path)) {
            return asset('storage/'.ltrim($model->local_image_path[0], '/'));
        }

        $imageUrl = $model->image_url;
        if ($imageUrl) {
            return $imageUrl;
        }

        if ($model->manufacturer) {
            if ($model->manufacturer->local_logo_path) {
                return asset('storage/'.ltrim($model->manufacturer->local_logo_path, '/'));
            }
            if ($model->manufacturer->logo_url) {
                return $model->manufacturer->logo_url;
            }
        }

        return null;
    }

    private function printDryRun(BikeNews $sourceNews, BikeModel $bikeModel, array $marketData): void
    {
        $this->info('  --- Dry Run ---');
        $this->line("  元ニュース: {$sourceNews->title}");
        $this->line("  マッチ車種: {$bikeModel->name}（{$bikeModel->manufacturer?->name}）");
        $this->line("  掲載台数: {$marketData['listing_count']}台");
        $this->line('  平均価格: '.number_format($marketData['avg_price']).'円');
        $this->line('  最安値: '.number_format($marketData['min_price']).'円');
        $this->line('  最高値: '.number_format($marketData['max_price']).'円');
        $this->line("  3ヶ月価格変動: {$marketData['price_change_rate']}%");
    }
}
