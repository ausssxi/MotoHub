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
use App\Http\Requests\Bike\BikeSearchRequest;

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
        
        // 無限スクロール処理
        if ($request->query('load_more')) {
            $html = '';
            foreach ($result['items'] as $listing) {
                $html .= view('bikes.partials.bike_card', ['listing' => $listing])->render();
            }
            return response()->json([
                'html' => $html,
                'next_url' => $result['pagination']['next_url']
            ]);
        }

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
     * 車両詳細ページを表示
     */
    public function show(int $id): View
    {
        $this->bikeService->incrementViewCount($id);

        $listing = $this->bikeService->getListingDetail($id);

        $relatedRaw = $listing->bike_model_id 
            ? $this->bikeService->getRelatedListings($listing->bike_model_id, $listing->id, 8) 
            : collect();
            
        $similarRaw = $this->bikeService->getSimilarListings($listing->manufacturer_id, $listing->bike_model_id, 8);

        $currentPrice = is_numeric($listing->total_price) ? (float)$listing->total_price : 0;
        $stats = $this->priceStatsService->getModelStats((int)$listing->bike_model_id, $currentPrice);
        
        $reviews = $listing->bike_model_id 
            ? $this->bikeService->getReviewsByModelId((int)$listing->bike_model_id, 3) 
            : collect();

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

    public function getModels(int $manufacturerId): JsonResponse
    {
        $models = $this->listingSearchService->getModelsByManufacturer($manufacturerId);
        return response()->json($models);
    }

    /**
     * リッチ・オートコンプリート用のサジェストAPI
     */
    public function suggest(Request $request): JsonResponse
    {
        $keyword = (string) $request->query('keyword', '');
        if (mb_strlen($keyword) < 1) {
            return response()->json([]);
        }

        // 1. 車種のサジェスト（従来のテキストベース）
        $models = $this->bikeService->getSearchSuggestions($keyword);
        
        // 2. 実際の車両のサジェスト（画像付きの直感的なUI用）
        // Meilisearchの爆速処理を利用して、キーワードに合致する「おすすめの3台」を引っ張る
        $listingsRaw = tap($this->listingSearchService->search($keyword, null, 'latest', [], 3), function($res) {
            return $res;
        })['items'];

        // フロントエンドに扱いやすい形に整形して返す
        return response()->json([
            'models' => $models,
            'listings' => collect($listingsRaw)->map(function ($bike) {
                return [
                    'id' => $bike['id'],
                    'name' => $bike['name'],
                    'image' => $bike['images'][0] ?? null,
                    'total_price' => $bike['total_price'],
                    'model_year' => $bike['model_year'],
                    'mileage' => $bike['mileage'],
                    'shop_name' => $bike['shop_name'],
                ];
            })
        ]);
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

    public function landing(string $prefecture, string $slug): View
    {
        $pageInfo = $this->seoLandingService->resolvePageInfo($prefecture, $slug);
        if (empty($pageInfo)) abort(404);
        $result = $this->listingSearchService->search(null, $prefecture, 'latest', $pageInfo['filters']);
        return view('bikes.landing', array_merge($result, [
            'pageInfo' => $pageInfo['meta'],
            'keyword' => '', 
            'prefecture' => $prefecture,
            'sort' => 'latest',
        ]));
    }

    public function storeReview(StoreReviewRequest $request, int $id)
    {
        $validated = $request->validated();
        $model = $this->bikeService->getBikeModelDetail($id);
        $this->bikeService->createReview($model->id, $validated);
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
        return redirect()->route('bikes.model_detail', $id)->with('success', 'レビューを投稿しました！');
    }

    public function modelDetail($id, \App\Services\Bike\PriceStatsService $priceStatsService)
    {
        // 1. バイクモデルの基本情報とリレーション（メーカー、レビューなど）を取得
        $model = \App\Models\BikeModel::with(['manufacturer', 'reviews'])->findOrFail($id);

        // 2. 関連する販売中の中古車を取得（最大5件）
        $listings = \App\Models\Listing::with('shop')->where('bike_model_id', $id)
            ->active() 
            ->limit(5)
            ->get()
            ->map(function($listing) {
                // Blade側でエラーにならないように配列の形に成形
                return [
                    'id' => $listing->id,
                    'name' => $listing->title ?? $listing->bikeModel->name,
                    'total_price' => $listing->total_price ? number_format($listing->total_price / 10000, 1) : '-',
                    'prefecture' => $listing->shop->prefecture ?? '地域不明', // shopから都道府県を取得
                    'images' => $listing->images ?? [], // すでにモデルのアクセサで配列化されているのでそのまま渡す
                ];
            });

        // 3. 買取相場・チャート用の本番データ（データベースの集計結果）
        $stats = $priceStatsService->getModelStats((int)$id);
        $history = $priceStatsService->getPriceHistory((int)$id);
        $resale = $priceStatsService->getResaleStats((int)$id);

        return view('bikes.model_detail', compact('model', 'stats', 'history', 'resale', 'listings'));
    }

}