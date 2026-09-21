<?php

declare(strict_types=1);

namespace App\Http\Controllers\RentalBike;

use App\Http\Controllers\Controller;
use App\Http\Controllers\RoadsideStation\RoadsideStationController;
use App\Models\RentalBikeShop;
use App\Models\RentalGarage;
use App\Models\Station;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * レンタルバイク店舗の公開ページ（複数事業者を横断して集めた事実情報のみ）。
 *
 * ★画像・紹介文・在庫は扱わない（ウェビックの掲載停止の経緯。今後も扱わない）。
 * ★市区町村ページは作らない（1区1件の薄いページを量産しない・データが増えてから）。
 *
 * 構成はレンタルガレージ（RentalGarageAreaController / RentalGarageController）に合わせる:
 *   - URL は /rental-bikes（全国）/ /rental-bikes/{prefecture}（都道府県別）/ /rental-bikes/{id}（詳細）。
 *   - 都道府県は正準リスト（RoadsideStationController::prefectures()）で検証、24時間キャッシュ。
 *   - 周辺情報は表示時にバウンディングボックス＋ハバーサインで算出（24件・既存ガレージ詳細と同方式）。
 *
 * ★総数（「全24店舗」等）はページに出さない（少なさが目立つため。他社を足したら出す）。
 */
final class RentalBikeController extends Controller
{
    /** 集計・周辺のキャッシュTTL（秒）。レンタルガレージ／poi_area と同じ24時間。 */
    private const CACHE_TTL = 86400;

    /** 周辺検索の半径（km）。詳細ページの内部リンク用。 */
    private const NEARBY_RADIUS_KM = 3.0;

    /** 公開対象（is_active=true のみ）。詳細ページ・一覧・サイトマップで共通。 */
    private function publicScope(): Builder
    {
        return RentalBikeShop::query()->where('is_active', true);
    }

    /**
     * 全国一覧（都道府県ごとにグルーピング）。★地図は出さない（重くなる）。★総数は出さない。
     */
    public function index(): View
    {
        $data = Cache::remember('rental_bike_index_v1', self::CACHE_TTL, function (): array {
            $shops = $this->publicScope()
                ->orderBy('city')
                ->orderBy('name')
                ->get(['id', 'name', 'company', 'prefecture', 'city', 'tel']);

            // 都道府県 → 市区町村順。都道府県は地方区分の正準順で並べる（該当のある県のみ）。
            $grouped = $shops->groupBy(fn (RentalBikeShop $s): string => (string) ($s->prefecture ?? ''));

            $ordered = [];
            foreach (RoadsideStationController::prefectures() as $pref) {
                if ($grouped->has($pref)) {
                    $ordered[$pref] = $this->shopRows($grouped->get($pref));
                }
            }

            return ['byPrefecture' => $ordered];
        });

        return view('rental_bike.index', array_merge($data, [
            'crossLinks' => $this->crossLinks(),
        ]));
    }

    /**
     * 都道府県別（市区町村見出しでグルーピング）。地図あり。該当0件は404。
     */
    public function prefecture(string $prefecture): View
    {
        if (! in_array($prefecture, RoadsideStationController::prefectures(), true)) {
            abort(404);
        }

        $shops = $this->publicScope()
            ->where('prefecture', $prefecture)
            ->orderBy('city')
            ->orderBy('name')
            ->get(['id', 'name', 'company', 'prefecture', 'city', 'tel', 'latitude', 'longitude']);

        if ($shops->isEmpty()) {
            abort(404);
        }

        // 市区町村見出しでグルーピング（五十音の近似として city 文字列順・上の orderBy を踏襲）。
        $byCity = [];
        foreach ($shops->groupBy(fn (RentalBikeShop $s): string => (string) ($s->city ?? '')) as $city => $group) {
            $byCity[(string) $city] = $this->shopRows($group);
        }

        // 地図マーカー（座標のある店舗のみ）。
        $markers = $shops
            ->filter(fn (RentalBikeShop $s): bool => $s->latitude !== null && $s->longitude !== null)
            ->map(fn (RentalBikeShop $s): array => [
                'lat' => (float) $s->latitude,
                'lng' => (float) $s->longitude,
                'name' => (string) $s->name,
                'url' => route('rental-bike.show', $s->id),
            ])
            ->values()
            ->all();

        return view('rental_bike.prefecture', [
            'prefecture' => $prefecture,
            'byCity' => $byCity,
            'markers' => $markers,
            'allPrefectures' => $this->prefecturesWithShops(),
            'crossLinks' => $this->crossLinks(),
        ]);
    }

