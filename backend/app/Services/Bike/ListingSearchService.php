<?php

declare(strict_types=1);

namespace App\Services\Bike;

use App\Repositories\Bike\ListingRepository;
use App\Repositories\Bike\ListingStatsRepository;
use App\Repositories\Bike\ManufacturerRepository;
use App\Repositories\Bike\BikeModelRepository;
use App\Repositories\Bike\CategoryRepository;
use App\Http\Resources\Bike\ListingResource;
use App\Services\Bike\Search\KeywordInferrer;
use App\Services\Bike\Search\SearchMetadataGenerator;
use App\Services\Bike\Search\PaginationFormatter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

final class ListingSearchService
{
    public function __construct(
        private readonly ListingRepository $listingRepo,
        private readonly ListingStatsRepository $statsRepo,
        private readonly ManufacturerRepository $manufacturerRepo,
        private readonly BikeModelRepository $modelRepo,
        private readonly CategoryRepository $categoryRepo,
        private readonly KeywordInferrer $inferrer,
        private readonly SearchMetadataGenerator $metaGenerator,
        private readonly PaginationFormatter $paginator
    ) {}

    /**
     * バイク出品情報の検索・絞り込み
     * Meilisearch の超高速検索を活かして、無駄な MySQL の集計をバイパスします
     */
    public function search(?string $keyword, ?string $prefecture = null, string $sort = 'latest', array $filters = [], int $perPage = 30): array
    {
        // 1. 車種・メーカーの自動補完
        if (!empty($filters['bike_model_id']) && empty($filters['manufacturer_id'])) {
            $model = $this->modelRepo->find((int)$filters['bike_model_id']);
            if ($model) $filters['manufacturer_id'] = $model->manufacturer_id;
        }

        // 2. キーワード推論
        if (!empty($keyword) && empty($filters['bike_model_id'])) {
            $inference = $this->inferrer->infer($keyword);
            if (empty($filters['manufacturer_id']) && $inference['manufacturer_id']) {
                $filters['manufacturer_id'] = $inference['manufacturer_id'];
            }
            if ($inference['bike_model_id']) {
                $filters['bike_model_id'] = $inference['bike_model_id'];
            }
        }

        // 検索条件をハッシュ化（キャッシュキーに使用）
        $page = request()->get('page', 1);
        $conditionHash = md5(json_encode([$keyword, $prefecture, $filters, $sort, $perPage]));
        $aggCacheKey = "search_agg_{$conditionHash}";
        $itemsCacheKey = "search_results_p{$page}_{$conditionHash}";

        // --- A. 重い集計処理（統計・UI上限値）のキャッシュ ---
        $aggData = Cache::remember($aggCacheKey, 3600, function () use ($keyword, $prefecture, $filters) {
            $meta = $this->metaGenerator->generate($keyword, $prefecture, $filters);
            $stats = $this->statsRepo->getPriceStats($keyword, $prefecture, $filters);
            
            return [
                'meta' => $meta,
                'statsRaw' => $stats,
            ];
        });

        $searchMeta = $aggData['meta'];
        $statsRaw   = $aggData['statsRaw'];

        // --- B. 検索結果データのキャッシュ & 高速取得 ---
        // 同じ条件のページは 30分間キャッシュ
        $searchResult = Cache::remember($itemsCacheKey, 1800, function () use ($keyword, $prefecture, $sort, $filters, $perPage) {
            // Meilisearch なら paginate() で正確な件数(total)も一瞬で計算してくれます
            $paginated = $this->listingRepo->searchByKeyword($keyword, $prefecture, $sort, $filters, $perPage);
            
            return [
                'items' => ListingResource::collection($paginated->getCollection())->resolve(),
                'pagination' => $this->paginator->format($paginated),
            ];
        });

        // 以前はここで MySQL(statsRaw) の件数で上書きしていましたが、
        // 今は Meilisearch が出した完璧な件数を正として、逆に統計データ側を補正します
        if (isset($searchResult['pagination']['total'])) {
            $statsRaw->count = $searchResult['pagination']['total'];
        }

        // 6. 付加情報の取得（キャッシュ不要な軽い処理）
        $models = collect();
        if (!empty($filters['manufacturer_id'])) {
            $models = $this->modelRepo->getByManufacturerId((int)$filters['manufacturer_id']);
        }

        return [
            'items'         => $searchResult['items'],
            'pagination'    => $searchResult['pagination'],
            'stats'         => $this->metaGenerator->formatStats($statsRaw),
            'meta'          => $searchMeta,
            'manufacturers' => $this->manufacturerRepo->getAllSortedByName(),
            'models'        => $models,
            'regions'       => config('bike.regions', []),
            'prefectures'   => collect(config('bike.regions', []))->flatten()->toArray(),
            'filters'       => $filters,
            'sortOptions'   => $this->getSortOptions(),
        ];
    }

