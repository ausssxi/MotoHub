<?php

use App\Models\BikeParking;
use App\Models\ParkingUsage;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function usedPk(array $attrs = []): BikeParking
{
    return BikeParking::create(array_merge([
        'name' => 'テスト駐輪場', 'address' => 'テスト住所1-2-3',
        'latitude' => 35.6595, 'longitude' => 139.7005,
        'prefecture' => '東京都', 'city' => '渋谷区', 'parking_type' => 'bike_only',
    ], $attrs));
}

// ─────────── POST /parking/{id}/used ───────────

it('increments the count on a used tap and returns json', function () {
    $p = usedPk();

    $this->post("/parking/{$p->id}/used")
        ->assertOk()
        ->assertJson(['count' => 1, 'already' => false]);

    expect(ParkingUsage::where('parking_id', $p->id)->count())->toBe(1);
});

it('does not increment on a second tap from the same visitor (success + current count)', function () {
    $p = usedPk();

    // 同一テスト内の2リクエストは同じ IP + UA → 同じ visitor_hash
    $this->post("/parking/{$p->id}/used")->assertJson(['count' => 1, 'already' => false]);
    $this->post("/parking/{$p->id}/used")->assertOk()->assertJson(['count' => 1, 'already' => true]);

    expect(ParkingUsage::where('parking_id', $p->id)->count())->toBe(1); // 増えない
});

it('never stores the raw ip (hashed visitor_hash only)', function () {
    $p = usedPk();
    $this->post("/parking/{$p->id}/used");

    $usage = ParkingUsage::first();
    expect(strlen($usage->visitor_hash))->toBe(64)
        ->and($usage->visitor_hash)->not->toContain('127.0.0.1');
});

it('returns 404 for a non-existent parking id', function () {
    $this->post('/parking/999999/used')->assertNotFound();
});

it('returns 404 when the parking is not active', function () {
    $p = usedPk(['is_active' => false]);
    $this->post("/parking/{$p->id}/used")->assertNotFound();
});

// ─────────── /parking/{id} 表示 ───────────

it('shows the used button with zero-state copy when no one has tapped', function () {
    $p = usedPk();

    $this->get("/parking/{$p->id}")
        ->assertOk()
        ->assertSee('ここ、使ったことある')
        ->assertSee('最初の1人になりませんか？');
});

it('shows the used count once one or more visitors have tapped', function () {
    $p = usedPk();
    ParkingUsage::create(['parking_id' => $p->id, 'visitor_hash' => str_repeat('a', 64)]);

    $this->get("/parking/{$p->id}")
        ->assertOk()
        ->assertSee('1人が利用');
});
