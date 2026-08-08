<?php

declare(strict_types=1);

namespace App\Http\Controllers\Poi;

use App\Http\Controllers\Controller;
use App\Models\Poi;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * コンビニ・GS の「市区町村まとめ」ページ（公開・認証なし）。
 *
 * gas_station / convenience_store の2種別を1コントローラで扱う。種別はルート defaults('type', ...)
 * で渡され、ビューへは $routePrefix（'gs' か 'konbini'）を渡して route($routePrefix.'.city', ...)
 * のようにURLを組む（ハードコード禁止）。作法は RoadsideStationController / roadside_station ビューに合わせる。
 *
 * pois.prefecture / pois.city は AssignPoiMunicipality で N03 行政区域から付与済み
 * （prefecture=都道府県フルネーム、city=municipalities.full_name）。
 */
final class PoiAreaController extends Controller
{
    /**
     * 種別ごとのメタ（ルート接頭辞・表示ラベル・もう一方の種別）。
     * gas_station は name も brand も無い行が2,200件あるため city ページは address で代替表示する。
     */
    private const TYPES = [
        'gas_station' => ['prefix' => 'gs', 'label' => 'ガソリンスタンド', 'other' => 'convenience_store'],
        'convenience_store' => ['prefix' => 'konbini', 'label' => 'コンビニ', 'other' => 'gas_station'],
        'car_wash' => ['prefix' => 'senshajo', 'label' => '洗車場', 'other' => 'gas_station'],
    ];

    /**
     * 8地方区分 → 所属都道府県（フルネーム＝pois.prefecture と一致）。
     * RoadsideStationController::REGIONS と同一の割り当て・表示順に合わせる。
     */
    private const REGIONS = [
        '北海道' => ['北海道'],
        '東北' => ['青森県', '岩手県', '宮城県', '秋田県', '山形県', '福島県'],
        '関東' => ['茨城県', '栃木県', '群馬県', '埼玉県', '千葉県', '東京都', '神奈川県'],
        '中部' => ['新潟県', '富山県', '石川県', '福井県', '山梨県', '長野県', '岐阜県', '静岡県', '愛知県'],
        '近畿' => ['三重県', '滋賀県', '京都府', '大阪府', '兵庫県', '奈良県', '和歌山県'],
        '中国' => ['鳥取県', '島根県', '岡山県', '広島県', '山口県'],
        '四国' => ['徳島県', '香川県', '愛媛県', '高知県'],
        '九州・沖縄' => ['福岡県', '佐賀県', '長崎県', '熊本県', '大分県', '宮崎県', '鹿児島県', '沖縄県'],
    ];

    /**
     * 全都道府県（フルネーム）の正準リスト。未知地名を弾くホワイトリストに使う。
     *
     * @return array<int, string>
     */
    private static function prefectures(): array
    {
        return array_merge(...array_values(self::REGIONS));
    }

    /**
     * 一覧ページ（都道府県ごとの件数と各都道府県へのリンク）。
     */
    public function index(Request $request): View
    {
        $type = (string) $request->route('type');
        $meta = self::TYPES[$type] ?? abort(404);

        $data = Cache::remember("poi_area_index:{$type}", 86400, function () use ($type) {
            $counts = $this->prefectureCounts($type);

            $total = 0;
            $regions = [];
            foreach (self::REGIONS as $region => $prefs) {
                $items = [];
                foreach ($prefs as $pref) {
                    $c = $counts[$pref] ?? 0;
                    $total += $c;
                    $items[] = ['prefecture' => $pref, 'count' => $c];
                }
                $regions[$region] = $items;
            }

            return ['total' => $total, 'regions' => $regions];
        });

        return view('poi_area.index', array_merge($data, [
            'routePrefix' => $meta['prefix'],
            'label' => $meta['label'],
            'crossLinks' => $this->listingCrossLinks(),
        ]));
    }