    /**
     * 検索条件に基づいてページタイトルを生成
     */
    public function generatePageTitle(?string $keyword, ?string $prefecture, array $filters): string
    {
        $title = '車両一覧';

        if ($keyword) {
            $title = "「{$keyword}」の検索結果";
        } elseif (!empty($filters['bike_model_id'])) {
            $model = $this->modelRepo->find((int)$filters['bike_model_id']);
            if ($model) {
                $title = "{$model->name} の車両一覧";
            }
        } elseif (!empty($filters['manufacturer_id'])) {
            $makers = $this->manufacturerRepo->getAllSortedByName();
            $maker = $makers->firstWhere('id', $filters['manufacturer_id']);
            if ($maker) {
                $title = "{$maker->name} の車両一覧";
            }
        } elseif (!empty($filters['category_id'])) {
            $category = $this->categoryRepo->find((int)$filters['category_id']);
            if ($category) {
                $title = "{$category->name} の車両一覧";
            }
        } elseif ($prefecture || !empty($filters['prefecture'])) {
            $pref = $prefecture ?: ($filters['prefecture'] ?? '');
            $title = "{$pref} の車両一覧";
        }

        if (!empty($filters['tag'])) {
            if ($title === '車両一覧') {
                return "#{$filters['tag']} の車両一覧";
            }
            return "#{$filters['tag']} " . $title;
        }

        return $title;
    }

    public function getSearchMetadata(?string $keyword = null, ?string $prefecture = null, array $filters = []): array
    {
        return $this->metaGenerator->generate($keyword, $prefecture, $filters);
    }

    public function getSortOptions(): array
    {
        return [
            'bargain_desc' => 'お買い得',
            'latest'       => '新着',
            'price_asc'    => '価格の安い',
            'price_desc'   => '価格の高い',
            'mileage_asc'  => '走行距離が少ない',
            'mileage_desc' => '走行距離が多い',
            'year_desc'    => '年式が新しい',
            'year_asc'     => '年式が古い',
        ];
    }

    public function getActiveCount(): int { return $this->statsRepo->countActiveListings(); }
    public function getModelsByManufacturer(int $mid): Collection { return $this->modelRepo->getByManufacturerId($mid); }
    
    public function getFilteredCount($k, $p, $f): int { 
        $cacheKey = 'search_count_' . md5(json_encode([$k, $p, $f]));
        
        return Cache::remember($cacheKey, 10800, function () use ($k, $p, $f) {
            // Meilisearchなら、1件だけ取得して総件数を引っ張るのが最速
            $paginated = $this->listingRepo->searchByKeyword($k, $p, 'latest', $f, 1);
            
            // Paginatorインターフェースには total() が存在しないため、安全にチェックしてから取得
            return method_exists($paginated, 'total') ? $paginated->total() : count($paginated->items()); 
        });
    }

    public function getPopularTags(): array
    {
        return [
            'ETC', 'ドラレコ', 'ワンオーナー', 'ABS', 
            '低走行', 'グリップヒーター', '社外マフラー', 'USB電源'
        ];
    }
}