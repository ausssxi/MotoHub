<?php

declare(strict_types=1);

namespace App\Http\Controllers\RentalGarage;

use App\Http\Controllers\Controller;
use App\Http\Requests\RentalGarage\StoreRentalGarageRequest;
use App\Models\RentalGarage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

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

        // 防御3 重複チェック: 半径100m以内に同名または同運営があれば拒否し、既存への導線を示す。
        $dup = $this->findNearbyDuplicate($data);
        if ($dup !== null) {
            return back()->withInput()
                ->with('duplicate', [
                    'name' => $dup->name,
                    'address' => (string) $dup->address,
                    'map_url' => route('riders.map'),
                ])
                ->withErrors(['name' => "半径100m以内に「{$dup->name}」が既に登録されています。地図でご確認ください。"]);
        }

        RentalGarage::create($data + [
            'source' => 'user',
            'is_active' => true,
            'is_verified' => false,
            // Leaflet のピン座標なので権威扱い。ジオコーダーで上書きしない（source は geocode 対象外）。
            'geocode_status' => 'source',
            'submitted_by' => $request->user()?->id,
            'source_url' => null, // ユーザー投稿は常に null（unique制約下でも MySQL は複数NULLを許容）
        ]);

        // 投稿座標を中心に・レンタルガレージのレイヤーONで着地（map.js が lat/lng/zoom を読む）。
        return $this->registeredRedirect((float) $data['latitude'], (float) $data['longitude']);
    }

    private function registeredRedirect(?float $lat = null, ?float $lng = null): RedirectResponse
    {
        $params = ($lat !== null && $lng !== null)
            ? ['lat' => $lat, 'lng' => $lng, 'zoom' => 16, 'layer' => 'rental_garage']
            : [];

        return redirect()->route('riders.map', $params)
            ->with('success', 'レンタルガレージを登録しました！ありがとうございます。');
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
