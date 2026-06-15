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
