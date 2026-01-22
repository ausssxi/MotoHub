<?php

declare(strict_types=1);

namespace App\Repositories\Bike;

use App\Models\Listing;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;

/**
 * バイクの出品情報に関する検索・統計操作を担当
 */
final class ListingRepository
{
    /**
     * メイン検索
     */
    public function searchByKeyword(?string $keyword, ?string $prefecture, string $sort, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = $this->buildFilteredQuery($keyword, $prefecture, $filters);

        // ソート適用（現状のロジック維持）
        $query = match ($sort) {
            'price_asc'    => $query->whereNotNull('total_price')->orderBy('total_price', 'asc'),
            'price_desc'   => $query->whereNotNull('total_price')->orderBy('total_price', 'desc'),
            'mileage_asc'  => $query->whereNotNull('mileage')->orderBy('mileage', 'asc'),
            'mileage_desc' => $query->whereNotNull('mileage')->orderBy('mileage', 'desc'),
            'year_desc'    => $query->orderBy('model_year', 'desc'),
            'year_asc'     => $query->orderBy('model_year', 'asc'),
            default        => $query->orderBy('created_at', 'desc'),
        };

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * 相場統計の取得
     */
    public function getPriceStats(?string $keyword, ?string $prefecture, array $filters): object
    {
        return $this->buildFilteredQuery($keyword, $prefecture, $filters)
            ->select([
                DB::raw('AVG(total_price) as avg_price'),
                DB::raw('MIN(total_price) as min_price'),
                DB::raw('MAX(total_price) as max_price'),
                DB::raw('COUNT(*) as count')
            ])
            ->where('total_price', '>', 10000)
            ->where('total_price', '<', 50000000)
            ->first();
    }

    /**
     * スライダー境界値の取得
     */
    public function getMinMaxStats(?string $keyword = null, ?string $prefecture = null): object
    {
        $query = Listing::query()->active()
            ->where('total_price', '<', 100000000)
            ->where('mileage', '<', 1000000)
            ->where('model_year', '<=', (int)date('Y') + 1);

        // スライダー境界値計算用のキーワード・地域フィルタ（現状維持）
        if ($keyword) $query->where('title', 'like', "%{$keyword}%");
        if ($prefecture) $query->whereHas('shop', fn($sq) => $sq->where('prefecture', 'like', "{$prefecture}%"));

        return $query->select([
                DB::raw('MAX(total_price) as max_price'),
                DB::raw('MAX(mileage) as max_mileage'),
                DB::raw('MIN(model_year) as min_year'),
                DB::raw('MAX(model_year) as max_year'),
            ])
            ->toBase()
            ->first();
    }

    /**
     * 全有効台数
     */
    public function countActiveListings(): int
    {
        return Listing::active()->count();
    }

    /**
     * 共通のフィルタリングクエリを構築
     * スコープをチェーンすることで、以前の if 文の塊を解消しました。
     */
    private function buildFilteredQuery(?string $keyword, ?string $prefecture, array $filters): Builder
    {
        $meta = $this->getMinMaxStats($keyword, $prefecture);
        $uiMaxPrice = max(300, (int) ceil(($meta->max_price ?? 0) / 50000) * 5); 
        $uiMaxMileage = max(50000, (int) ceil(($meta->max_mileage ?? 0) / 1000) * 1000);

        return Listing::query()
            ->with(['bikeModel.manufacturer', 'shop', 'site'])
            ->active()
            // Model Scope を活用
            ->withKeyword($keyword, !empty($filters['bike_model_id']))
            ->byPrefecture($prefecture ?: ($filters['prefecture'] ?? null))
            ->byModel($filters['manufacturer_id'] ?? null, $filters['bike_model_id'] ?? null)
            ->byCondition($filters['is_new'] ?? null)
            ->byRepairHistory($filters['has_repair_history'] ?? null)
            ->priceBetween($filters['min_price'] ?? null, $filters['max_price'] ?? null, $uiMaxPrice)
            ->mileageBetween($filters['min_mileage'] ?? null, $filters['max_mileage'] ?? null, $uiMaxMileage)
            ->yearBetween($filters['min_year'] ?? null, $filters['max_year'] ?? null);
    }
}