<?php

declare(strict_types=1);

use App\Models\BikeModel;
use App\Models\BlogPost;
use App\Models\Listing;
use App\Models\Manufacturer;
use App\Models\Shop;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function chainShop(string $name): Shop
{
    $s = new Shop;
    $s->forceFill(['name' => $name, 'address' => 'addr-'.uniqid(), 'prefecture' => '東京都', 'source' => Shop::SOURCE_SCRAPER])->save();

    return $s;
}

function chainModel(): BikeModel
{
    $m = Manufacturer::where('slug', 'honda-chain')->first();
    if (! $m) {
        $m = new Manufacturer;
        $m->forceFill(['name' => 'ホンダ', 'slug' => 'honda-chain'])->save();
    }
    $bm = BikeModel::where('slug', 'chain-cb')->first();
    if (! $bm) {
        $bm = new BikeModel;
        $bm->forceFill(['manufacturer_id' => $m->id, 'name' => 'テストCB', 'slug' => 'chain-cb', 'displacement' => 400])->save();
    }

    return $bm;
}

function chainListing(Shop $shop, ?BikeModel $model, int $yen = 200000): Listing
{
    $site = new Site;
    $site->forceFill(['name' => 'サイト '.uniqid()])->save();

    return Listing::forceCreate([
        'site_id' => $site->id,
        'shop_id' => $shop->id,
        'bike_model_id' => $model?->id,
        'source_url' => 'https://ex/'.uniqid(),
        'title' => 'テスト車両CB',
        'total_price' => $yen,
        'model_year' => 2021,
        'condition' => '中古車',
        'is_sold_out' => false,
        'image_urls' => ['https://ex/i.jpg'],
    ]);
}

// ---- #1 リンク切れ修正コマンド ----

it('fixes /blog/shops/chain/ typos in blog bodies (and is idempotent)', function () {
    $author = User::factory()->create();
    $p = new BlogPost;
    $p->forceFill([
        'author_id' => $author->id, // blog_posts.author_id は NOT NULL
        'slug' => 't-'.uniqid(), 'title' => 'テスト記事',
        'body' => '在庫はこちら → https://motohub.jp/blog/shops/chain/honda-dream をどうぞ。',
    ])->save();

    $this->artisan('blog:fix-chain-links')->assertSuccessful();

    $body = $p->fresh()->body;
    expect($body)->toContain('/shops/chain/honda-dream')
        ->not->toContain('/blog/shops/chain/');

    // 冪等: 再実行しても壊れない
    $this->artisan('blog:fix-chain-links')->assertSuccessful();
    expect($p->fresh()->body)->not->toContain('/blog/shops/chain/');
});

// ---- #2 totalStock < 5 で noindex ＋ ItemList非出力（doorway無し） ----

it('noindexes and emits no ItemList for a chain with under 5 stock', function () {
    chainShop('ライコランド東京'); // 在庫0

    $html = $this->get('/shops/chain/ricoland')->assertOk()->getContent();

    expect($html)->toContain('noindex')                         // 5台未満は noindex
        ->not->toContain('"@type":"ItemList"')                  // 空ItemListを出さない
        ->toContain('ライコランドの中古バイク在庫一覧');        // title/h1 は維持
});

// ---- #3 在庫あり: ItemList(Product/Offer) ＋ 在庫カード（ListingResource）＋ title維持 ----

it('emits ItemList Product/Offer and renders inventory cards for an in-stock chain', function () {
    $shop = chainShop('レッドバロン東京');
    $model = chainModel();
    foreach (range(1, 5) as $i) {
        chainListing($shop, $model, 200000);
    }

    $html = $this->get('/shops/chain/red-baron')->assertOk()->getContent();

    expect($html)->toContain('レッドバロンの中古バイク在庫一覧')  // title/h1 維持
        ->toContain('"@type":"ItemList"')
        ->toContain('"@type":"Offer"')
        ->toContain('"price":200000')          // 円
        ->toContain('"priceCurrency":"JPY"')
        ->toContain('ホンダ');                 // カードがListingResource経由でメーカー名を表示
});
