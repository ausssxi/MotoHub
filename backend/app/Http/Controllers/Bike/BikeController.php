<?php

declare(strict_types=1);

namespace App\Http\Controllers\Bike;

use App\Http\Controllers\Controller;
use App\Http\Requests\Bike\BikeSearchRequest;
use App\Http\Requests\Bike\StoreReviewRequest;
use App\Http\Resources\Bike\ListingResource;
use App\Models\Listing;
use App\Models\SeoFeature;
use App\Services\Bike\BikeNewsService;
use App\Services\Bike\BikePartsService;
use App\Services\Bike\BikeService;
use App\Services\Bike\BikeYouTubeService;
use App\Services\Bike\CityLandingService;
use App\Services\Bike\ListingSearchService;
use App\Services\Bike\PriceStatsService;
use App\Services\Bike\RegionalBargainService;
use App\Services\Bike\RegionalPriceService;
use App\Services\Bike\SeoCompareService;
use App\Services\Bike\SeoLandingService;
use App\Services\NearbyService;
use App\Services\RankingService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * バイク検索・表示機能を提供するメインコントローラー
 * パフォーマンス最適化済み（N+1対策、キャッシュ活用、関心の分離）
 */
final class BikeController extends Controller
{
    /** 車種ページQ&A: 先頭この件数の質問を回答本文込みで初期展開（価格コム式）。 */
    public const QA_EXPANDED_COUNT = 3;

    /** 車種ページQ&A: 一覧に載せる質問の上限（展開分＋折りたたみ分）。超過は「すべて見る」へ。 */
    public const QA_LIST_LIMIT = 5;

    /**
     * 旧ブログ/外部リンクに残る誤った /bikes/catalog/{slug} の救済マップ。
     * bare-slug でも maker接頭辞剥がしでも解決できない著者の誤スラッグを、
     * 正しい "mfrSlug/modelSlug" へ明示マッピングする（catalog()のフォールバックで使用）。
     */
    private const CATALOG_SLUG_ALIASES = [
        'fat-boy-114' => 'harley-davidson/flfbs-softail-fat-boy-114',
        'super-cub-110-lite' => 'honda/super-cub-110',
        'burgman-street-125ex' => 'suzuki/125ex',
    ];

    public function __construct(
        private readonly BikeService $bikeService,
        private readonly ListingSearchService $listingSearchService,
        private readonly SeoLandingService $seoLandingService,
        private readonly CityLandingService $cityLandingService,
        private readonly PriceStatsService $priceStatsService,
        private readonly NearbyService $nearbyService
    ) {}

    /**
     * トップページの表示
     */
    public function index(): View
    {
        $popularBikes = Cache::remember('top_popular_bikes', 1800, fn () => $this->bikeService->getPopularBikesForTopPage()
        );
        $categories = $this->bikeService->getCategoriesForTopPage();
        $manufacturers = $this->bikeService->getMajorManufacturers();
        $regions = config('bike.regions');
        $latestReviews = $this->bikeService->getLatestReviews();
        $licenses = $this->bikeService->getLicenses();
        $popularTags = $this->listingSearchService->getPopularTags();
        $features = $this->bikeService->getFeaturesForTopPage();
        $seoFeatures = SeoFeature::active()->latest()->limit(4)->get();

        // 最近登録された愛車（公開 opt-in 済みのみ）
        $latestMyBikes = \App\Models\MyBike::where('is_public', true)
            ->with(['bikeModel.manufacturer', 'user', 'images'])
            ->latest()
            ->limit(6)
            ->get();

        // ライブ統計バー用のカウント（リポジトリ側でキャッシュ済み）
        $totalListings = $this->listingSearchService->getActiveCount();
        $todayStart = today();
        $todayEnd = today()->addDay();
        // 値下げ件数は通知と同じしきい値で数える（config/price_alerts.php・ベタ書きしない）。
        // 値下げのみ(old>new・従来は方向フィルタ無しで値上げも混入)／変動額>=min_drop_amount／変動率<=max_drop_ratio(桁違い誤読除外)。
        // min_drop_ratio(1%)は通知専用のためここでは使わない。
        $dropMinAmount = (int) config('price_alerts.min_drop_amount', 5000);
        $dropMaxRatio = (float) config('price_alerts.max_drop_ratio', 0.5);
        $priceDropCount = Cache::remember('top_price_drop_count', 1800, fn () => DB::table('price_histories')
            ->where('created_at', '>=', $todayStart)
            ->where('created_at', '<', $todayEnd)
            ->whereColumn('old_price', '>', 'new_price')
            ->whereRaw('(old_price - new_price) >= ?', [$dropMinAmount])
            ->whereRaw('(old_price - new_price) <= old_price * ?', [$dropMaxRatio])
            ->distinct('listing_id')
            ->count('listing_id')
        );
        $newListingsCount = Cache::remember('top_new_listings_count', 1800, fn () => Listing::active()
            ->where('listings.created_at', '>=', $todayStart)
            ->where('listings.created_at', '<', $todayEnd)
            ->count()
        );

        // 前日の販売台数
        $yesterday = today()->subDay();
        $todaySoldCount = Cache::remember('top_yesterday_sold_v3', 3600, fn () => Listing::cappedSold($yesterday, $yesterday)->excludeBulkSold()->count()
        );

        // お買い得車両数
        $bargainsCount = Cache::remember('top_bargains_count', 3600, function () {
            $excludedModelIds = \App\Models\BikeModel::where('name', '他車種')->pluck('id');
            $modelAverages = Listing::where('is_sold_out', false)
                ->whereNotNull('bike_model_id')
                ->whereNotNull('total_price')
                ->where('total_price', '>', 0)
                ->whereNotIn('bike_model_id', $excludedModelIds)
                ->select('bike_model_id', DB::raw('AVG(total_price) as avg_price'), DB::raw('COUNT(*) as cnt'))
                ->groupBy('bike_model_id')
                ->having('cnt', '>=', 3)
                ->pluck('avg_price', 'bike_model_id');

            $count = 0;
            Listing::where('is_sold_out', false)
                ->whereNotNull('bike_model_id')
                ->whereNotNull('total_price')
                ->where('total_price', '>', 0)
                ->whereIn('bike_model_id', $modelAverages->keys())
                ->orderByDesc('created_at')
                ->limit(500)
                ->chunk(100, function ($listings) use ($modelAverages, &$count) {
                    foreach ($listings as $listing) {
                        $avgPrice = (float) $modelAverages[$listing->bike_model_id];
                        if ($avgPrice > 0 && $listing->total_price < ($avgPrice * 0.8)) {
                            $count++;
                        }
                    }
                });

            return $count;
        });

        // 売れ筋ランキングTOP5（今月）— cappedSold で水増し対策
        $rankingTop5 = Cache::remember('top_ranking_top5_v2', 3600, function () {
            $start = now()->startOfMonth();
            $end = now();
            $rows = Listing::cappedSold($start, $end)->excludeBulkSold()
                ->whereNotNull('bike_model_id')
                ->select('bike_model_id', DB::raw('COUNT(*) as sold_count'))
                ->groupBy('bike_model_id')
                ->orderByDesc('sold_count')
                ->limit(5)
                ->get();

            $models = \App\Models\BikeModel::with('manufacturer')
                ->whereIn('id', $rows->pluck('bike_model_id'))
                ->get()->keyBy('id');

            return $rows->map(function ($item) use ($models) {
                $m = $models->get($item->bike_model_id);

                return [
                    'bike_model_id' => $item->bike_model_id,
                    'name' => $m->name ?? '不明',
                    'manufacturer' => $m->manufacturer->name ?? '',
                    'image_url' => $m?->image_url,
                    'sold_count' => $item->sold_count,
                ];
            });
        });

        return view('bikes.index', compact(
            'popularBikes', 'categories', 'manufacturers', 'regions',
            'latestReviews', 'licenses', 'popularTags', 'features', 'seoFeatures',
            'totalListings', 'priceDropCount', 'newListingsCount', 'latestMyBikes',
            'todaySoldCount', 'bargainsCount', 'rankingTop5'
        ));
    }

    /**
     * 都道府県一覧ページの表示
     */
    public function prefectures(): View
    {
        $regions = $this->bikeService->getRegions();
        $totalListings = \App\Models\Listing::where('is_sold_out', false)->count();
        $prefCounts = \Illuminate\Support\Facades\Cache::remember('pref_listing_counts_v2', 3600, function () {
            $raw = \App\Models\Listing::where('listings.is_sold_out', false)
                ->join('shops', 'listings.shop_id', '=', 'shops.id')
                ->selectRaw('shops.prefecture, COUNT(*) as cnt')
                ->whereNotNull('shops.prefecture')
                ->groupBy('shops.prefecture')
                ->pluck('cnt', 'shops.prefecture')
                ->all();
            $mapped = [];
            foreach ($raw as $pref => $cnt) {
                $short = str_replace(['都', '道', '府', '県'], '', $pref);
                if ($short === '北海') {
                    $short = '北海道';
                }
                $mapped[$short] = ($mapped[$short] ?? 0) + $cnt;
            }

            return $mapped;
        });

        return view('bikes.prefectures', compact('regions', 'totalListings', 'prefCounts'));
    }

    /**
     * 都道府県別エリアインデックス（/bikes/area/{prefecture}）
     */
    public function areaIndex(string $prefecture): View
    {
        // v2: 車種(models)リンクを追加したためキー更新（旧キャッシュに models が無く未定義変数になるのを防ぐ）
        $cacheKey = 'area_index_v2_'.md5($prefecture);

        $data = Cache::remember($cacheKey, 3600, function () use ($prefecture) {
            $baseQuery = Listing::query()
                ->join('shops', 'listings.shop_id', '=', 'shops.id')
                ->where('shops.prefecture', 'like', "{$prefecture}%")
                ->where('listings.is_sold_out', false);

            $totalCount = (clone $baseQuery)->count();

            // メーカー別
            $makerRows = (clone $baseQuery)
                ->selectRaw('listings.manufacturer_id, COUNT(*) as cnt')
                ->where('listings.manufacturer_id', '>', 0)
                ->groupBy('listings.manufacturer_id')
                ->orderByDesc('cnt')
                ->limit(20)
                ->get();
            $makerNames = \App\Models\Manufacturer::whereIn('id', $makerRows->pluck('manufacturer_id'))->pluck('name', 'id');
            $makers = $makerRows->map(fn ($r) => [
                'label' => $makerNames->get($r->manufacturer_id),
                'url' => route('bikes.landing', ['prefecture' => $prefecture, 'slug' => $makerNames->get($r->manufacturer_id)]),
                'count' => (int) $r->cnt,
            ])->filter(fn ($m) => $m['label'] !== null)->values();

            // カテゴリ別
            $catRows = (clone $baseQuery)
                ->selectRaw('listings.category_id, COUNT(*) as cnt')
                ->where('listings.category_id', '>', 0)
                ->groupBy('listings.category_id')
                ->orderByDesc('cnt')
                ->limit(15)
                ->get();
            $catNames = \App\Models\Category::whereIn('id', $catRows->pluck('category_id'))->pluck('name', 'id');
            $categories = $catRows->map(fn ($r) => [
                'label' => $catNames->get($r->category_id),
                'url' => route('bikes.landing', ['prefecture' => $prefecture, 'slug' => $catNames->get($r->category_id)]),
                'count' => (int) $r->cnt,
            ])->filter(fn ($c) => $c['label'] !== null)->values();

            // 車種別（人気車種×エリア = CTR最高の bikes.landing へ内部リンク）
            // 在庫5台以上(=車種×県のsitemap/noindex閾値)に限定。同名重複モデルは在庫最多のみ残す。
            $modelRows = (clone $baseQuery)
                ->selectRaw('listings.bike_model_id, COUNT(*) as cnt')
                ->whereNotNull('listings.bike_model_id')
                ->groupBy('listings.bike_model_id')
                ->havingRaw('COUNT(*) >= 5')
                ->orderByDesc('cnt')
                ->limit(40)
                ->get();
            $modelNames = \App\Models\BikeModel::whereIn('id', $modelRows->pluck('bike_model_id'))->pluck('name', 'id');
            $models = $modelRows->map(fn ($r) => [
                'label' => $modelNames->get($r->bike_model_id),
                'url' => route('bikes.landing', ['prefecture' => $prefecture, 'slug' => $modelNames->get($r->bike_model_id)]),
                'count' => (int) $r->cnt,
            ])->filter(fn ($m) => $m['label'] !== null)
                ->unique('label') // 同名重複モデルは在庫最多(先頭)のみ
                ->take(24)
                ->values();

            // 排気量別
            $displacements = collect([
                ['label' => '原付(〜50cc)', 'slug' => '原付', 'min' => 0, 'max' => 50],
                ['label' => '小型(〜125cc)', 'slug' => '小型', 'min' => 51, 'max' => 125],
                ['label' => '軽二輪(〜250cc)', 'slug' => '軽二輪', 'min' => 126, 'max' => 250],
                ['label' => '中型(〜400cc)', 'slug' => '中型', 'min' => 251, 'max' => 400],
                ['label' => '大型(401cc〜)', 'slug' => '大型', 'min' => 401, 'max' => 99999],
            ])->map(function ($d) use ($baseQuery, $prefecture) {
                $cnt = (clone $baseQuery)
                    ->whereBetween('listings.displacement', [$d['min'], $d['max']])
                    ->count();

                return [
                    'label' => $d['label'],
                    'url' => route('bikes.landing', ['prefecture' => $prefecture, 'slug' => $d['slug']]),
                    'count' => $cnt,
                ];
            });

            return compact('totalCount', 'makers', 'categories', 'displacements', 'models');
        });

        return view('bikes.area-index', array_merge($data, ['prefecture' => $prefecture]));
    }

