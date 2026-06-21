<?php

declare(strict_types=1);

use App\Models\BikeModel;
use App\Models\FuelLog;
use App\Models\MaintenanceLog;
use App\Models\Manufacturer;
use App\Models\MyBike;
use App\Models\MyBikeImage;
use App\Models\User;
use App\Services\MyBike\MyBikeService;
use Illuminate\Support\Facades\Storage;

function sdUser(): User
{
    return User::factory()->create(['review_display_name' => 'rider_x']);
}

function sdBike(User $user, bool $public = false): MyBike
{
    $mfr = Manufacturer::where('slug', 'honda')->first() ?? Manufacturer::forceCreate(['name' => 'Honda', 'slug' => 'honda']);
    $model = BikeModel::where('slug', 'pcx')->first() ?? BikeModel::create(['manufacturer_id' => $mfr->id, 'name' => 'PCX', 'slug' => 'pcx']);

    return MyBike::create(['user_id' => $user->id, 'bike_model_id' => $model->id, 'name' => 'マイPCX', 'is_public' => $public, 'initial_odometer' => 0, 'current_odometer' => 0]);
}

/** 給油2件＋整備1件＋画像1件を持つ愛車を作る */
function sdBikeWithData(User $user, bool $public = false): MyBike
{
    $bike = sdBike($user, $public);
    $svc = app(MyBikeService::class);
    $svc->recordFuel($bike, ['filled_at' => '2026-01-01', 'odometer' => 10000, 'quantity' => 5, 'cost' => 800, 'is_full_tank' => 1]);
    $svc->recordFuel($bike, ['filled_at' => '2026-02-01', 'odometer' => 10500, 'quantity' => 5, 'cost' => 900, 'is_full_tank' => 1]);
    $svc->recordMaintenance($bike, ['maintained_at' => '2026-01-15', 'title' => 'オイル交換', 'odometer' => 10200, 'cost' => 3000]);
    MyBikeImage::create(['my_bike_id' => $bike->id, 'path' => 'garage/img_'.uniqid().'.jpg', 'sort_order' => 0]);

    return $bike->fresh();
}

// ====== 論理削除 + 子カスケード ======

it('soft-deletes the bike and cascades soft-delete to children', function () {
    $bike = sdBikeWithData(sdUser());
    $id = $bike->id;

    app(MyBikeService::class)->deleteBike($bike);

    // default scope では取得不可
    expect(MyBike::find($id))->toBeNull()
        ->and(FuelLog::where('my_bike_id', $id)->count())->toBe(0)
        ->and(MaintenanceLog::where('my_bike_id', $id)->count())->toBe(0)
        ->and(MyBikeImage::where('my_bike_id', $id)->count())->toBe(0);

    // withTrashed では残っている（deleted_at セット）
    expect(MyBike::withTrashed()->find($id))->not->toBeNull()
        ->and(FuelLog::withTrashed()->where('my_bike_id', $id)->count())->toBe(2)
        ->and(MaintenanceLog::withTrashed()->where('my_bike_id', $id)->count())->toBe(1)
        ->and(MyBikeImage::withTrashed()->where('my_bike_id', $id)->count())->toBe(1);
});

it('★REGRESSION: restore brings back the bike + fuel + maintenance + images, and efficiency/current_odometer', function () {
    $bike = sdBikeWithData(sdUser());
    $id = $bike->id;
    $effBefore = (float) FuelLog::where('my_bike_id', $id)->where('odometer', 10500)->first()->efficiency; // 100.0
    $odoBefore = (float) $bike->current_odometer; // 10500

    app(MyBikeService::class)->deleteBike($bike);
    MyBike::withTrashed()->find($id)->restore();

    // 一括復活（default scope で取得できる）
    expect(MyBike::find($id))->not->toBeNull()
        ->and(FuelLog::where('my_bike_id', $id)->count())->toBe(2)
        ->and(MaintenanceLog::where('my_bike_id', $id)->count())->toBe(1)
        ->and(MyBikeImage::where('my_bike_id', $id)->count())->toBe(1);

    // 燃費・current_odometer も元通り
    $restored = MyBike::find($id);
    $eff = (float) FuelLog::where('my_bike_id', $id)->where('odometer', 10500)->first()->efficiency;
    expect($eff)->toBe($effBefore)
        ->and((float) $restored->current_odometer)->toBe($odoBefore);
});

