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

// Push通知の購読管理（未ログインユーザーもアクセス可能）
Route::post('/push/subscribe', [\App\Http\Controllers\Api\PushSubscriptionController::class, 'subscribe']);
Route::delete('/push/unsubscribe', [\App\Http\Controllers\Api\PushSubscriptionController::class, 'unsubscribe']);
Route::get('/push/subscribed-models', [\App\Http\Controllers\Api\PushSubscriptionController::class, 'subscribedModels']);