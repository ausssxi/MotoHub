<?php

use App\Models\BikeParking;
use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function nearbyParking(array $attrs = []): BikeParking
{
    return BikeParking::create(array_merge([
        'name' => 'テスト駐輪場', 'address' => 'テスト住所1-2-3',
        'latitude' => 35.6595, 'longitude' => 139.7005,
        'prefecture' => '東京都', 'city' => '渋谷区', 'parking_type' => 'bike_only',
    ], $attrs));
}

// ─────────── 近くにショップがある ───────────

it('shows the nearby-shops section with GA4 tracking data attributes when a shop is close', function () {
    $p = nearbyParking();
    // 駐車場とほぼ同座標（バウンディングボックス約5km圏内）
    $shop = Shop::create([
        'name' => '近所のバイク屋', 'prefecture' => '東京都', 'city' => '渋谷区',
        'address' => 'addr-'.uniqid(), 'shop_type' => 'dealer', 'source' => 'scraper',
        'latitude' => 35.6600, 'longitude' => 139.7010,
    ]);

    $this->get("/parking/{$p->id}")
        ->assertOk()
        ->assertSee('近くのバイクショップ')
        ->assertSee('近所のバイク屋')
        ->assertSee('data-parking-to-shop', false)
        ->assertSee('data-shop-id="'.$shop->id.'"', false)
        ->assertSee('data-position="1"', false);
});

// ─────────── 近くにショップがない（セクション非表示） ───────────

it('hides the nearby-shops section when no shop is close', function () {
    $p = nearbyParking();
    // 遠方（沖縄）のショップ → バウンディングボックス外
    Shop::create([
        'name' => '遠くのバイク屋', 'prefecture' => '沖縄県', 'city' => '那覇市',
        'address' => 'addr-'.uniqid(), 'shop_type' => 'dealer', 'source' => 'scraper',
        'latitude' => 26.2124, 'longitude' => 127.6809,
    ]);

    // 見出しも、カード側の計測属性（data-shop-id）も出ない。
    // ※ 委譲リスナー内の selector 文字列 'a[data-parking-to-shop]' は常に JS に存在するため、
    //   セクション非表示の検証はカード固有の data-shop-id で行う。
    $this->get("/parking/{$p->id}")
        ->assertOk()
        ->assertDontSee('近くのバイクショップ')
        ->assertDontSee('data-shop-id=', false);
});

// ─────────── 緯度経度が無いときの防御（エラーにならない） ───────────

// bike_parkings.latitude/longitude は NOT NULL なので null 座標の駐車場は DB 上作れない。
// 座標が無い＝近隣ショップが空になる経路をコンポーネント単体で検証し、
// 空コレクション＋座標 null でも例外を投げず何も描画しないことを確かめる（防御的経路）。
it('renders nothing and does not error when the shop list is empty and coordinates are missing', function () {
    $html = $this->blade(
        '<x-nearby-shops :nearbyShops="$nearbyShops" :latitude="$latitude" :longitude="$longitude" :parkingId="$parkingId" />',
        ['nearbyShops' => collect(), 'latitude' => null, 'longitude' => null, 'parkingId' => 123],
    );

    expect(trim($html))->toBe('');
});

// ─────────── 座標ありだがショップ 0 件でもページは 200 ───────────

it('returns 200 for a parking that has coordinates but no nearby shops', function () {
    $p = nearbyParking();

    $this->get("/parking/{$p->id}")->assertOk();
});
