<?php

declare(strict_types=1);

use App\Models\BikeModel;
use App\Models\FuelLog;
use App\Models\Manufacturer;
use App\Models\MyBike;
use App\Models\User;
use App\Services\MyBike\MyBikeService;

function odoBike(User $user, float $current = 0, float $initial = 0): MyBike
{
    $mfr = Manufacturer::where('slug', 'honda')->first() ?? Manufacturer::forceCreate(['name' => 'Honda', 'slug' => 'honda']);
    $model = BikeModel::where('slug', 'pcx')->first() ?? BikeModel::create(['manufacturer_id' => $mfr->id, 'name' => 'PCX', 'slug' => 'pcx']);

    return MyBike::create(['user_id' => $user->id, 'bike_model_id' => $model->id, 'name' => 'マイPCX', 'initial_odometer' => $initial, 'current_odometer' => $current]);
}

function odoSvc(): MyBikeService
{
    return app(MyBikeService::class);
}

// ====== ★回帰: 既存(距離あり)の燃費がリファクタ前後で一致 ======

it('REGRESSION: full-to-full with partials between yields the same efficiency as before', function () {
    // 旧ロジック: 満タン10000 → ちょい足し(partial)10300 → 満タン10600。
    // 満タン10600の燃費 = (10600-10000) / (10600の量 + 間のpartial量)
    $bike = odoBike(User::factory()->create());
    $s = odoSvc();
    $s->recordFuel($bike, ['filled_at' => '2026-01-01', 'odometer' => 10000, 'quantity' => 5, 'is_full_tank' => 1]);
    $s->recordFuel($bike, ['filled_at' => '2026-01-05', 'odometer' => 10300, 'quantity' => 4, 'is_full_tank' => 0]); // partial
    $s->recordFuel($bike, ['filled_at' => '2026-01-10', 'odometer' => 10600, 'quantity' => 6, 'is_full_tank' => 1]);

    $bike->refresh()->load('fuelLogs');
    $first = $bike->fuelLogs->firstWhere('odometer', 10000);
    $partial = $bike->fuelLogs->firstWhere('odometer', 10300);
    $last = $bike->fuelLogs->firstWhere('odometer', 10600);

    expect($first->efficiency)->toBeNull()                  // 最初の満タンは基準点のみ
        ->and($partial->efficiency)->toBeNull()            // partial は基準点にしない
        ->and((float) $last->efficiency)->toBe(60.0);      // (10600-10000)/(6+4)=600/10=60.0
});

it('REGRESSION: simple full-to-full efficiency (300km / 5L = 60)', function () {
    $bike = odoBike(User::factory()->create());
    $s = odoSvc();
    $s->recordFuel($bike, ['filled_at' => '2026-01-01', 'odometer' => 10000, 'quantity' => 5, 'is_full_tank' => 1]);
    $s->recordFuel($bike, ['filled_at' => '2026-02-01', 'odometer' => 10300, 'quantity' => 5, 'is_full_tank' => 1]);

    $last = $bike->refresh()->fuelLogs->firstWhere('odometer', 10300);
    expect((float) $last->efficiency)->toBe(60.0);
});

// ====== 距離なしを挟む系 ======

it('distance-less fill: its quantity counts toward the denominator, it has no efficiency itself', function () {
    // 満タン10000 → 距離なし満タン(レシートのみ・5L) → 満タン10600。
    // 最後の燃費 = (10600-10000) / (最後の量 + 距離なしの量)
    $bike = odoBike(User::factory()->create());
    $s = odoSvc();
    $s->recordFuel($bike, ['filled_at' => '2026-01-01', 'odometer' => 10000, 'quantity' => 5, 'is_full_tank' => 1]);
    $s->recordFuel($bike, ['filled_at' => '2026-01-05', 'odometer' => null, 'quantity' => 4, 'is_full_tank' => 1]); // 距離なし
    $s->recordFuel($bike, ['filled_at' => '2026-01-10', 'odometer' => 10600, 'quantity' => 6, 'is_full_tank' => 1]);

    $bike->refresh()->load('fuelLogs');
    $noDist = $bike->fuelLogs->firstWhere('odometer', null);
    $last = $bike->fuelLogs->firstWhere('odometer', 10600);

    expect($noDist->efficiency)->toBeNull()                 // 距離なしは基準点にならない
        ->and((float) $last->efficiency)->toBe(60.0);      // 600 / (6+4) = 60.0（距離なしの量も分母）
});

