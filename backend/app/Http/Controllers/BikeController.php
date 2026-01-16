<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\BikeService;
use App\Services\ListingSearchService;
use Illuminate\Http\Request;
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
     * 運営者情報の表示
     * AdSense 審査に必要な固定ページは pages フォルダ内で管理
     */
    public function about(): View
    {
        // 検索件数の取得を削除してシンプルに
        return view('pages.about');
    }
}