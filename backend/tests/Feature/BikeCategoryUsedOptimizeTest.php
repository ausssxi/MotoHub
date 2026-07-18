<?php

declare(strict_types=1);

use App\Models\Listing;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

function ccUsedListing(int $displacement, int $totalPrice, string $title): Listing
{
    // Site は $fillable 非依存で生成（forceFill）。name は unique なので毎回一意に。
    $site = new Site;
    $site->forceFill(['name' => 'テストサイト '.uniqid()])->save();

    return Listing::forceCreate([
        'site_id' => $site->id,
        'source_url' => 'https://example.com/listing/'.uniqid(),
        'title' => $title,
        'displacement' => $displacement,
        'total_price' => $totalPrice,
        'model_year' => 2021,
        'condition' => '中古車',
        'is_sold_out' => false,
    ]);
}

// ---- 穴①: 口語シノニムが title/h1/meta に入る（cc のみ） ----

it('injects colloquial synonyms into the cc landing title/h1/meta', function () {
    Cache::flush();
    // 原付（50cc以下）
    $this->get(route('bikes.category_cc', '50'))->assertOk()
        ->assertSee('原付（50cc以下）');
    // 中型（251〜400cc）＝「中型バイク 中古」クエリの語彙一致
    $this->get(route('bikes.category_cc', '400'))->assertOk()
        ->assertSee('中型（251〜400cc）');
    // 大型（751cc以上）
    $this->get(route('bikes.category_cc', 'over750'))->assertOk()
        ->assertSee('大型（751cc以上）');
});

it('keeps type landing titles unchanged (already colloquial)', function () {
    Cache::flush();
    $this->get(route('bikes.category_type', 'naked'))->assertOk()
        ->assertSee('ネイキッドの中古バイク一覧');
});

// ---- 穴②: ItemList(Product/Offer) 構造化データ ----

it('emits ItemList Product/Offer schema reflecting in-stock used listings', function () {
    Cache::flush();
    ccUsedListing(50, 150000, 'テスト原付スクーター');

    $html = $this->get(route('bikes.category_cc', '50'))->assertOk()->getContent();

    expect($html)->toContain('"@type":"ItemList"')
        ->toContain('"@type":"Product"')
        ->toContain('"@type":"Offer"')
        ->toContain('"price":150000')          // 円・schema準拠
        ->toContain('"priceCurrency":"JPY"')
        ->toContain('テスト原付スクーター');
});

it('does not emit an empty ItemList when there is no inventory (no doorway schema)', function () {
    // 在庫ゼロを決定論的に検証（実DBの在庫有無に依存しない）＝空データをキャッシュに注入。
    Cache::put('category_landing:v2:cc:125', [
        'kpi' => ['total_count' => 0, 'avg_price' => null, 'min_price' => null, 'max_price' => null],
        'top_models' => collect(),
        'latest_listings' => collect(),
        'item_list' => [],
    ], 3600);

    $html = $this->get(route('bikes.category_cc', '125'))->assertOk()->getContent();

    // item_list 空＝ItemList を出さない（空schemaを作らない）。BreadcrumbList は維持。
    expect($html)->not->toContain('"@type":"ItemList"')
        ->toContain('BreadcrumbList');
});

// ---- 穴②: 最新入庫カードが ListingResource 変換で正しく表示される（生モデルの空カード修正） ----

it('renders listing cards via ListingResource so maker/name/price are populated (not raw model)', function () {
    Cache::flush();
    ccUsedListing(50, 240000, 'ホンダ ダックス125 テスト');

    $html = $this->get(route('bikes.category_cc', '50'))->assertOk()->getContent();

    // ListingResource 経由なら車種名(title)と整形価格(24.0万)が出る。生モデルだと出ない。
    expect($html)->toContain('ホンダ ダックス125 テスト')
        ->toContain('24.0'); // total_price 240000 → 24.0万（ListingResource整形）
});
