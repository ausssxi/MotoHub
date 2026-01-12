<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BikeController;

/**
 * 中古バイク検索ページ
 */
Route::get('/', [BikeController::class, 'index'])->name('bikes.index');
Route::get('/search', [BikeController::class, 'search'])->name('bikes.search'); 
Route::get('/bikes/{id}', [BikeController::class, 'show'])->name('bikes.show');
