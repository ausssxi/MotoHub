<?php

declare(strict_types=1);

namespace App\Services\Bike;

use App\Models\BikeModel;
use App\Models\Category;
use App\Models\Listing;
use App\Models\Manufacturer;
use App\Repositories\Bike\ManufacturerRepository;
use App\Repositories\Bike\CategoryRepository;
use App\Repositories\Bike\BikeModelRepository; // ★追加
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * SEO着地ページ用のロジッククラス
 * URLパラメータから検索条件やメタデータを生成します
 */
final class SeoLandingService
{
    public function __construct(
        private readonly ManufacturerRepository $manufacturerRepo,
        private readonly CategoryRepository $categoryRepo,
        private readonly BikeModelRepository $modelRepo // ★追加
    ) {}

    /**
     * パラメータを解析してページ情報を生成
     *
     * @param string $prefecture 都道府県名 (例: 東京都)
     * @param string $slug メーカー名、カテゴリ名、車種名、排気量キーワード
     */
    public function resolvePageInfo(string $prefecture, string $slug): array
    {
        $filters = ['prefecture' => $prefecture];
        $typeLabel = '';
        $type = 'unknown';

        // 1. メーカー判定 (例: ホンダ, Yamaha)
        $manufacturers = $this->manufacturerRepo->getAllSortedByName();
        $maker = $manufacturers->first(function ($m) use ($slug) {
            return $m->name !== null && (strtolower($m->name) === strtolower($slug) ||
                   str_contains(strtolower($m->name), strtolower($slug)));
        });

        if ($maker) {
            $filters['manufacturer_id'] = $maker->id;
            $typeLabel = $maker->name;
            $type = 'maker';
        } 
        // 2. カテゴリ判定 (例: ネイキッド, scooter)
        elseif ($category = $this->findCategory($slug)) {
            $filters['category_id'] = $category->id;
            $typeLabel = $category->name;
            $type = 'category';
        }
        // 3. ★追加: 車種名判定 (例: PCX, CB400SF)
        elseif ($model = $this->findModel($slug)) {
            $filters['bike_model_id'] = $model->id;
            // メーカー名も含めるとSEO的に強い (例: ホンダ PCX)
            $makerName = $model->manufacturer?->name ?? '';
            $typeLabel = "{$makerName} {$model->name}";
            $type = 'model';
        }
        // 4. ★追加: 排気量・免許区分判定 (例: 原付, 大型)
        elseif ($displacement = $this->findDisplacement($slug)) {
            if (isset($displacement['min'])) $filters['min_displacement'] = $displacement['min'];
            if (isset($displacement['max'])) $filters['max_displacement'] = $displacement['max'];
            $typeLabel = $displacement['label'];
            $type = 'displacement';
        }

        // どれにも該当しない場合は空を返す
        if (empty($typeLabel)) {
            return [];
        }

        return [
            'filters' => $filters,
            'meta' => $this->generateMetaData($prefecture, $typeLabel, $type)
        ];
    }

