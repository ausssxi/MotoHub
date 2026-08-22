<?php

declare(strict_types=1);

namespace App\Http\Controllers\License;

use App\Http\Controllers\Controller;
use App\Models\BikeParking;
use App\Models\DrivingSchool;
use App\Models\Listing;
use App\Models\RentalGarage;
use App\Models\Shop;
use App\Models\TouringSpot;
use Illuminate\Support\Facades\Cache;

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

        // 個別掲載していない県は、県協会（一次ソース）の公式サイトへ外部リンクするだけ。
        // 個別県ページ・sitemap は生やさない（config/driving_schools.php の association_links）。
        // 県名は正準マップ（TouringSpot::PREFECTURE_SLUG_MAP）から補い、リンク文言に使う。
        $associationLinks = collect(config('driving_schools.association_links', []))
            ->map(fn ($link, $slug) => (object) [
                'prefecture' => TouringSpot::prefectureNameFromSlug($slug) ?? $slug,
                'name' => $link['name'],
                'url' => $link['url'],
            ])
            ->values();

        return view('license.schools.index', [
            'prefectures' => $prefectures,
            'associationLinks' => $associationLinks,
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

        $prefecture = $schools->first()->prefecture;

        // 同県に在庫/販売店/駐車場ページが存在するか（1回だけ exists 判定）。
        // bikes 系は shops.prefecture を短縮形(都府県サフィックス除去・道は残す)で前方一致。
        $areaLinks = Cache::remember("license.schools.area_links.{$pref}", 3600, function () use ($prefecture) {
            $short = preg_replace('/[都府県]$/u', '', $prefecture);

            return [
                'bikes_short' => $short,
                'shops' => Shop::where('prefecture', $prefecture)->exists(),
                'parking' => BikeParking::active()->byPrefecture($prefecture)->exists(),
                'bikes' => Listing::query()
                    ->join('shops', 'listings.shop_id', '=', 'shops.id')
                    ->where('shops.prefecture', 'like', $short.'%')
                    ->where('listings.is_sold_out', false)
                    ->exists(),
            ];
        });

        // 他県の教習所ページ一覧（回遊用）。素の配列で保持。
        $otherPrefectures = Cache::remember('license.schools.pref_list', 3600, function () {
            return DrivingSchool::query()
                ->published()
                ->nirin()
                ->orderBy('prefecture_slug')
                ->get(['prefecture', 'prefecture_slug'])
                ->groupBy('prefecture_slug')
                ->map(fn ($g) => [
                    'slug' => $g->first()->prefecture_slug,
                    'name' => $g->first()->prefecture,
                    'count' => $g->count(),
                ])
                ->values()
                ->all();
        });

        // 県内の中古バイク相場・排気量帯別台数と、バイク環境（販売店/レンタルガレージ/駐輪場の件数）。
        // まとめた集計クエリ（車種ごと等のループは無し）を素の配列でキャッシュ。数値は取り出す前に (int) 済み。
        // bikes は既存 areaLinks と同じく shops.prefecture の短縮前方一致、店/駐車場/ガレージ件数は正式名で一致。
        $prefStats = Cache::remember("license_pref_stats_v1:{$pref}", 86400, function () use ($prefecture) {
            $short = preg_replace('/[都府県]$/u', '', $prefecture);

            // 価格は total_price（サイト内他ページと統一）。排気量帯は listings.displacement で分類。1クエリ。
            $bike = Listing::query()
                ->join('shops', 'listings.shop_id', '=', 'shops.id')
                ->where('shops.prefecture', 'like', $short.'%')
                ->where('listings.is_sold_out', false)
                ->selectRaw('
                    COUNT(*) as total,
                    MIN(CASE WHEN listings.total_price > 0 THEN listings.total_price END) as min_p,
                    MAX(CASE WHEN listings.total_price > 0 THEN listings.total_price END) as max_p,
                    AVG(CASE WHEN listings.total_price > 0 THEN listings.total_price END) as avg_p,
                    SUM(CASE WHEN listings.total_price > 0 THEN 1 ELSE 0 END) as priced,
                    SUM(CASE WHEN listings.displacement BETWEEN 1 AND 50 THEN 1 ELSE 0 END) as d50,
                    SUM(CASE WHEN listings.displacement BETWEEN 51 AND 125 THEN 1 ELSE 0 END) as d125,
                    SUM(CASE WHEN listings.displacement BETWEEN 126 AND 400 THEN 1 ELSE 0 END) as d400,
                    SUM(CASE WHEN listings.displacement >= 401 THEN 1 ELSE 0 END) as d401
                ')
                ->first();

            return [
                'bike_total' => (int) ($bike->total ?? 0),
                'bike_priced' => (int) ($bike->priced ?? 0),
                'bike_min' => (int) ($bike->min_p ?? 0),
                'bike_max' => (int) ($bike->max_p ?? 0),
                'bike_avg' => (int) round((float) ($bike->avg_p ?? 0)),
                'disp' => [
                    'd50' => (int) ($bike->d50 ?? 0),
                    'd125' => (int) ($bike->d125 ?? 0),
                    'd400' => (int) ($bike->d400 ?? 0),
                    'd401' => (int) ($bike->d401 ?? 0),
                ],
                'shops' => (int) Shop::where('prefecture', $prefecture)->count(),
                'garages' => (int) RentalGarage::where('is_active', true)->where('prefecture', $prefecture)->count(),
                'parkings' => (int) BikeParking::active()->byPrefecture($prefecture)->count(),
            ];
        });

        // 教習所の校数・大型二輪対応校数は、既にロード済みの $schools から算出（追加クエリなし）。説明文/タイトルで使う。
        $schoolTotal = $schools->count();
        $schoolOogata = $schools->where('oogata_nirin', true)->count();

        return view('license.schools.show', [
            'pref' => $pref,
            'prefecture' => $prefecture,
            'schools' => $schools,
            'sourceUrls' => $schools->pluck('source_url')->filter()->unique()->values(),
            'areaLinks' => $areaLinks,
            'otherPrefectures' => $otherPrefectures,
            'prefStats' => $prefStats,
            'schoolTotal' => $schoolTotal,
            'schoolOogata' => $schoolOogata,
        ]);
    }
}
