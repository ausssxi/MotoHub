<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Listing;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * バイクの出品情報に関するデータ操作を担当
 */
final class ListingRepository
{
    /**
     * キーワードと並び替え条件に基づいて出品情報を取得
     */
    public function searchByKeyword(string $keyword, string $sort = 'latest', int $perPage = 30): LengthAwarePaginator
    {
        $query = $this->baseSearchQuery($keyword);

        // 並び替えロジックの適用
        $query = match ($sort) {
            'price_asc'  => $query->orderBy('total_price', 'asc'),
            'price_desc' => $query->orderBy('total_price', 'desc'),
            default      => $query->orderBy('created_at', 'desc'), // latest
        };

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * 有効な出品情報の総数を取得
     */
    public function countActiveListings(): int
    {
        return Listing::where('is_sold_out', false)->count();
    }

    /**
     * 検索の基本クエリ
     */
    private function baseSearchQuery(string $keyword)
    {
        return Listing::with(['bikeModel.manufacturer', 'shop', 'site'])
            ->where(function($lq) use ($keyword) {
                $lq->where('title', 'like', "%{$keyword}%")
                      ->orWhereHas('bikeModel', function($bq) use ($keyword) {
                          $bq->where('name', 'like', "%{$keyword}%")
                            ->orWhereHas('manufacturer', function($mq) use ($keyword) {
                                $mq->where('name', 'like', "%{$keyword}%");
                            });
                      });
            })
            ->where('is_sold_out', false);
    }
}