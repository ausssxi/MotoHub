<?php

declare(strict_types=1);

use App\Models\BikeModel;
use App\Models\Manufacturer;
use App\Models\MyBike;
use App\Models\User;
use Illuminate\Support\Facades\Http;

function guardBike(User $user, float $current): MyBike
{
    $mfr = Manufacturer::where('slug', 'honda')->first() ?? Manufacturer::forceCreate(['name' => 'Honda', 'slug' => 'honda']);
    $model = BikeModel::where('slug', 'pcx')->first() ?? BikeModel::create(['manufacturer_id' => $mfr->id, 'name' => 'PCX', 'slug' => 'pcx']);

    return MyBike::create(['user_id' => $user->id, 'bike_model_id' => $model->id, 'name' => 'マイPCX', 'current_odometer' => $current]);
}

function fakeParse(array $payload): void
{
    Http::fake([
        'api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => json_encode($payload)]]], 200),
    ]);
}

// ---- B-1 helper unit ----

it('warns on a ~10x jump and hints at fractional digits (62663 -> 626634)', function () {
    $bike = guardBike(User::factory()->create(), 62663);
    $warning = $bike->odometerPlausibilityWarning(626634);

    expect($warning)->toContain('62663')
        ->toContain('626634')
        ->toContain('端数');
});

it('warns when the odometer goes backwards', function () {
    $bike = guardBike(User::factory()->create(), 50000);
    expect($bike->odometerPlausibilityWarning(49000))->toContain('小さく');
});

it('does not warn for a normal small increase', function () {
    $bike = guardBike(User::factory()->create(), 50000);
    expect($bike->odometerPlausibilityWarning(50120))->toBeNull();
});

it('does not warn when there is no history (L<=0)', function () {
    $bike = guardBike(User::factory()->create(), 0);
    expect($bike->odometerPlausibilityWarning(80000))->toBeNull();
});

it('does not warn when newOdometer is null', function () {
    $bike = guardBike(User::factory()->create(), 50000);
    expect($bike->odometerPlausibilityWarning(null))->toBeNull();
});

it('warns at 6x but not at 4x (multiplier boundary = 5)', function () {
    $bike = guardBike(User::factory()->create(), 10000);
    expect($bike->odometerPlausibilityWarning(60000))->not->toBeNull(); // 6x → warn
    expect($bike->odometerPlausibilityWarning(40000))->toBeNull();      // 4x → ok
    expect($bike->odometerPlausibilityWarning(50000))->toBeNull();      // exactly 5x → ok (strict >)
});

it('respects a config-tuned multiplier', function () {
    config(['garage.odometer_jump_multiplier' => 10]);
    $bike = guardBike(User::factory()->create(), 10000);
    expect($bike->odometerPlausibilityWarning(60000))->toBeNull();  // 6x now under 10x
    expect($bike->odometerPlausibilityWarning(110000))->not->toBeNull(); // 11x → warn
});

// ---- 日付文脈ガード（問題1: 過去日付の誤発火修正） ----

use App\Models\FuelLog;
use App\Models\MaintenanceLog;

function seedFuel(MyBike $bike, string $date, float $odo): FuelLog
{
    return FuelLog::create(['my_bike_id' => $bike->id, 'filled_at' => $date, 'odometer' => $odo, 'quantity' => 5, 'is_full_tank' => true]);
}

function seedMaint(MyBike $bike, string $date, ?float $odo): MaintenanceLog
{
    return MaintenanceLog::create(['my_bike_id' => $bike->id, 'maintained_at' => $date, 'title' => '整備', 'odometer' => $odo, 'type' => MaintenanceLog::TYPE_MAINTENANCE]);
}

it('does NOT warn for a past-dated record with a smaller odometer (symptom of 問題1)', function () {
    // running-max は給油6/15・62663km。過去日付6/06に62533kmは時系列的に正常。
    $bike = guardBike(User::factory()->create(), 62663);
    seedFuel($bike, '2026-06-15', 62663);

    expect($bike->odometerPlausibilityWarning(62533, '2026-06-06'))->toBeNull();
});

