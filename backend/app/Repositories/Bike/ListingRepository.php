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
 * 30万〜40万件規模に対応するための「超軽量Deferred Join」実装
 */
final class ListingRepository
{
    /**
     * 一覧表示に必要な基本カラム
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

    public function incrementViewCount(int $id): void
    {
        Listing::where('id', $id)->incrementEach([
            'view_count_total' => 1,
            'view_count_today' => 1,
        ]);
    }

    /**
     * メイン検索（超軽量Deferred Join）
     */
    public function searchByKeyword(?string $keyword, ?string $prefecture, string $sort, array $filters, int $perPage, array $uiParams = []): Paginator
    {
        // 1. 【ID取得クエリ】
        // toBase() を使うことで、Laravelの重いEloquentモデル生成をスキップし、
        // データベースから生の数値だけを高速に取得します。
        $idQuery = Listing::query()->active()->toBase();

        $this->applyFilters($idQuery, $keyword, $prefecture, $filters, $uiParams);
        $this->applySorting($idQuery, $sort);

        // 30万件あっても ID だけならメモリ消費は極小
        $paginatedIds = $idQuery->select('listings.id')->simplePaginate($perPage)->withQueryString();
        
        $ids = collect($paginatedIds->items())->pluck('id')->toArray();

        if (empty($ids)) {
            return $paginatedIds;
        }

        // 2. 【詳細データ取得】
        // 確定した30件に対して、必要なデータだけをガチャンと結合。
        $items = Listing::query()
            ->select(self::LIST_COLUMNS)
            ->with([
                'bikeModel:id,manufacturer_id,category_id,name', 
                'bikeModel.manufacturer:id,name', 
                'shop:id,name,prefecture', 
                'site:id,name',
                'tags:id,name'
            ])
            ->whereIn('listings.id', $ids)
            ->orderByRaw('FIELD(listings.id, ' . implode(',', $ids) . ')')
            ->get();

        return $paginatedIds->setCollection($items);
    }

    /**
     * ショップ別在庫取得（ここも高速化）
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

    private function applySorting($query, string $sort): void
    {
        match ($sort) {
            'bargain_desc' => $query->orderBy('listings.bargain_score', 'desc'),
            'price_asc'    => $query->whereNotNull('listings.total_price')->orderBy('listings.total_price', 'asc'),
            'price_desc'   => $query->whereNotNull('listings.total_price')->orderBy('listings.total_price', 'desc'),
            'mileage_asc'  => $query->whereNotNull('listings.mileage')->orderBy('listings.mileage', 'asc'),
            'mileage_desc' => $query->whereNotNull('listings.mileage')->orderBy('listings.mileage', 'desc'),
            'year_desc'    => $query->orderBy('listings.model_year', 'desc'),
            'year_asc'     => $query->orderBy('listings.model_year', 'asc'),
            default        => $query->orderBy('listings.created_at', 'desc'),
        };
    }

    private function applyFilters($query, ?string $keyword, ?string $prefecture, array $filters, array $uiParams): void
    {
        // タグ検索（JOINサブクエリ）
        if (!empty($filters['tag'])) {
            $query->whereIn('listings.id', function($q) use ($filters) {
                $q->select('listing_tag.listing_id')
                  ->from('listing_tag')
                  ->join('tags', 'tags.id', '=', 'listing_tag.tag_id')
                  ->where('tags.slug', $filters['tag']);
            });
        }

        // 基本スコープの適用（toBase()時はListingインスタンスではないためScopeが使えません）
        // したがって、Listingモデル側にあるスコープと同じ条件を手動で構築します
        if ($keyword) {
            // ここが MySQL で最も重い部分です。
            $query->where('listings.title', 'LIKE', "%{$keyword}%");
        }
        
        if ($prefecture || !empty($filters['prefecture'])) {
            $query->where('listings.prefecture', $prefecture ?: $filters['prefecture']);
        }

        if (!empty($filters['manufacturer_id'])) {
            $query->where('listings.manufacturer_id', $filters['manufacturer_id']);
        }

        if (!empty($filters['bike_model_id'])) {
            $query->where('listings.bike_model_id', $filters['bike_model_id']);
        }

        // 価格・走行距離・年式
        if (!empty($filters['min_price'])) $query->where('listings.total_price', '>=', $filters['min_price']);
        if (!empty($filters['max_price'])) $query->where('listings.total_price', '<=', $filters['max_price']);
        if (!empty($filters['min_mileage'])) $query->where('listings.mileage', '>=', $filters['min_mileage']);
        if (!empty($filters['max_mileage'])) $query->where('listings.mileage', '<=', $filters['max_mileage']);
        if (!empty($filters['min_year'])) $query->where('listings.model_year', '>=', $filters['min_year']);
        if (!empty($filters['max_year'])) $query->where('listings.model_year', '<=', $filters['max_year']);
    }

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