it('distance present -> none -> present: efficiency correct, current_odometer not broken', function () {
    $bike = odoBike(User::factory()->create());
    $s = odoSvc();
    $s->recordFuel($bike, ['filled_at' => '2026-01-01', 'odometer' => 20000, 'quantity' => 10, 'is_full_tank' => 1]);
    $s->recordFuel($bike, ['filled_at' => '2026-01-08', 'odometer' => null, 'quantity' => 5, 'is_full_tank' => 1]);
    $s->recordFuel($bike, ['filled_at' => '2026-01-15', 'odometer' => 20800, 'quantity' => 5, 'is_full_tank' => 1]);

    $bike->refresh();
    $last = $bike->fuelLogs->firstWhere('odometer', 20800);
    expect((float) $last->efficiency)->toBe(80.0)          // 800 / (5+5) = 80.0
        ->and((float) $bike->current_odometer)->toBe(20800.0); // 距離なしを無視した running-max
});

it('current_odometer ignores distance-less fills (running-max over non-null only)', function () {
    $bike = odoBike(User::factory()->create());
    $s = odoSvc();
    $s->recordFuel($bike, ['filled_at' => '2026-01-01', 'odometer' => 30000, 'quantity' => 5, 'is_full_tank' => 1]);
    $s->recordFuel($bike, ['filled_at' => '2026-02-01', 'odometer' => null, 'quantity' => 5, 'is_full_tank' => 1]);

    expect((float) $bike->refresh()->current_odometer)->toBe(30000.0);
});

// ====== スキップ（odometer_unknown）と必須 ======

it('soft-required: store rejects empty odometer without the unknown flag', function () {
    $user = User::factory()->create();
    $bike = odoBike($user);

    $this->actingAs($user)->post("/garage/{$bike->id}/fuel", [
        'filled_at' => '2026-01-01', 'quantity' => 5, 'is_full_tank' => 1, // odometer 無し・unknown 無し
    ])->assertSessionHasErrors('odometer');

    expect($bike->fuelLogs()->count())->toBe(0);
});

