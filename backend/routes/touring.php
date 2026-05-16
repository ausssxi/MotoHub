<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TouringController;
use App\Http\Controllers\TouringOgpController;
use App\Http\Controllers\TouringSpotController;
use App\Http\Controllers\Admin\TouringGuideController;
use App\Models\TouringSpot;

/*
|--------------------------------------------------------------------------
| Touring Guide Routes
|--------------------------------------------------------------------------
*/

// --- OGP画像（キャッシュミドルウェア不要・独自ヘッダーで制御） ---
Route::get('/touring/{slug}/ogp.png', [TouringOgpController::class, 'show'])->name('touring.ogp');

// --- ツーリングスポット（都道府県別SEOページ） ---
Route::prefix('touring')->name('touring.spot.')->group(function () {
    Route::get('/{prefectureSlug}/{spot}', [TouringSpotController::class, 'show'])
        ->where('prefectureSlug', TouringSpot::prefectureSlugRegex())
        ->name('show');
    Route::get('/{prefectureSlug}', [TouringSpotController::class, 'index'])
        ->where('prefectureSlug', TouringSpot::prefectureSlugRegex())
        ->name('index');
});

// --- 公開画面（ツーリングガイド） ---
Route::prefix('touring')->name('touring.')->group(function () {
    Route::get('/', [TouringController::class, 'index'])->name('index');
    Route::get('/planner', [TouringSpotController::class, 'planner'])->name('planner');
    Route::get('/{slug}', [TouringController::class, 'show'])->name('show');
});

// --- 管理画面 ---
Route::prefix('admin/touring')->name('admin.touring.')->middleware(['auth', 'can:manage-blog'])->group(function () {
    Route::get('/', [TouringGuideController::class, 'index'])->name('index');
    Route::get('/create', [TouringGuideController::class, 'create'])->name('create');
    Route::post('/', [TouringGuideController::class, 'store'])->name('store');
    Route::get('/{id}/edit', [TouringGuideController::class, 'edit'])->name('edit');
    Route::put('/{id}', [TouringGuideController::class, 'update'])->name('update');
    Route::delete('/{id}', [TouringGuideController::class, 'destroy'])->name('destroy');
});
