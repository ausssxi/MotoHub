<?php

declare(strict_types=1);

use App\Models\BikeModel;
use App\Models\Manufacturer;
use App\Models\MyBike;
use App\Models\User;

function garageBike(?string $handle): MyBike
{
    $mfr = Manufacturer::forceCreate(['name' => 'Honda', 'slug' => 'honda']);
    $model = BikeModel::create(['manufacturer_id' => $mfr->id, 'name' => 'PCX', 'slug' => 'pcx']);
    $user = User::factory()->create(['name' => '本名太郎', 'review_display_name' => $handle]);

    return MyBike::create(['user_id' => $user->id, 'bike_model_id' => $model->id, 'name' => 'マイPCX', 'current_odometer' => 1000]);
}

it('public garage index never leaks user->name; shows handle or 名無しライダー; noindex', function () {
    garageBike(null); // ハンドル未設定

    $this->get('/garage/public')
        ->assertOk()
        ->assertDontSee('本名太郎')           // 本名は出ない
        ->assertSee('名無しライダー')          // 未設定フォールバック
        ->assertSee('content="noindex, follow"', false); // 暫定noindex
});

it('public garage show never leaks user->name; shows handle or 名無しライダー; noindex', function () {
    $bike = garageBike(null);

    $this->get("/garage/public/{$bike->id}")
        ->assertOk()
        ->assertDontSee('本名太郎')
        ->assertSee('名無しライダー')
        ->assertSee('content="noindex, follow"', false);
});

it('public garage shows the review_display_name handle when set (not the real name)', function () {
    $bike = garageBike('rider_x');

    $this->get('/garage/public')
        ->assertOk()
        ->assertSee('rider_x')
        ->assertDontSee('本名太郎');

    $this->get("/garage/public/{$bike->id}")
        ->assertOk()
        ->assertSee('rider_x')
        ->assertDontSee('本名太郎');
});
