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
use App\Http\Requests\Bike\BikeSearchRequest; // ★追加: Form Request

/**
 * バイク検索・表示機能を提供するメインコントローラー
 * パフォーマンス最適化済み（N+1対策、キャッシュ活用、関心の分離）
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
        $popularTags = $this->listingSearchService->getPopularTags();
        $features = $this->bikeService->getFeaturesForTopPage();

        return view('bikes.index', compact('popularBikes', 'categories', 'manufacturers', 'regions', 'latestReviews', 'licenses', 'popularTags', 'features'));
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
     * ★変更: Form Request を使用し、バリデーション済みの安全なデータを取得
     */
    public function search(BikeSearchRequest $request): View|JsonResponse
    {
        // フォームリクエストから安全なフィルター条件のみを取得
        $filters = $request->toFilters();
        
        $keyword = $request->query('keyword');
        $prefecture = $request->query('prefecture');
        $sort = (string) $request->query('sort', 'bargain_desc');

        if ($request->has('count_only')) {
            $count = $this->listingSearchService->getFilteredCount($keyword, $prefecture, $filters);
            return response()->json(['total' => $count]);
        }

        $result = $this->listingSearchService->search($keyword, $prefecture, $sort, $filters);
        $pageTitle = $this->listingSearchService->generatePageTitle($keyword, $prefecture, $filters);
        $popularTags = $this->listingSearchService->getPopularTags();

        return view('bikes.search', array_merge($result, [
            'keyword'     => $keyword,
            'prefecture'  => $prefecture,
            'sort'        => $sort,
            'pageTitle'   => $pageTitle,
            'popularTags' => $popularTags,
        ]));
    }

    /**
     * 車両詳細ページを表示（爆速版）
     * ★変更: DBクエリをすべて BikeService に移譲し、コントローラーを軽量化
     */
    public function show(int $id): View
    {
        // 1. 詳細データの取得（Service経由でRepositoryに隠蔽）
        $listing = $this->bikeService->getListingDetail($id);

        // 2. 関連・類似車両の取得
        $relatedRaw = $listing->bike_model_id 
            ? $this->bikeService->getRelatedListings($listing->bike_model_id, $listing->id, 8) 
            : collect();
            
        $similarRaw = $this->bikeService->getSimilarListings($listing->manufacturer_id, $listing->bike_model_id, 8);

        // 3. 市場統計とレビューの取得
        $stats = $this->priceStatsService->getModelStats((int)$listing->bike_model_id);
        $reviews = $listing->bike_model_id 
            ? $this->bikeService->getReviewsByModelId((int)$listing->bike_model_id, 3) 
            : collect();

        // 4. データ整形とリンク生成
        $data = (object) (new ListingResource($listing))->resolve();
        $seoLinks = $this->bikeService->getSeoLinks($listing);
        $dynamicLinks = $this->bikeService->generateDynamicLinks($data, $seoLinks, $listing->tags);

        return view('bikes.show', [
            'listing'         => $data,
            'relatedListings' => ListingResource::collection($relatedRaw)->resolve(),
            'similarListings' => ListingResource::collection($similarRaw)->resolve(),
            'dynamicLinks'    => $dynamicLinks,
            'seoLinks'        => $seoLinks,
            'stats'           => $stats,
            'histogram'       => $stats['distribution'] ?? [],
            'tags'            => $listing->tags,
            'reviews'         => $reviews,
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

        // （※ここも将来的にRepositoryに移譲できますが、一旦既存のまま維持しています）
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
     * レビュー投稿処理（Ajax対応版のみに統合）
     */
    public function storeReview(StoreReviewRequest $request, int $id)
    {
        $validated = $request->validated();
        $model = $this->bikeService->getBikeModelDetail($id);
        
        // サービスでDBに保存（戻り値がない場合も想定して、表示用データは$validatedから作る）
        $this->bikeService->createReview($model->id, $validated);

        // ★画面遷移なしのAjaxリクエストだった場合は、JSONで結果とUI更新用のデータを返す
        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'レビューを投稿しました！',
                'review'  => [
                    'title'      => $validated['title'] ?? '無題',
                    'body'       => $validated['body'] ?? '',
                    'rating'     => $validated['rating'] ?? 5,
                    'nickname'   => $validated['nickname'] ?? '匿名ユーザー',
                    'created_at' => now()->format('Y年m月')
                ]
            ]);
        }

        // 通常のフォーム送信（別ページからの投稿）の場合は元の仕様通りリダイレクト
        return redirect()->route('bikes.model_detail', $id)->with('success', 'レビューを投稿しました！');
    }
}