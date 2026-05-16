<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RoadsideStation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

final class RoadsideStationApiController extends Controller
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

        $cacheKey = sprintf('roadside_stations:%.3f:%.3f:%.3f:%.3f', $swLat, $swLng, $neLat, $neLng);

        $stations = Cache::remember($cacheKey, 3600, function () use ($swLat, $swLng, $neLat, $neLng) {
            return RoadsideStation::query()
                ->whereBetween('latitude', [$swLat, $neLat])
                ->whereBetween('longitude', [$swLng, $neLng])
                ->limit(300)
                ->get([
                    'id', 'station_code', 'name', 'latitude', 'longitude',
                    'address', 'image_url', 'summary', 'website_url', 'wikipedia_url',
                    'has_restaurant', 'has_onsen', 'has_ev_charging', 'has_wifi',
                    'has_shower', 'has_gas_station', 'has_shop',
                ]);
        });

        return response()->json($stations);
    }
}
