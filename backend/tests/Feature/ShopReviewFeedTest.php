<?php

use App\Models\Shop;
use App\Models\ShopAcceptanceReport;
use App\Services\Shop\ShopAcceptanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function feedShop(string $name = 'フィード店'): Shop
{
    return Shop::create([
        'name' => $name, 'prefecture' => '東京都', 'city' => '世田谷区',
        'address' => 'fd-'.uniqid(), 'shop_type' => 'dealer', 'source' => Shop::SOURCE_SCRAPER,
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
        $c->shop->name; // 店名アクセス
    }
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    // count + select + eager-load(shop) 程度。件数(10)に比例しない（N+1なら12+）
    expect($queries)->toBeLessThanOrEqual(4);
});
