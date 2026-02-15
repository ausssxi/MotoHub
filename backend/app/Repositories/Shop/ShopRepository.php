<?php

declare(strict_types=1);

namespace App\Repositories\Shop;

use App\Models\Shop;
use Illuminate\Database\Eloquent\Collection;

final class ShopRepository
{
    /**
     * IDで店舗を取得 (既存メソッド)
     */
    public function find(int $id): ?Shop
    {
        return Shop::find($id);
    }

    /**
     * IDで店舗を取得（見つからない場合は404エラー）
     * 詳細ページなどで使用
     */
    public function findOrFail(int $id): Shop
    {
        return Shop::findOrFail($id);
    }

    /**
     * 指定された矩形範囲（緯度経度）に含まれる店舗を取得
     * 地図検索で使用
     */
    public function findInBounds(float $swLat, float $swLng, float $neLat, float $neLng, int $limit = 100): Collection
    {
        return Shop::query()
            ->select(['id', 'name', 'address', 'latitude', 'longitude', 'prefecture'])
            ->whereNotNull('latitude')
            ->whereBetween('latitude', [$swLat, $neLat])
            ->whereBetween('longitude', [$swLng, $neLng])
            ->withCount(['listings' => function ($q) {
                $q->where('is_sold_out', false);
            }])
            ->limit($limit)
            ->get();
    }
}