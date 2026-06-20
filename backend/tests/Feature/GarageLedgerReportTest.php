<?php

declare(strict_types=1);

use App\Models\BikeModel;
use App\Models\Manufacturer;
use App\Models\MyBike;
use App\Models\User;
use App\Services\MyBike\MyBikeService;

function reportBike(float $current = 0, ?string $purchasedAt = null): MyBike
{
    $user = User::factory()->create();
    $mfr = Manufacturer::where('slug', 'honda')->first() ?? Manufacturer::forceCreate(['name' => 'Honda', 'slug' => 'honda']);
    $model = BikeModel::where('slug', 'pcx')->first() ?? BikeModel::create(['manufacturer_id' => $mfr->id, 'name' => 'PCX', 'slug' => 'pcx']);

    return MyBike::create([
        'user_id' => $user->id, 'bike_model_id' => $model->id, 'name' => 'マイPCX',
        'initial_odometer' => 0, 'current_odometer' => $current, 'purchased_at' => $purchasedAt,
    ]);
}

/** @return array<string,mixed> */
function reportFor(MyBike $bike): array
{
    $svc = app(MyBikeService::class);
    $bike->refresh()->load(['fuelLogs', 'maintenanceLogs', 'customRecords']);
    $ledger = $svc->buildLedger($bike);
    $dashboard = $svc->buildDashboard($bike);

    return $svc->buildLedgerReport($bike, $ledger, $dashboard);
}

it('aggregates the fuel report (count / total / averages / efficiency / frequency)', function () {
    $bike = reportBike(10600);
    $svc = app(MyBikeService::class);
    // 3回給油（満タン）: 燃費は2回目以降に算出される
    $svc->recordFuel($bike, ['filled_at' => '2026-01-01', 'odometer' => 10000, 'quantity' => 10, 'cost' => 1500, 'is_full_tank' => 1]);
    $svc->recordFuel($bike, ['filled_at' => '2026-01-11', 'odometer' => 10300, 'quantity' => 10, 'cost' => 1600, 'is_full_tank' => 1]); // 300km/10L=30
    $svc->recordFuel($bike, ['filled_at' => '2026-01-21', 'odometer' => 10600, 'quantity' => 10, 'cost' => 1700, 'is_full_tank' => 1]); // 300km/10L=30

    $r = reportFor($bike)['fuel'];
    expect($r['count'])->toBe(3)
        ->and($r['total'])->toBe(4800)                 // 1500+1600+1700
        ->and($r['avg_quantity'])->toBe(10.0)          // 30L / 3
        ->and($r['avg_cost'])->toBe(1600)              // 4800 / 3
        ->and($r['avg_efficiency'])->toBe(30.0)        // fuelChart 平均（無回帰）
        ->and($r['days_per_fill'])->toBe(10.0)         // (1/1→1/21=20日) / 2
        ->and($r['km_per_fill'])->toBe(300.0)          // (10600-10000=600) / 2
        ->and($r['last_at']->format('Y-m-d'))->toBe('2026-01-21');
});

it('fuel report avg_efficiency matches the existing dashboard fuelChart (no regression)', function () {
    $bike = reportBike(10300);
    $svc = app(MyBikeService::class);
    $svc->recordFuel($bike, ['filled_at' => '2026-01-01', 'odometer' => 10000, 'quantity' => 10, 'is_full_tank' => 1]);
    $svc->recordFuel($bike, ['filled_at' => '2026-02-01', 'odometer' => 10300, 'quantity' => 10, 'is_full_tank' => 1]);

    $bike->refresh()->load(['fuelLogs', 'maintenanceLogs', 'customRecords']);
    $dashboard = $svc->buildDashboard($bike);
    $report = $svc->buildLedgerReport($bike, $svc->buildLedger($bike), $dashboard);

    expect($report['fuel']['avg_efficiency'])->toBe($dashboard['fuelChart']['average']);
});

it('aggregates the maintenance report by category (total / count / last_at)', function () {
    $bike = reportBike(20000);
    $svc = app(MyBikeService::class);
    $svc->recordMaintenance($bike, ['maintained_at' => '2026-01-01', 'title' => 'エンジンオイル交換', 'odometer' => 5000, 'cost' => 3000]);
    $svc->recordMaintenance($bike, ['maintained_at' => '2026-03-01', 'title' => 'エンジンオイル交換', 'odometer' => 8000, 'cost' => 3200]);
    $svc->recordMaintenance($bike, ['maintained_at' => '2026-02-01', 'title' => 'タイヤ交換（後）', 'odometer' => 7000, 'cost' => 18000]);

    $m = reportFor($bike)['maintenance'];
    expect($m['count'])->toBe(3)
        ->and($m['total'])->toBe(24200);

    $byLabel = collect($m['categories'])->keyBy('label');
    expect($byLabel['オイル']['total'])->toBe(6200)
        ->and($byLabel['オイル']['count'])->toBe(2)
        ->and($byLabel['オイル']['last_at']->format('Y-m-d'))->toBe('2026-03-01') // 最新
        ->and($byLabel['タイヤ']['total'])->toBe(18000)
        ->and($byLabel['タイヤ']['count'])->toBe(1);
    // 金額降順（タイヤ18000 > オイル6200）
    expect($m['categories'][0]['label'])->toBe('タイヤ');
});

