<?php

declare(strict_types=1);

use App\Models\BikeModel;
use App\Models\Manufacturer;
use App\Models\MyBike;
use App\Models\User;

function glUser(): User
{
    return User::factory()->create(['review_display_name' => 'rider_x']);
}

function glBike(User $user, bool $public): MyBike
{
    $mfr = Manufacturer::where('slug', 'honda')->first() ?? Manufacturer::forceCreate(['name' => 'Honda', 'slug' => 'honda']);
    $model = BikeModel::where('slug', 'pcx')->first() ?? BikeModel::create(['manufacturer_id' => $mfr->id, 'name' => 'PCX', 'slug' => 'pcx']);

    return MyBike::create(['user_id' => $user->id, 'bike_model_id' => $model->id, 'name' => 'マイPCX', 'is_public' => $public, 'initial_odometer' => 0, 'current_odometer' => 0]);
}

it('user menu shows 公開プロフィールを見る linking to the own profile when a public token exists', function () {
    $user = glUser();
    $token = $user->ensurePublicToken();
    glBike($user, true);

    $this->actingAs($user)->get(route('mybikes.index'))
        ->assertOk()
        ->assertSee('公開プロフィールを見る')
        ->assertSee(route('riders.profile', $token), false);
});

it('user menu hides the profile item (no /riders link) when there is no public token', function () {
    $user = glUser(); // 公開ガレージ無し＝token無し
    glBike($user, false);

    $res = $this->actingAs($user)->get(route('mybikes.index'))->assertOk();
    $res->assertDontSee('公開プロフィールを見る');
    expect($res->getContent())->not->toContain('/riders/');
});

it('does not expose another users token in the nav', function () {
    $other = glUser();
    $otherToken = $other->ensurePublicToken();
    glBike($other, true);

    $me = User::factory()->create(['review_display_name' => 'me']);
    glBike($me, false); // 自分はtoken無し

    $res = $this->actingAs($me)->get(route('mybikes.index'))->assertOk();
    expect($res->getContent())->not->toContain($otherToken);
});

it('removed the standalone profile card from the garage body (consolidated to the menu)', function () {
    $user = glUser();
    $user->ensurePublicToken();
    glBike($user, true);

    // /garage 本文の旧カード文言は無い（メニューに一本化）
    $this->actingAs($user)->get(route('mybikes.index'))
        ->assertOk()
        ->assertDontSee('公開中のガレージ・活動が他の人にどう見えるか確認できます');
});
