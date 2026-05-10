<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Abraham\TwitterOAuth\TwitterOAuth;
use App\Models\BikeModel;
use App\Models\Listing;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

final class PostRankingImage extends Command
{
    protected $signature = 'x:post-ranking-image
                            {--type=weekly-sales : weekly-sales|bargains|prefecture|budget|fast-selling|price-up|displacement}
                            {--prefecture= : 都道府県名（type=prefectureの場合、未指定なら自動ローテーション）}
                            {--displacement= : 排気量帯（125/250/400/大型）}
                            {--dry-run : 投稿せず内容をプレビュー}';

    protected $description = 'ランキング画像付きでX(Twitter)に投稿';

    private const DISCOUNT_THRESHOLD = 0.8;

    private const PREFECTURES = [
        '北海道', '青森県', '岩手県', '宮城県', '秋田県', '山形県', '福島県',
        '茨城県', '栃木県', '群馬県', '埼玉県', '千葉県', '東京都', '神奈川県',
        '新潟県', '富山県', '石川県', '福井県', '山梨県', '長野県',
        '岐阜県', '静岡県', '愛知県', '三重県',
        '滋賀県', '京都府', '大阪府', '兵庫県', '奈良県', '和歌山県',
        '鳥取県', '島根県', '岡山県', '広島県', '山口県',
        '徳島県', '香川県', '愛媛県', '高知県',
        '福岡県', '佐賀県', '長崎県', '熊本県', '大分県', '宮崎県', '鹿児島県', '沖縄県',
    ];

    public function handle(): int
    {
        $type = $this->option('type');
        $dryRun = (bool) $this->option('dry-run');

        if (! in_array($type, ['weekly-sales', 'bargains', 'prefecture', 'budget', 'fast-selling', 'price-up', 'displacement'], true)) {
            $this->error("無効なタイプ: {$type}");

            return self::FAILURE;
        }

        // 都道府県の解決
        $prefecture = $this->resolvePrefecture($type);
        if ($type === 'prefecture' && $prefecture === null) {
            return self::FAILURE;
        }

        // 画像の存在確認 → なければ自動生成
        $imagePath = $this->ensureImage($type, $prefecture);
        if ($imagePath === null) {
            $this->error('画像の生成に失敗しました。');

            return self::FAILURE;
        }

        // 投稿テキスト生成
        $text = $this->buildTweetText($type, $prefecture);
        if ($text === null) {
            $this->error('投稿テキストの生成に失敗しました（データ不足）。');

            return self::FAILURE;
        }

        if ($dryRun) {
            $this->warn('[DRY-RUN] 投稿はしません。');
            $this->newLine();
            $this->line('--- テキスト ---');
            $this->line($text);
            $this->line('文字数: ' . mb_strlen($text));
            $this->newLine();
            $this->line('--- 画像 ---');
            $this->info(Storage::disk('public')->path($imagePath));

            return self::SUCCESS;
        }

        // 投稿実行
        return $this->postTweet($text, $imagePath);
    }

    private function resolvePrefecture(string $type): ?string
    {
        if ($type !== 'prefecture') {
            return null;
        }

        $pref = $this->option('prefecture');
        if ($pref) {
            if (! in_array($pref, self::PREFECTURES, true)) {
                $this->error("無効な都道府県: {$pref}");

                return null;
            }

            return $pref;
        }

        // 自動ローテーション: 週番号 % 47
        $weekNumber = (int) now()->format('W');
        $index = $weekNumber % count(self::PREFECTURES);

        $pref = self::PREFECTURES[$index];
        $this->info("自動選択: {$pref}（週番号{$weekNumber}）");

        return $pref;
    }

