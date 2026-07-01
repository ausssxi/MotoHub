<?php

use App\Http\Controllers\Api\AiSearchController;
use App\Http\Controllers\Api\BikeApiController;
// コントローラーの読み込み
use App\Http\Controllers\Api\PoiApiController;
use App\Http\Controllers\Api\RoadsideStationApiController;
use App\Http\Controllers\Ar\ArController;
use App\Http\Controllers\Auth\GoogleLoginController;
use App\Http\Controllers\Auth\LineAuthController;
use App\Http\Controllers\Bike\BargainsController; // Breeze用
// 統計情報API
use App\Http\Controllers\Bike\BargainsOgpController; // 買取査定LP
use App\Http\Controllers\Bike\BikeController; // 愛車ログ機能
use App\Http\Controllers\Bike\BikeIdentifierController; // 公開ガレージ
use App\Http\Controllers\Bike\CategoryLandingController;
use App\Http\Controllers\Bike\DiscontinuedController;
use App\Http\Controllers\Bike\NewArrivalsController;
use App\Http\Controllers\Bike\NewArrivalsOgpController;
use App\Http\Controllers\Bike\OverseasBikeController;
use App\Http\Controllers\Bike\PriceDropsController;
use App\Http\Controllers\Bike\PriceDropsOgpController;
use App\Http\Controllers\Bike\ReviewOgpController;
use App\Http\Controllers\Bike\TrendController;
use App\Http\Controllers\DealOgpController;
use App\Http\Controllers\Feature\FeatureController;
use App\Http\Controllers\MyBike\GarageOgpController;
use App\Http\Controllers\MyBike\GaragePublicController;
use App\Http\Controllers\MyBike\MyBikeController;
use App\Http\Controllers\MyBike\RiderProfileController;
use App\Http\Controllers\NewsController;
use App\Http\Controllers\Page\SellController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\Parking\ParkingAreaController;
use App\Http\Controllers\Parking\ParkingController;
use App\Http\Controllers\Parking\StationParkingController;
use App\Http\Controllers\Parts\PartsController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RankingController;
use App\Http\Controllers\RidersMapController;
use App\Http\Controllers\Shindan\ShindanController;
use App\Http\Controllers\Shop\ShopAreaController;
use App\Http\Controllers\Shop\ShopController;
use App\Http\Controllers\Trouble\TroubleController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * MotoHub Route Definitions
 * ==========================================
 * リダイレクト設定 (SEO評価引き継ぎ用)
 * ==========================================
 */

// 1. 車種一覧ページ
Route::redirect('/models', '/bikes/models', 301);

// 2. 検索ページ
Route::get('/search', function (Request $request) {
    return redirect()->route('bikes.search', $request->all(), 301);
});

// 3. サジェスト機能
Route::get('/suggest', function (Request $request) {
    return redirect()->route('bikes.suggest', $request->all(), 301);
});

/**
 * ==========================================
 * メインルート設定 (MotoHub)
 * ==========================================
 */

// トップページ
Route::get('/', [BikeController::class, 'index'])->name('bikes.index');

// sort パラメータ付き /bikes リダイレクト
Route::get('/bikes', function (Request $request) {
    $sort = $request->query('sort');
    if ($sort === 'newest' || $sort === 'new' || $sort === 'latest') {
        return redirect()->route('bikes.new_arrivals', [], 301);
    }
    if ($sort === 'bargain_desc') {
        return redirect()->route('bikes.price_drops', [], 301);
    }

    return redirect()->route('bikes.search', $request->except('sort'), 301);
});

