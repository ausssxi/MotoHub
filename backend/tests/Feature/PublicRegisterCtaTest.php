<?php

declare(strict_types=1);

use App\Models\BikeModel;
use App\Models\Manufacturer;
use App\Models\MyBike;
use App\Models\User;

function ctaUser(): User
{
    return User::factory()->create(['review_display_name' => 'rider_x']);
}

function ctaPublicBike(User $user): MyBike
{
    $mfr = Manufacturer::where('slug', 'honda')->first() ?? Manufacturer::forceCreate(['name' => 'Honda', 'slug' => 'honda']);
    $model = BikeModel::where('slug', 'pcx')->first() ?? BikeModel::create(['manufacturer_id' => $mfr->id, 'name' => 'PCX', 'slug' => 'pcx']);

    return MyBike::create(['user_id' => $user->id, 'bike_model_id' => $model->id, 'name' => 'マイPCX', 'is_public' => true, 'initial_odometer' => 0, 'current_odometer' => 0]);
}

it('public garage shows the register CTA to guests, linking to /register', function () {
    $bike = ctaPublicBike(ctaUser());

    $this->get(route('garage.public.show', $bike->id))
        ->assertOk()
        ->assertSee('あなたも愛車を記録しよう')
        ->assertSee('無料で始める')
        ->assertSee(route('register'), false);
});

it('public garage hides the register CTA from logged-in users', function () {
    $owner = ctaUser();
    $bike = ctaPublicBike($owner);
    $viewer = User::factory()->create(['review_display_name' => 'viewer']);

    $this->actingAs($viewer)->get(route('garage.public.show', $bike->id))
        ->assertOk()
        ->assertDontSee('あなたも愛車を記録しよう');
});

it('rider profile shows the register CTA to guests, linking to /register', function () {
    $user = ctaUser();
    $token = $user->ensurePublicToken();
    ctaPublicBike($user);

    $this->get(route('riders.profile', $token))
        ->assertOk()
        ->assertSee('あなたも愛車を記録しよう')
        ->assertSee(route('register'), false);
});

it('rider profile hides the register CTA from logged-in users', function () {
    $user = ctaUser();
    $token = $user->ensurePublicToken();
    ctaPublicBike($user);
    $viewer = User::factory()->create(['review_display_name' => 'viewer']);

    $this->actingAs($viewer)->get(route('riders.profile', $token))
        ->assertOk()
        ->assertDontSee('あなたも愛車を記録しよう');
});
