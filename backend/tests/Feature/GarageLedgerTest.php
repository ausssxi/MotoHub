<?php

declare(strict_types=1);

use App\Models\BikeModel;
use App\Models\FuelLog;
use App\Models\MaintenanceLog;
use App\Models\Manufacturer;
use App\Models\MyBike;
use App\Models\User;
use App\Services\MyBike\MyBikeService;

function ledgerBike(User $user, float $initial = 0, float $current = 0): MyBike
{
    $mfr = Manufacturer::where('slug', 'honda')->first() ?? Manufacturer::forceCreate(['name' => 'Honda', 'slug' => 'honda']);
    $model = BikeModel::where('slug', 'pcx')->first() ?? BikeModel::create(['manufacturer_id' => $mfr->id, 'name' => 'PCX', 'slug' => 'pcx']);

    return MyBike::create([
        'user_id' => $user->id, 'bike_model_id' => $model->id, 'name' => 'マイPCX',
        'initial_odometer' => $initial, 'current_odometer' => $current,
    ]);
}

function ledgerFor(MyBike $bike): array
{
    return app(MyBikeService::class)->buildLedger(
        MyBike::with(['maintenanceLogs', 'customRecords', 'fuelLogs'])->findOrFail($bike->id)
    );
}

it('totals maintenance + custom + fuel into 総維持費', function () {
    $user = User::factory()->create();
    $bike = ledgerBike($user, 0, 10000);
    MaintenanceLog::create(['my_bike_id' => $bike->id, 'type' => 'maintenance', 'maintained_at' => now(), 'title' => 'オイル交換', 'cost' => 3000]);
    MaintenanceLog::create(['my_bike_id' => $bike->id, 'type' => 'custom', 'maintained_at' => now(), 'title' => 'マフラー', 'part_name' => 'マフラー', 'cost' => 80000]);
    FuelLog::create(['my_bike_id' => $bike->id, 'filled_at' => now(), 'odometer' => 9000, 'quantity' => 5, 'cost' => 900]);

    $l = ledgerFor($bike);
    expect($l['maintenance_total'])->toBe(3000)
        ->and($l['custom_total'])->toBe(80000)
        ->and($l['fuel_total'])->toBe(900)
        ->and($l['total'])->toBe(83900);
});

it('breaks down by 費目 (category) and sorts by amount', function () {
    $user = User::factory()->create();
    $bike = ledgerBike($user, 0, 10000);
    MaintenanceLog::create(['my_bike_id' => $bike->id, 'type' => 'maintenance', 'maintained_at' => now(), 'title' => 'エンジンオイル交換', 'cost' => 3000]);
    MaintenanceLog::create(['my_bike_id' => $bike->id, 'type' => 'maintenance', 'maintained_at' => now(), 'title' => 'オイルフィルター交換', 'cost' => 1000]);
    MaintenanceLog::create(['my_bike_id' => $bike->id, 'type' => 'maintenance', 'maintained_at' => now(), 'title' => 'タイヤ交換（後）', 'cost' => 18000]);
    MaintenanceLog::create(['my_bike_id' => $bike->id, 'type' => 'custom', 'maintained_at' => now(), 'title' => 'マフラー', 'part_name' => 'マフラー', 'cost' => 50000]);
    FuelLog::create(['my_bike_id' => $bike->id, 'filled_at' => now(), 'odometer' => 9000, 'quantity' => 5, 'cost' => 1000]);

    $byLabel = collect(ledgerFor($bike)['categories'])->keyBy('label');
    expect($byLabel['オイル']['amount'])->toBe(4000)   // 3000 + 1000（フィルター含む）
        ->and($byLabel['タイヤ']['amount'])->toBe(18000)
        ->and($byLabel['カスタム']['amount'])->toBe(50000)
        ->and($byLabel['燃料']['amount'])->toBe(1000);

    // 金額降順（カスタム50000が先頭）
    expect(ledgerFor($bike)['categories'][0]['label'])->toBe('カスタム');
});