// '/bikes' グループ (検索・一覧・詳細)
Route::prefix('bikes')->name('bikes.')->controller(BikeController::class)->group(function () {
    Route::get('/search', 'search')->name('search');    // /bikes/search
    Route::get('/models', 'models')->name('models');    // /bikes/models
    Route::get('/models/{manufacturer}/bikes', 'modelsApi')->name('models.api'); // Ajax
    Route::get('/suggest', 'suggest')->name('suggest'); // /bikes/suggest
    Route::get('/prefectures', 'prefectures')->name('prefectures');

    // 車種判定AI
    Route::get('/identify', [BikeIdentifierController::class, 'index'])->name('identify');
    Route::post('/identify', [BikeIdentifierController::class, 'identify'])->name('identify.post');

    // 新着入荷OGP画像
    Route::get('/new-arrivals/ogp.png', [NewArrivalsOgpController::class, 'show'])
        ->name('new_arrivals_ogp');

    // 新着入荷ページ
    Route::get('/new-arrivals/{date?}', [NewArrivalsController::class, 'index'])
        ->where('date', '\d{4}-\d{2}-\d{2}')
        ->name('new_arrivals');

    // 値下げOGP画像
    Route::get('/price-drops/ogp.png', [PriceDropsOgpController::class, 'show'])
        ->name('price_drops_ogp');

    // 値下げページ
    Route::get('/price-drops/{date?}', [PriceDropsController::class, 'index'])
        ->where('date', '\d{4}-\d{2}-\d{2}')
        ->name('price_drops');

    // お買い得OGP画像
    Route::get('/bargains/ogp.png', [BargainsOgpController::class, 'show'])
        ->name('bargains_ogp');

    // お買い得ページ
    Route::get('/bargains', [BargainsController::class, 'index'])
        ->name('bargains');

    // 絶版バイク一覧
    Route::get('/discontinued', [DiscontinuedController::class, 'index'])
        ->name('discontinued');

    // SEO着地ページ（市区町村レベル — セグメント数が多いので先に定義）
    Route::get('/area/{prefecture}/{city}/{slug}', 'cityLanding')->name('city_landing');

    // 4+セグメントcatch-all（GSC 404リダイレクト）
    Route::get('/area/{prefecture}/{extra}', function (string $prefecture, string $extra) {
        $firstSegment = explode('/', $extra)[0];
        $seoService = app(\App\Services\Bike\SeoLandingService::class);
        $pageInfo = $seoService->resolvePageInfo($prefecture, $firstSegment);
        if (! empty($pageInfo)) {
            return redirect("/bikes/area/{$prefecture}/{$firstSegment}", 301);
        }

        return redirect("/bikes/area/{$prefecture}", 301);
    })->where('extra', '.+/.+');

    // SEO着地ページ（都道府県レベル）
    Route::get('/area/{prefecture}/{slug}', 'landing')->name('landing');

    // 都道府県エリアインデックス
    Route::get('/area/{prefecture}', 'areaIndex')->name('area_index');

    // カタログページ（地域なしプログラマティックSEO）
    Route::get('/catalog/{slug}', 'catalog')->name('catalog');

    // 車種比較ハブ（200ペアの索引ページ）。静的パスを {slug} より先に登録。
    Route::get('/compare', 'modelCompareHub')->name('model_compare_hub');

    // 車種比較ページ（SEOプログラマティック）
    Route::get('/compare/{slug}', 'modelCompare')->name('model_compare');

    // 地域差・独立ページ（SEO）。静的indexを {slug} より先に登録。
    Route::get('/region-price', 'regionPriceIndex')->name('region_price_index');
    Route::get('/region-price/{slug}', 'regionPrice')->name('region_price');

    // レビュー一覧ハブ（車種軸）
    Route::get('/reviews', 'reviewsIndex')->name('reviews_index');

    // seo_urlフォールバック: メーカーslugがないモデルの /bikes/model/{id} を処理
    Route::get('/model/{id}', function ($id) {
        $model = \App\Models\BikeModel::with('manufacturer')->findOrFail($id);
        if ($model->merged_into_id) {
            return redirect($model->canonicalModel()->seo_url, 301);
        }
        $mfrSlug = $model->manufacturer?->slug;
        $modelSlug = $model->slug;

        if ($mfrSlug && $modelSlug) {
            return redirect("/bikes/{$mfrSlug}/{$modelSlug}", 301);
        }
        if ($mfrSlug) {
            return redirect("/bikes/{$mfrSlug}/{$id}", 301);
        }
        $controller = app(\App\Http\Controllers\Bike\BikeController::class);
        $priceStats = app(\App\Services\Bike\PriceStatsService::class);

        return $controller->modelDetail($id, $priceStats);
    })->where('id', '[0-9]+')->name('model_detail.fallback');

    // トレンド・ランキング
    Route::get('/trends', [TrendController::class, 'index'])->name('trends');

    // 輸入バイクLP
    Route::get('/overseas', [OverseasBikeController::class, 'index'])->name('overseas');
    Route::get('/overseas/{makerSlug}', [OverseasBikeController::class, 'maker'])
        ->where('makerSlug', '[a-z][a-z0-9\-]*')
        ->name('overseas.maker');

    // カテゴリ別LP（排気量）
    Route::get('/cc/{slug}', [CategoryLandingController::class, 'byDisplacement'])
        ->where('slug', '50|125|250|400|750|over750')
        ->name('category_cc');

    // カテゴリ別LP（タイプ）
    Route::get('/type/{slug}', [CategoryLandingController::class, 'byType'])
        ->where('slug', '[a-z][a-z0-9\-]*')
        ->name('category_type');

    // 車種別カタログページ
    Route::get('/models/{id}', function ($id) {
        $model = \App\Models\BikeModel::with('manufacturer')->findOrFail($id);
        if ($model->merged_into_id) {
            return redirect($model->canonicalModel()->seo_url, 301);
        }
        $mfrSlug = $model->manufacturer?->slug;
        $modelSlug = $model->slug;

        if ($mfrSlug && $modelSlug) {
            return redirect("/bikes/{$mfrSlug}/{$modelSlug}", 301);
        }
        if ($mfrSlug) {
            return redirect("/bikes/{$mfrSlug}/{$id}", 301);
        }
        $controller = app(\App\Http\Controllers\Bike\BikeController::class);
        $priceStats = app(\App\Services\Bike\PriceStatsService::class);

        return $controller->modelDetail($id, $priceStats);
    })->where('id', '[0-9]+')->name('model_detail.legacy');

    // レビュー投稿（★スパム対策：1分間に3回までのレート制限を追加）
    Route::post('/models/{id}/reviews', 'storeReview')
        ->name('model_detail.review')
        ->middleware('throttle:3,1');

    // 詳細ページ (ID指定) - 他の固定ルートより後に書く
    Route::get('/{id}', 'show')->name('show')->where('id', '[0-9]+');

    // お買い得OGP画像
    Route::get('/{listing}/ogp.png', [DealOgpController::class, 'show'])->name('deal_ogp')->where('listing', '[0-9]+');
});

