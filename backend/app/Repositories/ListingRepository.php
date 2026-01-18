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
     * キーワード、都道府県、および並び替え条件に基づいて出品情報を取得
     */
    public function searchByKeyword(?string $keyword, ?string $prefecture = null, string $sort = 'latest', int $perPage = 30): LengthAwarePaginator
    {
        $query = $this->baseSearchQuery($keyword, $prefecture);

        // 並び替えロジックの適用
        // 価格や走行距離の不明(NULL)を「高い/多い方」として扱うため orderByRaw を使用
        $query = match ($sort) {
            // 安い順：NULL以外を先に（IS NULL ASC）、その中で昇順
            'price_asc'    => $query->orderByRaw('total_price IS NULL ASC, total_price ASC'),
            // 高い順：NULLを先に（IS NULL DESC）、その中で降順
            'price_desc'   => $query->orderByRaw('total_price IS NULL DESC, total_price DESC'),
            // 少ない順：NULL以外を先に（IS NULL ASC）、その中で昇順
            'mileage_asc'  => $query->orderByRaw('mileage IS NULL ASC, mileage ASC'),
            // 多い順：NULLを先に（IS NULL DESC）、その中で降順
            'mileage_desc' => $query->orderByRaw('mileage IS NULL DESC, mileage DESC'),
            'year_desc'    => $query->orderBy('model_year', 'desc'),
            'year_asc'     => $query->orderBy('model_year', 'asc'),
            default        => $query->orderBy('created_at', 'desc'), // latest: 新着順
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
    private function baseSearchQuery(?string $keyword, ?string $prefecture = null)
    {
        $query = Listing::with(['bikeModel.manufacturer', 'shop', 'site'])
            ->where('is_sold_out', false);

        // キーワード検索（車種名、メーカー名、タイトル）
        if ($keyword) {
            $query->where(function($lq) use ($keyword) {
                $lq->where('title', 'like', "%{$keyword}%")
                      ->orWhereHas('bikeModel', function($bq) use ($keyword) {
                          $bq->where('name', 'like', "%{$keyword}%")
                            ->orWhereHas('manufacturer', function($mq) use ($keyword) {
                                $mq->where('name', 'like', "%{$keyword}%");
                            });
                      });
            });
        }

        // 都道府県検索（ショップの住所で絞り込み）
        if ($prefecture) {
            $query->whereHas('shop', function($sq) use ($prefecture) {
                $sq->where('address', 'like', "{$prefecture}%");
            });
        }

        return $query;
    }
}