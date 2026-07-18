<?php

declare(strict_types=1);

use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function mapShop(string $name, float $lat = 35.68, float $lng = 139.76): Shop
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

// ---- チェーン判定ヘルパ（config/bike.php pattern・チェーン横断ページと同一） ----

it('resolves the chain slug from a shop name (null for non-chain)', function () {
    expect(Shop::chainSlug('レッドバロン府中'))->toBe('red-baron')
        ->and(Shop::chainSlug('バイク王 なんば店'))->toBe('bikeo')
        ->and(Shop::chainSlug('カワサキ プラザ横浜'))->toBeNull()   // 半角空白入りは pattern 不一致
        ->and(Shop::chainSlug('カワサキプラザ横浜'))->toBe('kawasaki-plaza')
        ->and(Shop::chainSlug('街の個人バイク店'))->toBeNull()
        ->and(Shop::chainSlug(null))->toBeNull();
});

// ---- マップAPIが各shopに chain slug を付与 ----

it('tags each shop with its chain slug in the /shops/api/area payload', function () {
    mapShop('レッドバロン東京');
    mapShop('バイク館 立川店');
    mapShop('街の個人バイク店');

    $rows = collect($this->getJson('/shops/api/area?ne_lat=36&ne_lng=140&sw_lat=35&sw_lng=139')->assertOk()->json());

    expect($rows->firstWhere('name', 'レッドバロン東京')['chain'])->toBe('red-baron')
        ->and($rows->firstWhere('name', 'バイク館 立川店')['chain'])->toBe('bikekan')
        ->and($rows->firstWhere('name', '街の個人バイク店')['chain'])->toBeNull(); // 非チェーンは null＝従来アイコン
});
