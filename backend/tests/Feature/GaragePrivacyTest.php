<?php

declare(strict_types=1);

use App\Models\BikeModel;
use App\Models\Manufacturer;
use App\Models\MyBike;
use App\Models\User;

function privacyBike(User $user, bool $public = false): MyBike
{
    // Manufacturer の name は fillable でないため forceCreate。繰り返し呼ばれるので存在チェック。
    $mfr = Manufacturer::where('slug', 'honda')->first() ?? Manufacturer::forceCreate(['name' => 'Honda', 'slug' => 'honda']);
    $model = BikeModel::where('slug', 'pcx')->first() ?? BikeModel::create(['manufacturer_id' => $mfr->id, 'name' => 'PCX', 'slug' => 'pcx']);

    return MyBike::create(['user_id' => $user->id, 'bike_model_id' => $model->id, 'name' => 'マイPCX', 'current_odometer' => 1000, 'is_public' => $public]);
}

it('new garage bikes default to private (is_public false)', function () {
    $bike = privacyBike(User::factory()->create());
    expect($bike->fresh()->is_public)->toBeFalse();
});

it('private bike public URL returns 404 (no leak)', function () {
    $bike = privacyBike(User::factory()->create(), public: false);
    $this->get("/garage/public/{$bike->id}")->assertNotFound();
});

it('public bike public URL renders 200', function () {
    $bike = privacyBike(User::factory()->create(['review_display_name' => 'rider_x']), public: true);
    $this->get("/garage/public/{$bike->id}")->assertOk()->assertSee('rider_x');
});

it('public garage index only lists public bikes', function () {
    $pub = privacyBike(User::factory()->create(['review_display_name' => 'rider_pub']), public: true);
    $priv = privacyBike(User::factory()->create(['review_display_name' => 'rider_priv']), public: false);

    $this->get('/garage/public')
        ->assertOk()
        ->assertSee('rider_pub')
        ->assertDontSee('rider_priv');
});

it('making public requires a handle when unset, and sets both handle and is_public', function () {
    $user = User::factory()->create(['name' => '本名太郎', 'review_display_name' => null]);
    $bike = privacyBike($user);

    // ハンドル未指定 → 失敗・非公開のまま
    $this->actingAs($user)
        ->from("/garage/{$bike->id}")
        ->post("/garage/{$bike->id}/visibility", ['is_public' => '1'])
        ->assertSessionHasErrors('review_handle');
    expect($bike->fresh()->is_public)->toBeFalse();
    expect($user->fresh()->review_display_name)->toBeNull();

    // ハンドル指定 → 公開・ハンドル固定（本名は使わない）
    $this->actingAs($user)
        ->post("/garage/{$bike->id}/visibility", ['is_public' => '1', 'review_handle' => 'rider_x']);
    expect($bike->fresh()->is_public)->toBeTrue();
    expect($user->fresh()->review_display_name)->toBe('rider_x');
});

it('owner can revert to private', function () {
    $user = User::factory()->create(['review_display_name' => 'rider_x']);
    $bike = privacyBike($user, public: true);

    $this->actingAs($user)->post("/garage/{$bike->id}/visibility", ['is_public' => '0']);
    expect($bike->fresh()->is_public)->toBeFalse();
});

it('a non-owner cannot change visibility (404)', function () {
    $owner = User::factory()->create();
    $bike = privacyBike($owner);
    $intruder = User::factory()->create();

    $this->actingAs($intruder)
        ->post("/garage/{$bike->id}/visibility", ['is_public' => '1', 'review_handle' => 'hax'])
        ->assertNotFound();
    expect($bike->fresh()->is_public)->toBeFalse();
});
