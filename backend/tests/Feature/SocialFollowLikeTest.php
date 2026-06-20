<?php

declare(strict_types=1);

use App\Models\BikeModel;
use App\Models\Follow;
use App\Models\GarageLike;
use App\Models\Manufacturer;
use App\Models\MyBike;
use App\Models\User;

function socUser(?string $handle = 'rider_x', string $real = '本名タロウ', string $email = 'taro@example.com'): User
{
    return User::factory()->create(['name' => $real, 'review_display_name' => $handle, 'email' => $email]);
}

function socBike(User $user, bool $public = true): MyBike
{
    $mfr = Manufacturer::where('slug', 'honda')->first() ?? Manufacturer::forceCreate(['name' => 'Honda', 'slug' => 'honda']);
    $model = BikeModel::where('slug', 'pcx')->first() ?? BikeModel::create(['manufacturer_id' => $mfr->id, 'name' => 'PCX', 'slug' => 'pcx']);

    return MyBike::create(['user_id' => $user->id, 'bike_model_id' => $model->id, 'name' => 'マイPCX', 'is_public' => $public, 'initial_odometer' => 0, 'current_odometer' => 1000]);
}

// ---- フォロー ----

it('toggles follow and updates both counters', function () {
    $me = socUser('me', '本名み', 'me@example.com');
    $target = socUser('target', '本名た', 't@example.com');
    $token = $target->ensurePublicToken();

    $this->actingAs($me)->postJson(route('riders.follow', $token))
        ->assertOk()->assertJson(['following' => true, 'followers_count' => 1]);

    expect(Follow::where('follower_id', $me->id)->where('followee_id', $target->id)->count())->toBe(1)
        ->and($target->fresh()->followers_count)->toBe(1)
        ->and($me->fresh()->following_count)->toBe(1);

    // 再度叩くと解除
    $this->actingAs($me)->postJson(route('riders.follow', $token))
        ->assertOk()->assertJson(['following' => false, 'followers_count' => 0]);
    expect($target->fresh()->followers_count)->toBe(0)
        ->and($me->fresh()->following_count)->toBe(0);
});

it('does not double-follow (unique) even on rapid repeat', function () {
    $me = socUser('me2', 'x', 'me2@example.com');
    $target = socUser('t2', 'y', 't2@example.com');
    $token = $target->ensurePublicToken();

    $this->actingAs($me)->postJson(route('riders.follow', $token))->assertOk();
    // 直接二重作成を試みても unique で1件（カウンタも1のまま）
    expect(fn () => Follow::create(['follower_id' => $me->id, 'followee_id' => $target->id]))
        ->toThrow(\Illuminate\Database\QueryException::class);
    expect($target->fresh()->followers_count)->toBe(1);
});

it('rejects self-follow (422)', function () {
    $me = socUser('self', 'z', 'self@example.com');
    $token = $me->ensurePublicToken();

    $this->actingAs($me)->postJson(route('riders.follow', $token))->assertStatus(422);
    expect($me->fresh()->followers_count)->toBe(0);
});

it('requires auth to follow (guest redirected/401)', function () {
    $target = socUser('t3', 'y', 't3@example.com');
    $token = $target->ensurePublicToken();

    $this->post(route('riders.follow', $token))->assertRedirect(route('login'));
    expect($target->fresh()->followers_count)->toBe(0);
});

// ---- ガレージいいね ----

it('toggles a garage like and updates like_count', function () {
    $owner = socUser('owner', 'o', 'o@example.com');
    $liker = socUser('liker', 'l', 'l@example.com');
    $bike = socBike($owner);

    $this->actingAs($liker)->postJson(route('garage.like', $bike->id))
        ->assertOk()->assertJson(['liked' => true, 'like_count' => 1]);
    expect(GarageLike::where('user_id', $liker->id)->where('my_bike_id', $bike->id)->count())->toBe(1)
        ->and($bike->fresh()->like_count)->toBe(1);

    $this->actingAs($liker)->postJson(route('garage.like', $bike->id))
        ->assertOk()->assertJson(['liked' => false, 'like_count' => 0]);
    expect($bike->fresh()->like_count)->toBe(0);
});

