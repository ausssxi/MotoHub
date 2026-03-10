<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BikeParking;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Collection;

/**
 * 近くの駐車場・ショップを検索するサービス
 * 緯度±0.045 / 経度±0.055（約5km圏）のバウンディングボックスで検索
 */
final class NearbyService
{
    private const LAT_OFFSET = 0.045;
    private const LNG_OFFSET = 0.055;
    private const LIMIT = 8;

    public function getNearbyParkings(float $lat, float $lng, ?int $excludeId = null): Collection
    {
        return BikeParking::query()
            ->inBounds(
                $lat - self::LAT_OFFSET,
                $lng - self::LNG_OFFSET,
                $lat + self::LAT_OFFSET,
                $lng + self::LNG_OFFSET
            )
            ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))
            ->select(['id', 'name', 'address', 'latitude', 'longitude', 'parking_type', 'avg_rating', 'reviews_count', 'price_per_hour', 'price_per_day', 'price_per_month', 'is_covered', 'is_locked'])
            ->limit(self::LIMIT)
            ->get();
    }

    public function getNearbyShops(float $lat, float $lng, ?int $excludeId = null): Collection
    {
        return Shop::query()
            ->select(['id', 'name', 'address', 'latitude', 'longitude', 'prefecture'])
            ->whereNotNull('latitude')
            ->whereBetween('latitude', [$lat - self::LAT_OFFSET, $lat + self::LAT_OFFSET])
            ->whereBetween('longitude', [$lng - self::LNG_OFFSET, $lng + self::LNG_OFFSET])
            ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))
            ->withCount(['listings' => fn($q) => $q->where('is_sold_out', false)])
            ->limit(self::LIMIT)
            ->get();
    }
}
