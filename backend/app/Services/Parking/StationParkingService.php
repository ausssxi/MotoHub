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
        $cacheKey = "station_parking_detail_v4_{$station->id}";

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

            // 用途別おすすめ
            $reservationCompanies = ['akippa株式会社', '株式会社アース・カー'];
            $recommendations = [];

            // 短時間: price_per_hour最安（is_free除外）
            $hourlyBest = $parkings->filter(fn ($p) => !$p->is_free && $p->price_per_hour > 0)
                ->sortBy([['price_per_hour', 'asc'], ['distance', 'asc']])
                ->first();
            if ($hourlyBest) {
                $recommendations[] = [
                    'category' => '短時間利用',
                    'emoji' => '⏱️',
                    'icon' => 'clock',
                    'id' => $hourlyBest->id,
                    'name' => $hourlyBest->name,
                    'price_label' => number_format($hourlyBest->price_per_hour) . '円/時',
                    'distance_m' => isset($hourlyBest->distance) ? (int) round($hourlyBest->distance * 1000) : null,
                    'is_reservation' => in_array($hourlyBest->management_company, $reservationCompanies, true),
                ];
            }

            // 終日: price_per_day最安、なければprice_per_hour×8で推定
            $dailyBest = $parkings->filter(fn ($p) => !$p->is_free && ($p->price_per_day > 0 || $p->price_per_hour > 0))
                ->sortBy(function ($p) {
                    $day = $p->price_per_day > 0 ? $p->price_per_day : $p->price_per_hour * 8;
                    return [$day, $p->distance ?? PHP_INT_MAX];
                })
                ->first();
            if ($dailyBest) {
                $isEstimated = !$dailyBest->price_per_day;
                $dayPrice = $dailyBest->price_per_day > 0 ? $dailyBest->price_per_day : $dailyBest->price_per_hour * 8;
                $recommendations[] = [
                    'category' => '終日利用',
                    'emoji' => '☀️',
                    'icon' => 'sun',
                    'id' => $dailyBest->id,
                    'name' => $dailyBest->name,
                    'price_label' => number_format($dayPrice) . '円/日' . ($isEstimated ? '（推定）' : ''),
                    'distance_m' => isset($dailyBest->distance) ? (int) round($dailyBest->distance * 1000) : null,
                    'is_reservation' => in_array($dailyBest->management_company, $reservationCompanies, true),
                ];
            }

            // 月極: price_per_month最安
            $monthlyBest = $parkings->filter(fn ($p) => $p->price_per_month > 0)
                ->sortBy([['price_per_month', 'asc'], ['distance', 'asc']])
                ->first();
            if ($monthlyBest) {
                $recommendations[] = [
                    'category' => '月極利用',
                    'emoji' => '📅',
                    'icon' => 'calendar',
                    'id' => $monthlyBest->id,
                    'name' => $monthlyBest->name,
                    'price_label' => number_format($monthlyBest->price_per_month) . '円/月',
                    'distance_m' => isset($monthlyBest->distance) ? (int) round($monthlyBest->distance * 1000) : null,
                    'is_reservation' => in_array($monthlyBest->management_company, $reservationCompanies, true),
                ];
            }

            // この駅のポイント
            $stationPoints = [];

            // 屋根付き率
            if ($totalCount > 0) {
                $coveredRate = $coveredCount / $totalCount * 100;
                if ($coveredCount === 0) {
                    $stationPoints[] = ['icon' => 'cloud-rain', 'text' => '屋根付きの駐車場はありません。雨天時はバイクカバーの持参をおすすめします。'];
                } elseif ($coveredRate < 30) {
                    $stationPoints[] = ['icon' => 'cloud-rain', 'text' => "屋根付きは{$coveredCount}件（" . round($coveredRate) . "%）と少なめ。雨天時は早めの確保を。"];
                } elseif ($coveredRate >= 70) {
                    $stationPoints[] = ['icon' => 'umbrella', 'text' => "屋根付き駐車場が{$coveredCount}件（" . round($coveredRate) . "%）と充実しています。"];
                }
            }

            // 24時間率
            if ($totalCount > 0) {
                if ($available24hCount === 0) {
                    $stationPoints[] = ['icon' => 'moon', 'text' => '24時間営業の駐車場はありません。営業時間にご注意ください。'];
                } elseif ($available24hCount / $totalCount >= 0.5) {
                    $stationPoints[] = ['icon' => 'clock', 'text' => "24時間利用可能な駐車場が{$available24hCount}件と半数以上。深夜・早朝も安心です。"];
                }
            }

            // 時間料金幅
            if ($hourlyPrices->isNotEmpty() && $hourlyPrices->max() - $hourlyPrices->min() > 200) {
                $stationPoints[] = ['icon' => 'trending-up', 'text' => '時間料金は' . number_format($hourlyPrices->min()) . '〜' . number_format($hourlyPrices->max()) . '円/時と幅があります。料金比較がおすすめです。'];
            }

            // 総収容台数
            $totalCapacity = $parkings->sum('capacity');
            if ($totalCapacity > 0) {
                $stationPoints[] = ['icon' => 'layers', 'text' => "周辺の総収容台数は約{$totalCapacity}台です。"];
            }

            // 月極対応
            $monthlyAvailableCount = $parkings->filter(fn ($p) => $p->price_per_month > 0)->count();
            if ($monthlyAvailableCount === 0) {
                $stationPoints[] = ['icon' => 'calendar-x', 'text' => '月極プランのある駐車場は現在見つかりませんでした。'];
            } elseif ($monthlyAvailableCount >= 3) {
                $stationPoints[] = ['icon' => 'calendar-check', 'text' => "月極対応の駐車場が{$monthlyAvailableCount}件あり、通勤・通学にも便利です。"];
            }

            // 最大6項目
            $stationPoints = array_slice($stationPoints, 0, 6);

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
                'recommendations' => $recommendations,
                'stationPoints' => $stationPoints,
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