// メーカー×排気量カテゴリページ (例: /bikes/honda/250cc)
Route::get('/bikes/{mfrSlug}/{ccSlug}', [BikeController::class, 'categoryByDisplacement'])
    ->where('mfrSlug', '[a-z][a-z0-9\-]*')
    ->where('ccSlug', '\d+cc')
    ->name('bikes.category_displacement');

// レビューOGP画像（車種全体 / 個別レビュー）
Route::get('/bikes/{mfrSlug}/{modelSlug}/review-ogp/{reviewId}.png', [ReviewOgpController::class, 'showBySlug'])
    ->where('mfrSlug', '[a-z][a-z0-9\-]*')
    ->where('reviewId', '[0-9]+')
    ->name('bikes.review_ogp_individual');

Route::get('/bikes/{mfrSlug}/{modelSlug}/review-ogp.png', [ReviewOgpController::class, 'showBySlug'])
    ->where('mfrSlug', '[a-z][a-z0-9\-]*')
    ->name('bikes.review_ogp');

// レビューページ（個別レビュー / 車種全体）
Route::get('/bikes/{mfrSlug}/{modelSlug}/reviews/{reviewId}', [BikeController::class, 'modelReviews'])
    ->where('mfrSlug', '[a-z][a-z0-9\-]*')
    ->where('reviewId', '[0-9]+')
    ->name('bikes.model_review_single');

