<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
// コントローラーの読み込み
use App\Http\Controllers\Bike\BikeController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\Api\BikeApiController;
use App\Http\Controllers\Shop\ShopController;

/**
 * MotoHub Route Definitions
 * ==========================================
 * リダイレクト設定 (SEO評価引き継ぎ用)
 * ==========================================
 */

// 1. 車種一覧ページ
// 旧: /models -> 新: /bikes/models
Route::redirect('/models', '/bikes/models', 301);

// 2. 検索ページ (クエリパラメータ ?keyword=... を引き継ぐ)
// 旧: /search -> 新: /bikes/search
Route::get('/search', function (Request $request) {
    // 301リダイレクト (クエリパラメータを維持)
    return redirect()->route('bikes.search', $request->all(), 301);
});

// 3. サジェスト機能
// 旧: /suggest -> 新: /bikes/suggest
Route::get('/suggest', function (Request $request) {
    return redirect()->route('bikes.suggest', $request->all(), 301);
});

/**
 * ==========================================
 * メインルート設定
 * ==========================================
 */

// トップページ
Route::get('/', [BikeController::class, 'index'])->name('bikes.index');

// '/bikes' グループ (検索・一覧・詳細)
Route::prefix('bikes')->name('bikes.')->controller(BikeController::class)->group(function () {
    Route::get('/search', 'search')->name('search');    // /bikes/search
    Route::get('/models', 'models')->name('models');    // /bikes/models
    Route::get('/suggest', 'suggest')->name('suggest'); // /bikes/suggest
    Route::get('/prefectures', 'prefectures')->name('prefectures'); 
    Route::get('/models/{id}', 'modelDetail')->name('model_detail')->where('id', '[0-9]+');
    Route::get('/area/{prefecture}/{slug}', 'landing')->name('landing');
    Route::post('/models/{id}/reviews', 'storeReview')->name('model_detail.review');
    Route::get('/{id}', 'show')->name('show')->where('id', '[0-9]+'); 
});

Route::get('/shops/{id}', [ShopController::class, 'show'])->name('shops.show')->where('id', '[0-9]+');

// お気に入り機能
Route::get('/wishlist', [BikeController::class, 'wishlist'])->name('wishlist');
Route::get('/api/wishlist/fetch', [BikeController::class, 'fetchWishlist'])->name('api.wishlist.fetch');
Route::get('/compare', [BikeController::class, 'compare'])->name('bikes.compare');

// API関連
Route::prefix('api')->group(function () {
    Route::get('/bikes/count', [BikeApiController::class, 'count']);
    Route::get('/manufacturers/{manufacturer}/models', [BikeApiController::class, 'models']);
    Route::get('/stats/price/{bikeModelId}', [App\Http\Controllers\Api\StatsApiController::class, 'getPriceStats']);
});

// 固定ページ (運営者情報など)
Route::prefix('pages')->name('pages.')->group(function () {
    Route::get('/about', [PageController::class, 'about'])->name('about');
    Route::get('/contact', [PageController::class, 'contact'])->name('contact');
    Route::post('/contact', [PageController::class, 'send'])->name('contact.send');
    Route::get('/privacy-policy', [PageController::class, 'privacyPolicy'])->name('privacy-policy');
    Route::get('/terms', [PageController::class, 'terms'])->name('terms');
});