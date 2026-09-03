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
// Listing は Scout(Searchable)。このテストは検索と無関係なので Meilisearch へ同期させない
// （NullEngine 化）。実DB挙動・SEO分岐は変わらない。
beforeEach(function () {
    config(['scout.driver' => null]);
    cache()->flush();
});

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

it('在庫0かつ全国在庫チェーン → index され、取り寄せ導線が出る（車両Product/ItemListは出さない）', function () {
    $model = nsModel();
    $parent = nsShop('株式会社レッドバロン');
    nsListing($parent, $model);
    nsListing($parent, $model); // 全国在庫2台（親に集約）
    $branch = nsShop('レッドバロン金沢'); // 自店在庫0

    $html = $this->get(route('shops.show', $branch->id))->assertOk()->getContent();

    // 検証したいのは「全国共有型の在庫ゼロ個店＝独立ページとして最適化される」分岐。
    // 文言そのものではなく、その分岐でだけ現れる性質（index / 取り寄せ導線 / ファネル / 自己参照canonical）を見る。
    expect($html)
        ->not->toContain('noindex, follow')                                    // 独立ページとして index（noindexにしない）
        ->toContain('<link rel="canonical" href="'.route('shops.show', $branch->id).'">') // 自己参照canonical
        ->toContain('取り寄せ可能')                                            // 全国在庫からの取り寄せ導線（national entry でのみ出る）
        ->toContain('全店舗在庫一覧を見る')                                    // チェーン横断ページへのファネル
        ->not->toContain('"@type":"Product"')                                  // 在庫ゼロ個店に車両Productは出さない
        ->not->toContain('"@type":"ItemList"');                                // ItemListも出さない
});

// ─────────── ② 店舗別型の在庫あり個店＝一切変更しない ───────────

it('在庫あり → noindex にならず、取り寄せ導線は出ない（自己参照canonical・不変）', function () {
    $model = nsModel();
    $shop = nsShop('バイク王 金沢店');
    nsListing($shop, $model); // 自店在庫あり（店舗別型チェーンだが自店在庫があるので通常表示）

    $html = $this->get(route('shops.show', $shop->id))->assertOk()->getContent();

    // 検証したいのは「在庫がある店は最適化分岐に入らず従来どおり」。title 文言は変わりうるので、
    // 分岐の性質（index / 取り寄せ導線なし / 自己参照canonical）で見る。
    expect($html)
        ->not->toContain('noindex, follow')                                    // 在庫ありは index
        ->not->toContain('取り寄せ可能')                                        // 取り寄せ導線は national entry 専用
        ->not->toContain('全店舗在庫一覧を見る')                                // チェーンファネルも出さない
        ->toContain('<link rel="canonical" href="'.route('shops.show', $shop->id).'">'); // 自己参照canonical
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

it('在庫0かつ非チェーン → noindex, follow（取り寄せ導線は出ない）', function () {
    $shop = nsShop('街の個人バイク店');

    $html = $this->get(route('shops.show', $shop->id))->assertOk()->getContent();

    // 非チェーンの在庫ゼロ店は最適化対象外。noindex になり、取り寄せ導線も出ないことを見る（title文言は問わない）。
    expect($html)
        ->toContain('noindex, follow')                                         // 在庫0・非チェーンは noindex
        ->not->toContain('取り寄せ可能')                                        // 取り寄せ導線は出ない
        ->not->toContain('全店舗在庫一覧を見る');
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
