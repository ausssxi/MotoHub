<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\BlogSeriesController;
use App\Http\Controllers\BlogFeedController;
use App\Http\Controllers\Admin\BlogPostController as AdminBlogPostController;
use App\Http\Controllers\Admin\BlogSeriesController as AdminBlogSeriesController;
use App\Http\Controllers\Admin\BlogTagController as AdminBlogTagController;
use App\Http\Controllers\Admin\BlogImageController;
use App\Http\Controllers\Api\BlogTagController as ApiBlogTagController;
use App\Http\Controllers\BlogOgpController;
use App\Http\Controllers\Api\BlogPreviewController;

/*
|--------------------------------------------------------------------------
| Blog Routes
|--------------------------------------------------------------------------
|
| 公開画面: 認証不要 + blog.cache ミドルウェア
| 管理画面: auth + can:manage-blog ミドルウェア
|
*/

// --- OGP画像（キャッシュミドルウェア不要・独自ヘッダーで制御） ---
Route::get('/blog/{post:slug}/ogp.png', [BlogOgpController::class, 'show'])->name('blog.ogp');

// --- 公開画面（認証不要） ---
Route::prefix('blog')->name('blog.')->middleware('blog.cache')->group(function () {
    Route::get('/', [BlogController::class, 'index'])->name('index');
    Route::get('/tag/{slug}', [BlogController::class, 'byTag'])->name('tag');
    Route::get('/series/{slug}', [BlogSeriesController::class, 'show'])->name('series.show');
    Route::get('/feed', [BlogFeedController::class, 'rss'])->name('feed');
    Route::get('/{slug}', [BlogController::class, 'show'])->name('show');
});

// --- 管理画面（認証 + 権限チェック） ---
Route::prefix('admin/blog')->name('admin.blog.')->middleware(['auth', 'can:manage-blog'])->group(function () {
    // 記事管理
    Route::get('/posts', [AdminBlogPostController::class, 'index'])->name('posts.index');
    Route::get('/posts/create', [AdminBlogPostController::class, 'create'])->name('posts.create');
    Route::post('/posts', [AdminBlogPostController::class, 'store'])->name('posts.store');
    Route::get('/posts/{id}/edit', [AdminBlogPostController::class, 'edit'])->name('posts.edit');
    Route::put('/posts/{id}', [AdminBlogPostController::class, 'update'])->name('posts.update');
    Route::delete('/posts/{id}', [AdminBlogPostController::class, 'destroy'])->name('posts.destroy');

    // シリーズ管理
    Route::get('/series', [AdminBlogSeriesController::class, 'index'])->name('series.index');
    Route::get('/series/create', [AdminBlogSeriesController::class, 'create'])->name('series.create');
    Route::post('/series', [AdminBlogSeriesController::class, 'store'])->name('series.store');
    Route::get('/series/{id}/edit', [AdminBlogSeriesController::class, 'edit'])->name('series.edit');
    Route::put('/series/{id}', [AdminBlogSeriesController::class, 'update'])->name('series.update');
    Route::delete('/series/{id}', [AdminBlogSeriesController::class, 'destroy'])->name('series.destroy');

    // タグ管理
    Route::get('/tags', [AdminBlogTagController::class, 'index'])->name('tags.index');
    Route::post('/tags', [AdminBlogTagController::class, 'store'])->name('tags.store');
    Route::put('/tags/{id}', [AdminBlogTagController::class, 'update'])->name('tags.update');
    Route::delete('/tags/{id}', [AdminBlogTagController::class, 'destroy'])->name('tags.destroy');

    // 画像アップロード（Markdownエディタ用）
    Route::post('/upload-image', [BlogImageController::class, 'store'])->name('upload-image');
});

// --- API（管理画面Ajax用） ---
Route::prefix('api/blog')->name('api.blog.')->group(function () {
    Route::get('/tags/suggest', [ApiBlogTagController::class, 'suggest'])->name('tags.suggest');
    Route::post('/preview', [BlogPreviewController::class, 'store'])->name('preview');
});
