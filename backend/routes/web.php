<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Bike\ListingSearchController;

/**
 * 中古バイク検索ページ
 */
Route::get('/', [ListingSearchController::class, 'index'])->name('bikes.index');
