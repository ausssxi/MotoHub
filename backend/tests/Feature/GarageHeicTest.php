<?php

declare(strict_types=1);

use App\Models\BikeModel;
use App\Models\MaintenanceLog;
use App\Models\Manufacturer;
use App\Models\MyBike;
use App\Models\User;
use App\Services\MyBike\MyBikeImageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

// HEIC は Imagick(+libheif) でのみデコード可能。未導入環境（imagick無し）ではスキップ。
beforeEach(function () {
    if (! extension_loaded('imagick') || ! in_array('HEIC', \Imagick::queryFormats('HEIC'), true)) {
        $this->markTestSkipped('imagick(+libheif/HEIC) 未導入のためスキップ');
    }
});

function heicBike(?User $user = null): MyBike
{
    $user ??= User::factory()->create();
    $mfr = Manufacturer::where('slug', 'honda')->first() ?? Manufacturer::forceCreate(['name' => 'Honda', 'slug' => 'honda']);
    $model = BikeModel::where('slug', 'pcx')->first() ?? BikeModel::create(['manufacturer_id' => $mfr->id, 'name' => 'PCX', 'slug' => 'pcx']);

    return MyBike::create(['user_id' => $user->id, 'bike_model_id' => $model->id, 'name' => 'マイPCX', 'initial_odometer' => 0, 'current_odometer' => 1000]);
}

/** Imagick で実 HEIC を生成して UploadedFile を返す（iPhone アップロード相当）。 */
function makeHeic(): array
{
    $im = new \Imagick;
    $im->newImage(120, 120, new \ImagickPixel('rgb(120,80,40)'));
    $im->setImageFormat('heic');
    $bytes = $im->getImageBlob();
    $im->clear();
    $im->destroy();

    $path = sys_get_temp_dir().'/heic_'.bin2hex(random_bytes(6)).'.heic';
    file_put_contents($path, $bytes);

    return [new UploadedFile($path, 'iphone.heic', 'image/heic', null, true)];
}

it('accepts a HEIC upload and stores it as a JPEG', function () {
    $disk = (string) config('garage.image_disk');
    Storage::fake($disk);
    $user = User::factory()->create();
    $bike = heicBike($user);
    [$heic] = makeHeic();

    $this->actingAs($user)->post("/garage/{$bike->id}/maintenance", [
        'maintained_at' => now()->format('Y-m-d'), 'title' => 'オイル交換',
        'images' => [$heic],
    ])->assertRedirect()->assertSessionHasNoErrors();

    $rec = MaintenanceLog::where('my_bike_id', $bike->id)->firstOrFail();
    $img = $rec->images()->firstOrFail();
    $saved = Storage::disk($disk)->get($img->path);

    expect(substr($saved, 0, 2))->toBe("\xFF\xD8")  // JPEG SOI（HEIC→JPEG 変換済み）
        ->and($img->path)->toEndWith('.jpg');
});

it('produces a metadata-free JPEG from a HEIC on save (privacy core)', function () {
    // HEIC は HEIF 内に EXIF(GPS含む) を持ちうる。保存パスは HEIC→Imagick→GD再エンコード(toJpeg)で
    // メタを全除去する。ここでは「保存後の出力に EXIF/APP1 が一切残らない」不変条件を固定する。
    // （実 iPhone GPS 写真での最終確認は本番の実機QA＝runbook step7）。JPEG への GPS 注入除去は
    //  GarageRecordPhotoTest「removes EXIF/GPS metadata on save」で担保済（同じ toJpeg に合流）。
    $disk = (string) config('garage.image_disk');
    Storage::fake($disk);
    $user = User::factory()->create();
    $bike = heicBike($user);
    [$heic] = makeHeic();

    $rec = MaintenanceLog::create(['my_bike_id' => $bike->id, 'maintained_at' => now(), 'title' => 'タイヤ交換']);
    app(MyBikeImageService::class)->addToRecord($rec, $heic);

    $img = $rec->images()->firstOrFail();
    $saved = Storage::disk($disk)->get($img->path);

    expect(substr($saved, 0, 2))->toBe("\xFF\xD8")    // JPEG SOI（HEIC→JPEG 変換済み）
        ->and($saved)->not->toContain('Exif')          // EXIF 文字列なし
        ->and(strpos($saved, "\xFF\xE1"))->toBeFalse(); // APP1(EXIF/GPS)セグメントが存在しない
});
