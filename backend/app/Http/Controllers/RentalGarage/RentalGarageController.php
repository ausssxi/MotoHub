<?php

declare(strict_types=1);

namespace App\Http\Controllers\RentalGarage;

use App\Http\Controllers\Controller;
use App\Http\Requests\RentalGarage\StoreRentalGarageRequest;
use App\Models\BikeParking;
use App\Models\Poi;
use App\Models\RentalGarage;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;

final class RentalGarageController extends Controller
{
    /**
     * 投稿フォーム（駐輪場 parking.create と同構成）。
     */
    public function create(): View
    {
        return view('rental_garage.create');
    }

    /**
     * 詳細ページ（公開）。is_active=false は404。
     */
    public function show(int $id): View
    {
        $garage = RentalGarage::query()->where('id', $id)->where('is_active', true)->firstOrFail();

        $lat = (float) $garage->latitude;
        $lng = (float) $garage->longitude;
        $hasCoords = $garage->latitude !== null && $garage->longitude !== null;

        // 半径3km以内の関連（薄いページ対策の内部リンク）。
        $nearbyGarages = $hasCoords ? $this->nearby(
            RentalGarage::query()->where('is_active', true)->where('id', '!=', $garage->id), $lat, $lng
        ) : new Collection();
        $nearbyCarWashes = $hasCoords ? $this->nearby(
            Poi::query()->where('type', 'car_wash'), $lat, $lng
        ) : new Collection();
        $nearbyParkings = $hasCoords ? $this->nearby(
            BikeParking::query()->where('is_active', true), $lat, $lng
        ) : new Collection();

        // 同一都道府県の月額中央値（比較の一文用）。
        $prefMedian = $garage->prefecture ? $this->prefectureMonthlyMedian($garage->prefecture) : null;

        // source=user かつ未確認は noindex。
        $noindex = $garage->source === 'user' && ! $garage->is_verified;

        $crossLinks = [
            ['label' => 'ライダーズマップ', 'url' => route('riders.map'), 'icon' => 'map', 'description' => 'ガレージ・洗車場・GSを地図で'],
            ['label' => '駐車場マップ', 'url' => route('parking.index'), 'icon' => 'square-parking', 'description' => 'バイク駐車場を探す'],
            ['label' => '中古バイク検索', 'url' => route('bikes.search'), 'icon' => 'search', 'description' => '全国の在庫を検索'],
            ['label' => 'バイク盗難データ', 'url' => route('theft'), 'icon' => 'shield-alert', 'description' => '盗難対策に安全な保管を'],
        ];

        return view('rental_garage.show', compact(
            'garage', 'nearbyGarages', 'nearbyCarWashes', 'nearbyParkings', 'prefMedian', 'noindex', 'crossLinks'
        ));
    }

    /**
     * 与えたクエリから半径3km以内のレコードを近い順に最大5件返す（バウンディングボックス＋ハバーサイン）。
     */
    private function nearby(Builder $query, float $lat, float $lng): Collection
    {
        $latDelta = 3 / 111.0; // ~3km
        $lngDelta = 3 / (111.0 * max(cos(deg2rad($lat)), 0.01));

        return $query
            ->whereNotNull('latitude')->whereNotNull('longitude')
            ->whereBetween('latitude', [$lat - $latDelta, $lat + $latDelta])
            ->whereBetween('longitude', [$lng - $lngDelta, $lng + $lngDelta])
            ->limit(60)
            ->get()
            ->each(fn ($r) => $r->setAttribute('dist_m', $this->haversineMeters($lat, $lng, (float) $r->latitude, (float) $r->longitude)))
            ->filter(fn ($r) => $r->dist_m <= 3000)
            ->sortBy('dist_m')
            ->take(5)
            ->values();
    }

    /** 中央値の信頼できる最小サンプル数。これ未満の都道府県では比較文を出さない。 */
    private const MEDIAN_MIN_SAMPLE = 5;

    /**
     * 同一都道府県・公開中のレンタルガレージの月額下限の中央値（円）。
     * monthly_fee_min が null の行は必ず除外。サンプルが MEDIAN_MIN_SAMPLE 未満なら null（＝比較文非表示）。
     */
    private function prefectureMonthlyMedian(string $prefecture): ?int
    {
        $vals = RentalGarage::query()
            ->where('is_active', true)
            ->where('prefecture', $prefecture)
            ->whereNotNull('monthly_fee_min')
            ->pluck('monthly_fee_min')
            ->map(fn ($v) => (int) $v)
            ->sort()
            ->values();

        $n = $vals->count();
        if ($n < self::MEDIAN_MIN_SAMPLE) {
            return null; // 1件の県で「中央値＝自身」になる無意味な比較を防ぐ
        }
        $mid = intdiv($n, 2);

        return $n % 2 === 1 ? $vals[$mid] : (int) (($vals[$mid - 1] + $vals[$mid]) / 2);
    }

