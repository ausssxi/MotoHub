<?php

declare(strict_types=1);

namespace App\Repositories\Bike;

use App\Models\Listing;
use Illuminate\Contracts\Pagination\Paginator; // ★ 戻り値の型を抽象化するために追加
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
     */
    private const LIST_COLUMNS = [
        'listings.id', 
        'listings.bike_model_id', 
        'listings.shop_id', 
        'listings.manufacturer_id', 
        'listings.category_id',
        'listings.site_id',
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
        'listings.created_at',
        'listings.bargain_score', // お買い得ソートやバッジ表示で必要
        'listings.view_count_today', // 人気バッジ用
        'listings.favorite_count'    // 人気バッジ用
    ];

    /**
     * 閲覧数をインクリメント（累計と当日分を同時に +1）
     */
    public function incrementViewCount(int $id): void
    {
        // 高速化のためクエリビルダで直接更新
        Listing::where('id', $id)->incrementEach([
            'view_count_total' => 1,
            'view_count_today' => 1,
        ]);
    }

    /**
     * メイン検索
     * 戻り値の型を Paginator に変更し、simplePaginate（COUNTクエリなし）に対応させます
     */
    public function searchByKeyword(?string $keyword, ?string $prefecture, string $sort, array $filters, int $perPage, array $uiParams = []): Paginator
    {
        $query = $this->buildFilteredQuery($keyword, $prefecture, $filters, $uiParams);

        // ★軽量化: 必要なカラムだけに絞る
        $query->select(self::LIST_COLUMNS);

        // ソート適用（インデックスを活用）
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

        // ★高速化: simplePaginate を使用して COUNT(*) クエリを回避
        return $query->simplePaginate($perPage)->withQueryString();
    }

    /**
     * 店舗ごとの在庫一覧を取得
     */
    public function getByShopId(int $shopId, int $perPage = 30): Paginator
    {
        return Listing::query()
            ->select(self::LIST_COLUMNS) // 軽量化
            ->with(['bikeModel:id,manufacturer_id,name', 'shop:id,name,prefecture', 'site:id,name'])
            ->active()
            ->where('shop_id', $shopId)
            ->orderBy('created_at', 'desc')
            ->simplePaginate($perPage); // 高速化
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
        return Listing::query()
            // 最小限のカラムに絞る
            ->select([
                'id', 'bike_model_id', 'shop_id', 'title', 'total_price', 
                'model_year', 'mileage', 'image_urls', 'local_image_paths', 'bargain_score'
            ])
            ->with(['shop:id,prefecture']) // ショップも都道府県のみ取得
            ->where('bike_model_id', $bikeModelId)
            ->where('id', '!=', $excludeId)
            ->where('is_sold_out', false)
            ->whereNotNull('total_price')
            ->orderBy('total_price', 'asc')
            ->limit($limit) // ★修正: .limit から ->limit に変更
            ->get();
    }

    
    /**
     * 車両詳細情報をリレーション込みで取得する
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
     * 類似車両（同じメーカーの別車種など）を取得する
     */
    public function getSimilarListings(?int $manufacturerId, ?int $excludeModelId, int $limit = 8): Collection
    {
        if (!$manufacturerId) {
            return collect();
        }

        return Listing::with(['shop:id,name,prefecture', 'bikeModel:id,manufacturer_id,name'])
            ->where('manufacturer_id', $manufacturerId)
            ->where('bike_model_id', '!=', $excludeModelId)
            ->where('is_sold_out', false)
            ->inRandomOrder()
            ->take($limit)
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
                'bikeModel:id,manufacturer_id,category_id,name', 
                'bikeModel.manufacturer:id,name', 
                'bikeModel.categoryData:id,name', 
                'shop:id,name,prefecture', 
                'site:id,name',
                // カードで表示するためタグを復活（ただしIDと名前だけに絞って超軽量化）
                'tags:id,name' 
            ])
            ->active()
            ->withKeyword($keyword)
            ->byPrefecture($prefecture ?: ($filters['prefecture'] ?? null))
            ->byModel($filters['manufacturer_id'] ?? null, $filters['bike_model_id'] ?? null)
            ->byCategory($filters['category_id'] ?? null)
            ->byCondition($filters['is_new'] ?? null)
            ->byRepairHistory($filters['has_repair_history'] ?? null)
            ->priceBetween($filters['min_price'] ?? null, $filters['max_price'] ?? null, $maxPrice)
            ->mileageBetween($filters['min_mileage'] ?? null, $filters['max_mileage'] ?? null, $maxMileage)
            ->yearBetween($filters['min_year'] ?? null, $filters['max_year'] ?? null)
            ->displacementBetween($filters['min_displacement'] ?? null, $filters['max_displacement'] ?? null)
            ->withTag($filters['tag'] ?? null);
    }
    
    /**
     * 特定のモデルIDに紐づく有効な価格リストを取得する
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