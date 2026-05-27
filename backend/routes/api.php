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

// クイズ問題API
Route::get('/quiz/questions', [\App\Http\Controllers\Api\QuizController::class, 'questions']);

// ツーリングルート提案API
Route::post('/touring/suggest', [\App\Http\Controllers\Api\TouringPlannerController::class, 'suggest']);

// AIスマート検索API
Route::post('/ai-search', [\App\Http\Controllers\Api\AiSearchController::class, 'search'])->middleware('throttle:ai-search');

// ブログ記事・ツーリングガイド マップピンAPI
Route::get('/blog/map-pins', function (Request $request) {
    // map.jsは ne_lat/ne_lng/sw_lat/sw_lng を送信、手動テスト用に north/south/east/west もサポート
    $neLatitude = (float) ($request->query('ne_lat') ?: $request->query('north', 0));
    $neLongitude = (float) ($request->query('ne_lng') ?: $request->query('east', 0));
    $swLatitude = (float) ($request->query('sw_lat') ?: $request->query('south', 0));
    $swLongitude = (float) ($request->query('sw_lng') ?: $request->query('west', 0));

    $posts = \App\Models\BlogPost::published()
        ->whereNotNull('latitude')
        ->whereNotNull('longitude')
        ->whereBetween('latitude', [$swLatitude, $neLatitude])
        ->whereBetween('longitude', [$swLongitude, $neLongitude])
        ->select('id', 'title', 'slug', 'latitude', 'longitude', 'excerpt', 'published_at')
        ->orderByDesc('published_at')
        ->limit(50)
        ->get()
        ->map(fn ($post) => [
            'id' => $post->id,
            'type' => 'blog',
            'title' => $post->title,
            'slug' => $post->slug,
            'lat' => (float) $post->latitude,
            'lng' => (float) $post->longitude,
            'excerpt' => \Illuminate\Support\Str::limit(
                \App\Models\BlogPost::stripMarkdown($post->excerpt ?? ''),
                80
            ),
            'published_at' => $post->published_at->format('Y.m.d'),
        ]);

    $guides = \App\Models\TouringGuide::published()
        ->whereNotNull('latitude')
        ->whereNotNull('longitude')
        ->whereBetween('latitude', [$swLatitude, $neLatitude])
        ->whereBetween('longitude', [$swLongitude, $neLongitude])
        ->select('id', 'title', 'slug', 'latitude', 'longitude', 'excerpt', 'published_at')
        ->orderByDesc('published_at')
        ->limit(50)
        ->get()
        ->map(fn ($guide) => [
            'id' => $guide->id,
            'type' => 'touring',
            'title' => $guide->title,
            'slug' => $guide->slug,
            'lat' => (float) $guide->latitude,
            'lng' => (float) $guide->longitude,
            'excerpt' => \Illuminate\Support\Str::limit(
                \App\Models\BlogPost::stripMarkdown($guide->excerpt ?? ''),
                80
            ),
            'published_at' => $guide->published_at->format('Y.m.d'),
        ]);

    $merged = $posts->concat($guides)->sortByDesc('published_at')->take(50)->values();

    return response()->json($merged);
});