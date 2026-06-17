<?php

declare(strict_types=1);

use App\Models\BikeModel;
use App\Models\MaintenanceLog;
use App\Models\Manufacturer;
use App\Models\MyBike;
use App\Models\MyBikeImage;
use App\Models\User;
use App\Services\MyBike\MyBikeService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function photoBike(User $user): MyBike
{
    $mfr = Manufacturer::where('slug', 'honda')->first() ?? Manufacturer::forceCreate(['name' => 'Honda', 'slug' => 'honda']);
    $model = BikeModel::where('slug', 'pcx')->first() ?? BikeModel::create(['manufacturer_id' => $mfr->id, 'name' => 'PCX', 'slug' => 'pcx']);

    return MyBike::create(['user_id' => $user->id, 'bike_model_id' => $model->id, 'name' => 'マイPCX', 'initial_odometer' => 0, 'current_odometer' => 1000]);
}

function diskName(): string
{
    return (string) config('garage.image_disk');
}

/** GPS入りのAPP1(Exif)を注入したJPEGの UploadedFile を作る（保存後にメタが消えることの検証用）。 */
function jpegWithGps(): array
{
    $gd = imagecreatetruecolor(90, 90);
    imagefilledrectangle($gd, 0, 0, 90, 90, imagecolorallocate($gd, 120, 80, 40));
    ob_start();
    imagejpeg($gd, null, 90);
    $base = (string) ob_get_clean();
    imagedestroy($gd);

    $payload = "Exif\x00\x00".str_repeat("\x00", 6).'GPSLatitudeMARKER 35.681 139.767';
    $app1 = "\xFF\xE1".pack('n', strlen($payload) + 2).$payload;
    $withExif = substr($base, 0, 2).$app1.substr($base, 2); // SOI 直後に APP1(Exif/GPS) を挿入

    $path = sys_get_temp_dir().'/gps_'.bin2hex(random_bytes(6)).'.jpg';
    file_put_contents($path, $withExif);

    return [new UploadedFile($path, 'photo.jpg', 'image/jpeg', null, true), $withExif];
}

it('attaches multiple photos to a maintenance record (rows + private files)', function () {
    $disk = diskName();
    Storage::fake($disk);
    $user = User::factory()->create();
    $bike = photoBike($user);

    $this->actingAs($user)->post("/garage/{$bike->id}/maintenance", [
        'maintained_at' => now()->format('Y-m-d'), 'title' => 'オイル交換', 'cost' => 3000,
        'images' => [UploadedFile::fake()->image('a.jpg', 400, 400), UploadedFile::fake()->image('b.jpg', 400, 400)],
    ])->assertRedirect();

    $rec = MaintenanceLog::where('my_bike_id', $bike->id)->firstOrFail();
    expect($rec->images()->count())->toBe(2);
    foreach ($rec->images as $img) {
        expect($img->maintenance_log_id)->toBe($rec->id)
            ->and($img->my_bike_id)->toBe($bike->id);
        Storage::disk($disk)->assertExists($img->path);
    }
});

it('removes EXIF/GPS metadata on save (privacy core)', function () {
    $disk = diskName();
    Storage::fake($disk);
    $user = User::factory()->create();
    $bike = photoBike($user);
    [$file, $rawBytes] = jpegWithGps();
    expect($rawBytes)->toContain('GPSLatitudeMARKER'); // 入力にはGPSが入っている

    app(MyBikeService::class); // resolve container
    $rec = MaintenanceLog::create(['my_bike_id' => $bike->id, 'maintained_at' => now(), 'title' => 'タイヤ交換']);
    app(\App\Services\MyBike\MyBikeImageService::class)->addToRecord($rec, $file);

    $img = $rec->images()->firstOrFail();
    $saved = Storage::disk($disk)->get($img->path);
    expect($saved)->not->toContain('GPSLatitudeMARKER') // 保存後はGPSが消えている
        ->and($saved)->not->toContain('Exif');          // EXIF APP1 も無い（再エンコード）
});

