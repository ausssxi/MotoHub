<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Poi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

final class PoiApiController extends Controller
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

        $types = $request->input('type')
            ? explode(',', $request->input('type'))
            : ['gas_station', 'convenience_store', 'michi_no_eki'];

        // 3桁丸めでキャッシュキー生成
        $cacheKey = sprintf(
            'pois:%s:%.3f:%.3f:%.3f:%.3f',
            implode(',', $types),
            $swLat, $swLng, $neLat, $neLng
        );

        $pois = Cache::remember($cacheKey, 3600, function () use ($swLat, $swLng, $neLat, $neLng, $types) {
            return Poi::inBounds($swLat, $swLng, $neLat, $neLng)
                ->ofType($types)
                ->limit(200)
                ->get(['id', 'osm_id', 'type', 'name', 'latitude', 'longitude', 'address', 'brand', 'opening_hours']);
        });

        return response()->json($pois);
    }
}
