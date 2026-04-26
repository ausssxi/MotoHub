<?php

declare(strict_types=1);

namespace App\Http\Controllers\Feature;

use App\Http\Controllers\Controller;
use App\Models\BikeModel;
use App\Models\Listing;
use App\Models\SeoFeature;
use App\Services\Bike\ListingSearchService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

final class FeatureController extends Controller
{
    public function __construct(
        private readonly ListingSearchService $listingSearchService
    ) {}

    /**
     * 特集ページ一覧
     */
    public function index(): View
    {
        $features = SeoFeature::active()->ordered()->get();

        return view('features.index', compact('features'));
    }

    /**
     * 個別の特集ページ表示
     */
    public function show(Request $request, string $slug): View
    {
        $feature = SeoFeature::where('slug', $slug)
            ->active()
            ->firstOrFail();

        $sort = $request->query('sort', $feature->sort);

        $result = $this->listingSearchService->search(
            $feature->keyword,
            $feature->prefecture,
            $sort,
            $feature->search_conditions ?? [],
        );

        // DBベースのKPI集計（全件対象、キャッシュ付き）
        $featureKpi = $this->computeFeatureKpi($feature);

        $relatedFeatures = SeoFeature::active()
            ->where('id', '!=', $feature->id)
            ->ordered()
            ->limit(6)
            ->get();

        return view('features.show', array_merge($result, [
            'feature' => $feature,
            'sort' => $sort,
            'featureKpi' => $featureKpi,
            'relatedFeatures' => $relatedFeatures,
        ]));
    }

    /**
     * 特集の検索条件に基づいてDBから直接KPIを集計
     */
    private function computeFeatureKpi(SeoFeature $feature): array
    {
        return Cache::remember("feature_kpi_v1_{$feature->slug}", 3600, function () use ($feature) {
            $query = Listing::where('is_sold_out', false)
                ->where('total_price', '>', 0)
                ->whereNotNull('bike_model_id');

            $conditions = $feature->search_conditions ?? [];

            // 排気量フィルタ（bike_modelsテーブル経由）
            $minDisp = $conditions['min_displacement'] ?? $conditions['displacement_min'] ?? null;
            $maxDisp = $conditions['max_displacement'] ?? $conditions['displacement_max'] ?? null;
            if ($minDisp || $maxDisp) {
                $query->whereHas('bikeModel', function ($q) use ($minDisp, $maxDisp) {
                    if ($minDisp) {
                        $q->where('displacement', '>=', (int) $minDisp);
                    }
                    if ($maxDisp) {
                        $q->where('displacement', '<=', (int) $maxDisp);
                    }
                });
            }

            // 価格フィルタ（万円 → 円に変換）
            $minPrice = $conditions['min_price'] ?? $conditions['price_min'] ?? null;
            $maxPrice = $conditions['max_price'] ?? $conditions['price_max'] ?? null;
            if ($minPrice) {
                $query->where('total_price', '>=', (int) $minPrice * 10000);
            }
            if ($maxPrice) {
                $query->where('total_price', '<=', (int) $maxPrice * 10000);
            }

            // カテゴリフィルタ
            if (!empty($conditions['category_id'])) {
                $query->whereHas('bikeModel', fn ($q) => $q->where('category_id', (int) $conditions['category_id']));
            }

            // タグフィルタ
            if (!empty($conditions['tag'])) {
                $tagName = $conditions['tag'];
                $query->whereHas('tags', function ($q) use ($tagName) {
                    $q->where('name', $tagName);
                });
            }
            // bike_model_ids フィルタ
            if (!empty($conditions['bike_model_ids'])) {
                $query->whereIn('bike_model_id', array_map('intval', $conditions['bike_model_ids']));
            }

            // 走行距離フィルタ
            if (!empty($conditions['max_mileage'])) {
                $query->where('mileage', '<=', (int) $conditions['max_mileage']);
            }
            if (!empty($conditions['min_mileage'])) {
                $query->where('mileage', '>=', (int) $conditions['min_mileage']);
            }

            // 年式フィルタ
            if (!empty($conditions['min_model_year'])) {
                $query->where('model_year', '>=', (int) $conditions['min_model_year']);
            }
            if (!empty($conditions['max_model_year'])) {
                $query->where('model_year', '<=', (int) $conditions['max_model_year']);
            }

            // キーワードフィルタ（LIKEで近似）
            if ($feature->keyword) {
                $kw = $feature->keyword;
                $query->where(function ($q) use ($kw) {
                    $q->where('title', 'LIKE', "%{$kw}%")
                      ->orWhereHas('bikeModel', fn ($sub) => $sub->where('name', 'LIKE', "%{$kw}%"));
                });
            }

            // 価格統計
            $priceStats = (clone $query)->selectRaw('
                AVG(total_price) as avg_price,
                MIN(total_price) as min_price,
                MAX(total_price) as max_price
            ')->first();

            // TOP3車種
            $topModels = (clone $query)
                ->selectRaw('bike_model_id, COUNT(*) as cnt')
                ->groupBy('bike_model_id')
                ->orderByDesc('cnt')
                ->limit(3)
                ->get();

            $modelIds = $topModels->pluck('bike_model_id')->toArray();
            $modelNames = BikeModel::whereIn('id', $modelIds)->pluck('name', 'id');

            $modelAvgPrices = Listing::where('is_sold_out', false)
                ->where('total_price', '>', 0)
                ->whereIn('bike_model_id', $modelIds)
                ->selectRaw('bike_model_id, AVG(total_price) as avg_price')
                ->groupBy('bike_model_id')
                ->pluck('avg_price', 'bike_model_id');

            $topModelsFormatted = $topModels->map(fn ($row) => [
                'name' => $modelNames->get($row->bike_model_id, '不明'),
                'count' => (int) $row->cnt,
                'avg_price' => $modelAvgPrices->get($row->bike_model_id, 0) > 0
                    ? number_format((float) ($modelAvgPrices->get($row->bike_model_id) / 10000), 1)
                    : null,
            ])->toArray();

            $totalCount = (clone $query)->count();

            return [
                'total_count' => $totalCount,
                'avg_price' => $priceStats->avg_price > 0 ? number_format((float) ($priceStats->avg_price / 10000), 1) : null,
                'min_price' => $priceStats->min_price > 0 ? number_format((float) ($priceStats->min_price / 10000), 1) : null,
                'top_model' => $topModelsFormatted[0]['name'] ?? null,
                'top_models' => $topModelsFormatted,
            ];
        });
    }
}
