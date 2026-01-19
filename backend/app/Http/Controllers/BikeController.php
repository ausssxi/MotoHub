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
     * Agoda風スライダーに対応し、スマホ版の「件数のみ更新」リクエストも処理します
     */
    public function search(Request $request): View|JsonResponse
    {
        // 1. 基本パラメータの取得
        $keyword = $request->query('keyword');
        $prefecture = $request->query('prefecture');
        $sort = (string) $request->query('sort', 'latest');

        // 2. フィルター条件の抽出
        $filters = [
            'min_price'   => $request->query('min_price'),
            'max_price'   => $request->query('max_price'),
            'min_mileage' => $request->query('min_mileage'),
            'max_mileage' => $request->query('max_mileage'),
            'min_year'    => $request->query('min_year'),
            'max_year'    => $request->query('max_year'),
        ];

        // --- 重要：スマホ版モーダルからの「件数のみ取得」リクエストへの対応 ---
        if ($request->has('count_only')) {
            $count = $this->listingSearchService->getFilteredCount($keyword, $prefecture, $filters);
            return response()->json(['total' => $count]);
        }

        // 3. 通常の検索実行
        $result = $this->listingSearchService->search($keyword, $prefecture, $sort, $filters);
        
        // 4. スライダーの境界値（メタデータ）を取得
        $searchMeta = $this->listingSearchService->getSearchMetadata();
        
        // 5. サイト全体の有効掲載台数
        $totalListingsCount = $this->listingSearchService->getActiveCount();

        return view('bikes.search', [
            'items'              => $result['items'],
            'pagination'         => $result['pagination'],
            'keyword'            => $keyword,
            'prefecture'         => $prefecture,
            'sort'               => $sort,
            'filters'            => $filters,
            'meta'               => $searchMeta,
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
        $totalListingsCount = $this->listingSearchService->getActiveCount();
        return view('pages.about', compact('totalListingsCount'));
    }
}