    private function ensureImage(string $type, ?string $prefecture): ?string
    {
        $date = now()->format('Y-m-d');
        $displacement = $this->resolveDisplacement($type);

        if ($type === 'displacement' && $displacement) {
            $path = "x-images/displacement-{$displacement}-{$date}.png";
        } else {
            $path = "x-images/{$type}-{$date}.png";
        }

        if (Storage::disk('public')->exists($path)) {
            $this->info("既存画像を使用: {$path}");

            return $path;
        }

        $this->info('画像が未生成のため、生成コマンドを実行します...');

        $args = ['--type' => $type];
        if ($prefecture) {
            $args['--prefecture'] = $prefecture;
        }
        if ($displacement) {
            $args['--displacement'] = $displacement;
        }

        $exitCode = Artisan::call('x:generate-ranking-image', $args);

        if ($exitCode !== 0) {
            return null;
        }

        return Storage::disk('public')->exists($path) ? $path : null;
    }

    private function resolveDisplacement(string $type): ?string
    {
        if ($type !== 'displacement') {
            return null;
        }

        $displacement = $this->option('displacement');
        if ($displacement) {
            return $displacement;
        }

        // 自動ローテーション: 週番号 % 4
        $keys = ['125', '250', '400', '大型'];
        $weekNumber = (int) now()->format('W');
        $displacement = $keys[$weekNumber % count($keys)];
        $this->info("排気量帯を自動選択: {$displacement}（週番号{$weekNumber}）");

        return $displacement;
    }

    // ─── Tweet Text ─────────────────────────────────────────────────

    private function buildTweetText(string $type, ?string $prefecture): ?string
    {
        return match ($type) {
            'weekly-sales' => $this->buildWeeklySalesText(),
            'bargains' => $this->buildBargainsText(),
            'prefecture' => $this->buildPrefectureText($prefecture),
            'budget' => $this->buildBudgetText(),
            'fast-selling' => $this->buildFastSellingText(),
            'price-up' => $this->buildPriceUpText(),
            'displacement' => $this->buildDisplacementText($this->resolveDisplacement($type)),
        };
    }

    private function buildWeeklySalesText(): ?string
    {
        $rankings = Listing::where('is_sold_out', true)
            ->where('updated_at', '>=', now()->subWeek())
            ->whereNotNull('bike_model_id')
            ->select('bike_model_id', DB::raw('COUNT(*) as sold_count'))
            ->groupBy('bike_model_id')
            ->orderByDesc('sold_count')
            ->limit(5)
            ->get();

        if ($rankings->count() < 3) {
            return null;
        }

        $models = BikeModel::with('manufacturer')
            ->whereIn('id', $rankings->pluck('bike_model_id'))
            ->get()
            ->keyBy('id');

        $lines = ["先週最も売れたバイクTOP5\n"];
        foreach ($rankings->values()->take(3) as $i => $row) {
            $model = $models->get($row->bike_model_id);
            $name = $model?->displayLabel() ?? '不明';
            $rank = $i + 1;
            $lines[] = "{$rank}位 {$name}（{$row->sold_count}台）";
        }

        $lines[] = "\nデータ元: MotoHub";
        $lines[] = '#バイク #中古バイク #バイク好きと繋がりたい';

        return implode("\n", $lines);
    }

    private function buildBargainsText(): ?string
    {
        $excludedModelIds = BikeModel::where('name', '他車種')->pluck('id');

        $modelAverages = Listing::where('is_sold_out', false)
            ->whereNotNull('bike_model_id')
            ->whereNotNull('total_price')
            ->where('total_price', '>', 0)
            ->whereNotIn('bike_model_id', $excludedModelIds)
            ->select('bike_model_id', DB::raw('AVG(total_price) as avg_price'), DB::raw('COUNT(*) as cnt'))
            ->groupBy('bike_model_id')
            ->having('cnt', '>=', 3)
            ->pluck('avg_price', 'bike_model_id');

        $listings = Listing::with(['bikeModel.manufacturer'])
            ->where('is_sold_out', false)
            ->whereNotNull('bike_model_id')
            ->whereNotNull('total_price')
            ->where('total_price', '>', 0)
            ->whereIn('bike_model_id', $modelAverages->keys())
            ->orderByDesc('created_at')
            ->limit(500)
            ->get();

        $bargains = [];
        foreach ($listings as $listing) {
            $avgPrice = (float) $modelAverages[$listing->bike_model_id];
            if ($avgPrice <= 0) {
                continue;
            }

            if ($listing->total_price < ($avgPrice * self::DISCOUNT_THRESHOLD)) {
                $percentOff = (int) round((($avgPrice - $listing->total_price) / $avgPrice) * 100);
                $bargains[] = [
                    'name' => $listing->bikeModel?->displayLabel() ?? '不明',
                    'percent_off' => $percentOff,
                    'price' => $listing->total_price,
                ];
            }
        }

        usort($bargains, fn ($a, $b) => $b['percent_off'] <=> $a['percent_off']);

        if (count($bargains) < 3) {
            return null;
        }

        $lines = ["今週のお買い得バイクTOP3\n"];
        foreach (array_slice($bargains, 0, 3) as $i => $item) {
            $rank = $i + 1;
            $priceMan = number_format((int) round($item['price'] / 10000));
            $lines[] = "{$rank}位 {$item['name']} {$item['percent_off']}%OFF（{$priceMan}万円）";
        }

        $lines[] = "\nデータ元: MotoHub";
        $lines[] = '#バイク #中古バイク #お買い得';

        return implode("\n", $lines);
    }