    /**
     * 検索結果ページの表示
     */
    public function search(BikeSearchRequest $request): View|JsonResponse
    {
        // フォームリクエストから安全なフィルター条件のみを取得
        $filters = $request->toFilters();

        $keyword = $this->sanitizeKeyword($request->query('keyword'));
        $prefecture = $request->query('prefecture');
        $sort = (string) $request->query('sort', 'bargain_desc');

        try {
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
                    'next_url' => $result['pagination']['next_url'],
                ]);
            }

            $pageTitle = $this->listingSearchService->generatePageTitle($keyword, $prefecture, $filters);
            $popularTags = $this->listingSearchService->getPopularTags();
            $recommendedModels = $this->listingSearchService->getRecommendedModels($result['filters'], $result['items']);
            // キーワード一致の車種ページ導線チップ（キャッシュ済み・キーワード無しなら空）
            $modelChips = $this->listingSearchService->getModelChips($keyword);
            // 単一車種(bike_model_id)絞り込み時の車種ページ導線CTA（キーワード時/未指定はnull）
            $modelCta = $this->listingSearchService->getModelCta($keyword, $result['filters']);

            return view('bikes.search', array_merge($result, [
                'keyword' => $keyword,
                'prefecture' => $prefecture,
                'sort' => $sort,
                'pageTitle' => $pageTitle,
                'popularTags' => $popularTags,
                'recommendedModels' => $recommendedModels,
                'modelChips' => $modelChips,
                'modelCta' => $modelCta,
            ]));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Search failed', [
                'keyword' => $keyword,
                'filters' => $filters,
                'error' => $e->getMessage(),
            ]);

            // AJAX系リクエストにはJSON返却（Viewを返すとフロントでパースエラー）
            if ($request->has('count_only')) {
                return response()->json(['total' => 0]);
            }
            if ($request->query('load_more')) {
                return response()->json(['html' => '', 'next_url' => null]);
            }

            return view('bikes.search', [
                'items' => [],
                'pagination' => ['total' => 0, 'last_page' => 1, 'prev_url' => null, 'next_url' => null, 'pages' => []],
                'stats' => [],
                'meta' => [],
                'facets' => [],
                'relaxSuggestions' => [],
                'manufacturers' => collect(),
                'models' => collect(),
                'categories' => collect(),
                'regions' => config('bike.regions', []),
                'prefectures' => collect(config('bike.regions', []))->flatten()->toArray(),
                'filters' => $filters,
                'sortOptions' => [],
                'keyword' => $keyword,
                'prefecture' => $prefecture,
                'sort' => $sort,
                'pageTitle' => '検索結果',
                'popularTags' => $this->listingSearchService->getPopularTags(),
                'recommendedModels' => collect(),
            ]);
        }
    }

    /**
     * 検索キーワードからMeilisearchで問題になる特殊文字を除去
     */
    private function sanitizeKeyword(?string $keyword): ?string
    {
        if ($keyword === null || $keyword === '') {
            return $keyword;
        }

        // スラッシュ・引用符をスペースに変換
        $keyword = str_replace(['/', '"', "'"], ' ', $keyword);

        // 連続スペースを1つに
        $keyword = preg_replace('/\s+/', ' ', trim($keyword));

        return $keyword !== '' ? $keyword : null;
    }

    /**
     * 車両詳細ページを表示
     */
    public function show(int $id): View|Response
    {
        $listing = $this->bikeService->getListingDetail($id);

        // レコード不在 → 404
        if (! $listing) {
            return response()->view('errors.404', [
                'message' => 'この車両は掲載終了しました',
                'searchUrl' => route('bikes.index'),
                'bikeName' => null,
            ], 404);
        }

        $isSoldOut = (bool) $listing->is_sold_out;

        // 閲覧数のインクリメントはページ表示の本質ではない副作用なので、下の try からは切り離す。
        // 以前はこの下の try の中にあり、深夜バッチとの行ロック競合で 1205（Lock wait timeout）が
        // 出ると catch が errors.404 を返していた。2026-08-04〜08-11 の11件は、いずれも
        // ユーザーが約50秒（innodb_lock_wait_timeout の既定）待たされた末に404を見ている。
        // カウントは落ちてもよいが、ページは必ず出す。
        if (! $isSoldOut) {
            try {
                $this->bikeService->incrementViewCount($id);
            } catch (\Throwable $e) {
                // 「表示に失敗した」と読み違えられないよう、カウンタ更新の失敗だと分かる文言にする
                // （旧 'Listing show failed' が表示障害と誤解される原因だった）。
                \Illuminate\Support\Facades\Log::warning('Listing view counter increment failed (表示は継続)', [
                    'id' => $id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        try {
            $relatedRaw = $listing->bike_model_id
                ? $this->bikeService->getRelatedListings($listing->bike_model_id, $listing->id, 8)
                : collect();

            $similarRaw = $this->bikeService->getSimilarListings($listing->manufacturer_id, $listing->bike_model_id, 8);

            $currentPrice = is_numeric($listing->total_price) ? (float) $listing->total_price : 0;
            $stats = $this->priceStatsService->getModelStats((int) $listing->bike_model_id, $currentPrice);

            // パーセンタイル算出（distribution から計算、DB追加クエリなし）
            $pricePercentile = null;
            if ($currentPrice > 0 && ! empty($stats['distribution']) && ($stats['count'] ?? 0) > 1) {
                $cheaperCount = 0;
                foreach ($stats['distribution'] as $bucket) {
                    if ($bucket['range_max'] <= $currentPrice) {
                        $cheaperCount += $bucket['count'];
                    } elseif ($bucket['range_min'] < $currentPrice) {
                        $range = $bucket['range_max'] - $bucket['range_min'];
                        if ($range > 0) {
                            $cheaperCount += (int) round($bucket['count'] * ($currentPrice - $bucket['range_min']) / $range);
                        }
                    }
                }
                $pricePercentile = (int) round($cheaperCount / $stats['count'] * 100);
            }

            // 値下げ額の計算ロジック（コントローラー側で処理してビューへ渡す）
            $priceDropDiff = null;
            if ($listing->relationLoaded('priceHistories') && $listing->priceHistories->isNotEmpty()) {
                $latestDrop = $listing->priceHistories->first();
                if ($latestDrop->old_price > $latestDrop->new_price) {
                    // 万円単位に変換（例: 30000 → 3.0）
                    $priceDropDiff = number_format(($latestDrop->old_price - $latestDrop->new_price) / 10000, 1);
                }
            }

            // お買い得割引率（全国中央値ベース・robust20・上限50%）。deal用途は20%以上のみ。
            // 旧 avg ベース（外れ値で94%が出た）を撤去。eager-load 済みの全国statを再利用（追加クエリなし）。
            $discountRate = null;
            $detailBargain = RegionalBargainService::fromStat(
                $listing->bikeModel?->nationalPriceStat,
                $currentPrice > 0 ? (int) $currentPrice : null
            );
            if ($detailBargain !== null && $detailBargain['pct'] >= 20) {
                $discountRate = $detailBargain['pct'];
            }

            $reviews = $listing->bike_model_id
                ? $this->bikeService->getReviewsByModelId((int) $listing->bike_model_id, 3)
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
                $alsoViewed = Cache::remember("also_viewed_{$listing->id}", 3600, function () use ($listing) {
                    return \App\Models\Listing::with('shop')
                        ->where('is_sold_out', 0)
                        ->where('id', '!=', $listing->id)
                        ->where(function ($query) use ($listing) {
                            $query->where('category_id', $listing->category_id)
                                ->orWhereBetween('total_price', [
                                    $listing->total_price * 0.8,
                                    $listing->total_price * 1.2,
                                ]);
                        })
                        ->whereNotNull('total_price')
                        ->where('total_price', '>', 0)
                        ->inRandomOrder()
                        ->limit(6)
                        ->get();
                });
            }

            $crossLinks = [
                ['label' => '中古バイク検索', 'url' => route('bikes.search'), 'icon' => 'search', 'description' => '全国の在庫を検索'],
                ['label' => '車種カタログ', 'url' => route('bikes.models'), 'icon' => 'book-open', 'description' => '車種の相場を確認'],
                ['label' => '駐車場マップ', 'url' => route('parking.index'), 'icon' => 'square-parking', 'description' => 'バイク駐車場を探す'],
                ['label' => 'バイク診断', 'url' => route('shindan.index'), 'icon' => 'sparkles', 'description' => 'あなたにピッタリの1台'],
                ['label' => '愛車ガレージ', 'url' => route('mybikes.index'), 'icon' => 'car', 'description' => '愛車を登録・管理'],
                ['label' => 'バイク盗難データ', 'url' => route('theft'), 'icon' => 'shield-alert', 'description' => '全国の盗難件数と対策'],
            ];

            $relatedParts = $listing->bikeModel
                ? app(BikePartsService::class)->fetchFlat($listing->bikeModel)
                : [];

            $makerName = $listing->bikeModel?->manufacturer?->name ?? '';
            $modelName = $listing->bikeModel?->name ?? $listing->title ?? '';

            $news = [];
            if ($listing->bike_model_id) {
                $news = \App\Models\BikeNews::where('bike_model_id', $listing->bike_model_id)
                    ->latest()
                    ->limit(3)
                    ->get()
                    ->toArray();
            }
            if (empty($news) && $listing->manufacturer_id) {
                $news = \App\Models\BikeNews::where('manufacturer_id', $listing->manufacturer_id)
                    ->latest()
                    ->limit(3)
                    ->get()
                    ->toArray();
            }

            try {
                $videos = (new BikeYouTubeService)->getForModel((int) $listing->bike_model_id);
            } catch (\Throwable) {
                $videos = [];
            }

            // 施策B-H: コンテンツ差別化テキスト
            $bikeHighlight = $this->getBikeHighlight($data);
            $priceAnalysisText = $this->getPriceAnalysisText($data, $stats, $pricePercentile);
            $modelComment = $this->getModelComment($data);
            $regionComment = $this->getRegionComment($data);
            $priceBandComment = $this->getPriceBandComment($data);

            // 多角的比較指標（市場ポジション分析）
            $marketPosition = $this->getMarketPosition($listing);

            // 施策E: ブログ記事連携
            $relatedBlogPosts = collect();
            try {
                $blogModelName = $listing->bikeModel?->name ?? '';
                if ($blogModelName) {
                    $relatedBlogPosts = \App\Models\BlogPost::published()
                        ->where(function ($q) use ($blogModelName, $data) {
                            $q->where('title', 'like', "%{$blogModelName}%")
                                ->orWhere('title', 'like', '%'.($data->category ?? '____').'%');
                        })
                        ->orderByDesc('published_at')
                        ->limit(3)
                        ->get();
                }
            } catch (\Throwable) {
                $relatedBlogPosts = collect();
            }

            // 車種販売データ（ランキング連携）
            $rankingStats = null;
            if ($listing->bike_model_id) {
                $rankingStats = Cache::remember("show_ranking_stats_{$listing->bike_model_id}", 604800, function () use ($listing) {
                    $lms = now()->subMonth()->startOfMonth();
                    $lme = now()->subMonth()->endOfMonth();
                    $three = now()->subMonths(3);

                    $sold = Listing::where('bike_model_id', $listing->bike_model_id)
                        ->where('is_sold_out', true)
                        ->whereBetween('updated_at', [$lms, $lme])
                        ->count();

                    if ($sold === 0) {
                        return null;
                    }

                    $allSales = Listing::where('is_sold_out', true)
                        ->whereBetween('updated_at', [$lms, $lme])
                        ->whereNotNull('bike_model_id')
                        ->select('bike_model_id', DB::raw('COUNT(*) as cnt'))
                        ->groupBy('bike_model_id')
                        ->orderByDesc('cnt')
                        ->get();
                    $rank = $allSales->search(fn ($r) => $r->bike_model_id == $listing->bike_model_id);
                    $rank = $rank !== false ? $rank + 1 : null;

                    $avgDays = Listing::where('bike_model_id', $listing->bike_model_id)
                        ->where('is_sold_out', true)
                        ->whereBetween('updated_at', [$lms, $lme])
                        ->selectRaw('AVG(DATEDIFF(updated_at, created_at)) as avg_days')
                        ->value('avg_days');

                    $topPrice = Listing::where('bike_model_id', $listing->bike_model_id)
                        ->where('is_sold_out', true)->where('updated_at', '>=', $three)
                        ->whereNotNull('total_price')
                        ->select(DB::raw("CASE WHEN total_price<200000 THEN '〜20万円' WHEN total_price<300000 THEN '20〜30万円' WHEN total_price<400000 THEN '30〜40万円' WHEN total_price<500000 THEN '40〜50万円' WHEN total_price<700000 THEN '50〜70万円' WHEN total_price<1000000 THEN '70〜100万円' ELSE '100万円〜' END as price_range"), DB::raw('COUNT(*) as cnt'))
                        ->groupBy('price_range')->orderByDesc('cnt')->first();

                    $topRegion = Listing::where('listings.bike_model_id', $listing->bike_model_id)
                        ->where('listings.is_sold_out', true)->where('listings.updated_at', '>=', $three)
                        ->join('shops', 'listings.shop_id', '=', 'shops.id')
                        ->whereNotNull('shops.prefecture')
                        ->select('shops.prefecture', DB::raw('COUNT(*) as cnt'))
                        ->groupBy('shops.prefecture')->orderByDesc('cnt')->first();

                    return [
                        'sold' => $sold,
                        'rank' => $rank,
                        'totalModels' => $allSales->count(),
                        'dailyAvg' => round($sold / 30, 1),
                        'avgDays' => (int) round((float) ($avgDays ?? 0)),
                        'topPrice' => $topPrice?->price_range,
                        'topRegion' => $topRegion?->prefecture,
                    ];
                });
            }

            // 売り切れ車両用データ
            $soldOutData = null;
            $activeSameModel = collect();
            if ($isSoldOut) {
                // 販売記録
                $listingDays = $listing->created_at && $listing->updated_at
                    ? max(0, $listing->created_at->diffInDays($listing->updated_at))
                    : null;
                $soldPrice = $listing->total_price
                    ? number_format((float) ($listing->total_price / 10000), 1)
                    : null;

                // 同車種の販売中車両（最大6台）
                if ($listing->bike_model_id) {
                    $activeSameModel = Listing::with('shop:id,name,prefecture')
                        ->where('bike_model_id', $listing->bike_model_id)
                        ->where('is_sold_out', false)
                        ->orderBy('total_price')
                        ->limit(6)
                        ->get();
                }

                // 車種の市場データ（販売中車両ベース）
                $marketAvgPrice = null;
                $marketActiveCount = 0;
                if ($listing->bike_model_id) {
                    $marketData = Listing::where('bike_model_id', $listing->bike_model_id)
                        ->where('is_sold_out', false)
                        ->whereNotNull('total_price')
                        ->where('total_price', '>', 0)
                        ->selectRaw('AVG(total_price) as avg_price, COUNT(*) as cnt')
                        ->first();
                    $marketAvgPrice = $marketData->avg_price ? number_format((float) ($marketData->avg_price / 10000), 1) : null;
                    $marketActiveCount = (int) $marketData->cnt;
                }

                $soldOutData = [
                    'listing_days' => $listingDays,
                    'sold_price' => $soldPrice,
                    'created_at' => $listing->created_at?->format('Y年m月d日'),
                    'updated_at' => $listing->updated_at?->format('Y年m月d日'),
                    'market_avg_price' => $marketAvgPrice,
                    'market_active_count' => $marketActiveCount,
                    'ranking_rank' => $rankingStats['rank'] ?? null,
                    'ranking_total' => $rankingStats['totalModels'] ?? null,
                    'avg_sell_days' => $rankingStats['avgDays'] ?? null,
                ];
            }

            // 項目別レビュー統計（車種モデルページと同じロジック）
            $reviewDetailedStats = ['total' => 0, 'design' => ['avg' => null], 'engine' => ['avg' => null], 'handling' => ['avg' => null], 'fuel_economy' => ['avg' => null], 'cost_performance' => ['avg' => null]];
            if ($listing->bike_model_id) {
                $ratingFields = ['rating_design', 'rating_engine', 'rating_handling', 'rating_fuel_economy', 'rating_cost_performance'];
                $fieldKeys = ['design', 'engine', 'handling', 'fuel_economy', 'cost_performance'];
                $modelAvgs = DB::table('reviews')
                    ->where('bike_model_id', $listing->bike_model_id)
                    ->selectRaw('COUNT(*) as total, '.implode(', ', array_map(fn ($f) => "ROUND(AVG($f), 1) as avg_$f", $ratingFields)))
                    ->first();
                $reviewDetailedStats['total'] = (int) ($modelAvgs->total ?? 0);
                foreach ($ratingFields as $i => $field) {
                    $avg = $modelAvgs->{"avg_$field"} ?? null;
                    $reviewDetailedStats[$fieldKeys[$i]] = ['avg' => $avg ? (float) $avg : null];
                }
            }

            // ローン「月々の目安」はサーバー描画（薄コンテンツ対策＋UX）。生・円モデルで価格解決するため
            // Resource変換前の $listing を渡す（総額 ?? 本体・min_price判定はヘルパーに集約）。ライブ/売り切れ共通。
            $loanEstimate = \App\Support\LoanEstimate::forListing($listing);

            // MotoHub掲載日数（この車両ベース）。created_at=MotoHubが最初に確認した日（販売店の掲載日ではない）。
            // diffInDays は環境により float を返すため int にキャスト（表示が 62.0 にならないように）。初取得日=1日目。
            $daysOnSite = $listing->created_at
                ? (int) $listing->created_at->startOfDay()->diffInDays(now()->startOfDay()) + 1
                : null;

            // 値下げ実績（この車両ベース・1クエリ）。しきい値は config/price_alerts.php（ベタ書きしない）。
            // 値下げのみ(old>new)／変動額 >= min_drop_amount／変動率 <= max_drop_ratio（桁違い誤読を除外）。
            $dropMinAmount = (int) config('price_alerts.min_drop_amount', 5000);
            $dropMaxRatio = (float) config('price_alerts.max_drop_ratio', 0.5);
            $dropAgg = DB::table('price_histories')
                ->where('listing_id', $listing->id)
                ->whereColumn('old_price', '>', 'new_price')
                ->whereRaw('(old_price - new_price) >= ?', [$dropMinAmount])
                ->whereRaw('(old_price - new_price) <= old_price * ?', [$dropMaxRatio])
                ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(old_price - new_price), 0) as total')
                ->first();
            $priceDropSummary = [
                'count' => (int) ($dropAgg->cnt ?? 0),
                'total' => (int) ($dropAgg->total ?? 0),
            ];

            return view('bikes.show', [
                'listing' => $data,
                'loanEstimate' => $loanEstimate,
                'bikeModelForUrl' => $listing->bikeModel,
                'relatedListings' => ListingResource::collection($relatedRaw)->resolve(),
                'similarListings' => ListingResource::collection($similarRaw)->resolve(),
                'dynamicLinks' => $dynamicLinks,
                'seoLinks' => $seoLinks,
                'stats' => $stats,
                'histogram' => $stats['distribution'] ?? [],
                'tags' => $listing->tags,
                'reviews' => $reviews,
                'priceDropDiff' => $priceDropDiff,
                'discountRate' => $discountRate,
                'pricePercentile' => $pricePercentile,
                'nearbyParkings' => $nearbyParkings,
                'nearbyShops' => $nearbyShops,
                'crossLinks' => $crossLinks,
                'alsoViewed' => $alsoViewed,
                'shopLat' => $shopLat,
                'shopLng' => $shopLng,
                'relatedParts' => $relatedParts,
                'news' => $news,
                'videos' => $videos,
                'bikeHighlight' => $bikeHighlight,
                'priceAnalysisText' => $priceAnalysisText,
                'modelComment' => $modelComment,
                'regionComment' => $regionComment,
                'priceBandComment' => $priceBandComment,
                'relatedBlogPosts' => $relatedBlogPosts,
                'marketPosition' => $marketPosition,
                'regionBargain' => $detailBargain, // 全国中央値ベースのお得（og/ヒーロー用・null=非表示）
                'rankingStats' => $rankingStats,
                'soldOutData' => $soldOutData,
                'activeSameModel' => $activeSameModel,
                'reviewDetailedStats' => $reviewDetailedStats,
                'daysOnSite' => $daysOnSite,
                'priceDropSummary' => $priceDropSummary,
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Listing show failed', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->view('errors.404', [
                'message' => 'この車両情報の表示中にエラーが発生しました',
                'searchUrl' => route('bikes.index'),
                'bikeName' => null,
            ], 404);
        }
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
        $listingsRaw = tap($this->listingSearchService->search($keyword, null, 'latest', [], 3), function ($res) {
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
            }),
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
        $groups = array_filter($groups, fn ($list) => count($list) > 0);

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
     * 車種比較ハブ（200ペアの索引ページ）
     * URL: /bikes/compare
     */
    public function modelCompareHub(SeoCompareService $compareService): View
    {
        return view('bikes.compare_hub', [
            'groups' => $compareService->getHubGroups(),
        ]);
    }

    /**
     * 車種比較ページ（SEOプログラマティック）
     * URL: /bikes/compare/{slug}
     */
    public function modelCompare(string $slug, SeoCompareService $compareService): View|RedirectResponse
    {
        // 並び順非依存で active な比較ペアを解決。生成対象外 → 404（薄いページを作らない）
        $seoCompare = $compareService->findActiveBySlugAnyOrder($slug);
        if (! $seoCompare) {
            abort(404);
        }

        // 逆順/非正規アクセスは canonical（小さいid左）へ 301（重複インデックス防止）
        if ($slug !== $seoCompare->slug) {
            return redirect()->route('bikes.model_compare', $seoCompare->slug, 301);
        }

        $model1 = $seoCompare->model1;
        $model2 = $seoCompare->model2;

        $kpi = $compareService->computeCompareKpi($model1, $model2);
        $relatedComparisons = $compareService->getRelatedComparisons($model1, $model2);

        return view('bikes.compare', [
            'model1' => $model1,
            'model2' => $model2,
            'kpi' => $kpi,
            'relatedComparisons' => $relatedComparisons,
            'inventory1' => $compareService->getInventoryCards($model1),
            'inventory2' => $compareService->getInventoryCards($model2),
            'faq' => $compareService->buildFaq($model1, $model2, $kpi),
        ]);
    }

    /**
     * レビュー一覧ハブ（車種軸・最新フィード＋メーカー/排気量ブラウズ）。URL: /bikes/reviews
     * 個別 Review/Rating の JSON-LD は付けない（自演レビューを増幅しない）。
     */
    public function reviewsIndex(\Illuminate\Http\Request $request, \App\Repositories\Bike\ReviewRepository $reviewRepo): View
    {
        $maker = $request->integer('maker') ?: null;
        $ccSlug = (string) $request->query('cc', '');

        $ccBands = (array) config('comparison.cc_bands', []);
        $filters = ['maker' => $maker];
        $selectedCc = null;
        foreach ($ccBands as $band) {
            if ($ccSlug !== '' && ($band['slug'] ?? null) === $ccSlug) {
                $filters['cc_min'] = $band['min'];
                $filters['cc_max'] = $band['max'];
                $selectedCc = $ccSlug;
                break;
            }
        }

        return view('bikes.reviews_index', [
            'reviews' => $reviewRepo->getFeed($filters, 20)->withQueryString(),
            'makers' => $reviewRepo->reviewMakerCounts(),
            'ccBands' => $ccBands,
            'selectedMaker' => $maker,
            'selectedCc' => $selectedCc,
        ]);
    }

    /**
     * 地域差・独立ページ（SEO）。URL: /bikes/region-price/{slug}
     * ゲート: spread.robust_block_count≥3 かつ pct≥20。未達は abort(404)（薄ページ防止）。
     */
    public function regionPrice(string $slug, RegionalPriceService $regionalPriceService): View|RedirectResponse
    {
        $model = \App\Models\BikeModel::where('slug', $slug)->with('manufacturer')->orderBy('id')->firstOrFail();

        if ($model->merged_into_id) {
            return redirect($model->canonicalModel()->seo_url, 301);
        }

        $region = $regionalPriceService->getForModel($model);
        $spread = $region['spread'] ?? null;
        if ($spread === null || $spread['robust_block_count'] < 3 || $spread['pct'] < 20) {
            abort(404);
        }

        return view('bikes.region_price', [
            'model' => $model,
            'region' => $region,
        ]);
    }

    /**
     * 地域差・独立ページの索引。URL: /bikes/region-price
     * ゲート該当モデルを spread%降順で列挙（最小インデックス）。
     */
    public function regionPriceIndex(RegionalPriceService $regionalPriceService): View
    {
        return view('bikes.region_price_index', [
            'models' => $regionalPriceService->gatedRegionPriceModels(3, 20),
        ]);
    }

    /**
     * カタログページ（地域なしプログラマティックSEO）
     * URL: /bikes/catalog/{slug}
     */
    public function catalog(string $slug): View|RedirectResponse
    {
        $pageInfo = $this->seoLandingService->resolveCatalogPage($slug);
        if (empty($pageInfo)) {
            // フォールバック: 車種slugなら正規の車種ページへ301（旧ブログ/外部リンク救済）。
            // resolveCatalogPageが先に走るので、正規カタログslug（{cc}cc/maker/maker-category）は侵食しない。
            if ($model = $this->resolveModelBySlug($slug)) {
                // 統合済みなら canonical へ直接301（二重ホップ回避）
                return redirect($model->canonicalModel()->seo_url, 301);
            }

            return redirect('/bikes/search', 301);
        }

        $result = $this->listingSearchService->search(null, null, 'latest', $pageInfo['filters']);

        return view('bikes.catalog', array_merge($result, [
            'pageInfo' => $pageInfo['meta'],
            'keyword' => '',
            'sort' => 'latest',
        ]));
    }

    /**
     * カタログslugを車種に解決する（旧 /bikes/catalog/{slug} リンク救済用）。
     * 1) エイリアスマップ（著者の誤スラッグ → 正しい mfr/slug）
     * 2) bare slug（同名slugが複数なら最多active在庫を採用＝promotion）
     * 3) maker接頭辞付き（例 bmw-g310r → bmw/g310r。[bikes]ショートコード同型の mfr/slug 解決）
     */
    private function resolveModelBySlug(string $slug): ?\App\Models\BikeModel
    {
        $slug = trim($slug);
        if ($slug === '') {
            return null;
        }

        // 1) 著者の誤スラッグ → 正しい mfr/slug へのエイリアス
        if (isset(self::CATALOG_SLUG_ALIASES[$slug])
            && ($aliased = $this->resolveModelByMfrSlug(self::CATALOG_SLUG_ALIASES[$slug]))) {
            return $aliased;
        }

        // 2) bare slug（複数候補は最多active在庫を採用）
        $model = \App\Models\BikeModel::with('manufacturer')
            ->where('slug', $slug)
            ->withCount(['listings' => fn ($q) => $q->active()])
            ->orderByDesc('listings_count')
            ->first();
        if ($model && $model->manufacturer?->slug) {
            return $model;
        }

        // 3) maker接頭辞付き（bmw-g310r → bmw/g310r）
        if (str_contains($slug, '-')) {
            [$mfrSlug, $rest] = explode('-', $slug, 2);

            return $this->resolveModelByMfrSlug("{$mfrSlug}/{$rest}");
        }

        return null;
    }

    /**
     * "mfrSlug/modelSlug" を車種に解決する（/bikes/{mfr}/{slug} ルートと同一ロジック）。
     */
    private function resolveModelByMfrSlug(string $token): ?\App\Models\BikeModel
    {
        if (! str_contains($token, '/')) {
            return null;
        }
        [$mfrSlug, $modelSlug] = explode('/', $token, 2);

        $manufacturer = \App\Models\Manufacturer::where('slug', trim($mfrSlug))->first();
        if (! $manufacturer) {
            return null;
        }

        return \App\Models\BikeModel::with('manufacturer')
            ->where('manufacturer_id', $manufacturer->id)
            ->where('slug', trim($modelSlug))
            ->first();
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
            50 => ['min' => 0,    'max' => 50,   'label' => '50cc以下（原付一種）'],
            125 => ['min' => 51,   'max' => 125,  'label' => '51〜125cc（原付二種）'],
            250 => ['min' => 126,  'max' => 250,  'label' => '126〜250cc（軽二輪）'],
            400 => ['min' => 251,  'max' => 400,  'label' => '251〜400cc（普通二輪）'],
            750 => ['min' => 401,  'max' => 750,  'label' => '401〜750cc'],
            1000 => ['min' => 751,  'max' => null,  'label' => '751cc以上（大型）'],
        ];

        if (! isset($ranges[$displacement])) {
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

    public function landing(string $prefecture, string $slug): View|RedirectResponse
    {
        $pageInfo = $this->seoLandingService->resolvePageInfo($prefecture, $slug);
        if (empty($pageInfo)) {
            return redirect("/bikes/area/{$prefecture}", 301);
        }

        try {
            $result = $this->listingSearchService->search(null, $prefecture, 'latest', $pageInfo['filters']);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Landing search failed: {$prefecture}/{$slug}", ['error' => $e->getMessage()]);

            return redirect("/bikes/area/{$prefecture}", 301);
        }

        $landingKpi = $this->seoLandingService->computeLandingKpi($prefecture, $pageInfo['filters'], $pageInfo['meta']['type']);
        $relatedLinks = $this->seoLandingService->computeRelatedLinks(
            $prefecture,
            $pageInfo['filters'],
            $pageInfo['meta']['type'],
            $pageInfo['meta']['target_name'],
            $slug,
        );

        // 地域相場の一文＋region-priceリンク（モデルLPのみ・getForModel 1呼び出し＝N+1なし）
        $regionalNote = null;
        if (($pageInfo['meta']['type'] ?? null) === 'model' && ! empty($pageInfo['filters']['bike_model_id'])) {
            $noteModel = \App\Models\BikeModel::find($pageInfo['filters']['bike_model_id']);
            if ($noteModel) {
                $regionalNote = app(RegionalPriceService::class)->landingNote($noteModel, $prefecture);
            }
        }

        return view('bikes.landing', array_merge($result, [
            'pageInfo' => $pageInfo['meta'],
            'keyword' => '',
            'prefecture' => $prefecture,
            'sort' => 'latest',
            'landingKpi' => $landingKpi,
            'relatedLinks' => $relatedLinks,
            'regionalNote' => $regionalNote,
        ]));
    }

    /**
     * 市区町村レベルSEOランディングページ
     */
    public function cityLanding(string $prefecture, string $city, string $slug): View|RedirectResponse
    {
        $pageInfo = $this->cityLandingService->resolveCityPageInfo($prefecture, $city, $slug);
        if (empty($pageInfo)) {
            // slugが実は都道府県レベルの有効なslugかチェック
            $fallback = $this->seoLandingService->resolvePageInfo($prefecture, $city);
            if (! empty($fallback)) {
                return redirect("/bikes/area/{$prefecture}/{$city}", 301);
            }

            return redirect("/bikes/area/{$prefecture}", 301);
        }

        // 都道府県の短縮形→正式名の変換
        $fullPref = match ($prefecture) {
            '北海道' => '北海道',
            '東京' => '東京都',
            '大阪' => '大阪府',
            '京都' => '京都府',
            default => $prefecture.'県',
        };

        $sort = request('sort', 'latest');
        $paginated = $this->cityLandingService->getCityListings($fullPref, $city, $pageInfo['filters'], $sort);

        // Eloquentモデルをbike_cardパーシャル用にListingResourceで変換（万円表示等）
        $transformedItems = ListingResource::collection($paginated->getCollection())->resolve();
        $listings = $paginated->setCollection(collect($transformedItems));

        $landingKpi = $this->cityLandingService->computeCityKpi(
            $fullPref,
            $city,
            $pageInfo['filters'],
            $pageInfo['meta']['type'],
        );

        $relatedLinks = $this->cityLandingService->computeCityRelatedLinks(
            $fullPref,
            $city,
            $pageInfo['filters'],
            $pageInfo['meta']['type'],
            $slug,
            $prefecture,
        );

        return view('bikes.city-landing', [
            'pageInfo' => $pageInfo['meta'],
            'prefecture' => $prefecture,
            'city' => $city,
            'slug' => $slug,
            'sort' => $sort,
            'listings' => $listings,
            'landingKpi' => $landingKpi,
            'relatedLinks' => $relatedLinks,
        ]);
    }

    public function storeReview(StoreReviewRequest $request, int $id)
    {
        $user = $request->user();

        // === reCAPTCHA はゲストのみ必須（ログイン済みは accountability があるため任意・throttle は維持）===
        if (! $user) {
            $response = \Illuminate\Support\Facades\Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
                'secret' => config('services.recaptcha.secret_key'),
                'response' => $request->recaptcha_token,
            ]);

            if (! $response->json('success') || $response->json('score') < 0.5) {
                return response()->json(['message' => 'スパム判定されました。'], 403);
            }
        }

        $validated = $request->validated();
        // 生IPは保存せず sha256(ip|app.key) のみ（連投・濫用の単位／将来の事後対応用）。
        $validated['submitter_ip_hash'] = hash('sha256', $request->ip().'|'.config('app.key'));
        $model = $this->bikeService->getBikeModelDetail($id);

        // === ログインユーザー帰属（公開ハンドルのみ使用・User->name は絶対に使わない）===
        $userId = null;
        if ($user) {
            $userId = $user->id;
            $handle = $user->review_display_name;

            if (empty($handle)) {
                // 初回: 公開ハンドルを検証して保存（以降固定・タグ除去）
                $handle = trim(strip_tags((string) $request->input('review_handle')));
                if ($handle === '' || mb_strlen($handle) > 30) {
                    $msg = '公開表示名を1〜30文字で入力してください。';
                    if ($request->wantsJson()) {
                        return response()->json(['message' => $msg, 'errors' => ['review_handle' => [$msg]]], 422);
                    }

                    return back()->withErrors(['review_handle' => $msg])->withInput();
                }
                $user->review_display_name = $handle;
                $user->save();
            }

            // 表示名は公開ハンドルのスナップショット（本名 name は入れない）
            $validated['nickname'] = $handle;
        }

        $this->bikeService->createReview($model->id, $validated, $userId);
        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'レビューを投稿しました！',
                'review' => [
                    'title' => $validated['title'] ?? '無題',
                    'body' => $validated['body'] ?? '',
                    'rating' => $validated['rating'] ?? 5,
                    'nickname' => $validated['nickname'] ?? '名無しライダー',
                    'is_user' => $userId !== null,
                    'created_at' => now()->format('Y年m月'),
                    'rating_design' => $validated['rating_design'] ?? null,
                    'rating_engine' => $validated['rating_engine'] ?? null,
                    'rating_handling' => $validated['rating_handling'] ?? null,
                    'rating_fuel_economy' => $validated['rating_fuel_economy'] ?? null,
                    'rating_cost_performance' => $validated['rating_cost_performance'] ?? null,
                ],
            ]);
        }

        // review_posted: 再読込後に自分の投稿へスクロール＋一時ハイライトする合図（即反映の実感）。
        return redirect()->route('bikes.model_detail', [
            'mfrSlug' => $model->manufacturer->slug ?? $model->manufacturer_id,
            'modelSlug' => $model->slug ?? $model->id,
        ])->with('success', 'レビューを投稿しました！')->with('review_posted', 1);
    }

    public function modelDetail($id, \App\Services\Bike\PriceStatsService $priceStatsService)
    {
        return view('bikes.model_detail', $this->attachExternalContent($this->buildModelDetailData((int) $id)));
    }

    /**
     * 車種詳細ページのビューデータを構築（キャッシュ対象）
     */
    private function buildModelDetailData(int $id): array
    {
        // reviews.user は投稿者アバター表示用（ゲスト=user_id null は null で非クエリ）。キャッシュ版は v7 で刷新。
        $model = \App\Models\BikeModel::with(['manufacturer', 'reviews.user'])->findOrFail($id);

        $listings = \App\Models\Listing::with('shop')->where('bike_model_id', $id)
            ->active()
            ->limit(5)
            ->get()
            ->map(function ($listing) {
                return [
                    'id' => $listing->id,
                    'name' => $listing->title ?? $listing->bikeModel->name,
                    'total_price' => $listing->total_price ? number_format($listing->total_price / 10000, 1) : '-',
                    'prefecture' => $listing->shop->prefecture ?? '地域不明',
                    'images' => $listing->images ?? [],
                ];
            });

        $stats = $this->priceStatsService->getModelStats($id);
        $history = $this->priceStatsService->getPriceHistory($id);
        $resale = $this->priceStatsService->getResaleStats($id);

        // ★集計は承認済み(is_approved)のみ＝シード/非承認を除外（schema/表示の信頼性）。
        $reviewStats = DB::table('reviews')
            ->where('bike_model_id', $id)->where('is_approved', true)
            ->selectRaw('ROUND(AVG(rating), 1) as avg_rating, COUNT(*) as count')
            ->first();

        // 総合★の分布（5→1）。バー表示・一望性用。
        $distRows = DB::table('reviews')
            ->where('bike_model_id', $id)->where('is_approved', true)
            ->selectRaw('rating, COUNT(*) as c')->groupBy('rating')->pluck('c', 'rating');
        $reviewDistribution = [];
        for ($star = 5; $star >= 1; $star--) {
            $reviewDistribution[$star] = (int) ($distRows[$star] ?? 0);
        }

        $ratingFields = ['rating_design', 'rating_engine', 'rating_handling', 'rating_fuel_economy', 'rating_cost_performance'];
        $modelAvgs = DB::table('reviews')
            ->where('bike_model_id', $id)->where('is_approved', true)
            ->selectRaw(implode(', ', array_map(fn ($f) => "ROUND(AVG($f), 1) as avg_$f, COUNT($f) as cnt_$f", $ratingFields)))
            ->first();

        $categoryAvgs = null;
        if ($model->category_id) {
            $categoryModelIds = \App\Models\BikeModel::where('category_id', $model->category_id)->pluck('id');
            $categoryAvgs = DB::table('reviews')
                ->whereIn('bike_model_id', $categoryModelIds)->where('is_approved', true)
                ->selectRaw(implode(', ', array_map(fn ($f) => "ROUND(AVG($f), 1) as avg_$f", $ratingFields)))
                ->first();
        }

        $hasAnyRatingDetail = false;
        $categoryReviewStats = [];
        $fieldKeys = ['design', 'engine', 'handling', 'fuel_economy', 'cost_performance'];
        foreach ($ratingFields as $i => $field) {
            $avgKey = "avg_$field";
            $cntKey = "cnt_$field";
            $avg = $modelAvgs->$avgKey ?? null;
            $cnt = $modelAvgs->$cntKey ?? 0;
            if ($cnt > 0) {
                $hasAnyRatingDetail = true;
            }
            $catAvg = $categoryAvgs ? ($categoryAvgs->$avgKey ?? null) : null;
            $categoryReviewStats[$fieldKeys[$i]] = [
                'avg' => $avg ? (float) $avg : null,
                'category_avg' => $catAvg ? (float) $catAvg : null,
            ];
        }
        if (! $hasAnyRatingDetail) {
            $categoryReviewStats = [];
        }

        $relatedModels = \App\Models\BikeModel::with('manufacturer')
            ->where('manufacturer_id', $model->manufacturer_id)
            ->where('id', '!=', $model->id)
            ->whereNotNull('slug')
            ->withCount(['listings' => fn ($q) => $q->active()])
            ->orderByDesc('listings_count')
            ->limit(6)
            ->get();

        $similarDisplacementModels = collect();
        if ($model->displacement) {
            $similarDisplacementModels = \App\Models\BikeModel::with('manufacturer')
                ->where('id', '!=', $model->id)
                ->where('manufacturer_id', '!=', $model->manufacturer_id)
                ->whereBetween('displacement', [$model->displacement - 50, $model->displacement + 50])
                ->whereNotNull('slug')
                ->withCount(['listings' => fn ($q) => $q->active()])
                ->orderByDesc('listings_count')
                ->limit(6)
                ->get();
        }

        $sameCategoryModels = collect();
        if ($model->category_id) {
            $excludeIds = $similarDisplacementModels->pluck('id')->push($model->id)->all();
            $sameCategoryModels = \App\Models\BikeModel::with('manufacturer')
                ->where('category_id', $model->category_id)
                ->where('manufacturer_id', '!=', $model->manufacturer_id)
                ->whereNotIn('id', $excludeIds)
                ->whereNotNull('slug')
                ->withCount(['listings' => fn ($q) => $q->active()])
                ->orderByDesc('listings_count')
                ->limit(6)
                ->get();
        }

        $activeCount = Cache::remember("active_count_model_{$id}", 604800, function () use ($id) {
            return \App\Models\Listing::where('bike_model_id', $id)->active()->count();
        });

        // 公開モデル詳細の「オーナー」一覧は is_public のガレージのみ（カバーは公開配信＝200／
        // 非公開を混ぜるとカバーが404割れ＝壊れアイコンになるため）。本名は出さず公開ハンドルのみ。
        // 走行距離/維持費/燃費（シェアと同一ソース）入りの軽量カードをキャッシュ blob に焼く。
        $ownerCards = app(\App\Services\MyBike\MyBikeService::class)->publicGarageCardsForModel($model->id);
        $owners = $ownerCards['cards'];
        $ownersTotal = $ownerCards['total'];

        $similarModels = \App\Models\BikeModel::with('manufacturer')
            ->where('id', '!=', $model->id)
            ->where(function ($query) use ($model) {
                if ($model->category_id) {
                    $query->where('category_id', $model->category_id);
                }
            })
            ->whereHas('listings', fn ($query) => $query->where('is_sold_out', 0))
            ->withCount(['listings' => fn ($query) => $query->where('is_sold_out', 0)])
            ->orderByDesc('listings_count')
            ->limit(6)
            ->get();

        $prefectureStocks = \App\Models\Listing::where('bike_model_id', $id)
            ->active()
            ->join('shops', 'listings.shop_id', '=', 'shops.id')
            ->whereNotNull('shops.prefecture')
            ->selectRaw('shops.prefecture, COUNT(*) as stock_count')
            ->groupBy('shops.prefecture')
            ->orderByDesc('stock_count')
            ->get();

        $crossLinks = [
            ['label' => $model->name.'の在庫検索', 'url' => route('bikes.search', ['bike_model_id' => $model->id]), 'icon' => 'search', 'description' => '販売中の車両を探す'],
            ['label' => '駐車場マップ', 'url' => route('parking.index'), 'icon' => 'square-parking', 'description' => 'バイク駐車場を探す'],
            ['label' => 'ショップマップ', 'url' => route('shops.map'), 'icon' => 'store', 'description' => 'バイクショップを探す'],
            ['label' => $model->manufacturer->name.'の車種一覧', 'url' => route('bikes.models'), 'icon' => 'list', 'description' => '同メーカーの他モデル'],
            ['label' => 'バイク診断', 'url' => route('shindan.index'), 'icon' => 'sparkles', 'description' => 'あなたにピッタリの1台'],
            ['label' => '愛車ガレージ', 'url' => route('mybikes.index'), 'icon' => 'car', 'description' => '愛車を登録・管理'],
            ['label' => 'バイク盗難データ', 'url' => route('theft'), 'icon' => 'shield-alert', 'description' => '全国の盗難件数と対策'],
        ];

        // ⚠️ parts / news / videos は render path（楽天・RSS・YouTube API）から分離済み。
        // model_detailキャッシュ(7日)の blob には含めず、各エントリで read-only 注入する
        // （attachExternalContent）。ここに含めると空データが7日凍結する。
        $rankingStats = app(RankingService::class)->getModelRankingStats($id, $model->category_id);

        $yearDistribution = DB::table('listings')
            ->where('bike_model_id', $model->id)
            ->whereNotNull('model_year')
            ->where('model_year', '>', 1980)
            ->where('model_year', '<=', now()->year + 1)
            ->select('model_year', DB::raw('COUNT(*) as count'))
            ->groupBy('model_year')
            ->orderBy('model_year', 'asc')
            ->get();

        $yearStats = [];
        if ($yearDistribution->count() >= 3) {
            $totalWithYear = $yearDistribution->sum('count');
            $totalAll = DB::table('listings')->where('bike_model_id', $model->id)->count();
            $modeRow = $yearDistribution->sortByDesc('count')->first();
            $hasNew = DB::table('listings')
                ->where('bike_model_id', $model->id)
                ->where('model_year', '>=', now()->year)
                ->where('is_sold_out', false)
                ->exists();

            $yearStats = [
                'min' => $yearDistribution->min('model_year'),
                'max' => $yearDistribution->max('model_year'),
                'mode' => $modeRow->model_year,
                'has_new' => $hasNew,
                'coverage' => $totalAll > 0 ? round($totalWithYear / $totalAll * 100) : 0,
            ];
        }

        // 「よく比較される車種」は attachExternalContent の $relatedCompares（overviewタブの比較セクション）と
        // 同一SeoCompareペアの重複だったため統合。ここでの再取得は撤去（デッドクエリ排除）。

        return compact(
            'model', 'stats', 'history', 'resale', 'listings',
            'reviewStats', 'categoryReviewStats', 'reviewDistribution', 'relatedModels', 'similarDisplacementModels',
            'sameCategoryModels', 'activeCount', 'owners', 'ownersTotal', 'similarModels', 'crossLinks',
            'prefectureStocks', 'rankingStats',
            'yearDistribution', 'yearStats'
        );
    }

    /**
     * parts / news / videos を model_detailキャッシュの外で read-only 注入する。
     * 外部API（楽天・RSS・YouTube）はライブで叩かず、各refreshコマンドが埋めた
     * キャッシュ/DBのみを読む。既存blobに残る古い値も上書きで無効化する。
     */
    private function attachExternalContent(array $viewData): array
    {
        $model = $viewData['model'];
        $viewData['relatedParts'] = app(BikePartsService::class)->getForModel($model);
        $viewData['news'] = app(BikeNewsService::class)->getForModel($model);
        $viewData['videos'] = app(BikeYouTubeService::class)->getForModel((int) $model->id);
        // 地域別中古相場（日次バッチ stats:regional-prices が埋めるテーブルを読む）。
        // model_detailキャッシュ外なので、バッチ更新が即時反映される（TTL遅延なし）。
        $viewData['regionPriceStats'] = app(RegionalPriceService::class)->getForModel($model);

        // 適合表への導線サマリ（規格・区分レベル＋内部リンク。品番は出さない＝カニバリ回避）。
        // キャッシュ外＝インポート/打刻の即時反映。verified行のある task のみ。
        $viewData['fitmentSummary'] = app(\App\Services\Fitment\FitmentSummaryService::class)->forModel($model);

        // 車種Q&A（購入検討の疑問・相談。公開質問を新着順・回答数付き・回答本文も eager load）。
        // 車種ページでは先頭 QA_EXPANDED_COUNT 件を回答本文込みで初期DOM描画（価格コム式・SEO）。
        $viewData['modelQuestions'] = \App\Models\ModelQuestion::approved()
            ->where('bike_model_id', $model->id)
            ->withCount('approvedAnswers')
            ->with(['approvedAnswers' => fn ($q) => $q->with('user')->orderByDesc('created_at')])
            ->orderByDesc('created_at')
            ->limit(self::QA_LIST_LIMIT)
            ->get();
        $viewData['modelQuestionsTotal'] = \App\Models\ModelQuestion::approved()->where('bike_model_id', $model->id)->count();
        $viewData['qaExpandCount'] = self::QA_EXPANDED_COUNT;

        // 統合スレッド型クチコミ（新着順・公開のみ・返信数付き。キャッシュ外＝新規スレ/必答が即時反映）。
        $viewData['modelThreads'] = \App\Models\DiscussionThread::published()
            ->where('bike_model_id', $model->id)
            ->withCount('publishedReplies')
            ->orderByDesc('created_at')
            ->limit(self::QA_LIST_LIMIT)
            ->get();
        $viewData['modelThreadsTotal'] = \App\Models\DiscussionThread::published()->where('bike_model_id', $model->id)->count();

        // この車種を含む比較ページ（オーファン解消の内部リンク・存在する active ペアのみ・最大6）。
        $viewData['relatedCompares'] = \App\Models\SeoCompare::active()
            ->where(function ($q) use ($model) {
                $q->where('model1_id', $model->id)->orWhere('model2_id', $model->id);
            })
            ->ordered()
            ->with(['model1', 'model2'])
            ->limit(6)
            ->get();

        return $viewData;
    }

    /**
     * 施策B: カテゴリ×排気量帯×年式×走行距離で異なるハイライトテキスト
     */
    private function getBikeHighlight(object $listing): string
    {
        $rawDisp = $listing->displacement ?? ($listing->specs['displacement'] ?? '0');
        $displacement = (int) preg_replace('/[^0-9]/', '', (string) $rawDisp);
        $category = $listing->category ?? '';
        $year = $listing->model_year;
        $rawMileage = preg_replace('/[^0-9]/', '', (string) ($listing->mileage ?? ''));
        $mileage = ($rawMileage !== '' && $rawMileage !== '0') ? (int) $rawMileage : null;

        $parts = [];

        if ($displacement <= 50) {
            $parts[] = '原付一種（〜50cc）は普通免許で運転可能。維持費が最も安く、通勤・通学の足として人気です。';
        } elseif ($displacement <= 125) {
            $parts[] = '原付二種（51〜125cc）は小型限定免許で運転可能。高速道路は走れませんが、二段階右折不要で実用性が高いクラスです。';
        } elseif ($displacement <= 250) {
            $parts[] = '軽二輪（126〜250cc）は車検不要で維持費を抑えられる人気のクラス。高速道路も走行可能です。';
        } elseif ($displacement <= 400) {
            $parts[] = '普通二輪（251〜400cc）は車検が必要ですが、パワーと維持費のバランスが取れた万能クラスです。';
        } elseif ($displacement > 400) {
            $parts[] = '大型二輪（401cc〜）は大型二輪免許が必要。圧倒的なパワーとトルクで、長距離ツーリングにも余裕があります。';
        }

        $categoryComments = [
            'ネイキッド' => 'カウルがないためメンテナンスしやすく、ポジションも楽で初心者からベテランまで幅広く支持されています。',
            'スポーツ/レプリカ' => '前傾姿勢のライディングポジションでスポーティな走りが楽しめます。峠道やサーキットを攻めたい方に。',
            'アメリカン' => 'ロー&ロングのスタイルで足つき性が良く、ゆったりとしたクルージングが持ち味。カスタムパーツも豊富です。',
            'オフロード' => '軽量で足つき性に優れ、未舗装路でも走破できるサスペンションストロークが特徴。林道ツーリングに最適。',
            'スクーター' => 'AT操作で運転が楽。メットインスペースがあり実用性抜群。通勤・通学からちょい乗りまで幅広く活躍します。',
            'ツアラー' => '大型のカウルとゆったりしたシートで長距離走行の疲労を軽減。パニアケース装着可能なモデルが多いです。',
            'アドベンチャー' => 'オンロードもオフロードもこなせる万能タイプ。長距離ツーリングから未舗装路まで、1台で幅広く楽しめます。',
            'クラシック' => 'レトロなデザインが魅力。カフェレーサーやボバーなど、カスタムベースとしても人気があります。',
            'ミニバイク' => 'コンパクトなボディで取り回しが楽。駐輪スペースを選ばず、街中での機動性が抜群です。',
            'トライク' => '三輪で安定性が高く、普通免許で運転可能（車両による）。ヘルメット着用義務がない場合もあります。',
            '電動バイク(EV)' => 'ガソリン不要でランニングコストが低く、静粛性も魅力。近距離の通勤・通学に最適な次世代モビリティです。',
        ];
        if (isset($categoryComments[$category])) {
            $parts[] = $categoryComments[$category];
        }

        $currentYear = (int) date('Y');
        if ($year && is_numeric($year) && $currentYear - (int) $year <= 3) {
            $parts[] = '年式が新しいため、最新の安全装備や電子制御が搭載されている可能性が高いモデルです。';
        } elseif ($year && is_numeric($year) && $currentYear - (int) $year >= 20) {
            $parts[] = '生産から20年以上経過したヴィンテージモデル。純正部品の入手が難しい場合があるため、購入前にパーツ供給状況の確認をおすすめします。';
        }

        if ($mileage !== null && $mileage < 3000) {
            $parts[] = '走行距離が少なく、状態の良い車両と期待できます。';
        } elseif ($mileage !== null && $mileage > 50000) {
            $parts[] = '走行距離が多めですが、大型バイクではエンジンの当たりが出て調子が良くなることも。整備履歴を確認しましょう。';
        }

        return implode("\n", $parts);
    }

    /**
     * 施策D: 価格帯・パーセンタイルに応じた分析テキスト
     */
    private function getPriceAnalysisText(object $listing, ?array $stats, ?int $pricePercentile): string
    {
        $price = $listing->total_price;
        if (! $price || ! $stats || ($stats['count'] ?? 0) <= 1) {
            return '';
        }

        $modelName = $listing->bike_model_name ?? $listing->name;

        if ($pricePercentile !== null && $pricePercentile <= 20) {
            return "この{$modelName}は市場全体の中でもかなりお手頃な価格帯に位置しています。コストパフォーマンスを重視する方にとって注目の1台です。";
        } elseif ($pricePercentile !== null && $pricePercentile <= 40) {
            return "この{$modelName}は相場よりもやや安めの価格設定です。状態とのバランスを見て判断するのがおすすめです。";
        } elseif ($pricePercentile !== null && $pricePercentile <= 60) {
            return "この{$modelName}は相場の標準的な価格帯です。年式・走行距離・装備を考慮すると妥当な価格と言えます。";
        } elseif ($pricePercentile !== null && $pricePercentile <= 80) {
            return "この{$modelName}はやや高めの価格帯ですが、低走行や好条件の車両である可能性があります。車両の状態をショップに確認しましょう。";
        } else {
            return "この{$modelName}はプレミアム価格帯に位置しています。希少車・限定モデル・極上コンディションなど、特別な付加価値があると考えられます。";
        }
    }

    /**
     * 施策F: 車種別テキスト（人気車種個別 + メーカー汎用）
     */
    private function getModelComment(object $listing): string
    {
        $modelName = $listing->bike_model_name ?? '';
        $manufacturer = $listing->maker ?? '';

        $modelComments = [
            'PCX' => 'PCXはホンダの人気通勤スクーター。燃費性能が高く、スマートキーや前後ディスクブレーキなど装備も充実。通勤・通学からちょい乗りまで幅広く活躍します。',
            'レブル250' => 'レブル250は軽量でシート高が低く、初心者にも扱いやすいクルーザー。車検不要の250ccで維持費も抑えられます。',
            'CB400SF' => 'CB400SFは教習車としても採用される信頼性の高いネイキッド。VTEC搭載で低回転から高回転まで力強い走りが特徴です。',
            'Z900RS' => 'Z900RSはZ1をオマージュしたレトロスポーツ。現代の走行性能とクラシカルなデザインを両立し、発売以来ベストセラーを続けています。',
            'モンキー125' => 'モンキー125はコンパクトで愛嬌のあるデザインが人気のミニバイク。タンデムも可能で、カスタムパーツも豊富に揃っています。',
            'セロー250' => 'セロー250は「マウンテントレール」の愛称で知られるオフロードの名車。軽量で足つき性が良く、林道ツーリングの定番です。',
            'GSX-R1000' => 'GSX-R1000はスズキのフラッグシップスーパースポーツ。MotoGP直系の技術が投入されたサーキット志向のマシンです。',
            'YZF-R25' => 'YZF-R25はヤマハの人気250ccスポーツ。フルカウルの本格的なスタイルながら、扱いやすさも兼ね備えた入門スポーツの定番です。',
            'フォルツァ' => 'フォルツァはホンダの大型スクーター。快適な乗り心地と大容量の収納スペースで、通勤からツーリングまでこなせます。',
            'Ninja 400' => 'Ninja 400はカワサキの人気フルカウルスポーツ。軽量な車体に398ccエンジンを搭載し、パワフルかつ扱いやすいバランスが魅力です。',
            'CBR250RR' => 'CBR250RRはホンダのフラッグシップ250ccスポーツ。電子制御スロットルやクイックシフターなど、クラス最高峰の装備が特徴です。',
            'スーパーカブ110' => 'スーパーカブ110は世界で最も売れたバイクの血統を受け継ぐ実用車。圧倒的な燃費性能と耐久性が魅力です。',
            'CT125' => 'CT125ハンターカブはアウトドアテイストのレジャーバイク。オフロード走行も可能なタフネスさと、おしゃれなデザインが人気です。',
            'MT-07' => 'MT-07はヤマハのストリートファイター。軽量な車体に270度クランクの2気筒エンジンを搭載し、扱いやすさとトルク感を両立しています。',
            'MT-09' => 'MT-09はヤマハの3気筒ストリートファイター。圧倒的な加速力とアグレッシブなデザインでスポーツライディングを楽しめます。',
            'GB350' => 'GB350はホンダの空冷単気筒ネイキッド。心地よい鼓動感とクラシカルなデザイン、手頃な維持費で幅広い層から支持されています。',
            'Vストローム250' => 'Vストローム250はスズキのアドベンチャーツアラー。長距離ツーリングに適した快適性と250ccの経済性を両立した1台です。',
            'ジクサー250' => 'ジクサー250はスズキの油冷単気筒スポーツ。軽量な車体と低価格が魅力の、コスパ最強の250ccバイクです。',
            'XMAX' => 'XMAXはヤマハの人気ビッグスクーター。快適な乗り心地とスポーティな走りを両立し、通勤からツーリングまでオールマイティに活躍します。',
            'ADV160' => 'ADV160はホンダのアドベンチャースクーター。悪路走破性と実用性を兼ね備え、都市部から郊外まで活動範囲を広げてくれます。',
        ];

        if (isset($modelComments[$modelName])) {
            return $modelComments[$modelName];
        }

        $makerComments = [
            'ホンダ' => 'ホンダは世界最大の二輪メーカー。信頼性が高くパーツ供給も安定しており、初心者からベテランまで安心して乗れるバイクを幅広くラインナップしています。',
            'ヤマハ' => 'ヤマハはデザイン性とハンドリングに定評のあるメーカー。「感動創造企業」を掲げ、乗って楽しいバイクづくりに力を入れています。',
            'スズキ' => 'スズキはコストパフォーマンスの高さが魅力のメーカー。GSX-RシリーズやVストロームなど、個性的なモデルを多数展開しています。',
            'カワサキ' => 'カワサキは「漢カワサキ」の異名を持つパワフルなバイクが特徴。Ninjaシリーズやゼファーなど、根強いファンを持つ名車を多数輩出しています。',
            'ハーレーダビッドソン' => 'ハーレーは100年以上の歴史を誇るアメリカンバイクの象徴。独特の鼓動感とカスタム文化は唯一無二の存在です。',
            'BMW' => 'BMWモトラッドはドイツの高級バイクメーカー。水平対向エンジンやシャフトドライブなど独自技術と高い安全性が特徴です。',
            'ドゥカティ' => 'ドゥカティはイタリアの名門スポーツバイクメーカー。Lツインエンジンの鼓動とイタリアンデザインが世界中のライダーを魅了しています。',
            'トライアンフ' => 'トライアンフは英国最大のバイクメーカー。クラシカルなボンネビルからスポーティなスピードトリプルまで幅広い個性を持ちます。',
            'KTM' => 'KTMはオーストリアのオフロードに強いメーカー。「READY TO RACE」を掲げ、軽量でアグレッシブなモデルが特徴です。',
        ];

        return $makerComments[$manufacturer] ?? '';
    }

    /**
     * 施策G: 地域別テキスト
     */
    private function getRegionComment(object $listing): string
    {
        $prefecture = $listing->prefecture ?? '';

        $regionComments = [
            '北海道' => '北海道はライダーの聖地。夏のツーリングシーズンには全国からライダーが集まります。広大な直線道路と雄大な自然は唯一無二の体験です。',
            '青森県' => '青森県は奥入瀬渓流や八甲田山など自然豊かなツーリングスポットが点在。夏の十和田湖周遊は東北ツーリングのハイライトです。',
            '岩手県' => '岩手県は三陸海岸のシーサイドラインが人気。龍泉洞や平泉の中尊寺など歴史・自然の見どころも豊富です。',
            '宮城県' => '宮城県は仙台を拠点に蔵王エコーラインや松島など名所へアクセス良好。牛タンやずんだなどグルメツーリングも楽しめます。',
            '秋田県' => '秋田県は男鹿半島のなまはげロードや田沢湖など、独特の風土を感じるツーリングが楽しめます。',
            '山形県' => '山形県は蔵王のお釜や月山など、変化に富んだワインディングが魅力。さくらんぼやそばなどグルメも充実しています。',
            '福島県' => '福島県は磐梯吾妻スカイラインが絶景ロードとして全国的に有名。裏磐梯の湖沼群も見どころです。',
            '茨城県' => '茨城県は筑波山のワインディングやひたち海浜公園など、都心からのアクセスが良いツーリングスポットが充実しています。',
            '栃木県' => '栃木県はいろは坂・日光東照宮・那須高原など観光ツーリングの宝庫。紅葉シーズンは特に人気です。',
            '群馬県' => '群馬県は榛名山・赤城山・志賀草津道路など、ワインディング好きにはたまらないエリア。温泉も豊富です。',
            '埼玉県' => '埼玉県は秩父・長瀞エリアが人気のツーリングスポット。都心からのアクセスが良く、日帰りツーリングに最適です。',
            '千葉県' => '千葉県は房総半島の海沿いルートが人気。マザー牧場や鋸山など家族でも楽しめるスポットが多いのが特徴です。',
            '東京都' => '東京都は首都圏最大のバイクショップ密集地。奥多摩エリアは都心から近い人気のワインディングスポットです。',
            '神奈川県' => '神奈川県は箱根ターンパイクや湘南海岸など、バラエティ豊かなツーリングルートが魅力。バイクショップの数も全国トップクラスです。',
            '新潟県' => '新潟県は日本海沿いのシーサイドルートと山間部のワインディングの両方が楽しめます。コシヒカリの田園風景も絶景です。',
            '富山県' => '富山県は立山黒部アルペンルート近郊のワインディングが人気。富山湾の海鮮グルメも楽しみの一つです。',
            '石川県' => '石川県は能登半島一周ルートが人気のツーリングコース。千里浜なぎさドライブウェイは砂浜を走れる全国唯一のロードです。',
            '福井県' => '福井県はエンゼルラインや三方五湖レインボーラインなど絶景ルートが点在。越前ガニや恐竜博物館も見どころです。',
            '山梨県' => '山梨県は富士山を望むツーリングスポットの宝庫。富士五湖周遊や昇仙峡など、四季を通じて楽しめます。',
            '長野県' => '長野県はビーナスライン・志賀草津道路など、全国屈指の絶景ワインディングが集中するライダー天国です。',
            '岐阜県' => '岐阜県はせせらぎ街道や飛騨高山など、風情あるツーリングスポットが充実。白川郷は世界遺産としても人気です。',
            '静岡県' => '静岡県は伊豆スカイラインや御前崎など海と山の両方が楽しめるエリア。バイクメーカーの工場見学も可能です。',
            '愛知県' => '愛知県は中部圏の拠点として各方面へのアクセスが良好。渥美半島や知多半島の海沿いルートが人気です。',
            '三重県' => '三重県は伊勢志摩パールロードや青山高原など、海と山のコントラストが美しいルートが魅力。伊勢神宮参拝ツーリングも定番です。',
            '滋賀県' => '滋賀県は琵琶湖一周（ビワイチ）がライダーに人気。湖畔沿いの快走路は初心者にも走りやすいルートです。',
            '京都府' => '京都府は美山かやぶきの里や天橋立など、歴史と自然が融合したツーリングスポットが豊富です。',
            '大阪府' => '大阪府は関西圏最大のバイクショップ激戦区。泉北や河内の峠道は地元ライダーに人気のスポットです。',
            '兵庫県' => '兵庫県は六甲山や淡路島など変化に富んだツーリングが楽しめます。淡路島一周は日帰りツーリングの定番です。',
            '奈良県' => '奈良県は吉野山や大台ヶ原など、山深い自然を感じるツーリングが魅力。鹿に注意しながらのライディングも奈良ならでは。',
            '和歌山県' => '和歌山県は熊野古道や白浜など、南紀の温暖な気候と海沿いルートが魅力。冬でもツーリングを楽しめる温暖さが人気です。',
            '鳥取県' => '鳥取県は鳥取砂丘や大山のワインディングが人気。日本海沿いの国道9号線は快走路として知られています。',
            '島根県' => '島根県は出雲大社参拝ツーリングや隠岐の島など、神秘的な雰囲気のスポットが特徴です。',
            '岡山県' => '岡山県は蒜山高原やブルーラインなど、爽快なツーリングロードが充実。「晴れの国」で雨のリスクが少ないのも魅力です。',
            '広島県' => '広島県はしまなみ海道が全国屈指のツーリングルート。瀬戸内海の多島美を眺めながらの走行は格別です。',
            '山口県' => '山口県は角島大橋や秋吉台カルストロードなど、SNS映えする絶景ルートが人気。本州最西端の到達証明も記念になります。',
            '徳島県' => '徳島県は大歩危・小歩危の渓谷沿いルートや剣山スーパー林道など、冒険心をくすぐるルートが魅力です。',
            '香川県' => '香川県は小豆島ツーリングやうどん巡りが人気。瀬戸大橋を渡るルートも四国ツーリングの醍醐味です。',
            '愛媛県' => '愛媛県はしまなみ海道の四国側起点。UFOラインや四国カルストなど、四国を代表する絶景ロードが集中しています。',
            '高知県' => '高知県は四万十川沿いの清流ルートや室戸岬・足摺岬など、ダイナミックな海岸線が魅力。カツオのたたきは絶品です。',
            '福岡県' => '福岡県は九州ツーリングの拠点。都市部から少し足を延ばせば糸島や英彦山など自然豊かなスポットにアクセスできます。',
            '佐賀県' => '佐賀県は虹の松原や呼子の朝市など、コンパクトながら見どころの多いエリア。有田焼の窯元巡りも楽しめます。',
            '長崎県' => '長崎県は島原半島や五島列島など、独特の地形と歴史を感じるツーリングが楽しめます。',
            '熊本県' => '熊本県は阿蘇のミルクロードやまなみハイウェイなど、全国のライダーが憧れる絶景ロードの聖地です。',
            '大分県' => '大分県はやまなみハイウェイの起点で、別府・湯布院など温泉ツーリングの定番エリア。九重連山の景色は圧巻です。',
            '宮崎県' => '宮崎県は日南海岸のフェニックスロードが南国ムード満点。冬でも温暖で、年間を通じてツーリングを楽しめます。',
            '鹿児島県' => '鹿児島県は桜島を望むシーサイドルートや指宿の開聞岳など、火山と海のダイナミックな景色が魅力です。',
            '沖縄県' => '沖縄県は年中温暖でツーリングに最適。海中道路や古宇利大橋など、エメラルドグリーンの海を眺めながら走る体験は格別です。',
        ];

        return $regionComments[$prefecture] ?? '';
    }

    /**
     * 施策H: 価格帯別テキスト
     */
    private function getPriceBandComment(object $listing): string
    {
        $price = $listing->total_price;
        if (! $price || ! is_numeric($price)) {
            return '';
        }

        $priceYen = (float) $price * 10000;

        return match (true) {
            $priceYen < 200000 => '20万円以下は初めてのバイクや通勤用のセカンドバイクとして人気の価格帯。任意保険や装備品の予算も含めて総予算30万円程度で始められます。走行距離や年式をよく確認し、試乗できるショップを選ぶのがおすすめです。',
            $priceYen < 500000 => '20〜50万円は最も選択肢が多い価格帯。人気の250ccクラスや年式の新しい125ccが豊富に揃います。この価格帯では状態の良い車両が見つかりやすく、初心者にもおすすめです。',
            $priceYen < 1000000 => '50〜100万円は中型〜大型バイクの中心価格帯。400ccの車検付きモデルや、年式の新しい250ccスポーツなどが選べます。装備や状態にこだわった選び方ができる価格帯です。',
            $priceYen < 1500000 => '100〜150万円は大型バイクの人気価格帯。リッタークラスのネイキッドやアドベンチャーモデルが狙えます。新車に近いコンディションの中古車も見つかりやすいゾーンです。',
            $priceYen < 2000000 => '150〜200万円はプレミアムクラス。高年式の大型スポーツやツアラー、輸入車のエントリーモデルが中心。充実した電子制御や安全装備を備えたモデルが多いです。',
            default => '200万円以上はハイエンドクラス。最新のフラッグシップモデルや希少な限定車が中心です。新車に近い走行距離や極上コンディションの車両が多く、所有する喜びを最大限に味わえます。',
        };
    }

    /**
     * 同車種在庫と比較した市場ポジション分析
     *
     * @param  Listing  $listing  Raw Eloquent model
     * @return array{items: array, overall: string, count: int}|null
     */
    private function getMarketPosition(Listing $listing): ?array
    {
        if (! $listing->bike_model_id) {
            return null;
        }

        $modelStats = Cache::remember(
            "market_position_{$listing->bike_model_id}",
            604800,
            function () use ($listing) {
                return Listing::where('bike_model_id', $listing->bike_model_id)
                    ->where('is_sold_out', false)
                    ->selectRaw('
                        COUNT(*) as cnt,
                        AVG(total_price) as avg_price,
                        AVG(mileage) as avg_mileage,
                        AVG(model_year) as avg_year,
                        AVG(CASE WHEN total_price IS NOT NULL AND price IS NOT NULL AND total_price > price THEN total_price - price ELSE NULL END) as avg_expenses
                    ')
                    ->first();
            }
        );

        if (! $modelStats || $modelStats->cnt < 5) {
            return null;
        }

        $items = [];

        // 価格比較
        if ($listing->total_price && $listing->total_price > 0 && $modelStats->avg_price > 0) {
            $priceDiffPct = (($listing->total_price - (float) $modelStats->avg_price) / (float) $modelStats->avg_price) * 100;
            if ($priceDiffPct <= -10) {
                $icon = '&#x2705;'; // ✅
                $label = '割安';
                $rank = 'good';
            } elseif ($priceDiffPct <= 10) {
                $icon = '&#x27A1;&#xFE0F;'; // ➡️
                $label = '相場並み';
                $rank = 'normal';
            } else {
                $icon = '&#x26A0;&#xFE0F;'; // ⚠️
                $label = '割高';
                $rank = 'caution';
            }
            $items[] = [
                'key' => 'price',
                'title' => '価格',
                'icon' => $icon,
                'label' => $label,
                'rank' => $rank,
                'value' => number_format((float) ($listing->total_price / 10000), 1).'万円',
                'avg' => number_format((float) ($modelStats->avg_price / 10000), 1).'万円',
            ];
        }

        // 走行距離比較
        if ($listing->mileage !== null && (float) $modelStats->avg_mileage > 0) {
            $mileageDiffPct = (($listing->mileage - (float) $modelStats->avg_mileage) / (float) $modelStats->avg_mileage) * 100;
            if ($mileageDiffPct <= -10) {
                $icon = '&#x2705;';
                $label = '低走行';
                $rank = 'good';
            } elseif ($mileageDiffPct <= 10) {
                $icon = '&#x27A1;&#xFE0F;';
                $label = '平均的';
                $rank = 'normal';
            } else {
                $icon = '&#x26A0;&#xFE0F;';
                $label = '多走行';
                $rank = 'caution';
            }
            $items[] = [
                'key' => 'mileage',
                'title' => '走行距離',
                'icon' => $icon,
                'label' => $label,
                'rank' => $rank,
                'value' => number_format($listing->mileage).'km',
                'avg' => number_format((int) round((float) $modelStats->avg_mileage)).'km',
            ];
        }

        // 年式比較
        if ($listing->model_year && $listing->model_year > 0 && (float) $modelStats->avg_year > 0) {
            $yearDiff = $listing->model_year - (float) $modelStats->avg_year;
            if ($yearDiff >= 1) {
                $icon = '&#x2705;';
                $label = '高年式';
                $rank = 'good';
            } elseif ($yearDiff >= -1) {
                $icon = '&#x27A1;&#xFE0F;';
                $label = '平均的';
                $rank = 'normal';
            } else {
                $icon = '&#x26A0;&#xFE0F;';
                $label = '低年式';
                $rank = 'caution';
            }
            $items[] = [
                'key' => 'year',
                'title' => '年式',
                'icon' => $icon,
                'label' => $label,
                'rank' => $rank,
                'value' => $listing->model_year.'年',
                'avg' => round((float) $modelStats->avg_year).'年',
            ];
        }

        // 諸経費比較
        if ($listing->total_price && $listing->price && $listing->total_price > $listing->price && $modelStats->avg_expenses && $modelStats->avg_expenses > 0) {
            $thisExpenses = $listing->total_price - $listing->price;
            $expDiff = $thisExpenses - (float) $modelStats->avg_expenses;
            $expPercent = ($expDiff / (float) $modelStats->avg_expenses) * 100;

            if ($expPercent <= -15) {
                $icon = '&#x2705;';
                $label = '安い';
                $rank = 'good';
            } elseif ($expPercent >= 15) {
                $icon = '&#x26A0;&#xFE0F;';
                $label = 'やや高め';
                $rank = 'caution';
            } else {
                $icon = '&#x27A1;&#xFE0F;';
                $label = '平均的';
                $rank = 'normal';
            }

            $items[] = [
                'key' => 'expenses',
                'title' => '諸経費',
                'icon' => $icon,
                'label' => $label,
                'rank' => $rank,
                'value' => number_format((int) $thisExpenses).'円',
                'avg' => number_format((int) $modelStats->avg_expenses).'円',
            ];
        }

        if (empty($items)) {
            return null;
        }

        // 総合評価
        $goodCount = collect($items)->where('rank', 'good')->count();
        $total = count($items);
        if ($goodCount === $total) {
            $overall = 'excellent';
        } elseif ($goodCount >= $total / 2) {
            $overall = 'good';
        } else {
            $overall = 'fair';
        }

        return [
            'items' => $items,
            'overall' => $overall,
            'count' => (int) $modelStats->cnt,
        ];
    }

    /**
     * 車種詳細ページ（スラッグURL版）
     * URL: /bikes/{mfrSlug}/{modelSlug}
     *
     * $modelSlug はスラッグ文字列 or 数値ID（日本語名フォールバック）
     */
    public function modelDetailBySlug(string $mfrSlug, string $modelSlug)
    {
        $manufacturer = \App\Models\Manufacturer::where('slug', $mfrSlug)->first();

        if (! $manufacturer) {
            abort(404);
        }

        $model = is_numeric($modelSlug)
            ? \App\Models\BikeModel::where('id', $modelSlug)->where('manufacturer_id', $manufacturer->id)->firstOrFail()
            : \App\Models\BikeModel::where('slug', $modelSlug)->where('manufacturer_id', $manufacturer->id)->firstOrFail();

        // 統合済み(merged_into_id)なら canonical のURLへ301（id/slug どちらのアクセスもまとめて寄せる）
        if ($model->merged_into_id) {
            return redirect($model->canonicalModel()->seo_url, 301);
        }

        // IDアクセスでslugがある場合は正規URLへ301リダイレクト
        if (is_numeric($modelSlug) && $model->slug) {
            return redirect("/bikes/{$mfrSlug}/{$model->slug}", 301);
        }

        // slugがnullの車種はIDをキーに含める（Observerのパージ処理と一致させ、キー衝突を防ぐ）
        $slugForKey = $model->slug ?? $model->id;
        $cacheKey = \App\Models\BikeModel::modelDetailCacheKey($mfrSlug, $slugForKey);
        $viewData = $this->attachExternalContent(
            Cache::remember($cacheKey, 604800, fn () => $this->buildModelDetailData($model->id))
        );

        return view('bikes.model_detail', $viewData);
    }

    /**
     * レビュー専用ページ（車種詳細と同じ画面、OGPをレーダーチャートに差替、レビューセクションへ自動スクロール）
     */
    /** レビューへの「参考になった」（重複防止つき・スレ投票と同型）。 */
    public function markReviewHelpful(Request $request, int $reviewId): \Illuminate\Http\JsonResponse
    {
        $review = \App\Models\Review::where('is_approved', true)->findOrFail($reviewId);

        $voterHash = $request->user()
            ? hash('sha256', 'user:'.$request->user()->id)
            : hash('sha256', $request->ip().'|'.config('app.key'));

        $vote = \App\Models\ReviewHelpfulVote::firstOrCreate(
            ['review_id' => $review->id, 'voter_hash' => $voterHash],
            ['user_id' => $request->user()?->id],
        );

        if ($vote->wasRecentlyCreated) {
            $review->increment('helpful_count');
        }

        return response()->json(['helpful_count' => $review->fresh()->helpful_count]);
    }

    public function modelReviews(string $mfrSlug, string $modelSlug, ?int $reviewId = null)
    {
        $manufacturer = \App\Models\Manufacturer::where('slug', $mfrSlug)->first();

        if (! $manufacturer) {
            abort(404);
        }

        $model = \App\Models\BikeModel::where('slug', $modelSlug)
            ->where('manufacturer_id', $manufacturer->id)
            ->firstOrFail();

        // 統合済みなら canonical のモデルページへ301
        if ($model->merged_into_id) {
            return redirect($model->canonicalModel()->seo_url, 301);
        }

        // slugがnullの車種はIDをキーに含める（Observerのパージ処理と一致させ、キー衝突を防ぐ）
        $slugForKey = $model->slug ?? $model->id;
        $cacheKey = \App\Models\BikeModel::modelDetailCacheKey($mfrSlug, $slugForKey);
        $viewData = $this->attachExternalContent(
            Cache::remember($cacheKey, 604800, fn () => $this->buildModelDetailData($model->id))
        );
        $viewData['reviewOgpMode'] = true;
        $viewData['scrollToReviewId'] = $reviewId;

        return view('bikes.model_detail', $viewData);
    }

    /**
     * レビュー個別ページ（IDベース：slugがない車種用フォールバック）
     */
    public function modelReviewsById(int $modelId, int $reviewId)
    {
        $model = \App\Models\BikeModel::with('manufacturer')->findOrFail($modelId);

        // 統合済みなら canonical のモデルページへ301
        if ($model->merged_into_id) {
            return redirect($model->canonicalModel()->seo_url, 301);
        }

        // slugがあればスラッグベースURLへリダイレクト
        if ($model->slug && $model->manufacturer?->slug) {
            return redirect()->route('bikes.model_review_single', [
                'mfrSlug' => $model->manufacturer->slug,
                'modelSlug' => $model->slug,
                'reviewId' => $reviewId,
            ], 301);
        }

        $cacheKey = \App\Models\BikeModel::modelDetailCacheKey('id', $modelId);
        $viewData = $this->attachExternalContent(
            Cache::remember($cacheKey, 604800, fn () => $this->buildModelDetailData($modelId))
        );
        $viewData['reviewOgpMode'] = true;
        $viewData['scrollToReviewId'] = $reviewId;

        return view('bikes.model_detail', $viewData);
    }
}
