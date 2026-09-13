<?php

declare(strict_types=1);

namespace App\Http\Controllers\Poi;

use App\Http\Controllers\Controller;
use App\Models\BikeParking;
use App\Models\Poi;
use App\Models\RentalGarage;
use App\Models\RoadsideStation;
use App\Models\Shop;
use App\Models\Station;
use App\Support\AddressFormatter;
use App\Support\PoiDisplayResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
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
     * 種別ごとのメタ（ルート接頭辞・表示ラベル・もう一方の種別・市区町村ページの meta description 末尾文）。
     * gas_station は name も brand も無い行が2,200件あるため city ページは address で代替表示する。
     *
     * metaTail は市区町村ページの meta description の後半（「…を一覧。」に続く一文）。type ごとに実態へ合わせる。
     * 洗車場は671件中 住所32件・営業時間31件しかデータが無いため、GS用の「住所・営業時間・ブランド」文言は使わない。
     * 一覧・都道府県ページの meta は $label だけで自然な汎用文のため type 分岐せず据え置き。
     */
    private const TYPES = [
        'gas_station' => ['prefix' => 'gs', 'label' => 'ガソリンスタンド', 'other' => 'convenience_store', 'metaTail' => '住所・営業時間・ブランド情報を掲載しています。'],
        'convenience_store' => ['prefix' => 'konbini', 'label' => 'コンビニ', 'other' => 'gas_station', 'metaTail' => '住所・営業時間・ブランド情報を掲載しています。'],
        'car_wash' => ['prefix' => 'senshajo', 'label' => '洗車場', 'other' => 'gas_station', 'metaTail' => 'セルフ洗車場・洗車機の場所を地図で確認できます。'],
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
            // type は normalizedBrand() の種別分岐に必須（欠けると生の brand のまま出る）。
            ->get(['id', 'type', 'name', 'brand', 'address', 'opening_hours', 'self_service', 'automated', 'latitude', 'longitude', 'municipality_code']);

        if ($pois->isEmpty()) {
            abort(404);
        }

        $items = $pois->map(function (Poi $p) use ($type, $prefecture, $city): array {
            $display = $this->displayName($p);

            // 洗車場で name も brand も無い行は、住所をそのまま見出しにせず
            // 「設備ラベル（町名）」を組み立てる。住所行と同じ文字列が2回出るのを避ける。
            if ($type === 'car_wash' && filled($p->address) && $display === trim((string) $p->address)) {
                $display = $this->carWashLabel($p, $prefecture, $city);
            }
            // ブランド副見出しも表記ゆれを統一（GS/コンビニは正規化名・洗車場等は生の brand）。
            $brand = $this->normalizedBrand($p);

            return [
                'id' => (int) $p->id,
                'display' => $display,
                // 表示名（name→brand→address）と同一のブランドは重複行になるので出さない。
                'brand' => ($brand !== null && $brand !== '' && $brand !== $display) ? $brand : null,
                'address' => filled($p->address) ? (string) $p->address : null,
                'opening_hours' => filled($p->opening_hours) ? (string) $p->opening_hours : null,
                // 洗車場の設備フラグ（OSM の生値）。バッジ判定はビュー側で行う。GS/コンビニは通常 NULL。
                'self_service' => $p->self_service,
                'automated' => $p->automated,
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
            'metaTail' => $meta['metaTail'],
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
     * POI詳細ページ。洗車場は単体の情報が少ないため、周辺のガソリンスタンド・コンビニと
     * 同一市区町村の同種別を添えて、そのページだけで用が足りる形にする。
     */
    public function show(Request $request, string $prefecture, string $city, string $id): View
    {
        $type = (string) $request->route('type');
        $meta = self::TYPES[$type] ?? abort(404);

        if (! in_array($prefecture, self::prefectures(), true)) {
            abort(404);
        }

        $poi = Poi::query()
            ->where('type', $type)
            ->where('prefecture', $prefecture)
            ->where('city', $city)
            ->where('id', (int) $id)
            ->first();

        if ($poi === null) {
            abort(404);
        }

        $sameCityQuery = Poi::query()
            ->where('type', $type)
            ->where('prefecture', $prefecture)
            ->where('city', $city)
            ->where('id', '<>', $poi->id)
            ->orderByRaw("(COALESCE(NULLIF(name, ''), NULLIF(brand, '')) IS NULL)")
            ->orderBy('id')
            ->limit(8);

        if ($type === 'car_wash') {
            // 洗車場のみ: 自分から50m以内は OSM の二重登録とみなして除外する（本番で50m以内ペアが46組）。
            // address は町丁目までで番地が無く、同一住所に実在する複数施設もある（例: 様似町栄町に3件）ため、
            // 住所では判定できない。座標の実距離で見る。距離を測れない座標なしの行は実在を隠さないよう残す。
            // 母数が小さい（同一市区町村×洗車場は数件）ので nearbyFacilities のような矩形の粗絞りは入れない
            // ——粗絞りは「近くを探す」用で、ここは逆に遠い行を残すため矩形を掛けると実在施設を落としてしまう。
            // gs / コンビニは同一住所に別施設が普通にあるため、この分岐に入れず従来どおり除外しない。
            $sameCityQuery
                ->selectRaw(
                    'id, name, brand, address, type, prefecture, city, self_service, automated, '
                    .'ST_Distance_Sphere(POINT(longitude, latitude), POINT(?, ?)) AS dist_m',
                    [(float) $poi->longitude, (float) $poi->latitude]
                )
                ->havingRaw('dist_m IS NULL OR dist_m > 50');
        } else {
            $sameCityQuery->select(['id', 'name', 'brand', 'address', 'type', 'prefecture', 'city', 'self_service', 'automated']);
        }

        $sameCity = $sameCityQuery->get()
            ->map(fn (Poi $p): array => [
                'id' => (int) $p->id,
                'display' => $this->resolveDisplay($p, $type, $prefecture, $city),
                'address' => filled($p->address) ? (string) $p->address : null,
            ])->all();

        // 洗車場は周辺のバイク関連施設（ショップ・駐車場・レンタルガレージ）＋最寄り駅＋件数入り紹介文。
        // GSは周辺2種類（洗車場・バイク駐車場）＋最寄り駅＋「次のGSまで◯km」（事前計算列を読むだけ・空間クエリ無し）。
        // どちらも空間クエリを含むため POI 単位でキャッシュする（洗車場24h / GSは変化が遅く7日）。
        $isCarWash = $type === 'car_wash';
        $isGas = $type === 'gas_station';
        $isKonbini = $type === 'convenience_store';
        $lat = (float) $poi->latitude;
        $lng = (float) $poi->longitude;

        // 既定値（各種別で使う分だけ後段で埋める）。
        $nearbyShops = $nearbyParkings = $nearbyGarages = $nearbyWashes = [];
        $nearbyRoadside = $nearbyGasList = [];
        $nearestStation = null;
        $carWashSummary = '';
        $gasSummary = '';
        $konbiniSummary = '';
        $nextGas = null;
        $nextKonbini = null;
        $gasIsolated = false;
        $konbiniIsolated = false;

        if ($isCarWash) {
            [$nearbyShops, $nearbyParkings, $nearbyGarages, $nearestStation, $carWashSummary]
                // 世代付きキー。説明文(carWashSummary)はここにキャッシュされるため、文言に影響する変更ごとに必ず上げる。
                // 本番は cache:clear 不可（別機能の6000件規模が飛ぶ）で、世代を進めるのが唯一の即時反映手段。
                // v2: townPart() 導入で町名表記を修正 / v3: townPart() 実データ準拠に再修正＋50m重複除外に合わせて再生成。
                = Cache::remember("senshajo_detail_nearby:v4:{$poi->id}", 86400, function () use ($poi, $prefecture, $city, $lat, $lng) {
                    $shops = $this->nearbyFacilities(Shop::query(), $lat, $lng);
                    // is_active=1 のみ（非公開の駐車場・ガレージは出さない）。
                    $parkings = $this->nearbyFacilities(BikeParking::query()->where('is_active', 1), $lat, $lng);
                    $garages = $this->nearbyFacilities(RentalGarage::query()->where('is_active', 1), $lat, $lng);
                    $station = $this->nearestStation($lat, $lng);

                    // 自動生成文の件数（半径5km以内）。GSは pois の gas_station を数える。
                    // 表示順で並べ、0件カテゴリは carWashSummary 側で文から省く。
                    $counts = [
                        ['バイクショップ', $this->countWithinRadius(Shop::query(), $lat, $lng), '店'],
                        ['ガソリンスタンド', $this->countWithinRadius(Poi::query()->where('type', 'gas_station'), $lat, $lng), '軒'],
                        ['バイク駐車場', $this->countWithinRadius(BikeParking::query()->where('is_active', 1), $lat, $lng), 'か所'],
                        ['レンタルガレージ', $this->countWithinRadius(RentalGarage::query()->where('is_active', 1), $lat, $lng), 'か所'],
                    ];

                    return [$shops, $parkings, $garages, $station, $this->carWashSummary($poi, $prefecture, $city, $station, $counts)];
                });
        } elseif ($isGas) {
            // 周辺は2種類だけ（洗車場・バイク駐車場）。16,546ページ規模のため4種類は投げない。空間クエリのみ7日キャッシュ。
            // v1→v2: 「次のGS/離島」判定をキャッシュから外し毎回 $poi の事前計算列から出す構造に変更（旧キャッシュ無効化も兼ねる）。
            [$nearbyWashes, $nearbyParkings, $nearestStation]
                = Cache::remember("gs_detail_nearby:v3:{$poi->id}", 604800, function () use ($lat, $lng) {
                    $washes = $this->nearbyFacilities(Poi::query()->where('type', 'car_wash'), $lat, $lng);
                    $parkings = $this->nearbyFacilities(BikeParking::query()->where('is_active', 1), $lat, $lng);
                    $station = $this->nearestStation($lat, $lng);

                    return [$washes, $parkings, $station];
                });

            // 「次のGSまで」「離島」は事前計算列を読むだけ（空間クエリ無し）＝キャッシュに載せず毎回最新。
            // ★未計算(nearest_computed_at IS NULL)の行は離島扱いしない。poi:fetch(毎晩)追加分の誤表示を防ぐ核心。
            $gasIsolated = $poi->isGenuinelyIsolated();
            $nextGas = ($poi->nearestComputed() && $poi->nearest_same_type_id !== null)
                ? $this->resolveNextSameType($poi)
                : null;
            $gasSummary = $this->gasSummary($poi, $prefecture, $city);
        } elseif ($isKonbini) {
            // 周辺は2種類だけ（道の駅・GS）。31,050ページ規模のため空間クエリのみ7日キャッシュ。
            // トイレ有無は書かない方針のため、トイレが確実な道の駅を代わりに出す。
            [$nearbyRoadside, $nearbyGasList, $nearestStation]
                = Cache::remember("konbini_detail_nearby:v2:{$poi->id}", 604800, function () use ($lat, $lng) {
                    $roadside = $this->nearbyRoadsideStations($lat, $lng);
                    $gas = $this->nearbyFacilities(Poi::query()->where('type', 'gas_station'), $lat, $lng);
                    $station = $this->nearestStation($lat, $lng);

                    return [$roadside, $gas, $station];
                });

            // 離島/次コンビニ判定は事前計算列を読むだけ＝キャッシュ外で毎回算出（GSと同じ理由・佐久市の再発防止）。
            // 都市部（近傍<3km）は「次のコンビニまで」を出さない（0.2kmと書いても意味が無い）＝孤立(>=3km)時のみ。
            $konbiniIsolated = $poi->isGenuinelyIsolated();
            $isFarKonbini = $poi->nearestComputed()
                && $poi->nearest_same_type_id !== null
                && $poi->nearest_same_type_m !== null
                && $poi->nearest_same_type_m >= 3000;
            $nextKonbini = $isFarKonbini ? $this->resolveNextSameType($poi) : null;
            $konbiniSummary = $this->konbiniSummary($poi, $prefecture, $city);
        }

        // 見出しを近接GSのブランドで一意化するため、$display より先に近接GSを取得する（追加クエリなし・値を使い回す）。
        // GS/コンビニは周辺を専用ブロック（事前計算＋2種のみ）で出すため、洗車場用の nearbyByType 空間クエリは張らない。
        $nearbyGas = ($isGas || $isKonbini) ? [] : $this->nearbyByType($poi, 'gas_station');
        $nearbyStore = ($isGas || $isKonbini) ? [] : $this->nearbyByType($poi, 'convenience_store');

        // 名前を持たない洗車場のみ、50m以内で自前の名称を持つGSのブランドを見出しに併記して同名重複を解消する。
        // resolveDisplay 側で「設備ラベルに落ちるケース」だけに適用されるため、名前を持つ施設の見出しは変わらない。
        $gasBrand = $type === 'car_wash' ? $this->nearbyGasBrand($nearbyGas) : null;
        $display = $this->resolveDisplay($poi, $type, $prefecture, $city, $gasBrand);

        // 同名見出しの重複対策で <title> にだけ最寄り駅節を足して一意化する（洗車場・GS・コンビニ）。
        // 例: GS「apollostation」×4、コンビニ「セブン-イレブン」×53（h1 は townPart 併記、title は駅節でさらに一意化）。
        // h1 / JSON-LD name は簡潔さ優先で $display のまま。
        $pageTitle = ($isCarWash || $isGas || $isKonbini)
            ? $this->carWashTitle($display, $nearestStation, $city, $meta['label'])
            : null;

        return view('poi_area.show', [
            'routePrefix' => $meta['prefix'],
            'label' => $meta['label'],
            'prefecture' => $prefecture,
            'city' => $city,
            'poi' => $poi,
            // h1 / JSON-LD name はこの $display を参照。<title> だけは $pageTitle（洗車場・GSは駅節つき）を使う。
            'display' => $display,
            'pageTitle' => $pageTitle,
            // 「ブランド」欄用の正規化名（GS/コンビニ=表記ゆれ統一・洗車場等=生の brand・exclude/空=null）。
            'brandLabel' => $this->normalizedBrand($poi),
            // JSON-LD の streetAddress も townPart() を通し、郡部・政令市の二重表記を構造化データからも排除する。
            'streetAddress' => $this->townPart($prefecture, $city, $poi->address),
            'nearbyGas' => $nearbyGas,
            'nearbyStore' => $nearbyStore,
            'sameCity' => $sameCity,
            // 洗車場のみ内容を持つ（GS・コンビニは空／null）。ビュー側は senshajo でのみ表示する。
            'nearbyShops' => $nearbyShops,
            'nearbyParkings' => $nearbyParkings,
            'nearbyGarages' => $nearbyGarages,
            'nearestStation' => $nearestStation,
            'carWashSummary' => $carWashSummary,
            // GSのみ内容を持つ（他種別は空／null/false）。ビュー側は gs でのみ表示する。
            'nearbyWashes' => $nearbyWashes,
            'nextGas' => $nextGas,
            // 本物の離島（計算済み＆近傍なし）のときだけ true。未計算は false＝「他にありません」を出さない。
            'gasIsolated' => $gasIsolated,
            'gasSummary' => $gasSummary,
            'gas24h' => $isGas ? $this->isGas24h($poi) : false,
            // コンビニのみ内容を持つ（他種別は空／null/false）。周辺は道の駅＋GSの2種。
            'nearbyRoadside' => $nearbyRoadside,
            'nearbyGasList' => $nearbyGasList,
            'nextKonbini' => $nextKonbini,
            'konbiniIsolated' => $konbiniIsolated,
            'konbiniSummary' => $konbiniSummary,
            'konbini24h' => $isKonbini ? $this->isGas24h($poi) : false,
            'crossLinks' => $this->listingCrossLinks(),
        ]);
    }

    /**
     * 短縮URL /{prefix}/{id} → 正規URLへのリダイレクト。地図ピン等、prefecture/city を持たない導線用。
     * 種別はルート defaults('type', ...) で渡す（/senshajo/{id}=car_wash, /gs/{id}=gas_station）。
     * 指定種別に一致しない行は404（senshajo.short に GS の id が来ても従来どおり404のまま挙動を保つ）。
     * prefecture/city が両方そろえば canonical へ301。片方でも欠ける（行政区未割当）行は正規URLを組めないので
     * 一覧へ302で逃がす（データが埋まれば正しい遷移になるため、恒久リダイレクトにしない）。
     */
    public function short(Request $request, string $id): RedirectResponse
    {
        $type = (string) $request->route('type');
        $meta = self::TYPES[$type] ?? abort(404);

        $poi = Poi::query()->where('id', (int) $id)->first();

        if ($poi === null || $poi->type !== $type) {
            abort(404);
        }

        if (blank($poi->prefecture) || blank($poi->city)) {
            return redirect()->route($meta['prefix'].'.index', [], 302);
        }

        return redirect()->route($meta['prefix'].'.show', [$poi->prefecture, $poi->city, $poi->id], 301);
    }

    /**
     * 表示名の決定。洗車場で name も brand も無い行は、住所の代わりに設備ラベルを使う。
     *
     * $gasBrand を渡すと、設備ラベルに落ちる（名前を持たない）洗車場に限り近接GSのブランドを併記する。
     * 名前を持つ施設は displayName() をそのまま返すので、$gasBrand を渡してもラベルは一切変わらない。
     * 一覧（city.blade）は1件ごとの空間クエリを避けるため $gasBrand を渡さず、従来どおりの文言のままにする。
     */
    private function resolveDisplay(Poi $poi, string $type, string $prefecture, string $city, ?string $gasBrand = null): string
    {
        // 解決ロジックの正本は PoiDisplayResolver（地図JS/API と共有・分裂防止）。
        return $this->displayResolver()->resolve($poi, $type, $prefecture, $city, $gasBrand);
    }

    private ?PoiDisplayResolver $displayResolver = null;

    private function displayResolver(): PoiDisplayResolver
    {
        return $this->displayResolver ??= new PoiDisplayResolver;
    }

    /**
     * GS/コンビニ詳細の見出し。ブランドの表記ゆれを統一しつつ具体的店名は温存する（gas/コンビニ共通）。
     *   - name が具体的店名（例「三菱商事エネルギー 牛潟SS」「apollostation 牛潟SS」）→ そのまま（name 内は一切いじらない）
     *   - name が素のブランド名そのもの（正規化して config patterns と【完全一致】。例「エネオス」「JA」）→ 正規化名へ
     *   - name が無い → brand を正規化名に（gas/cvsOperatorLabel）
     *   正規化名になった見出しだけ townPart() の町名を併記して同一市内の重複を解消する（例: ENEOS（荏田東二丁目））。
     *
     * ★完全一致に限る＝「エネオス安波給油所」は patterns の「エネオス」を含むが完全一致でないので触らない（誤爆しない）。
     */
    private function brandHeading(Poi $poi, string $prefecture, string $city): string
    {
        return $this->displayResolver()->brandHeading($poi, $prefecture, $city);
    }

    /**
     * GS詳細の自動生成文（所在地＋セルフ/フルサービス＋24時間＋次のGSまでの距離）。
     * 例:「神奈川県横浜市青葉区荏田東にあるセルフのガソリンスタンドです。24時間営業。次のGSまで約0.5km。」
     * 孤立GS（nearest_same_type_id が null）は「この付近に他のガソリンスタンドはありません。」に切り替える。
     */
    private function gasSummary(Poi $poi, string $prefecture, string $city): string
    {
        $town = $this->townPart($prefecture, $city, $poi->address);
        $place = $prefecture.$city.$town;

        $selfRaw = strtolower(trim((string) ($poi->self_service ?? '')));
        if (in_array($selfRaw, ['yes', 'only'], true)) {
            $kind = 'セルフのガソリンスタンド';
        } elseif ($selfRaw === 'no') {
            $kind = 'フルサービスのガソリンスタンド';
        } else {
            $kind = 'ガソリンスタンド';
        }
        $text = $place.'にある'.$kind.'です。';

        if ($this->isGas24h($poi)) {
            $text .= '24時間営業。';
        }

        // 次のGS／離島は「計算済み」のときだけ言及する。未計算(nearest_computed_at IS NULL)は何も言わない
        // （毎晩追加される未計算行を誤って「他にありません」と断定しないため。判定は Poi の共通入口に集約）。
        if ($poi->nearestComputed()) {
            if ($poi->nearest_same_type_id === null) {
                // 本物の離島（100km以内に他のGSが無い）。ツーリングでは価値の高い情報。
                $text .= 'この付近に他のガソリンスタンドはありません。';
            } elseif ($poi->nearest_same_type_m !== null) {
                $km = round(((int) $poi->nearest_same_type_m) / 1000, 1);
                $text .= $km < 0.1
                    ? '次のガソリンスタンドはすぐ近くにあります。'
                    : '次のガソリンスタンドまで約'.number_format($km, 1).'km。';
            }
        }

        return $text;
    }

    /** opening_hours から24時間営業を判定する（OSMの 24/7・和文表記の双方に対応）。GS/コンビニ共用。 */
    private function isGas24h(Poi $poi): bool
    {
        $oh = strtolower(trim((string) ($poi->opening_hours ?? '')));

        return $oh !== '' && (str_contains($oh, '24/7') || str_contains($oh, '24時間') || str_contains($oh, '24 hours'));
    }

    /**
     * 「次の同種別POI（GS/コンビニ）」を事前計算列（nearest_same_type_id / _m）から解決する。空間クエリは投げない。
     * 参照先の name/brand と正規URLを組むため id 直引き（PK1件）だけ行う。孤立（id が null）や参照先消失時は null。
     * 表示名・URLは参照先の type に合わせる（gasDisplay/konbiniDisplay と {prefix}.show）。
     *
     * @return array{display: string, km: float, url: ?string}|null
     */
    private function resolveNextSameType(Poi $poi): ?array
    {
        if ($poi->nearest_same_type_id === null) {
            return null;
        }

        $n = Poi::query()->where('id', $poi->nearest_same_type_id)
            ->first(['id', 'type', 'name', 'brand', 'address', 'prefecture', 'city']);
        if ($n === null) {
            return null;
        }

        $prefix = self::TYPES[$n->type]['prefix'] ?? null;
        $url = ($prefix !== null && filled($n->prefecture) && filled($n->city))
            ? route($prefix.'.show', [$n->prefecture, $n->city, $n->id])
            : null;

        $display = ($n->type === 'gas_station' || $n->type === 'convenience_store')
            ? $this->brandHeading($n, (string) $n->prefecture, (string) $n->city)
            : $this->displayName($n);

        return [
            'display' => $display,
            'km' => round(((int) ($poi->nearest_same_type_m ?? 0)) / 1000, 1),
            'url' => $url,
        ];
    }

    /**
     * brand の正規化表示名（表記ゆれ統一の単一入口）。種別ごとに config 駆動の分類を通す。
     *   GS       → Poi::gasOperatorLabel（config/gas.php: ENEOS/出光/コスモ石油/カーエネクス/コストコ 等）
     *   コンビニ → Poi::cvsOperatorLabel（config/convenience.php: セブン-イレブン/ローソンストア100 等）
     * 分類できない独立系は生の屋号（>10字は…）を返す。exclude/空は null。地図/API と同じ config を共有する。
     */
    private function normalizedBrand(Poi $poi): ?string
    {
        return $this->displayResolver()->normalizedBrand($poi);
    }

    /**
     * name が「素のブランド名そのもの」のときだけ、その正規化表示名を返す（そうでなければ null）。
     * 判定は name を ShopNameNormalizer で正規化し、config の brand トークンと【完全一致】するか。
     * 部分一致では絶対に置換しない＝「エネオス安波給油所」は触らない（構造的に誤爆しない）。GS/コンビニのみ対象。
     */
    private function exactBrandLabel(Poi $poi): ?string
    {
        return $this->displayResolver()->exactBrandLabel($poi);
    }

    /**
     * コンビニ詳細の自動生成文（所在地＋24時間＋孤立時のみ「次のコンビニまで」）。
     * 都市部（近傍<3km）は距離を出さない（0.2kmと書いても意味が無い）。判定は計算済みのときだけ（[[未計算は無言]]）。
     */
    private function konbiniSummary(Poi $poi, string $prefecture, string $city): string
    {
        $town = $this->townPart($prefecture, $city, $poi->address);
        $text = $prefecture.$city.$town.'にあるコンビニです。';

        if ($this->isGas24h($poi)) {
            $text .= '24時間営業。';
        }

        if ($poi->nearestComputed()) {
            if ($poi->nearest_same_type_id === null) {
                $text .= 'この付近に他のコンビニはありません。';
            } elseif ($poi->nearest_same_type_m !== null && $poi->nearest_same_type_m >= 3000) {
                // 孤立（3km以上）のときだけ距離を主役にする。都市部は言及しない。
                $km = round(((int) $poi->nearest_same_type_m) / 1000, 1);
                $text .= 'この先、次のコンビニまで約'.number_format($km, 1).'km。';
            }
        }

        return $text;
    }

    /**
     * 起点周辺の道の駅（RoadsideStation・トイレが確実）を直線距離順に返す。link は michinoeki.show({station_code})。
     * nearbyFacilities と同流儀（矩形で粗絞り→ST_Distance_Sphere で実距離）だが、リンクキーが station_code のため専用。
     *
     * @return array<int, array{station_code: string, name: string, km: float}>
     */
    private function nearbyRoadsideStations(float $lat, float $lng, float $radiusKm = 10.0, int $limit = 3): array
    {
        $deg = $radiusKm / 111.0;

        return RoadsideStation::query()
            ->whereNotNull('latitude')->whereNotNull('longitude')
            ->whereBetween('latitude', [$lat - $deg, $lat + $deg])
            ->whereBetween('longitude', [$lng - $deg, $lng + $deg])
            ->selectRaw(
                'station_code, name, ST_Distance_Sphere(POINT(longitude, latitude), POINT(?, ?)) AS dist_m',
                [$lng, $lat]
            )
            ->havingRaw('dist_m <= ?', [$radiusKm * 1000])
            ->orderBy('dist_m')
            ->limit($limit)
            ->get()
            ->map(fn ($r): array => [
                'station_code' => (string) $r->station_code,
                'name' => (string) $r->name,
                'km' => round(((float) $r->dist_m) / 1000, 1),
            ])
            ->all();
    }

    /**
     * 起点POIの周辺にある指定種別のPOIを直線距離順に取得。約10kmの矩形で粗く絞ってから実距離で並べる。
     *
     * dist_m（丸めないメートル）と own_name（name→brand のみ・住所フォールバックしない）も返す。
     * 前者は50m判定用、後者は近接GSのブランドで洗車場見出しを一意化する用途で使う（ビュー表示はしない）。
     *
     * @return array<int, array{id: int, display: string, address: ?string, prefecture: string, city: string, km: float, dist_m: float, own_name: ?string}>
     */
    private function nearbyByType(Poi $origin, string $type, int $limit = 5): array
    {
        $lat = (float) $origin->latitude;
        $lng = (float) $origin->longitude;

        return Poi::query()
            ->where('type', $type)
            ->where('id', '<>', $origin->id)
            ->whereNotNull('prefecture')->where('prefecture', '<>', '')
            ->whereNotNull('city')->where('city', '<>', '')
            ->whereBetween('latitude', [$lat - 0.09, $lat + 0.09])
            ->whereBetween('longitude', [$lng - 0.11, $lng + 0.11])
            ->selectRaw(
                'id, name, brand, address, prefecture, city, type, self_service, automated, '
                .'ST_Distance_Sphere(POINT(longitude, latitude), POINT(?, ?)) AS dist_m',
                [$lng, $lat]
            )
            ->orderBy('dist_m')
            ->limit($limit)
            ->get()
            ->map(fn (Poi $p): array => [
                'id' => (int) $p->id,
                'display' => $this->displayName($p),
                'address' => filled($p->address) ? (string) $p->address : null,
                'prefecture' => (string) $p->prefecture,
                'city' => (string) $p->city,
                'km' => round(((float) $p->dist_m) / 1000, 1),
                // km は0.1km丸めで50m判定には粗いため、生メートルを別途持つ。own_name は住所へ落ちない自前の名称。
                'dist_m' => (float) $p->dist_m,
                'own_name' => $this->ownName($p),
            ])
            ->all();
    }

    /**
     * POI が自前で持つ名称（name → brand）。どちらも空なら null。
     * displayName() と違い住所へフォールバックしないので、「名前を持つ施設か」の判定に使える。
     */
    private function ownName(Poi $poi): ?string
    {
        foreach ([$poi->name, $poi->brand] as $candidate) {
            $v = trim((string) ($candidate ?? ''));
            if ($v !== '') {
                return $v;
            }
        }

        return null;
    }

    /**
     * 洗車場見出しへ併記する近接GSのブランド名。nearbyByType の結果を使い回すので追加クエリは無い。
     * 最寄りGSが50m以内で、かつ自前の name/brand を持つ場合だけ返す（住所フォールバックは弾く。
     * さもないと「◯◯町一丁目併設」のような住所併記になってしまう）。条件を満たさなければ null。
     *
     * @param  array<int, array{dist_m?: float, own_name?: ?string}>  $nearbyGas  nearbyByType($poi, 'gas_station') の結果（距離昇順）
     */
    private function nearbyGasBrand(array $nearbyGas): ?string
    {
        $nearest = $nearbyGas[0] ?? null;
        if ($nearest === null) {
            return null;
        }

        if (($nearest['dist_m'] ?? INF) <= 50 && filled($nearest['own_name'] ?? null)) {
            // 「ENEOS併設」と「エネオス併設」の揺れを消すため、近接GSの屋号も正規化名に寄せる。
            $own = (string) $nearest['own_name'];

            return Poi::gasOperatorLabel($own, Poi::gasBrand($own)) ?? $own;
        }

        return null;
    }

    /**
     * 洗車場詳細に添える「周辺のバイク関連施設」の共通クエリ。
     * shops / bike_parkings / rental_garages は別テーブル・別リンク体系なので、is_active 等の絞り込みは
     * 呼び出し側で基底クエリに付けてもらい、ここは距離計算だけを担う（重複を避ける）。
     *
     * どのテーブルも latitude / longitude / name / id を持つ前提。緯度経度の矩形で粗く絞ってから
     * ST_Distance_Sphere（メートル）で実距離順に並べ、半径外の矩形隅を HAVING で落とす。
     * 矩形は緯度1度≒111kmで換算し、経度は日本域で縮む分だけ取りこぼしが出ないよう同係数で広めに取る
     * （最終的な半径判定は ST_Distance_Sphere が担保するので粗絞りは広めで良い）。SRID 0 の POINT は
     * 「経度・緯度」の順（POINT(longitude, latitude)）。
     *
     * 対象が Poi のときは name/brand/address から facilityLabel() でラベルを組む（type 分岐に必要な列も SELECT する）。
     * ★ラベルが作れない（name/brand が空 かつ 住所から町名が取れない）行は配列から除外する
     *   ＝「空リンク」や「種別名だけ」の無意味な導線を出さない。除外で 0 件ならビュー側の @if(!empty()) で節ごと消える。
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query  対象テーブルの基底クエリ
     * @return array<int, array{id: int, name: string, km: float}>
     */
    private function nearbyFacilities($query, float $lat, float $lng, float $radiusKm = 10.0, int $limit = 3): array
    {
        $deg = $radiusKm / 111.0;
        $isPoi = $query->getModel() instanceof Poi;

        // Poi は type/brand/address/prefecture/city も要る（type 欠落だと facilityLabel が既定に落ちる）。
        $columns = $isPoi ? 'id, type, name, brand, address, prefecture, city' : 'id, name';

        return $query
            ->whereNotNull('latitude')->whereNotNull('longitude')
            ->whereBetween('latitude', [$lat - $deg, $lat + $deg])
            ->whereBetween('longitude', [$lng - $deg, $lng + $deg])
            ->selectRaw(
                $columns.', ST_Distance_Sphere(POINT(longitude, latitude), POINT(?, ?)) AS dist_m',
                [$lng, $lat]
            )
            ->havingRaw('dist_m <= ?', [$radiusKm * 1000])
            ->orderBy('dist_m')
            ->limit($limit)
            ->get()
            ->map(function ($r) use ($isPoi): ?array {
                // Poi は name/brand/住所町名でラベルを作る。他テーブル(shops/parkings/garages)は name をそのまま。
                $label = $isPoi
                    ? $this->facilityLabel($r)
                    : (filled($r->name) ? (string) $r->name : null);

                if ($label === null || $label === '') {
                    return null; // ラベルが作れない行は落とす（無意味なリンクを出さない）
                }

                return [
                    'id' => (int) $r->id,
                    'name' => $label,
                    'km' => round(((float) $r->dist_m) / 1000, 1),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * 周辺リストに出す Poi のラベル。順に見て最初に決まったものを返し、全部だめなら null（＝その行は出さない）。
     *   1) name が非空 → name をそのまま（具体的店名を温存）
     *   2) brand が非空 → normalizedBrand()（gas/cvsOperatorLabel で表記ゆれ統一）
     *   3) address から町名が取れる → 「{種別ラベル}（{町名}）」（例: ガソリンスタンド（新砂一丁目））
     *   4) それ以外 → null
     */
    private function facilityLabel(Poi $poi): ?string
    {
        $name = trim((string) ($poi->name ?? ''));
        if ($name !== '') {
            return $name;
        }

        $brandLabel = $this->normalizedBrand($poi);
        if ($brandLabel !== null && $brandLabel !== '') {
            return $brandLabel;
        }

        $town = AddressFormatter::townPart($poi->prefecture, $poi->city, $poi->address);
        if ($town !== '') {
            $typeLabel = self::TYPES[$poi->type]['label'] ?? 'スポット';

            return $typeLabel.'（'.$town.'）';
        }

        return null;
    }

    /**
     * 起点の半径15km以内で最も近い駅を1件返す。stations には address 列が無いため名前と距離のみ。
     * 駅名が「駅」で終わらない場合だけ補い、表示・文言の双方でそのまま使えるようにする。
     * 15km以内に無ければ null（駅が遠い地域では最寄り駅を出さない）。
     *
     * @return array{name: string, km: float}|null
     */
    private function nearestStation(float $lat, float $lng, float $radiusKm = 15.0): ?array
    {
        $deg = $radiusKm / 111.0;

        $row = Station::query()
            ->whereBetween('latitude', [$lat - $deg, $lat + $deg])
            ->whereBetween('longitude', [$lng - $deg, $lng + $deg])
            ->selectRaw(
                'id, name, ST_Distance_Sphere(POINT(longitude, latitude), POINT(?, ?)) AS dist_m',
                [$lng, $lat]
            )
            ->havingRaw('dist_m <= ?', [$radiusKm * 1000])
            ->orderBy('dist_m')
            ->first();

        if ($row === null) {
            return null;
        }

        $name = (string) $row->name;
        if (! str_ends_with($name, '駅')) {
            $name .= '駅';
        }

        return ['name' => $name, 'km' => round(((float) $row->dist_m) / 1000, 1)];
    }

    /**
     * 起点の半径 $radiusKm 以内にある基底クエリ対象の件数。自動生成文の「半径5km以内に…」に使う。
     * 粗絞りの矩形だけでは隅が半径を超えるため、ST_Distance_Sphere で厳密に半径内へ絞ってから数える。
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query  対象テーブルの基底クエリ（type や is_active は付与済み）
     */
    private function countWithinRadius($query, float $lat, float $lng, float $radiusKm = 5.0): int
    {
        $deg = $radiusKm / 111.0;

        return (int) $query
            ->whereNotNull('latitude')->whereNotNull('longitude')
            ->whereBetween('latitude', [$lat - $deg, $lat + $deg])
            ->whereBetween('longitude', [$lng - $deg, $lng + $deg])
            ->whereRaw(
                'ST_Distance_Sphere(POINT(longitude, latitude), POINT(?, ?)) <= ?',
                [$lng, $lat, $radiusKm * 1000]
            )
            ->count();
    }

    /**
     * 洗車場詳細の見出し下に出す自動生成文（施設ごとに数字が変わる1〜3文）。
     * 例:「埼玉県春日部市水角にあるセルフの洗車場です。最寄りは○○駅から約2.1km。
     *     半径5km以内に、バイクショップ3店、ガソリンスタンド8軒、バイク駐車場12か所があります。」
     * 最寄り駅が無ければ2文目を、5km以内が全カテゴリ0なら3文目を省く。0件のカテゴリも文から省く。
     *
     * @param  array{name: string, km: float}|null  $station  nearestStation() の戻り
     * @param  array<int, array{0: string, 1: int, 2: string}>  $counts  [ラベル, 件数, 助数詞] の配列
     */
    private function carWashSummary(Poi $poi, string $prefecture, string $city, ?array $station, array $counts): string
    {
        // 1文目: 所在地＋設備。町名は townPart() で住所から行政区分を除いて取り出す（郡部の二重表記対策込み）。
        $town = $this->townPart($prefecture, $city, $poi->address);
        $place = $prefecture.$city.$town;

        // 設備は carWashLabel と同じ self_service / automated 判定に合わせる（'yes'/'only' を真とみなす）。
        $yes = static fn ($v): bool => in_array(strtolower(trim((string) ($v ?? ''))), ['yes', 'only'], true);
        $self = $yes($poi->self_service);
        $auto = $yes($poi->automated);
        if ($self && $auto) {
            $kind = 'セルフ・洗車機併設の洗車場';
        } elseif ($self) {
            $kind = 'セルフの洗車場';
        } elseif ($auto) {
            $kind = '洗車機のある洗車場';
        } else {
            $kind = '洗車場';
        }
        $text = $place.'にある'.$kind.'です。';

        // 2文目: 最寄り駅（15km以内に無ければ省く）。距離は 0.1km 未満なら「同じ敷地内」に統一。
        if ($station !== null) {
            $text .= $station['km'] < 0.1
                ? '最寄りは'.$station['name'].'で、同じ敷地内にあります。'
                : '最寄りは'.$station['name'].'から約'.number_format($station['km'], 1).'km。';
        }

        // 3文目: 半径5km以内の周辺件数。0件カテゴリは省き、すべて0なら文自体を出さない。
        $parts = [];
        foreach ($counts as [$label, $count, $unit]) {
            if ($count > 0) {
                $parts[] = $label.$count.$unit;
            }
        }
        if ($parts !== []) {
            $text .= '半径5km以内に、'.implode('、', $parts).'があります。';
        }

        return $text;
    }

    /**
     * 洗車場詳細ページの <title> 専用文字列。同名ラベルの重複を避けるため見出しに最寄り駅節を足す。
     * ノードは「見出し｜駅と距離｜市区町村の種別」を全角｜で連結。全角35文字を目安に、溢れたら
     * 末尾（市区町村→駅）から削り、見出しは必ず残す（優先度: 見出し > 駅と距離 > 市区町村）。
     * 追加クエリは無し（$station は show() で既に取得済みの nearestStation() の戻りを渡す）。
     *
     * @param  array{name: string, km: float}|null  $station  15km以内に駅が無ければ null（駅節を省く）
     */
    private function carWashTitle(string $display, ?array $station, string $city, string $label): string
    {
        $nodes = [$display];
        if ($station !== null) {
            // 距離は詳細ページと同じルール: 0.1km未満は距離を出さず「すぐ」に留める。
            $nodes[] = $station['km'] < 0.1
                ? $station['name'].'すぐ'
                : $station['name'].'から約'.number_format($station['km'], 1).'km';
        }
        $nodes[] = $city.'の'.$label;

        // mb_strwidth は全角=2/半角=1。全角35文字＝幅70を上限に、末尾要素から落として収める。
        while (count($nodes) > 1 && mb_strwidth(implode('｜', $nodes)) > 70) {
            array_pop($nodes);
        }

        return implode('｜', $nodes);
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
    /**
     * 名称もブランドも無い洗車場の見出し。OSM の self_service / automated から設備を、
     * address から町名を取り出して「コイン洗車場（水角）」の形にする。
     *
     * $gasBrand（近接GSのブランド）を渡すと括弧内に「◯◯併設」を足して同名重複を解消する
     * （例: 洗車場（横芝）→ 洗車場（横芝・ENEOS併設）／町名が無ければ 洗車場（ENEOS併設））。
     * 括弧内の要素が1つのときは中黒を出さない。
     */
    private function carWashLabel(Poi $poi, string $prefecture, string $city, ?string $gasBrand = null): string
    {
        return $this->displayResolver()->carWashLabel($poi, $prefecture, $city, $gasBrand);
    }

    /**
     * 住所から町名（丁目・番地の手前の地名）を取り出す。carWashLabel / carWashSummary / JSON-LD で共用し、
     * 「山武郡横芝光町横芝光町横芝」のような二重表記を防ぐ。
     *
     * pois.city は municipalities.full_name（郡付き。例「山武郡横芝光町」）だが、pois.address 側は表記がずれる。
     * 本番246件の実データでは、単純な str_replace([$prefecture,$city]) で108件が市区町村名を残していた。傾向:
     *   - 郡部（…郡○○町/村）: address は郡を含まない → 郡以降（○○町/村）を候補に足す
     *   - 政令市（○○市△△区）: address は区が抜けて市が残る（例 city「横浜市鶴見区」/ addr「…横浜市駒岡」）
     *     → 「市まで」を候補に足すのが本命。念のため「区のみ」も足して安全側にする。
     * str_replace は配列順に処理するので、短い候補が長い候補の一部を先に削らないよう長い順に並べる。
     */
    private function townPart(string $prefecture, string $city, ?string $address): string
    {
        // ロジックの正本は AddressFormatter に集約（Poi::getDisplayNameAttribute と共用・分裂防止）。
        return AddressFormatter::townPart($prefecture, $city, $address);
    }

    /**
     * 表示名フォールバック: name → brand（種別ごとに正規化）→ address →「名称不明」。
     * name は一切いじらない。brand を見出しに使う場合だけ normalizedBrand() で表記ゆれを統一する
     * （GS/コンビニ。エネオス→ENEOS 等。それ以外の種別は生の brand）。
     */
    private function displayName(Poi $poi): string
    {
        return $this->displayResolver()->displayName($poi);
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