    /**
     * エリアLPのKPIを集計（キャッシュ付き）
     * $type: 'maker','model','category','displacement' など
     */
    public function computeLandingKpi(string $prefecture, array $filters, string $type = 'maker'): array
    {
        $cacheKey = 'landing_kpi_v4_' . md5(serialize([$prefecture, $filters, $type]));

        return Cache::remember($cacheKey, 3600, function () use ($prefecture, $filters, $type) {
            $query = Listing::query()
                ->join('shops', 'listings.shop_id', '=', 'shops.id')
                ->where('shops.prefecture', 'like', "{$prefecture}%")
                ->where('listings.is_sold_out', false)
                ->where('listings.total_price', '>', 0)
                ->whereNotNull('listings.bike_model_id');

            if (!empty($filters['manufacturer_id'])) {
                $query->where('listings.manufacturer_id', (int) $filters['manufacturer_id']);
            }
            if (!empty($filters['category_id'])) {
                $query->where('listings.category_id', (int) $filters['category_id']);
            }
            if (!empty($filters['bike_model_id'])) {
                $query->where('listings.bike_model_id', (int) $filters['bike_model_id']);
            }
            if (!empty($filters['min_displacement']) || !empty($filters['max_displacement'])) {
                $query->join('bike_models', 'listings.bike_model_id', '=', 'bike_models.id');
                if (!empty($filters['min_displacement'])) {
                    $query->where('bike_models.displacement', '>=', (int) $filters['min_displacement']);
                }
                if (!empty($filters['max_displacement'])) {
                    $query->where('bike_models.displacement', '<=', (int) $filters['max_displacement']);
                }
            }

            $priceStats = (clone $query)->selectRaw('
                AVG(listings.total_price) as avg_price,
                MIN(listings.total_price) as min_price
            ')->first();

            $totalCount = (clone $query)->count();
            $priceDistribution = $this->buildPriceDistribution($query, $totalCount);

            // 車種ページ: 年式別TOP3
            if ($type === 'model') {
                return $this->buildModelKpi($query, $totalCount, $priceStats, $priceDistribution);
            }

            // それ以外: 車種別TOP3（従来ロジック）
            return $this->buildDefaultKpi($query, $totalCount, $priceStats, $priceDistribution);
        });
    }

    /**
     * 価格帯分布を集計
     */
    private function buildPriceDistribution(mixed $query, int $totalCount): array
    {
        if ($totalCount === 0) {
            return [];
        }

        $bands = [
            ['label' => '〜10万円',      'min' => 0,       'max' => 100000],
            ['label' => '10〜20万円',     'min' => 100000,  'max' => 200000],
            ['label' => '20〜30万円',     'min' => 200000,  'max' => 300000],
            ['label' => '30〜50万円',     'min' => 300000,  'max' => 500000],
            ['label' => '50〜80万円',     'min' => 500000,  'max' => 800000],
            ['label' => '80〜100万円',    'min' => 800000,  'max' => 1000000],
            ['label' => '100万円〜',      'min' => 1000000, 'max' => null],
        ];

        $caseWhen = 'CASE';
        foreach ($bands as $i => $band) {
            if ($band['max'] === null) {
                $caseWhen .= " WHEN listings.total_price >= {$band['min']} THEN {$i}";
            } else {
                $caseWhen .= " WHEN listings.total_price >= {$band['min']} AND listings.total_price < {$band['max']} THEN {$i}";
            }
        }
        $caseWhen .= ' END';

        $rows = (clone $query)
            ->selectRaw("{$caseWhen} as band_idx, COUNT(*) as cnt")
            ->groupByRaw($caseWhen)
            ->get()
            ->keyBy('band_idx');

        $maxCount = $rows->max('cnt') ?: 1;

        $distribution = [];
        foreach ($bands as $i => $band) {
            $count = (int) ($rows->get($i)?->cnt ?? 0);
            if ($count === 0) {
                continue;
            }
            $distribution[] = [
                'label' => $band['label'],
                'count' => $count,
                'percent' => round($count / $totalCount * 100, 1),
                'bar_width' => round($count / $maxCount * 100),
                'is_max' => $count === $maxCount,
            ];
        }

        return $distribution;
    }

    /**
     * 年式分布を集計
     */
    private function buildYearDistribution(mixed $query, int $totalCount): array
    {
        if ($totalCount === 0) {
            return [];
        }

        $currentYear = (int) date('Y');
        $bands = [
            ['label' => '〜2005年', 'min' => 0, 'max' => 2005],
            ['label' => '2006〜2010年', 'min' => 2006, 'max' => 2010],
            ['label' => '2011〜2015年', 'min' => 2011, 'max' => 2015],
            ['label' => '2016〜2020年', 'min' => 2016, 'max' => 2020],
            ['label' => '2021年〜', 'min' => 2021, 'max' => $currentYear + 1],
        ];

        $caseWhen = 'CASE';
        foreach ($bands as $i => $band) {
            $caseWhen .= " WHEN listings.model_year >= {$band['min']} AND listings.model_year <= {$band['max']} THEN {$i}";
        }
        $caseWhen .= ' END';

        $rows = (clone $query)
            ->where('listings.model_year', '>', 0)
            ->selectRaw("{$caseWhen} as band_idx, COUNT(*) as cnt")
            ->groupByRaw($caseWhen)
            ->get()
            ->keyBy('band_idx');

        $maxCount = $rows->max('cnt') ?: 1;

        $distribution = [];
        foreach ($bands as $i => $band) {
            $count = (int) ($rows->get($i)?->cnt ?? 0);
            if ($count === 0) {
                continue;
            }
            $distribution[] = [
                'label' => $band['label'],
                'count' => $count,
                'bar_width' => round($count / $maxCount * 100),
                'is_max' => $count === $maxCount,
            ];
        }

        return $distribution;
    }

    /**
     * 車種ページ用KPI: 年式別TOP3
     */
    private function buildModelKpi(mixed $query, int $totalCount, mixed $priceStats, array $priceDistribution): array
    {
        $topYears = (clone $query)
            ->where('listings.model_year', '>', 0)
            ->selectRaw('listings.model_year, COUNT(*) as cnt, AVG(listings.total_price) as year_avg_price')
            ->groupBy('listings.model_year')
            ->orderByDesc('cnt')
            ->limit(3)
            ->get();

        $topYearsFormatted = $topYears->map(fn ($row) => [
            'name' => "{$row->model_year}年式",
            'count' => (int) $row->cnt,
            'avg_price' => $row->year_avg_price > 0
                ? number_format((float) ($row->year_avg_price / 10000), 1)
                : null,
        ])->toArray();

        // 追加KPI: 平均走行距離、平均年式
        $extraStats = (clone $query)->selectRaw('
            AVG(CASE WHEN listings.mileage > 0 THEN listings.mileage END) as avg_mileage,
            AVG(CASE WHEN listings.model_year > 1980 THEN listings.model_year END) as avg_year
        ')->first();

        // 年式分布
        $yearDistribution = $this->buildYearDistribution($query, $totalCount);

        return [
            'total_count' => $totalCount,
            'avg_price' => $priceStats->avg_price > 0 ? number_format((float) ($priceStats->avg_price / 10000), 1) : null,
            'min_price' => $priceStats->min_price > 0 ? number_format((float) ($priceStats->min_price / 10000), 1) : null,
            'avg_mileage' => ($extraStats->avg_mileage ?? 0) > 0 ? number_format((float) ($extraStats->avg_mileage / 10000), 1) : null,
            'avg_year' => ($extraStats->avg_year ?? 0) > 1980 ? (int) round((float) $extraStats->avg_year) : null,
            'top_model' => $topYearsFormatted[0]['name'] ?? null,
            'top_models' => $topYearsFormatted,
            'kpi_mode' => 'model_year',
            'price_distribution' => $priceDistribution,
            'year_distribution' => $yearDistribution,
        ];
    }

    /**
     * デフォルトKPI: 車種別TOP3
     */
    private function buildDefaultKpi(mixed $query, int $totalCount, mixed $priceStats, array $priceDistribution): array
    {
        $topModels = (clone $query)
            ->selectRaw('listings.bike_model_id, COUNT(*) as cnt')
            ->groupBy('listings.bike_model_id')
            ->orderByDesc('cnt')
            ->limit(3)
            ->get();

        $modelIds = $topModels->pluck('bike_model_id')->toArray();
        $modelNames = BikeModel::whereIn('id', $modelIds)->pluck('name', 'id');

        $modelAvgPrices = Listing::where('is_sold_out', false)
            ->where('total_price', '>', 0)
            ->whereIn('bike_model_id', $modelIds)
            ->selectRaw('bike_model_id, AVG(total_price) as avg_price')
            ->groupBy('bike_model_id')
            ->pluck('avg_price', 'bike_model_id');

        $topModelsFormatted = $topModels->map(fn ($row) => [
            'name' => $modelNames->get($row->bike_model_id, '不明'),
            'count' => (int) $row->cnt,
            'avg_price' => $modelAvgPrices->get($row->bike_model_id, 0) > 0
                ? number_format((float) ($modelAvgPrices->get($row->bike_model_id) / 10000), 1)
                : null,
        ])->toArray();

        // 追加KPI: 平均走行距離、平均年式
        $extraStats = (clone $query)->selectRaw('
            AVG(CASE WHEN listings.mileage > 0 THEN listings.mileage END) as avg_mileage,
            AVG(CASE WHEN listings.model_year > 1980 THEN listings.model_year END) as avg_year
        ')->first();

        // 年式分布
        $yearDistribution = $this->buildYearDistribution($query, $totalCount);

        return [
            'total_count' => $totalCount,
            'avg_price' => $priceStats->avg_price > 0 ? number_format((float) ($priceStats->avg_price / 10000), 1) : null,
            'min_price' => $priceStats->min_price > 0 ? number_format((float) ($priceStats->min_price / 10000), 1) : null,
            'avg_mileage' => ($extraStats->avg_mileage ?? 0) > 0 ? number_format((float) ($extraStats->avg_mileage / 10000), 1) : null,
            'avg_year' => ($extraStats->avg_year ?? 0) > 1980 ? (int) round((float) $extraStats->avg_year) : null,
            'top_model' => $topModelsFormatted[0]['name'] ?? null,
            'top_models' => $topModelsFormatted,
            'kpi_mode' => 'default',
            'price_distribution' => $priceDistribution,
            'year_distribution' => $yearDistribution,
        ];
    }

    /**
     * エリアLP用の関連リンクを生成（キャッシュ付き）
     */
    public function computeRelatedLinks(string $prefecture, array $filters, string $type, string $targetName, string $slug): array
    {
        $cacheKey = 'landing_links_v1_' . md5(serialize([$prefecture, $filters, $type]));

        return Cache::remember($cacheKey, 3600, function () use ($prefecture, $filters, $type, $targetName, $slug) {
            $sameArea = $this->buildSameAreaLinks($prefecture, $filters, $type);
            $otherAreas = $this->buildOtherAreaLinks($prefecture, $filters, $targetName, $slug);

            return [
                'same_area' => $sameArea,
                'other_areas' => $otherAreas,
            ];
        });
    }

    /**
     * Block 1: 同地域の他メーカー/カテゴリ/車種リンク
     */
    private function buildSameAreaLinks(string $prefecture, array $filters, string $type): array
    {
        $baseQuery = Listing::query()
            ->join('shops', 'listings.shop_id', '=', 'shops.id')
            ->where('shops.prefecture', 'like', "{$prefecture}%")
            ->where('listings.is_sold_out', false);

        $links = [];

        if (in_array($type, ['maker', 'model'], true)) {
            $rows = (clone $baseQuery)
                ->selectRaw('listings.manufacturer_id, COUNT(*) as cnt')
                ->where('listings.manufacturer_id', '>', 0)
                ->when(!empty($filters['manufacturer_id']), fn ($q) => $q->where('listings.manufacturer_id', '!=', $filters['manufacturer_id']))
                ->groupBy('listings.manufacturer_id')
                ->orderByDesc('cnt')
                ->limit(8)
                ->get();

            $names = Manufacturer::whereIn('id', $rows->pluck('manufacturer_id'))->pluck('name', 'id');

            foreach ($rows as $row) {
                $name = $names->get($row->manufacturer_id);
                if ($name) {
                    $links[] = [
                        'label' => "{$prefecture} × {$name}",
                        'url' => route('bikes.landing', ['prefecture' => $prefecture, 'slug' => $name]),
                        'count' => (int) $row->cnt,
                    ];
                }
            }
        } elseif ($type === 'category') {
            $rows = (clone $baseQuery)
                ->selectRaw('listings.category_id, COUNT(*) as cnt')
                ->where('listings.category_id', '>', 0)
                ->when(!empty($filters['category_id']), fn ($q) => $q->where('listings.category_id', '!=', $filters['category_id']))
                ->groupBy('listings.category_id')
                ->orderByDesc('cnt')
                ->limit(8)
                ->get();

            $names = Category::whereIn('id', $rows->pluck('category_id'))->pluck('name', 'id');

            foreach ($rows as $row) {
                $name = $names->get($row->category_id);
                if ($name) {
                    $links[] = [
                        'label' => "{$prefecture} × {$name}",
                        'url' => route('bikes.landing', ['prefecture' => $prefecture, 'slug' => $name]),
                        'count' => (int) $row->cnt,
                    ];
                }
            }
        } elseif ($type === 'displacement') {
            $rows = (clone $baseQuery)
                ->selectRaw('listings.manufacturer_id, COUNT(*) as cnt')
                ->where('listings.manufacturer_id', '>', 0)
                ->groupBy('listings.manufacturer_id')
                ->orderByDesc('cnt')
                ->limit(8)
                ->get();

            $names = Manufacturer::whereIn('id', $rows->pluck('manufacturer_id'))->pluck('name', 'id');

            foreach ($rows as $row) {
                $name = $names->get($row->manufacturer_id);
                if ($name) {
                    $links[] = [
                        'label' => "{$prefecture} × {$name}",
                        'url' => route('bikes.landing', ['prefecture' => $prefecture, 'slug' => $name]),
                        'count' => (int) $row->cnt,
                    ];
                }
            }
        }

        return $links;
    }

    /**
     * Block 2: 同ターゲットの他地域リンク
     */
    private function buildOtherAreaLinks(string $prefecture, array $filters, string $targetName, string $slug): array
    {
        $areaQuery = Listing::query()
            ->join('shops', 'listings.shop_id', '=', 'shops.id')
            ->where('listings.is_sold_out', false)
            ->where('shops.prefecture', 'not like', "{$prefecture}%");

        if (!empty($filters['manufacturer_id'])) {
            $areaQuery->where('listings.manufacturer_id', (int) $filters['manufacturer_id']);
        }
        if (!empty($filters['category_id'])) {
            $areaQuery->where('listings.category_id', (int) $filters['category_id']);
        }
        if (!empty($filters['bike_model_id'])) {
            $areaQuery->where('listings.bike_model_id', (int) $filters['bike_model_id']);
        }
        if (!empty($filters['min_displacement']) || !empty($filters['max_displacement'])) {
            $areaQuery->join('bike_models', 'listings.bike_model_id', '=', 'bike_models.id');
            if (!empty($filters['min_displacement'])) {
                $areaQuery->where('bike_models.displacement', '>=', (int) $filters['min_displacement']);
            }
            if (!empty($filters['max_displacement'])) {
                $areaQuery->where('bike_models.displacement', '<=', (int) $filters['max_displacement']);
            }
        }

        $rawCounts = $areaQuery
            ->selectRaw('shops.prefecture, COUNT(*) as cnt')
            ->groupBy('shops.prefecture')
            ->get();

        $allPrefs = collect(config('bike.regions'))->flatten()->reject(fn ($p) => $p === $prefecture)->values();

        $prefAgg = [];
        foreach ($rawCounts as $row) {
            foreach ($allPrefs as $pref) {
                if (str_starts_with($row->prefecture, $pref)) {
                    $prefAgg[$pref] = ($prefAgg[$pref] ?? 0) + (int) $row->cnt;
                    break;
                }
            }
        }

        return collect($prefAgg)
            ->sortDesc()
            ->take(10)
            ->map(fn ($count, $pref) => [
                'label' => "{$pref}の{$targetName}",
                'url' => route('bikes.landing', ['prefecture' => $pref, 'slug' => $slug]),
                'count' => $count,
            ])
            ->values()
            ->toArray();
    }

    /**
     * カタログページ（地域なし）のslugを解析してページ情報を生成
     *
     * slug例:
     *   "honda-naked"  → メーカー×カテゴリ
     *   "250cc"        → 排気量帯
     *   "honda"        → メーカー全車種
     */
    public function resolveCatalogPage(string $slug): array
    {
        $filters = [];
        $typeLabel = '';
        $type = 'unknown';

        // 1. 排気量帯判定 (例: 250cc, 125cc, 400cc)
        if (preg_match('/^(\d+)cc$/', $slug, $matches)) {
            $cc = (int) $matches[1];
            $displacement = $this->mapCcToRange($cc);
            if ($displacement) {
                if (isset($displacement['min'])) $filters['min_displacement'] = $displacement['min'];
                if (isset($displacement['max'])) $filters['max_displacement'] = $displacement['max'];
                $typeLabel = $displacement['label'];
                $type = 'displacement';
            }
        }

        // 2. メーカー×カテゴリ判定 (例: honda-naked)
        if (empty($typeLabel) && str_contains($slug, '-')) {
            [$mfrSlug, $catSlug] = explode('-', $slug, 2);

            $manufacturers = $this->manufacturerRepo->getAllSortedByName();
            $maker = $manufacturers->first(fn($m) => $m->slug !== null && strtolower($m->slug) === strtolower($mfrSlug));

            $categories = $this->categoryRepo->getAllSorted();
            $category = $categories->first(fn($c) => $c->slug !== null && strtolower($c->slug) === strtolower($catSlug));

            if ($maker && $category) {
                $filters['manufacturer_id'] = $maker->id;
                $filters['category_id'] = $category->id;
                $typeLabel = "{$maker->name} {$category->name}";
                $type = 'maker_category';
            }
        }

        // 3. メーカー単体判定 (例: honda)
        if (empty($typeLabel)) {
            $manufacturers = $this->manufacturerRepo->getAllSortedByName();
            $maker = $manufacturers->first(fn($m) => $m->slug !== null && strtolower($m->slug) === strtolower($slug));

            if ($maker) {
                $filters['manufacturer_id'] = $maker->id;
                $typeLabel = $maker->name;
                $type = 'maker';
            }
        }

        if (empty($typeLabel)) {
            return [];
        }

        return [
            'filters' => $filters,
            'meta' => $this->generateCatalogMetaData($typeLabel, $type, $slug),
        ];
    }

    /**
     * cc数値を排気量帯にマッピング
     */
    private function mapCcToRange(int $cc): ?array
    {
        return match (true) {
            $cc <= 50  => ['min' => 0, 'max' => 50, 'label' => '50cc以下（原付）バイク'],
            $cc <= 125 => ['min' => 51, 'max' => 125, 'label' => "{$cc}cc以下バイク"],
            $cc <= 250 => ['min' => 126, 'max' => 250, 'label' => "{$cc}ccバイク"],
            $cc <= 400 => ['min' => 126, 'max' => 400, 'label' => "{$cc}cc以下（中型）バイク"],
            $cc <= 750 => ['min' => 401, 'max' => 750, 'label' => "{$cc}ccバイク"],
            default    => ['min' => 401, 'max' => null, 'label' => "大型（{$cc}cc〜）バイク"],
        };
    }

    /**
     * カタログページ用のSEOメタデータを生成
     */
    private function generateCatalogMetaData(string $label, string $type, string $slug): array
    {
        $title = "{$label} 中古バイク・新車一覧";
        $h1 = "<span class='text-blue-600'>{$label}</span> 中古バイク・新車一覧";
        $description = "{$label}の中古バイク・新車を一括検索。MotoHubなら、複数のバイクショップの在庫を一括で比較・検討できます。価格相場やスペックの比較も簡単です。";

        if ($type === 'maker_category') {
            $title = "{$label} 中古バイク・新車【在庫一覧・価格比較】";
            $description = "{$label}の中古・新車バイクの在庫一覧。支払総額の安い順や走行距離の少ない順で比較できます。{$label}の相場情報も掲載中。";
        } elseif ($type === 'displacement') {
            $title = "{$label} 中古・新車を探す | MotoHub";
        }

        return [
            'title' => $title,
            'description' => $description,
            'h1_html' => $h1,
            'target_name' => $label,
            'type' => $type,
            'slug' => $slug,
        ];
    }

    /**
     * カテゴリを検索
     */
    private function findCategory(string $slug)
    {
        $categories = $this->categoryRepo->getAllSorted();
        return $categories->first(function ($c) use ($slug) {
            return ($c->slug !== null && strtolower($c->slug) === strtolower($slug)) || $c->name === $slug;
        });
    }

    /**
     * 車種名を検索
     */
    private function findModel(string $slug)
    {
        // searchByName は部分一致検索なので、先頭1件を取得して検証
        // (厳密にするなら完全一致チェックを入れる)
        $models = $this->modelRepo->searchByName($slug, 1);
        return $models->first();
    }

    /**
     * 排気量キーワードを判定
     */
    private function findDisplacement(string $slug): ?array
    {
        $map = [
            '原付' => ['min' => 0, 'max' => 50, 'label' => '原付バイク(50cc以下)'],
            'スクーター' => ['min' => 0, 'max' => 125, 'label' => 'スクーター・原付二種'], // カテゴリになければここで拾う
            '小型' => ['min' => 51, 'max' => 125, 'label' => '小型バイク(125cc以下)'],
            '原付二種' => ['min' => 51, 'max' => 125, 'label' => '原付二種'],
            '中型' => ['min' => 126, 'max' => 400, 'label' => '中型バイク(400cc以下)'],
            '普通二輪' => ['min' => 126, 'max' => 400, 'label' => '普通二輪'],
            '大型' => ['min' => 401, 'max' => null, 'label' => '大型バイク'],
            'リッター' => ['min' => 1000, 'max' => null, 'label' => 'リッターバイク'],
        ];

        foreach ($map as $key => $value) {
            if (str_contains($slug, $key)) {
                return $value;
            }
        }
        return null;
    }

    /**
     * タイプに応じたSEOメタデータを生成
     */
    private function generateMetaData(string $prefecture, string $label, string $type): array
    {
        // デフォルト
        $title = "{$prefecture}の{$label} 中古バイク在庫一覧";
        $h1 = "{$prefecture}の<span class='text-blue-600'>{$label}</span>中古バイク在庫一覧";
        $description = "{$prefecture}で販売中の{$label}の中古バイク・新車をまとめて検索。価格・走行距離で比較、MotoHubなら複数ショップの在庫を一括検討できます。";

        // タイプ別の最適化（クリック率向上）
        switch ($type) {
            case 'model': // 車種名の場合（一番購買意欲が高い）
                $title = "{$prefecture}の{$label} 中古バイク・新車【相場・価格比較】";
                $description = "{$prefecture}の{$label}の中古バイク・新車を掲載中。支払総額の安い順や走行距離の少ない順で比較。相場・価格推移もチェック！";
                break;

            case 'maker': // メーカーの場合
                $title = "{$prefecture}の{$label} 中古バイク在庫一覧";
                $description = "{$prefecture}で販売中の{$label}の中古バイクを一括検索。価格・走行距離・年式で比較できます。";
                break;

            case 'displacement': // 排気量の場合
                $title = "{$prefecture}の{$label} 中古バイク・新車を探す";
                break;
        }

        return [
            'title' => $title,
            'description' => $description,
            'h1_html' => $h1,
            'prefecture' => $prefecture,
            'target_name' => $label,
            'type' => $type,
        ];
    }
}