    /**
     * 都道府県別（市区町村を件数付きで一覧・full_name 昇順・0件は載せない）。該当0件は404。
     */
    public function prefecture(Request $request, string $prefecture): View
    {
        $type = (string) $request->route('type');
        $meta = self::TYPES[$type] ?? abort(404);

        if (! in_array($prefecture, self::prefectures(), true)) {
            abort(404);
        }

        $data = Cache::remember("poi_area_pref:{$type}:{$prefecture}", 86400, function () use ($type, $prefecture) {
            $cities = Poi::query()
                ->where('type', $type)
                ->where('prefecture', $prefecture)
                ->whereNotNull('city')
                ->where('city', '!=', '')
                ->selectRaw('city, COUNT(*) as cnt')
                ->groupBy('city')
                ->orderBy('city')
                ->get()
                ->map(fn ($r): array => ['city' => (string) $r->city, 'count' => (int) $r->cnt])
                ->all();

            if ($cities === []) {
                return null; // 0件 → 404
            }

            $count = array_sum(array_column($cities, 'count'));

            return ['prefecture' => $prefecture, 'count' => $count, 'cities' => $cities];
        });

        if ($data === null) {
            abort(404);
        }

        return view('poi_area.prefecture', array_merge($data, [
            'routePrefix' => $meta['prefix'],
            'label' => $meta['label'],
            'allPrefectures' => $this->allPrefecturesWithCounts($type),
            'crossLinks' => $this->listingCrossLinks(),
        ]));
    }

    /**
     * 市区町村別 POI 一覧。該当0件は404。
     * 表示名は name → brand → address → 「名称不明」の優先順。並びは brand, name, id 昇順で安定化。
     */
    public function city(Request $request, string $prefecture, string $city): View
    {
        $type = (string) $request->route('type');
        $meta = self::TYPES[$type] ?? abort(404);

        if (! in_array($prefecture, self::prefectures(), true)) {
            abort(404);
        }

        $pois = Poi::query()
            ->where('type', $type)
            ->where('prefecture', $prefecture)
            ->where('city', $city)
            // 名称もブランドも両方空の行（GSで2,200件）は末尾へ回してから brand, name, id 昇順。
            ->orderByRaw("(COALESCE(NULLIF(name, ''), NULLIF(brand, '')) IS NULL)")
            ->orderBy('brand')
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name', 'brand', 'address', 'opening_hours', 'latitude', 'longitude', 'municipality_code']);

        if ($pois->isEmpty()) {
            abort(404);
        }

        $items = $pois->map(function (Poi $p): array {
            $display = $this->displayName($p);
            $brand = filled($p->brand) ? (string) $p->brand : null;

            return [
                'display' => $display,
                // 表示名（name→brand→address）と同一のブランドは重複行になるので出さない。
                'brand' => ($brand !== null && $brand !== $display) ? $brand : null,
                'address' => filled($p->address) ? (string) $p->address : null,
                'opening_hours' => filled($p->opening_hours) ? (string) $p->opening_hours : null,
            ];
        })->all();

        $other = self::TYPES[$meta['other']];

        // もう一方の種別が同じ市区町村に何件あるか。0件なら相互リンクを出さない（404回避）。
        $otherCount = Poi::query()
            ->where('type', $meta['other'])
            ->where('prefecture', $prefecture)
            ->where('city', $city)
            ->count();

        return view('poi_area.city', [
            'routePrefix' => $meta['prefix'],
            'label' => $meta['label'],
            'prefecture' => $prefecture,
            'city' => $city,
            'count' => $pois->count(),
            'items' => $items,
            // 掲載10件未満のページだけ、近隣市区町村の同種別POIを最寄り5件補足する。
            'nearby' => $this->nearbyPois($pois, $type),
            // 同じ市区町村の「もう一方の種別」ページへのリンク（存在するときだけ出す）。
            'otherPrefix' => $other['prefix'],
            'otherLabel' => $other['label'],
            'otherCount' => $otherCount,
            'crossLinks' => $this->listingCrossLinks(),
        ]);
    }

