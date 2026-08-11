<?php

declare(strict_types=1);

namespace App\Repositories\Bike;

use App\Models\Listing;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * バイクの出品情報に関する「検索・取得」操作を担当
 * 全文検索エンジン Meilisearch (Laravel Scout) を使用した超高速版
 */
final class ListingRepository
{
    private const LIST_COLUMNS = [
        'listings.id', 'listings.bike_model_id', 'listings.shop_id',
        'listings.manufacturer_id', 'listings.category_id', 'listings.site_id',
        'listings.title', 'listings.model_year', 'listings.mileage',
        'listings.displacement', 'listings.total_price', 'listings.price',
        'listings.condition', 'listings.has_repair_history', 'listings.is_sold_out', 'listings.image_urls',
        'listings.local_image_paths',
        'listings.created_at', 'listings.bargain_score',
        'listings.view_count_today', 'listings.favorite_count',
    ];

    /**
     * 閲覧数を+1する。
     *
     * ロック待ちを1秒で打ち切る。深夜バッチ（listings:compute-capped-sold）が listings を
     * 広範囲にロックしている間にここへ来ると、既定の innodb_lock_wait_timeout=50秒ぶん
     * ユーザーを待たせてしまうため（2026-08-04〜08-11 に11件発生）。
     * カウントは落ちてもよいが、ページ表示を50秒待たせるのは割に合わない。
     * 1秒で諦めた場合は 1205 が投げられ、呼び出し元（BikeController::show）が握って表示を続ける。
     *
     * ※ この設定は「このインクリメントのためだけ」のもの。在庫詳細ページのリクエストでは
     *   他に書き込みが無いことを確認済み（show() の try 内に increment 以外の書き込みは無し）。
     * ※ 影響範囲: MySQL接続は永続接続ではない（config/database.php の mysql options に
     *   PDO::ATTR_PERSISTENT が無く、php-fpm もリクエストごとに接続を張り直す）。よって
     *   他リクエストへ漏れることはないが、同一リクエスト内の後続クエリへ残らないよう
     *   finally で DEFAULT（グローバル値）へ戻す。
     */
    public function incrementViewCount(int $id): void
    {
        $isMysql = DB::connection()->getDriverName() === 'mysql';

        if ($isMysql) {
            DB::statement('SET SESSION innodb_lock_wait_timeout = 1');
        }

        try {
            Listing::where('id', $id)->incrementEach([
                'view_count_total' => 1,
                'view_count_today' => 1,
            ]);
        } finally {
            if ($isMysql) {
                DB::statement('SET SESSION innodb_lock_wait_timeout = DEFAULT');
            }
        }
    }

    public function searchByKeyword(?string $keyword, ?string $prefecture, string $sort, array $filters, int $perPage): Paginator
    {
        $search = Listing::search($keyword ?? '', function ($meiliSearch, string $query, array $options) use ($prefecture, $filters) {

            $filterString = $this->buildMeilisearchFilters($prefecture, $filters);
            if ($filterString !== '') {
                $options['filter'] = $filterString;
            }

            return $meiliSearch->search($query, $options);
        });

        $this->applyMeilisearchSorting($search, $sort);

        return $search->query(function ($query) {
            $query->select(self::LIST_COLUMNS)
                ->with([
                    'bikeModel:id,manufacturer_id,category_id,name',
                    'bikeModel.manufacturer:id,name',
                    'bikeModel.nationalPriceStat', // お得バッジの全国中央値（一括取得＝N+1なし）
                    'shop:id,name,prefecture',
                    'site:id,name',
                    'tags:id,name',
                ]);
        })->paginate($perPage);
    }

    public function getFacets(?string $keyword, ?string $prefecture, array $filters, array $facetAttributes = ['prefecture']): array
    {
        $raw = Listing::search($keyword ?? '', function ($meiliSearch, string $query, array $options) use ($prefecture, $filters, $facetAttributes) {

            $filterString = $this->buildMeilisearchFilters($prefecture, $filters);
            if ($filterString !== '') {
                $options['filter'] = $filterString;
            }

            $options['facets'] = $facetAttributes;
            $options['limit'] = 0;

            return $meiliSearch->search($query, $options);
        })->raw();

        return $raw['facetDistribution'] ?? [];
    }

