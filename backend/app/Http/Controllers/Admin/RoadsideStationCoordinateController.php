<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RoadsideStation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 管理者専用: 座標未設定（latitude/longitude が NULL）の道の駅を地図クリックで埋める画面。
 * ルートは auth のみ。ここで is_admin を検査し、false は 404（既存 ShopSubmissionImageController と同流儀）。
 */
final class RoadsideStationCoordinateController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless((bool) $request->user()?->is_admin, 404);

        $stations = RoadsideStation::query()
            ->where(fn ($q) => $q->whereNull('latitude')->orWhereNull('longitude'))
            ->orderBy('station_code')
            ->get(['id', 'station_code', 'name', 'nickname', 'prefecture', 'city', 'website_url', 'designated_year']);

        $total = RoadsideStation::query()->count();
        $remaining = $stations->count();

        return view('admin.roadside_stations.coordinates', compact('stations', 'total', 'remaining'));
    }

    public function update(Request $request, RoadsideStation $station): JsonResponse
    {
        abort_unless((bool) $request->user()?->is_admin, 404);

        // 日本国内かの粗い検査（桁の打ち間違い・南北の取り違えを弾く）。
        $validated = $request->validate([
            'latitude' => 'required|numeric|between:20,46',
            'longitude' => 'required|numeric|between:122,154',
        ]);

        // address は触らない（NULL のまま）。
        $station->update([
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
        ]);

        $remaining = RoadsideStation::query()
            ->where(fn ($q) => $q->whereNull('latitude')->orWhereNull('longitude'))
            ->count();

        return response()->json([
            'ok' => true,
            'station_code' => $station->station_code,
            'latitude' => (float) $station->latitude,
            'longitude' => (float) $station->longitude,
            'remaining' => $remaining,
        ]);
    }
}
