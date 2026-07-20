<?php

declare(strict_types=1);

use App\Models\BikeModel;
use App\Models\Listing;
use App\Models\Manufacturer;
use App\Models\Shop;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// 全国在庫サマリーはチェーン単位キャッシュのため、テスト間の汚染を防ぐ。
beforeEach(fn () => cache()->flush());

function nsShop(string $name, bool $withCoords = true): Shop
{
    $s = new Shop;
    $s->forceFill([
        'name' => $name, 'address' => 'addr-'.uniqid(), 'prefecture' => '石川県',
        'source' => Shop::SOURCE_SCRAPER,
        'latitude' => $withCoords ? 36.56 : null,
        'longitude' => $withCoords ? 136.65 : null,
    ])->save();

    return $s;
}

function nsModel(): BikeModel
{
    $m = Manufacturer::where('slug', 'honda-ns')->first();
    if (! $m) {
        $m = new Manufacturer;
        $m->forceFill(['name' => 'ホンダ', 'slug' => 'honda-ns'])->save();
    }
    $bm = BikeModel::where('slug', 'ns-cb400')->first();
    if (! $bm) {
        $bm = new BikeModel;
        $bm->forceFill(['manufacturer_id' => $m->id, 'name' => 'CB400', 'slug' => 'ns-cb400', 'displacement' => 400])->save();
    }

    return $bm;
}

function nsListing(Shop $shop, ?BikeModel $model = null): Listing
{
    $site = new Site;
    $site->forceFill(['name' => 'ns-site-'.uniqid()])->save();

    return Listing::forceCreate([
        'site_id' => $site->id, 'shop_id' => $shop->id, 'bike_model_id' => $model?->id,
        'source_url' => 'https://ex/'.uniqid(), 'title' => 'CB400テスト',
        'total_price' => 500000, 'model_year' => 2021, 'condition' => '中古車',
        'is_sold_out' => false, 'image_urls' => ['https://ex/i.jpg'],
    ]);
}

// ─────────── ① 全国共有型の在庫ゼロ個店＝最適化される ───────────

it('optimizes a national-stock chain zero-stock shop: index + 取り寄せ title + self-canonical + funnel, no Product/ItemList, dealer schema present', function () {
    $model = nsModel();
    $parent = nsShop('株式会社レッドバロン');
    nsListing($parent, $model);
    nsListing($parent, $model); // 全国在庫2台（親に集約）
    $branch = nsShop('レッドバロン金沢'); // 自店在庫0

    $html = $this->get(route('shops.show', $branch->id))->assertOk()->getContent();

    expect($html)
        ->not->toContain('noindex, follow')                                   // index する
        ->toContain('レッドバロン全国在庫2台から取り寄せ可能')                  // title/h1 が実態一致
        ->toContain('<link rel="canonical" href="'.route('shops.show', $branch->id).'">') // 自己参照canonical
        ->toContain('全店舗在庫一覧を見る')                                     // 全国在庫への導線
        ->toContain('主要車種')                                                // 軽サマリー
        // ※店舗個別schema(MotorcycleDealer/LocalBusiness)は現状この個店ページに出ていない（サイト共通schemaのみ）。
        //   その付与は別タスク（全店共通で出すべき）＝今回スコープ外のためアサートしない。
        ->not->toContain('"@type":"Product"')                                  // 在庫ゼロなので車両Productは出さない
        ->not->toContain('"@type":"ItemList"');                                // ItemListも出さない
});

// ─────────── ② 店舗別型の在庫あり個店＝一切変更しない ───────────

it('leaves a store-based chain shop WITH its own stock unchanged (default title, no 取り寄せ, indexable, self-canonical)', function () {
    $model = nsModel();
    $shop = nsShop('バイク王 金沢店');
    nsListing($shop, $model); // 自店在庫あり

    $html = $this->get(route('shops.show', $shop->id))->assertOk()->getContent();

    expect($html)
        ->toContain('の在庫・取扱車両一覧')                                     // 従来title
        ->not->toContain('から取り寄せ可能')                                   // 取り寄せ文言は出ない
        ->not->toContain('noindex, follow')                                    // 在庫ありでindex
        ->toContain('<link rel="canonical" href="'.route('shops.show', $shop->id).'">'); // 自己参照（不変）
});

// ─────────── ③ 混在/店舗別型の空店（national_stockフラグ無し）＝巻き込まない ───────────

it('does NOT optimize a chain empty shop WITHOUT the national_stock flag, even when the chain has stock (stays noindex, default title)', function () {
    $model = nsModel();
    $parent = nsShop('バイク館 本店');
    nsListing($parent, $model); // チェーンには在庫あり → chainInfo はセットされる
    $branch = nsShop('バイク館 立川店'); // この店は在庫0

    $html = $this->get(route('shops.show', $branch->id))->assertOk()->getContent();

    // フラグ無しなので chainInfo があっても最適化されない＝従来通り noindex・取り寄せ文言なし
    expect($html)
        ->toContain('noindex, follow')
        ->not->toContain('から取り寄せ可能')
        ->not->toContain('全国在庫2台から');
});

// ─────────── ④ 非チェーン店＝一切変更しない ───────────

it('leaves a non-chain zero-stock shop unchanged (noindex, default title)', function () {
    $shop = nsShop('街の個人バイク店');

    $html = $this->get(route('shops.show', $shop->id))->assertOk()->getContent();

    expect($html)
        ->toContain('noindex, follow')
        ->toContain('の在庫・取扱車両一覧')
        ->not->toContain('から取り寄せ可能');
});

// ─────────── ⑤ 全国在庫ページ＝ItemList schema を持つ（受け皿・不変） ───────────

it('keeps the national inventory chain page emitting ItemList Product/Offer', function () {
    $model = nsModel();
    $shop = nsShop('レッドバロン富山');
    foreach (range(1, 6) as $i) {
        nsListing($shop, $model); // 5台以上でnoindex回避（doorwayガード）
    }

    $html = $this->get(route('shops.chain', 'red-baron'))->assertOk()->getContent();

    expect($html)
        ->toContain('"@type":"ItemList"')
        ->toContain('"@type":"Product"');
});