    /**
     * 近隣の市区町村にある同種別POIの最寄り最大5件。掲載10件以上のページや、半径内に無い離島では空配列。
     *
     * 基準点はページ掲載POIの緯度経度の平均。±2.0度で粗く絞ってから ST_Distance_Sphere（メートル）で
     * 実距離順に並べる。SRID 0 の POINT は「経度・緯度」の順（POINT(longitude, latitude)）。
     * リンクが壊れないよう、都道府県・市区町村・市区町村コードが揃った行のみ対象にする。
     *
     * @param  \Illuminate\Support\Collection<int, Poi>  $pagePois  当該ページの掲載POI
     * @return array<int, array{prefecture: string, city: string, display: string, km: float}>
     */
    private function nearbyPois($pagePois, string $type): array
    {
        if ($pagePois->count() >= 10) {
            return [];
        }

        $avgLat = (float) $pagePois->avg('latitude');
        $avgLng = (float) $pagePois->avg('longitude');

        // 当該市区町村（コード）を除外。full_name 重複に備え、ページ上の全コードを除く。
        $excludeCodes = $pagePois->pluck('municipality_code')->filter()->unique()->values()->all();

        return Poi::query()
            ->where('type', $type)
            ->whereNotNull('municipality_code')
            ->whereNotNull('prefecture')->where('prefecture', '!=', '')
            ->whereNotNull('city')->where('city', '!=', '')
            ->when($excludeCodes !== [], fn ($q) => $q->whereNotIn('municipality_code', $excludeCodes))
            // ±2.0度で粗く矩形絞り（インデックスを効かせる前段）。
            ->whereBetween('latitude', [$avgLat - 2.0, $avgLat + 2.0])
            ->whereBetween('longitude', [$avgLng - 2.0, $avgLng + 2.0])
            ->selectRaw(
                'id, name, brand, address, prefecture, city, '
                .'ST_Distance_Sphere(POINT(longitude, latitude), POINT(?, ?)) AS dist_m',
                [$avgLng, $avgLat]
            )
            ->orderBy('dist_m')
            ->limit(5)
            ->get()
            ->map(fn (Poi $p): array => [
                'prefecture' => (string) $p->prefecture,
                'city' => (string) $p->city,
                'display' => $this->displayName($p),
                'km' => round(((float) $p->dist_m) / 1000, 1),
            ])
            ->all();
    }

    /**
     * 表示名フォールバック: name → brand → address → 「名称不明」。
     *
     * ※ Poi::getDisplayNameAttribute は name→brand→種別ラベルで address を出さないため、
     *   address 代替（名称もブランドも無いGS 2,200件）を要件どおり満たすここで独自に組む。
     */
    private function displayName(Poi $poi): string
    {
        foreach ([$poi->name, $poi->brand, $poi->address] as $candidate) {
            $v = trim((string) ($candidate ?? ''));
            if ($v !== '') {
                return $v;
            }
        }

        return '名称不明';
    }

    /**
     * 都道府県ごとの件数 [prefecture => count]（1クエリ集計・24時間キャッシュ）。
     *
     * @return array<string, int>
     */
    private function prefectureCounts(string $type): array
    {
        return Cache::remember("poi_area_pref_counts:{$type}", 86400, fn () => Poi::query()
            ->where('type', $type)
            ->whereNotNull('prefecture')
            ->where('prefecture', '!=', '')
            ->selectRaw('prefecture, COUNT(*) as cnt')
            ->groupBy('prefecture')
            ->pluck('cnt', 'prefecture')
            ->map(fn ($v) => (int) $v)
            ->all());
    }

    /**
     * 全47都道府県 [prefecture, count] を地方区分順にフラット化（「他の都道府県から探す」用）。
     *
     * @return array<int, array{prefecture: string, count: int}>
     */
    private function allPrefecturesWithCounts(string $type): array
    {
        $counts = $this->prefectureCounts($type);
        $out = [];
        foreach (self::REGIONS as $prefs) {
            foreach ($prefs as $pref) {
                $out[] = ['prefecture' => $pref, 'count' => $counts[$pref] ?? 0];
            }
        }

        return $out;
    }

    /**
     * 一覧・都道府県別ページの回遊リンク（roadside の listingCrossLinks と同じ4本）。
     *
     * @return array<int, array{label: string, url: string, icon: string, description: string}>
     */
    private function listingCrossLinks(): array
    {
        return [
            ['label' => 'ライダーズマップ', 'url' => route('riders.map'), 'icon' => 'map', 'description' => '道の駅・GS・洗車場を地図で'],
            ['label' => 'ツーリングプランナー', 'url' => route('touring.planner'), 'icon' => 'route', 'description' => 'ルートを引いて計画する'],
            ['label' => '駐車場マップ', 'url' => route('parking.index'), 'icon' => 'square-parking', 'description' => 'バイク駐車場を探す'],
            ['label' => '中古バイク検索', 'url' => route('bikes.search'), 'icon' => 'search', 'description' => '全国の在庫を検索'],
        ];
    }
}
