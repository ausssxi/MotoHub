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

        $relatedFeatures = SeoFeature::active()
            ->where('id', '!=', $feature->id)
            ->ordered()
            ->limit(6)
            ->get();

        return view('features.show', array_merge($result, [
            'feature' => $feature,
            'sort' => $sort,
            'relatedFeatures' => $relatedFeatures,
        ]));
    }
}
