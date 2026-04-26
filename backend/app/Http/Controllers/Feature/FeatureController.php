<?php

declare(strict_types=1);

namespace App\Http\Controllers\Feature;

use App\Http\Controllers\Controller;
use App\Models\SeoFeature;
use App\Services\Bike\ListingSearchService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

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

        // TOP3車種の集計
        $topModels = $this->computeTopModels($result['items']);

        $relatedFeatures = SeoFeature::active()
            ->where('id', '!=', $feature->id)
            ->ordered()
            ->limit(6)
            ->get();

        return view('features.show', array_merge($result, [
            'feature' => $feature,
            'sort' => $sort,
            'topModels' => $topModels,
            'relatedFeatures' => $relatedFeatures,
        ]));
    }

    /**
     * 検索結果からTOP3車種を集計
     */
    private function computeTopModels(array $items): array
    {
        $grouped = collect($items)
            ->filter(fn ($item) => !empty($item['bike_model_id']) && !empty($item['bike_model_name']))
            ->groupBy('bike_model_id');

        if ($grouped->isEmpty()) {
            return [];
        }

        return $grouped->map(function ($listings, $modelId) {
            $prices = collect($listings)->pluck('total_price')->filter(fn ($p) => $p > 0);

            return [
                'name' => $listings->first()['bike_model_name'],
                'count' => $listings->count(),
                'avg_price' => $prices->isNotEmpty() ? number_format($prices->avg() / 10000, 1) : null,
            ];
        })
            ->sortByDesc('count')
            ->take(3)
            ->values()
            ->toArray();
    }
}
