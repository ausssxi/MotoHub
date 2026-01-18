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
     * 検索とフィルタリング、並び替えを実行
     */
    public function searchByKeyword(
        ?string $keyword, 
        ?string $prefecture = null, 
        string $sort = 'latest', 
        array $filters = [], 
        int $perPage = 30
    ): LengthAwarePaginator {
        $query = $this->baseSearchQuery($keyword, $prefecture, $filters);

        // 並び替えロジック
        // IS NULL を使うことで、不明なデータを「一番高い/多い」扱いで末尾または先頭に持っていきます
        switch ($sort) {
            case 'price_asc':
                $query->orderByRaw('total_price IS NULL ASC, total_price ASC');
                break;
            case 'price_desc':
                $query->orderByRaw('total_price IS NULL DESC, total_price DESC');
                break;
            case 'mileage_asc':
                $query->orderByRaw('mileage IS NULL ASC, mileage ASC');
                break;
            case 'mileage_desc':
                $query->orderByRaw('mileage IS NULL DESC, mileage DESC');
                break;
            case 'year_desc':
                $query->orderBy('model_year', 'desc');
                break;
            case 'year_asc':
                $query->orderBy('model_year', 'asc');
                break;
            default:
                $query->orderBy('created_at', 'desc');
                break;
        }

        return $query->paginate($perPage)->withQueryString();
    }

    public function countActiveListings(): int
    {
        return Listing::where('is_sold_out', false)->count();
    }

    /**
     * 基本クエリと絞り込みフィルター
     */
    private function baseSearchQuery(?string $keyword, ?string $prefecture = null, array $filters = [])
    {
        $query = Listing::with(['bikeModel.manufacturer', 'shop', 'site'])
            ->where('is_sold_out', false);

        // キーワード検索
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

        // 都道府県
        if ($prefecture) {
            $query->whereHas('shop', function($sq) use ($prefecture) {
                $sq->where('address', 'like', "{$prefecture}%");
            });
        }

        // --- フィルター適用 ---
        
        // 価格 (万円を円に換算)
        if (!empty($filters['min_price'])) {
            $query->where('total_price', '>=', (int)$filters['min_price'] * 10000);
        }
        if (!empty($filters['max_price'])) {
            $query->where('total_price', '<=', (int)$filters['max_price'] * 10000);
        }

        // 走行距離
        if (isset($filters['min_mileage']) && $filters['min_mileage'] !== '') {
            $query->where('mileage', '>=', (int)$filters['min_mileage']);
        }
        if (isset($filters['max_mileage']) && $filters['max_mileage'] !== '') {
            $query->where('mileage', '<=', (int)$filters['max_mileage']);
        }

        // 年式
        if (!empty($filters['min_year'])) {
            $query->where('model_year', '>=', (int)$filters['min_year']);
        }
        if (!empty($filters['max_year'])) {
            $query->where('model_year', '<=', (int)$filters['max_year']);
        }

        return $query;
    }
}