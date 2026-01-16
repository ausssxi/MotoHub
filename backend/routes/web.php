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

// --- 固定ページ (PageController) ---
Route::prefix('pages')->name('pages.')->group(function () {
    Route::get('/about', [PageController::class, 'about'])->name('about');
    Route::get('/contact', [PageController::class, 'contact'])->name('contact');
    Route::get('/privacy-policy', [PageController::class, 'privacyPolicy'])->name('privacy-policy');
    Route::get('/terms', [PageController::class, 'terms'])->name('terms');
});