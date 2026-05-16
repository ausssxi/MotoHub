<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BikeModel;
use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Models\Listing;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Geometry\Factories\LineFactory;
use Intervention\Image\ImageManager;
use Intervention\Image\Typography\FontFactory;

final class GenerateMarketReport extends Command
{
    protected $signature = 'blog:generate-market-report
        {--month= : 対象月 (1-12)}
        {--year= : 対象年}
        {--user-id=2 : 投稿者のユーザーID}
        {--publish : 公開状態で投稿（デフォルトは下書き）}
        {--dry-run : 生成結果をターミナルに出力するのみ}';

    protected $description = '月次中古バイク相場レポートを生成してブログ記事として保存';

    private const MIN_LISTINGS_FOR_RANKING = 5;

    public function handle(): int
    {
        $year = (int) ($this->option('year') ?: now()->subMonth()->year);
        $month = (int) ($this->option('month') ?: now()->subMonth()->month);

        $targetMonth = Carbon::create($year, $month, 1)->startOfMonth();
        $targetEnd = $targetMonth->copy()->endOfMonth();
        $prevMonth = $targetMonth->copy()->subMonth();
        $prevEnd = $prevMonth->copy()->endOfMonth();
        $threeMonthsAgo = $targetMonth->copy()->subMonths(3);

        $this->info("対象月: {$targetMonth->format('Y年m月')}");
        $this->info("前月: {$prevMonth->format('Y年m月')}");

        // データ集計
        $summary = $this->computeSummary($targetMonth, $targetEnd, $prevMonth, $prevEnd);
        $priceUp = $this->computePriceChange($targetMonth, $targetEnd, $prevMonth, $prevEnd, 'up');
        $priceDown = $this->computePriceChange($targetMonth, $targetEnd, $prevMonth, $prevEnd, 'down');
        $displacement = $this->computeDisplacementTrends($targetMonth, $targetEnd, $prevMonth, $prevEnd, $threeMonthsAgo);
        $popular = $this->computePopularModels($targetMonth, $targetEnd);
        $categoryTrends = $this->computeCategoryTrends($targetMonth, $targetEnd, $prevMonth, $prevEnd);

        // Markdown生成
        $markdown = $this->buildMarkdown($targetMonth, $summary, $priceUp, $priceDown, $displacement, $popular, $categoryTrends);

        if ($this->option('dry-run')) {
            $this->line('');
            $this->line($markdown);
            $this->info("\n--- dry-run 完了 (保存されていません) ---");
            return self::SUCCESS;
        }

        // ブログ記事として保存
        $this->saveAsBlogPost($targetMonth, $markdown, $summary);

        return self::SUCCESS;
    }

    private function computeSummary(Carbon $start, Carbon $end, Carbon $prevStart, Carbon $prevEnd): array
    {
        // 当月の掲載台数（月末時点のアクティブ）
        $activeCount = Listing::where('is_sold_out', false)
            ->where('created_at', '<=', $end)
            ->count();

        // 新規掲載数
        $newListings = Listing::whereBetween('created_at', [$start, $end])->count();

        // 販売台数（updated_atが対象月内で売り切れになったもの）
        $soldCount = Listing::where('is_sold_out', true)
            ->whereBetween('updated_at', [$start, $end])
            ->where('created_at', '<=', DB::raw('updated_at - INTERVAL 1 DAY'))
            ->count();

        // 当月平均価格
        $avgPrice = Listing::where('is_sold_out', false)
            ->where('total_price', '>', 0)
            ->where('created_at', '<=', $end)
            ->avg('total_price');

        // 前月平均価格
        $prevAvgPrice = Listing::where('is_sold_out', false)
            ->where('total_price', '>', 0)
            ->where('created_at', '<=', $prevEnd)
            ->avg('total_price');

        $priceChange = ($prevAvgPrice && $prevAvgPrice > 0)
            ? round(($avgPrice - $prevAvgPrice) / $prevAvgPrice * 100, 1)
            : null;

        return [
            'active_count' => $activeCount,
            'new_listings' => $newListings,
            'sold_count' => $soldCount,
            'avg_price' => $avgPrice ? round($avgPrice / 10000, 1) : null,
            'prev_avg_price' => $prevAvgPrice ? round($prevAvgPrice / 10000, 1) : null,
            'price_change' => $priceChange,
        ];
    }

