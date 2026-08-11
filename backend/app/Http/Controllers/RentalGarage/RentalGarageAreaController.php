<?php

declare(strict_types=1);

namespace App\Http\Controllers\RentalGarage;

use App\Http\Controllers\Controller;
use App\Http\Controllers\RoadsideStation\RoadsideStationController;
use App\Models\RentalGarage;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

/**
 * レンタルガレージの「エリアから探す」一覧（公開・認証なし）。
 *
 * 詳細（/rental-garages/{id}）しか入口が無く、1,032件のデータへユーザーも検索エンジンも
 * 辿り着けなかったため、一覧トップ／都道府県別／市区町村別を追加する。
 *
 * 構成は PoiAreaController（/gs・/senshajo）と ParkingAreaController（/parking/area）に合わせる:
 *   index / prefecture / city の3アクション、都道府県は正準リストで検証、
 *   一覧・都道府県別は24時間キャッシュ、回遊リンクは x-cross-links。
 *
 * 掲載条件は詳細ページ・サイトマップと同一（publicScope 参照）。
 */
final class RentalGarageAreaController extends Controller
{
    /** 一覧・都道府県別の集計キャッシュTTL（秒）。poi_area と同じ24時間。 */
    private const CACHE_TTL = 86400;

    /**
     * 一覧に載せる条件。詳細ページ（RentalGarageController::show）と
     * サイトマップ（GenerateSitemap 4.5b）の条件をそのまま踏襲する。
     *
     *  - is_active = true          … 詳細ページが404にする行を一覧に出さない
     *  - source='user' かつ is_verified=false は除外
     *    … 詳細ページが noindex にする行。index可能な一覧から noindex ページへ送らないため、
     *      また未確認のユーザー投稿を集約して見せないため。
     */
    private function publicScope(): Builder
    {
        return RentalGarage::query()
            ->where('is_active', true)
            ->where(fn (Builder $q) => $q->where('source', '!=', 'user')->orWhere('is_verified', true));
    }

    /**
     * 一覧トップ（地方区分ごとに都道府県と件数）。
     */
    public function index(): View
    {
        $data = Cache::remember('rental_garage_area_index', self::CACHE_TTL, function (): array {
            $counts = $this->prefectureCounts();

            $total = 0;
            $regions = [];
            foreach (RoadsideStationController::regions() as $region => $prefs) {
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

        return view('rental_garage.area-index', array_merge($data, [
            'crossLinks' => $this->crossLinks(),
        ]));
    }

    /**
     * 都道府県別（市区町村を件数付きで一覧）。該当0件は404。
     */
    public function prefecture(string $prefecture): View
    {
        if (! in_array($prefecture, RoadsideStationController::prefectures(), true)) {
            abort(404);
        }

        $data = Cache::remember("rental_garage_area_pref:{$prefecture}", self::CACHE_TTL, function () use ($prefecture): ?array {
            $cities = $this->publicScope()
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

            return [
                'prefecture' => $prefecture,
                'count' => array_sum(array_column($cities, 'count')),
                'cities' => $cities,
            ];
        });

        if ($data === null) {
            abort(404);
        }

        return view('rental_garage.area-prefecture', array_merge($data, [
            'allPrefectures' => $this->allPrefecturesWithCounts(),
            'crossLinks' => $this->crossLinks(),
        ]));
    }

    /**
     * 市区町村別のガレージ一覧。該当0件は404。
     * 詳細ページへ繋ぐための最小限（名称・所在地・種別・月額・区画サイズ・設備）だけを渡す。
     */
    public function city(string $prefecture, string $city): View
    {
        if (! in_array($prefecture, RoadsideStationController::prefectures(), true)) {
            abort(404);
        }

        $garages = $this->publicScope()
            ->where('prefecture', $prefecture)
            ->where('city', $city)
            ->orderBy('name')
            ->orderBy('id')
            ->get([
                'id', 'name', 'operator', 'garage_type', 'address',
                'monthly_fee_min', 'monthly_fee_max', 'size_text',
                'is_24h', 'has_power', 'has_security', 'has_shutter',
            ]);

        if ($garages->isEmpty()) {
            abort(404);
        }

        $items = $garages->map(fn (RentalGarage $g): array => [
            'id' => $g->id,
            'name' => (string) $g->name,
            'operator' => filled($g->operator) ? (string) $g->operator : null,
            'typeLabel' => $g->garage_type_label,
            'address' => filled($g->address) ? (string) $g->address : null,
            'feeText' => $this->feeText($g),
            // size_text は未投入の行があるため null 前提で扱う（ビュー側で行ごと出し分け）。
            'sizeText' => filled($g->size_text) ? (string) $g->size_text : null,
            // 設備は true のみバッジ表示。false（なし）と null（不明）は出さない。
            'facilities' => array_keys(array_filter([
                '24時間出入り' => $g->is_24h === true,
                '電源' => $g->has_power === true,
                '防犯設備' => $g->has_security === true,
                'シャッター' => $g->has_shutter === true,
            ])),
        ])->all();

        return view('rental_garage.area-city', [
            'prefecture' => $prefecture,
            'city' => $city,
            'count' => $garages->count(),
            'items' => $items,
            'crossLinks' => $this->crossLinks(),
        ]);
    }

    /**
     * 月額の表示文字列。rental_garage/show.blade.php と同じ出し分け。両方 null なら null。
     */
    private function feeText(RentalGarage $garage): ?string
    {
        $min = $garage->monthly_fee_min;
        $max = $garage->monthly_fee_max;

        if ($min && $max) {
            return number_format((int) $min).'〜'.number_format((int) $max).'円';
        }
        if ($min) {
            return number_format((int) $min).'円〜';
        }
        if ($max) {
            return '〜'.number_format((int) $max).'円';
        }

        return null;
    }

    /**
     * 都道府県ごとの掲載件数 [prefecture => count]（1クエリ集計・24時間キャッシュ）。
     *
     * @return array<string, int>
     */
    private function prefectureCounts(): array
    {
        return Cache::remember('rental_garage_area_pref_counts', self::CACHE_TTL, fn (): array => $this->publicScope()
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
    private function allPrefecturesWithCounts(): array
    {
        $counts = $this->prefectureCounts();
        $out = [];
        foreach (RoadsideStationController::regions() as $prefs) {
            foreach ($prefs as $pref) {
                $out[] = ['prefecture' => $pref, 'count' => $counts[$pref] ?? 0];
            }
        }

        return $out;
    }

    /**
     * 回遊リンク（詳細ページ RentalGarageController::show と同じ4本）。
     *
     * @return array<int, array{label: string, url: string, icon: string, description: string}>
     */
    private function crossLinks(): array
    {
        return [
            ['label' => 'ライダーズマップ', 'url' => route('riders.map'), 'icon' => 'map', 'description' => 'ガレージ・洗車場・GSを地図で'],
            ['label' => '駐車場マップ', 'url' => route('parking.index'), 'icon' => 'square-parking', 'description' => 'バイク駐車場を探す'],
            ['label' => '中古バイク検索', 'url' => route('bikes.search'), 'icon' => 'search', 'description' => '全国の在庫を検索'],
            ['label' => 'バイク盗難データ', 'url' => route('theft'), 'icon' => 'shield-alert', 'description' => '盗難対策に安全な保管を'],
        ];
    }
}