it('record delete removes attached image rows and physical files', function () {
    $disk = diskName();
    Storage::fake($disk);
    $user = User::factory()->create();
    $bike = photoBike($user);
    $this->actingAs($user)->post("/garage/{$bike->id}/maintenance", [
        'maintained_at' => now()->format('Y-m-d'), 'title' => 'オイル交換',
        'images' => [UploadedFile::fake()->image('a.jpg', 400, 400)],
    ]);
    $rec = MaintenanceLog::where('my_bike_id', $bike->id)->firstOrFail();
    $img = $rec->images()->firstOrFail();
    Storage::disk($disk)->assertExists($img->path);

    $this->actingAs($user)->delete("/garage/{$bike->id}/records/{$rec->id}")->assertRedirect();

    expect(MyBikeImage::find($img->id))->toBeNull();
    Storage::disk($disk)->assertMissing($img->path);
});

it('rejects HEIC and oversized/too-many images with 422', function () {
    Storage::fake(diskName());
    $user = User::factory()->create();
    $bike = photoBike($user);

    // HEIC（GD非対応）→ mimes で 422
    $this->actingAs($user)->post("/garage/{$bike->id}/maintenance", [
        'maintained_at' => now()->format('Y-m-d'), 'title' => 'x',
        'images' => [UploadedFile::fake()->create('iphone.heic', 100, 'image/heic')],
    ])->assertSessionHasErrors('images.0');

    // 枚数超過 → 422
    config(['garage.max_record_images' => 2]);
    $this->actingAs($user)->post("/garage/{$bike->id}/maintenance", [
        'maintained_at' => now()->format('Y-m-d'), 'title' => 'x',
        'images' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg'), UploadedFile::fake()->image('c.jpg')],
    ])->assertSessionHasErrors('images');
});

it('serves a record photo to the owner only (non-owner 404, guest 401)', function () {
    Storage::fake(diskName());
    $user = User::factory()->create();
    $bike = photoBike($user);
    $this->actingAs($user)->post("/garage/{$bike->id}/maintenance", [
        'maintained_at' => now()->format('Y-m-d'), 'title' => 'オイル交換',
        'images' => [UploadedFile::fake()->image('a.jpg', 400, 400)],
    ]);
    $rec = MaintenanceLog::where('my_bike_id', $bike->id)->firstOrFail();
    $img = $rec->images()->firstOrFail();
    $url = "/garage/{$bike->id}/records/{$rec->id}/images/{$img->id}";

    $this->actingAs($user)->get($url)->assertOk();
    $this->actingAs(User::factory()->create())->get($url)->assertNotFound();
    auth()->logout();
    $this->get($url)->assertRedirect(route('login')); // 未認証は弾く
});

it('record photos never leak into the bike gallery (cover/public source)', function () {
    Storage::fake(diskName());
    $user = User::factory()->create();
    $bike = photoBike($user);
    $this->actingAs($user)->post("/garage/{$bike->id}/maintenance", [
        'maintained_at' => now()->format('Y-m-d'), 'title' => 'オイル交換',
        'images' => [UploadedFile::fake()->image('a.jpg', 400, 400)],
    ]);

    // ギャラリー relation(images) は maintenance_log_id NULL のみ＝記録写真は含まれない
    expect($bike->images()->count())->toBe(0);
    expect(MyBikeImage::where('my_bike_id', $bike->id)->count())->toBe(1); // 物理的には存在
});

it('post-hoc add and per-photo delete are owner-only', function () {
    Storage::fake(diskName());
    $user = User::factory()->create();
    $bike = photoBike($user);
    $rec = MaintenanceLog::create(['my_bike_id' => $bike->id, 'maintained_at' => now(), 'title' => 'オイル交換']);

    // 非所有者は後付け不可
    $this->actingAs(User::factory()->create())->post("/garage/{$bike->id}/records/{$rec->id}/images", [
        'images' => [UploadedFile::fake()->image('a.jpg', 400, 400)],
    ])->assertNotFound();

    // 所有者は後付けOK
    $this->actingAs($user)->post("/garage/{$bike->id}/records/{$rec->id}/images", [
        'images' => [UploadedFile::fake()->image('a.jpg', 400, 400)],
    ])->assertRedirect();
    $img = $rec->images()->firstOrFail();

    // 非所有者は削除不可
    $this->actingAs(User::factory()->create())
        ->delete("/garage/{$bike->id}/records/{$rec->id}/images/{$img->id}")->assertNotFound();
    expect(MyBikeImage::find($img->id))->not->toBeNull();

    // 所有者は削除OK
    $this->actingAs($user)->delete("/garage/{$bike->id}/records/{$rec->id}/images/{$img->id}")->assertRedirect();
    expect(MyBikeImage::find($img->id))->toBeNull();
});