    private function computePriceChange(Carbon $start, Carbon $end, Carbon $prevStart, Carbon $prevEnd, string $direction): array
    {
        // 「その他」車種を除外するためjoin
        $excludeOther = fn ($query) => $query
            ->join('bike_models', 'bike_models.id', '=', 'listings.bike_model_id')
            ->where('bike_models.name', 'NOT LIKE', '%その他%');

        // 当月の車種別平均価格（5台以上）
        // 当月末時点の掲載中 = created_at <= 当月末 AND (is_sold_out = false OR updated_at > 当月末)
        $currentPrices = Listing::where('listings.total_price', '>', 0)
            ->whereNotNull('listings.bike_model_id')
            ->where('listings.created_at', '<=', $end)
            ->where(function ($q) use ($end) {
                $q->where('listings.is_sold_out', false)
                  ->orWhere('listings.updated_at', '>', $end);
            })
            ->tap($excludeOther)
            ->select('listings.bike_model_id', DB::raw('AVG(listings.total_price) as avg_price'), DB::raw('COUNT(*) as cnt'))
            ->groupBy('listings.bike_model_id')
            ->having('cnt', '>=', self::MIN_LISTINGS_FOR_RANKING)
            ->get()
            ->keyBy('bike_model_id');

        // 前月末時点の掲載中 = created_at <= 前月末 AND (is_sold_out = false OR updated_at > 前月末)
        $prevPrices = Listing::where('listings.total_price', '>', 0)
            ->whereNotNull('listings.bike_model_id')
            ->where('listings.created_at', '<=', $prevEnd)
            ->where(function ($q) use ($prevEnd) {
                $q->where('listings.is_sold_out', false)
                  ->orWhere('listings.updated_at', '>', $prevEnd);
            })
            ->tap($excludeOther)
            ->select('listings.bike_model_id', DB::raw('AVG(listings.total_price) as avg_price'), DB::raw('COUNT(*) as cnt'))
            ->groupBy('listings.bike_model_id')
            ->having('cnt', '>=', self::MIN_LISTINGS_FOR_RANKING)
            ->get()
            ->keyBy('bike_model_id');

        $changes = [];
        foreach ($currentPrices as $modelId => $current) {
            $prev = $prevPrices->get($modelId);
            if (!$prev || $prev->avg_price <= 0) continue;

            $changePercent = round(($current->avg_price - $prev->avg_price) / $prev->avg_price * 100, 1);
            $changes[] = [
                'bike_model_id' => $modelId,
                'current_avg' => round($current->avg_price / 10000, 1),
                'prev_avg' => round($prev->avg_price / 10000, 1),
                'change_percent' => $changePercent,
                'count' => (int) $current->cnt,
            ];
        }

        // ソート
        usort($changes, function ($a, $b) use ($direction) {
            return $direction === 'up'
                ? $b['change_percent'] <=> $a['change_percent']
                : $a['change_percent'] <=> $b['change_percent'];
        });

        $top10 = array_slice($changes, 0, 10);

        // モデル名を取得
        $modelIds = array_column($top10, 'bike_model_id');
        $models = BikeModel::with('manufacturer')->whereIn('id', $modelIds)->get()->keyBy('id');

        return array_map(function ($item) use ($models) {
            $model = $models->get($item['bike_model_id']);
            $item['name'] = $model
                ? trim(($model->manufacturer?->name ?? '') . ' ' . $model->name)
                : '不明';
            return $item;
        }, $top10);
    }