    /**
     * 店舗詳細（公開）。is_active=false は404。
     */
    public function show(int $id): View
    {
        $shop = $this->publicScope()->where('id', $id)->firstOrFail();

        $lat = (float) $shop->latitude;
        $lng = (float) $shop->longitude;
        $hasCoords = $shop->latitude !== null && $shop->longitude !== null;

        // 周辺（駅・レンタルガレージ）を1つのキャッシュにまとめる（24時間）。座標が無ければ空。
        $nearby = $hasCoords
            ? Cache::remember("rental_bike_nearby_v1:{$shop->id}", self::CACHE_TTL, fn (): array => [
                'stations' => $this->nearby(Station::query(), $lat, $lng, 3),
                'garages' => $this->nearby(
                    RentalGarage::query()->where('is_active', true), $lat, $lng, 5
                ),
            ])
            : ['stations' => new Collection, 'garages' => new Collection];

        $nearbyStations = $nearby['stations'];
        $nearbyGarages = $nearby['garages'];

        return view('rental_bike.show', compact(
            'shop', 'nearbyStations', 'nearbyGarages'
        ) + ['crossLinks' => $this->crossLinks()]);
    }

    /**
     * 一覧行（店舗名 / 事業者名 / 市区町村 / 電話〔あれば〕）。電話が無ければ null（＝ビューで非表示）。
     *
     * @param  Collection<int, RentalBikeShop>  $shops
     * @return array<int, array{id: int, name: string, company: string, city: ?string, tel: ?string}>
     */
    private function shopRows(Collection $shops): array
    {
        return $shops->map(fn (RentalBikeShop $s): array => [
            'id' => (int) $s->id,
            'name' => (string) $s->name,
            'company' => (string) $s->company,
            'city' => filled($s->city) ? (string) $s->city : null,
            'tel' => filled($s->tel) ? (string) $s->tel : null,
        ])->all();
    }

    /**
     * 与えたクエリから半径内のレコードを近い順に最大 $limit 件返す（バウンディングボックス＋ハバーサイン）。
     * レンタルガレージ詳細（RentalGarageController::nearby）と同式・同半径。dist_m を各行に付与する。
     *
     * @return Collection<int, \Illuminate\Database\Eloquent\Model>
     */
    private function nearby(Builder $query, float $lat, float $lng, int $limit): Collection
    {
        $latDelta = self::NEARBY_RADIUS_KM / 111.0;
        $lngDelta = self::NEARBY_RADIUS_KM / (111.0 * max(cos(deg2rad($lat)), 0.01));

        return $query
            ->whereNotNull('latitude')->whereNotNull('longitude')
            ->whereBetween('latitude', [$lat - $latDelta, $lat + $latDelta])
            ->whereBetween('longitude', [$lng - $lngDelta, $lng + $lngDelta])
            ->limit(60)
            ->get()
            ->each(fn ($r) => $r->setAttribute('dist_m', $this->haversineMeters($lat, $lng, (float) $r->latitude, (float) $r->longitude)))
            ->filter(fn ($r) => $r->dist_m <= self::NEARBY_RADIUS_KM * 1000)
            ->sortBy('dist_m')
            ->take($limit)
            ->values();
    }

    private function haversineMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * 店舗のある都道府県 [prefecture => count]（地方区分順・「他の都道府県から探す」用）。
     *
     * @return array<int, array{prefecture: string, count: int}>
     */
    private function prefecturesWithShops(): array
    {
        $counts = Cache::remember('rental_bike_pref_counts_v1', self::CACHE_TTL, fn (): array => $this->publicScope()
            ->whereNotNull('prefecture')
            ->where('prefecture', '!=', '')
            ->selectRaw('prefecture, COUNT(*) as cnt')
            ->groupBy('prefecture')
            ->pluck('cnt', 'prefecture')
            ->map(fn ($v) => (int) $v)
            ->all());

        $out = [];
        foreach (RoadsideStationController::prefectures() as $pref) {
            if (isset($counts[$pref])) {
                $out[] = ['prefecture' => $pref, 'count' => $counts[$pref]];
            }
        }

        return $out;
    }

    /**
     * 回遊リンク（レンタルガレージの詳細/一覧と同系。レンタルガレージへの相互リンクを含む）。
     *
     * @return array<int, array{label: string, url: string, icon: string, description: string}>
     */
    private function crossLinks(): array
    {
        return [
            ['label' => 'レンタルガレージ', 'url' => route('rental-garage.area.index'), 'icon' => 'warehouse', 'description' => 'バイクの保管場所を探す'],
            ['label' => 'ライダーズマップ', 'url' => route('riders.map'), 'icon' => 'map', 'description' => 'ガレージ・洗車場・GSを地図で'],
            ['label' => '駐車場マップ', 'url' => route('parking.index'), 'icon' => 'square-parking', 'description' => 'バイク駐車場を探す'],
            ['label' => '中古バイク検索', 'url' => route('bikes.search'), 'icon' => 'search', 'description' => '全国の在庫を検索'],
        ];
    }
}
