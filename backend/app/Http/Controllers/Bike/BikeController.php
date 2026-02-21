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
use App\Services\Bike\SeoLandingService; 
use App\Services\Bike\PriceStatsService;
use App\Http\Resources\Bike\ListingResource;
use App\Http\Requests\Bike\StoreReviewRequest;

/**
 * バイク検索・表示機能を提供するメインコントローラー
 * パフォーマンス最適化済み（N+1対策、キャッシュ活用）
 */
final class BikeController extends Controller
{
    public function __construct(
        private readonly BikeService $bikeService,
        private readonly ListingSearchService $listingSearchService,
        private readonly SeoLandingService $seoLandingService,
        private readonly PriceStatsService $priceStatsService
    ) {}

    /**
     * トップページの表示
     */
    public function index(): View
    {
        $popularBikes = $this->bikeService->getPopularBikesForTopPage();
        $categories = $this->bikeService->getCategoriesForTopPage();
        $manufacturers = $this->bikeService->getMajorManufacturers();
        $regions = config('bike.regions');
        $latestReviews = $this->bikeService->getLatestReviews();
        $licenses = $this->bikeService->getLicenses();
        // Serviceから人気のタグを取得
        $popularTags = $this->listingSearchService->getPopularTags();

        return view('bikes.index', compact('popularBikes', 'categories', 'manufacturers', 'regions', 'latestReviews', 'licenses', 'popularTags'));
    }

    /**
     * 都道府県一覧ページの表示
     */
    public function prefectures(): View
    {
        $regions = $this->bikeService->getRegions();
        return view('bikes.prefectures', compact('regions'));
    }

    /**
     * 検索結果ページの表示
     */
    public function search(Request $request): View|JsonResponse
    {
        $keyword = $request->query('keyword');
        $prefecture = $request->query('prefecture');
        $sort = (string) $request->query('sort', 'bargain_desc');

        $filters = [
            'min_price'          => $request->query('min_price'),
            'max_price'          => $request->query('max_price'),
            'min_mileage'        => $request->query('min_mileage'),
            'max_mileage'        => $request->query('max_mileage'),
            'min_year'           => $request->query('min_year'),
            'max_year'           => $request->query('max_year'),
            'min_displacement'   => $request->query('min_displacement'),
            'max_displacement'   => $request->query('max_displacement'),
            'manufacturer_id'    => $request->query('manufacturer_id'),
            'bike_model_id'      => $request->query('bike_model_id'),
            'category_id'        => $request->query('category_id'),
            'is_new'             => $request->query('is_new'),
            'has_repair_history' => $request->query('has_repair_history'),
            'prefecture'         => $request->query('prefecture'),
            'tag'                => $request->query('tag'),
        ];

        if ($request->has('count_only')) {
            $count = $this->listingSearchService->getFilteredCount($keyword, $prefecture, $filters);
            return response()->json(['total' => $count]);
        }

        $result = $this->listingSearchService->search($keyword, $prefecture, $sort, $filters);
        $pageTitle = $this->listingSearchService->generatePageTitle($keyword, $prefecture, $filters);
        // ★修正: ここで人気のタグを取得してビューに渡す
        $popularTags = $this->listingSearchService->getPopularTags();

        return view('bikes.search', array_merge($result, [
            'keyword'    => $keyword,
            'prefecture' => $prefecture,
            'sort'       => $sort,
            'pageTitle'  => $pageTitle,
            'popularTags' => $popularTags,
        ]));
    }

    /**
     * 車両詳細ページを表示（爆速版）
     */
    public function show(int $id): View
    {
        // 1. Eager Loading の最適化
        $listing = Listing::with([
            'shop', 
            'bikeModel.manufacturer', 
            'bikeModel.categoryData',
            'bikeModel.marketStats' ,
            'tags'
        ])->findOrFail($id);

        // 2. 関連車両の取得（同じ車種）
        $relatedListings = collect();
        if ($listing->bike_model_id) {
            $relatedRaw = $this->bikeService->getRelatedListings($listing->bike_model_id, $listing->id, 8);
            $relatedListings = ListingResource::collection($relatedRaw)->resolve();
        }

        // ★追加：類似車両の取得（同じメーカーの別車種など、視野を広げる提案）
        $similarListings = collect();
        if ($listing->manufacturer_id) {
            $similarRaw = Listing::with(['shop', 'bikeModel.manufacturer'])
                ->where('manufacturer_id', $listing->manufacturer_id)
                ->where('bike_model_id', '!=', $listing->bike_model_id) // 違う車種
                ->where('is_sold_out', false)
                ->inRandomOrder()
                ->take(8) // 8件取得
                ->get();
            $similarListings = ListingResource::collection($similarRaw)->resolve();
        }

        // 3. SEO用リンク集の生成
        $seoLinks = $this->bikeService->getSeoLinks($listing);

        // 4. 市場統計データの取得
        $stats = $this->priceStatsService->getModelStats((int)$listing->bike_model_id);

        // 5. リソース変換
        $data = (object) (new ListingResource($listing))->resolve();

        // DBから取得したタグをビューに渡す
        $tags = $listing->tags;

        return view('bikes.show', [
            'listing'         => $data,
            'relatedListings' => $relatedListings,
            'similarListings' => $similarListings, // ビューに渡す
            'seoLinks'        => $seoLinks,
            'stats'           => $stats,
            'histogram'       => $stats['distribution'] ?? [],
            'tags'            => $tags
        ]);
    }

    /**
     * 車種別・相場＆リセール情報ページ（爆速版）
     */
    public function modelDetail(int $id): View
    {
        $model = $this->bikeService->getBikeModelDetail($id);
        $model->load(['reviews']);

        $stats = $this->priceStatsService->getModelStats($id);
        $resale = $this->priceStatsService->getResaleStats($id);
        $history = $this->priceStatsService->getPriceHistory($id);

        $listingsRaw = $this->bikeService->getRelatedListings($id, 0, 8);
        $listings = ListingResource::collection($listingsRaw)->resolve();

        return view('bikes.model_detail', compact('model', 'stats', 'resale', 'history', 'listings'));
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

    public function models(): View
    {
        $data = $this->bikeService->getAllModelsForIndex();
        return view('bikes.models', $data);
    }

    public function wishlist(): View
    {
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

    public function compare(): View
    {
        return view('pages.compare');
    }

    /**
     * SEO着地ページ (地域 × メーカー/カテゴリ)
     */
    public function landing(string $prefecture, string $slug): View
    {
        $pageInfo = $this->seoLandingService->resolvePageInfo($prefecture, $slug);

        if (empty($pageInfo)) {
            abort(404);
        }

        $filters = $pageInfo['filters'];
        $meta = $pageInfo['meta'];

        $result = $this->listingSearchService->search(null, $prefecture, 'latest', $filters);
        
        return view('bikes.landing', array_merge($result, [
            'pageInfo' => $meta,
            'keyword' => '', 
            'prefecture' => $prefecture,
            'sort' => 'latest',
        ]));
    }

    /**
     * レビュー投稿処理
     */
    public function storeReview(StoreReviewRequest $request, int $id)
    {
        $validated = $request->validated();
        $model = $this->bikeService->getBikeModelDetail($id);
        $this->bikeService->createReview($model->id, $validated);

        return redirect()->route('bikes.model_detail', $id)->with('success', 'レビューを投稿しました！');
    }
}