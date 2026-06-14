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
