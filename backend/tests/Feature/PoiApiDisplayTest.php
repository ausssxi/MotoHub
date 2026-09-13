<?php

declare(strict_types=1);

use App\Models\Poi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

/** @param array<string,mixed> $attrs */
function apiPoi(array $attrs): Poi
{
    static $osm = 1000;

    $poi = (new Poi)->forceFill(array_merge([
        'osm_id' => $osm++,
        'latitude' => 35.68,
        'longitude' => 139.76,
    ], $attrs));
    $poi->save();

    return $poi;
}

// 丸の内周辺（35.6〜35.7 / 139.7〜139.8）に収まる座標を使う。
$bounds = ['ne_lat' => 35.700, 'ne_lng' => 139.800, 'sw_lat' => 35.600, 'sw_lng' => 139.700];

it('returns display, prefecture and city in the API payload', function () use ($bounds) {
    apiPoi([
        'type' => 'gas_station',
        'name' => null,
        'brand' => 'エネオス',
        'address' => '東京都千代田区丸の内',
        'prefecture' => '東京都',
        'city' => '千代田区',
    ]);

    $res = $this->getJson('/api/pois?type=gas_station&'.http_build_query($bounds))->assertOk();

    $item = collect($res->json())->firstWhere('brand', 'エネオス');
    expect($item)->not->toBeNull();
    expect($item['display'])->toBe('ENEOS（丸の内）'); // 地図＝詳細ページと一致
    expect($item['prefecture'])->toBe('東京都');
    expect($item['city'])->toBe('千代田区');
});

it('uses the v2 cache key (old-gen key untouched)', function () use ($bounds) {
    apiPoi([
        'type' => 'gas_station', 'brand' => 'エネオス', 'address' => '東京都千代田区丸の内',
        'prefecture' => '東京都', 'city' => '千代田区',
    ]);

    $this->getJson('/api/pois?type=gas_station&'.http_build_query($bounds))->assertOk();

    $v2 = 'pois:v2:gas_station:35.600:139.700:35.700:139.800';
    $old = 'pois:gas_station:35.600:139.700:35.700:139.800';
    expect(Cache::has($v2))->toBeTrue();
    expect(Cache::has($old))->toBeFalse();
});

it('resolves car_wash display with facility label (map = detail page)', function () use ($bounds) {
    apiPoi([
        'type' => 'car_wash',
        'name' => null,
        'brand' => null,
        'address' => '東京都千代田区丸の内',
        'prefecture' => '東京都',
        'city' => '千代田区',
        'self_service' => 'yes',
        'automated' => 'yes',
    ]);

    $res = $this->getJson('/api/pois?type=car_wash&'.http_build_query($bounds))->assertOk();

    $item = collect($res->json())->first();
    expect($item['display'])->toBe('コイン洗車場（丸の内）');
});