it('soft-required: store accepts empty odometer WITH the unknown flag (null saved)', function () {
    $user = User::factory()->create();
    $bike = odoBike($user);

    $this->actingAs($user)->post("/garage/{$bike->id}/fuel", [
        'filled_at' => '2026-01-01', 'quantity' => 5, 'cost' => 800, 'is_full_tank' => 1,
        'odometer_unknown' => '1',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $log = $bike->fuelLogs()->first();
    expect($log)->not->toBeNull()
        ->and($log->odometer)->toBeNull()
        ->and((int) $log->cost)->toBe(800);
});

it('distance-less fuel cost is still counted in the ledger', function () {
    $user = User::factory()->create();
    $bike = odoBike($user);
    odoSvc()->recordFuel($bike, ['filled_at' => '2026-01-01', 'odometer' => null, 'quantity' => 5, 'cost' => 900, 'is_full_tank' => 1]);

    $ledger = odoSvc()->buildLedger($bike->refresh());
    expect($ledger['fuel_total'])->toBe(900)
        ->and($ledger['total'])->toBe(900);
});

// ====== 編集で距離を埋めると遡及修正 ======

it('editing a distance-less fill to add the odometer retro-fixes efficiency', function () {
    $user = User::factory()->create();
    $bike = odoBike($user);
    $s = odoSvc();
    $s->recordFuel($bike, ['filled_at' => '2026-01-01', 'odometer' => 10000, 'quantity' => 5, 'is_full_tank' => 1]);
    $s->recordFuel($bike, ['filled_at' => '2026-01-10', 'odometer' => null, 'quantity' => 5, 'is_full_tank' => 1]);

    $bike->refresh();
    $second = $bike->fuelLogs->firstWhere('odometer', null);
    expect($second->efficiency)->toBeNull(); // まだ距離なし＝燃費なし

    // 編集で距離を 10500 に補完 → 基準点になり燃費が付く
    $this->actingAs($user)->put("/garage/{$bike->id}/fuel/{$second->id}", [
        'filled_at' => '2026-01-10', 'odometer' => 10500, 'quantity' => 5, 'is_full_tank' => 1,
    ])->assertRedirect();

    $fixed = FuelLog::find($second->id);
    expect((float) $fixed->odometer)->toBe(10500.0)
        ->and((float) $fixed->efficiency)->toBe(100.0)            // (10500-10000)/5 = 100
        ->and((float) $bike->refresh()->current_odometer)->toBe(10500.0);
});

// ====== 順序の安定（同日複数・バックデート挿入） ======

it('chronological order is stable for same-day fills (filled_at then id)', function () {
    $bike = odoBike(User::factory()->create());
    $s = odoSvc();
    // 同日に満タン2件（id順で基準点が決まる）
    $s->recordFuel($bike, ['filled_at' => '2026-01-01', 'odometer' => 10000, 'quantity' => 5, 'is_full_tank' => 1]);
    $s->recordFuel($bike, ['filled_at' => '2026-01-01', 'odometer' => 10200, 'quantity' => 4, 'is_full_tank' => 1]);

    $bike->refresh();
    $second = $bike->fuelLogs->firstWhere('odometer', 10200);
    expect((float) $second->efficiency)->toBe(50.0); // (10200-10000)/4 = 50.0（同日でも id 順で安定）
});

it('backdated insert recomputes later records efficiency', function () {
    $bike = odoBike(User::factory()->create());
    $s = odoSvc();
    // 先に新しい日付の満タンを入れる（この時点では基準点が前に無いので燃費なし）
    $s->recordFuel($bike, ['filled_at' => '2026-02-01', 'odometer' => 10600, 'quantity' => 6, 'is_full_tank' => 1]);
    expect($bike->refresh()->fuelLogs->firstWhere('odometer', 10600)->efficiency)->toBeNull();

    // 後から「過去日付」の満タンを挿入 → 後続(10600)の燃費が再計算される
    $s->recordFuel($bike, ['filled_at' => '2026-01-01', 'odometer' => 10000, 'quantity' => 5, 'is_full_tank' => 1]);

    $last = $bike->refresh()->fuelLogs->firstWhere('odometer', 10600);
    expect((float) $last->efficiency)->toBe(100.0); // (10600-10000)/6 = 100
});

// ====== UI: 距離不明トグルの描画＋距離未入力バッジ ======

it('fuel form renders the odometer-unknown toggle and hidden flag', function () {
    $user = User::factory()->create();
    $bike = odoBike($user);

    $html = $this->actingAs($user)->get("/garage/{$bike->id}")->assertOk()->getContent();
    expect($html)->toContain('name="odometer_unknown"')
        ->toContain('距離が分からない')
        ->toContain('odoUnknown');  // inline Alpine 状態
});

it('fuel history shows a 距離未入力 badge for null-odometer records', function () {
    $user = User::factory()->create();
    $bike = odoBike($user);
    odoSvc()->recordFuel($bike, ['filled_at' => '2026-01-01', 'odometer' => null, 'quantity' => 5, 'cost' => 800, 'is_full_tank' => 1]);

    $this->actingAs($user)->get("/garage/{$bike->id}")
        ->assertOk()
        ->assertSee('距離未入力');
});
