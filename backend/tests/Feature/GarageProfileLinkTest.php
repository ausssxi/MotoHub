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

it('shows a link to the own public profile when the user has a public token', function () {
    $user = glUser();
    $token = $user->ensurePublicToken();
    glBike($user, true);

    $this->actingAs($user)->get(route('mybikes.index'))
        ->assertOk()
        ->assertSee('自分の公開プロフィールを見る')
        ->assertSee(route('riders.profile', $token), false);
});

it('shows guidance (no link) when the user has no public token', function () {
    $user = glUser(); // 公開ガレージ無し＝token無し
    glBike($user, false);

    $res = $this->actingAs($user)->get(route('mybikes.index'))->assertOk();
    $res->assertSee('公開プロフィールページが作られます')
        ->assertDontSee('自分の公開プロフィールを見る');
    // /riders/ リンクが一切出ない（他人のtokenも出さない）
    expect($res->getContent())->not->toContain('/riders/');
});

it('does not expose another users token on my garage page', function () {
    $other = glUser();
    $otherToken = $other->ensurePublicToken();
    glBike($other, true);

    $me = User::factory()->create(['review_display_name' => 'me']);
    glBike($me, false); // 自分はtoken無し

    $res = $this->actingAs($me)->get(route('mybikes.index'))->assertOk();
    expect($res->getContent())->not->toContain($otherToken);
});
