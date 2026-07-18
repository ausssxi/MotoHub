<?php

declare(strict_types=1);

use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::flush());

function coordShop(string $name, float $lat, float $lng): Shop
{
    $s = new Shop;
    $s->forceFill([
        'name' => $name,
        'address' => 'addr-'.uniqid(),
        'latitude' => $lat,
        'longitude' => $lng,
        'prefecture' => '東京都',
        'source' => Shop::SOURCE_SCRAPER,
    ])->save();

    return $s;
}

// ---- 段階1: 県庁/市の代表点（同一座標≥50店）の団子を map API から非表示 ----

it('hides shops piled on a coarse representative point (同一座標≥50店) from the map API', function () {
    // 都庁代表点に 50 店（誤ジオコーディングの団子）
    for ($i = 0; $i < 50; $i++) {
        coordShop("県庁ダンゴ{$i}", 35.689503, 139.691727);
    }
    // 正常な個別座標の店
    coordShop('正常店B', 35.700000, 139.700000);
    // 同一ビルの少数共有（3 < 50）＝閾値未満なので表示される（誤検知しない）
    coordShop('同ビル1', 35.695000, 139.695000);
    coordShop('同ビル2', 35.695000, 139.695000);
    coordShop('同ビル3', 35.695000, 139.695000);

    $rows = collect($this->getJson('/shops/api/area?ne_lat=36&ne_lng=140&sw_lat=35&sw_lng=139')->assertOk()->json());
    $names = $rows->pluck('name');

    // 団子（≥50）は非表示
    expect($names->contains('県庁ダンゴ0'))->toBeFalse()
        ->and($names->filter(fn ($n) => str_starts_with($n, '県庁ダンゴ'))->count())->toBe(0)
        // 正常店・閾値未満(3店)の同一座標は表示維持
        ->and($names->contains('正常店B'))->toBeTrue()
        ->and($names->contains('同ビル1'))->toBeTrue();
});

it('does not hide anything when no coordinate reaches the threshold', function () {
    coordShop('店A', 35.68, 139.76);
    coordShop('店B', 35.69, 139.77);

    $rows = collect($this->getJson('/shops/api/area?ne_lat=36&ne_lng=140&sw_lat=35&sw_lng=139')->assertOk()->json());

    expect(collect($rows)->pluck('name'))->toContain('店A', '店B');
});
