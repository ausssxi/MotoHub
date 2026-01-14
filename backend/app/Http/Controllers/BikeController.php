<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\BikeService;
use App\Services\ListingSearchService;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;

/**
 * 車種・出品情報の画面制御を担当
 */
final class BikeController extends Controller
{
    /**
     * @param BikeService $bikeService
     * @param ListingSearchService $listingSearchService
     */
    public function __construct(
        private readonly BikeService $bikeService,
        private readonly ListingSearchService $listingSearchService
    ) {}

    /**
     * トップページ（車種一覧）の表示
     */
    public function index(): View
    {
        $popularBikes = $this->bikeService->getPopularBikesForTopPage();
        $regions = config('bike.regions', []);
        
        $totalListingsCount = $this->listingSearchService->getActiveCount();

        return view('bikes.index', compact('popularBikes', 'regions', 'totalListingsCount'));
    }

    /**
     * キーワードに基づく出品情報の検索
     */
    public function search(Request $request): View
    {
        $keyword = $request->query('keyword', '');
        
        // 1. サービスから結果セットを取得
        $result = $this->listingSearchService->search((string) $keyword);
        
        // 2. ナビゲーション用の総掲載台数を取得
        $totalListingsCount = $this->listingSearchService->getActiveCount();

        return view('bikes.search', [
            // 修正：$result 全体ではなく、中身の 'items' だけを listings として渡す
            'listings'         => $result['items'],
            'totalSearchCount' => $result['total'],
            'keyword'          => $keyword,
            'regions'          => config('bike.regions', []),
            'totalListingsCount' => $totalListingsCount,
        ]);
    }
}