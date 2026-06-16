<?php

declare(strict_types=1);

use App\Models\BikeModel;
use App\Models\FuelLog;
use App\Models\MaintenanceLog;
use App\Models\Manufacturer;
use App\Models\MyBike;
use App\Models\User;
use App\Services\MyBike\MyBikeService;

function delBike(User $user, float $initial = 1000, float $current = 1000): MyBike
{
    $mfr = Manufacturer::where('slug', 'honda')->first() ?? Manufacturer::forceCreate(['name' => 'Honda', 'slug' => 'honda']);
    $model = BikeModel::where('slug', 'pcx')->first() ?? BikeModel::create(['manufacturer_id' => $mfr->id, 'name' => 'PCX', 'slug' => 'pcx']);

    return MyBike::create([
        'user_id' => $user->id, 'bike_model_id' => $model->id, 'name' => 'マイPCX',
        'initial_odometer' => $initial, 'current_odometer' => $current,
    ]);
}

function svc(): MyBikeService
{
    return app(MyBikeService::class);
}

/** 満タン3本（1000/1300/1600・各10L）。L2,L3 の efficiency=30.0。current=1600。 */
function seedThreeFull(MyBike $bike): array
{
    $s = svc();
    foreach ([1000, 1300, 1600] as $odo) {
        $s->recordFuel($bike, ['filled_at' => now(), 'odometer' => $odo, 'quantity' => 10, 'is_full_tank' => true]);
    }

    return FuelLog::where('my_bike_id', $bike->id)->orderBy('odometer')->get()->all();
}

it('middle deletion re-links efficiency of the next full-tank log', function () {
    $user = User::factory()->create();
    $bike = delBike($user);
    [$l1, $l2, $l3] = seedThreeFull($bike);

    expect((float) $l2->fresh()->efficiency)->toBe(30.0)
        ->and((float) $l3->fresh()->efficiency)->toBe(30.0);

    // 中間(L2=1300)を削除 → L3 は prev full=L1(1000) に再リンク: (1600-1000)/10 = 60.0
    $this->actingAs($user)->delete("/garage/{$bike->id}/fuel/{$l2->id}")->assertRedirect();

    expect(FuelLog::find($l2->id))->toBeNull()
        ->and((float) $l3->fresh()->efficiency)->toBe(60.0)
        ->and($l1->fresh()->efficiency)->toBeNull(); // 最初の満タンは依然 null
});

it('deleting the max-odometer log lowers current_odometer to the next max', function () {
    $user = User::factory()->create();
    $bike = delBike($user);
    [$l1, $l2, $l3] = seedThreeFull($bike);
    expect((float) $bike->fresh()->current_odometer)->toBe(1600.0);

    $this->actingAs($user)->delete("/garage/{$bike->id}/fuel/{$l3->id}")->assertRedirect();

    expect((float) $bike->fresh()->current_odometer)->toBe(1300.0); // 次のmax
});

it('deleting the first full-tank log nulls the next efficiency (no prior full)', function () {
    $user = User::factory()->create();
    $bike = delBike($user);
    [$l1, $l2, $l3] = seedThreeFull($bike);

    $this->actingAs($user)->delete("/garage/{$bike->id}/fuel/{$l1->id}")->assertRedirect();

    // L2 は基準の満タンを失う → null。L3 は prev full=L2 のまま 30.0
    expect($l2->fresh()->efficiency)->toBeNull()
        ->and((float) $l3->fresh()->efficiency)->toBe(30.0);
});

it('deleting all fuel logs leaves no efficiency and resets current_odometer to initial', function () {
    $user = User::factory()->create();
    $bike = delBike($user, initial: 1000);
    [$l1, $l2, $l3] = seedThreeFull($bike);

    foreach ([$l1, $l2, $l3] as $l) {
        $this->actingAs($user)->delete("/garage/{$bike->id}/fuel/{$l->id}");
    }

    expect(FuelLog::where('my_bike_id', $bike->id)->count())->toBe(0)
        ->and((float) $bike->fresh()->current_odometer)->toBe(1000.0); // 登録初期へ
});

it('fuel deletion is owner-only (non-owner gets 404, log not deleted)', function () {
    $owner = User::factory()->create();
    $bike = delBike($owner);
    [$l1] = seedThreeFull($bike);

    $this->actingAs(User::factory()->create())
        ->delete("/garage/{$bike->id}/fuel/{$l1->id}")
        ->assertNotFound();

    expect(FuelLog::find($l1->id))->not->toBeNull();
});

it('the fuel history renders a delete form with a confirm dialog', function () {
    $user = User::factory()->create();
    $bike = delBike($user);
    [$l1] = seedThreeFull($bike);

    $this->actingAs($user)->get("/garage/{$bike->id}")
        ->assertOk()
        ->assertSee(route('mybikes.fuel.destroy', [$bike->id, $l1->id]), false)
        ->assertSee('燃費と総走行距離が再計算されます', false);
});

it('recompute matches the insert-time formula (idempotent = reused式)', function () {
    $user = User::factory()->create();
    $bike = delBike($user);
    [$l1, $l2, $l3] = seedThreeFull($bike);

    $before = FuelLog::where('my_bike_id', $bike->id)->orderBy('odometer')->pluck('efficiency', 'id')->toArray();

    svc()->recomputeFuelEfficiency($bike->fresh()); // 再計算

    $after = FuelLog::where('my_bike_id', $bike->id)->orderBy('odometer')->pluck('efficiency', 'id')->toArray();
    expect($after)->toBe($before); // 挿入時と完全一致＝式の流用が正しい
});

// --- Part D: 記録側 destroyRecord の running-max 整合 ---

it('deleting the max-odometer maintenance record recomputes current_odometer', function () {
    $user = User::factory()->create();
    $bike = delBike($user, initial: 1000, current: 1000);
    // 整備(odo 8000)で current が 8000 まで前進
    svc()->recordMaintenance($bike, ['maintained_at' => now()->format('Y-m-d'), 'title' => 'オイル交換', 'odometer' => 8000, 'cost' => 3000]);
    expect((float) $bike->fresh()->current_odometer)->toBe(8000.0);

    $rec = MaintenanceLog::where('my_bike_id', $bike->id)->maintenance()->firstOrFail();
    $this->actingAs($user)->delete("/garage/{$bike->id}/records/{$rec->id}")->assertRedirect();

    // 他に odometer source 無し → 登録初期へ戻る
    expect((float) $bike->fresh()->current_odometer)->toBe(1000.0);
});

it('deleting a custom record reduces custom_total and recomputes current_odometer', function () {
    $user = User::factory()->create();
    $bike = delBike($user, initial: 500, current: 500);
    svc()->recordCustom($bike, ['maintained_at' => now()->format('Y-m-d'), 'part_name' => 'マフラー', 'odometer' => 9000, 'cost' => 80000]);
    expect((float) $bike->fresh()->current_odometer)->toBe(9000.0);

    $rec = MaintenanceLog::where('my_bike_id', $bike->id)->custom()->firstOrFail();
    $this->actingAs($user)->delete("/garage/{$bike->id}/records/{$rec->id}")->assertRedirect();

    $d = svc()->buildDashboard(MyBike::with(['maintenanceLogs', 'customRecords', 'fuelLogs'])->findOrFail($bike->id));
    expect($d['cost']['custom_total'])->toBe(0)
        ->and((float) $bike->fresh()->current_odometer)->toBe(500.0);
});
