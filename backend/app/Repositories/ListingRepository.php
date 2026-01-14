<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Listing;
use Illuminate\Support\Collection;

/**
 * バイクの出品情報に関するデータ操作を担当
 */
final class ListingRepository
{
    /**
     * キーワードに基づいて出品情報を取得（リミットあり）
     */
    public function searchByKeyword(string $keyword): Collection
    {
        return $this->baseSearchQuery($keyword)
            ->orderBy('created_at', 'desc')
            ->limit(30)
            ->get();
    }

    /**
     * キーワードに一致する出品情報の総数を取得
     */
    public function countByKeyword(string $keyword): int
    {
        return $this->baseSearchQuery($keyword)->count();
    }

    /**
     * 有効な出品情報（全サイト）の総数を取得
     */
    public function countActiveListings(): int
    {
        return Listing::where('is_sold_out', false)->count();
    }

    /**
     * 検索の基本クエリ（重複を避けるために共通化）
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