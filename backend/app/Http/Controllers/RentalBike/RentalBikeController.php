<?php

declare(strict_types=1);

namespace App\Http\Controllers\RentalBike;

use App\Http\Controllers\Controller;
use App\Http\Controllers\RoadsideStation\RoadsideStationController;
use App\Models\RentalBikePrice;
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

        // 車格別の参考価格（第2段階・ヤマハ/二輪処のみデータあり。無ければ空＝表示しない）。
        // 件数は最大5行なので都度クエリで足りる（キャッシュしない＝取得直後に反映される）。
        $prices = RentalBikePrice::query()
            ->where('company_slug', $shop->company_slug)
            ->fresh() // ★取得から70日を超えた料金は出さない（自動更新が止まっても古い料金を出し続けない）
            ->get()
            // ★車格の並びは 原付→125cc→250cc→400cc→大型 に固定（array_search が index 0 を返す原付を
            //   末尾に送らないよう false を明示判定する）。
            ->sortBy(function (RentalBikePrice $p): int {
                $i = array_search($p->vehicle_class, RentalBikePrice::CLASS_ORDER, true);

                return $i === false ? 99 : $i;
            })
            ->values();
        $pricesFetchedAt = $prices->max('fetched_at');

        return view('rental_bike.show', compact(
            'shop', 'nearbyStations', 'nearbyGarages', 'prices', 'pricesFetchedAt'
        ) + ['crossLinks' => $this->crossLinks()]);
    }

    /** 事業者の店舗数を数字で出す下限。これ未満は数字を伏せて「店舗を探す」導線のみにする。 */
    private const PRICE_STORE_COUNT_MIN = 10;

    /**
     * 料金比較ハブ（/rental-bikes/price）。5車格への入口。
     * ★車格の集合は config('rental_bike.price_page_classes') が正本。
     */
    public function priceIndex(): View
    {
        $classes = config('rental_bike.price_page_classes', []);

        // 各車格に「表示できる（鮮度内の）料金が何社あるか」を添える（0社の車格も入口は出す＝公式リンクで拾う）。
        $counts = Cache::remember('rental_bike_price_class_counts_v1', self::CACHE_TTL, fn (): array => RentalBikePrice::query()
            ->fresh()
            ->selectRaw('vehicle_class, COUNT(DISTINCT company_slug) as cnt')
            ->groupBy('vehicle_class')
            ->pluck('cnt', 'vehicle_class')
            ->map(fn ($v) => (int) $v)
            ->all());

        $cards = [];
        foreach ($classes as $slug => $vehicleClass) {
            $cards[] = [
                'slug' => (string) $slug,
                'vehicle_class' => (string) $vehicleClass,
                'providers' => $counts[$vehicleClass] ?? 0,
            ];
        }

        return view('rental_bike.price_index', [
            'cards' => $cards,
            'crossLinks' => $this->crossLinks(),
        ]);
    }

    /**
     * 車格別の料金比較（/rental-bikes/price/{class}）。事業者ごとの参考料金を1画面で並べる。
     * ★時間制（ヤマハ=24時間）と日数制（二輪処=1日）は等価でないため、条件を明示して単純比較はしない。
     * ★819・AJ は料金を持たない（店舗ごとに異なる）＝公式リンクのみ。
     */
    public function priceShow(string $class): View
    {
        $classes = config('rental_bike.price_page_classes', []);
        if (! array_key_exists($class, $classes)) {
            abort(404);
        }
        $vehicleClass = (string) $classes[$class];

        $directory = $this->providerDirectory();

        // 鮮度内の料金行（この車格）。安い順。条件差があるため「最安」表記はしない。
        $rows = RentalBikePrice::query()
            ->where('vehicle_class', $vehicleClass)
            ->fresh() // ★70日超は出さない
            ->orderBy('price_yen')
            ->get()
            ->map(function (RentalBikePrice $p) use ($directory): array {
                $info = $directory[$p->company_slug] ?? null;
                $common = config('rental_bike.pricing_links.'.$p->company_slug);

                return [
                    'company_slug' => $p->company_slug,
                    'company' => $info['label'] ?? (config('rental_bike.company_names.'.$p->company_slug) ?? $p->company_slug),
                    'shop_count' => $info['count'] ?? 0,
                    'price_yen' => (int) $p->price_yen,
                    'price_is_from' => (bool) $p->price_is_from,
                    'plan_label' => (string) $p->plan_label,
                    'note' => $p->note,
                    // 公式の料金ページ（common＝全社共通ページのみ。per_shop はここには来ない）。
                    'official_url' => (is_array($common) && ($common['mode'] ?? '') === 'common') ? ($common['url'] ?? null) : null,
                ];
            })
            ->values();

        $pricesFetchedAt = RentalBikePrice::query()
            ->where('vehicle_class', $vehicleClass)
            ->fresh()
            ->max('fetched_at');
        $pricesFetchedAt = $pricesFetchedAt ? \Illuminate\Support\Carbon::parse($pricesFetchedAt) : null;

        // 料金を持たない per_shop 事業者（819・AJ）。店舗があるものだけ「公式で確認」カードに出す。
        $perShop = [];
        foreach ((array) config('rental_bike.pricing_links', []) as $slug => $link) {
            if (($link['mode'] ?? '') !== 'per_shop') {
                continue;
            }
            $info = $directory[$slug] ?? null;
            if ($info === null || ($info['count'] ?? 0) < 1) {
                continue; // 店舗が無ければ出さない
            }
            $perShop[] = [
                'company_slug' => (string) $slug,
                'company' => $info['label'],
                'shop_count' => $info['count'],
            ];
        }

        // FAQ（★実データと事実だけ。根拠のない相場・金額は書かない）。表示とJSON-LDで同一文言を使う。
        $faqs = $this->priceFaqs($vehicleClass, $rows, $perShop, $pricesFetchedAt);

        return view('rental_bike.price_show', [
            'classSlug' => $class,
            'vehicleClass' => $vehicleClass,
            'rows' => $rows,
            'perShop' => $perShop,
            'pricesFetchedAt' => $pricesFetchedAt,
            'faqs' => $faqs,
            'storeCountMin' => self::PRICE_STORE_COUNT_MIN,
            'otherClasses' => $classes,
            'crossLinks' => $this->crossLinks(),
        ]);
    }

    /**
     * 事業者ディレクトリ [company_slug => ['label' => 会社名, 'count' => 公開店舗数]]。
     * 表示名・店舗数とも公開店舗（is_active）から実データで作る。24時間キャッシュ。
     *
     * @return array<string, array{label: string, count: int}>
     */
    private function providerDirectory(): array
    {
        return Cache::remember('rental_bike_provider_dir_v1', self::CACHE_TTL, function (): array {
            $out = [];
            $this->publicScope()
                ->whereNotNull('company_slug')
                ->selectRaw('company_slug, MAX(company) as company, COUNT(*) as cnt')
                ->groupBy('company_slug')
                ->get()
                ->each(function ($r) use (&$out): void {
                    $slug = (string) $r->company_slug;
                    $out[$slug] = [
                        'label' => filled($r->company) ? (string) $r->company : (config('rental_bike.company_names.'.$slug) ?? $slug),
                        'count' => (int) $r->cnt,
                    ];
                });

            return $out;
        });
    }

    /**
     * 料金比較ページの FAQ（事実のみ）。回答は $rows（実料金）と確定事実（保険別・公式で確認）だけで作る。
     *
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $rows
     * @param  array<int, array<string, mixed>>  $perShop
     * @return array<int, array{q: string, a: string}>
     */
    private function priceFaqs(string $vehicleClass, Collection $rows, array $perShop, ?\Illuminate\Support\Carbon $fetchedAt): array
    {
        $faqs = [];
        $asOf = $fetchedAt ? $fetchedAt->format('Y年n月') : null;

        if ($rows->isNotEmpty()) {
            // 各社の実料金だけを列挙（相場・平均は作らない）。
            $parts = $rows->map(function (array $r): string {
                $price = '¥'.number_format($r['price_yen']).($r['price_is_from'] ? '〜' : '');

                return $r['company'].'が'.$r['plan_label'].'で'.$price;
            })->all();
            $faqs[] = [
                'q' => 'レンタルバイク（'.$vehicleClass.'）の料金はいくらくらいですか？',
                'a' => '参考価格として、'.implode('、', $parts).'です。'
                    .($asOf ? $asOf.'時点の参考価格で、' : '')
                    .'保険・補償は別途です。時間の数え方が事業者ごとに異なるため単純な比較はできません。最新の料金は各公式サイトでご確認ください。',
            ];
        }

        if ($perShop !== []) {
            $names = array_map(fn (array $p): string => $p['company'], $perShop);
            $faqs[] = [
                'q' => implode('・', $names).'の'.$vehicleClass.'の料金は？',
                'a' => implode('・', $names).'は店舗ごとに料金が異なるため、各店舗の公式ページでご確認ください。',
            ];
        }

        $faqs[] = [
            'q' => '表示されている料金に保険は含まれますか？',
            'a' => '含まれていません。保険・補償は別途で、金額は事業者・プランにより異なります。最新の料金・補償内容は各公式サイトでご確認ください。',
        ];

        return $faqs;
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
