<?php

declare(strict_types=1);

use App\Models\BikeModel;
use App\Models\Manufacturer;
use App\Models\MyBike;
use App\Models\User;
use App\Services\MyBike\MyBikeService;

function editInfoBike(User $user, float $initial = 0, float $current = 0): MyBike
{
    $mfr = Manufacturer::where('slug', 'honda')->first() ?? Manufacturer::forceCreate(['name' => 'Honda', 'slug' => 'honda']);
    $model = BikeModel::where('slug', 'pcx')->first() ?? BikeModel::create(['manufacturer_id' => $mfr->id, 'name' => 'PCX', 'slug' => 'pcx']);

    return MyBike::create(['user_id' => $user->id, 'bike_model_id' => $model->id, 'name' => 'マイPCX', 'is_public' => false, 'initial_odometer' => $initial, 'current_odometer' => $current]);
}

it('owner can edit the nickname', function () {
    $user = User::factory()->create();
    $bike = editInfoBike($user);

    $this->actingAs($user)->put(route('mybikes.update', $bike->id), ['name' => '通勤号', 'initial_odometer' => 0])
        ->assertRedirect(route('mybikes.index'));

    expect($bike->fresh()->name)->toBe('通勤号');
});

it('clearing the nickname falls back to the model name (NOT NULL column holds the model name)', function () {
    $user = User::factory()->create();
    $bike = editInfoBike($user);

    $this->actingAs($user)->put(route('mybikes.update', $bike->id), ['name' => '', 'initial_odometer' => 0])
        ->assertRedirect();

    $fresh = $bike->fresh()->load('bikeModel');
    expect($fresh->name)->toBe('PCX')              // 空愛称→車種名を格納（NOT NULL設計）
        ->and($fresh->display_name)->toBe('PCX');
});

it('bike edit is owner-only (non-owner gets 404, unchanged)', function () {
    $owner = User::factory()->create();
    $bike = editInfoBike($owner);

    $this->actingAs(User::factory()->create())
        ->put(route('mybikes.update', $bike->id), ['name' => 'のっとり', 'initial_odometer' => 99999])
        ->assertNotFound();

    expect($bike->fresh()->name)->toBe('マイPCX');
});

it('editing the initial odometer recomputes km単価 (per_km) consistently with total', function () {
    $user = User::factory()->create();
    $bike = editInfoBike($user);
    $svc = app(MyBikeService::class);
    $svc->recordFuel($bike, ['filled_at' => '2026-01-01', 'odometer' => 10000, 'quantity' => 5, 'is_full_tank' => 1]);
    $svc->recordFuel($bike, ['filled_at' => '2026-02-01', 'odometer' => 10500, 'quantity' => 5, 'is_full_tank' => 1]);
    $svc->recordMaintenance($bike, ['maintained_at' => '2026-02-01', 'title' => 'オイル交換', 'odometer' => 10500, 'cost' => 3000]);

    // initial=0: distance = 10500 - 0 = 10500 → per_km = 3000 / 10500 = 0.3
    expect($svc->buildLedger($bike->fresh())['per_km'])->toBe(0.3);

    // initial を 10000 に編集 → distance = 10500 - 10000 = 500 → per_km = 3000 / 500 = 6.0
    $this->actingAs($user)->put(route('mybikes.update', $bike->id), ['name' => 'マイPCX', 'initial_odometer' => 10000])
        ->assertRedirect();

    $fresh = $bike->fresh();
    $ledger = $svc->buildLedger($fresh);
    expect((float) $fresh->initial_odometer)->toBe(10000.0)
        ->and((float) $fresh->current_odometer)->toBe(10500.0)   // running-max は記録依存で不変
        ->and($ledger['total'])->toBe(3000)                       // 総維持費は不変
        ->and($ledger['per_km'])->toBe(6.0);                      // km単価のみ再計算
});

it('warns (no hard block) when the initial odometer exceeds an existing record', function () {
    $user = User::factory()->create();
    $bike = editInfoBike($user);
    app(MyBikeService::class)->recordMaintenance($bike, ['maintained_at' => '2026-02-01', 'title' => '点検', 'odometer' => 5000]);

    // initial=8000 は最小記録(5000)より大きい＝矛盾 → 警告。ただし保存はされる。
    $this->actingAs($user)->put(route('mybikes.update', $bike->id), ['name' => 'マイPCX', 'initial_odometer' => 8000])
        ->assertRedirect()
        ->assertSessionHas('odometer_warning', fn ($v) => is_string($v) && str_contains($v, '8000') && str_contains($v, '5000'));

    expect((float) $bike->fresh()->initial_odometer)->toBe(8000.0); // hard block しない＝保存済み
});

it('does not warn when the initial odometer is consistent with records', function () {
    $user = User::factory()->create();
    $bike = editInfoBike($user);
    app(MyBikeService::class)->recordMaintenance($bike, ['maintained_at' => '2026-02-01', 'title' => '点検', 'odometer' => 5000]);

    $this->actingAs($user)->put(route('mybikes.update', $bike->id), ['name' => 'マイPCX', 'initial_odometer' => 3000])
        ->assertRedirect()
        ->assertSessionMissing('odometer_warning'); // 矛盾なし＝警告フラッシュしない
});

it('does not change the bike_model_id or is_public (out of scope fields ignored)', function () {
    $user = User::factory()->create();
    $bike = editInfoBike($user);
    $originalModelId = $bike->bike_model_id;

    $this->actingAs($user)->put(route('mybikes.update', $bike->id), [
        'name' => 'マイPCX', 'initial_odometer' => 0,
        'bike_model_id' => 999999, 'is_public' => 1, // 対象外＝無視されるべき
    ])->assertRedirect();

    $fresh = $bike->fresh();
    expect($fresh->bike_model_id)->toBe($originalModelId)
        ->and($fresh->is_public)->toBeFalse();
});
