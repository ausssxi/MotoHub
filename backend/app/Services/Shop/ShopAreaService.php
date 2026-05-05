<?php

declare(strict_types=1);

namespace App\Services\Shop;

use App\Models\Listing;
use App\Models\Shop;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class ShopAreaService
{
    /**
     * エリアインデックス: 都道府県別のショップ件数
     */
    public function getAreaIndex(): array
    {
        $regions = config('parking.regions', []);

        $prefCounts = Cache::remember('shop_area_pref_counts', 86400, function () {
            return Shop::whereNotNull('prefecture')
                ->where('prefecture', '!=', '')
                ->select('prefecture', DB::raw('COUNT(*) as count'))
                ->groupBy('prefecture')
                ->pluck('count', 'prefecture');
        });

        return [
            'regions' => $regions,
            'prefCounts' => $prefCounts,
            'totalCount' => $prefCounts->sum(),
        ];
    }

    /**
     * 都道府県詳細: 市区町村別の集計 + 在庫統計
     */
    public function getPrefectureDetail(string $prefecture): ?array
    {
        $cacheKey = 'shop_area_pref_' . md5($prefecture);

        return Cache::remember($cacheKey, 86400, function () use ($prefecture) {
            $shops = Shop::where('prefecture', $prefecture)->get();

            if ($shops->isEmpty()) {
                return null;
            }

            // 市区町村別の集計
            $cities = $shops
                ->groupBy(fn($s) => $s->city ?? '')
                ->map(function (Collection $cityShops, string $city) {
                    return [
                        'name' => $city,
                        'count' => $cityShops->count(),
                    ];
                })
                ->filter(fn($c) => $c['name'] !== '')
                ->sortByDesc('count')
                ->values();

            // 在庫統計
            $listingStats = Listing::whereHas('shop', fn($q) => $q->where('prefecture', $prefecture))
                ->where('is_sold_out', false)
                ->selectRaw('COUNT(*) as total_listings, AVG(total_price) as avg_price')
                ->first();

            // メーカー分布
            $makerDistribution = Listing::whereHas('shop', fn($q) => $q->where('prefecture', $prefecture))
                ->where('is_sold_out', false)
                ->join('bike_models', 'listings.bike_model_id', '=', 'bike_models.id')
                ->join('manufacturers', 'bike_models.manufacturer_id', '=', 'manufacturers.id')
                ->select('manufacturers.name', DB::raw('COUNT(*) as count'))
                ->groupBy('manufacturers.name')
                ->orderByDesc('count')
                ->limit(10)
                ->get();

            // 近隣在庫バイク
            $nearbyListings = Listing::whereHas('shop', fn($q) => $q->where('prefecture', $prefecture))
                ->where('is_sold_out', false)
                ->with(['shop', 'bikeModel'])
                ->orderByDesc('created_at')
                ->limit(6)
                ->get();

            $shopsWithCoords = $shops->filter(fn($s) => $s->latitude && $s->longitude);

            return [
                'prefecture' => $prefecture,
                'totalShops' => $shops->count(),
                'totalListings' => (int) ($listingStats->total_listings ?? 0),
                'avgPrice' => $listingStats->avg_price ? (int) round((float) $listingStats->avg_price) : null,
                'cities' => $cities,
                'makerDistribution' => $makerDistribution,
                'avgLat' => $shopsWithCoords->avg('latitude') ?: 35.6762,
                'avgLng' => $shopsWithCoords->avg('longitude') ?: 139.6503,
                'nearbyListings' => $nearbyListings,
            ];
        });
    }

    /**
     * 市区町村詳細: ショップリスト + チャートデータ + FAQ
     */
    public function getCityDetail(string $prefecture, string $city): ?array
    {
        $cacheKey = 'shop_area_city_v1_' . md5($prefecture . $city);

        return Cache::remember($cacheKey, 3600, function () use ($prefecture, $city) {
            $shops = Shop::where('prefecture', $prefecture)
                ->where('city', $city)
                ->withCount(['listings' => fn($q) => $q->where('is_sold_out', false)])
                ->orderByDesc('listings_count')
                ->orderByDesc('rating')
                ->get();

            if ($shops->isEmpty()) {
                return null;
            }

            // メーカー分布
            $makerDistribution = Listing::whereHas('shop', fn($q) => $q->where('prefecture', $prefecture)->where('city', $city))
                ->where('is_sold_out', false)
                ->join('bike_models', 'listings.bike_model_id', '=', 'bike_models.id')
                ->join('manufacturers', 'bike_models.manufacturer_id', '=', 'manufacturers.id')
                ->select('manufacturers.name', DB::raw('COUNT(*) as count'))
                ->groupBy('manufacturers.name')
                ->orderByDesc('count')
                ->limit(10)
                ->get();

            // 価格帯分布
            $priceRanges = Listing::whereHas('shop', fn($q) => $q->where('prefecture', $prefecture)->where('city', $city))
                ->where('is_sold_out', false)
                ->whereNotNull('total_price')
                ->where('total_price', '>', 0)
                ->selectRaw("
                    CASE
                        WHEN total_price < 300000 THEN '〜30万円'
                        WHEN total_price < 500000 THEN '30〜50万円'
                        WHEN total_price < 800000 THEN '50〜80万円'
                        WHEN total_price < 1000000 THEN '80〜100万円'
                        WHEN total_price < 1500000 THEN '100〜150万円'
                        ELSE '150万円〜'
                    END as price_range,
                    CASE
                        WHEN total_price < 300000 THEN 1
                        WHEN total_price < 500000 THEN 2
                        WHEN total_price < 800000 THEN 3
                        WHEN total_price < 1000000 THEN 4
                        WHEN total_price < 1500000 THEN 5
                        ELSE 6
                    END as sort_order,
                    COUNT(*) as cnt
                ")
                ->groupBy('price_range', 'sort_order')
                ->orderBy('sort_order')
                ->get();

            // 在庫統計
            $totalListings = $shops->sum('listings_count');
            $avgPrice = Listing::whereHas('shop', fn($q) => $q->where('prefecture', $prefecture)->where('city', $city))
                ->where('is_sold_out', false)
                ->whereNotNull('total_price')
                ->where('total_price', '>', 0)
                ->avg('total_price');

            // 同県内の他の市区町村
            $siblingCities = Shop::where('prefecture', $prefecture)
                ->where('city', '!=', $city)
                ->whereNotNull('city')
                ->where('city', '!=', '')
                ->select('city', DB::raw('COUNT(*) as count'))
                ->groupBy('city')
                ->orderByDesc('count')
                ->limit(12)
                ->get();

            // 近隣在庫バイク
            $nearbyListings = Listing::whereHas('shop', fn($q) => $q->where('prefecture', $prefecture)->where('city', $city))
                ->where('is_sold_out', false)
                ->with(['shop', 'bikeModel'])
                ->orderByDesc('created_at')
                ->limit(6)
                ->get();

            $shopsWithCoords = $shops->filter(fn($s) => $s->latitude && $s->longitude);

            return [
                'prefecture' => $prefecture,
                'city' => $city,
                'shops' => $shops,
                'totalShops' => $shops->count(),
                'totalListings' => $totalListings,
                'avgPrice' => $avgPrice ? (int) round((float) $avgPrice) : null,
                'makerDistribution' => $makerDistribution,
                'priceRanges' => $priceRanges,
                'siblingCities' => $siblingCities,
                'avgLat' => $shopsWithCoords->avg('latitude') ?: 35.6762,
                'avgLng' => $shopsWithCoords->avg('longitude') ?: 139.6503,
                'nearbyListings' => $nearbyListings,
            ];
        });
    }

    /**
     * 全都道府県の一覧（サイトマップ用）
     */
    public function getAllPrefectures(): Collection
    {
        return collect(config('parking.regions', []))
            ->flatMap(fn($prefs) => array_keys($prefs));
    }

    /**
     * 都道府県内の全市区町村リスト（サイトマップ用）
     */
    public function getCitiesForPrefecture(string $prefecture): Collection
    {
        return Shop::where('prefecture', $prefecture)
            ->whereNotNull('city')
            ->where('city', '!=', '')
            ->select('city')
            ->distinct()
            ->pluck('city');
    }

    /**
     * 市区町村のショップ数を取得（サイトマップフィルタ用）
     */
    public function getShopCountForCity(string $prefecture, string $city): int
    {
        return Shop::where('prefecture', $prefecture)
            ->where('city', $city)
            ->count();
    }
}
