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

    public function alongRoute(Request $request): JsonResponse
    {
        $request->validate([
            'coordinates' => 'required|array|min:2',
            'coordinates.*' => 'array|size:2',
            'coordinates.*.*' => 'numeric',
            'buffer_km' => 'numeric|min:0.1|max:10',
            'types' => 'array',
            'types.*' => 'string',
        ]);

        $coordinates = $request->input('coordinates');
        $bufferKm = (float) ($request->input('buffer_km', 0.3));
        $types = $request->input('types', ['gas_station', 'convenience_store', 'michi_no_eki']);

        // Cache key from coordinate hash
        $cacheKey = 'pois_along:' . md5(json_encode($coordinates)) . ':' . $bufferKm . ':' . implode(',', $types);

        $pois = Cache::remember($cacheKey, 1800, function () use ($coordinates, $bufferKm, $types) {
            // Bounding box from route coordinates + buffer
            $lats = array_column($coordinates, 0);
            $lngs = array_column($coordinates, 1);
            $bufferDeg = $bufferKm / 111.0; // rough km->deg

            $swLat = min($lats) - $bufferDeg;
            $swLng = min($lngs) - $bufferDeg;
            $neLat = max($lats) + $bufferDeg;
            $neLng = max($lngs) + $bufferDeg;

            $candidates = Poi::inBounds($swLat, $swLng, $neLat, $neLng)
                ->ofType($types)
                ->limit(2000)
                ->get(['id', 'osm_id', 'type', 'name', 'latitude', 'longitude', 'address', 'brand', 'opening_hours']);

            // Filter by actual distance to route segments
            $bufferM = $bufferKm * 1000;
            $results = [];

            foreach ($candidates as $poi) {
                $minDist = PHP_FLOAT_MAX;
                for ($i = 0; $i < count($coordinates) - 1; $i++) {
                    $d = $this->pointToSegmentDistance(
                        (float) $poi->latitude,
                        (float) $poi->longitude,
                        $coordinates[$i][0],
                        $coordinates[$i][1],
                        $coordinates[$i + 1][0],
                        $coordinates[$i + 1][1]
                    );
                    if ($d < $minDist) {
                        $minDist = $d;
                    }
                    if ($minDist <= $bufferM) {
                        break; // Already within buffer, no need to check further
                    }
                }

                if ($minDist <= $bufferM) {
                    $results[] = [
                        'poi' => $poi,
                        'distance' => $minDist,
                    ];
                }
            }

            // Sort by distance, limit 500
            usort($results, fn ($a, $b) => $a['distance'] <=> $b['distance']);
            $results = array_slice($results, 0, 500);

            return array_map(fn ($r) => $r['poi'], $results);
        });

        return response()->json($pois);
    }

    /**
     * Distance in meters from a point to the nearest point on a line segment.
     */
    private function pointToSegmentDistance(
        float $pLat, float $pLng,
        float $aLat, float $aLng,
        float $bLat, float $bLng
    ): float {
        $dAB_lat = $bLat - $aLat;
        $dAB_lng = $bLng - $aLng;
        $lenSq = $dAB_lat * $dAB_lat + $dAB_lng * $dAB_lng;

        if ($lenSq < 1e-12) {
            return $this->haversineMeters($pLat, $pLng, $aLat, $aLng);
        }

        $t = (($pLat - $aLat) * $dAB_lat + ($pLng - $aLng) * $dAB_lng) / $lenSq;
        $t = max(0.0, min(1.0, $t));

        $projLat = $aLat + $t * $dAB_lat;
        $projLng = $aLng + $t * $dAB_lng;

        return $this->haversineMeters($pLat, $pLng, $projLat, $projLng);
    }

    private function haversineMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $R = 6371000.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) * sin($dLat / 2)
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
            * sin($dLng / 2) * sin($dLng / 2);

        return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