    private function buildPrefectureText(?string $prefecture): ?string
    {
        if (! $prefecture) {
            return null;
        }

        $rankings = Listing::where('is_sold_out', false)
            ->whereNotNull('bike_model_id')
            ->join('shops', 'listings.shop_id', '=', 'shops.id')
            ->where('shops.prefecture', $prefecture)
            ->select('listings.bike_model_id', DB::raw('COUNT(*) as listing_count'))
            ->groupBy('listings.bike_model_id')
            ->orderByDesc('listing_count')
            ->limit(5)
            ->get();

        if ($rankings->count() < 3) {
            return null;
        }

        $models = BikeModel::with('manufacturer')
            ->whereIn('id', $rankings->pluck('bike_model_id'))
            ->get()
            ->keyBy('id');

        $lines = ["{$prefecture}で人気のバイクTOP5\n"];
        foreach ($rankings->values()->take(3) as $i => $row) {
            $model = $models->get($row->bike_model_id);
            $name = $model?->displayLabel() ?? '不明';
            $rank = $i + 1;
            $lines[] = "{$rank}位 {$name}（{$row->listing_count}台）";
        }

        // 都道府県名からハッシュタグ用テキスト（「都」「府」「県」を除去）
        $prefTag = preg_replace('/[都府県]$/u', '', $prefecture);

        $lines[] = "\nデータ元: MotoHub";
        $lines[] = "#バイク #{$prefTag} #中古バイク";

        return implode("\n", $lines);
    }

    private function buildBudgetText(): ?string
    {
        $rankings = Listing::where('is_sold_out', false)
            ->whereNotNull('bike_model_id')
            ->whereNotNull('total_price')
            ->where('total_price', '<=', 100000)
            ->select('bike_model_id', DB::raw('COUNT(*) as stock_count'), DB::raw('MIN(total_price) as min_price'))
            ->groupBy('bike_model_id')
            ->orderByDesc('stock_count')
            ->limit(5)
            ->get();

        if ($rankings->count() < 3) {
            return null;
        }

        $models = BikeModel::with('manufacturer')
            ->whereIn('id', $rankings->pluck('bike_model_id'))
            ->get()
            ->keyBy('id');

        $lines = ["10万円以下で買えるバイクTOP5\n"];
        foreach ($rankings->values()->take(3) as $i => $row) {
            $model = $models->get($row->bike_model_id);
            $name = $model?->displayLabel() ?? '不明';
            $rank = $i + 1;
            $minPrice = number_format((int) $row->min_price);
            $lines[] = "{$rank}位 {$name}（¥{$minPrice}〜）";
        }

        $lines[] = "\nデータ元: MotoHub";
        $lines[] = '#バイク #中古バイク #格安バイク #10万円以下';

        return implode("\n", $lines);
    }

