<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Poi;
use App\Models\RoadsideStation;
use App\Models\TouringGuide;
use App\Models\TouringSpot;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

/**
 * ツーリングガイドの中心座標から、ルート周辺の立ち寄り先を集める。
 *
 * ガイドは9本しかなく施設もめったに変わらないので 24h キャッシュ（キーに世代 v1）。
 * ビュー/コントローラに空間クエリを書かせない（同じ計算をスポット側から再利用予定）。
 *
 * 距離判定は「矩形で粗く絞る（SQL・index利用）→ 正確な距離は PHP の Haversine」。
 * ST_Distance_Sphere を SQL に直書きしないのは、SQLite（テスト）に無く全件スキャンも避けるため。
 *
 * ★ 座標カラムに注意: touring_spots=lat/lng、roadside_stations/pois=latitude/longitude。
 */
final class TouringNearby
{
    /** 半径 = distance_km / DIVISOR、[MIN, MAX] にクランプ。distance_km 不明時は DEFAULT。 */
    private const RADIUS_DIVISOR = 5;

    private const RADIUS_MIN_KM = 12.0;

    private const RADIUS_MAX_KM = 45.0;

    private const RADIUS_DEFAULT_KM = 20.0;

    private const LIMIT_SPOT = 4;

    private const LIMIT_STATION = 5;

    private const LIMIT_GAS = 6;

    private const LIMIT_CAR_WASH = 3;

    private const CACHE_TTL = 86400;

    /** roadside_stations の設備フラグ → バッジ表示名（true のものだけ・最大4）。 */
    private const STATION_BADGES = [
        'has_onsen' => '温泉',
        'has_camp' => 'キャンプ',
        'has_ev_charging' => 'EV充電',
        'has_shower' => 'シャワー',
        'has_observatory' => '展望台',
        'has_restaurant' => '食事',
    ];

    private PoiDisplayResolver $resolver;

    public function __construct(?PoiDisplayResolver $resolver = null)
    {
        $this->resolver = $resolver ?? new PoiDisplayResolver;
    }

    /** distance_km → 検索半径 km。 */
    public static function radiusKm(?int $distanceKm): float
    {
        if ($distanceKm === null || $distanceKm <= 0) {
            return self::RADIUS_DEFAULT_KM;
        }

        $r = (float) $distanceKm / self::RADIUS_DIVISOR;

        return max(self::RADIUS_MIN_KM, min(self::RADIUS_MAX_KM, $r));
    }

    /**
     * ガイド周辺の立ち寄り先。0件の種別は空（呼び出し側でブロックごと非表示）。
     *
     * @return array{
     *   spots: array<int, array<string,mixed>>,
     *   stations: array<int, array<string,mixed>>,
     *   gas: array{count:int, sparse:bool, items:array<int, array<string,mixed>>},
     *   car_wash: array<int, array<string,mixed>>
     * }
     */
    public function forGuide(TouringGuide $guide): array
    {
        return Cache::remember("touring:nearby:v1:{$guide->id}", self::CACHE_TTL, function () use ($guide): array {
            $lat = (float) $guide->latitude;
            $lng = (float) $guide->longitude;
            $radius = self::radiusKm($guide->distance_km !== null ? (int) $guide->distance_km : null);
            $pref = (string) $guide->prefecture;

            return [
                'spots' => $this->spots($lat, $lng, $radius, $pref),
                'stations' => $this->stations($lat, $lng, $radius, $pref),
                'gas' => $this->gas($lat, $lng, $radius, $pref),
                'car_wash' => $this->carWash($lat, $lng, $radius, $pref),
            ];
        });
    }

    /** @return array<int, array<string,mixed>> */
    private function spots(float $lat, float $lng, float $radius, string $guidePref): array
    {
        $query = TouringSpot::query()->select('id', 'name', 'prefecture', 'slug', 'lat', 'lng', 'image_url');
        $this->rectangle($query, 'lat', 'lng', $lat, $lng, $radius);

        $rows = $this->rankByDistance($query->get(), 'lat', 'lng', $lat, $lng, $radius, $guidePref, self::LIMIT_SPOT);

        $items = [];
        foreach ($rows as [$spot, $dist]) {
            $name = trim((string) $spot->name);
            if ($name === '') {
                continue; // 名前が無いものはリンクにしない
            }
            $prefSlug = TouringSpot::slugFromPrefectureName((string) $spot->prefecture);
            $url = ($prefSlug !== null && filled($spot->slug))
                ? route('touring.spot.show', ['prefectureSlug' => $prefSlug, 'spot' => $spot->slug])
                : null;

            $items[] = [
                'name' => $name,
                'prefecture' => (string) $spot->prefecture,
                'distance_km' => $this->roundKm($dist),
                'image_url' => filled($spot->image_url) ? (string) $spot->image_url : null,
                'url' => $url,
            ];
        }

        return $items;
    }

    /** @return array<int, array<string,mixed>> */
    private function stations(float $lat, float $lng, float $radius, string $guidePref): array
    {
        $query = RoadsideStation::query()
            ->select(array_merge(
                ['id', 'station_code', 'name', 'nickname', 'prefecture', 'city', 'latitude', 'longitude'],
                array_keys(self::STATION_BADGES),
            ))
            ->whereNotNull('latitude')
            ->whereNotNull('longitude');
        $this->rectangle($query, 'latitude', 'longitude', $lat, $lng, $radius);

        $rows = $this->rankByDistance($query->get(), 'latitude', 'longitude', $lat, $lng, $radius, $guidePref, self::LIMIT_STATION);

        $items = [];
        foreach ($rows as [$station, $dist]) {
            $name = trim((string) $station->name);
            if ($name === '') {
                continue;
            }

            $badges = [];
            foreach (self::STATION_BADGES as $col => $label) {
                if ($station->{$col}) {
                    $badges[] = $label;
                }
                if (count($badges) >= 4) {
                    break;
                }
            }

            $items[] = [
                'name' => $name,
                'nickname' => $this->stationNickname($station->nickname, $name),
                'city' => (string) $station->city,
                'distance_km' => $this->roundKm($dist),
                'badges' => $badges,
                'url' => route('michinoeki.show', ['station_code' => $station->station_code]),
            ];
        }

        return $items;
    }