    private function computeDisplacementTrends(Carbon $start, Carbon $end, Carbon $prevStart, Carbon $prevEnd, Carbon $threeMonthsAgo): array
    {
        $ranges = [
            '50cc以下' => [0, 50],
            '51〜125cc' => [51, 125],
            '126〜250cc' => [126, 250],
            '251〜400cc' => [251, 400],
            '401〜750cc' => [401, 750],
            '751cc以上' => [751, 99999],
        ];

        $results = [];
        foreach ($ranges as $label => [$min, $max]) {
            // asOf時点で掲載中だった車両 = created_at <= asOf AND (is_sold_out = false OR updated_at > asOf)
            $baseQuery = fn (Carbon $asOf) => Listing::where('total_price', '>', 0)
                ->where('displacement', '>=', $min)
                ->where('displacement', '<=', $max)
                ->where('created_at', '<=', $asOf)
                ->where(function ($q) use ($asOf) {
                    $q->where('is_sold_out', false)
                      ->orWhere('updated_at', '>', $asOf);
                });

            $currentAvg = $baseQuery($end)->avg('total_price');
            $prevAvg = $baseQuery($prevEnd)->avg('total_price');
            $threeMonthAvg = $baseQuery($threeMonthsAgo->copy()->endOfMonth())->avg('total_price');

            $monthChange = ($prevAvg && $prevAvg > 0)
                ? round(($currentAvg - $prevAvg) / $prevAvg * 100, 1)
                : null;
            $threeMonthChange = ($threeMonthAvg && $threeMonthAvg > 0)
                ? round(($currentAvg - $threeMonthAvg) / $threeMonthAvg * 100, 1)
                : null;

            $results[] = [
                'label' => $label,
                'avg_price' => $currentAvg ? round($currentAvg / 10000, 1) : null,
                'month_change' => $monthChange,
                'three_month_change' => $threeMonthChange,
            ];
        }

        return $results;
    }

    private function computePopularModels(Carbon $start, Carbon $end): array
    {
        $rankings = Listing::cappedSold($start, $end)
            ->whereNotNull('bike_model_id')
            ->select('bike_model_id', DB::raw('COUNT(*) as sold_count'))
            ->groupBy('bike_model_id')
            ->orderByDesc('sold_count')
            ->limit(10)
            ->get();

        $modelIds = $rankings->pluck('bike_model_id')->toArray();
        $models = BikeModel::with('manufacturer')->whereIn('id', $modelIds)->get()->keyBy('id');

        // 平均価格も取得
        $avgPrices = Listing::where('is_sold_out', true)
            ->whereBetween('updated_at', [$start, $end])
            ->where('total_price', '>', 0)
            ->whereIn('bike_model_id', $modelIds)
            ->select('bike_model_id', DB::raw('AVG(total_price) as avg_price'))
            ->groupBy('bike_model_id')
            ->pluck('avg_price', 'bike_model_id');

        return $rankings->map(function ($row) use ($models, $avgPrices) {
            $model = $models->get($row->bike_model_id);
            return [
                'name' => $model
                    ? trim(($model->manufacturer?->name ?? '') . ' ' . $model->name)
                    : '不明',
                'sold_count' => (int) $row->sold_count,
                'avg_price' => ($avgPrices->get($row->bike_model_id, 0) > 0)
                    ? round($avgPrices->get($row->bike_model_id) / 10000, 1)
                    : null,
            ];
        })->toArray();
    }

    private function computeCategoryTrends(Carbon $start, Carbon $end, Carbon $prevStart, Carbon $prevEnd): array
    {
        $categories = [
            4 => 'ネイキッド',
            2 => 'スクーター（〜125cc）',
            3 => 'スクーター（126cc〜）',
            10 => 'アメリカン',
            8 => 'スポーツ/レプリカ',
            11 => 'オフロード',
            6 => 'ツアラー',
            14 => 'アドベンチャー',
            21 => 'クラシック',
            1 => 'ミニバイク',
        ];

        $results = [];
        foreach ($categories as $id => $label) {
            // asOf時点で掲載中だった車両 = created_at <= asOf AND (is_sold_out = false OR updated_at > asOf)
            $baseQuery = fn (Carbon $asOf) => Listing::where('total_price', '>', 0)
                ->where('category_id', $id)
                ->where('created_at', '<=', $asOf)
                ->where(function ($q) use ($asOf) {
                    $q->where('is_sold_out', false)
                      ->orWhere('updated_at', '>', $asOf);
                });

            $currentCount = $baseQuery($end)->count();
            $currentAvg = $baseQuery($end)->avg('total_price');
            $prevAvg = $baseQuery($prevEnd)->avg('total_price');

            $change = ($prevAvg && $prevAvg > 0)
                ? round(($currentAvg - $prevAvg) / $prevAvg * 100, 1)
                : null;

            $results[] = [
                'label' => $label,
                'count' => $currentCount,
                'avg_price' => $currentAvg ? round($currentAvg / 10000, 1) : null,
                'change' => $change,
            ];
        }

        return $results;
    }