it('maintenance report reuses the dashboard reminders verbatim (次回予定が一致)', function () {
    $bike = reportBike(20000);
    $svc = app(MyBikeService::class);
    $svc->recordMaintenance($bike, ['maintained_at' => '2026-01-01', 'title' => 'エンジンオイル交換', 'odometer' => 5000, 'cost' => 3000]);

    $bike->refresh()->load(['fuelLogs', 'maintenanceLogs', 'customRecords']);
    $dashboard = $svc->buildDashboard($bike);
    $report = $svc->buildLedgerReport($bike, $svc->buildLedger($bike), $dashboard);

    expect($report['maintenance']['reminders'])->toBe($dashboard['reminders']);
    // ガイドライン（オイル=3000km）がリマインダーに乗っている
    expect(collect($report['maintenance']['reminders'])->firstWhere('title', 'エンジンオイル交換')['guideline'])->toBe(3000);
});

it('aggregates the custom report (count / total / installed specs reuse installed_parts)', function () {
    $bike = reportBike(20000);
    $svc = app(MyBikeService::class);
    $svc->recordCustom($bike, ['maintained_at' => '2026-01-01', 'part_name' => 'ヨシムラ マフラー', 'category' => '排気', 'is_installed' => 1, 'cost' => 80000]);
    $svc->recordCustom($bike, ['maintained_at' => '2026-02-01', 'part_name' => '旧グリップ', 'category' => 'その他', 'is_installed' => 0, 'cost' => 2000]);

    $c = reportFor($bike)['custom'];
    expect($c['count'])->toBe(2)
        ->and($c['total'])->toBe(82000)
        ->and($c['installed_count'])->toBe(1)                       // 装着中のみ
        ->and($c['installed']->first()->part_name)->toBe('ヨシムラ マフラー');
});

it('builds the summary (ownership period / monthly avg / per_km)', function () {
    // 購入から6ヶ月（diffInMonths+1）。total=12000 → 月平均2000。
    $bike = reportBike(6000, now()->subMonths(5)->format('Y-m-d'));
    $svc = app(MyBikeService::class);
    $svc->recordMaintenance($bike, ['maintained_at' => now()->subMonths(1)->format('Y-m-d'), 'title' => '整備', 'odometer' => 6000, 'cost' => 12000]);

    $s = reportFor($bike)['summary'];
    expect($s['months_owned'])->toBe(6)
        ->and($s['total'])->toBe(12000)
        ->and($s['monthly_avg'])->toBe(2000)     // 12000 / 6
        ->and($s['per_km'])->toBe(2.0)           // 12000 / (6000-0)
        ->and($s['distance'])->toBe(6000.0);
});

it('is graceful with no data (zeros / nulls, no errors)', function () {
    $bike = reportBike(0);
    $r = reportFor($bike);

    expect($r['fuel']['count'])->toBe(0)
        ->and($r['fuel']['avg_quantity'])->toBeNull()
        ->and($r['fuel']['avg_efficiency'])->toBeNull()
        ->and($r['fuel']['days_per_fill'])->toBeNull()
        ->and($r['maintenance']['count'])->toBe(0)
        ->and($r['maintenance']['categories'])->toBe([])
        ->and($r['custom']['count'])->toBe(0)
        ->and($r['custom']['installed_count'])->toBe(0)
        ->and($r['summary']['monthly_avg'])->toBeNull();   // 期間起点なし
});

it('is graceful with a single fuel record (no frequency yet)', function () {
    $bike = reportBike(10000);
    app(MyBikeService::class)->recordFuel($bike, ['filled_at' => '2026-01-01', 'odometer' => 10000, 'quantity' => 8, 'cost' => 1200, 'is_full_tank' => 1]);

    $r = reportFor($bike)['fuel'];
    expect($r['count'])->toBe(1)
        ->and($r['avg_quantity'])->toBe(8.0)
        ->and($r['avg_cost'])->toBe(1200)
        ->and($r['days_per_fill'])->toBeNull()   // 1件では頻度を出さない
        ->and($r['km_per_fill'])->toBeNull()
        ->and($r['avg_efficiency'])->toBeNull(); // 満タン1回では燃費未算出（無回帰）
});
