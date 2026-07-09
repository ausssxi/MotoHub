<?php

use App\Models\BikeModel;
use App\Models\BikeParking;
use App\Models\Manufacturer;
use App\Models\ParkingReview;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('ng_words.words', []); // 既定は無効・NGテストで個別に設定
});

function reviewModel(): BikeModel
{
    $mfr = new Manufacturer(['slug' => 'test-mfr']);
    $mfr->name = 'テストメーカー';
    $mfr->save();

    return BikeModel::create(['manufacturer_id' => $mfr->id, 'name' => 'テスト車', 'slug' => 'test-bike']);
}

function reviewer(): User
{
    return User::factory()->create(['review_display_name' => 'テスター']); // 初回ハンドル処理をスキップ
}

function validReview(): array
{
    return ['rating' => 5, 'title' => 'よい', 'body' => '乗りやすいバイクでした'];
}

function parkingSpot(): BikeParking
{
    return BikeParking::create([
        'name' => 'テスト駐輪場', 'prefecture' => '東京都', 'address' => 'p-'.uniqid(),
        'latitude' => 35.0, 'longitude' => 139.0,
    ]);
}

// ─────────── reviews ───────────

it('records a hashed ip (no raw ip) on a review', function () {
    $model = reviewModel();

    $this->actingAs(reviewer())
        ->postJson("/bikes/models/{$model->id}/reviews", validReview())
        ->assertOk();

    $r = Review::first();
    expect($r->submitter_ip_hash)->not->toBeNull()
        ->and(strlen($r->submitter_ip_hash))->toBe(64)
        ->and($r->submitter_ip_hash)->not->toContain('127.0.0.1');
});

it('rejects a review when the honeypot is filled', function () {
    $model = reviewModel();

    $this->actingAs(reviewer())
        ->postJson("/bikes/models/{$model->id}/reviews", validReview() + ['website' => 'http://spam'])
        ->assertStatus(422);

    expect(Review::count())->toBe(0);
});

it('blocks a review containing an ng word (not leaking the word)', function () {
    config()->set('ng_words.words', ['死ね']);
    $model = reviewModel();

    $res = $this->actingAs(reviewer())
        ->postJson("/bikes/models/{$model->id}/reviews", ['rating' => 1, 'title' => 'ふつう', 'body' => 'こんな店は死ね'])
        ->assertStatus(422);

    expect(Review::count())->toBe(0)
        ->and(json_encode($res->json('errors')))->not->toContain('死ね');
});

it('allows a legitimate negative review through', function () {
    config()->set('ng_words.words', ['死ね', '殺す']);
    $model = reviewModel();

    $this->actingAs(reviewer())
        ->postJson("/bikes/models/{$model->id}/reviews", ['rating' => 2, 'title' => '不満', 'body' => '乗り心地が悪いと感じた'])
        ->assertOk();

    expect(Review::where('body', '乗り心地が悪いと感じた')->exists())->toBeTrue();
});

// ─────────── parking_reviews ───────────

it('records a hashed ip (no raw ip) on a parking review', function () {
    $p = parkingSpot();

    $this->post("/parking/{$p->id}/review", ['rating' => 4, 'body' => '停めやすかった'])
        ->assertRedirect();

    $r = ParkingReview::first();
    expect($r->submitter_ip_hash)->not->toBeNull()
        ->and(strlen($r->submitter_ip_hash))->toBe(64)
        ->and($r->submitter_ip_hash)->not->toContain('127.0.0.1');
});

it('rejects a parking review when the honeypot is filled', function () {
    $p = parkingSpot();

    $this->post("/parking/{$p->id}/review", ['rating' => 4, 'body' => 'ok', 'website' => 'http://spam'])
        ->assertSessionHasErrors('website');

    expect(ParkingReview::count())->toBe(0);
});

it('blocks a parking review containing an ng word', function () {
    config()->set('ng_words.words', ['死ね']);
    $p = parkingSpot();

    $this->post("/parking/{$p->id}/review", ['rating' => 1, 'body' => '管理人は死ね'])
        ->assertSessionHasErrors('body');

    expect(ParkingReview::count())->toBe(0);
});
