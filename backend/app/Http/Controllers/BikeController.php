<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\BikeService;
use App\Services\ListingSearchService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Contracts\View\View;

/**
 * MotoHub の表示制御を担当
 */
final class BikeController extends Controller
{
    public function __construct(
        private readonly BikeService $bikeService,
        private readonly ListingSearchService $listingSearchService
    ) {}

    /**
     * トップページの表示
     */
    public function index(): View
    {
        $popularBikes = $this->bikeService->getPopularBikesForTopPage();
        $regions = config('bike.regions', []);
        $totalListingsCount = $this->listingSearchService->getActiveCount();

        return view('bikes.index', compact('popularBikes', 'regions', 'totalListingsCount'));
    }

    /**
     * サジェスト用のJSONデータを返す
     */
    public function suggest(Request $request): JsonResponse
    {
        $keyword = $request->query('keyword');
        if (empty($keyword)) {
            return response()->json([]);
        }

        $suggestions = $this->bikeService->getSearchSuggestions($keyword);
        return response()->json($suggestions);
    }

    /**
     * 検索結果の表示
     */
    public function search(Request $request): View
    {
        $keyword = $request->query('keyword');
        $prefecture = $request->query('prefecture');
        $sort = (string) $request->query('sort', 'latest');

        $result = $this->listingSearchService->search($keyword, $prefecture, $sort);
        $totalListingsCount = $this->listingSearchService->getActiveCount();

        return view('bikes.search', [
            'listings'           => $result['items'],
            'pagination'         => $result['pagination'],
            'keyword'            => $keyword,
            'prefecture'         => $prefecture,
            'sort'               => $sort,
            'totalListingsCount' => $totalListingsCount,
        ]);
    }

    /**
     * 全車種一覧ページの表示
     */
    public function models(): View
    {
        $data = $this->bikeService->getAllModelsForIndex();
        $totalListingsCount = $this->listingSearchService->getActiveCount();

        return view('bikes.models', [
            'manufacturers' => $data['manufacturers'],
            'totalModelsCount' => $data['totalModelsCount'],
            'totalListingsCount' => $totalListingsCount,
        ]);
    }

    /**
     * 運営者情報の表示
     * AdSense 審査に必要な固定ページは pages フォルダ内で管理
     */
    public function about(): View
    {
        // 検索件数の取得を削除してシンプルに
        return view('pages.about');
    }
}