// レビュー個別ページ（IDベース：slugがない車種用フォールバック）
Route::get('/bikes/models/{modelId}/reviews/{reviewId}', [BikeController::class, 'modelReviewsById'])
    ->where(['modelId' => '[0-9]+', 'reviewId' => '[0-9]+'])
    ->name('bikes.model_review_single_by_id');

Route::get('/bikes/{mfrSlug}/{modelSlug}/reviews', [BikeController::class, 'modelReviews'])
    ->where('mfrSlug', '[a-z][a-z0-9\-]*')
    ->name('bikes.model_reviews');

Route::get('/bikes/{mfrSlug}/{modelSlug}', [BikeController::class, 'modelDetailBySlug'])
    ->where('mfrSlug', '[a-z][a-z0-9\-]*')
    ->name('bikes.model_detail');

// ライダーズマップ（統合マップ）
Route::get('/riders-map', [RidersMapController::class, 'index'])->name('riders.map');

Route::prefix('shops')->name('shops.')->group(function () {
    // マップページ → ライダーズマップへ301リダイレクト
    Route::get('/map', fn (Request $request) => redirect()->route('riders.map', $request->query(), 301))->name('map');
    // エリア検索API (※公開APIなので一旦ここに残します)
    Route::get('/api/area', [ShopController::class, 'area'])->name('api.area');

    // エリア別ショップページ
    Route::prefix('area')->name('area.')->controller(ShopAreaController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/{prefecture}', 'prefecture')->name('prefecture');
        Route::get('/{prefecture}/{city}', 'city')->name('city');
    });

    // 整備・修理ショップ（repair_only）エリアページ
    Route::prefix('repair')->name('repair.')->controller(\App\Http\Controllers\Shop\ShopRepairAreaController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/{prefecture}', 'prefecture')->name('prefecture');
        Route::get('/{prefecture}/{city}', 'city')->name('city');
    });

    // チェーン別まとめページ（/{id} の前に配置）
    Route::get('/chain/{chainSlug}', [ShopController::class, 'chainShow'])->name('chain');

    Route::get('/{id}', [ShopController::class, 'show'])->name('show')->where('id', '[0-9]+');
    Route::post('/{shop}/visited', [ShopController::class, 'visited'])->name('visited')->where('shop', '[0-9]+')->middleware('throttle:10,1');
    Route::post('/{shop}/acceptance-report', [ShopController::class, 'submitAcceptanceReport'])->name('acceptance-report')->where('shop', '[0-9]+')->middleware('throttle:3,1');
});

// 公開ガレージ（auth不要）
Route::prefix('garage/public')->name('garage.public.')->controller(GaragePublicController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/{myBike}', 'show')->name('show')->where('myBike', '[0-9]+');
});

// 公開ガレージの動的OGP画像（非公開は 404）
Route::get('/garage/public/{myBike}/ogp.png', [GarageOgpController::class, 'show'])
    ->name('garage.public.ogp')
    ->where('myBike', '[0-9]+');

// 公開ライダープロフィール（auth不要・非連番トークンキー・公開ハンドル＋公開ガレージのみ）
Route::get('/riders/{token}', [RiderProfileController::class, 'show'])
    ->name('riders.profile')
    ->where('token', '[A-Za-z0-9]+');

// ギャラリー画像の配信（auth不要）。許可はメソッド内で判定：
// 「所有者」OR「is_public ガレージのカバー(=1枚目)」なら200、それ以外は404。
// 非公開ガレージの画像・is_publicの非カバー写真は owner 以外404（leak防止）。
Route::get('/garage/{myBike}/images/{image}', [MyBikeController::class, 'showImage'])
    ->name('mybikes.images.show')
    ->where(['myBike' => '[0-9]+', 'image' => '[0-9]+']);

// 駐車場マップ（公開）
Route::prefix('parking')->name('parking.')->controller(ParkingController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/api/search', 'search')->name('search');
    Route::get('/create', 'create')->name('create')->middleware('auth');
    Route::get('/{bikeParking}', 'show')->name('show')->where('bikeParking', '[0-9]+');
    Route::post('/{bikeParking}/review', 'storeReview')->name('review')->where('bikeParking', '[0-9]+')->middleware('throttle:3,1');
    Route::post('/{bikeParking}/used', 'incrementUsed')->name('used')->where('bikeParking', '[0-9]+')->middleware('throttle:10,1');
});

