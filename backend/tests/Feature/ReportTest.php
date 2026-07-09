<?php

use App\Models\Report;
use App\Models\Shop;
use App\Models\ShopAcceptanceReport;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeReportedComment(): ShopAcceptanceReport
{
    $shop = Shop::create([
        'name' => 'テスト整備', 'prefecture' => '東京都', 'city' => '世田谷区',
        'address' => 'addr-'.uniqid(), 'shop_type' => 'dealer', 'source' => 'scraper',
    ]);

    return ShopAcceptanceReport::create([
        'shop_id' => $shop->id,
        'comment' => '対応してもらえました',
        'submitter_name' => '名無しライダー',
        'is_approved' => true,
    ]);
}

// ─────────── 通報の受付 ───────────

it('creates a report row with a hashed ip and no raw ip', function () {
    $c = makeReportedComment();

    $this->post('/reports', [
        'type' => 'shop_comment', 'id' => $c->id, 'reason' => 'fact',
    ])->assertRedirect();

    $report = Report::first();
    expect($report)->not->toBeNull()
        ->and($report->reportable_type)->toBe(ShopAcceptanceReport::class)
        ->and($report->reportable_id)->toBe($c->id)
        ->and($report->reason)->toBe('fact')
        ->and($report->status)->toBe(Report::STATUS_OPEN)
        ->and($report->reporter_ip_hash)->not->toBeNull()
        ->and(strlen($report->reporter_ip_hash))->toBe(64) // sha256
        ->and($report->reporter_ip_hash)->not->toContain('127.0.0.1'); // 生IPを残さない
});

it('soft-handles a duplicate report from the same ip and target (no second row)', function () {
    $c = makeReportedComment();

    $this->post('/reports', ['type' => 'shop_comment', 'id' => $c->id, 'reason' => 'fact'])->assertRedirect();
    $this->post('/reports', ['type' => 'shop_comment', 'id' => $c->id, 'reason' => 'spam'])->assertRedirect();

    expect(Report::count())->toBe(1); // 二重通報は作らない
});

it('accepts a report without a reason (reason is optional)', function () {
    $c = makeReportedComment();

    $this->post('/reports', ['type' => 'shop_comment', 'id' => $c->id])->assertRedirect();

    expect(Report::where('reportable_id', $c->id)->exists())->toBeTrue();
});

it('rejects an unknown reportable type (allowlist)', function () {
    $this->post('/reports', ['type' => 'user', 'id' => 1])->assertSessionHasErrors('type');
    expect(Report::count())->toBe(0);
});

it('rejects an invalid reason not in the allowlist', function () {
    $c = makeReportedComment();
    $this->post('/reports', ['type' => 'shop_comment', 'id' => $c->id, 'reason' => 'zzz'])
        ->assertSessionHasErrors('reason');
});

it('does not create a report for a non-existent target id but still shows success', function () {
    $this->post('/reports', ['type' => 'shop_comment', 'id' => 999999])
        ->assertRedirect()->assertSessionHas('report_success');
    expect(Report::count())->toBe(0);
});

it('rejects a report when the honeypot is filled (bot)', function () {
    $c = makeReportedComment();
    $this->post('/reports', ['type' => 'shop_comment', 'id' => $c->id, 'website' => 'http://spam'])
        ->assertSessionHasErrors('website');
    expect(Report::count())->toBe(0);
});

it('throttles rapid repeated report requests (429)', function () {
    $c = makeReportedComment();
    // throttle:5,10 → 6回目で 429
    for ($i = 0; $i < 5; $i++) {
        $this->post('/reports', ['type' => 'shop_comment', 'id' => $c->id])->assertRedirect();
    }
    $this->post('/reports', ['type' => 'shop_comment', 'id' => $c->id])->assertStatus(429);
});

// ─────────── モデル・定数 ───────────

it('exposes reason and status label maps', function () {
    expect(Report::REASONS)->toHaveKeys(['fact', 'defame', 'spam', 'other'])
        ->and(Report::STATUSES)->toHaveKeys([Report::STATUS_OPEN, Report::STATUS_REVIEWED, Report::STATUS_DISMISSED])
        ->and(Report::REPORTABLE_TYPES['shop_comment'])->toBe(ShopAcceptanceReport::class);
});
