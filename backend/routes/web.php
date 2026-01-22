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
Route::get('/search', [BikeController::class, 'search'])->name('bikes.search'); 
Route::get('/models', [BikeController::class, 'models'])->name('bikes.models');
Route::get('/bikes/suggest', [BikeController::class, 'suggest'])->name('bikes.suggest');

// --- お気に入り機能 (Wishlist) ---
// URLを /wishlist にして独立させ、主要機能としての扱いを明確にします
Route::get('/wishlist', [BikeController::class, 'wishlist'])->name('wishlist');
Route::get('/api/wishlist/fetch', [BikeController::class, 'fetchWishlist'])->name('api.wishlist.fetch');

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