// 駅別駐車場ページ
Route::prefix('parking/station')->name('parking.station.')->controller(StationParkingController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/{station}', 'show')->name('show');
});

// 駐車場エリア別ページ
Route::prefix('parking/area')->name('parking.area.')->controller(ParkingAreaController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/{prefecture}', 'prefecture')->name('prefecture');
    Route::get('/{prefecture}/{city}', 'city')->name('city');
    Route::get('/{prefecture}/{city}/{stationName}', 'stationInArea')->name('station');
});

// 駐車場マップ（要ログイン）
Route::middleware('auth')->prefix('parking')->name('parking.')->controller(ParkingController::class)->group(function () {
    Route::post('/', 'store')->name('store');
    Route::get('/{bikeParking}/edit', 'edit')->name('edit')->where('bikeParking', '[0-9]+');
    Route::put('/{bikeParking}', 'update')->name('update')->where('bikeParking', '[0-9]+');
});

// AR駐車場・ショップファインダー
Route::get('/ar', [ArController::class, 'index'])->name('ar.index');

// パーツ検索（楽天市場・Yahoo!ショッピングAPI）
Route::get('/parts', [PartsController::class, 'index'])->name('parts.index');
Route::get('/parts/search', [PartsController::class, 'search'])->name('parts.search');
Route::get('/parts/search/yahoo', [PartsController::class, 'searchYahoo'])->name('parts.search.yahoo');
Route::get('/parts/compare', [PartsController::class, 'compare'])->name('parts.compare');
Route::get('/parts/category/{slug}', [PartsController::class, 'category'])->name('parts.category');

// ニュースページ
Route::prefix('news')->name('news.')->controller(NewsController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/model/{bikeModelId}', 'model')->name('model');
    Route::get('/{id}', 'show')->name('show')->where('id', '[0-9]+');
});

Route::middleware('auth')->prefix('news')->name('news.')->controller(NewsController::class)->group(function () {
    Route::post('/{newsId}/comment', 'comment')->name('comment')->middleware('throttle:3,1');
    Route::post('/{newsId}/pick', 'togglePick')->name('pick')->middleware('throttle:10,1');
    Route::post('/comment/{commentId}/like', 'toggleCommentLike')->name('comment.like')->middleware('throttle:10,1');
});

// ソーシャル②: フォロー / ガレージいいね（ログイン必須・トグル）
Route::middleware('auth')->group(function () {
    Route::post('/riders/{token}/follow', [\App\Http\Controllers\Social\FollowController::class, 'toggle'])
        ->name('riders.follow')->where('token', '[A-Za-z0-9]+')->middleware('throttle:30,1');
    Route::post('/garage/{myBike}/like', [\App\Http\Controllers\Social\GarageLikeController::class, 'toggle'])
        ->name('garage.like')->where('myBike', '[0-9]+')->middleware('throttle:30,1');
});

// 売れ筋ランキング
Route::prefix('ranking')->name('ranking.')->controller(RankingController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/download', 'downloadCsv')->name('download');
    Route::get('/daily/{date?}', 'daily')->name('daily')->where('date', '\d{4}-\d{2}-\d{2}');
    Route::get('/weekly', 'weekly')->name('weekly');
    Route::get('/monthly/{month?}', 'monthly')->name('monthly')->where('month', '\d{4}-\d{1,2}');
    Route::get('/model/{bikeModelId}', 'modelStats')->name('model_stats')->where('bikeModelId', '[0-9]+');
});

// 特集ページ (SEOランディング)
Route::prefix('features')->name('features.')->controller(FeatureController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/{slug}', 'show')->name('show');
});

// お気に入り・比較機能 (未ログインでも閲覧可能なページ)
Route::get('/wishlist', [BikeController::class, 'wishlist'])->name('wishlist');
Route::get('/api/wishlist/fetch', [BikeController::class, 'fetchWishlist'])->name('api.wishlist.fetch');
Route::get('/compare', [BikeController::class, 'compare'])->name('bikes.compare');

