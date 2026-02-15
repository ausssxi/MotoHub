<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
// コントローラーの読み込み
use App\Http\Controllers\Bike\BikeController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\Api\BikeApiController;
use App\Http\Controllers\Shop\ShopController;
use App\Http\Controllers\Bike\TrendController;
use App\Http\Controllers\Api\FavoriteController; // お気に入りAPI
use App\Http\Controllers\ProfileController; // Breeze用
use App\Http\Controllers\Api\SavedSearchController; // 検索条件保存API
use App\Http\Controllers\Api\StatsController; // 統計情報API
use App\Http\Controllers\Page\SellController; // 買取査定LP

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

// '/bikes' グループ (検索・一覧・詳細)
Route::prefix('bikes')->name('bikes.')->controller(BikeController::class)->group(function () {
    Route::get('/search', 'search')->name('search');    // /bikes/search
    Route::get('/models', 'models')->name('models');    // /bikes/models
    Route::get('/suggest', 'suggest')->name('suggest'); // /bikes/suggest
    Route::get('/prefectures', 'prefectures')->name('prefectures');
    
    // SEO着地ページ
    Route::get('/area/{prefecture}/{slug}', 'landing')->name('landing');
    
    // トレンド・ランキング
    Route::get('/trends', [TrendController::class, 'index'])->name('trends');

    // 車種別カタログページ
    Route::get('/models/{id}', 'modelDetail')->name('model_detail')->where('id', '[0-9]+');
    
    // レビュー投稿
    Route::post('/models/{id}/reviews', 'storeReview')->name('model_detail.review');

    // 詳細ページ (ID指定) - 他の固定ルートより後に書く
    Route::get('/{id}', 'show')->name('show')->where('id', '[0-9]+'); 
});

Route::get('/shops/{id}', [ShopController::class, 'show'])->name('shops.show')->where('id', '[0-9]+');

// お気に入り・比較機能 (未ログインでも閲覧可能なページ)
Route::get('/wishlist', [BikeController::class, 'wishlist'])->name('wishlist');
Route::get('/api/wishlist/fetch', [BikeController::class, 'fetchWishlist'])->name('api.wishlist.fetch');
Route::get('/compare', [BikeController::class, 'compare'])->name('bikes.compare');

// API関連
Route::prefix('api')->group(function () {
    Route::get('/bikes/count', [BikeApiController::class, 'count']);
    Route::get('/manufacturers/{manufacturer}/models', [BikeApiController::class, 'models']);
    Route::get('/stats/price/{bikeModelId}', [App\Http\Controllers\Api\StatsController::class, 'getPriceStats']);
});

// 固定ページ (運営者情報など)
Route::prefix('pages')->name('pages.')->group(function () {
    Route::get('/about', [PageController::class, 'about'])->name('about');
    Route::get('/contact', [PageController::class, 'contact'])->name('contact');
    Route::post('/contact', [PageController::class, 'send'])->name('contact.send');
    Route::get('/privacy-policy', [PageController::class, 'privacyPolicy'])->name('privacy-policy');
    Route::get('/terms', [PageController::class, 'terms'])->name('terms');
});

// 買取査定LP 
Route::get('/sell', [SellController::class, 'index'])->name('sell.index');
Route::post('/api/sell/calculate', [SellController::class, 'calculate'])->name('sell.calculate');

/**
 * ==========================================
 * 会員機能ルート (Breeze & お気に入り)
 * ==========================================
 */

// ログイン後のダッシュボード (マイページ)
Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

// 認証が必要な機能グループ
Route::middleware('auth')->group(function () {
    // お気に入りAPI (DB保存)
    Route::post('/api/favorites/toggle', [FavoriteController::class, 'toggle'])->name('api.favorites.toggle');
    Route::get('/api/favorites/ids', [FavoriteController::class, 'index'])->name('api.favorites.ids');

    // 検索条件の保存API
    Route::post('/api/saved-searches', [SavedSearchController::class, 'store'])->name('api.saved_searches.store');

    // プロフィール編集 (Breeze標準)
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Breezeの認証ルート読み込み (login, register等)
require __DIR__.'/auth.php';