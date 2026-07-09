<?php

use App\Models\BikeParking;
use App\Models\ParkingReview;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('ng_words.words', []);
    config()->set('shop.new_user_parking_days', 14);
});

function pk(array $attrs = []): BikeParking
{
    return BikeParking::create(array_merge([
        'name' => 'テスト駐輪場', 'address' => 'テスト住所1-2-3',
        'latitude' => 35.6595, 'longitude' => 139.7005,
        'prefecture' => '東京都', 'city' => '渋谷区', 'parking_type' => 'bike_only',
    ], $attrs));
}

// ─────────── 即反映 ───────────

it('publishes a review immediately and shows it on the parking page', function () {
    $p = pk();

    $this->post("/parking/{$p->id}/review", ['rating' => 4, 'body' => '停めやすかった'])
        ->assertRedirect();

    $r = ParkingReview::first();
    expect($r->is_approved)->toBeTrue();
    $this->get("/parking/{$p->id}")->assertOk()->assertSee('停めやすかった'); // 即反映
});

// ─────────── NGワード / 批判は通す ───────────

it('blocks a review containing an ng word (not saved)', function () {
    config()->set('ng_words.words', ['死ね']);
    $p = pk();

    $this->post("/parking/{$p->id}/review", ['rating' => 1, 'body' => '管理人は死ね'])
        ->assertSessionHasErrors('body');
    expect(ParkingReview::count())->toBe(0);
});

it('lets legitimate criticism through (negative info is the point)', function () {
    config()->set('ng_words.words', ['死ね', '殺す']);
    $p = pk();

    foreach (['狭い', '出しにくい', '満車が多い'] as $body) {
        $this->post("/parking/{$p->id}/review", ['rating' => 2, 'body' => $body])->assertRedirect();
    }
    expect(ParkingReview::count())->toBe(3); // 批判は通す
});

// ─────────── 防御（回帰＋throttle） ───────────

it('rejects a review when the honeypot is filled (regression)', function () {
    $p = pk();
    $this->post("/parking/{$p->id}/review", ['rating' => 4, 'body' => 'ok', 'website' => 'http://spam'])
        ->assertSessionHasErrors('website');
    expect(ParkingReview::count())->toBe(0);
});

it('records a hashed ip and never the raw ip (regression)', function () {
    $p = pk();
    $this->post("/parking/{$p->id}/review", ['rating' => 5, 'body' => '広い']);
    $r = ParkingReview::first();
    expect(strlen($r->submitter_ip_hash))->toBe(64)
        ->and($r->submitter_ip_hash)->not->toContain('127.0.0.1');
});

it('throttles rapid review posting (429)', function () {
    $p = pk();
    for ($i = 0; $i < 3; $i++) {
        $this->post("/parking/{$p->id}/review", ['rating' => 3, 'body' => "r{$i}"])->assertRedirect();
    }
    $this->post("/parking/{$p->id}/review", ['rating' => 3, 'body' => 'over'])->assertStatus(429);
});

// ─────────── 通報（polymorphic 流用・ログイン不要） ───────────

it('accepts a guest report of a parking review via the shared reports endpoint', function () {
    $p = pk();
    $review = ParkingReview::create(['bike_parking_id' => $p->id, 'nickname' => '名無し', 'rating' => 2, 'body' => '狭い', 'is_approved' => true]);

    expect(Report::REPORTABLE_TYPES)->toHaveKey('parking_review');

    $this->post('/reports', ['type' => 'parking_review', 'id' => $review->id, 'reason' => 'spam'])
        ->assertRedirect()->assertSessionHas('report_success');

    $report = Report::first();
    expect($report->reportable_type)->toBe(ParkingReview::class)
        ->and($report->reportable_id)->toBe($review->id);
});

// ─────────── 新規ユーザー駐車場ガード ───────────

it('holds a review for approval on a new user-submitted parking', function () {
    $user = User::factory()->create();
    $p = pk(['user_id' => $user->id]); // created_at = now → 新規ユーザー駐車場

    expect($p->isNewUserParking())->toBeTrue();

    $this->post("/parking/{$p->id}/review", ['rating' => 5, 'body' => '自演かもしれないレビュー'])->assertRedirect();

    expect(ParkingReview::first()->is_approved)->toBeFalse();     // 承認待ち
    $this->get("/parking/{$p->id}")->assertOk()->assertDontSee('自演かもしれないレビュー'); // 出ない
});

it('publishes immediately on a non-user (scraper/official) parking', function () {
    $p = pk(['user_id' => null]); // 運営/スクレイパー由来
    expect($p->isNewUserParking())->toBeFalse();

    $this->post("/parking/{$p->id}/review", ['rating' => 5, 'body' => '公式スポットの即反映'])->assertRedirect();
    expect(ParkingReview::first()->is_approved)->toBeTrue();
});

it('publishes immediately once a user parking ages past the window', function () {
    $user = User::factory()->create();
    $p = pk(['user_id' => $user->id]);
    $p->created_at = now()->subDays(30);
    $p->save();

    expect($p->isNewUserParking())->toBeFalse();
    $this->post("/parking/{$p->id}/review", ['rating' => 4, 'body' => '古いスポット'])->assertRedirect();
    expect(ParkingReview::first()->is_approved)->toBeTrue();
});

// ─────────── 集計（公開のみ） ───────────

it('recomputes avg_rating and reviews_count from approved reviews only', function () {
    $p = pk();

    // 公開レビュー2件
    $this->post("/parking/{$p->id}/review", ['rating' => 4, 'body' => 'a']);
    $this->post("/parking/{$p->id}/review", ['rating' => 2, 'body' => 'b']);

    $p->refresh();
    expect($p->reviews_count)->toBe(2)->and((float) $p->avg_rating)->toBe(3.0);

    // 非公開レビューを足して再集計 → 集計に含まれない
    ParkingReview::create(['bike_parking_id' => $p->id, 'nickname' => 'x', 'rating' => 5, 'body' => 'hidden', 'is_approved' => false]);
    app(\App\Services\Parking\ParkingService::class)->recomputeAggregates($p->id);

    $p->refresh();
    expect($p->reviews_count)->toBe(2)->and((float) $p->avg_rating)->toBe(3.0); // 非公開は除外
});
