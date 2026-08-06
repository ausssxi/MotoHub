<?php

declare(strict_types=1);

namespace App\Http\Controllers\RoadsideStation;

use App\Http\Controllers\Controller;
use App\Models\BikeParking;
use App\Models\Poi;
use App\Models\RentalGarage;
use App\Models\RoadsideStation;
use App\Models\Shop;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

final class RoadsideStationController extends Controller
{
    /**
     * まとめサイト（＝公式サイトではない）のホスト接尾辞。
     * website_url がこのいずれかで終わる場合はリンクを一切出さない（1,162件が該当）。
     */
    private const AGGREGATOR_HOST_SUFFIXES = ['michi-no-eki.jp', 'michinoeki.jp', 'michieki.jp'];

    /**
     * 8地方区分 → 所属都道府県（フルネーム＝roadside_stations.prefecture と完全一致）。
     * config/regions.php の blocks と同一の割り当て（三重県は近畿）。表示順もこの順。
     * 一覧/都道府県別ページのグルーピングと、全47都道府県フッターの並び順に使う。
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
     * 全都道府県（フルネーム）の正準リスト。REGIONS を平坦化して47件・重複なしで返す。
     * 都道府県リストの単一の出所。サイトマップ生成など、コントローラ外からも参照する。
     *
     * @return array<int, string>
     */
    public static function prefectures(): array
    {
        return array_merge(...array_values(self::REGIONS));
    }

    /**
     * 道の駅 一覧ページ（全国・地方区分別の都道府県リンク）。
     * 都道府県ごとの駅数を1クエリで集計し、地方区分でグルーピングしてビューへ渡す。
     */
    public function index(): View
    {
        $data = Cache::remember('roadside_index', 86400, function () {
            $counts = $this->prefectureCounts();

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

            // 全47都道府県への内部リンク（フッター用）。地方順にフラット化。
            $allPrefectures = [];
            foreach ($regions as $items) {
                foreach ($items as $it) {
                    $allPrefectures[] = $it;
                }
            }

            // データドリフト検知: 47都道府県の合計と総件数が食い違ったら1本だけ警告。
            // closure 内なのでキャッシュ有効中(24h)は再実行されず、毎リクエストは走らない。
            $grandTotal = RoadsideStation::count();
            if ($grandTotal !== $total) {
                Log::warning('道の駅の件数ドリフト: 47都道府県の合計='.$total.' / 総件数='.$grandTotal);
            }

            return ['total' => $total, 'regions' => $regions, 'allPrefectures' => $allPrefectures];
        });

        return view('roadside_station.index', array_merge($data, [
            'crossLinks' => $this->listingCrossLinks(),
        ]));
    }

    /**
     * 都道府県別 道の駅一覧。市区町村ごとにグルーピングして返す。該当0件は404。
     * キャッシュには Eloquent を入れず素の配列に落とす（show の buildNearby と同方針）。
     */
    public function area(string $prefecture): View
    {
        // 未知の都道府県はキャッシュキーに到達させない。
        //  (a) 生入力がキーに入るとキャッシュキーが無限に増える → 47通りに固定。
        //  (b) Cache::remember は値 null をミス扱いにするため、存在しない県は毎回DBを叩く → 到達前に弾く。
        // 正準な47都道府県ホワイトリスト（prefectures()）に無ければ即404。
        if (! in_array($prefecture, self::prefectures(), true)) {
            abort(404);
        }

        $data = Cache::remember("roadside_area:{$prefecture}", 86400, function () use ($prefecture) {
            $stations = RoadsideStation::query()
                ->where('prefecture', $prefecture)
                ->orderBy('city')
                ->orderBy('name')
                ->get(['station_code', 'name', 'nickname', 'city', 'address', 'route', 'image_url', 'designated_year', 'summary']);

            if ($stations->isEmpty()) {
                return null; // 未知/駅0の都道府県 → area() 側で 404
            }

            // 市区町村ごとにグルーピング（素の配列）。summary は有無だけ持つ。
            $byCity = [];
            foreach ($stations as $s) {
                $city = (string) ($s->city ?? '');
                $byCity[$city][] = [
                    'station_code' => $s->station_code,
                    'name' => $s->name,
                    'nickname' => $s->nickname,
                    'city' => $s->city,
                    'address' => $s->address,
                    'route' => $s->route,
                    'image_url' => $s->image_url,
                    'designated_year' => $s->designated_year,
                    'has_summary' => filled($s->summary),
                ];
            }

            return ['prefecture' => $prefecture, 'count' => $stations->count(), 'byCity' => $byCity];
        });

        if ($data === null) {
            abort(404);
        }

        return view('roadside_station.area', array_merge($data, [
            // 全47都道府県への内部リンク（フッター「他の都道府県から探す」用）。
            'allPrefectures' => $this->allPrefecturesWithCounts(),
            'crossLinks' => $this->listingCrossLinks(),
        ]));
    }

