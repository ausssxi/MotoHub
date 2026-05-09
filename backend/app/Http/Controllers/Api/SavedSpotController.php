<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserSavedSpot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SavedSpotController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $neLatitude = (float) $request->query('ne_lat', 0);
        $neLongitude = (float) $request->query('ne_lng', 0);
        $swLatitude = (float) $request->query('sw_lat', 0);
        $swLongitude = (float) $request->query('sw_lng', 0);

        $spots = UserSavedSpot::where('user_id', $request->user()->id)
            ->whereBetween('latitude', [$swLatitude, $neLatitude])
            ->whereBetween('longitude', [$swLongitude, $neLongitude])
            ->select('id', 'name', 'latitude', 'longitude', 'memo', 'category', 'created_at')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn (UserSavedSpot $spot) => [
                'id' => $spot->id,
                'name' => $spot->name,
                'lat' => (float) $spot->latitude,
                'lng' => (float) $spot->longitude,
                'memo' => $spot->memo,
                'category' => $spot->category,
                'created_at' => $spot->created_at->format('Y.m.d'),
            ]);

        return response()->json($spots);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'memo' => 'nullable|string|max:1000',
            'category' => 'nullable|in:touring_spot,rest_area,scenic_point,other',
        ]);

        $spot = UserSavedSpot::create([
            'user_id' => $request->user()->id,
            ...$validated,
        ]);

        return response()->json([
            'id' => $spot->id,
            'name' => $spot->name,
            'lat' => (float) $spot->latitude,
            'lng' => (float) $spot->longitude,
            'memo' => $spot->memo,
            'category' => $spot->category,
            'created_at' => $spot->created_at->format('Y.m.d'),
        ], 201);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $deleted = UserSavedSpot::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->delete();

        if (! $deleted) {
            return response()->json(['message' => 'スポットが見つかりません'], 404);
        }

        return response()->json(['message' => '削除しました']);
    }
}
