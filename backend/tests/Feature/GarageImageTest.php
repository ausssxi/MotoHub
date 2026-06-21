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
 * cross-user leak の回帰テスト（必須）。非公開ガレージの画像は owner 以外すべて404であること。
 * 配信ルートは auth 不要だが、非所有者は「is_public ガレージのカバー」以外404。
 * ここでは private な bike を使うので「非所有者」「別bikeのimage id混在」「未ログイン」が全て404。
 */
it('private garage images are owner-only against every cross-user variant', function () {
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

    // (4) 未ログイン → 非公開ガレージの画像は404（配信ルートはpublicだが owner 以外不可）
    auth()->logout();
    $this->get("/garage/{$victimBike->id}/images/{$victimImage->id}")->assertNotFound();
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

it('owner delete soft-deletes the row and KEEPS the file (recoverable; file purged only on forceDelete)', function () {
    $disk = fakeGarageDisk();
    $user = User::factory()->create();
    $bike = imageBike($user);
    $this->actingAs($user)->post("/garage/{$bike->id}/images", ['image' => UploadedFile::fake()->image('p.jpg', 500, 500)]);
    $image = MyBikeImage::where('my_bike_id', $bike->id)->firstOrFail();
    Storage::disk($disk)->assertExists($image->path);

    $this->actingAs($user)
        ->delete("/garage/{$bike->id}/images/{$image->id}")
        ->assertRedirect();

    // 論理削除＝default scope から消えるが行とファイルは残る（復元可能）
    expect(MyBikeImage::find($image->id))->toBeNull()
        ->and(MyBikeImage::withTrashed()->find($image->id))->not->toBeNull();
    Storage::disk($disk)->assertExists($image->path);

    // forceDelete でのみ実ファイルも削除
    MyBikeImage::withTrashed()->find($image->id)->forceDelete();
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

it('soft-deleting the bike cascades image rows (soft) and KEEPS files for restore', function () {
    $disk = fakeGarageDisk();
    $user = User::factory()->create();
    $bike = imageBike($user);
    $this->actingAs($user)->post("/garage/{$bike->id}/images", ['image' => UploadedFile::fake()->image('p.jpg', 500, 500)]);
    $image = MyBikeImage::where('my_bike_id', $bike->id)->firstOrFail();
    Storage::disk($disk)->assertExists($image->path);

    $this->actingAs($user)->delete("/garage/{$bike->id}")->assertRedirect();

    // 子の画像行はソフト削除（default scope から消える）・ファイルは復元のため保持
    expect(MyBikeImage::where('my_bike_id', $bike->id)->count())->toBe(0)
        ->and(MyBikeImage::withTrashed()->where('my_bike_id', $bike->id)->count())->toBe(1);
    Storage::disk($disk)->assertExists($image->path);
});

/*
 * 公開ガレージの配信許可（確定仕様）。
 *  - is_public ガレージのカバー(=1枚目) → 未ログイン/別ユーザーでも 200
 *  - is_public でも「非カバー写真」は owner 以外 404（公開はカバー1枚だけ）
 *  - キャプション等の private 情報は公開面に出ない
 */
it('public garage: cover is served to everyone but non-cover photos stay owner-only', function () {
    fakeGarageDisk();
    $owner = User::factory()->create(['review_display_name' => 'rider_x']);
    $bike = imageBike($owner);
    $bike->update(['is_public' => true]);

    // 1枚目（カバー）＋2枚目（非カバー）
    $this->actingAs($owner)->post("/garage/{$bike->id}/images", ['image' => UploadedFile::fake()->image('cover.jpg', 500, 500)]);
    $this->actingAs($owner)->post("/garage/{$bike->id}/images", [
        'image' => UploadedFile::fake()->image('second.jpg', 500, 500),
        'caption' => '自宅ガレージ',
    ]);
    $images = MyBikeImage::where('my_bike_id', $bike->id)->orderBy('sort_order')->orderBy('id')->get();
    $cover = $images[0];
    $second = $images[1];

    $other = User::factory()->create();

    // カバー: 未ログイン・別ユーザーでも 200
    auth()->logout();
    $this->get("/garage/{$bike->id}/images/{$cover->id}")->assertOk();
    $this->actingAs($other)->get("/garage/{$bike->id}/images/{$cover->id}")->assertOk();

    // 非カバー(2枚目): owner のみ200、別ユーザー/未ログインは 404
    $this->actingAs($owner)->get("/garage/{$bike->id}/images/{$second->id}")->assertOk();
    $this->actingAs($other)->get("/garage/{$bike->id}/images/{$second->id}")->assertNotFound();
    auth()->logout();
    $this->get("/garage/{$bike->id}/images/{$second->id}")->assertNotFound();

    // 公開ページにはカバーが出るが、非カバーのキャプション等 private 情報は出ない
    $this->get("/garage/public/{$bike->id}")
        ->assertOk()
        ->assertSee("/garage/{$bike->id}/images/{$cover->id}", false)
        ->assertDontSee('自宅ガレージ');
});
