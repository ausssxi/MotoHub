<?php

declare(strict_types=1);

use App\Models\BikeModel;
use App\Models\Manufacturer;
use App\Models\MyBike;
use App\Models\MyBikeImage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function imageBike(User $user): MyBike
{
    $mfr = Manufacturer::where('slug', 'honda')->first() ?? Manufacturer::forceCreate(['name' => 'Honda', 'slug' => 'honda']);
    $model = BikeModel::where('slug', 'pcx')->first() ?? BikeModel::create(['manufacturer_id' => $mfr->id, 'name' => 'PCX', 'slug' => 'pcx']);

    return MyBike::create(['user_id' => $user->id, 'bike_model_id' => $model->id, 'name' => 'マイPCX', 'current_odometer' => 1000]);
}

function fakeGarageDisk(): string
{
    $disk = (string) config('garage.image_disk');
    Storage::fake($disk);

    return $disk;
}

it('owner upload adds a row and stores the optimized file on the private disk', function () {
    $disk = fakeGarageDisk();
    $user = User::factory()->create();
    $bike = imageBike($user);

    $this->actingAs($user)
        ->post("/garage/{$bike->id}/images", [
            'image' => UploadedFile::fake()->image('photo.jpg', 1200, 900),
            'caption' => '納車日',
        ])
        ->assertRedirect();

    $image = MyBikeImage::where('my_bike_id', $bike->id)->first();
    expect($image)->not->toBeNull()
        ->and($image->caption)->toBe('納車日')
        ->and($image->path)->toStartWith("garage/{$bike->id}/");

    Storage::disk($disk)->assertExists($image->path);
});

it('a non-owner cannot upload to someone elses bike (404)', function () {
    fakeGarageDisk();
    $owner = User::factory()->create();
    $bike = imageBike($owner);
    $intruder = User::factory()->create();

    $this->actingAs($intruder)
        ->post("/garage/{$bike->id}/images", [
            'image' => UploadedFile::fake()->image('x.jpg', 400, 400),
        ])
        ->assertNotFound();

    expect(MyBikeImage::where('my_bike_id', $bike->id)->count())->toBe(0);
});

it('owner can stream the image but a non-owner gets 404 (private delivery)', function () {
    fakeGarageDisk();
    $user = User::factory()->create();
    $bike = imageBike($user);
    $this->actingAs($user)->post("/garage/{$bike->id}/images", ['image' => UploadedFile::fake()->image('p.jpg', 600, 600)]);
    $image = MyBikeImage::where('my_bike_id', $bike->id)->firstOrFail();

    $this->actingAs($user)->get("/garage/{$bike->id}/images/{$image->id}")->assertOk();

    $this->actingAs(User::factory()->create())
        ->get("/garage/{$bike->id}/images/{$image->id}")
        ->assertNotFound();
});

/*
 * cross-user leak の回帰テスト（必須）。配信ルートは必ず所有者スコープで解決すること：
 * auth user の bike({id}) を所有スコープ取得 → その bike の image({image}) → 無ければ404。
 * これにより「非所有者」「他人の画像ID」「別bikeのimage id混在」「未ログイン」がすべて弾かれる。
 */
it('image delivery is owner-scoped against every cross-user variant', function () {
    fakeGarageDisk();

    // 被害者の bike + 画像
    $victim = User::factory()->create();
    $victimBike = imageBike($victim);
    $this->actingAs($victim)->post("/garage/{$victimBike->id}/images", ['image' => UploadedFile::fake()->image('v.jpg', 500, 500)]);
    $victimImage = MyBikeImage::where('my_bike_id', $victimBike->id)->firstOrFail();

    // 攻撃者（別のログイン済みユーザー）が自分の bike を持つ
    $attacker = User::factory()->create();
    $attackerBike = MyBike::create(['user_id' => $attacker->id, 'bike_model_id' => $victimBike->bike_model_id, 'name' => '攻撃者バイク', 'current_odometer' => 1]);

    // (1) 被害者本人 → 200
    $this->actingAs($victim)->get("/garage/{$victimBike->id}/images/{$victimImage->id}")->assertOk();

    // (2) 別ログインユーザーが被害者の URL を直叩き → 404（存在も漏らさない）
    $this->actingAs($attacker)->get("/garage/{$victimBike->id}/images/{$victimImage->id}")->assertNotFound();

    // (3) 攻撃者が「自分の bike id」＋「被害者の image id」を混ぜる → 404（cross-bike 混在）
    $this->actingAs($attacker)->get("/garage/{$attackerBike->id}/images/{$victimImage->id}")->assertNotFound();

    // (4) 未ログイン → auth で弾かれログインへ（画像は返さない）
    auth()->logout();
    $this->get("/garage/{$victimBike->id}/images/{$victimImage->id}")->assertRedirect(route('login'));
});