// API関連 (※公開APIなのでここに残します)
Route::prefix('api')->group(function () {
    Route::get('/bikes/count', [BikeApiController::class, 'count']);
    Route::get('/manufacturers/{manufacturer}/models', [BikeApiController::class, 'models']);
    Route::get('/manufacturers/{manufacturer}/models-light', [BikeApiController::class, 'modelsLight']);
    Route::get('/stats/price/{bikeModelId}', [App\Http\Controllers\Api\StatsApiController::class, 'getPriceStats']);
    Route::get('/widget/price/{bikeModelId}', [\App\Http\Controllers\Api\WidgetApiController::class, 'price']);
    Route::get('/pois', [PoiApiController::class, 'search'])->name('api.pois');
    Route::post('/pois/along-route', [PoiApiController::class, 'alongRoute'])->name('api.pois.along_route');
    Route::get('/roadside-stations', [RoadsideStationApiController::class, 'search'])->name('api.roadside_stations');
});

// 固定ページ (運営者情報など)
Route::prefix('pages')->name('pages.')->group(function () {
    Route::get('/about', [PageController::class, 'about'])->name('about');
    Route::get('/contact', [PageController::class, 'contact'])->name('contact');
    Route::post('/contact', [PageController::class, 'send'])->name('contact.send');
    Route::get('/privacy-policy', [PageController::class, 'privacyPolicy'])->name('privacy-policy');
    Route::get('/terms', [PageController::class, 'terms'])->name('terms');
    Route::get('/widget', [PageController::class, 'widget'])->name('widget');
});

// データAPI 紹介ページ（PR/Zenn/ブログからの受け皿・公開URLは /data）
Route::get('/data', [PageController::class, 'data'])->name('data');

// バイク4択クイズ
Route::view('/quiz', 'quiz.index')->name('quiz');

// バイクわらしべ長者
Route::view('/warashibe', 'warashibe.index')->name('warashibe');

// バイク2048パズル
Route::view('/puzzle', 'puzzle.index')->name('puzzle');

// バイクガレージパズル（スバラシティ風）
Route::view('/games/subaracity', 'games.subaracity')->name('games.subaracity');

// ライダーの塔（放置系RPG）
Route::view('/games/riders-tower', 'games.riders-tower')->name('games.riders-tower');

// AIスマート検索
Route::get('/ai-search', [AiSearchController::class, 'index'])->name('ai-search');

// 買取査定LP
Route::get('/sell', [SellController::class, 'index'])->name('sell.index');
Route::post('/api/sell/calculate', [SellController::class, 'calculate'])->name('sell.calculate');

/**
 * ==========================================
 * 会員機能ルート (Breeze & マイページ等)
 * ==========================================
 */

// ログイン後のダッシュボード (マイページ)
Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

