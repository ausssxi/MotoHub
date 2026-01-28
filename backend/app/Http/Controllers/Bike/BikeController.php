<?php

declare(strict_types=1);

namespace App\Http\Controllers\Bike;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Contracts\View\View;
use App\Models\Listing;
use App\Services\Bike\BikeService;
use App\Services\Bike\ListingSearchService;
use App\Http\Resources\Bike\ListingResource;

/**
 * バイク検索・表示機能を提供するメインコントローラー
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
        $categories = $this->bikeService->getCategoriesForTopPage();
        $regions = config('bike.regions');

        // totalListingsCount は自動注入されるため削除
        return view('bikes.index', compact('popularBikes', 'categories', 'regions'));
    }

    /**
     * 検索結果ページの表示
     */
    public function search(Request $request): View|JsonResponse
    {
        $keyword = $request->query('keyword');
        $prefecture = $request->query('prefecture');
        $sort = (string) $request->query('sort', 'latest');

        $filters = [
            'min_price'          => $request->query('min_price'),
            'max_price'          => $request->query('max_price'),
            'min_mileage'        => $request->query('min_mileage'),
            'max_mileage'        => $request->query('max_mileage'),
            'min_year'           => $request->query('min_year'),
            'max_year'           => $request->query('max_year'),
            'manufacturer_id'    => $request->query('manufacturer_id'),
            'bike_model_id'      => $request->query('bike_model_id'),
            'is_new'             => $request->query('is_new'),
            'has_repair_history' => $request->query('has_repair_history'),
            'prefecture'         => $request->query('prefecture'),
        ];

        if ($request->has('count_only')) {
            $count = $this->listingSearchService->getFilteredCount($keyword, $prefecture, $filters);
            return response()->json(['total' => $count]);
        }

        $result = $this->listingSearchService->search($keyword, $prefecture, $sort, $filters);
        $searchMeta = $this->listingSearchService->getSearchMetadata($keyword, $prefecture, $filters);

        // totalListingsCount を削除
        return view('bikes.search', array_merge($result, [
            'keyword'    => $keyword,
            'prefecture' => $prefecture,
            'sort'       => $sort,
            'meta'       => $searchMeta,
        ]));
    }

    /**
     * 車両詳細ページを表示
     */
    public function show($id)
    {
        // 1. データを取得
        $listing = Listing::with(['shop', 'bikeModel.manufacturer'])->findOrFail($id);

        // 2. 関連車両（同じ車種）を取得
        // ★修正: リポジトリ直接ではなく、Service経由で取得
        $relatedListings = collect();
        if ($listing->bike_model_id) {
            $relatedRaw = $this->bikeService->getRelatedListings($listing->bike_model_id, $listing->id, 8);
            $relatedListings = ListingResource::collection($relatedRaw)->resolve();
        }

        // 3. Resourceを使って整形
        $data = (object) (new ListingResource($listing))->resolve();

        return view('bikes.show', [
            'listing' => $data,
            'relatedListings' => $relatedListings,
        ]);
    }

    public function getModels(int $manufacturerId): JsonResponse
    {
        $models = $this->listingSearchService->getModelsByManufacturer($manufacturerId);
        return response()->json($models);
    }

    public function suggest(Request $request): JsonResponse
    {
        $keyword = (string) $request->query('keyword', '');
        if (mb_strlen($keyword) < 1) {
            return response()->json([]);
        }

        $suggestions = $this->bikeService->getSearchSuggestions($keyword);
        return response()->json($suggestions);
    }

    /**
     * 車種一覧ページの表示
     */
    public function models(): View
    {
        $data = $this->bikeService->getAllModelsForIndex();
        
        // totalListingsCount を削除
        // 配列のマージが不要になったのでシンプルに渡せます
        return view('bikes.models', $data);
    }

    /**
     * お気に入り一覧ページの表示
     */
    public function wishlist(): View
    {
        // 変数が不要になったのでそのままビューを返します
        return view('pages.wishlist');
    }

    public function fetchWishlist(Request $request): JsonResponse
    {
        $ids = explode(',', $request->query('ids', ''));
        if (empty($ids) || $ids[0] === '') {
            return response()->json([]);
        }

        $listings = Listing::with(['bikeModel.manufacturer', 'shop', 'site'])
            ->whereIn('id', $ids)
            ->where('is_sold_out', false)
            ->get();

        return response()->json(ListingResource::collection($listings)->resolve());
    }

    /**
     * 比較ページの表示
     */
    public function compare(): View
    {
        // 変数が不要になったのでそのままビューを返します
        return view('pages.compare');
    }
}