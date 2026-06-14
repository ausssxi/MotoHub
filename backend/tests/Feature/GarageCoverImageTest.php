<?php

declare(strict_types=1);

use App\Models\BikeModel;
use App\Models\Manufacturer;
use App\Models\MyBike;
use App\Models\MyBikeImage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function coverBike(User $user, array $attrs = []): MyBike
{
    $mfr = Manufacturer::where('slug', 'honda')->first() ?? Manufacturer::forceCreate(['name' => 'Honda', 'slug' => 'honda']);
    // BikeModel::image_url は computed（local_image_path 由来）。fallback 検証用にカタログ画像を持たせる。
    $model = BikeModel::where('slug', 'pcx')->first()
        ?? BikeModel::create(['manufacturer_id' => $mfr->id, 'name' => 'PCX', 'slug' => 'pcx', 'local_image_path' => ['catalog/pcx.jpg']]);

    return MyBike::create(array_merge([
        'user_id' => $user->id, 'bike_model_id' => $model->id, 'name' => 'マイPCX', 'current_odometer' => 1000,
    ], $attrs));
}

function uploadCoverImage(User $user, MyBike $bike): MyBikeImage
{
    Storage::fake((string) config('garage.image_disk'));
    test()->actingAs($user)->post("/garage/{$bike->id}/images", ['image' => UploadedFile::fake()->image('p.jpg', 600, 600)]);

    return MyBikeImage::where('my_bike_id', $bike->id)->orderBy('sort_order')->orderBy('id')->firstOrFail();
}

it('private garage card shows the first gallery photo as the cover', function () {
    $user = User::factory()->create();
    $bike = coverBike($user);
    $image = uploadCoverImage($user, $bike);

    $this->actingAs($user)->get('/garage')
        ->assertOk()
        ->assertSee("/garage/{$bike->id}/images/{$image->id}", false) // ギャラリー1枚目が出る
        ->assertDontSee('catalog/pcx.jpg');                           // カタログにfallbackしない
});

it('private cover falls back to the catalog image when there is no gallery photo', function () {
    $user = User::factory()->create();
    $bike = coverBike($user);

    expect($bike->private_cover_image)
        ->toBe($bike->bikeModel->image_url)
        ->toContain('catalog/pcx.jpg');
});

it('private cover prefers an explicit image_url over the gallery (future v1.2 hook)', function () {
    $user = User::factory()->create();
    $bike = coverBike($user);
    uploadCoverImage($user, $bike);
    // image_url は現状 fillable ではない（明示カバーは v1.2）。チェーン先頭の検証のため forceFill。
    $bike->forceFill(['image_url' => 'https://cdn.test/explicit-cover.jpg'])->save();
    $bike->refresh()->load('images');

    expect($bike->private_cover_image)->toBe('https://cdn.test/explicit-cover.jpg');
});

it('the public-safe accessor NEVER returns a gallery photo (no leak)', function () {
    $user = User::factory()->create();
    $bike = coverBike($user);
    uploadCoverImage($user, $bike);
    $bike->refresh()->load('images');

    // display_image は public 面で使われる。ギャラリーURLを返してはならない。
    expect($bike->display_image)
        ->toBe($bike->bikeModel->image_url)
        ->not->toContain('/images/');
});

it('public_show does not expose the private gallery as a cover', function () {
    $user = User::factory()->create(['review_display_name' => 'rider_x']);
    $bike = coverBike($user, ['is_public' => true]);
    $image = uploadCoverImage($user, $bike);

    auth()->logout();
    $this->get("/garage/public/{$bike->id}")
        ->assertOk()
        ->assertDontSee("/garage/{$bike->id}/images/{$image->id}"); // ギャラリーURLが公開面に出ない
});

it('public_index does not expose the private gallery as a cover', function () {
    $user = User::factory()->create(['review_display_name' => 'rider_x']);
    $bike = coverBike($user, ['is_public' => true]);
    $image = uploadCoverImage($user, $bike);

    auth()->logout();
    $this->get('/garage/public')
        ->assertOk()
        ->assertDontSee("/garage/{$bike->id}/images/{$image->id}");
});
