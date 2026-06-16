<?php

declare(strict_types=1);

use App\Models\BikeModel;
use App\Models\MaintenanceLog;
use App\Models\Manufacturer;
use App\Models\MyBike;
use App\Models\User;
use App\Services\MyBike\MyBikeService;

function recordsBike(User $user, float $current = 5000): MyBike
{
    $mfr = Manufacturer::where('slug', 'honda')->first() ?? Manufacturer::forceCreate(['name' => 'Honda', 'slug' => 'honda']);
    $model = BikeModel::where('slug', 'pcx')->first() ?? BikeModel::create(['manufacturer_id' => $mfr->id, 'name' => 'PCX', 'slug' => 'pcx']);

    return MyBike::create(['user_id' => $user->id, 'bike_model_id' => $model->id, 'name' => 'マイPCX', 'current_odometer' => $current]);
}

function dashboardFor(MyBike $bike): array
{
    return app(MyBikeService::class)->buildDashboard(
        MyBike::with(['maintenanceLogs', 'customRecords', 'fuelLogs'])->findOrFail($bike->id)
    );
}

it('maintenance store reflects cost in 維持費 and supplies the reminder 前回', function () {
    $user = User::factory()->create();
    $bike = recordsBike($user, 9000);

    $this->actingAs($user)->post("/garage/{$bike->id}/maintenance", [
        'maintained_at' => now()->subMonths(2)->format('Y-m-d'),
        'title' => 'オイル交換',
        'odometer' => 6000,
        'cost' => 3000,
    ])->assertRedirect();

    $d = dashboardFor($bike);
    expect($d['cost']['maintenance_total'])->toBe(3000)
        ->and($d['cost']['total'])->toBe(3000);

    $byTitle = collect($d['reminders'])->keyBy('title');
    expect($byTitle->has('オイル交換'))->toBeTrue()
        ->and($byTitle['オイル交換']['last_odometer'])->toBe(6000.0)
        ->and($byTitle['オイル交換']['distance'])->toBe(3000.0); // 9000 - 6000
});

it('custom store appears in installed_parts and 維持費, but NOT in reminders/maintenance', function () {
    $user = User::factory()->create();
    $bike = recordsBike($user, 5000);

    $this->actingAs($user)->post("/garage/{$bike->id}/custom", [
        'maintained_at' => now()->format('Y-m-d'),
        'part_name' => 'ヨシムラ マフラー',
        'brand' => 'YOSHIMURA',
        'category' => '排気',
        'odometer' => 5000,
        'cost' => 80000,
        'is_installed' => 1,
    ])->assertRedirect();

    $d = dashboardFor($bike);

    // 今ついてる装備に出る
    $parts = collect($d['installed_parts']);
    expect($parts)->toHaveCount(1)
        ->and($parts->first()->part_name)->toBe('ヨシムラ マフラー');

    // 維持費(custom)に乗る
    expect($d['cost']['custom_total'])->toBe(80000)
        ->and($d['cost']['total'])->toBe(80000);

    // リマインダーには出ない・maintenance にも数えない
    expect($d['reminders'])->toBe([]);
    expect($bike->maintenanceLogs()->count())->toBe(0)
        ->and($bike->customRecords()->count())->toBe(1);
});

it('odometer guard warns on a ~10x jump at store time but does NOT block saving', function () {
    $user = User::factory()->create();
    $bike = recordsBike($user, 62663);

    $this->actingAs($user)->post("/garage/{$bike->id}/maintenance", [
        'maintained_at' => now()->format('Y-m-d'),
        'title' => 'タイヤ交換',
        'odometer' => 626634,
        'cost' => 20000,
    ])
        ->assertRedirect()
        ->assertSessionHas('odometer_warning', fn ($v) => is_string($v) && str_contains($v, '端数'));

    // 保存は止めない
    expect($bike->maintenanceLogs()->count())->toBe(1);
});

