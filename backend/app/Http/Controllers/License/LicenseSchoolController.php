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
        // 鮮度（isStale）は tier×現在時刻の判定でモデルにロジックを集約しているため、
        // SQL 集約にせず行を読み込み、県ごとに 掲載校数 と「stale が1件以上あるか」を PHP で畳む。
        $prefectures = DrivingSchool::query()
            ->published()
            ->nirin()
            ->orderBy('prefecture_slug')
            ->get(['prefecture', 'prefecture_slug', 'verified_at'])
            ->groupBy('prefecture_slug')
            ->map(fn ($schools) => (object) [
                'prefecture' => $schools->first()->prefecture,
                'prefecture_slug' => $schools->first()->prefecture_slug,
                'count' => $schools->count(),
                'has_stale' => $schools->contains(fn ($s) => $s->isStale()),
            ])
            ->values();

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
        ]);
    }
}
