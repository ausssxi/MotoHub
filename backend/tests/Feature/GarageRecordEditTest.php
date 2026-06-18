<?php

declare(strict_types=1);

use App\Models\BikeModel;
use App\Models\Manufacturer;
use App\Models\MyBike;
use App\Models\User;
use App\Services\MyBike\MyBikeService;

function editBike(User $user, float $current = 0): MyBike
{
    $mfr = Manufacturer::where('slug', 'honda')->first() ?? Manufacturer::forceCreate(['name' => 'Honda', 'slug' => 'honda']);
    $model = BikeModel::where('slug', 'pcx')->first() ?? BikeModel::create(['manufacturer_id' => $mfr->id, 'name' => 'PCX', 'slug' => 'pcx']);

    return MyBike::create(['user_id' => $user->id, 'bike_model_id' => $model->id, 'name' => 'マイPCX', 'initial_odometer' => 0, 'current_odometer' => $current]);
}

// ---- 給油の編集 ----

it('owner can edit a fuel log; efficiency and current_odometer are recomputed', function () {
    $user = User::factory()->create();
    $bike = editBike($user);
    $svc = app(MyBikeService::class);

    $svc->recordFuel($bike, ['filled_at' => '2026-01-01', 'odometer' => 10000, 'quantity' => 5, 'is_full_tank' => 1]);
    $svc->recordFuel($bike, ['filled_at' => '2026-02-01', 'odometer' => 10300, 'quantity' => 5, 'is_full_tank' => 1]);
    $second = $bike->fuelLogs()->where('odometer', 10300)->firstOrFail();

    expect((float) $second->efficiency)->toBe(60.0); // 300km / 5L

    $this->actingAs($user)->put(route('mybikes.fuel.update', [$bike->id, $second->id]), [
        'filled_at' => '2026-02-01', 'odometer' => 10600, 'quantity' => 5, 'is_full_tank' => 1,
    ])->assertRedirect();

    $second->refresh();
    $bike->refresh();
    expect((float) $second->efficiency)->toBe(120.0)          // 600km / 5L 再計算
        ->and((float) $bike->current_odometer)->toBe(10600.0); // running-max 再計算
});

it('lowering the max fuel odometer recomputes current_odometer downward', function () {
    $user = User::factory()->create();
    $bike = editBike($user);
    $svc = app(MyBikeService::class);
    $svc->recordFuel($bike, ['filled_at' => '2026-01-01', 'odometer' => 10000, 'quantity' => 5, 'is_full_tank' => 1]);
    $svc->recordFuel($bike, ['filled_at' => '2026-02-01', 'odometer' => 20000, 'quantity' => 5, 'is_full_tank' => 1]);
    $max = $bike->fuelLogs()->where('odometer', 20000)->firstOrFail();
    expect((float) $bike->fresh()->current_odometer)->toBe(20000.0);

    $this->actingAs($user)->put(route('mybikes.fuel.update', [$bike->id, $max->id]), [
        'filled_at' => '2026-02-01', 'odometer' => 12000, 'quantity' => 5, 'is_full_tank' => 1,
    ])->assertRedirect();

    expect((float) $bike->fresh()->current_odometer)->toBe(12000.0);
});

it('fuel edit is owner-only (non-owner gets 404, row unchanged)', function () {
    $owner = User::factory()->create();
    $bike = editBike($owner);
    app(MyBikeService::class)->recordFuel($bike, ['filled_at' => '2026-01-01', 'odometer' => 10000, 'quantity' => 5, 'is_full_tank' => 1]);
    $log = $bike->fuelLogs()->firstOrFail();

    $this->actingAs(User::factory()->create())->put(route('mybikes.fuel.update', [$bike->id, $log->id]), [
        'filled_at' => '2026-01-01', 'odometer' => 99999, 'quantity' => 5, 'is_full_tank' => 1,
    ])->assertNotFound();

    expect((float) $log->fresh()->odometer)->toBe(10000.0);
});

// ---- 整備の編集 ----

it('owner can edit a maintenance record; cost change is reflected in the ledger (維持費)', function () {
    $user = User::factory()->create();
    $bike = editBike($user);
    $svc = app(MyBikeService::class);
    $rec = $svc->recordMaintenance($bike, ['maintained_at' => '2026-03-01', 'title' => 'オイル交換', 'odometer' => 5000, 'cost' => 3000]);

    expect($svc->buildLedger($bike->fresh())['total'])->toBe(3000);

    $this->actingAs($user)->put(route('mybikes.maintenance.update', [$bike->id, $rec->id]), [
        'maintained_at' => '2026-03-01', 'title' => 'オイル交換', 'odometer' => 5000, 'cost' => 5000,
    ])->assertRedirect();

    $rec->refresh();
    expect($rec->cost)->toBe(5000)
        ->and($svc->buildLedger($bike->fresh())['total'])->toBe(5000);
});

