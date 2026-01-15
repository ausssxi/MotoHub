<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\BikeService;
use App\Services\ListingSearchService;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;

final class BikeController extends Controller
{
    public function __construct(
        private readonly BikeService $bikeService,
        private readonly ListingSearchService $listingSearchService
    ) {}

    public function index(): View
    {
        $popularBikes = $this->bikeService->getPopularBikesForTopPage();
        $totalListingsCount = $this->listingSearchService->getActiveCount();

        return view('bikes.index', compact('popularBikes', 'totalListingsCount'));
    }

    /**
     * 検索結果の表示
     */
    public function search(Request $request): View
    {
        $keyword = (string) $request->query('keyword', '');
        $sort = (string) $request->query('sort', 'latest'); // デフォルトは新着順

        $result = $this->listingSearchService->search($keyword, $sort);
        $totalListingsCount = $this->listingSearchService->getActiveCount();

        return view('bikes.search', [
            'listings'           => $result['items'],
            'pagination'         => $result['pagination'],
            'keyword'            => $keyword,
            'sort'               => $sort,
            'totalListingsCount' => $totalListingsCount,
        ]);
    }
}