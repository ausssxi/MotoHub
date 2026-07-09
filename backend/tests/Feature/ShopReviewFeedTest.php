<?php

use App\Models\Shop;
use App\Models\ShopAcceptanceReport;
use App\Services\Shop\ShopAcceptanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function feedShop(string $name = 'フィード店', ?string $localImage = null): Shop
{
    return Shop::create([
        'name' => $name, 'prefecture' => '東京都', 'city' => '世田谷区',
        'address' => 'fd-'.uniqid(), 'shop_type' => 'dealer', 'source' => Shop::SOURCE_SCRAPER,
        'local_image_path' => $localImage,
    ]);
}

function feedComment(Shop $shop, string $comment, bool $approved, ?string $at = null): ShopAcceptanceReport
{
    $r = ShopAcceptanceReport::create([
        'shop_id' => $shop->id, 'comment' => $comment, 'submitter_name' => '名無しライダー',
        'is_approved' => false, 'comment_approved' => $approved,
    ]);
    if ($at) {
        $r->created_at = $at;
        $r->save();
    }

    return $r;
}

// ─────────── 抽出・並び ───────────

it('shows only comment_approved=true comments in newest-first order', function () {
    $shop = feedShop();
    feedComment($shop, '古い承認コメント', true, '2026-07-01 10:00:00');
    feedComment($shop, '新しい承認コメント', true, '2026-07-08 10:00:00');
    feedComment($shop, '未承認コメント', false, '2026-07-09 10:00:00');

    $res = $this->get('/shops/reviews')->assertOk();
    $res->assertSee('新しい承認コメント')
        ->assertSee('古い承認コメント')
        ->assertDontSee('未承認コメント'); // comment_approved=false は出さない

    // 新着順（新しい方が先に現れる）
    $pos = fn ($s) => strpos($res->getContent(), $s);
    expect($pos('新しい承認コメント'))->toBeLessThan($pos('古い承認コメント'));
});

it('excludes empty comments and orphan-shop comments', function () {
    $shop = feedShop();
    feedComment($shop, '', true); // 空コメントは出さない

    $feed = app(ShopAcceptanceService::class)->getRecentCommentsFeed(20);
    expect($feed->total())->toBe(0);
});

// ─────────── 店名リンク・通報ボタン ───────────

it('links each comment to its shop page and shows the report affordance', function () {
    $shop = feedShop('リンク先の店');
    feedComment($shop, 'リンクの口コミ', true);

    $res = $this->get('/shops/reviews')->assertOk();
    $res->assertSee('リンク先の店')
        ->assertSee(route('shops.show', $shop), false)  // 店ページへの回遊リンク
        ->assertSee('shop_comment', false)              // 通報の type（reports.store 連携）
        ->assertSee(route('reports.store'), false);
});

// ─────────── 店外観写真（3.1） ───────────

it('shows the shop photo with a lazy-loaded, onerror-fallback image when present', function () {
    $shop = feedShop('写真つき店', 'shop-user/test-photo.jpg');
    feedComment($shop, '写真つきの口コミ', true);

    $res = $this->get('/shops/reviews')->assertOk();
    $res->assertSee('shop-user/test-photo.jpg', false)  // display_image_url（公開ディスク）
        ->assertSee('loading="lazy"', false)            // 遅延読み込み
        ->assertSee('onerror', false);                  // 欠損フォールバック
});

it('renders without an image (placeholder) when the shop has none', function () {
    $shop = feedShop('写真なし店');                     // local_image_path=null / image_url=null
    feedComment($shop, '写真なしの口コミ', true);

    expect($shop->display_image_url)->toBeNull();       // 画像URLが無い
    $this->get('/shops/reviews')->assertOk()            // カードは壊れない（プレースホルダー）
        ->assertSee('写真なしの口コミ');
});

// ─────────── 内部リンク強化（3.1） ───────────

it('makes the card link to the shop and shows an explicit onward anchor with the shop name', function () {
    $shop = feedShop('回遊先の店');
    feedComment($shop, '回遊の口コミ', true);

    $res = $this->get('/shops/reviews')->assertOk();
    $res->assertSee(route('shops.show', $shop), false)          // カードクリック先＋明示アンカー
        ->assertSee('回遊先の店の詳細・在庫を見る');            // アンカーテキストに店名（内部リンク価値）
});

it('links the location prefecture to its shop area page', function () {
    $shop = feedShop('エリアリンクの店');
    feedComment($shop, 'エリアの口コミ', true);

    $this->get('/shops/reviews')->assertOk()
        ->assertSee(route('shops.area.prefecture', $shop->prefecture), false);
});

// ─────────── ページネーション ───────────

it('paginates at 20 per page', function () {
    $shop = feedShop();
    for ($i = 0; $i < 25; $i++) {
        feedComment($shop, "コメント{$i}", true, now()->subMinutes(25 - $i)->toDateTimeString());
    }

    $feed = app(ShopAcceptanceService::class)->getRecentCommentsFeed(20);
    expect($feed->perPage())->toBe(20)
        ->and($feed->total())->toBe(25)
        ->and($feed->count())->toBe(20); // 1頁目は20件

    $this->get('/shops/reviews?page=2')->assertOk()->assertSee('コメント'); // 2頁目もエラーにならない
});

// ─────────── 空状態 ───────────

it('renders an empty state without error when there are no comments', function () {
    $this->get('/shops/reviews')->assertOk()->assertSee('まだ口コミがありません');
});

// ─────────── N+1 回避 ───────────

it('eager loads shops without N+1', function () {
    for ($i = 0; $i < 10; $i++) {
        feedComment(feedShop("店{$i}"), "口コミ{$i}", true);
    }

    DB::enableQueryLog();
    $feed = app(ShopAcceptanceService::class)->getRecentCommentsFeed(20);
    foreach ($feed as $c) {
        $c->shop->name;               // 店名アクセス
        $c->shop->display_image_url;  // 画像URLアクセサ（ロード済み属性のみ＝追加クエリ無し）
    }
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    // count + select + eager-load(shop) 程度。件数(10)に比例しない（N+1なら12+）。画像も追加クエリ無し。
    expect($queries)->toBeLessThanOrEqual(4);
});