it('editing an odometer on a maintenance record recomputes running-max', function () {
    $user = User::factory()->create();
    $bike = editBike($user);
    $svc = app(MyBikeService::class);
    $rec = $svc->recordMaintenance($bike, ['maintained_at' => '2026-03-01', 'title' => 'タイヤ', 'odometer' => 30000]);
    expect((float) $bike->fresh()->current_odometer)->toBe(30000.0);

    $this->actingAs($user)->put(route('mybikes.maintenance.update', [$bike->id, $rec->id]), [
        'maintained_at' => '2026-03-01', 'title' => 'タイヤ', 'odometer' => 15000,
    ])->assertRedirect();

    expect((float) $bike->fresh()->current_odometer)->toBe(15000.0);
});

it('maintenance update enforces type: a custom record cannot be edited via the maintenance endpoint (404)', function () {
    $user = User::factory()->create();
    $bike = editBike($user);
    $custom = app(MyBikeService::class)->recordCustom($bike, ['maintained_at' => '2026-03-01', 'part_name' => 'マフラー', 'category' => '排気', 'is_installed' => 1]);

    $this->actingAs($user)->put(route('mybikes.maintenance.update', [$bike->id, $custom->id]), [
        'maintained_at' => '2026-03-01', 'title' => 'すり替え',
    ])->assertNotFound();

    expect($custom->fresh()->part_name)->toBe('マフラー'); // 変わっていない
});

// ---- カスタムの編集 ----

it('owner can edit a custom record (part_name/title stay in sync, type-checked)', function () {
    $user = User::factory()->create();
    $bike = editBike($user);
    $svc = app(MyBikeService::class);
    $rec = $svc->recordCustom($bike, ['maintained_at' => '2026-03-01', 'part_name' => '旧マフラー', 'category' => '排気', 'is_installed' => 1, 'cost' => 50000]);

    $this->actingAs($user)->put(route('mybikes.custom.update', [$bike->id, $rec->id]), [
        'maintained_at' => '2026-03-01', 'part_name' => '新マフラー', 'category' => '排気', 'cost' => 80000, 'is_installed' => 1,
    ])->assertRedirect()->assertSessionHasNoErrors();

    $rec->refresh();
    expect($rec->part_name)->toBe('新マフラー')
        ->and($rec->title)->toBe('新マフラー') // 一覧互換のため title も追従
        ->and($rec->cost)->toBe(80000);
});

it('custom update enforces type: a maintenance record cannot be edited via the custom endpoint (404)', function () {
    $user = User::factory()->create();
    $bike = editBike($user);
    $maint = app(MyBikeService::class)->recordMaintenance($bike, ['maintained_at' => '2026-03-01', 'title' => 'オイル交換', 'odometer' => 5000]);

    $this->actingAs($user)->put(route('mybikes.custom.update', [$bike->id, $maint->id]), [
        'maintained_at' => '2026-03-01', 'part_name' => 'すり替え', 'category' => '排気',
    ])->assertNotFound();
});

// ---- フロント: 編集UIの結線 ----

it('the garage page renders edit affordances and PUT-spoofed forms for all three record types', function () {
    $user = User::factory()->create();
    $bike = editBike($user);
    $svc = app(MyBikeService::class);
    $svc->recordFuel($bike, ['filled_at' => '2026-01-01', 'odometer' => 10000, 'quantity' => 5, 'is_full_tank' => 1]);
    $svc->recordMaintenance($bike, ['maintained_at' => '2026-02-01', 'title' => 'オイル交換', 'odometer' => 10500, 'cost' => 3000]);
    $svc->recordCustom($bike, ['maintained_at' => '2026-02-15', 'part_name' => 'マフラー', 'category' => '排気', 'is_installed' => 1]);

    $html = $this->actingAs($user)->get("/garage/{$bike->id}")->assertOk()->getContent();

    // 履歴の編集ボタン（3type）
    expect($html)->toContain('editFuel($el)')
        ->toContain('editMaint($el)')
        ->toContain('editCustom($el)');

    // 各フォームが editId に応じて update URL へ PUT で切り替わる（method spoof）
    expect($html)->toContain("garage/{$bike->id}/fuel")
        ->toContain("garage/{$bike->id}/maintenance")
        ->toContain("garage/{$bike->id}/custom")
        ->toContain("editId ? 'PUT' : 'POST'");
});

// ---- 編集後の日付文脈ガード（問題1との結合） ----

it('after-edit odometer guard uses date context and excludes the record itself (no false warning)', function () {
    $user = User::factory()->create();
    $bike = editBike($user, 62663);
    $svc = app(MyBikeService::class);
    // 唯一の記録（current_odometer と同値）を、同日付で小さい値へ編集してもサーバ側で誤警告しない。
    $rec = $svc->recordMaintenance($bike, ['maintained_at' => '2026-06-10', 'title' => '整備', 'odometer' => 62663]);

    $warning = $svc->odometerWarningFor($bike->fresh(), ['maintained_at' => '2026-06-10', 'odometer' => 62000], null, $rec->id);
    expect($warning)->toBeNull();
});