    /**
     * 道の駅 詳細ページ（公開・認証なし）。
     * station_code は先頭ゼロを含む5桁文字列なので int にキャストしない。
     */
    public function show(string $stationCode): View
    {
        $station = RoadsideStation::query()
            ->where('station_code', $stationCode)
            ->firstOrFail();

        $hasCoords = $station->latitude !== null && $station->longitude !== null;

        // 施設情報の出し分け:
        // summary が空の108件は施設フラグ(has_*)が全項目 false のまま入っており、
        // 「なし」と表示すると事実と異なる（未収集であって「無い」わけではない）。
        // そのため施設情報セクションごと出力しない。代替テキストも出さない
        // （108ページに同一文言が並ぶのを避けるため）。
        $hasFacilityData = filled($station->summary);

        // 公式サイトの出し分け（まとめサイト除外＋紹介元でラベルを変える）。
        [$officialUrl, $officialLabel] = $this->resolveOfficial($station->website_url);

        // 周辺情報（座標があるときのみ）。素の配列に落として24時間キャッシュ。
        $nearby = $hasCoords
            ? Cache::remember("roadside_nearby:{$stationCode}", 86400, fn () => $this->buildNearby($station))
            : $this->emptyNearby();

        // 回遊リンク（道の駅向け）。route() 名は実在するもののみ。
        $crossLinks = [
            ['label' => 'ライダーズマップ', 'url' => route('riders.map'), 'icon' => 'map', 'description' => '道の駅・GS・洗車場を地図で'],
            ['label' => 'ツーリングプランナー', 'url' => route('touring.planner'), 'icon' => 'route', 'description' => 'ルートを引いて計画する'],
            ['label' => '駐車場マップ', 'url' => route('parking.index'), 'icon' => 'square-parking', 'description' => 'バイク駐車場を探す'],
            ['label' => '中古バイク検索', 'url' => route('bikes.search'), 'icon' => 'search', 'description' => '全国の在庫を検索'],
        ];

        return view('roadside_station.show', compact(
            'station', 'hasCoords', 'hasFacilityData', 'officialUrl', 'officialLabel', 'nearby', 'crossLinks'
        ));
    }

    /**
     * website_url からリンク用の [URL, ラベル] を決める。
     *  - まとめサイト（AGGREGATOR_HOST_SUFFIXES）→ [null, null]（出さない）
     *  - mlit.go.jp  → 「国土交通省の紹介ページ」
     *  - ogb.go.jp   → 「内閣府沖縄総合事務局の紹介ページ」
     *  - それ以外    → 「公式サイト」
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function resolveOfficial(?string $url): array
    {
        if ($url === null || trim($url) === '') {
            return [null, null];
        }
        $host = parse_url($url, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return [null, null];
        }
        $host = strtolower($host);

        foreach (self::AGGREGATOR_HOST_SUFFIXES as $suffix) {
            if ($host === $suffix || str_ends_with($host, '.'.$suffix)) {
                return [null, null]; // まとめサイト → リンクを出さない
            }
        }

        if ($host === 'mlit.go.jp' || str_ends_with($host, '.mlit.go.jp')) {
            return [$url, '国土交通省の紹介ページ'];
        }
        if ($host === 'ogb.go.jp' || str_ends_with($host, '.ogb.go.jp')) {
            return [$url, '内閣府沖縄総合事務局の紹介ページ'];
        }

        return [$url, '公式サイト'];
    }

    /**
     * 周辺情報7本を [key => array<array{label,url,distance_km}>] で返す。
     * Eloquent モデルのままキャッシュせず、素の配列に落としてから返す。
     *
     * @return array<string, array<int, array{label: string, url: string, distance_km: float}>>
     */
    private function buildNearby(RoadsideStation $station): array
    {
        $lat = (float) $station->latitude;
        $lng = (float) $station->longitude;

        // POI は詳細ページが無いため、リンク先はライダーズマップに座標＋レイヤーを付ける
        // （レンタルガレージ詳細の「近くの洗車場」と同じ書き方）。
        $poiItem = fn ($r, string $layer): array => [
            'label' => $r->display_name,
            'url' => route('riders.map', ['lat' => $r->latitude, 'lng' => $r->longitude, 'zoom' => 16, 'layer' => $layer]),
            'distance_km' => (float) $r->dist_km,
        ];

        return [
            // 近くのガソリンスタンド 10km / 5件
            'gas_stations' => $this->nearby(Poi::query()->where('type', 'gas_station'), $lat, $lng, 10.0, 5)
                ->map(fn ($r) => $poiItem($r, 'gas_station'))->all(),
            // 近くのコンビニ 5km / 5件
            'convenience_stores' => $this->nearby(Poi::query()->where('type', 'convenience_store'), $lat, $lng, 5.0, 5)
                ->map(fn ($r) => $poiItem($r, 'convenience_store'))->all(),
            // 近くの洗車場 15km / 3件
            'car_washes' => $this->nearby(Poi::query()->where('type', 'car_wash'), $lat, $lng, 15.0, 3)
                ->map(fn ($r) => $poiItem($r, 'car_wash'))->all(),
            // 近くのバイクショップ 15km / 5件（Shop には公開フラグが無く、座標付きは全て公開扱い＝地図と同挙動）
            'shops' => $this->nearby(Shop::query(), $lat, $lng, 15.0, 5)
                ->map(fn ($r) => [
                    'label' => (string) $r->name,
                    'url' => route('shops.show', $r->id),
                    'distance_km' => (float) $r->dist_km,
                ])->all(),
            // 近くのバイク駐車場 10km / 3件（公開のみ）
            'parkings' => $this->nearby(BikeParking::query()->where('is_active', true), $lat, $lng, 10.0, 3)
                ->map(fn ($r) => [
                    'label' => (string) $r->name,
                    'url' => route('parking.show', $r->id),
                    'distance_km' => (float) $r->dist_km,
                ])->all(),
            // 近くのレンタルガレージ 20km / 3件（公開のみ）
            'rental_garages' => $this->nearby(RentalGarage::query()->where('is_active', true), $lat, $lng, 20.0, 3)
                ->map(fn ($r) => [
                    'label' => (string) $r->name,
                    'url' => route('rental-garage.show', $r->id),
                    'distance_km' => (float) $r->dist_km,
                ])->all(),
            // 近くの道の駅 50km / 5件（自駅を除く）
            'roadside_stations' => $this->nearby(RoadsideStation::query()->where('id', '!=', $station->id), $lat, $lng, 50.0, 5)
                ->map(fn ($r) => [
                    'label' => (string) $r->name,
                    'url' => route('michinoeki.show', $r->station_code),
                    'distance_km' => (float) $r->dist_km,
                ])->all(),
        ];
    }