    private function buildMarkdown(Carbon $targetMonth, array $summary, array $priceUp, array $priceDown, array $displacement, array $popular, array $categoryTrends): string
    {
        $monthLabel = $targetMonth->format('Y年n月');
        $md = '';

        // サマリー
        $md .= "## {$monthLabel} 中古バイク市場サマリー\n\n";
        $md .= "| 指標 | 数値 |\n";
        $md .= "|------|------|\n";
        $md .= "| 掲載台数 | " . number_format($summary['active_count']) . "台 |\n";
        $md .= "| 新規掲載数 | " . number_format($summary['new_listings']) . "台 |\n";
        $md .= "| 販売台数 | " . number_format($summary['sold_count']) . "台 |\n";
        $md .= "| 全体平均価格 | " . ($summary['avg_price'] ?? '-') . "万円 |\n";
        if ($summary['price_change'] !== null) {
            $sign = $summary['price_change'] >= 0 ? '+' : '';
            $md .= "| 前月比 | {$sign}{$summary['price_change']}% |\n";
        }
        $md .= "\n";

        // 今月のポイント
        $md .= "### 今月のポイント\n\n";
        $md .= $this->generateHighlights($targetMonth, $summary, $priceUp, $priceDown);
        $md .= "\n\n";

        // 値上がりランキング
        $md .= "## 値上がり車種ランキング TOP10\n\n";
        if (!empty($priceUp)) {
            $md .= "※ 今月・前月ともに掲載" . self::MIN_LISTINGS_FOR_RANKING . "台以上の車種が対象\n\n";
            $md .= "| 順位 | 車種名 | 今月平均 | 前月平均 | 変動率 |\n";
            $md .= "|:----:|--------|:--------:|:--------:|:------:|\n";
            foreach ($priceUp as $i => $item) {
                $md .= "| " . ($i + 1) . " | {$item['name']} | {$item['current_avg']}万円 | {$item['prev_avg']}万円 | +{$item['change_percent']}% |\n";
            }
        } else {
            $md .= "該当データなし\n";
        }
        $md .= "\n";

        // 値下がりランキング
        $md .= "## 値下がり車種ランキング TOP10\n\n";
        if (!empty($priceDown)) {
            $md .= "※ 今月・前月ともに掲載" . self::MIN_LISTINGS_FOR_RANKING . "台以上の車種が対象\n\n";
            $md .= "| 順位 | 車種名 | 今月平均 | 前月平均 | 変動率 |\n";
            $md .= "|:----:|--------|:--------:|:--------:|:------:|\n";
            foreach ($priceDown as $i => $item) {
                $md .= "| " . ($i + 1) . " | {$item['name']} | {$item['current_avg']}万円 | {$item['prev_avg']}万円 | {$item['change_percent']}% |\n";
            }
        } else {
            $md .= "該当データなし\n";
        }
        $md .= "\n";

        // 排気量別推移
        $md .= "## 排気量別 平均価格推移\n\n";
        $md .= "| クラス | 平均価格 | 前月比 | 3ヶ月前比 |\n";
        $md .= "|--------|:--------:|:------:|:---------:|\n";
        foreach ($displacement as $row) {
            $mChange = $row['month_change'] !== null ? ($row['month_change'] >= 0 ? '+' : '') . $row['month_change'] . '%' : '-';
            $tChange = $row['three_month_change'] !== null ? ($row['three_month_change'] >= 0 ? '+' : '') . $row['three_month_change'] . '%' : '-';
            $md .= "| {$row['label']} | " . ($row['avg_price'] ?? '-') . "万円 | {$mChange} | {$tChange} |\n";
        }
        $md .= "\n";

        // 人気車種TOP10
        $md .= "## 人気車種 TOP10（販売台数ベース）\n\n";
        if (!empty($popular)) {
            $md .= "| 順位 | 車種名 | 販売台数 | 平均価格 |\n";
            $md .= "|:----:|--------|:--------:|:--------:|\n";
            foreach ($popular as $i => $item) {
                $price = $item['avg_price'] ? $item['avg_price'] . '万円' : '-';
                $md .= "| " . ($i + 1) . " | {$item['name']} | {$item['sold_count']}台 | {$price} |\n";
            }
        } else {
            $md .= "該当データなし\n";
        }
        $md .= "\n";

        // カテゴリ別トレンド
        $md .= "## カテゴリ別トレンド\n\n";
        $md .= "| カテゴリ | 掲載台数 | 平均価格 | 前月比 |\n";
        $md .= "|----------|:--------:|:--------:|:------:|\n";
        foreach ($categoryTrends as $row) {
            $change = $row['change'] !== null ? ($row['change'] >= 0 ? '+' : '') . $row['change'] . '%' : '-';
            $md .= "| {$row['label']} | " . number_format($row['count']) . "台 | " . ($row['avg_price'] ?? '-') . "万円 | {$change} |\n";
        }
        $md .= "\n";

        // 注目ポイント
        $md .= "## 注目ポイント\n\n";
        $md .= $this->getSeasonalComment($targetMonth);
        $md .= "\n\n";

        // フッター
        $md .= "---\n\n";
        $md .= "※ 本レポートはMotoHubに掲載中の中古バイクデータを基に自動集計しています。\n";
        $md .= "※ 掲載" . self::MIN_LISTINGS_FOR_RANKING . "台未満の車種はランキング対象外です。\n";
        $md .= "※ 販売台数は掲載終了（売り切れ）となった車両数を基に算出しています。\n";

        return $md;
    }

