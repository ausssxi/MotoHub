<?php

declare(strict_types=1);

namespace App\Services\Parking;

use App\Models\Listing;
use App\Models\Station;
use Illuminate\Support\Facades\Cache;

final class StationParkingService
{
    /**
     * 駅詳細ページ用データ
     */
    public function getStationDetail(Station $station): array
    {
        $cacheKey = "station_parking_detail_{$station->id}";

        return Cache::remember($cacheKey, 86400, function () use ($station) {
            $parkings = $station->nearbyParkings(0.5)->get();

            $totalCount = $parkings->count();
            $freeCount = $parkings->where('is_free', true)->count();

            // 料金統計
            $hourlyPrices = $parkings->pluck('price_per_hour')->filter()->values();
            $monthlyPrices = $parkings->pluck('price_per_month')->filter()->values();

            $priceStats = [
                'avg_per_hour' => $hourlyPrices->isNotEmpty() ? (int) round($hourlyPrices->avg()) : null,
                'min_per_hour' => $hourlyPrices->isNotEmpty() ? (int) $hourlyPrices->min() : null,
                'max_per_hour' => $hourlyPrices->isNotEmpty() ? (int) $hourlyPrices->max() : null,
                'avg_per_month' => $monthlyPrices->isNotEmpty() ? (int) round($monthlyPrices->avg()) : null,
            ];

            // 近隣のバイク在庫（prefectureはshopsテーブル）
            $prefShort = mb_substr($station->prefecture, 0, -1);
            $nearbyListings = Listing::whereHas('shop', fn ($q) => $q->where('prefecture', $prefShort))
                ->where('is_sold_out', false)
                ->whereNotNull('total_price')
                ->with(['bikeModel', 'shop'])
                ->inRandomOrder()
                ->limit(6)
                ->get();

            // 同じ都道府県の他の駅（主要駅のみ）
            $siblingStations = Station::where('prefecture', $station->prefecture)
                ->where('id', '!=', $station->id)
                ->major()
                ->withCount(['bikeParkings'])
                ->orderByDesc('bike_parkings_count')
                ->limit(10)
                ->get();

            return [
                'station' => $station,
                'parkings' => $parkings,
                'totalCount' => $totalCount,
                'freeCount' => $freeCount,
                'priceStats' => $priceStats,
                'nearbyListings' => $nearbyListings,
                'siblingStations' => $siblingStations,
            ];
        });
    }

    /**
     * 駅一覧ページ用データ（主要駅のみ）
     */
    public function getStationIndex(): array
    {
        return Cache::remember('station_parking_index', 86400, function () {
            $stations = Station::major()
                ->withCount(['bikeParkings'])
                ->orderBy('prefecture')
                ->orderByDesc('bike_parkings_count')
                ->get();

            // 都道府県別にグループ化
            $grouped = $stations->groupBy('prefecture');

            return [
                'stationsByPrefecture' => $grouped,
                'totalStations' => $stations->count(),
            ];
        });
    }

    /**
     * サイトマップ用: 主要駅のslug一覧
     */
    public function getMajorStationSlugs(): array
    {
        return Station::major()->pluck('slug')->toArray();
    }
}
