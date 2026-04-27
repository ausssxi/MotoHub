<?php

declare(strict_types=1);

namespace App\Services\Parking;

use App\Models\BikeParking;
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
        $cacheKey = "station_parking_detail_v3_{$station->id}";

        return Cache::remember($cacheKey, 3600, function () use ($station) {
            $parkings = $station->nearbyParkings(0.5)->get();

            $totalCount = $parkings->count();
            $freeCount = $parkings->where('is_free', true)->count();
            $coveredCount = $parkings->where('is_covered', true)->count();
            $available24hCount = $parkings->where('available_24h', true)->count();
            $bikeOnlyCount = $parkings->where('parking_type', 'bike_only')->count();
            $bicycleSharedCount = $parkings->where('parking_type', 'bicycle_shared')->count();

            // 大型バイク対応の判定
            $largeBikeKeywords = ['大型', '400', '750', '制限なし', '排気量制限なし'];
            $largeBikeCount = $parkings->filter(function ($p) use ($largeBikeKeywords) {
                if (!$p->vehicle_restriction) {
                    return false;
                }
                foreach ($largeBikeKeywords as $kw) {
                    if (mb_strpos($p->vehicle_restriction, $kw) !== false) {
                        return true;
                    }
                }
                return false;
            })->count();

            // 料金統計
            $hourlyPrices = $parkings->pluck('price_per_hour')->filter()->values();
            $monthlyPrices = $parkings->pluck('price_per_month')->filter()->values();
            $monthlyParkings = $parkings->filter(fn ($p) => $p->price_per_month > 0)->values();

            $priceStats = [
                'avg_per_hour' => $hourlyPrices->isNotEmpty() ? (int) round($hourlyPrices->avg()) : null,
                'min_per_hour' => $hourlyPrices->isNotEmpty() ? (int) $hourlyPrices->min() : null,
                'max_per_hour' => $hourlyPrices->isNotEmpty() ? (int) $hourlyPrices->max() : null,
                'avg_per_month' => $monthlyPrices->isNotEmpty() ? (int) round($monthlyPrices->avg()) : null,
                'min_per_month' => $monthlyPrices->isNotEmpty() ? (int) $monthlyPrices->min() : null,
                'max_per_month' => $monthlyPrices->isNotEmpty() ? (int) $monthlyPrices->max() : null,
                'monthly_count' => $monthlyParkings->count(),
                'monthly_parkings' => $monthlyParkings->take(3)->map(fn ($p) => [
                    'name' => $p->name,
                    'price' => $p->price_per_month,
                ])->values()->toArray(),
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

            // 比較表データ（時間料金安い順にソート）
            $comparisonTable = $parkings->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'price_per_hour' => $p->price_per_hour,
                'price_per_day' => $p->price_per_day,
                'price_per_month' => $p->price_per_month,
                'available_24h' => $p->available_24h,
                'is_covered' => $p->is_covered,
                'distance_m' => isset($p->distance) ? (int) round($p->distance * 1000) : null,
                'capacity' => $p->capacity,
                'is_free' => $p->is_free,
            ])->sortBy(function ($p) {
                if ($p['is_free']) return 0;
                return $p['price_per_hour'] ?? PHP_INT_MAX;
            })->values()->toArray();

            return [
                'station' => $station,
                'parkings' => $parkings,
                'totalCount' => $totalCount,
                'freeCount' => $freeCount,
                'coveredCount' => $coveredCount,
                'available24hCount' => $available24hCount,
                'largeBikeCount' => $largeBikeCount,
                'bikeOnlyCount' => $bikeOnlyCount,
                'bicycleSharedCount' => $bicycleSharedCount,
                'priceStats' => $priceStats,
                'comparisonTable' => $comparisonTable,
                'nearbyListings' => $nearbyListings,
                'siblingStations' => $siblingStations,
            ];
        });
    }

    /**
     * 駅一覧ページ用データ（主要駅 + その他の充実駅）
     */
    public function getStationIndex(): array
    {
        return Cache::remember('station_parking_index_v2', 86400, function () {
            // 主要駅
            $majorStations = Station::major()
                ->withCount(['bikeParkings'])
                ->orderBy('prefecture')
                ->orderByDesc('bike_parkings_count')
                ->get();

            $majorByPrefecture = $majorStations->groupBy('prefecture');

            // その他の駅（駐車場5件以上、主要駅除外）
            $sitemapStations = $this->getSitemapStations(5);
            $otherStations = $sitemapStations->where('is_major', false)->values();

            // name だけではソートできないのでDB再取得（slug一覧から）
            $otherSlugs = $otherStations->pluck('slug')->toArray();
            $othersFull = Station::whereIn('slug', $otherSlugs)
                ->withCount(['bikeParkings'])
                ->orderBy('prefecture')
                ->orderBy('name')
                ->get();

            $othersByPrefecture = $othersFull->groupBy('prefecture');

            return [
                'majorByPrefecture' => $majorByPrefecture,
                'totalMajor' => $majorStations->count(),
                'othersByPrefecture' => $othersByPrefecture,
                'totalOthers' => $othersFull->count(),
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

    /**
     * サイトマップ用: 主要駅 OR 半径500m以内の駐車場が閾値以上の駅
     * グリッドベース空間インデックス＋Haversineで高速計算
     *
     * @return \Illuminate\Support\Collection<int, Station>
     */
    public function getSitemapStations(int $minParkings = 5): \Illuminate\Support\Collection
    {
        return Cache::remember("sitemap_stations_{$minParkings}", 86400, function () use ($minParkings) {
            // 駐車場を空間グリッドに配置（0.01度 ≈ 1kmセル）
            $grid = [];
            $parkings = BikeParking::active()
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->select('latitude', 'longitude')
                ->get();

            foreach ($parkings as $p) {
                $cellKey = ((int) ($p->latitude * 100)).'|'.((int) ($p->longitude * 100));
                $grid[$cellKey][] = [$p->latitude, $p->longitude];
            }

            $stations = Station::select('slug', 'is_major', 'updated_at', 'latitude', 'longitude')->get();

            return $stations->filter(function (Station $station) use ($grid, $minParkings) {
                if ($station->is_major) {
                    return true;
                }

                $cLat = (int) ($station->latitude * 100);
                $cLng = (int) ($station->longitude * 100);
                $sLatRad = deg2rad($station->latitude);
                $count = 0;

                // 隣接9セルのみチェック
                for ($dLat = -1; $dLat <= 1; $dLat++) {
                    for ($dLng = -1; $dLng <= 1; $dLng++) {
                        $cellKey = ($cLat + $dLat).'|'.($cLng + $dLng);
                        foreach ($grid[$cellKey] ?? [] as [$pLat, $pLng]) {
                            $dLatR = deg2rad($pLat - $station->latitude);
                            $dLngR = deg2rad($pLng - $station->longitude);
                            $a = sin($dLatR / 2) ** 2
                                + cos($sLatRad) * cos(deg2rad($pLat)) * sin($dLngR / 2) ** 2;
                            if (6371 * 2 * atan2(sqrt($a), sqrt(1 - $a)) <= 0.5) {
                                $count++;
                                if ($count >= $minParkings) {
                                    return true;
                                }
                            }
                        }
                    }
                }

                return false;
            })->values();
        });
    }
}