    /**
     * Meilisearch 用のフィルター文字列を構築する
     */
    private function buildMeilisearchFilters(?string $prefecture, array $filters): string
    {
        $filterStrings = [];

        // 常に有効な車両のみ（売約済みを除外）を検索対象とする
        $filterStrings[] = 'is_sold_out = 0';

        // ▼▼▼ 修正の核心部分（表記揺れ吸収フィルター） ▼▼▼
        $p = $prefecture ?: ($filters['prefecture'] ?? null);
        if ($p) {
            // "都・道・府・県" を取り除いた純粋な地名を取得（例：東京都 -> 東京）
            $short = str_replace(['都', '道', '府', '県'], '', $p);

            // Meilisearchに「東京でも東京都でもどっちでもOK！」と指示を出します
            $filterStrings[] = "(prefecture = '{$short}' OR prefecture = '{$short}都' OR prefecture = '{$short}道' OR prefecture = '{$short}府' OR prefecture = '{$short}県')";
        }
        // ▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲
        if (! empty($filters['bike_model_ids'])) {
            $ids = array_map('intval', $filters['bike_model_ids']);
            $filterStrings[] = 'bike_model_id IN ['.implode(', ', $ids).']';
        } elseif (! empty($filters['bike_model_id'])) {
            $filterStrings[] = 'bike_model_id = '.(int) $filters['bike_model_id'];
        } elseif (! empty($filters['manufacturer_id'])) {
            $filterStrings[] = 'manufacturer_id = '.(int) $filters['manufacturer_id'];
        }

        if (! empty($filters['bike_model_id'])) {
            $filterStrings[] = 'bike_model_id = '.(int) $filters['bike_model_id'];
        } elseif (! empty($filters['manufacturer_id'])) {
            $filterStrings[] = 'manufacturer_id = '.(int) $filters['manufacturer_id'];
        }

        if (! empty($filters['category_id'])) {
            $filterStrings[] = 'category_id = '.(int) $filters['category_id'];
        }

        if (isset($filters['is_new']) && $filters['is_new'] !== '') {
            $filterStrings[] = 'is_new = '.(int) $filters['is_new'];
        }
        if (isset($filters['has_repair_history']) && $filters['has_repair_history'] !== '') {
            $filterStrings[] = 'has_repair_history = '.(int) $filters['has_repair_history'];
        }

        if (! empty($filters['tags']) && is_array($filters['tags'])) {
            $tagFilters = [];
            foreach ($filters['tags'] as $tag) {
                $safeTag = str_replace("'", "\\'", $tag);
                $tagFilters[] = "tag_slugs = '{$safeTag}'";
            }
            if (count($tagFilters) === 1) {
                $filterStrings[] = $tagFilters[0];
            } elseif (count($tagFilters) > 1) {
                $filterStrings[] = '('.implode(' AND ', $tagFilters).')';
            }
        } elseif (! empty($filters['tag'])) {
            $safeTag = str_replace("'", "\\'", $filters['tag']);
            $filterStrings[] = "tag_slugs = '{$safeTag}'";
        }

        // --- 範囲指定フィルター ---
        $minPrice = $filters['price_min'] ?? $filters['min_price'] ?? null;
        if ($minPrice !== null && $minPrice > 0) {
            $filterStrings[] = 'total_price >= '.((int) $minPrice * 10000);
        }
        $maxPrice = $filters['price_max'] ?? $filters['max_price'] ?? null;
        if ($maxPrice !== null) {
            $filterStrings[] = 'total_price > 0';
            $filterStrings[] = 'total_price <= '.((int) $maxPrice * 10000);
        }

        $minMileage = $filters['mileage_min'] ?? $filters['min_mileage'] ?? null;
        if ($minMileage !== null && $minMileage > 0) {
            $filterStrings[] = 'mileage >= '.(int) $minMileage;
        }
        $maxMileage = $filters['mileage_max'] ?? $filters['max_mileage'] ?? null;
        if ($maxMileage !== null) {
            $filterStrings[] = 'mileage <= '.(int) $maxMileage;
        }

        $minYear = $filters['year_min'] ?? $filters['min_year'] ?? null;
        if ($minYear !== null && $minYear > 0) {
            $filterStrings[] = 'model_year >= '.(int) $minYear;
        }
        $maxYear = $filters['year_max'] ?? $filters['max_year'] ?? null;
        if ($maxYear !== null) {
            $filterStrings[] = 'model_year <= '.(int) $maxYear;
        }

        $minDisp = $filters['displacement_min'] ?? $filters['min_displacement'] ?? null;
        if ($minDisp !== null && $minDisp > 0) {
            $filterStrings[] = 'displacement >= '.(int) $minDisp;
        }
        $maxDisp = $filters['displacement_max'] ?? $filters['max_displacement'] ?? null;
        if ($maxDisp !== null) {
            $filterStrings[] = 'displacement <= '.(int) $maxDisp;
        }

        return implode(' AND ', $filterStrings);
    }

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
                // site_id は掲載停止サイト（Webike）の画像非表示判定に必須（Listing::imagesAreSuppressed）。
                'id', 'bike_model_id', 'shop_id', 'site_id', 'title', 'total_price',
                'model_year', 'mileage', 'image_urls', 'created_at', 'bargain_score',
            ])
            ->with(['shop:id,prefecture', 'bikeModel:id,name', 'bikeModel.nationalPriceStat'])
            ->where('bike_model_id', $bikeModelId)
            ->where('id', '!=', $excludeId)
            ->where('is_sold_out', false)
            ->whereNotNull('total_price')
            ->orderBy('total_price', 'asc')
            ->limit($limit)
            ->get();
    }

    public function getListingDetail(int $id): ?Listing
    {
        return Listing::with([
            'shop',
            'bikeModel.manufacturer',
            'bikeModel.categoryData',
            'bikeModel.marketStats',
            'bikeModel.nationalPriceStat',
            'tags:id,name',
        ])->find($id);
    }

    public function getSimilarListings(?int $manufacturerId, ?int $excludeModelId, int $limit = 8): Collection
    {
        if (! $manufacturerId) {
            return collect();
        }

        return Listing::with(['shop:id,name,prefecture', 'bikeModel:id,manufacturer_id,name', 'bikeModel.nationalPriceStat'])
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