it('owner can update a caption', function () {
    fakeGarageDisk();
    $user = User::factory()->create();
    $bike = imageBike($user);
    $this->actingAs($user)->post("/garage/{$bike->id}/images", ['image' => UploadedFile::fake()->image('p.jpg', 500, 500)]);
    $image = MyBikeImage::where('my_bike_id', $bike->id)->firstOrFail();

    $this->actingAs($user)
        ->patch("/garage/{$bike->id}/images/{$image->id}", ['caption' => '箱根ツーリング'])
        ->assertRedirect();

    expect($image->fresh()->caption)->toBe('箱根ツーリング');
});

it('owner delete removes the row and the file', function () {
    $disk = fakeGarageDisk();
    $user = User::factory()->create();
    $bike = imageBike($user);
    $this->actingAs($user)->post("/garage/{$bike->id}/images", ['image' => UploadedFile::fake()->image('p.jpg', 500, 500)]);
    $image = MyBikeImage::where('my_bike_id', $bike->id)->firstOrFail();
    Storage::disk($disk)->assertExists($image->path);

    $this->actingAs($user)
        ->delete("/garage/{$bike->id}/images/{$image->id}")
        ->assertRedirect();

    expect(MyBikeImage::find($image->id))->toBeNull();
    Storage::disk($disk)->assertMissing($image->path);
});

it('enforces the per-bike image limit', function () {
    fakeGarageDisk();
    config(['garage.max_images' => 2]);
    $user = User::factory()->create();
    $bike = imageBike($user);

    // 上限まで（行を直接作成・ファイル処理を省く）
    MyBikeImage::create(['my_bike_id' => $bike->id, 'path' => 'garage/x/a.jpg', 'sort_order' => 0]);
    MyBikeImage::create(['my_bike_id' => $bike->id, 'path' => 'garage/x/b.jpg', 'sort_order' => 1]);

    $this->actingAs($user)
        ->post("/garage/{$bike->id}/images", ['image' => UploadedFile::fake()->image('c.jpg', 400, 400)])
        ->assertSessionHasErrors('image');

    expect(MyBikeImage::where('my_bike_id', $bike->id)->count())->toBe(2);
});

it('deleting the bike cascades image rows and deletes their files', function () {
    $disk = fakeGarageDisk();
    $user = User::factory()->create();
    $bike = imageBike($user);
    $this->actingAs($user)->post("/garage/{$bike->id}/images", ['image' => UploadedFile::fake()->image('p.jpg', 500, 500)]);
    $image = MyBikeImage::where('my_bike_id', $bike->id)->firstOrFail();
    Storage::disk($disk)->assertExists($image->path);

    $this->actingAs($user)->delete("/garage/{$bike->id}")->assertRedirect();

    expect(MyBikeImage::where('my_bike_id', $bike->id)->count())->toBe(0);
    Storage::disk($disk)->assertMissing($image->path);
});

it('the public garage page never exposes the private gallery', function () {
    fakeGarageDisk();
    $user = User::factory()->create(['review_display_name' => 'rider_x']);
    $bike = imageBike($user);
    $bike->update(['is_public' => true]);
    $this->actingAs($user)->post("/garage/{$bike->id}/images", [
        'image' => UploadedFile::fake()->image('secret.jpg', 500, 500),
        'caption' => '自宅ガレージ',
    ]);
    $image = MyBikeImage::where('my_bike_id', $bike->id)->firstOrFail();

    auth()->logout();
    $this->get("/garage/public/{$bike->id}")
        ->assertOk()
        ->assertDontSee("/images/{$image->id}")  // 配信ルートが漏れない
        ->assertDontSee('自宅ガレージ');           // キャプションも出ない
});
