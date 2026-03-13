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
use App\Services\NearbyService;
use App\Http\Resources\Bike\ListingResource;
use App\Http\Requests\Bike\StoreReviewRequest;
use App\Http\Requests\Bike\BikeSearchRequest;
use App\Models\SeoFeature;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

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
        private readonly PriceStatsService $priceStatsService,
        private readonly NearbyService $nearbyService
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
        $seoFeatures = SeoFeature::active()->latest()->limit(4)->get();

        // 最近登録された愛車
        $latestMyBikes = \App\Models\MyBike::with(['bikeModel.manufacturer', 'user'])
            ->latest()
            ->limit(6)
            ->get();

        // ライブ統計バー用のカウント
        $totalListings = Listing::active()->count();
        $priceDropCount = DB::table('price_histories')
            ->whereDate('created_at', today())
            ->distinct('listing_id')
            ->count('listing_id');
        $newListingsCount = Listing::active()
            ->whereDate('listings.created_at', today())
            ->count();

        return view('bikes.index', compact(
            'popularBikes', 'categories', 'manufacturers', 'regions',
            'latestReviews', 'licenses', 'popularTags', 'features', 'seoFeatures',
            'totalListings', 'priceDropCount', 'newListingsCount', 'latestMyBikes'
        ));
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
        $recommendedModels = $this->listingSearchService->getRecommendedModels($result['filters'], $result['items']);

        return view('bikes.search', array_merge($result, [
            'keyword'           => $keyword,
            'prefecture'        => $prefecture,
            'sort'              => $sort,
            'pageTitle'         => $pageTitle,
            'popularTags'       => $popularTags,
            'recommendedModels' => $recommendedModels,
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

        // 値下げ額の計算ロジック（コントローラー側で処理してビューへ渡す）
        $priceDropDiff = null;
        if ($listing->relationLoaded('priceHistories') && $listing->priceHistories->isNotEmpty()) {
            $latestDrop = $listing->priceHistories->first();
            if ($latestDrop->old_price > $latestDrop->new_price) {
                // 万円単位に変換（例: 30000 → 3.0）
                $priceDropDiff = number_format(($latestDrop->old_price - $latestDrop->new_price) / 10000, 1);
            }
        }
        
        $reviews = $listing->bike_model_id 
            ? $this->bikeService->getReviewsByModelId((int)$listing->bike_model_id, 3) 
            : collect();

        $data = (object) (new ListingResource($listing))->resolve();
        $seoLinks = $this->bikeService->getSeoLinks($listing);
        $dynamicLinks = $this->bikeService->generateDynamicLinks($data, $seoLinks, $listing->tags);

        // 近くの駐車場・ショップ（店舗のlat/lngを使用）
        $nearbyParkings = collect();
        $nearbyShops = collect();
        $shopLat = $listing->shop?->latitude;
        $shopLng = $listing->shop?->longitude;
        if ($shopLat && $shopLng) {
            $nearbyParkings = $this->nearbyService->getNearbyParkings((float) $shopLat, (float) $shopLng);
            $nearbyShops = $this->nearbyService->getNearbyShops((float) $shopLat, (float) $shopLng, $listing->shop->id);
        }

        $alsoViewed = collect();
        if ($listing->bike_model_id && $listing->total_price) {
            $alsoViewed = \App\Models\Listing::with('shop')
                ->where('is_sold_out', 0)
                ->where('id', '!=', $listing->id)
                ->where(function($query) use ($listing) {
                    $query->where('category_id', $listing->category_id)
                          ->orWhereBetween('total_price', [
                              $listing->total_price * 0.8,
                              $listing->total_price * 1.2
                          ]);
                })
                ->whereNotNull('total_price')
                ->where('total_price', '>', 0)
                ->inRandomOrder()
                ->limit(6)
                ->get();
        }

        $crossLinks = [
            ['label' => '中古バイク検索', 'url' => route('bikes.search'), 'icon' => 'search', 'description' => '全国の在庫を検索'],
            ['label' => '車種カタログ', 'url' => route('bikes.models'), 'icon' => 'book-open', 'description' => '車種の相場を確認'],
            ['label' => '駐車場マップ', 'url' => route('parking.index'), 'icon' => 'square-parking', 'description' => 'バイク駐車場を探す'],
            ['label' => 'バイク診断', 'url' => route('shindan.index'), 'icon' => 'sparkles', 'description' => 'あなたにピッタリの1台'],
            ['label' => '愛車ガレージ', 'url' => route('mybikes.index'), 'icon' => 'garage', 'description' => '愛車を登録・管理'],
        ];

        return view('bikes.show', [
            'listing'         => $data,
            'bikeModelForUrl' => $listing->bikeModel,
            'relatedListings' => ListingResource::collection($relatedRaw)->resolve(),
            'similarListings' => ListingResource::collection($similarRaw)->resolve(),
            'dynamicLinks'    => $dynamicLinks,
            'seoLinks'        => $seoLinks,
            'stats'           => $stats,
            'histogram'       => $stats['distribution'] ?? [],
            'tags'            => $listing->tags,
            'reviews'         => $reviews,
            'priceDropDiff'   => $priceDropDiff,
            'nearbyParkings'  => $nearbyParkings,
            'nearbyShops'     => $nearbyShops,
            'crossLinks'      => $crossLinks,
            'alsoViewed'      => $alsoViewed,
            'shopLat'         => $shopLat,
            'shopLng'         => $shopLng,
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
        $data = $this->bikeService->getManufacturersForIndex();
        $data['trendingBikes'] = $this->bikeService->getTrendingBikes(10);
        return view('bikes.models', $data);
    }

    /**
     * メーカー別車種一覧API（Ajax用）
     */
    public function modelsApi(int $manufacturerId): JsonResponse
    {
        $groups = $this->bikeService->getGroupedModelsForManufacturer($manufacturerId);

        // 空グループを除外
        $groups = array_filter($groups, fn($list) => count($list) > 0);

        return response()->json(['groups' => $groups]);
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
     * カタログページ（地域なしプログラマティックSEO）
     * URL: /bikes/catalog/{slug}
     */
    public function catalog(string $slug): View
    {
        $pageInfo = $this->seoLandingService->resolveCatalogPage($slug);
        if (empty($pageInfo)) abort(404);

        $result = $this->listingSearchService->search(null, null, 'latest', $pageInfo['filters']);

        return view('bikes.catalog', array_merge($result, [
            'pageInfo' => $pageInfo['meta'],
            'keyword' => '',
            'sort' => 'latest',
        ]));
    }

    /**
     * メーカー×排気量カテゴリページ
     * URL: /bikes/{mfrSlug}/{displacement}cc (例: /bikes/honda/250cc)
     */
    public function categoryByDisplacement(string $mfrSlug, string $ccSlug): View
    {
        $manufacturer = \App\Models\Manufacturer::where('slug', $mfrSlug)->firstOrFail();
        $displacement = (int) rtrim($ccSlug, 'cc');

        // 排気量レンジの定義
        $ranges = [
            50   => ['min' => 0,    'max' => 50,   'label' => '50cc以下（原付一種）'],
            125  => ['min' => 51,   'max' => 125,  'label' => '51〜125cc（原付二種）'],
            250  => ['min' => 126,  'max' => 250,  'label' => '126〜250cc（軽二輪）'],
            400  => ['min' => 251,  'max' => 400,  'label' => '251〜400cc（普通二輪）'],
            750  => ['min' => 401,  'max' => 750,  'label' => '401〜750cc'],
            1000 => ['min' => 751,  'max' => null,  'label' => '751cc以上（大型）'],
        ];

        if (!isset($ranges[$displacement])) {
            abort(404);
        }

        $range = $ranges[$displacement];
        $filters = ['manufacturer_id' => $manufacturer->id];

        if ($range['min'] > 0) {
            $filters['min_displacement'] = $range['min'];
        }
        if ($range['max'] !== null) {
            $filters['max_displacement'] = $range['max'];
        }

        $result = $this->listingSearchService->search(null, null, 'latest', $filters);

        $mfrName = $manufacturer->name;
        $pageInfo = [
            'title' => "{$mfrName} {$displacement}cc 中古バイク一覧",
            'description' => "{$mfrName}の{$range['label']}中古バイク・新車を一括検索。価格・年式・走行距離で比較して、あなたにピッタリの1台を見つけましょう。",
            'h1_html' => "{$mfrName} <span class=\"text-blue-600\">{$displacement}cc</span> の中古バイク一覧",
            'target_name' => "{$mfrName} {$displacement}cc",
        ];

        return view('bikes.catalog', array_merge($result, [
            'pageInfo' => $pageInfo,
            'keyword' => '',
            'sort' => 'latest',
        ]));
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
        // === 1. reCAPTCHAの検証 ===
        $response = \Illuminate\Support\Facades\Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
            'secret' => env('RECAPTCHA_SECRET_KEY'),
            'response' => $request->recaptcha_token, // フロントから送られてきたトークン
        ]);

        // Googleからの返答を配列として変数に格納する（★ここが抜けていた原因です）
        $recaptchaResult = $response->json();
        
        // ★原因究明のため、Googleからの返事をログに書き出す
        \Illuminate\Support\Facades\Log::info('reCAPTCHA検証結果: ', $recaptchaResult);

        if (!$response->json('success') || $response->json('score') < 0.5) {
            return response()->json(['message' => 'スパム判定されました。'], 403);
        }

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

        $reviewStats = DB::table('reviews')
            ->where('bike_model_id', $id)
            ->selectRaw('ROUND(AVG(rating), 1) as avg_rating, COUNT(*) as count')
            ->first();
        
        $relatedModels = \App\Models\BikeModel::with('manufacturer')
            ->where('manufacturer_id', $model->manufacturer_id)
            ->where('id', '!=', $model->id)
            ->whereNotNull('slug')
            ->withCount(['listings' => fn($q) => $q->active()])
            ->orderByDesc('listings_count')
            ->limit(6)
            ->get();

        // 同排気量帯の人気車種（±50cc、他メーカー含む、自車種と同メーカー除外）
        $similarDisplacementModels = collect();
        if ($model->displacement) {
            $similarDisplacementModels = \App\Models\BikeModel::with('manufacturer')
                ->where('id', '!=', $model->id)
                ->where('manufacturer_id', '!=', $model->manufacturer_id)
                ->whereBetween('displacement', [$model->displacement - 50, $model->displacement + 50])
                ->whereNotNull('slug')
                ->withCount(['listings' => fn($q) => $q->active()])
                ->orderByDesc('listings_count')
                ->limit(6)
                ->get();
        }

        // 同カテゴリの車種（他メーカー含む、自車種と同メーカー除外）
        $sameCategoryModels = collect();
        if ($model->category_id) {
            $excludeIds = $similarDisplacementModels->pluck('id')->push($model->id)->all();
            $sameCategoryModels = \App\Models\BikeModel::with('manufacturer')
                ->where('category_id', $model->category_id)
                ->where('manufacturer_id', '!=', $model->manufacturer_id)
                ->whereNotIn('id', $excludeIds)
                ->whereNotNull('slug')
                ->withCount(['listings' => fn($q) => $q->active()])
                ->orderByDesc('listings_count')
                ->limit(6)
                ->get();
        }

        $activeCount = \App\Models\Listing::where('bike_model_id', $id)->active()->count();

        // オーナー一覧（この車種のMyBike）
        $owners = \App\Models\MyBike::with('user')
            ->where('bike_model_id', $model->id)
            ->latest()
            ->limit(6)
            ->get();

        $similarModels = \App\Models\BikeModel::with('manufacturer')
            ->where('id', '!=', $model->id)
            ->where(function($query) use ($model) {
                if ($model->category_id) {
                    $query->where('category_id', $model->category_id);
                }
            })
            ->whereHas('listings', function($query) {
                $query->where('is_sold_out', 0);
            })
            ->withCount(['listings' => function($query) {
                $query->where('is_sold_out', 0);
            }])
            ->orderByDesc('listings_count')
            ->limit(6)
            ->get();

        // エリア別在庫数（主要都道府県のみ、在庫ありのもの上位表示）
        $prefectureStocks = \App\Models\Listing::where('bike_model_id', $id)
            ->active()
            ->join('shops', 'listings.shop_id', '=', 'shops.id')
            ->whereNotNull('shops.prefecture')
            ->selectRaw('shops.prefecture, COUNT(*) as stock_count')
            ->groupBy('shops.prefecture')
            ->orderByDesc('stock_count')
            ->get();

        $crossLinks = [
            ['label' => $model->name . 'の在庫検索', 'url' => route('bikes.search', ['bike_model_id' => $model->id]), 'icon' => 'search', 'description' => '販売中の車両を探す'],
            ['label' => '駐車場マップ', 'url' => route('parking.index'), 'icon' => 'square-parking', 'description' => 'バイク駐車場を探す'],
            ['label' => 'ショップマップ', 'url' => route('shops.map'), 'icon' => 'store', 'description' => 'バイクショップを探す'],
            ['label' => $model->manufacturer->name . 'の車種一覧', 'url' => route('bikes.models'), 'icon' => 'list', 'description' => '同メーカーの他モデル'],
            ['label' => 'バイク診断', 'url' => route('shindan.index'), 'icon' => 'sparkles', 'description' => 'あなたにピッタリの1台'],
            ['label' => '愛車ガレージ', 'url' => route('mybikes.index'), 'icon' => 'garage', 'description' => '愛車を登録・管理'],
        ];

        return view('bikes.model_detail', compact(
            'model', 'stats', 'history', 'resale', 'listings',
            'reviewStats', 'relatedModels', 'similarDisplacementModels',
            'sameCategoryModels', 'activeCount', 'owners', 'similarModels', 'crossLinks',
            'prefectureStocks'
        ));
    }

   /**
    * 車種詳細ページ（スラッグURL版）
    * URL: /bikes/{mfrSlug}/{modelSlug}
    * 
    * $modelSlug はスラッグ文字列 or 数値ID（日本語名フォールバック）
    */
    public function modelDetailBySlug(string $mfrSlug, string $modelSlug)
    {
        // 1. メーカーをスラッグで検索
        $manufacturer = \App\Models\Manufacturer::where('slug', $mfrSlug)->first();

        if (!$manufacturer) {
            abort(404);
        }

        // 2. 車種を検索（スラッグ or ID）
        if (is_numeric($modelSlug)) {
            // IDフォールバック（日本語車種名の場合）
            $model = \App\Models\BikeModel::where('id', $modelSlug)
                ->where('manufacturer_id', $manufacturer->id)
                ->firstOrFail();
        } else {
            // スラッグで検索
            $model = \App\Models\BikeModel::where('slug', $modelSlug)
                ->where('manufacturer_id', $manufacturer->id)
                ->firstOrFail();
        }

        $priceStatsService = app(\App\Services\Bike\PriceStatsService::class);
        return $this->modelDetail($model->id, $priceStatsService);
    }
}