<?php

declare(strict_types=1);

use App\Models\BikeModel;
use App\Models\Manufacturer;
use App\Models\MyBike;
use App\Models\User;

function dnUser(array $attrs = []): User
{
    return User::factory()->create(array_merge(['name' => '本名あつし', 'review_display_name' => null], $attrs));
}

function dnPublicBike(User $user): MyBike
{
    $mfr = Manufacturer::where('slug', 'honda')->first() ?? Manufacturer::forceCreate(['name' => 'Honda', 'slug' => 'honda']);
    $model = BikeModel::where('slug', 'pcx')->first() ?? BikeModel::create(['manufacturer_id' => $mfr->id, 'name' => 'PCX', 'slug' => 'pcx']);

    return MyBike::create(['user_id' => $user->id, 'bike_model_id' => $model->id, 'name' => 'マイPCX', 'is_public' => true, 'initial_odometer' => 0, 'current_odometer' => 0]);
}

// ---- displayName() ヘルパ ----

it('displayName prefers the handle, falls back to name, then 名無しライダー', function () {
    expect(dnUser(['review_display_name' => 'rider_x'])->displayName())->toBe('rider_x')
        ->and(dnUser(['review_display_name' => null, 'name' => '本名あつし'])->displayName())->toBe('本名あつし')
        ->and(dnUser(['review_display_name' => null, 'name' => ''])->displayName())->toBe('名無しライダー');
});

// ---- ログイン中UI（ナビ） ----

it('nav shows the handle when set', function () {
    $user = dnUser(['review_display_name' => 'rider_x']);
    $this->actingAs($user)->get(route('mybikes.index'))
        ->assertOk()
        ->assertSee('rider_x');
});

it('nav falls back to name when handle is unset (no blank)', function () {
    $user = dnUser(['review_display_name' => null, 'name' => 'なまえ太郎']);
    $this->actingAs($user)->get(route('mybikes.index'))
        ->assertOk()
        ->assertSee('なまえ太郎');
});

// ---- アカウント設定: 公開表示名 編集 ----

it('settings page shows the 公開表示名 field as the primary identity', function () {
    $user = dnUser(['review_display_name' => 'rider_x']);
    $this->actingAs($user)->get(route('profile.edit'))
        ->assertOk()
        ->assertSee('公開表示名')
        ->assertSee('name="review_display_name"', false)
        ->assertSee('rider_x', false); // 現在値が入っている
});

it('user can set and later change the handle (set-once lifted)', function () {
    $user = dnUser(['review_display_name' => null]);

    // 初回設定
    $this->actingAs($user)->patch(route('profile.update'), [
        'name' => '本名あつし', 'email' => $user->email, 'review_display_name' => 'first_handle',
    ])->assertRedirect();
    expect($user->fresh()->review_display_name)->toBe('first_handle');

    // 変更（set-once ではない）
    $this->actingAs($user)->patch(route('profile.update'), [
        'name' => '本名あつし', 'email' => $user->email, 'review_display_name' => 'second_handle',
    ])->assertRedirect();
    expect($user->fresh()->review_display_name)->toBe('second_handle');
});

it('clearing the handle sets it to null (UI falls back to name)', function () {
    $user = dnUser(['review_display_name' => 'rider_x']);

    $this->actingAs($user)->patch(route('profile.update'), [
        'name' => '本名あつし', 'email' => $user->email, 'review_display_name' => '',
    ])->assertRedirect();
    expect($user->fresh()->review_display_name)->toBeNull()
        ->and($user->fresh()->displayName())->toBe('本名あつし');
});

it('strips tags from the handle', function () {
    $user = dnUser();
    $this->actingAs($user)->patch(route('profile.update'), [
        'name' => '本名あつし', 'email' => $user->email, 'review_display_name' => '<b>rider</b>_x',
    ])->assertRedirect();
    expect($user->fresh()->review_display_name)->toBe('rider_x');
});

it('rejects a handle longer than 30 chars', function () {
    $user = dnUser();
    $this->actingAs($user)->patch(route('profile.update'), [
        'name' => '本名あつし', 'email' => $user->email, 'review_display_name' => str_repeat('あ', 31),
    ])->assertSessionHasErrors('review_display_name');
});

// ---- ★公開面に本名(name)が出ない ----

it('public garage of a handle-less user shows 名無しライダー, never the name', function () {
    $user = dnUser(['review_display_name' => null, 'name' => '公開本名タロウ']);
    $bike = dnPublicBike($user);

    $html = $this->get(route('garage.public.show', $bike->id))->assertOk()->getContent();
    expect($html)->not->toContain('公開本名タロウ');
    expect($html)->toContain('名無しライダー'); // フォールバックは name でなく名無し
});

it('public garage index of a handle-less user never shows the name', function () {
    $user = dnUser(['review_display_name' => null, 'name' => '一覧本名']);
    dnPublicBike($user);

    $html = $this->get(route('garage.public.index'))->assertOk()->getContent();
    expect($html)->not->toContain('一覧本名');
});