it('restore does NOT bring back a child that was individually deleted before the bike deletion', function () {
    $bike = sdBikeWithData(sdUser());
    $id = $bike->id;
    // 先に給油1件を個別削除（バイク削除より前＝別タイミングのユーザー操作）
    \Illuminate\Support\Carbon::setTestNow('2026-03-01 10:00:00');
    $victim = FuelLog::where('my_bike_id', $id)->where('odometer', 10000)->first();
    app(MyBikeService::class)->deleteFuelLog($bike, $victim->id);

    // 後日バイクを削除（カスケードはこの時刻で stamp）
    \Illuminate\Support\Carbon::setTestNow('2026-03-05 10:00:00');
    app(MyBikeService::class)->deleteBike($bike->fresh());
    MyBike::withTrashed()->find($id)->restore();
    \Illuminate\Support\Carbon::setTestNow();

    // 個別削除した1件は復活しない＝残り1件だけ復活
    expect(FuelLog::where('my_bike_id', $id)->count())->toBe(1)
        ->and(FuelLog::where('my_bike_id', $id)->where('odometer', 10500)->exists())->toBeTrue()
        ->and(FuelLog::where('my_bike_id', $id)->where('odometer', 10000)->exists())->toBeFalse();
});

// ====== 画像ファイルの扱い ======

it('soft delete keeps the image file; force delete removes it', function () {
    Storage::fake(config('garage.image_disk'));
    $user = sdUser();
    $bike = sdBike($user);
    $path = 'garage/keep_'.uniqid().'.jpg';
    Storage::disk(config('garage.image_disk'))->put($path, 'x');
    MyBikeImage::create(['my_bike_id' => $bike->id, 'path' => $path, 'sort_order' => 0]);

    // 論理削除 → ファイルは残る
    app(MyBikeService::class)->deleteBike($bike);
    expect(Storage::disk(config('garage.image_disk'))->exists($path))->toBeTrue();

    // 物理削除 → ファイルも消える
    MyBike::withTrashed()->find($bike->id)->forceDelete();
    expect(Storage::disk(config('garage.image_disk'))->exists($path))->toBeFalse()
        ->and(MyBikeImage::withTrashed()->where('my_bike_id', $bike->id)->count())->toBe(0)
        ->and(FuelLog::withTrashed()->where('my_bike_id', $bike->id)->count())->toBe(0);
});

// ====== 公開面にソフト削除が出ない ======

it('a soft-deleted public garage disappears from public index, profile, model owners, and cannot be liked', function () {
    $user = sdUser();
    $token = $user->ensurePublicToken();
    $bike = sdBike($user, public: true);
    $svc = app(MyBikeService::class);

    // 削除前: 公開面に出る
    expect($svc->publicGarageCardsForUser($user))->toHaveCount(1)
        ->and($svc->publicGarageCardsForModel($bike->bike_model_id)['total'])->toBe(1);

    $svc->deleteBike($bike);

    // 公開index
    $this->get(route('garage.public.index'))->assertOk();
    expect(MyBike::where('is_public', true)->count())->toBe(0);
    // プロフィール集約 / 車種オーナーカード
    expect($svc->publicGarageCardsForUser($user))->toHaveCount(0)
        ->and($svc->publicGarageCardsForModel($bike->bike_model_id)['total'])->toBe(0);
    // 公開ガレージページは404
    $this->get(route('garage.public.show', $bike->id))->assertNotFound();
    // いいねも404（ルートモデルバインドが trashed を除外）
    $this->actingAs(sdUser())->postJson(route('garage.like', $bike->id))->assertNotFound();
});

// ====== 既存削除UIが壊れない ======

it('destroy endpoint soft-deletes and redirects (existing UI intact)', function () {
    $user = sdUser();
    $bike = sdBikeWithData($user);

    $this->actingAs($user)->delete(route('mybikes.destroy', $bike->id))
        ->assertRedirect(route('mybikes.index'));

    expect(MyBike::find($bike->id))->toBeNull()
        ->and(MyBike::withTrashed()->find($bike->id))->not->toBeNull();
});

it('owner garage list excludes soft-deleted bikes', function () {
    $user = sdUser();
    $keep = sdBike($user);
    $del = sdBike($user);
    app(MyBikeService::class)->deleteBike($del);

    expect($user->myBikes()->pluck('id')->all())->toContain($keep->id)->not->toContain($del->id);
});
