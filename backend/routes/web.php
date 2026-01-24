<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Bike\BikeController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\Api\BikeApiController;

/**
 * MotoHub Route Definitions
 */

// --- メイン機能 (BikeController) ---
Route::get('/', [BikeController::class, 'index'])->name('bikes.index');

// '/bikes' で始まるルートをグループ化
Route::prefix('bikes')->name('bikes.')->controller(BikeController::class)->group(function () {
    Route::get('/search', 'search')->name('search');    // URL: /bikes/search, Name: bikes.search
    Route::get('/models', 'models')->name('models');    // URL: /bikes/models, Name: bikes.models
    Route::get('/suggest', 'suggest')->name('suggest'); // URL: /bikes/suggest, Name: bikes.suggest
    Route::get('/{id}', 'show')->name('show');          // URL: /bikes/{id}, Name: bikes.show
});

// --- お気に入り機能 (Wishlist) ---
// URLを /wishlist にして独立させ、主要機能としての扱いを明確にします
Route::get('/wishlist', [BikeController::class, 'wishlist'])->name('wishlist');
Route::get('/api/wishlist/fetch', [BikeController::class, 'fetchWishlist'])->name('api.wishlist.fetch');
Route::get('/compare', [BikeController::class, 'compare'])->name('bikes.compare');

// --- API関連 (JavaScriptからの非同期リクエスト用) ---
Route::prefix('api')->group(function () {
    // 車両の条件一致件数を取得するAPI (JSは /api/bikes/count を呼ぶ)
    Route::get('/bikes/count', [BikeApiController::class, 'count']);

    // メーカー・車種連動用API (JSは /api/manufacturers/{id}/models を呼ぶ)
    Route::get('/manufacturers/{manufacturer}/models', [BikeApiController::class, 'models']);
});
// --- 固定・情報ページ (PageController) ---
// 運営情報や法的ページなど、情報の閲覧がメインのページをグループ化します
Route::prefix('pages')->name('pages.')->group(function () {
    Route::get('/about', [PageController::class, 'about'])->name('about');
    
    // お問い合わせ
    Route::get('/contact', [PageController::class, 'contact'])->name('contact');
    Route::post('/contact', [PageController::class, 'send'])->name('contact.send');

    Route::get('/privacy-policy', [PageController::class, 'privacyPolicy'])->name('privacy-policy');
    Route::get('/terms', [PageController::class, 'terms'])->name('terms');
});
