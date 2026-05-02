<?php

declare(strict_types=1);

namespace App\Services\Bike;

use App\Models\BikeModel;
use App\Models\Listing;
use App\Models\Manufacturer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * 市区町村レベルSEOランディングページ用サービス
 */
final class CityLandingService
{
    /**
     * URLパラメータから市区町村ページ情報を解決
     *
     * @return array{filters: array, meta: array}|array{}
     */
    public function resolveCityPageInfo(string $pref, string $city, string $slug): array
    {
        // 1. メーカーslug判定
        $maker = Manufacturer::where('slug', $slug)->first();
        if ($maker) {
            return [
                'filters' => ['manufacturer_id' => $maker->id],
                'meta' => $this->buildMeta($pref, $city, $maker->name, 'maker', $slug),
            ];
        }

        // 2. 車種slug判定
        $model = BikeModel::where('slug', $slug)->first();
        if ($model) {
            $makerName = $model->manufacturer?->name ?? '';
            $label = trim("{$makerName} {$model->name}");

            return [
                'filters' => ['bike_model_id' => $model->id],
                'meta' => $this->buildMeta($pref, $city, $label, 'model', $slug),
            ];
        }

        return [];
    }

    /**
     * 市区町村×フィルタでリスティング一覧をページネーション取得
     */
    public function getCityListings(string $fullPref, string $city, array $filters, string $sort = 'latest', int $perPage = 30): LengthAwarePaginator
    {
        $query = Listing::query()
            ->join('shops', 'listings.shop_id', '=', 'shops.id')
            ->where('shops.prefecture', 'like', "{$fullPref}%")
            ->where('shops.city', $city)
            ->where('listings.is_sold_out', false)
            ->whereNotNull('listings.bike_model_id')
            ->select('listings.*');

        $this->applyFilters($query, $filters);

        match ($sort) {
            'price_asc' => $query->whereNotNull('listings.total_price')
                ->where('listings.total_price', '>', 0)
                ->orderBy('listings.total_price', 'asc'),
            'price_desc' => $query->whereNotNull('listings.total_price')
                ->where('listings.total_price', '>', 0)
                ->orderBy('listings.total_price', 'desc'),
            'mileage_asc' => $query->whereNotNull('listings.mileage')
                ->orderBy('listings.mileage', 'asc'),
            default => $query->orderByDesc('listings.updated_at'),
        };

        return $query->with(['shop', 'bikeModel.manufacturer', 'site', 'tags'])->paginate($perPage);
    }

