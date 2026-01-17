<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BikeController;
use App\Http\Controllers\PageController;

/**
 * MotoHub Route Definitions
 */

// --- バイク検索機能 (BikeController) ---
Route::get('/', [BikeController::class, 'index'])->name('bikes.index');
Route::get('/search', [BikeController::class, 'search'])->name('bikes.search'); 
Route::get('/models', [BikeController::class, 'models'])->name('bikes.models');
Route::get('/bikes/suggest', [BikeController::class, 'suggest'])->name('bikes.suggest');

// --- 固定ページ (PageController) ---
Route::prefix('pages')->name('pages.')->group(function () {
    Route::get('/about', [PageController::class, 'about'])->name('about');
    
    // お問い合わせ (表示)
    Route::get('/contact', [PageController::class, 'contact'])->name('contact');
    // お問い合わせ (送信処理)
    Route::post('/contact', [PageController::class, 'send'])->name('contact.send');

    Route::get('/privacy-policy', [PageController::class, 'privacyPolicy'])->name('privacy-policy');
    Route::get('/terms', [PageController::class, 'terms'])->name('terms');
});