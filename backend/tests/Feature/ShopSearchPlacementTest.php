<?php

use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function placementShop(array $overrides = []): Shop
{
    static $seq = 0;
    $seq++;

    return Shop::create(array_merge([
        'name' => "テストショップ{$seq}",
        'prefecture' => '東京都',
        'city' => '世田谷区',
        'address' => "addr-{$seq}",
        'latitude' => 35.64,
        'longitude' => 139.65,
        'shop_type' => 'dealer',
        'source' => 'scraper',
    ], $overrides));
}

// A-1: 店詳細ページ
it('shows a name-search box on the shop detail page, preset to the shop prefecture', function () {
    $shop = placementShop(['prefecture' => '大阪府', 'city' => '大阪市北区']);

    $res = $this->get('/shops/'.$shop->id)->assertOk();
    $res->assertSee('他のバイクショップを探す');
    $res->assertSee('action="'.route('shops.search').'"', false);
    $res->assertSee('name="pref" value="大阪府"', false); // 同県プリセット
});

// A-2: 市区町村ページ（area）
it('shows a name-search box on the area city page', function () {
    placementShop();

    $this->get(route('shops.area.city', ['東京都', '世田谷区']))
        ->assertOk()
        ->assertSee('action="'.route('shops.search').'"', false);
});

// A-2: 市区町村ページ（repair）
it('shows a name-search box on the repair city page', function () {
    placementShop(['shop_type' => 'repair_only']);

    $res = $this->get(route('shops.repair.city', ['東京都', '世田谷区']))->assertOk();
    $res->assertSee('action="'.route('shops.search').'"', false);
    $res->assertSee('name="type" value="repair_only"', false); // repair 系は店種固定
});

// A-3: 投稿完了画面（サンクス）
it('shows a link to shop search on the submission thank-you screen', function () {
    $res = $this->withSession(['submission_success' => '1'])
        ->get(route('shops.submit.create'))
        ->assertOk();

    $res->assertSee('受け付けました');
    $res->assertSee(route('shops.search'), false); // 検索への導線
});

// B: トップページ（リンクのみ・検索ボックス input ではない）
it('has a link (not a search box) to shop search on the home page', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('バイクショップを店名で探す')
        ->assertSee('href="'.route('shops.search').'"', false);
});
