<?php

declare(strict_types=1);

use App\Models\BikeModel;
use App\Models\FuelLog;
use App\Models\MaintenanceLog;
use App\Models\Manufacturer;
use App\Models\MyBike;
use App\Models\User;
use App\Services\MyBike\MyBikeService;

function dashBike(User $user, float $currentOdo = 10000): MyBike
{
    $mfr = Manufacturer::where('slug', 'honda')->first() ?? Manufacturer::forceCreate(['name' => 'Honda', 'slug' => 'honda']);
    $model = BikeModel::where('slug', 'pcx')->first() ?? BikeModel::create(['manufacturer_id' => $mfr->id, 'name' => 'PCX', 'slug' => 'pcx']);

    return MyBike::create(['user_id' => $user->id, 'bike_model_id' => $model->id, 'name' => 'マイPCX', 'current_odometer' => $currentOdo]);
}

function loadBike(int $id): MyBike
{
    return MyBike::with(['maintenanceLogs', 'fuelLogs'])->findOrFail($id);
}

it('cost summary aggregates total / last-12-months / by-year correctly', function () {
    $bike = dashBike(User::factory()->create());
    MaintenanceLog::create(['my_bike_id' => $bike->id, 'maintained_at' => now()->subMonths(3), 'title' => 'オイル交換', 'odometer' => 6000, 'cost' => 3000]);
    MaintenanceLog::create(['my_bike_id' => $bike->id, 'maintained_at' => now()->subYears(2), 'title' => 'タイヤ交換', 'odometer' => 1000, 'cost' => 5000]);
    FuelLog::create(['my_bike_id' => $bike->id, 'filled_at' => now()->subMonths(2), 'odometer' => 5800, 'quantity' => 5, 'cost' => 1000, 'efficiency' => 30]);
    FuelLog::create(['my_bike_id' => $bike->id, 'filled_at' => now()->subYears(2), 'odometer' => 900, 'quantity' => 5, 'cost' => 2000, 'efficiency' => 20]);

    $d = app(MyBikeService::class)->buildDashboard(loadBike($bike->id));

    expect($d['cost']['maintenance_total'])->toBe(8000);
    expect($d['cost']['fuel_total'])->toBe(3000);
    expect($d['cost']['total'])->toBe(11000);
    expect($d['cost']['last12'])->toBe(4000); // 直近の整備3000 + 給油1000
    expect($d['cost']['by_year'][now()->format('Y')]['total'])->toBe(4000);
});

it('fuel chart builds time-ascending data + average, and is safe with zero logs', function () {
    $bike = dashBike(User::factory()->create());
    FuelLog::create(['my_bike_id' => $bike->id, 'filled_at' => now()->subMonths(1), 'odometer' => 6000, 'quantity' => 5, 'cost' => 1000, 'efficiency' => 30]);
    FuelLog::create(['my_bike_id' => $bike->id, 'filled_at' => now()->subMonths(3), 'odometer' => 5000, 'quantity' => 5, 'cost' => 1000, 'efficiency' => 20]);

    $d = app(MyBikeService::class)->buildDashboard(loadBike($bike->id));
    expect($d['fuelChart']['data'])->toBe([20.0, 30.0]); // 古い→新しい
    expect($d['fuelChart']['average'])->toBe(25.0);

    // ログ0でも落ちない
    $empty = app(MyBikeService::class)->buildDashboard(loadBike(dashBike(User::factory()->create())->id));
    expect($empty['fuelChart']['data'])->toBe([]);
    expect($empty['fuelChart']['average'])->toBeNull();
    expect($empty['reminders'])->toBe([]);
});

it('reminders compute distance since latest same-type maintenance, with 目安 over-flag', function () {
    $bike = dashBike(User::factory()->create(), currentOdo: 10000);
    // オイル交換を2回（直近=最大odometer 6000）
    MaintenanceLog::create(['my_bike_id' => $bike->id, 'maintained_at' => now()->subYears(1), 'title' => 'オイル交換', 'odometer' => 2000, 'cost' => 3000]);
    MaintenanceLog::create(['my_bike_id' => $bike->id, 'maintained_at' => now()->subMonths(2), 'title' => 'オイル交換', 'odometer' => 6000, 'cost' => 3000]);
    MaintenanceLog::create(['my_bike_id' => $bike->id, 'maintained_at' => now()->subMonths(1), 'title' => 'タイヤ交換', 'odometer' => 1000, 'cost' => 5000]);

    $d = app(MyBikeService::class)->buildDashboard(loadBike($bike->id));
    $byTitle = collect($d['reminders'])->keyBy('title');

    // オイル: 10000-6000=4000（直近=odo最大の6000を採用）> 目安3000 → over
    expect($byTitle['オイル交換']['distance'])->toBe(4000.0);
    expect($byTitle['オイル交換']['over'])->toBeTrue();
    // タイヤ: 10000-1000=9000 < 目安15000 → not over
    expect($byTitle['タイヤ交換']['distance'])->toBe(9000.0);
    expect($byTitle['タイヤ交換']['over'])->toBeFalse();
    // 距離降順（タイヤ9000が先頭）
    expect($d['reminders'][0]['title'])->toBe('タイヤ交換');
});

it('CSV export is owner-only and contains the logs', function () {
    $owner = User::factory()->create();
    $bike = dashBike($owner);
    MaintenanceLog::create(['my_bike_id' => $bike->id, 'maintained_at' => now(), 'title' => 'オイル交換', 'odometer' => 6000, 'cost' => 3000, 'note' => 'G2 10W-40']);
    FuelLog::create(['my_bike_id' => $bike->id, 'filled_at' => now(), 'odometer' => 6100, 'quantity' => 5, 'cost' => 900, 'efficiency' => 28, 'memo' => '環七GS']);

    // 非所有者は 404
    $this->actingAs(User::factory()->create())->get("/garage/{$bike->id}/export")->assertNotFound();

    // 所有者は 200＋中身
    $res = $this->actingAs($owner)->get("/garage/{$bike->id}/export")->assertOk();
    $csv = $res->streamedContent();
    expect($csv)->toContain('オイル交換');
    expect($csv)->toContain('G2 10W-40');
    expect($csv)->toContain('環七GS');
    expect($csv)->toContain('整備');
    expect($csv)->toContain('給油');
});