// 認証が必要な「画面・Web機能」グループ (※ここは auth に戻します)
Route::middleware('auth')->group(function () {

    // お気に入りAPI
    Route::post('/api/favorites/toggle', [\App\Http\Controllers\Api\FavoriteController::class, 'toggle'])->name('api.favorites.toggle');
    Route::get('/api/favorites/ids', [\App\Http\Controllers\Api\FavoriteController::class, 'index'])->name('api.favorites.ids');

    // 検索条件の保存API
    Route::post('/api/saved-searches', [\App\Http\Controllers\Api\SavedSearchController::class, 'store'])->name('api.saved_searches.store');

    // 閲覧履歴API
    Route::post('/api/history/record', [\App\Http\Controllers\Api\HistoryController::class, 'record'])->name('api.history.record');
    Route::get('/api/history/ids', [\App\Http\Controllers\Api\HistoryController::class, 'index'])->name('api.history.ids');
    Route::post('/api/history/sync', [\App\Http\Controllers\Api\HistoryController::class, 'sync'])->name('api.history.sync');

    // お気に入りスポットAPI
    Route::get('/api/spots', [\App\Http\Controllers\Api\SavedSpotController::class, 'index'])->name('api.spots.index');
    Route::post('/api/spots', [\App\Http\Controllers\Api\SavedSpotController::class, 'store'])->name('api.spots.store');
    Route::delete('/api/spots/{id}', [\App\Http\Controllers\Api\SavedSpotController::class, 'destroy'])->name('api.spots.destroy');

    // お気に入りスポット一覧ページ
    Route::get('/mypage/saved-spots', function () {
        $spots = \App\Models\UserSavedSpot::where('user_id', auth()->id())
            ->orderByDesc('created_at')
            ->get();

        return view('mypage.saved-spots', compact('spots'));
    })->name('mypage.saved_spots');

    // プロフィール編集 (Breeze標準)
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    // プロフィール表示設定（駐車場レビュー等のオプトアウト）。名前/メール更新とは別フォーム。
    Route::patch('/profile/visibility', [ProfileController::class, 'updateVisibility'])->name('profile.visibility');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // 愛車ログ機能
    Route::prefix('garage')->name('mybikes.')->controller(MyBikeController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::get('/{myBike}', 'show')->name('show')->where('myBike', '[0-9]+');
        // 愛車情報の編集（愛称＋走行距離・owner-only。車種変更/公開設定は対象外）
        Route::put('/{myBike}', 'update')->name('update')->where('myBike', '[0-9]+');
        Route::delete('/{myBike}', 'destroy')->name('destroy');
        Route::post('/{myBike}/visibility', 'updateVisibility')->name('visibility')->where('myBike', '[0-9]+');
        Route::get('/{myBike}/export', 'exportCsv')->name('export')->where('myBike', '[0-9]+');
        Route::post('/{myBike}/fuel', 'storeFuel')->name('fuel.store');
        // 給油記録の編集／削除（owner-only・更新/削除後に燃費・総走行距離を再計算）
        Route::put('/{myBike}/fuel/{fuelLog}', 'updateFuel')->name('fuel.update')->where(['myBike' => '[0-9]+', 'fuelLog' => '[0-9]+']);
        Route::delete('/{myBike}/fuel/{fuelLog}', 'destroyFuel')->name('fuel.destroy')->where(['myBike' => '[0-9]+', 'fuelLog' => '[0-9]+']);
        Route::post('/{myBike}/maintenance', 'storeMaintenance')->name('maintenance.store')->where('myBike', '[0-9]+');
        // 整備記録の編集（owner-only・type検証・走行距離/維持費は次回表示で再集計）
        Route::put('/{myBike}/maintenance/{record}', 'updateMaintenance')->name('maintenance.update')->where(['myBike' => '[0-9]+', 'record' => '[0-9]+']);
        // カスタム記録の保存／編集／記録（整備・カスタム）の削除（owner-only）
        Route::post('/{myBike}/custom', 'storeCustom')->name('custom.store')->where('myBike', '[0-9]+');
        Route::put('/{myBike}/custom/{record}', 'updateCustom')->name('custom.update')->where(['myBike' => '[0-9]+', 'record' => '[0-9]+']);
        Route::delete('/{myBike}/records/{record}', 'destroyRecord')->name('records.destroy')->where(['myBike' => '[0-9]+', 'record' => '[0-9]+']);
        // 記録(整備/カスタム)の添付写真（owner-only・private配信）。後付け追加/配信/削除。
        Route::post('/{myBike}/records/{record}/images', 'storeRecordImage')->name('records.images.store')->where(['myBike' => '[0-9]+', 'record' => '[0-9]+']);
        Route::get('/{myBike}/records/{record}/images/{image}', 'showRecordImage')->name('records.images.show')->where(['myBike' => '[0-9]+', 'record' => '[0-9]+', 'image' => '[0-9]+']);
        Route::delete('/{myBike}/records/{record}/images/{image}', 'destroyRecordImage')->name('records.images.destroy')->where(['myBike' => '[0-9]+', 'record' => '[0-9]+', 'image' => '[0-9]+']);
        // 給油フォームのOCR入力補完（owner-only・コスト無防備を防ぐため throttle 必須）
        Route::post('/{myBike}/ocr/fuel', 'extractFuelOcr')->name('ocr.fuel')->where('myBike', '[0-9]+')->middleware('throttle:ocr-extract');
        // 給油フォームの音声入力補完（owner-only・throttle 必須）
        Route::post('/{myBike}/voice/fuel', 'parseFuelVoice')->name('voice.fuel')->where('myBike', '[0-9]+')->middleware('throttle:voice-extract');
        // 整備・カスタム記録の OCR/voice 入力補完（owner-only・throttle は給油と共有＝合算）
        Route::post('/{myBike}/ocr/record', 'extractRecordOcr')->name('ocr.record')->where('myBike', '[0-9]+')->middleware('throttle:ocr-extract');
        Route::post('/{myBike}/voice/record', 'parseRecordVoice')->name('voice.record')->where('myBike', '[0-9]+')->middleware('throttle:voice-extract');
        // ギャラリー画像の操作（owner-only）。アップロード/キャプション/削除は本人のみ。
        Route::post('/{myBike}/images', 'storeImage')->name('images.store')->where('myBike', '[0-9]+');
        Route::patch('/{myBike}/images/{image}', 'updateImageCaption')->name('images.caption')->where(['myBike' => '[0-9]+', 'image' => '[0-9]+']);
        Route::delete('/{myBike}/images/{image}', 'destroyImage')->name('images.destroy')->where(['myBike' => '[0-9]+', 'image' => '[0-9]+']);
        Route::get('/api/search-models', 'searchModels')->name('api.search_models');
        // カスタム記録のパーツ名/ブランド サジェスト（自前データのみ・外部API不使用・軽量）。乱用防止に軽くthrottle。
        Route::get('/api/parts-suggest', 'partsSuggest')->name('api.parts_suggest')->middleware('throttle:60,1');
        // カスタム記録の商品検索（2a・「商品を探す」押下時のみ外部API・ProductSearchServiceでグレースフル）。
        Route::get('/api/parts-products', 'partsProducts')->name('api.parts_products')->middleware('throttle:30,1');
    });
});