    private function generateHighlights(Carbon $month, array $summary, array $priceUp, array $priceDown): string
    {
        $lines = [];

        if ($summary['price_change'] !== null) {
            $direction = $summary['price_change'] >= 0 ? '上昇' : '下落';
            $lines[] = "- 中古バイク全体の平均価格は前月比{$summary['price_change']}%の{$direction}（{$summary['avg_price']}万円）";
        }

        if (!empty($priceUp)) {
            $lines[] = "- 値上がり率トップは「{$priceUp[0]['name']}」（+{$priceUp[0]['change_percent']}%）";
        }

        if (!empty($priceDown)) {
            $lines[] = "- 値下がり率トップは「{$priceDown[0]['name']}」（{$priceDown[0]['change_percent']}%）";
        }

        if ($summary['sold_count'] > 0) {
            $lines[] = "- 今月の販売台数は" . number_format($summary['sold_count']) . "台（新規掲載: " . number_format($summary['new_listings']) . "台）";
        }

        return implode("\n", array_slice($lines, 0, 3));
    }

    private function getSeasonalComment(Carbon $month): string
    {
        return match ((int) $month->month) {
            1 => "1月は年末年始の影響で市場の動きが鈍化する傾向があります。一方で、春に向けた早期購入を狙うライダーにとっては競争が少なく、良い車両を見つけやすい時期でもあります。",
            2 => "2月は春のバイクシーズンに向けた準備期間。3月以降の需要増を見越した価格上昇が一部車種で始まる傾向があります。",
            3 => "3月はバイクシーズン開幕を控え、需要が本格的に高まる時期です。人気車種は価格が上昇傾向にあり、早めの購入検討がおすすめです。新生活に合わせた通勤・通学用バイクの需要も増加します。",
            4 => "4月はバイクシーズン本番。ツーリング需要の高まりから中型〜大型クラスの価格が上昇しやすい時期です。新生活需要で原付・小型二輪も活発に動きます。",
            5 => "5月はGWのツーリング需要でバイク市場が最も活況を呈する時期の一つ。アドベンチャーやツアラーなど長距離向けモデルの価格が特に堅調です。",
            6 => "6月は梅雨入りに伴い需要がやや落ち着く時期。価格交渉がしやすくなる傾向があり、夏のツーリングに向けた購入には良いタイミングです。",
            7 => "7月は夏休みに向けた需要回復期。オフロードやアドベンチャーモデルへの関心が高まります。梅雨明け後は一気に需要が増える傾向があります。",
            8 => "8月は夏のツーリングシーズン真っ只中。大型バイクの需要が高い一方、酷暑の影響で小排気量の通勤需要はやや落ち着きます。",
            9 => "9月は秋のツーリングシーズンに向けた需要期。涼しくなり始めバイクに乗りやすい季節の到来で、幅広い排気量帯で動きがあります。",
            10 => "10月は秋のベストシーズン。紅葉ツーリング需要で市場は活発。特にツアラーやネイキッドの人気が高まります。冬前の駆け込み購入も見られます。",
            11 => "11月は冬の到来を前に在庫整理セールが増える時期。バイクショップの決算期とも重なり、お買い得な車両が出やすい傾向があります。",
            12 => "12月は年末に向けてバイク市場は落ち着きを見せます。冬場は価格が下がりやすく、春に向けた購入を検討するには良い時期です。年末セールも狙い目です。",
        };
    }

