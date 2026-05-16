<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\TouringSpot;

final class TouringSpotController extends Controller
{
    public function index(string $prefectureSlug)
    {
        $prefName = TouringSpot::prefectureNameFromSlug($prefectureSlug);

        if (!$prefName) {
            abort(404);
        }

        $spots = TouringSpot::byPrefectureSlug($prefectureSlug)
            ->orderBy('name')
            ->get();

        $allPrefectures = TouringSpot::select('prefecture')
            ->distinct()
            ->orderBy('prefecture')
            ->pluck('prefecture')
            ->map(fn (string $name) => [
                'name' => $name,
                'slug' => TouringSpot::slugFromPrefectureName($name),
            ]);

        return view('touring.spot-index', compact('spots', 'prefName', 'prefectureSlug', 'allPrefectures'));
    }

    public function show(string $prefectureSlug, TouringSpot $spot)
    {
        $prefName = TouringSpot::prefectureNameFromSlug($prefectureSlug);

        if (!$prefName || $spot->prefecture !== $prefName) {
            abort(404);
        }

        $relatedSpots = TouringSpot::where('prefecture', $prefName)
            ->where('id', '!=', $spot->id)
            ->limit(4)
            ->get();

        return view('touring.spot-show', compact('spot', 'prefName', 'prefectureSlug', 'relatedSpots'));
    }
}