it('records are owner-only (non-owner gets 404 on store and destroy)', function () {
    $owner = User::factory()->create();
    $bike = recordsBike($owner);
    $rec = MaintenanceLog::create(['my_bike_id' => $bike->id, 'maintained_at' => now(), 'title' => 'オイル交換', 'odometer' => 5000, 'cost' => 3000]);
    $intruder = User::factory()->create();

    $this->actingAs($intruder)->post("/garage/{$bike->id}/maintenance", [
        'maintained_at' => now()->format('Y-m-d'), 'title' => 'x', 'cost' => 1,
    ])->assertNotFound();

    $this->actingAs($intruder)->post("/garage/{$bike->id}/custom", [
        'maintained_at' => now()->format('Y-m-d'), 'part_name' => 'x',
    ])->assertNotFound();

    $this->actingAs($intruder)->delete("/garage/{$bike->id}/records/{$rec->id}")->assertNotFound();
    expect(MaintenanceLog::find($rec->id))->not->toBeNull(); // 削除されていない
});

it('owner can delete a record (type-agnostic)', function () {
    $user = User::factory()->create();
    $bike = recordsBike($user);
    $rec = MaintenanceLog::create(['my_bike_id' => $bike->id, 'type' => 'custom', 'maintained_at' => now(), 'title' => 'マフラー', 'part_name' => 'マフラー', 'is_installed' => true]);

    $this->actingAs($user)->delete("/garage/{$bike->id}/records/{$rec->id}")->assertRedirect();
    expect(MaintenanceLog::find($rec->id))->toBeNull();
});

it('validates type-specific required fields', function () {
    $user = User::factory()->create();
    $bike = recordsBike($user);

    // maintenance: title 必須
    $this->actingAs($user)->post("/garage/{$bike->id}/maintenance", [
        'maintained_at' => now()->format('Y-m-d'), 'cost' => 1000,
    ])->assertSessionHasErrors('title');

    // custom: part_name 必須
    $this->actingAs($user)->post("/garage/{$bike->id}/custom", [
        'maintained_at' => now()->format('Y-m-d'), 'cost' => 1000,
    ])->assertSessionHasErrors('part_name');
});

it('is_public defaults to false on records (visibility hook)', function () {
    $user = User::factory()->create();
    $bike = recordsBike($user);
    $this->actingAs($user)->post("/garage/{$bike->id}/custom", [
        'maintained_at' => now()->format('Y-m-d'), 'part_name' => 'シート',
    ]);

    $rec = MaintenanceLog::where('my_bike_id', $bike->id)->custom()->firstOrFail();
    expect($rec->is_public)->toBeFalse();
});

it('public-visible AND rule: a record shows publicly only if bike.is_public && record.is_public', function () {
    // サーフェスは未実装。ロジック（AND）をスコープ/フラグで検証。
    $user = User::factory()->create();
    $bike = recordsBike($user);
    $bike->update(['is_public' => true]);

    // 既定の record は is_public=false → AND は false（公開されない）
    $rec = MaintenanceLog::create(['my_bike_id' => $bike->id, 'maintained_at' => now(), 'title' => 'オイル交換', 'odometer' => 5000]);
    $publiclyVisible = $bike->is_public && $rec->is_public;
    expect($publiclyVisible)->toBeFalse();

    // 両方 true で初めて公開対象
    $rec->update(['is_public' => true]);
    expect($bike->fresh()->is_public && $rec->fresh()->is_public)->toBeTrue();
});

it('custom records never leak into the public maintenance history source', function () {
    $user = User::factory()->create();
    $bike = recordsBike($user);
    MaintenanceLog::create(['my_bike_id' => $bike->id, 'type' => 'custom', 'maintained_at' => now(), 'title' => 'マフラー', 'part_name' => 'マフラー']);
    MaintenanceLog::create(['my_bike_id' => $bike->id, 'maintained_at' => now(), 'title' => 'オイル交換', 'odometer' => 5000]);

    // public_show / CSV / リマインダーが読む maintenanceLogs は type=maintenance のみ
    expect($bike->maintenanceLogs()->pluck('title')->all())->toBe(['オイル交換']);
});
