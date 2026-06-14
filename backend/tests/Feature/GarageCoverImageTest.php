<?php

declare(strict_types=1);

use App\Models\BikeModel;
use App\Models\Manufacturer;
use App\Models\MyBike;
use App\Models\MyBikeImage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function coverBike(User $user, array $attrs = [], bool $withCatalog = true): MyBike
{
    $mfr = Manufacturer::where('slug', 'honda')->first() ?? Manufacturer::forceCreate(['name' => 'Honda', 'slug' => 'honda']);
    // BikeModel::image_url は computed（local_image_path 由来）。fallback 検証用にカタログ画像を持たせる。
    $slug = $withCatalog ? 'pcx' : 'pcx-nocat';
    $model = BikeModel::where('slug', $slug)->first()
        ?? BikeModel::create(array_filter([
            'manufacturer_id' => $mfr->id,
            'name' => 'PCX',
            'slug' => $slug,
            'local_image_path' => $withCatalog ? ['catalog/pcx.jpg'] : null,
        ]));

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

it('cover resolves to the first gallery photo via the delivery route', function () {
    $user = User::factory()->create();
    $bike = coverBike($user);
    $image = uploadCoverImage($user, $bike);
    $bike->refresh()->load('images');

    expect($bike->display_image)->toBe(route('mybikes.images.show', [$bike->id, $image->id]));
});

it('cover falls back to the catalog image when there is no gallery photo', function () {
    $user = User::factory()->create();
    $bike = coverBike($user);

    expect($bike->display_image)
        ->toBe($bike->bikeModel->image_url)
        ->toContain('catalog/pcx.jpg');
});

it('cover prefers an explicit image_url over the gallery (future v1.2 hook)', function () {
    $user = User::factory()->create();
    $bike = coverBike($user);
    uploadCoverImage($user, $bike);
    // image_url は現状 fillable ではない（明示カバーは v1.2）。チェーン先頭の検証のため forceFill。
    $bike->forceFill(['image_url' => 'https://cdn.test/explicit-cover.jpg'])->save();
    $bike->refresh()->load('images');

    expect($bike->display_image)->toBe('https://cdn.test/explicit-cover.jpg');
});

it('cover is null (placeholder) when there is no image at all', function () {
    $user = User::factory()->create();
    $bike = coverBike($user, withCatalog: false); // カタログ画像なし・ギャラリーなし・image_urlなし

    expect($bike->display_image)->toBeNull();
});

it('public_show displays the gallery cover for a public garage', function () {
    $user = User::factory()->create(['review_display_name' => 'rider_x']);
    $bike = coverBike($user, ['is_public' => true]);
    $image = uploadCoverImage($user, $bike);

    auth()->logout();
    $this->get("/garage/public/{$bike->id}")
        ->assertOk()
        ->assertSee("/garage/{$bike->id}/images/{$image->id}", false); // カバーが公開面に出る
});

it('public_index displays the gallery cover for a public garage', function () {
    $user = User::factory()->create(['review_display_name' => 'rider_x']);
    $bike = coverBike($user, ['is_public' => true]);
    $image = uploadCoverImage($user, $bike);

    auth()->logout();
    $this->get('/garage/public')
        ->assertOk()
        ->assertSee("/garage/{$bike->id}/images/{$image->id}", false);
});
