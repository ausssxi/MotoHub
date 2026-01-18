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
        // 地域データは config から取得（以前の定義を維持）
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
     * Service層で整形済みのデータを受け取り、Viewへ渡します
     */
    public function search(Request $request): View
    {
        $keyword = $request->query('keyword');
        $prefecture = $request->query('prefecture');
        $sort = (string) $request->query('sort', 'latest');

        // フィルター条件を抽出
        $filters = [
            'min_price'   => $request->query('min_price'),
            'max_price'   => $request->query('max_price'),
            'min_mileage' => $request->query('min_mileage'),
            'max_mileage' => $request->query('max_mileage'),
            'min_year'    => $request->query('min_year'),
            'max_year'    => $request->query('max_year'),
        ];

        // Serviceから items と pagination(pages, display_textを含む) を取得
        $result = $this->listingSearchService->search($keyword, $prefecture, $sort, $filters);
        $totalListingsCount = $this->listingSearchService->getActiveCount();

        return view('bikes.search', [
            'items'              => $result['items'],      // Bladeの @forelse ($items...) に合わせる
            'pagination'         => $result['pagination'],   // 整形済みデータ
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
            'manufacturers'      => $data['manufacturers'],
            'totalModelsCount'   => $data['totalModelsCount'],
            'totalListingsCount' => $totalListingsCount,
        ]);
    }

    /**
     * 運営者情報の表示
     */
    public function about(): View
    {
        // 掲載台数などの動的データが必要な場合はここでも取得して渡せます
        return view('pages.about');
    }
}