it('warns on a true time-series regression (a value smaller than an earlier-dated record)', function () {
    $bike = guardBike(User::factory()->create(), 62663);
    seedFuel($bike, '2026-06-01', 62000); // 入力日より前

    // 6/10 に 61000km は 6/1(62000km) を下回る＝逆行
    expect($bike->odometerPlausibilityWarning(61000, '2026-06-10'))
        ->not->toBeNull()
        ->toContain('前の記録');
});

it('warns when a value exceeds a later-dated record (inserting backwards in time)', function () {
    $bike = guardBike(User::factory()->create(), 70000);
    seedFuel($bike, '2026-06-20', 70000); // 入力日より後

    // 6/10 に 80000km は 6/20(70000km) を上回る＝時系列矛盾
    expect($bike->odometerPlausibilityWarning(80000, '2026-06-10'))
        ->not->toBeNull()
        ->toContain('後の記録');
});

it('warns on a ~10x jump in date context (hints at fractional digits)', function () {
    $bike = guardBike(User::factory()->create(), 62663);
    seedFuel($bike, '2026-06-01', 62663); // 直前

    expect($bike->odometerPlausibilityWarning(626634, '2026-06-10'))
        ->not->toBeNull()
        ->toContain('端数');
});

it('does NOT warn at the same-day boundary, nor on the very first record', function () {
    $bike = guardBike(User::factory()->create(), 62663);
    seedFuel($bike, '2026-06-10', 62663);

    // 同日は前後どちらにも含めない＝警告しない
    expect($bike->odometerPlausibilityWarning(50000, '2026-06-10'))->toBeNull();

    // 初回（前後に記録なし）も警告しない
    $fresh = guardBike(User::factory()->create(), 0);
    expect($fresh->odometerPlausibilityWarning(80000, '2026-06-10'))->toBeNull();
});

it('excludes the edited record itself from the bounds (edit reuse)', function () {
    $bike = guardBike(User::factory()->create(), 62663);
    $log = seedMaint($bike, '2026-06-10', 62663);

    // 自分自身を除外しなければ「同値の自分」とぶつかるが、除外すれば前後に他記録なし＝警告なし
    expect($bike->odometerPlausibilityWarning(62000, '2026-06-10', null, $log->id))->toBeNull();
});

// ---- B-2 response contract (voice + ocr) ----

it('voice response carries last_odometer and odometer_warning', function () {
    fakeParse(['odometer' => 626634, 'quantity' => null, 'cost' => null, 'date' => null, 'confidence' => '高']);
    $user = User::factory()->create();
    $bike = guardBike($user, 62663);

    $this->actingAs($user)->postJson("/garage/{$bike->id}/voice/fuel", ['transcript' => '62万キロ'])
        ->assertOk()
        ->assertJsonPath('last_odometer', 62663)
        ->assertJsonPath('values.odometer', 626634)
        ->json('odometer_warning');

    $res = $this->actingAs($user)->postJson("/garage/{$bike->id}/voice/fuel", ['transcript' => '62万キロ']);
    expect($res->json('odometer_warning'))->toContain('端数');
});

it('ocr meter response carries the guard, and is null when plausible', function () {
    fakeParse(['odometer' => 62800, 'quantity' => null, 'cost' => null, 'date' => null, 'confidence' => '高']);
    $user = User::factory()->create();
    $bike = guardBike($user, 62663);

    $this->actingAs($user)->postJson("/garage/{$bike->id}/ocr/fuel", [
        'image' => \Illuminate\Http\UploadedFile::fake()->image('meter.jpg', 800, 600),
        'type' => 'odometer',
    ])
        ->assertOk()
        ->assertJsonPath('last_odometer', 62663)
        ->assertJsonPath('odometer_warning', null); // 62800 は妥当 → 警告なし
});
