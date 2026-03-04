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
     */
    public function search(?string $keyword, ?string $prefecture = null, string $sort = 'latest', array $filters = [], int $perPage = 30): array
    {
        // 1. 車種・メーカーの自動補完
        if (!empty($filters['bike_model_id']) && empty($filters['manufacturer_id'])) {
            $model = $this->modelRepo->find((int)$filters['bike_model_id']);
            if ($model) $filters['manufacturer_id'] = $model->manufacturer_id;
        }

        // ▼▼▼ 修正: キーワード推論（高度なセマンティック解析） ▼▼▼
        if (!empty($keyword)) {
            // "TW225e 東京 ETC" などの複合キーワードを解析
            $inference = $this->inferrer->infer($keyword);
            
            // メーカーを抽出
            if (empty($filters['manufacturer_id']) && $inference['manufacturer_id']) {
                $filters['manufacturer_id'] = $inference['manufacturer_id'];
            }
            // 車種を抽出
            if (empty($filters['bike_model_id']) && $inference['bike_model_id']) {
                $filters['bike_model_id'] = $inference['bike_model_id'];
            }
            // 地域を抽出（最優先で適用）
            if (empty($prefecture) && empty($filters['prefecture']) && $inference['prefecture']) {
                $prefecture = $inference['prefecture'];
                $filters['prefecture'] = $inference['prefecture']; // サイドバーUI用にもセット
            }
            // タグを抽出
            if (empty($filters['tag']) && $inference['tag']) {
                $filters['tag'] = $inference['tag'];
            }

            // フィルターに変換できた単語を取り除き、残った言葉だけを純粋なキーワード検索に回す
            $keyword = $inference['remaining_keyword'];
        }
        // ▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲▲

        // 検索条件をハッシュ化（キャッシュキーに使用）
        // ★修正: json_encodeが失敗してfalseを返すのを防ぐため、堅牢なserializeに変更
        $page = request()->get('page', 1);
        $conditionHash = md5(serialize([$keyword, $prefecture, $filters, $sort, $perPage]));
        $aggCacheKey = "search_agg_{$conditionHash}";
        $itemsCacheKey = "search_results_p{$page}_{$conditionHash}";

        // --- A. 重い集計処理（統計・ファセット）のキャッシュ ---
        $aggData = Cache::remember($aggCacheKey, 3600, function () use ($keyword, $prefecture, $filters) {
            $meta = $this->metaGenerator->generate($keyword, $prefecture, $filters);
            $stats = $this->statsRepo->getPriceStats($keyword, $prefecture, $filters);
            
            $facets = $this->listingRepo->getFacets($keyword, $prefecture, $filters, [
                'prefecture', 
                'is_new', 
                'has_repair_history'
            ]);

            return [
                'meta' => $meta,
                'statsRaw' => $stats,
                'facets' => $facets,
            ];
        });

        $searchMeta = $aggData['meta'];
        $statsRaw   = $aggData['statsRaw'];
        $facets     = $aggData['facets'];

        // --- B. 検索結果データのキャッシュ & 高速取得 ---
        $searchResult = Cache::remember($itemsCacheKey, 1800, function () use ($keyword, $prefecture, $sort, $filters, $perPage) {
            $paginated = $this->listingRepo->searchByKeyword($keyword, $prefecture, $sort, $filters, $perPage);
            
            return [
                'items' => ListingResource::collection($paginated->getCollection())->resolve(),
                'pagination' => $this->paginator->format($paginated),
            ];
        });

        if (isset($searchResult['pagination']['total'])) {
            $statsRaw->count = $searchResult['pagination']['total'];
        }

        // 0件ヒット時のケア（離脱防止サジェスト）
        $relaxSuggestions = [];
        if ($searchResult['pagination']['total'] === 0) {
            $relaxSuggestions = $this->generateRelaxedSuggestions($keyword, $prefecture, $filters);
        }

        $models = collect();
        if (!empty($filters['manufacturer_id'])) {
            $models = $this->modelRepo->getByManufacturerId((int)$filters['manufacturer_id']);
        }

        return [
            'items'            => $searchResult['items'],
            'pagination'       => $searchResult['pagination'],
            'stats'            => $this->metaGenerator->formatStats($statsRaw),
            'meta'             => $searchMeta,
            'facets'           => $facets,
            'relaxSuggestions' => $relaxSuggestions,
            'manufacturers'    => $this->manufacturerRepo->getAllSortedByName(),
            'models'           => $models,
            'regions'          => config('bike.regions', []),
            'prefectures'      => collect(config('bike.regions', []))->flatten()->toArray(),
            'filters'          => $filters,
            'sortOptions'      => $this->getSortOptions(),
        ];
    }

    /**
     * 0件ヒット時に、条件を緩和した提案を生成する
     */
    private function generateRelaxedSuggestions(?string $keyword, ?string $prefecture, array $filters): array
    {
        $suggestions = [];
        
        $buildUrl = function ($keysToRemove) {
            $params = request()->except(array_merge((array)$keysToRemove, ['page']));
            return route('bikes.search', $params);
        };

        if (!empty($prefecture) || !empty($filters['prefecture'])) {
            $relaxed = $filters;
            unset($relaxed['prefecture']);
            $count = $this->getFilteredCount($keyword, null, $relaxed);
            if ($count > 0) {
                $suggestions[] = [
                    'icon' => 'map',
                    'label' => '地域を全国に広げる',
                    'count' => $count,
                    'url' => $buildUrl(['prefecture'])
                ];
            }
        }

        if (!empty($filters['max_price']) || !empty($filters['min_price'])) {
            $relaxed = $filters;
            unset($relaxed['max_price'], $relaxed['min_price'], $relaxed['price_max'], $relaxed['price_min']);
            $count = $this->getFilteredCount($keyword, $prefecture, $relaxed);
            if ($count > 0) {
                $suggestions[] = [
                    'icon' => 'coins',
                    'label' => '価格の条件を外す',
                    'count' => $count,
                    'url' => $buildUrl(['max_price', 'min_price', 'price_max', 'price_min'])
                ];
            }
        }

        if (!empty($filters['max_mileage'])) {
            $relaxed = $filters;
            unset($relaxed['max_mileage'], $relaxed['min_mileage']);
            $count = $this->getFilteredCount($keyword, $prefecture, $relaxed);
            if ($count > 0) {
                $suggestions[] = [
                    'icon' => 'gauge',
                    'label' => '走行距離の条件を外す',
                    'count' => $count,
                    'url' => $buildUrl(['max_mileage', 'min_mileage'])
                ];
            }
        }

        if (isset($filters['has_repair_history']) || isset($filters['is_new'])) {
            $relaxed = $filters;
            unset($relaxed['has_repair_history'], $relaxed['is_new']);
            $count = $this->getFilteredCount($keyword, $prefecture, $relaxed);
            if ($count > 0) {
                $suggestions[] = [
                    'icon' => 'wrench',
                    'label' => '車両状態（修復歴など）の条件を外す',
                    'count' => $count,
                    'url' => $buildUrl(['has_repair_history', 'is_new'])
                ];
            }
        }

        if ($keyword && (!empty($prefecture) || !empty($filters))) {
            $count = $this->getFilteredCount($keyword, null, []);
            if ($count > 0) {
                $suggestions[] = [
                    'icon' => 'search',
                    'label' => "「{$keyword}」の全国の車両を見る",
                    'count' => $count,
                    'url' => route('bikes.search', ['keyword' => $keyword])
                ];
            }
        }

        if (empty($suggestions)) {
            $suggestions[] = [
                'icon' => 'bike',
                'label' => '条件をすべてリセットして探す',
                'count' => $this->getActiveCount(),
                'url' => route('bikes.search')
            ];
        }

        return $suggestions;
    }

    public function generatePageTitle(?string $keyword, ?string $prefecture, array $filters): string
    {
        $title = '車両一覧';
        if ($keyword) $title = "「{$keyword}」の検索結果";
        elseif (!empty($filters['bike_model_id'])) {
            $model = $this->modelRepo->find((int)$filters['bike_model_id']);
            if ($model) $title = "{$model->name} の車両一覧";
        } elseif (!empty($filters['manufacturer_id'])) {
            $makers = $this->manufacturerRepo->getAllSortedByName();
            $maker = $makers->firstWhere('id', $filters['manufacturer_id']);
            if ($maker) $title = "{$maker->name} の車両一覧";
        } elseif (!empty($filters['category_id'])) {
            $category = $this->categoryRepo->find((int)$filters['category_id']);
            if ($category) $title = "{$category->name} の車両一覧";
        } elseif ($prefecture || !empty($filters['prefecture'])) {
            $pref = $prefecture ?: ($filters['prefecture'] ?? '');
            $title = "{$pref} の車両一覧";
        }
        if (!empty($filters['tag'])) {
            if ($title === '車両一覧') return "#{$filters['tag']} の車両一覧";
            return "#{$filters['tag']} " . $title;
        }
        return $title;
    }

    public function getSearchMetadata(?string $keyword = null, ?string $prefecture = null, array $filters = []): array { return $this->metaGenerator->generate($keyword, $prefecture, $filters); }
    public function getSortOptions(): array { return ['bargain_desc' => 'お買い得', 'latest' => '新着', 'price_asc' => '価格の安い', 'price_desc' => '価格の高い', 'mileage_asc' => '走行距離が少ない', 'mileage_desc' => '走行距離が多い', 'year_desc' => '年式が新しい', 'year_asc' => '年式が古い']; }
    public function getActiveCount(): int { return $this->statsRepo->countActiveListings(); }
    public function getModelsByManufacturer(int $mid): Collection { return $this->modelRepo->getByManufacturerId($mid); }
    
    public function getFilteredCount($k, $p, $f): int { 
        $cacheKey = 'search_count_' . md5(json_encode([$k, $p, $f]));
        return Cache::remember($cacheKey, 10800, function () use ($k, $p, $f) {
            $paginated = $this->listingRepo->searchByKeyword($k, $p, 'latest', $f, 1);
            return method_exists($paginated, 'total') ? $paginated->total() : count($paginated->items()); 
        });
    }

    public function getPopularTags(): array { return ['ETC', 'ドラレコ', 'ワンオーナー', 'ABS', '低走行', 'グリップヒーター', '社外マフラー', 'USB電源']; }

    /**
     * 検索結果ページ向けのおすすめ車種を取得
     */
    public function getRecommendedModels(array $filters, array $items): Collection
    {
        if (empty($items)) {
            return collect();
        }

        $cacheKey = 'search_recommend_' . md5(serialize($filters));

        return Cache::remember($cacheKey, 1800, function () use ($filters, $items) {
            $excludeModelId = !empty($filters['bike_model_id']) ? (int) $filters['bike_model_id'] : null;
            $manufacturerId = !empty($filters['manufacturer_id']) ? (int) $filters['manufacturer_id'] : null;

            // 優先1: メーカー指定あり → 同メーカーの他車種
            if ($manufacturerId) {
                $models = \App\Models\BikeModel::with('manufacturer')
                    ->where('manufacturer_id', $manufacturerId)
                    ->when($excludeModelId, fn($q, $id) => $q->where('id', '!=', $id))
                    ->whereNotNull('slug')
                    ->withCount(['listings' => fn($q) => $q->active()])
                    ->having('listings_count', '>', 0)
                    ->orderByDesc('listings_count')
                    ->limit(6)
                    ->get();

                if ($models->isNotEmpty()) {
                    return $models;
                }
            }

            // 優先2: 検索結果から最頻出カテゴリを推論 → 同カテゴリの人気車種
            $dominantCategoryId = $this->inferDominantCategory($items);
            if ($dominantCategoryId) {
                $models = \App\Models\BikeModel::with('manufacturer')
                    ->where('category_id', $dominantCategoryId)
                    ->when($excludeModelId, fn($q, $id) => $q->where('id', '!=', $id))
                    ->whereNotNull('slug')
                    ->withCount(['listings' => fn($q) => $q->active()])
                    ->having('listings_count', '>', 0)
                    ->orderByDesc('listings_count')
                    ->limit(6)
                    ->get();

                if ($models->isNotEmpty()) {
                    return $models;
                }
            }

            return collect();
        });
    }

    /**
     * 検索結果から最も出現頻度の高いカテゴリIDを推論
     */
    private function inferDominantCategory(array $items): ?int
    {
        $modelIds = collect($items)->pluck('bike_model_id')->filter()->unique()->values();

        if ($modelIds->isEmpty()) {
            return null;
        }

        $topCategoryId = \App\Models\BikeModel::whereIn('id', $modelIds)
            ->whereNotNull('category_id')
            ->selectRaw('category_id, COUNT(*) as cnt')
            ->groupBy('category_id')
            ->orderByDesc('cnt')
            ->value('category_id');

        return $topCategoryId ? (int) $topCategoryId : null;
    }
}