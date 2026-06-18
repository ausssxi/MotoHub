<?php

declare(strict_types=1);

use App\Models\BikeModel;
use App\Models\FuelLog;
use App\Models\MaintenanceLog;
use App\Models\Manufacturer;
use App\Models\MyBike;
use App\Models\User;
use App\Services\MyBike\MyBikeService;

function chartBike(User $user, float $current = 10000): MyBike
{
    $mfr = Manufacturer::where('slug', 'honda')->first() ?? Manufacturer::forceCreate(['name' => 'Honda', 'slug' => 'honda']);
    $model = BikeModel::where('slug', 'pcx')->first() ?? BikeModel::create(['manufacturer_id' => $mfr->id, 'name' => 'PCX', 'slug' => 'pcx']);

    return MyBike::create(['user_id' => $user->id, 'bike_model_id' => $model->id, 'name' => 'マイPCX', 'initial_odometer' => 0, 'current_odometer' => $current]);
}

function chartDataFor(MyBike $bike): array
{
    $svc = app(MyBikeService::class);
    $bike = MyBike::with(['maintenanceLogs', 'customRecords', 'fuelLogs'])->findOrFail($bike->id);

    return $svc->ledgerChartData($svc->buildLedger($bike));
}

it('shapes monthly data into ascending time order (labels + values)', function () {
    $user = User::factory()->create();
    $bike = chartBike($user);
    MaintenanceLog::create(['my_bike_id' => $bike->id, 'type' => 'maintenance', 'maintained_at' => now()->setDate(2026, 4, 5), 'title' => 'オイル交換', 'cost' => 3000]);
    FuelLog::create(['my_bike_id' => $bike->id, 'filled_at' => now()->setDate(2026, 6, 5), 'odometer' => 9000, 'quantity' => 5, 'cost' => 1000]);

    $m = chartDataFor($bike)['monthly'];
    // buildLedger は新しい順 → chart は古い順（昇順）に反転
    expect($m['labels'])->toBe(['2026-04', '2026-06'])
        ->and($m['values'])->toBe([3000, 1000]);
});

it('shapes yearly data ascending with 年 suffix', function () {
    $user = User::factory()->create();
    $bike = chartBike($user);
    MaintenanceLog::create(['my_bike_id' => $bike->id, 'type' => 'maintenance', 'maintained_at' => now()->setDate(2025, 3, 1), 'title' => 'タイヤ交換', 'cost' => 15000]);
    MaintenanceLog::create(['my_bike_id' => $bike->id, 'type' => 'maintenance', 'maintained_at' => now()->setDate(2026, 6, 1), 'title' => 'オイル交換', 'cost' => 3000]);

    $y = chartDataFor($bike)['yearly'];
    expect($y['labels'])->toBe(['2025年', '2026年'])
        ->and($y['values'])->toBe([15000, 3000]);
});

it('shapes category data in 3a descending order (labels + values aligned)', function () {
    $user = User::factory()->create();
    $bike = chartBike($user);
    MaintenanceLog::create(['my_bike_id' => $bike->id, 'type' => 'maintenance', 'maintained_at' => now(), 'title' => 'オイル交換', 'cost' => 3000]);
    MaintenanceLog::create(['my_bike_id' => $bike->id, 'type' => 'custom', 'maintained_at' => now(), 'title' => 'マフラー', 'part_name' => 'マフラー', 'cost' => 50000]);
    FuelLog::create(['my_bike_id' => $bike->id, 'filled_at' => now(), 'odometer' => 9000, 'quantity' => 5, 'cost' => 1000]);

    $c = chartDataFor($bike)['category'];
    expect($c['labels'])->toBe(['カスタム', 'オイル', '燃料'])   // 50000 > 3000 > 1000
        ->and($c['values'])->toBe([50000, 3000, 1000]);
});

it('is graceful with empty data (all chart arrays empty)', function () {
    $user = User::factory()->create();
    $bike = chartBike($user, 0);

    $d = chartDataFor($bike);
    expect($d['monthly']['labels'])->toBe([])
        ->and($d['monthly']['values'])->toBe([])
        ->and($d['yearly']['labels'])->toBe([])
        ->and($d['category']['labels'])->toBe([]);
});

it('handles a single record / single month / single category without breaking', function () {
    $user = User::factory()->create();
    $bike = chartBike($user);
    MaintenanceLog::create(['my_bike_id' => $bike->id, 'type' => 'maintenance', 'maintained_at' => now()->setDate(2026, 6, 1), 'title' => 'オイル交換', 'cost' => 3000]);

    $d = chartDataFor($bike);
    expect($d['monthly']['labels'])->toBe(['2026-06'])
        ->and($d['monthly']['values'])->toBe([3000])
        ->and($d['category']['labels'])->toBe(['オイル'])
        ->and($d['category']['values'])->toBe([3000])
        ->and($d['yearly']['labels'])->toBe(['2026年']);
});

it('renders ledger chart canvases and loads Chart.js on the garage page', function () {
    $user = User::factory()->create();
    $bike = chartBike($user);
    MaintenanceLog::create(['my_bike_id' => $bike->id, 'type' => 'maintenance', 'maintained_at' => now(), 'title' => 'オイル交換', 'cost' => 3000]);

    $this->actingAs($user)->get("/garage/{$bike->id}")
        ->assertOk()
        ->assertSee('ledgerMonthChart', false)
        ->assertSee('ledgerCategoryChart', false)
        ->assertSee('ledgerYearChart', false)
        ->assertSee('chart.umd.js', false)          // Chart.js 読込
        ->assertSee('drawLedgerCharts', false);     // 遅延描画フック
});
