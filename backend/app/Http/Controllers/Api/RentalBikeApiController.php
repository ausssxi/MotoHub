<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RentalBikeShop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * ライダースマップの「レンタルバイク」レイヤー用。RentalGarageApiController と同型（bbox 検索・1時間キャッシュ）。
 * ★公開は is_active=true のみ。★画像・紹介文・在庫は扱わない（カラムも無い）。
 */
final class RentalBikeApiController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'ne_lat' => 'required|numeric',
            'ne_lng' => 'required|numeric',
            'sw_lat' => 'required|numeric',
            'sw_lng' => 'required|numeric',
        ]);

        $neLat = (float) $request->input('ne_lat');
        $neLng = (float) $request->input('ne_lng');
        $swLat = (float) $request->input('sw_lat');
        $swLng = (float) $request->input('sw_lng');

        // 3桁丸めでキャッシュキー生成（RentalGarageApiController と同流儀）。
        $cacheKey = sprintf('rental_bikes:%.3f:%.3f:%.3f:%.3f', $swLat, $swLng, $neLat, $neLng);

        $shops = Cache::remember($cacheKey, 3600, function () use ($swLat, $swLng, $neLat, $neLng) {
            return RentalBikeShop::query()
                ->where('is_active', true)
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->whereBetween('latitude', [$swLat, $neLat])
                ->whereBetween('longitude', [$swLng, $neLng])
                ->limit(200)
                ->get(['id', 'name', 'company', 'address', 'tel', 'latitude', 'longitude']);
        });

        return response()->json($shops);
    }
}
