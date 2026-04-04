<?php

declare(strict_types=1);

namespace App\Services\Parking;

use App\Models\BikeParking;
use App\Models\Listing;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class ParkingAreaService
{
    /**
     * エリアインデックス: 都道府県別の駐車場件数
     */
    public function getAreaIndex(): array
    {
        $regions = config('parking.regions', []);

        $prefCounts = Cache::remember('parking_area_pref_counts', 86400, function () {
            return BikeParking::active()
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
     * 都道府県詳細: 市区町村別の集計 + 料金相場
     */
    public function getPrefectureDetail(string $prefecture): ?array
    {
        $cacheKey = 'parking_area_pref_' . md5($prefecture);

        return Cache::remember($cacheKey, 86400, function () use ($prefecture) {
            $parkings = BikeParking::active()
                ->byPrefecture($prefecture)
                ->get();

            if ($parkings->isEmpty()) {
                return null;
            }

            // 市区町村別の集計
            $cities = $parkings->groupBy('city')
                ->map(function (Collection $cityParkings, string $city) {
                    $paidParkings = $cityParkings->filter(fn($p) => $p->price_per_hour > 0);
                    return [
                        'name' => $city,
                        'count' => $cityParkings->count(),
                        'free_count' => $cityParkings->where('is_free', true)->count(),
                        'avg_price_per_hour' => $paidParkings->isNotEmpty()
                            ? (int) round($paidParkings->avg('price_per_hour'))
                            : null,
                    ];
                })
                ->sortByDesc('count')
                ->values();

            // 都道府県全体の料金相場
            $paidAll = $parkings->filter(fn($p) => $p->price_per_hour > 0);
            $priceStats = [
                'avg_per_hour' => $paidAll->isNotEmpty() ? (int) round($paidAll->avg('price_per_hour')) : null,
                'min_per_hour' => $paidAll->isNotEmpty() ? (int) $paidAll->min('price_per_hour') : null,
                'max_per_hour' => $paidAll->isNotEmpty() ? (int) $paidAll->max('price_per_hour') : null,
            ];

            $paidMonthly = $parkings->filter(fn($p) => $p->price_per_month > 0);
            $priceStats['avg_per_month'] = $paidMonthly->isNotEmpty()
                ? (int) round($paidMonthly->avg('price_per_month'))
                : null;

            // 在庫バイク
            $nearbyListings = Listing::whereHas('shop', fn($q) => $q->where('prefecture', $prefecture))
                ->where('is_sold_out', 0)
                ->with(['shop', 'bikeModel'])
                ->orderByDesc('created_at')
                ->limit(6)
                ->get();

            return [
                'prefecture' => $prefecture,
                'totalCount' => $parkings->count(),
                'freeCount' => $parkings->where('is_free', true)->count(),
                'cities' => $cities,
                'priceStats' => $priceStats,
                'avgLat' => $parkings->avg('latitude'),
                'avgLng' => $parkings->avg('longitude'),
                'nearbyListings' => $nearbyListings,
            ];
        });
    }

    /**
     * 市区町村詳細: 駐車場リスト + 料金相場
     */
    public function getCityDetail(string $prefecture, string $city): ?array
    {
        $parkings = BikeParking::active()
            ->byPrefecture($prefecture)
            ->where('city', $city)
            ->orderByDesc('reviews_count')
            ->orderByDesc('used_count')
            ->get();

        if ($parkings->isEmpty()) {
            return null;
        }

        // 料金相場
        $paidHourly = $parkings->filter(fn($p) => $p->price_per_hour > 0);
        $paidMonthly = $parkings->filter(fn($p) => $p->price_per_month > 0);
        $priceStats = [
            'avg_per_hour' => $paidHourly->isNotEmpty() ? (int) round($paidHourly->avg('price_per_hour')) : null,
            'min_per_hour' => $paidHourly->isNotEmpty() ? (int) $paidHourly->min('price_per_hour') : null,
            'max_per_hour' => $paidHourly->isNotEmpty() ? (int) $paidHourly->max('price_per_hour') : null,
            'avg_per_month' => $paidMonthly->isNotEmpty() ? (int) round($paidMonthly->avg('price_per_month')) : null,
        ];

        // 同県内の他の市区町村（周辺エリア）
        $siblingCities = BikeParking::active()
            ->byPrefecture($prefecture)
            ->where('city', '!=', $city)
            ->select('city', DB::raw('COUNT(*) as count'))
            ->groupBy('city')
            ->orderByDesc('count')
            ->limit(12)
            ->get();

        // 在庫バイク
        $nearbyListings = Listing::whereHas('shop', fn($q) => $q->where('prefecture', $prefecture))
            ->where('is_sold_out', 0)
            ->with(['shop', 'bikeModel'])
            ->orderByDesc('created_at')
            ->limit(6)
            ->get();

        return [
            'prefecture' => $prefecture,
            'city' => $city,
            'parkings' => $parkings,
            'totalCount' => $parkings->count(),
            'freeCount' => $parkings->where('is_free', true)->count(),
            'priceStats' => $priceStats,
            'siblingCities' => $siblingCities,
            'avgLat' => $parkings->avg('latitude'),
            'avgLng' => $parkings->avg('longitude'),
            'nearbyListings' => $nearbyListings,
        ];
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
        return BikeParking::active()
            ->byPrefecture($prefecture)
            ->select('city')
            ->distinct()
            ->whereNotNull('city')
            ->where('city', '!=', '')
            ->pluck('city');
    }
}
