<?php

declare(strict_types=1);

namespace App\Repositories\Bike;

use App\Models\Listing;
use Illuminate\Support\Collection;

/**
 * バイクの出品情報に関するデータ操作を担当
 */
final class ListingRepository
{
    /**
     * キーワードに基づいて出品情報を取得
     *
     * @param string $keyword
     * @return Collection
     */
    public function searchByKeyword(string $keyword): Collection
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
            ->where('is_sold_out', false)
            ->orderBy('created_at', 'desc')
            ->limit(30)
            ->get();
    }
}