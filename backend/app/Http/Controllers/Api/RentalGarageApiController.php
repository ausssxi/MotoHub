<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RentalGarage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

final class RentalGarageApiController extends Controller
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

        $types = $request->input('garage_type')
            ? explode(',', $request->input('garage_type'))
            : [];

        // 3桁丸めでキャッシュキー生成（PoiApiController と同流儀。garage_type も含める）
        $cacheKey = sprintf(
            'rental_garages:%s:%.3f:%.3f:%.3f:%.3f',
            implode(',', $types),
            $swLat, $swLng, $neLat, $neLng
        );

        $garages = Cache::remember($cacheKey, 3600, function () use ($swLat, $swLng, $neLat, $neLng, $types) {
            return RentalGarage::query()
                ->where('is_active', true)
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                // 代表点（市区町村どまり）は地図に出さない。座標がある(=whereNotNull)ことは別途担保。
                // ※ 将来のユーザー投稿は地図上でピンを置く方式で座標が正確なため、投稿時に
                //   geocode_status='ok' を入れる前提（この条件で除外されない）。
                ->where('geocode_status', '!=', 'approximate')
                ->whereBetween('latitude', [$swLat, $neLat])
                ->whereBetween('longitude', [$swLng, $neLng])
                ->when($types, fn ($q) => $q->whereIn('garage_type', $types))
                ->limit(200)
                ->get([
                    'id', 'name', 'operator', 'garage_type', 'latitude', 'longitude', 'address',
                    'monthly_fee_min', 'monthly_fee_max', 'size_text', 'is_24h', 'has_power', 'has_security', 'has_shutter',
                    'website_url', 'source',
                ]);
        });

        return response()->json($garages);
    }
}
