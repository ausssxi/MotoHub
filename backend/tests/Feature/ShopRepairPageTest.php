<?php

declare(strict_types=1);

use App\Models\BikeModel;
use App\Models\Listing;
use App\Models\Manufacturer;
use App\Models\Shop;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * 店舗ページの「整備店ページ／販売店ページ」出し分け回帰テスト。
 *
 * 中古専門店は Webike の販売バッジ（公取協加盟店・メーカー正規店）を持てず整備バッジだけを持つため
 * shop_type='repair_only' に分類される。従来はこの shop_type だけで整備店ページ（台数なし・販売schemaなし）
 * に固定していたため、在庫を大量に持つ repair_only 店（例: 有限会社バイク館 1,897台）が販売ページとして
 * 表示されなかった。blade で概念を2つに分割:
 *   ① $isRepair     = shop_type==='repair_only'（整備をする店か）… AutoRepair schema はこちら（在庫不問で維持）
 *   ② $isRepairPage = $isRepair かつ 在庫0（整備店ページとして見せるか）… title 分岐はこちら
 * 在庫を持つ repair_only は通常の販売店と同じ扱い（販売タイトル＋MotorcycleDealer schema）になり、
 * かつ AutoRepair schema も残す。robots（noindex）の挙動は不変。
 *
 * ※ 個店ページに Product/ItemList schema は元々付かない（ItemList/Product はチェーン横断ページ専用）。
 *   「販売店扱い」の schema 上の実体は MotorcycleDealer（通常の在庫あり販売店と同一）なので、それで検証する。
 */

uses(RefreshDatabase::class);

// Listing は Scout(Searchable)。検索と無関係なので Meilisearch へ同期させない（実DB挙動・分岐は不変）。
beforeEach(function () {
    config(['scout.driver' => null]);
    cache()->flush();
});

function repairShop(string $name): Shop
{
    $s = new Shop;
    $s->forceFill([
        'name' => $name,
        'address' => 'addr-'.uniqid(),
        'prefecture' => '東京都',
        'city' => '墨田区',
        'shop_type' => 'repair_only',   // ← shop_type 自体は本テストでも変更しない前提（分類は別系統）
        'source' => Shop::SOURCE_SCRAPER,
        'latitude' => 35.71,            // 座標あり（noindex の座標条件を満たさないため）
        'longitude' => 139.80,
    ])->save();

    return $s;
}

function repairListing(Shop $shop): Listing
{
    $mfr = Manufacturer::firstWhere('slug', 'rp-honda') ?? tap(new Manufacturer, fn ($m) => $m->forceFill(['name' => 'ホンダ', 'slug' => 'rp-honda'])->save());
    $bm = BikeModel::firstWhere('slug', 'rp-cb400') ?? tap(new BikeModel, fn ($b) => $b->forceFill(['manufacturer_id' => $mfr->id, 'name' => 'CB400', 'slug' => 'rp-cb400', 'displacement' => 400])->save());
    $site = tap(new Site, fn ($s) => $s->forceFill(['name' => 'rp-site-'.uniqid()])->save());

    return Listing::forceCreate([
        'site_id' => $site->id, 'shop_id' => $shop->id, 'bike_model_id' => $bm->id,
        'source_url' => 'https://ex/'.uniqid(), 'title' => 'CB400テスト',
        'total_price' => 500000, 'model_year' => 2021, 'condition' => '中古車',
        'is_sold_out' => false, 'image_urls' => ['https://ex/i.jpg'],
    ]);
}

it('在庫を持つ repair_only は販売ページ扱い：販売店schema付き・整備店タイトルにならない・noindexにならない', function () {
    $shop = repairShop('有限会社バイク館テスト');
    repairListing($shop); // 在庫1台

    $html = $this->get(route('shops.show', $shop->id))->assertOk()->getContent();

    expect($html)
        ->not->toContain('（バイク整備・修理）')       // 整備店ページのタイトルに固定しない
        ->toContain('中古バイク在庫')                  // 通常の販売タイトル（台数を出す）
        ->toContain('"MotorcycleDealer"')      // 販売店schema（＝通常の在庫あり販売店と同じ扱い）
        ->not->toContain('noindex, follow');           // 在庫>0 なので index（robots不変）
});

it('在庫を持つ repair_only でも AutoRepair schema は残る（整備店である事実は失わない）', function () {
    $shop = repairShop('有限会社バイク館テスト2');
    repairListing($shop);

    $html = $this->get(route('shops.show', $shop->id))->assertOk()->getContent();

    // 整備店schema（概念①）と販売店schema（概念②で販売ページ側）が両立する。
    expect($html)
        ->toContain('"AutoRepair"')
        ->toContain('"MotorcycleDealer"');
});

it('在庫0の repair_only は整備店ページ扱い：整備店タイトル・AutoRepairあり・MotorcycleDealerなし・noindexにならない', function () {
    $shop = repairShop('街の整備専門店テスト'); // 在庫0

    $html = $this->get(route('shops.show', $shop->id))->assertOk()->getContent();

    expect($html)
        ->toContain('（バイク整備・修理）')            // 整備店ページのタイトル
        ->toContain('"AutoRepair"')            // 整備店schema
        ->not->toContain('"MotorcycleDealer"') // 販売店schemaは出さない
        ->not->toContain('noindex, follow');           // 整備店は在庫0でも index（robots不変＝従来どおり）
});
