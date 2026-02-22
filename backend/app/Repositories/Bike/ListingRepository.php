<?php

declare(strict_types=1);

namespace App\Repositories\Bike;

use App\Models\Listing;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Support\Collection;

/**
 * バイクの出品情報に関する「検索・取得」操作を担当
 * 全文検索エンジン Meilisearch (Laravel Scout) を使用した超高速版
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
     * メイン検索 (Meilisearch 対応版)
     * 30万〜40万件のデータから、ミリ秒単位で検索結果を返します
     */
    public function searchByKeyword(?string $keyword, ?string $prefecture, string $sort, array $filters, int $perPage, array $uiParams = []): Paginator
    {
        // 1. Scout Search を開始 (キーワードが空なら全件対象)
        $search = Listing::search($keyword ?? '');

        // 2. フィルタリング (Meilisearch のフィルタエンジンを使用)
        // ※ Scout 側のフィルタを適用するため、専用の Scope を使わず query 内で指定
        // 注意: Meilisearch 側で filterableAttributes の設定が必要です
        $this->applyMeilisearchFilters($search, $prefecture, $filters);

        // 3. ソート (Meilisearch 側で実行)
        $this->applyMeilisearchSorting($search, $sort);

        // 4. データ取得 & リレーションの結合 (Deferred Join)
        // Meilisearch が特定した 30件 のIDに基づき、MySQL から詳細情報を取得します
        return $search->query(function ($query) {
            $query->select(self::LIST_COLUMNS)
                ->with([
                    'bikeModel:id,manufacturer_id,category_id,name', 
                    'bikeModel.manufacturer:id,name', 
                    'shop:id,name,prefecture', 
                    'site:id,name',
                    'tags:id,name'
                ]);
        })->paginate($perPage); // Meilisearch は爆速なので simplePaginate でなくても高速です
    }

    /**
     * Meilisearch 向けのフィルタ適用
     */
    private function applyMeilisearchFilters($search, ?string $prefecture, array $filters): void
    {
        if ($prefecture) $search->where('prefecture', $prefecture);
        if (!empty($filters['prefecture'])) $search->where('prefecture', $filters['prefecture']);
        if (!empty($filters['manufacturer_id'])) $search->where('manufacturer_id', (int)$filters['manufacturer_id']);
        if (!empty($filters['bike_model_id'])) $search->where('bike_model_id', (int)$filters['bike_model_id']);
        if (!empty($filters['category_id'])) $search->where('category_id', (int)$filters['category_id']);
        if (isset($filters['is_new'])) $search->where('is_new', (int)$filters['is_new']);
        
        // タグ検索（Meilisearch 内に配列で格納されている前提）
        if (!empty($filters['tag'])) $search->where('tag_slugs', $filters['tag']);

        // 範囲指定（Meilisearch 側での対応が必要）
        // Scout の標準的な where は完全一致のみのため、複雑な範囲指定が必要な場合は
        // engine の callback を利用するか、あらかじめ特定の範囲にタグ付けしておきます。
    }

    /**
     * Meilisearch 向けのソート適用
     */
    private function applyMeilisearchSorting($search, string $sort): void
    {
        $direction = match ($sort) {
            'price_asc', 'mileage_asc', 'year_asc' => 'asc',
            default => 'desc',
        };

        $field = match ($sort) {
            'bargain_desc' => 'bargain_score',
            'price_asc', 'price_desc' => 'total_price',
            'mileage_asc', 'mileage_desc' => 'mileage',
            'year_asc', 'year_desc' => 'model_year',
            default => 'created_at',
        };

        $search->orderBy($field, $direction);
    }

    /**
     * 以下、詳細取得系（MySQL直打ちでOK）
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