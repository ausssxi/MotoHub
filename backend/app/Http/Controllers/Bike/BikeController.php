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

        return view('bikes.index', compact('popularBikes', 'categories', 'manufacturers', 'regions'));
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
            'category_id'        => $request->query('category_id'),
            'is_new'             => $request->query('is_new'),
            'has_repair_history' => $request->query('has_repair_history'),
            'prefecture'         => $request->query('prefecture'),
        ];

        if ($request->has('count_only')) {
            $count = $this->listingSearchService->getFilteredCount($keyword, $prefecture, $filters);
            return response()->json(['total' => $count]);
        }

        $result = $this->listingSearchService->search($keyword, $prefecture, $sort, $filters);
        
        // ページタイトルをサービスで生成
        $pageTitle = $this->listingSearchService->generatePageTitle($keyword, $prefecture, $filters);

        // $result の中に 'meta' も含まれているため、getSearchMetadata の再呼び出しは削除しました
        return view('bikes.search', array_merge($result, [
            'keyword'    => $keyword,
            'prefecture' => $prefecture,
            'sort'       => $sort,
            'pageTitle'  => $pageTitle,
        ]));
    }

    /**
     * 車両詳細ページを表示
     */
    public function show($id)
    {
        $listing = Listing::with(['shop', 'bikeModel.manufacturer', 'bikeModel.categoryData'])->findOrFail($id);

        $relatedListings = collect();
        if ($listing->bike_model_id) {
            $relatedRaw = $this->bikeService->getRelatedListings($listing->bike_model_id, $listing->id, 8);
            $relatedListings = ListingResource::collection($relatedRaw)->resolve();
        }

        // 2. SEO用リンク集の生成
        $seoLinks = $this->bikeService->getSeoLinks($listing);

        $marketAnalysis = $this->bikeService->getMarketAnalysis(
            $listing->bike_model_id, 
            (int)$listing->total_price
        );

        $data = (object) (new ListingResource($listing))->resolve();

        return view('bikes.show', [
            'listing' => $data,
            'relatedListings' => $relatedListings,
            'seoLinks' => $seoLinks,
            'stats' => $marketAnalysis['stats'],     
            'histogram' => $marketAnalysis['histogram'] 
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
        // ページ情報を解決
        $pageInfo = $this->seoLandingService->resolvePageInfo($prefecture, $slug);

        if (empty($pageInfo)) {
            abort(404);
        }

        $filters = $pageInfo['filters'];
        $meta = $pageInfo['meta'];

        // 検索実行
        $result = $this->listingSearchService->search(null, $prefecture, 'latest', $filters);
        
        // 検索結果ページと似ているが、SEOに特化した専用ビューを表示
        return view('bikes.landing', array_merge($result, [
            'pageInfo' => $meta,
            'keyword' => '', // 検索窓用
            'prefecture' => $prefecture,
            'sort' => 'latest',
        ]));
    }

    /**
     *車種別・相場＆リセール情報ページ
     */
    public function modelDetail(int $id): View
    {
        // 車種情報の取得
        $model = $this->bikeService->getBikeModelDetail($id);
        $model->load(['reviews']);

        // 統計データの取得
        $stats = $this->priceStatsService->getModelStats($id);
        $resale = $this->priceStatsService->getResaleStats($id);

        // 現在販売中の車両を取得（安い順に8件）
        // ListingSearchService経由などで取得しても良いですが、ここではRepositoryを直接利用するか、Serviceに追加したメソッドを使います。
        // 今回はシンプルに Service にメソッドを追加するか、既存の getRelatedListings を流用します。
        
        // 自分自身を除外する必要はないので、excludeIdに0などを渡して検索
        $listingsRaw = $this->bikeService->getRelatedListings($id, 0, 8);
        $listings = ListingResource::collection($listingsRaw)->resolve();

        return view('bikes.model_detail', compact('model', 'stats', 'resale', 'listings'));
    }

/**
     * レビュー投稿処理
     * Request から StoreReviewRequest に変更
     */
    public function storeReview(StoreReviewRequest $request, int $id)
    {
        // バリデーション済みのデータを取得
        $validated = $request->validated();

        // モデルの存在確認（Service経由）
        $model = $this->bikeService->getBikeModelDetail($id);

        // サービス経由で保存
        $this->bikeService->createReview($model->id, $validated);

        return redirect()->route('bikes.model_detail', $id)->with('success', 'レビューを投稿しました！');
    }
}