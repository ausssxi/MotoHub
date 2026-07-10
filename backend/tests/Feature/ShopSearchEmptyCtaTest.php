<?php

use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function ctaShop(string $name): Shop
{
    return Shop::create([
        'name' => $name, 'prefecture' => '東京都', 'city' => '世田谷区',
        'address' => 'cta-'.uniqid(), 'shop_type' => 'dealer', 'source' => 'scraper',
    ]);
}

// ─────────── 0件時の登録CTA ───────────

it('shows the register CTA with a valid href to the shop-submit route on zero hits', function () {
    $res = $this->get('/shops/search?q='.urlencode('存在しない店名ZZZ'))->assertOk();

    $res->assertSee('見つかりませんでした')
        ->assertSee(route('shops.submit.create'), false)   // 常設カードと同じ登録入口へリンク
        ->assertSee('このお店の情報を投稿する');
});

it('renders the CTA as a solid, build-present button (not the purged bg-emerald-600)', function () {
    // bg-emerald-600 は本番のコンパイル済みCSSに存在せず「押せなさそう」に見えた原因。
    // bg-green-600（存在するクラス）で塗り、確実に押せる見た目にする。
    $html = $this->get('/shops/search?q='.urlencode('存在しない店名ZZZ'))->assertOk()->getContent();

    // CTA アンカーが solid な bg-green-600 を持つ
    expect($html)->toContain('bg-green-600');
    // パージ済みクラスへ逆戻りしていない（無背景＝不可視化の再発防止）
    expect(str_contains($html, 'bg-emerald-600'))->toBeFalse();
});

it('carries the query as a prefill param to the submit page', function () {
    $this->get('/shops/search?q='.urlencode('未掲載バイク店'))->assertOk()
        ->assertSee(route('shops.submit.create', ['name' => '未掲載バイク店']), false);
});

// ─────────── 出し分け（1件以上では 0件CTA を出さない） ───────────

it('does not show the zero-hit CTA when there is at least one result', function () {
    ctaShop('ヒットするバイク店');

    $res = $this->get('/shops/search?q='.urlencode('ヒットするバイク店'))->assertOk();
    $res->assertSee('ヒットするバイク店')
        ->assertDontSee('見つかりませんでした'); // 0件の歓迎ブロックは出ない
});

// ─────────── 登録入口が到達可能（未ログインでも submit ページは開く） ───────────

it('reaches the shop submit page from the CTA link (guest ok)', function () {
    $this->get(route('shops.submit.create', ['name' => 'テスト店']))->assertOk();
});
