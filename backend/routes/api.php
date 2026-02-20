<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// ログイン中のユーザー情報を取得するAPI（Laravel11初期設定）
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});