    private function buildFastSellingText(): ?string
    {
        $rankings = Listing::where('is_sold_out', true)
            ->where('updated_at', '>=', now()->subDays(30))
            ->whereNotNull('bike_model_id')
            ->selectRaw('bike_model_id, AVG(DATEDIFF(updated_at, created_at)) as avg_days, COUNT(*) as sold_count')
            ->groupBy('bike_model_id')
            ->having('sold_count', '>=', 3)
            ->havingRaw('AVG(DATEDIFF(updated_at, created_at)) BETWEEN 1 AND 365')
            ->orderBy('avg_days')
            ->limit(5)
            ->get();

        if ($rankings->count() < 3) {
            return null;
        }

        $models = BikeModel::with('manufacturer')
            ->whereIn('id', $rankings->pluck('bike_model_id'))
            ->get()
            ->keyBy('id');

        $lines = ["即売れバイクTOP5\n"];
        foreach ($rankings->values()->take(3) as $i => $row) {
            $model = $models->get($row->bike_model_id);
            $name = $model?->displayLabel() ?? '不明';
            $rank = $i + 1;
            $avgDays = (int) round($row->avg_days);
            $lines[] = "{$rank}位 {$name}（平均{$avgDays}日で売却）";
        }

        $lines[] = "\nデータ元: MotoHub";
        $lines[] = '#バイク #中古バイク #即売れ #バイク相場';

        return implode("\n", $lines);
    }

    private function buildPriceUpText(): ?string
    {
        $thisMonth = Listing::where('is_sold_out', false)
            ->whereNotNull('total_price')
            ->where('total_price', '>', 0)
            ->whereNotNull('bike_model_id')
            ->select('bike_model_id', DB::raw('AVG(total_price) as avg_price'), DB::raw('COUNT(*) as cnt'))
            ->groupBy('bike_model_id')
            ->having('cnt', '>=', 5)
            ->get()
            ->keyBy('bike_model_id');

        $lastMonth = Listing::where('is_sold_out', true)
            ->whereBetween('updated_at', [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()])
            ->whereNotNull('total_price')
            ->where('total_price', '>', 0)
            ->whereNotNull('bike_model_id')
            ->select('bike_model_id', DB::raw('AVG(total_price) as avg_price'))
            ->groupBy('bike_model_id')
            ->get()
            ->keyBy('bike_model_id');

        $priceChanges = [];
        foreach ($thisMonth as $modelId => $current) {
            $previous = $lastMonth->get($modelId);
            if (! $previous || $previous->avg_price <= 0) {
                continue;
            }

            $changePercent = (($current->avg_price - $previous->avg_price) / $previous->avg_price) * 100;
            if ($changePercent > 0) {
                $priceChanges[] = [
                    'bike_model_id' => $modelId,
                    'change_percent' => $changePercent,
                    'current_avg' => $current->avg_price,
                ];
            }
        }

        usort($priceChanges, fn ($a, $b) => $b['change_percent'] <=> $a['change_percent']);

        if (count($priceChanges) < 3) {
            return null;
        }

        $topChanges = array_slice($priceChanges, 0, 5);
        $models = BikeModel::with('manufacturer')
            ->whereIn('id', array_column($topChanges, 'bike_model_id'))
            ->get()
            ->keyBy('id');

        $lines = ["今月値上がりしたバイクTOP5\n"];
        foreach (array_values(array_slice($topChanges, 0, 3)) as $i => $item) {
            $model = $models->get($item['bike_model_id']);
            $name = $model?->displayLabel() ?? '不明';
            $rank = $i + 1;
            $percent = number_format($item['change_percent'], 1);
            $lines[] = "{$rank}位 {$name}（+{$percent}%）";
        }

        $lines[] = "\nデータ元: MotoHub";
        $lines[] = '#バイク #中古バイク #値上がり #バイク相場';

        return implode("\n", $lines);
    }

