<?php

declare(strict_types=1);

namespace App\Repositories\Bike;

use App\Models\Listing;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * バイクの出品情報に関する「検索・取得」操作を担当
 * カラム選択の最適化とインデックス利用を強化した高速版
 */
final class ListingRepository
{
    /**
     * 一覧表示に必要な基本カラムのリスト
     * description（長文）を除外してデータ転送量を大幅に削減します
     */
    private const LIST_COLUMNS = [
        'listings.id', 
        'listings.bike_model_id', 
        'listings.shop_id', 
        'listings.manufacturer_id', 
        'listings.category_id',
        'listings.title', 
        'listings.model_year', 
        'listings.mileage', 
        'listings.displacement',
        'listings.total_price', 
        'listings.price', 
        'listings.condition', 
        'listings.is_sold_out',
        'listings.image_urls', 
        'listings.local_image_paths', 
        'listings.created_at'
    ];

    /**
     * メイン検索
     *
     * @param array $uiParams スライダーUIの上限値など
     */
    public function searchByKeyword(?string $keyword, ?string $prefecture, string $sort, array $filters, int $perPage, array $uiParams = []): LengthAwarePaginator
    {
        $query = $this->buildFilteredQuery($keyword, $prefecture, $filters, $uiParams);

        // ★軽量化: 必要なカラムだけに絞る
        $query->select(self::LIST_COLUMNS);

        // ソート適用（インデックス idx_active_... シリーズを活用）
        $query = match ($sort) {
            'price_asc'    => $query->whereNotNull('listings.total_price')->orderBy('listings.total_price', 'asc'),
            'price_desc'   => $query->whereNotNull('listings.total_price')->orderBy('listings.total_price', 'desc'),
            'mileage_asc'  => $query->whereNotNull('listings.mileage')->orderBy('listings.mileage', 'asc'),
            'mileage_desc' => $query->whereNotNull('listings.mileage')->orderBy('listings.mileage', 'desc'),
            'year_desc'    => $query->orderBy('listings.model_year', 'desc'),
            'year_asc'     => $query->orderBy('listings.model_year', 'asc'),
            default        => $query->orderBy('listings.created_at', 'desc'),
        };

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * 店舗ごとの在庫一覧を取得
     */
    public function getByShopId(int $shopId, int $perPage = 30): LengthAwarePaginator
    {
        return Listing::query()
            ->select(self::LIST_COLUMNS) // 軽量化
            ->with(['bikeModel.manufacturer', 'shop', 'site'])
            ->active()
            ->where('shop_id', $shopId)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
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
     * インデックス idx_related_lookup を使用して爆速で取得します
     */
    public function getRelatedListings(int $bikeModelId, int $excludeId, int $limit = 10): Collection
    {
        return Listing::query()
            // 最小限のカラムに絞る
            ->select([
                'id', 'bike_model_id', 'shop_id', 'title', 'total_price', 
                'model_year', 'mileage', 'image_urls', 'local_image_paths'
            ])
            ->with(['shop:id,prefecture']) // ショップも都道府県のみ取得
            ->where('bike_model_id', $bikeModelId)
            ->where('id', '!=', $excludeId)
            ->where('is_sold_out', false)
            ->whereNotNull('total_price')
            ->orderBy('total_price', 'asc') // ★重要: インデックスを効かせる
            ->limit($limit)
            ->get();
    }

    /**
     * 共通のフィルタリングクエリを構築
     */
    private function buildFilteredQuery(?string $keyword, ?string $prefecture, array $filters, array $uiParams): Builder
    {
        $maxPrice = $uiParams['max_price'] ?? null;
        $maxMileage = $uiParams['max_mileage'] ?? null;

        return Listing::query()
            ->with([
                'bikeModel.manufacturer', 
                'bikeModel.categoryData', 
                'bikeModel.marketStats', // ★N+1問題対策：お買い得バッジ計算用
                'shop', 
                'site'
            ])
            ->active()
            // Model Scope を活用（JOIN最適化済みを想定）
            ->withKeyword($keyword)
            ->byPrefecture($prefecture ?: ($filters['prefecture'] ?? null))
            ->byModel($filters['manufacturer_id'] ?? null, $filters['bike_model_id'] ?? null)
            ->byCategory($filters['category_id'] ?? null)
            ->byCondition($filters['is_new'] ?? null)
            ->byRepairHistory($filters['has_repair_history'] ?? null)
            ->priceBetween($filters['min_price'] ?? null, $filters['max_price'] ?? null, $maxPrice)
            ->mileageBetween($filters['min_mileage'] ?? null, $filters['max_mileage'] ?? null, $maxMileage)
            ->yearBetween($filters['min_year'] ?? null, $filters['max_year'] ?? null)
            ->displacementBetween($filters['min_displacement'] ?? null, $filters['max_displacement'] ?? null);
    }
    
    /**
     * 特定のモデルIDに紐づく有効な価格リストを取得する
     * ※バッチ処理（UpdateMarketStats）などで利用
     */
    public function findValidPricesByModelId(int $bikeModelId): array
    {
        return Listing::where('bike_model_id', $bikeModelId)
            ->active()
            ->whereNotNull('total_price')
            ->where('total_price', '>', 10000) // 1万円以下は異常値として除外
            ->pluck('total_price')
            ->toArray();
    }
}