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

// AR: 近隣ショップ検索API
Route::get('/shops/nearby', function (Request $request) {
    $lat = (float) $request->query('lat');
    $lng = (float) $request->query('lng');
    $radius = (int) $request->query('radius', 500);
    $degree = $radius / 111000;

    $shops = \App\Models\Shop::select('id', 'name', 'latitude', 'longitude', 'address', 'prefecture')
        ->whereNotNull('latitude')
        ->whereNotNull('longitude')
        ->whereBetween('latitude', [$lat - $degree, $lat + $degree])
        ->whereBetween('longitude', [$lng - $degree, $lng + $degree])
        ->limit(20)
        ->get();

    return response()->json($shops);
});

// Push通知の購読管理（未ログインユーザーもアクセス可能）
Route::post('/push/subscribe', [\App\Http\Controllers\Api\PushSubscriptionController::class, 'subscribe']);
Route::delete('/push/unsubscribe', [\App\Http\Controllers\Api\PushSubscriptionController::class, 'unsubscribe']);
Route::get('/push/subscribed-models', [\App\Http\Controllers\Api\PushSubscriptionController::class, 'subscribedModels']);