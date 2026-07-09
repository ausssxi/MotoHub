<?php

use App\Models\Shop;
use App\Models\ShopAcceptanceReport;
use App\Services\Shop\ShopAcceptanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    config()->set('ng_words.words', []); // NGは別テストで担保・ここでは無効化
    config()->set('shop.new_user_shop_days', 14);
});

function scraperShop(): Shop
{
    return Shop::create([
        'name' => '既存整備店', 'prefecture' => '東京都', 'city' => '世田谷区',
        'address' => 'sc-'.uniqid(), 'shop_type' => 'dealer', 'source' => Shop::SOURCE_SCRAPER,
    ]);
}

function newUserShop(): Shop
{
    return Shop::create([
        'name' => '新規投稿店', 'prefecture' => '東京都', 'city' => '世田谷区',
        'address' => 'us-'.uniqid(), 'shop_type' => 'dealer', 'source' => Shop::SOURCE_USER,
    ]); // created_at = now → 新規店
}

function summary(int $shopId): array
{
    return app(ShopAcceptanceService::class)->getApprovedSummary($shopId);
}

// ─────────── コメント即反映（既存店） ───────────

it('publishes a comment immediately (comment_approved=true) on an existing scraper shop', function () {
    $shop = scraperShop();

    $this->post("/shops/{$shop->id}/acceptance-report", [
        'comment' => '丁寧に対応してもらえました',
    ])->assertSessionHas('acceptance_success', 'instant');

    $report = ShopAcceptanceReport::first();
    expect($report->comment_approved)->toBeTrue()   // コメントは即反映
        ->and($report->is_approved)->toBeFalse();   // 事実系は承認待ちのまま

    // 即座に表示クエリに乗る
    expect(collect(summary($shop->id)['comments'])->pluck('comment'))
        ->toContain('丁寧に対応してもらえました');
});

it('keeps factual flags pending (is_approved=false) and out of the aggregate', function () {
    $shop = scraperShop();

    $this->post("/shops/{$shop->id}/acceptance-report", [
        'accepts_other_store' => '1', 'accepts_bring_in' => '1',
        'comment' => '持ち込みOKでした',
    ])->assertRedirect();

    $report = ShopAcceptanceReport::first();
    expect($report->is_approved)->toBeFalse()
        ->and($report->comment_approved)->toBeTrue();

    // 事実系フラグ集計は承認待ちなので0（コメントだけ表示に乗る）
    $s = summary($shop->id);
    expect($s['counts']['accepts_other_store'])->toBe(0)
        ->and($s['counts']['accepts_bring_in'])->toBe(0)
        ->and(collect($s['comments'])->pluck('comment'))->toContain('持ち込みOKでした');
});

// ─────────── 新規店ガード ───────────

it('holds a new user shop comment for approval (comment_approved=false)', function () {
    $shop = newUserShop();

    $this->post("/shops/{$shop->id}/acceptance-report", [
        'comment' => '新規店への投稿',
    ])->assertSessionHas('acceptance_success', '1'); // instant ではない

    $report = ShopAcceptanceReport::first();
    expect($report->comment_approved)->toBeFalse();          // 承認へ回る
    expect(summary($shop->id)['comments'])->toBe([]);        // 即表示されない
});

it('publishes immediately once a user shop ages past the new-shop window', function () {
    config()->set('shop.new_user_shop_days', 14);
    $shop = newUserShop();
    $shop->created_at = now()->subDays(30); // 30日前＝新規窓を過ぎた
    $shop->save();

    expect($shop->isNewUserShop())->toBeFalse();

    $this->post("/shops/{$shop->id}/acceptance-report", ['comment' => '古い投稿店'])
        ->assertSessionHas('acceptance_success', 'instant');

    expect(ShopAcceptanceReport::first()->comment_approved)->toBeTrue();
});

// ─────────── キャッシュ即反映 ───────────

it('purges the summary cache so a new comment appears without staleness', function () {
    $shop = scraperShop();

    // 先に空サマリをキャッシュさせる
    expect(summary($shop->id)['comments'])->toBe([]);

    $this->post("/shops/{$shop->id}/acceptance-report", ['comment' => 'キャッシュ後の投稿'])->assertRedirect();

    // Observer::saved が forgetSummary → 再構築で新コメントが見える
    expect(collect(summary($shop->id)['comments'])->pluck('comment'))
        ->toContain('キャッシュ後の投稿');
});

// ─────────── 事後モデレーション（Filament トグル相当） ───────────

it('hides a comment when comment_approved is toggled off (moderation)', function () {
    $shop = scraperShop();
    $r = ShopAcceptanceReport::create([
        'shop_id' => $shop->id, 'comment' => '不適切かもしれないコメント',
        'is_approved' => false, 'comment_approved' => true,
    ]);
    expect(collect(summary($shop->id)['comments'])->pluck('comment'))->toContain('不適切かもしれないコメント');

    $r->update(['comment_approved' => false]); // 運営が非表示化
    expect(summary($shop->id)['comments'])->toBe([]);
});

// ─────────── 移行の意図（既存承認コメントが消えない） ───────────

it('shows a legacy approved comment that the migration marked comment_approved=true', function () {
    $shop = scraperShop();
    // migration が is_approved=true 行に comment_approved=true を付与した後の状態を再現
    ShopAcceptanceReport::create([
        'shop_id' => $shop->id, 'comment' => '移行済みの既存コメント',
        'is_approved' => true, 'comment_approved' => true,
    ]);

    expect(collect(summary($shop->id)['comments'])->pluck('comment'))
        ->toContain('移行済みの既存コメント');
});
