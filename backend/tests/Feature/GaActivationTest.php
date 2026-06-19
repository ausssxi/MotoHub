<?php

declare(strict_types=1);

use App\Models\BikeModel;
use App\Models\Manufacturer;
use App\Models\MyBike;
use App\Models\User;
use App\Services\MyBike\MyBikeService;

function gaUser(): User
{
    return User::factory()->create(['name' => '本名タロウ', 'email' => 'taro@example.com', 'review_display_name' => 'rider_x']);
}

function gaBike(User $user): MyBike
{
    $mfr = Manufacturer::where('slug', 'honda')->first() ?? Manufacturer::forceCreate(['name' => 'Honda', 'slug' => 'honda']);
    $model = BikeModel::where('slug', 'pcx')->first() ?? BikeModel::create(['manufacturer_id' => $mfr->id, 'name' => 'PCX', 'slug' => 'pcx']);

    return MyBike::create(['user_id' => $user->id, 'bike_model_id' => $model->id, 'name' => 'マイPCX', 'initial_odometer' => 0, 'current_odometer' => 1000]);
}

// ---- ファネルイベントのフラッシュ（成功時1回） ----

it('flashes garage_bike_add on garage bike registration', function () {
    $user = gaUser();
    $mfr = Manufacturer::where('slug', 'honda')->first() ?? Manufacturer::forceCreate(['name' => 'Honda', 'slug' => 'honda']);
    $model = BikeModel::where('slug', 'pcx')->first() ?? BikeModel::create(['manufacturer_id' => $mfr->id, 'name' => 'PCX', 'slug' => 'pcx']);

    $this->actingAs($user)->post('/garage', ['bike_model_id' => $model->id, 'name' => 'マイPCX'])
        ->assertRedirect()
        ->assertSessionHas('ga_garage_bike_add', true);
});

it('flashes garage_record_add with the record_type on each record save', function () {
    $user = gaUser();
    $bike = gaBike($user);

    $this->actingAs($user)->post("/garage/{$bike->id}/fuel", [
        'filled_at' => now()->format('Y-m-d'), 'odometer' => 1100, 'quantity' => 5, 'is_full_tank' => 1,
    ])->assertRedirect()->assertSessionHas('ga_garage_record_add', 'fuel');

    $this->actingAs($user)->post("/garage/{$bike->id}/maintenance", [
        'maintained_at' => now()->format('Y-m-d'), 'title' => 'オイル交換', 'odometer' => 1200,
    ])->assertRedirect()->assertSessionHas('ga_garage_record_add', 'maintenance');

    $this->actingAs($user)->post("/garage/{$bike->id}/custom", [
        'maintained_at' => now()->format('Y-m-d'), 'part_name' => 'マフラー', 'category' => '排気', 'is_installed' => 1,
    ])->assertRedirect()->assertSessionHas('ga_garage_record_add', 'custom');
});

it('actually emits the garage_record_add gtag event in the served HTML after a save (flash → redirect → layout)', function () {
    $user = gaUser();
    $bike = gaBike($user);

    $html = $this->actingAs($user)
        ->from("/garage/{$bike->id}")
        ->followingRedirects()
        ->post("/garage/{$bike->id}/fuel", [
            'filled_at' => now()->format('Y-m-d'), 'odometer' => 1100, 'quantity' => 5, 'is_full_tank' => 1,
        ])
        ->assertOk()
        ->getContent();

    expect($html)->toContain("gtag('event', 'garage_record_add', { record_type: 'fuel' })");
});

it('does NOT flash garage_record_add on an edit (update is not an activation add)', function () {
    $user = gaUser();
    $bike = gaBike($user);
    $rec = app(MyBikeService::class)->recordMaintenance($bike, ['maintained_at' => '2026-03-01', 'title' => 'オイル交換', 'odometer' => 1100]);

    $this->actingAs($user)->put("/garage/{$bike->id}/maintenance/{$rec->id}", [
        'maintained_at' => '2026-03-01', 'title' => 'オイル交換', 'odometer' => 1100, 'cost' => 3000,
    ])->assertRedirect()->assertSessionMissing('ga_garage_record_add');
});

// ---- user_id（再訪/クロスデバイス）＋ PII非送出 ----

it('sets the GA user_id (numeric internal id) for a logged-in user, without leaking name/email', function () {
    $user = gaUser();

    $html = $this->actingAs($user)->get('/garage')->assertOk()->getContent();

    // GA payload（gtag呼び出し行）だけを検査する。ヘッダ等で本人が自分の名前を見るのは別問題。
    $gtag = collect(explode("\n", $html))->filter(fn ($l) => str_contains($l, 'gtag('))->implode("\n");

    expect($gtag)->toContain("'user_id': '".$user->id."'")  // GA4 user_id = 内部数値ID
        ->not->toContain('本名タロウ')                       // 本名を送らない
        ->not->toContain('taro@example.com');               // メールを送らない
});

it('does NOT set a GA user_id for guests', function () {
    $html = $this->get(route('garage.public.index'))->assertOk()->getContent();

    expect($html)->not->toContain("'user_id':");
});