it('computes km単価 from the running-max odometer span (current - initial)', function () {
    $user = User::factory()->create();
    $bike = ledgerBike($user, 1000, 11000); // 走行 10000km
    MaintenanceLog::create(['my_bike_id' => $bike->id, 'type' => 'maintenance', 'maintained_at' => now(), 'title' => 'オイル交換', 'cost' => 5000]);
    FuelLog::create(['my_bike_id' => $bike->id, 'filled_at' => now(), 'odometer' => 11000, 'quantity' => 5, 'cost' => 5000]);

    $l = ledgerFor($bike);
    expect($l['distance'])->toBe(10000.0)
        ->and($l['per_km'])->toBe(1.0); // 10000円 / 10000km
});

it('km単価 is null (no division by zero) when distance is 0 or single record', function () {
    $user = User::factory()->create();
    $bike = ledgerBike($user, 5000, 5000); // initial == current → 距離0
    MaintenanceLog::create(['my_bike_id' => $bike->id, 'type' => 'maintenance', 'maintained_at' => now(), 'title' => 'オイル交換', 'cost' => 3000]);

    $l = ledgerFor($bike);
    expect($l['distance'])->toBe(0.0)
        ->and($l['per_km'])->toBeNull()
        ->and($l['total'])->toBe(3000); // 合計は出る
});

it('aggregates by month and by year', function () {
    $user = User::factory()->create();
    $bike = ledgerBike($user, 0, 10000);
    MaintenanceLog::create(['my_bike_id' => $bike->id, 'type' => 'maintenance', 'maintained_at' => now()->setDate(2026, 6, 10), 'title' => 'オイル交換', 'cost' => 3000]);
    FuelLog::create(['my_bike_id' => $bike->id, 'filled_at' => now()->setDate(2026, 6, 20), 'odometer' => 9000, 'quantity' => 5, 'cost' => 1000]);
    MaintenanceLog::create(['my_bike_id' => $bike->id, 'type' => 'maintenance', 'maintained_at' => now()->setDate(2025, 3, 1), 'title' => 'タイヤ交換', 'cost' => 15000]);

    $l = ledgerFor($bike);
    expect($l['by_month']['2026-06']['total'])->toBe(4000)   // 3000 + 1000
        ->and($l['by_year']['2026']['total'])->toBe(4000)
        ->and($l['by_year']['2025']['total'])->toBe(15000);
});

it('is graceful with no records (empty ledger)', function () {
    $user = User::factory()->create();
    $bike = ledgerBike($user, 0, 0);

    $l = ledgerFor($bike);
    expect($l['total'])->toBe(0)
        ->and($l['record_count'])->toBe(0)
        ->and($l['categories'])->toBe([])
        ->and($l['per_km'])->toBeNull()
        ->and($l['from'])->toBeNull();
});

it('recomputes after a record is deleted', function () {
    $user = User::factory()->create();
    $bike = ledgerBike($user, 0, 10000);
    MaintenanceLog::create(['my_bike_id' => $bike->id, 'type' => 'maintenance', 'maintained_at' => now(), 'title' => 'オイル交換', 'cost' => 3000]);
    $rec = MaintenanceLog::create(['my_bike_id' => $bike->id, 'type' => 'maintenance', 'maintained_at' => now(), 'title' => 'タイヤ交換', 'cost' => 18000]);

    expect(ledgerFor($bike)['total'])->toBe(21000);

    $this->actingAs($user)->delete("/garage/{$bike->id}/records/{$rec->id}")->assertRedirect();

    expect(ledgerFor($bike)['total'])->toBe(3000); // 再計算される
});

it('renders the ledger tab with totals on the garage page', function () {
    $user = User::factory()->create();
    $bike = ledgerBike($user, 0, 10000);
    MaintenanceLog::create(['my_bike_id' => $bike->id, 'type' => 'maintenance', 'maintained_at' => now(), 'title' => 'オイル交換', 'cost' => 3000]);
    FuelLog::create(['my_bike_id' => $bike->id, 'filled_at' => now(), 'odometer' => 9000, 'quantity' => 5, 'cost' => 1000]);

    $this->actingAs($user)->get("/garage/{$bike->id}")
        ->assertOk()
        ->assertSee('会計簿')
        ->assertSee('総維持費')
        ->assertSee('km単価')
        ->assertSee('費目別内訳');
});