    private function buildDisplacementText(?string $displacement): ?string
    {
        if (! $displacement) {
            return null;
        }

        $ranges = [
            '125' => [0, 125],
            '250' => [126, 250],
            '400' => [251, 400],
            '大型' => [401, null],
        ];

        if (! isset($ranges[$displacement])) {
            return null;
        }

        [$min, $max] = $ranges[$displacement];

        $rankings = Listing::where('is_sold_out', false)
            ->whereNotNull('bike_model_id')
            ->displacementBetween($min, $max)
            ->select('bike_model_id', DB::raw('COUNT(*) as stock_count'))
            ->groupBy('bike_model_id')
            ->orderByDesc('stock_count')
            ->limit(5)
            ->get();

        if ($rankings->count() < 3) {
            return null;
        }

        $models = BikeModel::with('manufacturer')
            ->whereIn('id', $rankings->pluck('bike_model_id'))
            ->get()
            ->keyBy('id');

        $label = $displacement === '大型' ? '大型（401cc〜）' : "{$displacement}ccクラス";

        $lines = ["{$label} 人気バイクTOP5\n"];
        foreach ($rankings->values()->take(3) as $i => $row) {
            $model = $models->get($row->bike_model_id);
            $name = $model?->displayLabel() ?? '不明';
            $rank = $i + 1;
            $lines[] = "{$rank}位 {$name}（{$row->stock_count}台）";
        }

        $ccTag = $displacement === '大型' ? '大型バイク' : "{$displacement}cc";

        $lines[] = "\nデータ元: MotoHub";
        $lines[] = "#バイク #{$ccTag} #中古バイク";

        return implode("\n", $lines);
    }

    // ─── Twitter API ────────────────────────────────────────────────

    private function postTweet(string $text, string $imagePath): int
    {
        try {
            $connection = new TwitterOAuth(
                config('services.twitter.consumer_key'),
                config('services.twitter.consumer_secret'),
                config('services.twitter.access_token'),
                config('services.twitter.access_token_secret')
            );
        } catch (\Exception $e) {
            $this->error('Twitter認証エラー: ' . $e->getMessage());
            Log::error('PostRankingImage: Twitter auth failed', ['error' => $e->getMessage()]);

            return self::FAILURE;
        }

        // 画像アップロード（v1.1、最大3回リトライ）
        $mediaId = $this->uploadMedia($connection, $imagePath);

        // ツイート投稿（v2）
        $connection->setApiVersion('2');

        $payload = ['text' => $text];
        if ($mediaId) {
            $payload['media'] = ['media_ids' => [$mediaId]];
        }

        $result = $connection->post('tweets', $payload);

        if ($connection->getLastHttpCode() === 201) {
            $tweetId = $result->data->id ?? 'unknown';
            $this->info("投稿成功（Tweet ID: {$tweetId}）");
            Log::info('PostRankingImage: 投稿成功', [
                'tweet_id' => $tweetId,
                'type' => $this->option('type'),
            ]);

            return self::SUCCESS;
        }

        $httpCode = $connection->getLastHttpCode();

        if ($httpCode === 429) {
            $this->warn('レート制限に達しました。次回のcron実行に任せます。');
            Log::warning('PostRankingImage: Rate limited', ['code' => $httpCode]);

            return self::FAILURE;
        }

        $this->error("投稿失敗（HTTP {$httpCode}）");
        Log::error('PostRankingImage: Tweet failed', [
            'code' => $httpCode,
            'response' => (array) $result,
        ]);

        return self::FAILURE;
    }

    private function uploadMedia(TwitterOAuth $connection, string $imagePath): ?string
    {
        $fullPath = Storage::disk('public')->path($imagePath);

        $maxRetries = 3;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $connection->setApiVersion('1.1');
                $media = $connection->upload('media/upload', ['media' => $fullPath]);

                if (isset($media->media_id_string)) {
                    $this->info("画像アップロード成功（media_id: {$media->media_id_string}）");

                    return $media->media_id_string;
                }

                $this->warn("画像アップロード: media_id未取得（試行 {$attempt}/{$maxRetries}）");
            } catch (\Exception $e) {
                $this->warn("画像アップロード失敗（試行 {$attempt}/{$maxRetries}）: {$e->getMessage()}");

                if ($attempt < $maxRetries) {
                    sleep(2);
                }
            }
        }

        $this->error('画像アップロードを3回試行しましたが失敗しました。テキストのみで投稿します。');
        Log::error('PostRankingImage: Media upload failed after 3 retries', ['path' => $fullPath]);

        return null;
    }
}
