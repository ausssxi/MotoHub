<?php

declare(strict_types=1);

namespace App\Http\Controllers\License;

use App\Http\Controllers\Controller;
use App\Models\DrivingSchool;

/**
 * 二輪教習が受けられる指定自動車教習所の一覧（/license/schools）。
 *
 * - 取得は必ず published()（verified_at 非NULL）＋ nirin()（二輪対応）を通す。
 * - 1件も無い都道府県はページを生やさない（index に出さない／show は 404）。
 */
final class LicenseSchoolController extends Controller
{
    /**
     * 都道府県の選択ページ。公開対象がある県だけをカードで出す。
     */
    public function index()
    {
        $prefectures = DrivingSchool::query()
            ->published()
            ->nirin()
            ->selectRaw('prefecture_slug, prefecture, COUNT(*) as count')
            ->groupBy('prefecture_slug', 'prefecture')
            ->orderBy('prefecture_slug')
            ->get();

        return view('license.schools.index', [
            'prefectures' => $prefectures,
        ]);
    }

    /**
     * 都道府県別の教習所一覧。0件なら 404。
     */
    public function show(string $pref)
    {
        $schools = DrivingSchool::query()
            ->published()
            ->nirin()
            ->where('prefecture_slug', $pref)
            ->orderBy('city')
            ->orderBy('name')
            ->get();

        abort_if($schools->isEmpty(), 404);

        return view('license.schools.show', [
            'pref' => $pref,
            'prefecture' => $schools->first()->prefecture,
            'schools' => $schools,
            'sourceUrls' => $schools->pluck('source_url')->filter()->unique()->values(),
            'lastVerified' => $schools->max('verified_at'),
        ]);
    }
}