    /**
     * @return array{count:int, sparse:bool, items:array<int, array<string,mixed>>}
     */
    private function gas(float $lat, float $lng, float $radius, string $guidePref): array
    {
        $ranked = $this->pois('gas_station', $lat, $lng, $radius, $guidePref);
        $count = count($ranked);
        $threshold = (int) config('touring.gas_sparse_threshold', 40);
        $sparse = $count < $threshold;

        // 少ないルートのときだけリストを出す（多いルートは件数のみ＝地図リンクに委ねる）。
        $items = $sparse
            ? $this->mapPoiItems(array_slice($ranked, 0, self::LIMIT_GAS), 'gas_station', 'gs.short')
            : [];

        return ['count' => $count, 'sparse' => $sparse, 'items' => $items];
    }

    /** @return array<int, array<string,mixed>> */
    private function carWash(float $lat, float $lng, float $radius, string $guidePref): array
    {
        $ranked = array_slice($this->pois('car_wash', $lat, $lng, $radius, $guidePref), 0, self::LIMIT_CAR_WASH);

        return $this->mapPoiItems($ranked, 'car_wash', 'senshajo.short');
    }

    /**
     * pois を種別で矩形→距離ランキングして返す（距離降順ではなく同県優先＋距離昇順）。
     *
     * @return array<int, array{0: Poi, 1: float}>
     */
    private function pois(string $type, float $lat, float $lng, float $radius, string $guidePref): array
    {
        $query = Poi::query()
            ->where('type', $type)
            ->select('id', 'type', 'name', 'brand', 'address', 'prefecture', 'city', 'latitude', 'longitude', 'self_service', 'automated');
        $this->rectangle($query, 'latitude', 'longitude', $lat, $lng, $radius);

        // 件数（GSの分岐）に使うので上限なしでランキングする。
        return $this->rankByDistance($query->get(), 'latitude', 'longitude', $lat, $lng, $radius, $guidePref, null);
    }

    /**
     * @param  array<int, array{0: Poi, 1: float}>  $ranked
     * @return array<int, array<string,mixed>>
     */
    private function mapPoiItems(array $ranked, string $type, string $routeName): array
    {
        $items = [];
        foreach ($ranked as [$poi, $dist]) {
            // ★表示名は PoiDisplayResolver 経由（地図/詳細ページと一致）。ビューで name 直読みしない。
            $display = trim($this->resolver->resolve($poi, $type, (string) $poi->prefecture, (string) $poi->city));
            if ($display === '' || $display === '名称不明') {
                continue; // 名前が解決できないものはリストに出さない
            }

            $items[] = [
                'display' => $display,
                'city' => (string) $poi->city,
                'distance_km' => $this->roundKm($dist),
                'url' => route($routeName, ['id' => $poi->id]),
            ];
        }

        return $items;
    }

    /**
     * 矩形で粗く絞る。緯度幅=R/111、経度幅=R/(111*cosφ)。
     */
    private function rectangle(Builder $query, string $latCol, string $lngCol, float $lat, float $lng, float $radiusKm): void
    {
        $dLat = $radiusKm / 111.0;
        $cos = cos(deg2rad($lat));
        if (abs($cos) < 1e-6) {
            $cos = 1e-6; // 極付近のゼロ割回避（実データでは通らない）
        }
        $dLng = $radiusKm / (111.0 * abs($cos));

        $query->whereBetween($latCol, [$lat - $dLat, $lat + $dLat])
            ->whereBetween($lngCol, [$lng - $dLng, $lng + $dLng]);
    }

    /**
     * 候補を正確な距離（Haversine）で絞り、同県優先→距離昇順で並べ、必要なら上限で切る。
     *
     * @param  iterable<int, object>  $rows
     * @return array<int, array{0: object, 1: float}>
     */
    private function rankByDistance(iterable $rows, string $latCol, string $lngCol, float $lat, float $lng, float $radiusKm, string $guidePref, ?int $limit): array
    {
        $ranked = [];
        foreach ($rows as $row) {
            $d = $this->haversineKm($lat, $lng, (float) $row->{$latCol}, (float) $row->{$lngCol});
            if ($d <= $radiusKm) {
                $ranked[] = [$row, $d];
            }
        }

        usort($ranked, function (array $a, array $b) use ($guidePref): int {
            $sa = ((string) $a[0]->prefecture === $guidePref) ? 1 : 0;
            $sb = ((string) $b[0]->prefecture === $guidePref) ? 1 : 0;
            if ($sa !== $sb) {
                return $sb <=> $sa; // 同県を先に
            }

            return $a[1] <=> $b[1]; // 距離昇順
        });

        return $limit !== null ? array_slice($ranked, 0, $limit) : $ranked;
    }

    /** nickname（'|' 区切り）から name と別物の先頭要素を1つ返す。無ければ null。 */
    private function stationNickname(?string $nickname, string $name): ?string
    {
        if (! filled($nickname)) {
            return null;
        }

        foreach (array_filter(array_map('trim', explode('|', $nickname))) as $nick) {
            if (! RoadsideStation::nicknameMatchesName($nick, $name)) {
                return $nick;
            }
        }

        return null;
    }

    private function roundKm(float $km): int
    {
        return max(1, (int) round($km));
    }

    private function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
