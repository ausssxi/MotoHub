<?php

declare(strict_types=1);

namespace App\Repositories\Bike;

use App\Models\Listing;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * バイクの出品情報に関する「検索・取得」操作を担当
 */
final class ListingRepository
{
    /**
     * メイン検索
     *
     * @param array $uiParams スライダーUIの上限値など（StatsRepositoryで計算されたもの）
     */
    public function searchByKeyword(?string $keyword, ?string $prefecture, string $sort, array $filters, int $perPage, array $uiParams = []): LengthAwarePaginator
    {
        $query = $this->buildFilteredQuery($keyword, $prefecture, $filters, $uiParams);

        // ソート適用
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
     * 全有効台数
     */
    public function countActiveListings(): int
    {
        return Listing::active()->count();
    }

    /**
     * 同じ車種の関連車両を取得（価格の安い順）
     */
    public function getRelatedListings(int $bikeModelId, int $excludeId, int $limit = 10): Collection
    {
        return Listing::with(['shop']) // 画像などのリレーションはResourceで解決するため最小限に
            ->where('bike_model_id', $bikeModelId)
            ->where('id', '!=', $excludeId) // 自分自身を除外
            ->where('is_sold_out', false)
            ->whereNotNull('total_price')
            ->where('total_price', '>', 0)
            ->orderBy('total_price', 'asc') // 安い順
            ->limit($limit)
            ->get();
    }

    /**
     * 共通のフィルタリングクエリを構築
     */
    private function buildFilteredQuery(?string $keyword, ?string $prefecture, array $filters, array $uiParams): Builder
    {
        // UI用の最大値（スライダーの右端）を取得
        // これがないと「35万円以下」を選択した際に上限が固定されず、正しく絞り込めない場合があるため
        $maxPrice = $uiParams['max_price'] ?? null;
        $maxMileage = $uiParams['max_mileage'] ?? null;

        return Listing::query()
            ->with(['bikeModel.manufacturer', 'shop', 'site'])
            ->active()
            // Model Scope を活用して可読性を向上
            ->withKeyword($keyword, !empty($filters['bike_model_id']))
            ->byPrefecture($prefecture ?: ($filters['prefecture'] ?? null))
            ->byModel($filters['manufacturer_id'] ?? null, $filters['bike_model_id'] ?? null)
            ->byCondition($filters['is_new'] ?? null)
            ->byRepairHistory($filters['has_repair_history'] ?? null)
            ->priceBetween($filters['min_price'] ?? null, $filters['max_price'] ?? null, $maxPrice)
            ->mileageBetween($filters['min_mileage'] ?? null, $filters['max_mileage'] ?? null, $maxMileage)
            ->yearBetween($filters['min_year'] ?? null, $filters['max_year'] ?? null);
    }
}