    /**
     * 市区町村ページ用KPIを集計（キャッシュ付き）
     */
    public function computeCityKpi(string $fullPref, string $city, array $filters, string $type = 'maker'): array
    {
        $cacheKey = 'city_landing_kpi_v1_' . md5(serialize([$fullPref, $city, $filters, $type]));

        return Cache::remember($cacheKey, 86400, function () use ($fullPref, $city, $filters, $type) {
            $query = Listing::query()
                ->join('shops', 'listings.shop_id', '=', 'shops.id')
                ->where('shops.prefecture', 'like', "{$fullPref}%")
                ->where('shops.city', $city)
                ->where('listings.is_sold_out', false)
                ->where('listings.total_price', '>', 0)
                ->whereNotNull('listings.bike_model_id');

            $this->applyFilters($query, $filters);

            $totalCount = (clone $query)->count();
            if ($totalCount === 0) {
                return [
                    'total_count' => 0,
                    'avg_price' => null,
                    'min_price' => null,
                    'top_model' => null,
                    'top_models' => [],
                    'kpi_mode' => $type === 'model' ? 'model_year' : 'default',
                    'price_distribution' => [],
                ];
            }

            $priceStats = (clone $query)->selectRaw('
                AVG(listings.total_price) as avg_price,
                MIN(listings.total_price) as min_price
            ')->first();

            $priceDistribution = $this->buildPriceDistribution($query, $totalCount);

            if ($type === 'model') {
                return $this->buildModelKpi($query, $totalCount, $priceStats, $priceDistribution);
            }

            return $this->buildDefaultKpi($query, $totalCount, $priceStats, $priceDistribution);
        });
    }

    /**
     * 市区町村ページ用の関連リンクを生成（キャッシュ付き）
     */
    public function computeCityRelatedLinks(string $fullPref, string $city, array $filters, string $type, string $slug, string $pref): array
    {
        $cacheKey = 'city_landing_links_v1_' . md5(serialize([$fullPref, $city, $filters, $type, $slug]));

        return Cache::remember($cacheKey, 86400, function () use ($fullPref, $city, $filters, $type, $slug, $pref) {
            return [
                'same_city' => $this->buildSameCityLinks($fullPref, $city, $filters, $type, $pref),
                'other_cities' => $this->buildOtherCityLinks($fullPref, $city, $filters, $type, $slug, $pref),
            ];
        });
    }

    /**
     * 同市区町村の他メーカー/車種（8件）
     */
    private function buildSameCityLinks(string $fullPref, string $city, array $filters, string $type, string $pref): array
    {
        $baseQuery = Listing::query()
            ->join('shops', 'listings.shop_id', '=', 'shops.id')
            ->where('shops.prefecture', 'like', "{$fullPref}%")
            ->where('shops.city', $city)
            ->where('listings.is_sold_out', false);

        $links = [];

        if ($type === 'maker') {
            $rows = (clone $baseQuery)
                ->selectRaw('listings.manufacturer_id, COUNT(*) as cnt')
                ->where('listings.manufacturer_id', '>', 0)
                ->when(!empty($filters['manufacturer_id']), fn ($q) => $q->where('listings.manufacturer_id', '!=', $filters['manufacturer_id']))
                ->groupBy('listings.manufacturer_id')
                ->orderByDesc('cnt')
                ->limit(8)
                ->get();

            $manufacturers = Manufacturer::whereIn('id', $rows->pluck('manufacturer_id'))
                ->whereNotNull('slug')
                ->pluck('slug', 'id');
            $names = Manufacturer::whereIn('id', $rows->pluck('manufacturer_id'))->pluck('name', 'id');

            foreach ($rows as $row) {
                $mfrSlug = $manufacturers->get($row->manufacturer_id);
                $name = $names->get($row->manufacturer_id);
                if ($mfrSlug && $name) {
                    $links[] = [
                        'label' => "{$city} × {$name}",
                        'url' => route('bikes.city_landing', ['prefecture' => $pref, 'city' => $city, 'slug' => $mfrSlug]),
                        'count' => (int) $row->cnt,
                    ];
                }
            }
        } elseif ($type === 'model') {
            // 同市区町村の他メーカー
            $rows = (clone $baseQuery)
                ->selectRaw('listings.manufacturer_id, COUNT(*) as cnt')
                ->where('listings.manufacturer_id', '>', 0)
                ->groupBy('listings.manufacturer_id')
                ->orderByDesc('cnt')
                ->limit(8)
                ->get();

            $manufacturers = Manufacturer::whereIn('id', $rows->pluck('manufacturer_id'))
                ->whereNotNull('slug')
                ->pluck('slug', 'id');
            $names = Manufacturer::whereIn('id', $rows->pluck('manufacturer_id'))->pluck('name', 'id');

            foreach ($rows as $row) {
                $mfrSlug = $manufacturers->get($row->manufacturer_id);
                $name = $names->get($row->manufacturer_id);
                if ($mfrSlug && $name) {
                    $links[] = [
                        'label' => "{$city} × {$name}",
                        'url' => route('bikes.city_landing', ['prefecture' => $pref, 'city' => $city, 'slug' => $mfrSlug]),
                        'count' => (int) $row->cnt,
                    ];
                }
            }
        }

        return $links;
    }

    /**
     * 同都道府県の他市区町村で同じターゲット（10件）
     */
    private function buildOtherCityLinks(string $fullPref, string $city, array $filters, string $type, string $slug, string $pref): array
    {
        $query = Listing::query()
            ->join('shops', 'listings.shop_id', '=', 'shops.id')
            ->where('shops.prefecture', 'like', "{$fullPref}%")
            ->where('shops.city', '!=', $city)
            ->whereNotNull('shops.city')
            ->where('listings.is_sold_out', false);

        $this->applyFilters($query, $filters);

        $rows = $query
            ->selectRaw('shops.city as other_city, COUNT(*) as cnt')
            ->groupBy('shops.city')
            ->orderByDesc('cnt')
            ->limit(10)
            ->get();

        return $rows->map(fn ($row) => [
            'label' => "{$row->other_city}の同条件",
            'url' => route('bikes.city_landing', ['prefecture' => $pref, 'city' => $row->other_city, 'slug' => $slug]),
            'count' => (int) $row->cnt,
        ])->toArray();
    }

    /**
     * 共通フィルタ適用
     */
    private function applyFilters(mixed $query, array $filters): void
    {
        if (!empty($filters['manufacturer_id'])) {
            $query->where('listings.manufacturer_id', (int) $filters['manufacturer_id']);
        }
        if (!empty($filters['bike_model_id'])) {
            $query->where('listings.bike_model_id', (int) $filters['bike_model_id']);
        }
    }

    /**
     * SEOメタデータを構築
     */
    private function buildMeta(string $pref, string $city, string $targetName, string $type, string $slug): array
    {
        $title = "{$city}の{$targetName} 中古バイク";
        $description = "{$pref}{$city}で販売中の{$targetName}の中古バイク・新車をまとめて検索。価格・走行距離で比較、MotoHubなら複数ショップの在庫を一括検討できます。";
        $h1Html = "{$city}の<span class='text-blue-600'>{$targetName}</span> 中古バイク在庫一覧";

        if ($type === 'model') {
            $title = "{$city}の{$targetName} 中古バイク・新車【相場・価格比較】";
            $description = "{$pref}{$city}の{$targetName}の中古バイク・新車を掲載中。支払総額の安い順や走行距離の少ない順で比較。相場・価格推移もチェック！";
        }

        return [
            'title' => $title,
            'description' => $description,
            'h1_html' => $h1Html,
            'prefecture' => $pref,
            'city' => $city,
            'target_name' => $targetName,
            'type' => $type,
            'slug' => $slug,
        ];
    }

    /**
     * 価格帯分布を集計（SeoLandingServiceと同じ7バンド）
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

        return [
            'total_count' => $totalCount,
            'avg_price' => $priceStats->avg_price > 0 ? number_format((float) ($priceStats->avg_price / 10000), 1) : null,
            'min_price' => $priceStats->min_price > 0 ? number_format((float) ($priceStats->min_price / 10000), 1) : null,
            'top_model' => $topYearsFormatted[0]['name'] ?? null,
            'top_models' => $topYearsFormatted,
            'kpi_mode' => 'model_year',
            'price_distribution' => $priceDistribution,
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

        return [
            'total_count' => $totalCount,
            'avg_price' => $priceStats->avg_price > 0 ? number_format((float) ($priceStats->avg_price / 10000), 1) : null,
            'min_price' => $priceStats->min_price > 0 ? number_format((float) ($priceStats->min_price / 10000), 1) : null,
            'top_model' => $topModelsFormatted[0]['name'] ?? null,
            'top_models' => $topModelsFormatted,
            'kpi_mode' => 'default',
            'price_distribution' => $priceDistribution,
        ];
    }
}