// --- Google認証ルート ---
Route::get('/auth/google/redirect', [GoogleLoginController::class, 'redirect'])
    ->name('auth.google.redirect');
Route::get('/auth/google/callback', [GoogleLoginController::class, 'callback'])
    ->name('auth.google.callback');

// --- LINE認証ルート ---
Route::get('/auth/line/redirect', [LineAuthController::class, 'redirect'])
    ->name('auth.line.redirect');
Route::get('/auth/line/callback', [LineAuthController::class, 'callback'])
    ->name('auth.line.callback');

// --- LINE連携解除（認証必要） ---
Route::middleware('auth')->group(function () {
    // 既存のルート...

    Route::delete('/auth/line/unlink', [LineAuthController::class, 'unlink'])
        ->name('auth.line.unlink');
});

Route::get('/shindan', [ShindanController::class, 'index'])->name('shindan.index');
Route::post('/shindan/diagnose', [ShindanController::class, 'diagnose'])
    ->name('shindan.diagnose')
    ->middleware('throttle:3,1');
Route::get('/shindan/result', [ShindanController::class, 'result'])->name('shindan.result');

// 症状自己診断ツール（トラブル診断）— ルールベース決定木。クライアント側で辿る
Route::get('/trouble', [TroubleController::class, 'index'])->name('trouble.index');

// Breezeの認証ルート読み込み (login, register等)
require __DIR__.'/auth.php';

// ブログ機能ルート読み込み
require __DIR__.'/blog.php';

// ツーリングガイド機能ルート読み込み
require __DIR__.'/touring.php';

// --- RSSフィード（Google News Publisher Center用） ---
use App\Http\Controllers\FeedController;

Route::get('/feed/news', [FeedController::class, 'news'])->name('feed.news');
Route::get('/feed/blog', [FeedController::class, 'blog'])->name('feed.blog');
Route::get('/feed/original', [FeedController::class, 'original'])->name('feed.original');