    private function saveAsBlogPost(Carbon $targetMonth, string $markdown, array $summary): void
    {
        $monthStr = $targetMonth->format('Y-m');
        $monthLabel = $targetMonth->format('Y年n月');
        $slug = "market-report-{$monthStr}";
        $userId = (int) $this->option('user-id');

        // サムネイル画像生成
        $imagePath = $this->generateThumbnail($targetMonth, $summary);

        // 既存記事チェック
        $existing = BlogPost::where('slug', $slug)->first();
        if ($existing) {
            $this->warn("slug '{$slug}' の記事は既に存在します。上書きします。");
            $existing->update([
                'body' => $markdown,
                'eyecatch_image' => $imagePath,
                'og_image' => $imagePath,
                'meta_description' => "{$monthLabel}の中古バイク相場レポート。値上がり・値下がり車種ランキング、排気量別の価格推移、人気車種TOP10を掲載。",
            ]);
            $this->info("既存記事を更新しました: {$existing->title}");
            return;
        }

        $title = "{$monthLabel} 中古バイク相場レポート｜値上がり・値下がり車種ランキング";
        $status = $this->option('publish') ? 'published' : 'draft';
        $publishedAt = $this->option('publish') ? $targetMonth->copy()->addMonth()->startOfMonth() : null;

        $post = BlogPost::create([
            'author_id' => $userId,
            'title' => $title,
            'slug' => $slug,
            'body' => $markdown,
            'eyecatch_image' => $imagePath,
            'og_image' => $imagePath,
            'status' => $status,
            'published_at' => $publishedAt,
            'meta_title' => "{$monthLabel} 中古バイク相場レポート | MotoHub",
            'meta_description' => "{$monthLabel}の中古バイク相場レポート。値上がり・値下がり車種ランキング、排気量別の価格推移、人気車種TOP10を掲載。",
        ]);

        // タグ付与
        $tags = collect(['相場レポート', '市場動向'])->map(function ($name) {
            return BlogTag::firstOrCreate(['name' => $name]);
        });
        $post->tags()->sync($tags->pluck('id'));

        $this->info("ブログ記事を{$status}として保存しました。");
        $this->info("  タイトル: {$title}");
        $this->info("  スラッグ: {$slug}");
        $this->info("  ステータス: {$status}");
        $this->info("  画像: {$imagePath}");
        if ($publishedAt) {
            $this->info("  公開予定日: {$publishedAt->format('Y-m-d')}");
        }
    }

