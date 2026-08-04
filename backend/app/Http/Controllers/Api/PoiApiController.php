<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Poi;
use App\Models\RoadsideStation;
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

        // 道の駅は roadside_stations 由来（/api/roadside-stations）へ一本化したため既定から除外。
        // type を明示指定された場合はそのまま尊重する。
        $types = $request->input('type')
            ? explode(',', $request->input('type'))
            : ['gas_station', 'convenience_store'];

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

        return response()->json($this->withPoiBrands($pois));
    }

    /**
     * GS(gas_brand/gas_operator)・コンビニ(cvs_brand/cvs_operator)に運営ブランドを注入し、
     * 'exclude'（非GS誤ラベル/閉店タグ）はペイロードから落とす。
     *
     * ★キャッシュ「取得後」に適用＝デプロイ直後から即反映（1hキャッシュの再生成待ちが不要）。
     *   対象外のtype（道の駅等）はそのまま素通し。
     */
    private function withPoiBrands(\Illuminate\Support\Collection|\Illuminate\Database\Eloquent\Collection $pois): \Illuminate\Support\Collection
    {
        return $pois->map(function (Poi $poi) {
            if ($poi->type === 'gas_station') {
                $brand = Poi::gasBrand($poi->brand);
                if ($brand === 'exclude') {
                    return null; // 非GS誤ラベルは地図に出さない
                }
                $poi->setAttribute('gas_brand', $brand);
                $poi->setAttribute('gas_operator', Poi::gasOperatorLabel($poi->brand, $brand));
            } elseif ($poi->type === 'convenience_store') {
                $brand = Poi::cvsBrand($poi->brand);
                if ($brand === 'exclude') {
                    return null; // 閉店タグ(disused:)は地図に出さない
                }
                $poi->setAttribute('cvs_brand', $brand);
                $poi->setAttribute('cvs_operator', Poi::cvsOperatorLabel($poi->brand, $brand));
            }

            return $poi;
        })->filter()->values();
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
        // 道の駅は roadside_stations 由来へ一本化。既定から除外し、明示指定時のみ下で roadside から取得。
        $types = $request->input('types', ['gas_station', 'convenience_store']);

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

            // 道の駅(michi_no_eki)は pois ではなく roadside_stations から取得。他種別は従来どおり pois。
            $wantsMichi = in_array('michi_no_eki', $types, true);
            $poiTypes = array_values(array_filter($types, fn ($t) => $t !== 'michi_no_eki'));

            $candidates = collect();

            if ($poiTypes !== []) {
                $candidates = $candidates->merge(
                    Poi::inBounds($swLat, $swLng, $neLat, $neLng)
                        ->ofType($poiTypes)
                        ->limit(2000)
                        ->get(['id', 'osm_id', 'type', 'name', 'latitude', 'longitude', 'address', 'brand', 'opening_hours'])
                );
            }

            if ($wantsMichi) {
                // 座標NULLの駅は必ず除外。pois とキー名/型を揃える（latitude/longitude は float）。
                $stations = RoadsideStation::query()
                    ->whereNotNull('latitude')
                    ->whereNotNull('longitude')
                    ->whereBetween('latitude', [$swLat, $neLat])
                    ->whereBetween('longitude', [$swLng, $neLng])
                    ->limit(2000)
                    ->get(['id', 'name', 'latitude', 'longitude', 'address']);

                $candidates = $candidates->merge(
                    $stations->map(fn (RoadsideStation $s) => (new Poi())->forceFill([
                        'id' => $s->id,
                        'type' => 'michi_no_eki',
                        'name' => $s->name,
                        'latitude' => (float) $s->latitude,   // roadside は decimal:7=文字列 → float に統一
                        'longitude' => (float) $s->longitude,
                        'address' => $s->address,
                    ]))
                );
            }

            // ルート沿い判定は pois/道の駅で共通の1実装を使う（判定を2重実装しない・上限500/距離昇順も共通）。
            return $this->filterAlongRoute($candidates, $coordinates, $bufferKm);
        });

        return response()->json($this->withPoiBrands(collect($pois)));
    }

    /**
     * ルート座標列との最短距離が buffer 内の候補だけを残し、距離昇順で最大500件返す。
     * pois・道の駅（Poiインスタンスへ整形済み）どちらの候補にも共通で使う。
     *
     * @param  \Illuminate\Support\Collection<int, Poi>  $candidates
     * @return array<int, Poi>
     */
    private function filterAlongRoute($candidates, array $coordinates, float $bufferKm): array
    {
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
