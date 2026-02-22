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
     * メイン検索 (Meilisearch ネイティブフィルタ対応版)
     * 範囲指定などもすべてMeilisearch側で処理させ、MySQLへの負担をゼロにします。
     */
    public function searchByKeyword(?string $keyword, ?string $prefecture, string $sort, array $filters, int $perPage, array $uiParams = []): Paginator
    {
        // 1. Scout Search を開始 + Meilisearchエンジンのコールバックを利用
        $search = Listing::search($keyword ?? '', function ($meiliSearch, string $query, array $options) use ($prefecture, $filters) {
            
            $filterStrings = [];

            // --- 完全一致フィルター ---
            if ($prefecture) $filterStrings[] = "prefecture = '{$prefecture}'";
            if (!empty($filters['prefecture'])) $filterStrings[] = "prefecture = '{$filters['prefecture']}'";
            if (!empty($filters['manufacturer_id'])) $filterStrings[] = "manufacturer_id = " . (int)$filters['manufacturer_id'];
            if (!empty($filters['bike_model_id'])) $filterStrings[] = "bike_model_id = " . (int)$filters['bike_model_id'];
            if (!empty($filters['category_id'])) $filterStrings[] = "category_id = " . (int)$filters['category_id'];
            if (isset($filters['is_new']) && $filters['is_new'] !== '') $filterStrings[] = "is_new = " . (int)$filters['is_new'];
            if (!empty($filters['tag'])) $filterStrings[] = "tag_slugs = '{$filters['tag']}'";

            // --- 範囲指定フィルター (ここが爆速化の鍵！) ---
            // ※フロントから price_min などのキーで送られてくると想定しています
            if (!empty($filters['price_min'])) $filterStrings[] = "total_price >= " . ((int)$filters['price_min'] * 10000);
            if (!empty($filters['price_max'])) $filterStrings[] = "total_price <= " . ((int)$filters['price_max'] * 10000);
            
            if (!empty($filters['mileage_min'])) $filterStrings[] = "mileage >= " . (int)$filters['mileage_min'];
            if (!empty($filters['mileage_max'])) $filterStrings[] = "mileage <= " . (int)$filters['mileage_max'];
            
            if (!empty($filters['year_min'])) $filterStrings[] = "model_year >= " . (int)$filters['year_min'];
            if (!empty($filters['year_max'])) $filterStrings[] = "model_year <= " . (int)$filters['year_max'];
            
            if (!empty($filters['displacement_min'])) $filterStrings[] = "displacement >= " . (int)$filters['displacement_min'];
            if (!empty($filters['displacement_max'])) $filterStrings[] = "displacement <= " . (int)$filters['displacement_max'];

            // 構築したフィルター文字列をMeilisearchのオプションにセット (例: "manufacturer_id = 1 AND total_price >= 100000")
            if (!empty($filterStrings)) {
                $options['filter'] = implode(' AND ', $filterStrings);
            }

            // Meilisearchに最適化されたクエリを発行！
            return $meiliSearch->search($query, $options);
        });

        // 2. ソート (Meilisearch 側で実行)
        $this->applyMeilisearchSorting($search, $sort);

        // 3. データ取得 & リレーションの結合 (N+1対策は完璧です)
        return $search->query(function ($query) {
            $query->select(self::LIST_COLUMNS)
                ->with([
                    'bikeModel:id,manufacturer_id,category_id,name', 
                    'bikeModel.manufacturer:id,name', 
                    'shop:id,name,prefecture', 
                    'site:id,name',
                    'tags:id,name'
                ]);
        })->paginate($perPage); 
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