<?php

declare(strict_types=1);

use App\Models\BikeModel;
use App\Models\Manufacturer;
use App\Models\MyBike;
use App\Models\User;

function profUser(string $realName = '本名タロウ', ?string $handle = 'rider_x', string $email = 'taro@example.com'): User
{
    return User::factory()->create(['name' => $realName, 'review_display_name' => $handle, 'email' => $email]);
}

function profModel(string $slug = 'pcx', string $name = 'PCX'): BikeModel
{
    $mfr = Manufacturer::where('slug', 'honda')->first() ?? Manufacturer::forceCreate(['name' => 'Honda', 'slug' => 'honda']);

    return BikeModel::where('slug', $slug)->first() ?? BikeModel::create(['manufacturer_id' => $mfr->id, 'name' => $name, 'slug' => $slug]);
}

function profBike(User $user, bool $public, ?string $name = 'マイPCX'): MyBike
{
    return MyBike::create([
        'user_id' => $user->id, 'bike_model_id' => profModel()->id, 'name' => $name,
        'is_public' => $public, 'initial_odometer' => 0, 'current_odometer' => 12345,
    ]);
}

it('shows the public profile with the handle and the user public garages', function () {
    $user = profUser();
    $token = $user->ensurePublicToken();
    profBike($user, true, 'CB号');

    $this->get(route('riders.profile', $token))
        ->assertOk()
        ->assertSee('rider_x')
        ->assertSee('CB号')
        ->assertSee(route('garage.public.show', $user->myBikes()->first()->id), false);
});

it('never leaks the real name, email, or internal user id on the profile', function () {
    $user = profUser('漏れ太郎', 'rider_x', 'leak@example.com');
    $token = $user->ensurePublicToken();
    profBike($user, true);

    $html = $this->get(route('riders.profile', $token))->assertOk()->getContent();

    expect($html)->not->toContain('漏れ太郎')
        ->not->toContain('leak@example.com')
        ->not->toContain('/riders/'.$user->id) // 連番idがURLに出ない
        ->and($token)->not->toBe((string) $user->id);
});

it('does not show private garages on the profile', function () {
    $user = profUser();
    $token = $user->ensurePublicToken();
    profBike($user, true, '公開号');
    profBike($user, false, 'ヒミツ号');

    $html = $this->get(route('riders.profile', $token))->assertOk()->getContent();
    expect($html)->toContain('公開号')->not->toContain('ヒミツ号');
});

it('does not show another users garages on the profile', function () {
    $a = profUser('Aさん', 'rider_a');
    $tokenA = $a->ensurePublicToken();
    profBike($a, true, 'Aの愛車');

    $b = profUser('Bさん', 'rider_b', 'b@example.com');
    $b->ensurePublicToken();
    profBike($b, true, 'Bの愛車');

    $html = $this->get(route('riders.profile', $tokenA))->assertOk()->getContent();
    expect($html)->toContain('Aの愛車')->not->toContain('Bの愛車')->not->toContain('rider_b');
});

it('404s for an unknown token', function () {
    $this->get(route('riders.profile', 'doesnotexist123'))->assertNotFound();
});

it('404s when the user has a token but no public garages (graceful)', function () {
    $user = profUser();
    $token = $user->ensurePublicToken();
    profBike($user, false); // 非公開のみ

    $this->get(route('riders.profile', $token))->assertNotFound();
});

it('uses 名無しライダー when the owner has no handle (no real name)', function () {
    $user = profUser('名前バレ太郎', null);
    $token = $user->ensurePublicToken();
    profBike($user, true);

    $html = $this->get(route('riders.profile', $token))->assertOk()->getContent();
    expect($html)->toContain('名無しライダー')->not->toContain('名前バレ太郎');
});

// ---- リンク差し込み点 ----

it('public garage page links the owner handle to the profile', function () {
    $user = profUser();
    $token = $user->ensurePublicToken();
    $bike = profBike($user, true);

    $this->get(route('garage.public.show', $bike->id))
        ->assertOk()
        ->assertSee(route('riders.profile', $token), false);
});

it('public garage index links the owner handle to the profile', function () {
    $user = profUser();
    $token = $user->ensurePublicToken();
    profBike($user, true);

    $this->get(route('garage.public.index'))
        ->assertOk()
        ->assertSee(route('riders.profile', $token), false);
});

// ---- トークン発番 ----

it('ensurePublicToken is idempotent and unique, not the sequential id', function () {
    $user = profUser();
    $t1 = $user->ensurePublicToken();
    $t2 = $user->fresh()->ensurePublicToken();

    expect($t1)->toBe($t2)                       // 冪等
        ->and(strlen($t1))->toBe(16)
        ->and($t1)->not->toBe((string) $user->id); // 連番idではない
});

it('publishing a garage assigns the owner a public token', function () {
    $user = profUser($real = '本名', null); // ハンドル未設定
    $bike = profBike($user, false);

    expect($user->public_token)->toBeNull();

    $this->actingAs($user)->post(route('mybikes.visibility', $bike->id), [
        'is_public' => 1, 'review_handle' => 'new_rider',
    ])->assertRedirect();

    expect($user->fresh()->public_token)->not->toBeNull();
});
