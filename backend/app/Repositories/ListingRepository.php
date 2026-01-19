<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Listing;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * バイクの出品情報に関するデータ操作を担当
 */
final class ListingRepository
{
    /**
     * 検索、フィルタリング、および並び替えを実行
     */
    public function searchByKeyword(
        ?string $keyword, 
        ?string $prefecture = null, 
        string $sort = 'latest', 
        array $filters = [], 
        int $perPage = 30
    ): LengthAwarePaginator {
        try {
            $query = $this->baseSearchQuery($keyword, $prefecture, $filters);

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

        } catch (\Exception $e) {
            Log::error("Search Error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 有効な在庫データの最小・最大統計値を取得
     * クエリ（データの抽出）のみを担当します
     */
    public function getMinMaxStats(): object
    {
        return DB::table('listings')
            ->where('is_sold_out', false)
            ->select([
                DB::raw('MIN(total_price) as min_price'),
                DB::raw('MAX(total_price) as max_price'),
                DB::raw('MIN(mileage) as min_mileage'),
                DB::raw('MAX(mileage) as max_mileage'),
                DB::raw('MIN(model_year) as min_year'),
                DB::raw('MAX(model_year) as max_year'),
            ])->first();
    }

    /**
     * 有効な出品情報の総数を取得
     */
    public function countActiveListings(): int
    {
        return Listing::where('is_sold_out', false)->count();
    }

    /**
     * 検索の基本クエリ構築
     */
    private function baseSearchQuery(?string $keyword, ?string $prefecture = null, array $filters = [])
    {
        $query = Listing::with(['bikeModel.manufacturer', 'shop'])
            ->where('is_sold_out', false);

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

        if ($prefecture) {
            $query->whereHas('shop', function($sq) use ($prefecture) {
                $sq->where('address', 'like', "{$prefecture}%");
            });
        }

        if (!empty($filters['min_price'])) {
            $query->where('total_price', '>=', (int)$filters['min_price'] * 10000);
        }
        if (!empty($filters['max_price'])) {
            $query->where('total_price', '<=', (int)$filters['max_price'] * 10000);
        }
        if (isset($filters['max_mileage']) && $filters['max_mileage'] !== '') {
            $query->where('mileage', '<=', (int)$filters['max_mileage']);
        }
        if (!empty($filters['min_year'])) {
            $query->where('model_year', '>=', (int)$filters['min_year']);
        }
        if (!empty($filters['max_year'])) {
            $query->where('model_year', '<=', (int)$filters['max_year']);
        }

        return $query;
    }
}