    /**
     * 投稿処理。防御3点（throttle=ルート側 / honeypot / 100m重複）を備える。
     */
    public function store(StoreRentalGarageRequest $request): RedirectResponse
    {
        // 防御2 ハニーポット: ボットは保存せず、成功を装って破棄（エラーも出さない）。
        if ($request->honeypotTriggered) {
            return $this->registeredRedirect();
        }

        $data = $request->validated();

        // 設備3択（1=あり / 0=なし / 未指定=不明）→ boolean|null。null と false を厳密に区別。
        foreach (['is_24h', 'has_power', 'has_security', 'has_shutter'] as $key) {
            $data[$key] = match ($request->input($key)) {
                '1' => true,
                '0' => false,
                default => null,
            };
        }

        // 月額の上下限整合（両方あるとき下限≤上限）。
        if (isset($data['monthly_fee_min'], $data['monthly_fee_max'])
            && $data['monthly_fee_max'] < $data['monthly_fee_min']) {
            return back()->withInput()->withErrors(['monthly_fee_max' => '月額の上限は下限以上で入力してください。']);
        }

        // 防御3 重複チェック: 半径100m以内に同名または同運営があれば拒否し、既存の詳細ページへ導線を示す。
        $dup = $this->findNearbyDuplicate($data);
        if ($dup !== null) {
            return back()->withInput()
                ->with('duplicate', [
                    'name' => $dup->name,
                    'address' => (string) $dup->address,
                    'url' => route('rental-garage.show', $dup->id),
                ])
                ->withErrors(['name' => "半径100m以内に「{$dup->name}」が既に登録されています。既存の詳細ページをご確認ください。"]);
        }

        RentalGarage::create($data + [
            'source' => 'user',
            // 承認制: ユーザー投稿は管理画面で確認するまで非公開（店舗投稿 shop_submissions と同作法）。
            // 未確認の地図情報が即公開されると、実在しない保管場所へ人を誘導しかねないため。
            'is_active' => false,
            'is_verified' => false,
            // Leaflet のピン座標なので権威扱い。ジオコーダーで上書きしない（source は geocode 対象外）。
            'geocode_status' => 'source',
            'submitted_by' => $request->user()?->id,
            'source_url' => null, // ユーザー投稿は常に null（unique制約下でも MySQL は複数NULLを許容）
        ]);

        // 確認後に公開される旨を投稿フォームで通知（is_active=false のため詳細ページは404・着地させない）。
        return $this->registeredRedirect();
    }

    private function registeredRedirect(): RedirectResponse
    {
        // 投稿後の着地。honeypot 破棄時もこれを使い、本物の成功と同じ着地・文言でボットに気取らせない。
        return redirect()->route('rental-garage.create')
            ->with('submission_success', '1');
    }

    /**
     * 半径100m以内に「同名」または「同運営会社」の既存レコードがあれば返す。
     * バウンディングボックスで粗く絞り、ハバーサイン（ParkingService と同式・R=6371000）で厳密判定。
     */
    private function findNearbyDuplicate(array $data): ?RentalGarage
    {
        $lat = (float) $data['latitude'];
        $lng = (float) $data['longitude'];
        $name = (string) $data['name'];
        $operator = $data['operator'] ?? null;

        // 約100m 相当の粗フィルタ（緯度 ~0.001度 ≒ 111m、経度は緯度で補正）。
        $latDelta = 0.001;
        $lngDelta = 0.001 / max(cos(deg2rad($lat)), 0.01);

        $candidates = RentalGarage::query()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereBetween('latitude', [$lat - $latDelta, $lat + $latDelta])
            ->whereBetween('longitude', [$lng - $lngDelta, $lng + $lngDelta])
            ->where(function ($q) use ($name, $operator) {
                $q->where('name', $name);
                if ($operator !== null && $operator !== '') {
                    $q->orWhere('operator', $operator);
                }
            })
            ->get(['id', 'name', 'address', 'operator', 'latitude', 'longitude']);

        foreach ($candidates as $c) {
            if ($this->haversineMeters($lat, $lng, (float) $c->latitude, (float) $c->longitude) <= 100.0) {
                return $c;
            }
        }

        return null;
    }

    private function haversineMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