it('does not double-like (unique)', function () {
    $owner = socUser('owner2', 'o', 'o2@example.com');
    $liker = socUser('liker2', 'l', 'l2@example.com');
    $bike = socBike($owner);

    $this->actingAs($liker)->postJson(route('garage.like', $bike->id))->assertOk();
    expect(fn () => GarageLike::create(['user_id' => $liker->id, 'my_bike_id' => $bike->id]))
        ->toThrow(\Illuminate\Database\QueryException::class);
    expect($bike->fresh()->like_count)->toBe(1);
});

it('rejects liking your own garage (422)', function () {
    $owner = socUser('owner3', 'o', 'o3@example.com');
    $bike = socBike($owner);

    $this->actingAs($owner)->postJson(route('garage.like', $bike->id))->assertStatus(422);
    expect($bike->fresh()->like_count)->toBe(0);
});

it('rejects liking a private garage (404)', function () {
    $owner = socUser('owner4', 'o', 'o4@example.com');
    $liker = socUser('liker4', 'l', 'l4@example.com');
    $bike = socBike($owner, public: false);

    $this->actingAs($liker)->postJson(route('garage.like', $bike->id))->assertNotFound();
});

it('requires auth to like (guest redirected)', function () {
    $owner = socUser('owner5', 'o', 'o5@example.com');
    $bike = socBike($owner);

    $this->post(route('garage.like', $bike->id))->assertRedirect(route('login'));
    expect($bike->fresh()->like_count)->toBe(0);
});

// ---- 表示・privacy・自己操作のUI ----

it('shows follow button + counts on the profile, not for self, no PII', function () {
    $owner = socUser('rider_x', '本名タロウ', 'taro@example.com');
    $token = $owner->ensurePublicToken();
    socBike($owner);
    $viewer = socUser('viewer', 'v', 'v@example.com');

    // 他人が見る: フォローボタンあり・本名なし
    $html = $this->actingAs($viewer)->get(route('riders.profile', $token))->assertOk()->getContent();
    expect($html)->toContain('riders/'.$token.'/follow')   // フォローのfetch先
        ->toContain('フォロワー')
        ->not->toContain('本名タロウ')
        ->not->toContain('taro@example.com');

    // 本人が見る: フォローボタンは出さない（数は出る）
    $ownHtml = $this->actingAs($owner)->get(route('riders.profile', $token))->assertOk()->getContent();
    expect($ownHtml)->not->toContain('riders/'.$token.'/follow')
        ->toContain('フォロワー');
});

it('shows the like button on a public garage, hidden for the owner (count only)', function () {
    $owner = socUser('owner6', 'o', 'o6@example.com');
    $bike = socBike($owner);
    $viewer = socUser('viewer6', 'v', 'v6@example.com');

    // 他人: いいねボタン（fetch先）あり
    $this->actingAs($viewer)->get(route('garage.public.show', $bike->id))
        ->assertOk()->assertSee('garage/'.$bike->id.'/like', false);

    // 本人: いいねの fetch ボタンは出さない
    $ownHtml = $this->actingAs($owner)->get(route('garage.public.show', $bike->id))->assertOk()->getContent();
    expect($ownHtml)->not->toContain('garage/'.$bike->id.'/like');
});

it('reflects already-liked state on the public garage page', function () {
    $owner = socUser('owner7', 'o', 'o7@example.com');
    $liker = socUser('liker7', 'l', 'l7@example.com');
    $bike = socBike($owner);
    GarageLike::create(['user_id' => $liker->id, 'my_bike_id' => $bike->id]);
    $bike->increment('like_count');

    $html = $this->actingAs($liker)->get(route('garage.public.show', $bike->id))->assertOk()->getContent();
    expect($html)->toContain('liked: true'); // inline Alpine 初期値が既いいねを反映
});
