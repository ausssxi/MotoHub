<?php

declare(strict_types=1);

namespace App\Repositories\Bike;

use App\Models\Listing;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * バイクの出品情報に関する「検索・取得」操作を担当
 * カラム選択の最適化とインデックス利用を強化した高速版
 */
final class ListingRepository
{
    /**
     * 一覧表示に必要な基本カラムのリスト
     */
    private const LIST_COLUMNS = [
        'listings.id', 'listings.bike_model_id', 'listings.shop_id', 
        'listings.manufacturer_id', 'listings.category_id', 'listings.site_id',
        'listings.title', 'listings.model_year', 'listings.mileage', 
        'listings.displacement', 'listings.total_price', 'listings.price', 
        'listings.condition', 'listings.is_sold_out', 'listings.image_urls', 
        'listings.created_at', 'listings.bargain_score',
        'listings.view_count_today', 'listings.favorite_count'
    ];

    /**
     * 閲覧数をインクリメント
     */
    public function incrementViewCount(int $id): void
    {
        Listing::where('id', $id)->incrementEach([
            'view_count_total' => 1,
            'view_count_today' => 1,
        ]);
    }

    /**
     * メイン検索
     * whereHasをJOIN方式のサブクエリに置き換え、simplePaginateで高速化
     */
    public function searchByKeyword(?string $keyword, ?string $prefecture, string $sort, array $filters, int $perPage, array $uiParams = []): Paginator
    {
        $query = Listing::query()
            ->select(self::LIST_COLUMNS)
            ->with([
                'bikeModel:id,manufacturer_id,name', 
                'bikeModel.manufacturer:id,name', 
                'shop:id,name,prefecture', 
                'site:id,name',
                'tags:id,name'
            ])
            ->active();

        // --- 1. タグ検索の高速化 (JOINサブクエリ) ---
        if (!empty($filters['tag'])) {
            $query->whereIn('listings.id', function($q) use ($filters) {
                $q->select('listing_tag.listing_id')
                  ->from('listing_tag')
                  ->join('tags', 'tags.id', '=', 'listing_tag.tag_id')
                  ->where('tags.slug', $filters['tag']);
            });
        }

        // --- 2. 基本条件の適用 ---
        $query->withKeyword($keyword)
              ->byPrefecture($prefecture ?: ($filters['prefecture'] ?? null))
              ->byModel($filters['manufacturer_id'] ?? null, $filters['bike_model_id'] ?? null)
              ->byCategory($filters['category_id'] ?? null)
              ->byCondition($filters['is_new'] ?? null)
              ->byRepairHistory($filters['has_repair_history'] ?? null);

        // --- 3. スライダー条件の適用 ---
        $query->priceBetween($filters['min_price'] ?? null, $filters['max_price'] ?? null, $uiParams['max_price'] ?? null)
              ->mileageBetween($filters['min_mileage'] ?? null, $filters['max_mileage'] ?? null, $uiParams['max_mileage'] ?? null)
              ->yearBetween($filters['min_year'] ?? null, $filters['max_year'] ?? null)
              ->displacementBetween($filters['min_displacement'] ?? null, $filters['max_displacement'] ?? null);

        // --- 4. インデックスを活かすソート ---
        $query = match ($sort) {
            'bargain_desc' => $query->orderBy('listings.bargain_score', 'desc'),
            'price_asc'    => $query->whereNotNull('listings.total_price')->orderBy('listings.total_price', 'asc'),
            'price_desc'   => $query->whereNotNull('listings.total_price')->orderBy('listings.total_price', 'desc'),
            'mileage_asc'  => $query->whereNotNull('listings.mileage')->orderBy('listings.mileage', 'asc'),
            'mileage_desc' => $query->whereNotNull('listings.mileage')->orderBy('listings.mileage', 'desc'),
            'year_desc'    => $query->orderBy('listings.model_year', 'desc'),
            'year_asc'     => $query->orderBy('listings.model_year', 'asc'),
            default        => $query->orderBy('listings.created_at', 'desc'),
        };

        return $query->simplePaginate($perPage)->withQueryString();
    }

    /**
     * 店舗ごとの在庫一覧を取得
     */
    public function getByShopId(int $shopId, int $perPage = 30): Paginator
    {
        return Listing::query()
            ->select(self::LIST_COLUMNS)
            ->with(['bikeModel:id,manufacturer_id,name', 'shop:id,name,prefecture', 'site:id,name'])
            ->active()
            ->where('shop_id', $shopId)
            ->orderBy('created_at', 'desc')
            ->simplePaginate($perPage);
    }

    /**
     * 関連車両を取得（詳細ページ用）
     */
    public function getRelatedListings(int $bikeModelId, int $excludeId, int $limit = 10): Collection
    {
        return Listing::query()
            ->select([
                'id', 'bike_model_id', 'shop_id', 'title', 'total_price', 
                'model_year', 'mileage', 'image_urls', 'created_at', 'bargain_score'
            ])
            ->with(['shop:id,prefecture'])
            ->where('bike_model_id', $bikeModelId)
            ->where('id', '!=', $excludeId)
            ->where('is_sold_out', false)
            ->whereNotNull('total_price')
            ->orderBy('total_price', 'asc')
            ->limit($limit)
            ->get();
    }

    /**
     * 車両詳細情報を取得
     */
    public function getListingDetail(int $id): Listing
    {
        return Listing::with([
            'shop', 
            'bikeModel.manufacturer', 
            'bikeModel.categoryData',
            'bikeModel.marketStats',
            'tags:id,name'
        ])->findOrFail($id);
    }

    /**
     * 類似車両を取得
     */
    public function getSimilarListings(?int $manufacturerId, ?int $excludeModelId, int $limit = 8): Collection
    {
        if (!$manufacturerId) return collect();

        return Listing::with(['shop:id,name,prefecture', 'bikeModel:id,manufacturer_id,name'])
            ->where('manufacturer_id', $manufacturerId)
            ->where('bike_model_id', '!=', $excludeModelId)
            ->where('is_sold_out', false)
            ->inRandomOrder()
            ->take($limit)
            ->get();
    }

    /**
     * 特定のモデルIDに紐づく有効な価格リストを取得
     */
    public function findValidPricesByModelId(int $bikeModelId): array
    {
        return Listing::where('bike_model_id', $bikeModelId)
            ->active()
            ->whereNotNull('total_price')
            ->where('total_price', '>', 10000)
            ->pluck('total_price')
            ->toArray();
    }
}