    /** 座標が無いときの空の周辺情報（キーはビューと揃える）。 */
    private function emptyNearby(): array
    {
        return [
            'gas_stations' => [],
            'convenience_stores' => [],
            'car_washes' => [],
            'shops' => [],
            'parkings' => [],
            'rental_garages' => [],
            'roadside_stations' => [],
        ];
    }

    /**
     * 与えたクエリを矩形で粗く絞り、正距円筒近似の dist_sq で距離順に $limit 件取得。
     * 取得後、PHP 側で haversine の実距離(km)を dist_km として付与して返す。
     */
    private function nearby(Builder $query, float $lat, float $lng, float $km, int $limit): Collection
    {
        $dLat = $km / 111.0;
        $dLng = $km / (111.0 * max(0.01, abs(cos(deg2rad($lat)))));

        return $query
            ->whereNotNull('latitude')->whereNotNull('longitude')
            ->whereBetween('latitude', [$lat - $dLat, $lat + $dLat])
            ->whereBetween('longitude', [$lng - $dLng, $lng + $dLng])
            // 正距円筒近似（緯度で経度差を補正）。単調なので順序は正しい。
            ->selectRaw('*, (POW(latitude - ?, 2) + POW((longitude - ?) * COS(RADIANS(?)), 2)) AS dist_sq', [$lat, $lng, $lat])
            ->orderBy('dist_sq')
            ->limit($limit)
            ->get()
            ->each(fn ($r) => $r->setAttribute(
                'dist_km',
                $this->haversineKm($lat, $lng, (float) $r->latitude, (float) $r->longitude)
            ));
    }

    /** 2点間の距離(km)。表示用の実距離（正距円筒の dist_sq は並べ替え専用）。 */
    private function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * 都道府県ごとの駅数 [prefecture => count]（1クエリ集計・24時間キャッシュ）。
     * index と area フッターの両方で共用。
     *
     * @return array<string, int>
     */
    private function prefectureCounts(): array
    {
        return Cache::remember('roadside_pref_counts', 86400, fn () => RoadsideStation::query()
            ->selectRaw('prefecture, COUNT(*) as cnt')
            ->whereNotNull('prefecture')
            ->groupBy('prefecture')
            ->pluck('cnt', 'prefecture')
            ->map(fn ($v) => (int) $v)
            ->all());
    }

    /**
     * 全47都道府県 [prefecture, count] を地方区分順にフラット化して返す（フッターリンク用）。
     *
     * @return array<int, array{prefecture: string, count: int}>
     */
    private function allPrefecturesWithCounts(): array
    {
        $counts = $this->prefectureCounts();
        $out = [];
        foreach (self::REGIONS as $prefs) {
            foreach ($prefs as $pref) {
                $out[] = ['prefecture' => $pref, 'count' => $counts[$pref] ?? 0];
            }
        }

        return $out;
    }

    /**
     * 一覧・都道府県別ページの回遊リンク（詳細ページと同じ4本）。
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