    private function generateThumbnail(Carbon $targetMonth, array $summary): string
    {
        $width = 1200;
        $height = 630;
        $fontPath = storage_path('app/fonts/NotoSansJP-Bold.ttf');
        $hasFont = file_exists($fontPath);

        $manager = new ImageManager(new GdDriver());
        $image = $manager->create($width, $height);

        // 背景グラデーション（ダークブルー #0f172a → #1e3a5f）
        for ($y = 0; $y < $height; $y++) {
            $ratio = $y / $height;
            $r = (int) (0x0f + (0x1e - 0x0f) * $ratio);
            $g = (int) (0x17 + (0x3a - 0x17) * $ratio);
            $b = (int) (0x2a + (0x5f - 0x2a) * $ratio);
            $hex = sprintf('#%02x%02x%02x', $r, $g, $b);
            $image->drawLine(function (LineFactory $line) use ($y, $width, $hex) {
                $line->from(0, $y);
                $line->to($width, $y);
                $line->color($hex);
            });
        }

        // 上部アクセントライン（オレンジ）
        for ($i = 0; $i < 5; $i++) {
            $image->drawLine(function (LineFactory $line) use ($i, $width) {
                $line->from(0, $i);
                $line->to($width, $i);
                $line->color('#e67e22');
            });
        }

        if ($hasFont) {
            // 上部: 「MotoHub 月次レポート」
            $image->text('MotoHub 月次レポート', (int) ($width / 2), 120, function (FontFactory $font) use ($fontPath) {
                $font->filename($fontPath);
                $font->size(28);
                $font->color('rgba(255, 255, 255, 0.7)');
                $font->align('center');
                $font->valign('middle');
            });

            // 中央: 年月
            $monthText = $targetMonth->format('Y年n月');
            $image->text($monthText, (int) ($width / 2), 220, function (FontFactory $font) use ($fontPath) {
                $font->filename($fontPath);
                $font->size(80);
                $font->color('#ffffff');
                $font->align('center');
                $font->valign('middle');
            });

            // 中央下: 「中古バイク相場レポート」
            $image->text('中古バイク相場レポート', (int) ($width / 2), 310, function (FontFactory $font) use ($fontPath) {
                $font->filename($fontPath);
                $font->size(40);
                $font->color('#ffffff');
                $font->align('center');
                $font->valign('middle');
            });

            // 下部: データカード3つ
            $cards = [];
            if ($summary['avg_price'] !== null) {
                $cards[] = ['label' => '平均価格', 'value' => $summary['avg_price'] . '万円'];
            }
            if ($summary['price_change'] !== null) {
                $sign = $summary['price_change'] >= 0 ? '+' : '';
                $cards[] = [
                    'label' => '前月比',
                    'value' => $sign . $summary['price_change'] . '%',
                    'color' => $summary['price_change'] >= 0 ? '#4ade80' : '#f87171',
                ];
            }
            if ($summary['sold_count'] > 0) {
                $cards[] = ['label' => '販売台数', 'value' => number_format($summary['sold_count']) . '台'];
            }

            $cardCount = count($cards);
            if ($cardCount > 0) {
                $cardWidth = 280;
                $totalWidth = $cardCount * $cardWidth + ($cardCount - 1) * 40;
                $startX = (int) (($width - $totalWidth) / 2) + (int) ($cardWidth / 2);

                foreach ($cards as $i => $card) {
                    $cx = $startX + $i * ($cardWidth + 40);
                    $cy = 480;

                    // カード背景（白）
                    for ($ry = $cy - 55; $ry <= $cy + 55; $ry++) {
                        $image->drawLine(function (LineFactory $line) use ($cx, $ry, $cardWidth) {
                            $line->from($cx - (int) ($cardWidth / 2), $ry);
                            $line->to($cx + (int) ($cardWidth / 2), $ry);
                            $line->color('rgba(255, 255, 255, 0.95)');
                        });
                    }

                    // ラベル（グレー）
                    $image->text($card['label'], $cx, $cy - 25, function (FontFactory $font) use ($fontPath) {
                        $font->filename($fontPath);
                        $font->size(18);
                        $font->color('#6b7280');
                        $font->align('center');
                        $font->valign('middle');
                    });

                    // 値（黒、前月比のみ緑/赤）
                    $valueColor = $card['color'] ?? '#1f2937';
                    $image->text($card['value'], $cx, $cy + 20, function (FontFactory $font) use ($fontPath, $valueColor) {
                        $font->filename($fontPath);
                        $font->size(32);
                        $font->color($valueColor);
                        $font->align('center');
                        $font->valign('middle');
                    });
                }
            }

            // 右下ブランディング
            $image->text('MotoHub', $width - 60, $height - 30, function (FontFactory $font) use ($fontPath) {
                $font->filename($fontPath);
                $font->size(18);
                $font->color('rgba(255, 255, 255, 0.4)');
                $font->align('right');
                $font->valign('bottom');
            });
        }

        // 保存
        $filename = "market-report-{$targetMonth->format('Y-m')}.png";
        $savePath = "blog/thumbnails/{$filename}";
        Storage::disk('public')->makeDirectory('blog/thumbnails');
        Storage::disk('public')->put($savePath, $image->toPng()->toString());

        $this->info("サムネイル画像を生成しました: {$savePath}");

        return $savePath;
    }
}
