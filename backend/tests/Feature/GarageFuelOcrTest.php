<?php

declare(strict_types=1);

use App\Models\BikeModel;
use App\Models\Manufacturer;
use App\Models\MyBike;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

function ocrBike(User $user): MyBike
{
    $mfr = Manufacturer::where('slug', 'honda')->first() ?? Manufacturer::forceCreate(['name' => 'Honda', 'slug' => 'honda']);
    $model = BikeModel::where('slug', 'pcx')->first() ?? BikeModel::create(['manufacturer_id' => $mfr->id, 'name' => 'PCX', 'slug' => 'pcx']);

    return MyBike::create(['user_id' => $user->id, 'bike_model_id' => $model->id, 'name' => 'マイPCX', 'current_odometer' => 1000]);
}

function fakeAnthropic(array $payload): void
{
    Http::fake([
        'api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => json_encode($payload)]]], 200),
    ]);
}

it('owner can extract receipt fields (quantity/cost/date), odometer stays null', function () {
    fakeAnthropic(['odometer' => null, 'quantity' => 5.5, 'cost' => 1000, 'date' => '2026-06-10', 'confidence' => '高']);
    $user = User::factory()->create();
    $bike = ocrBike($user);

    $res = $this->actingAs($user)->postJson("/garage/{$bike->id}/ocr/fuel", [
        'image' => UploadedFile::fake()->image('receipt.jpg', 800, 1000),
        'type' => 'receipt',
    ]);

    $res->assertOk()
        ->assertJsonPath('confidence', '高')
        ->assertJsonPath('values.quantity', 5.5)
        ->assertJsonPath('values.cost', 1000)
        ->assertJsonPath('values.filled_at', '2026-06-10')
        ->assertJsonMissingPath('values.odometer');
});

it('receipt extracts a short store_name into values (memo source)', function () {
    fakeAnthropic(['quantity' => 10, 'cost' => 1500, 'date' => '2026-06-10', 'store_name' => 'apollo セルフ横山台', 'confidence' => '高']);
    $user = User::factory()->create();
    $bike = ocrBike($user);

    $this->actingAs($user)->postJson("/garage/{$bike->id}/ocr/fuel", [
        'image' => UploadedFile::fake()->image('receipt.jpg', 800, 1000),
        'type' => 'receipt',
    ])
        ->assertOk()
        ->assertJsonPath('values.store_name', 'apollo セルフ横山台');
});

it('omits store_name when null (no memo fill), contract stays intact', function () {
    fakeAnthropic(['quantity' => 8.5, 'cost' => 1200, 'date' => null, 'store_name' => null, 'confidence' => '中']);
    $user = User::factory()->create();
    $bike = ocrBike($user);

    $this->actingAs($user)->postJson("/garage/{$bike->id}/ocr/fuel", [
        'image' => UploadedFile::fake()->image('receipt.jpg', 800, 1000),
        'type' => 'receipt',
    ])
        ->assertOk()
        ->assertJsonPath('values.quantity', 8.5)
        ->assertJsonMissingPath('values.store_name');
});

it('PII safety-net drops store_name that looks like card/phone/registration or is too long', function () {
    $user = User::factory()->create();
    $bike = ocrBike($user);

    // カード番号風（長い数字列）→ 破棄
    fakeAnthropic(['quantity' => 5, 'store_name' => '1234 5678 9012 3456', 'confidence' => '高']);
    $this->actingAs($user)->postJson("/garage/{$bike->id}/ocr/fuel", [
        'image' => UploadedFile::fake()->image('r.jpg', 400, 400), 'type' => 'receipt',
    ])->assertOk()->assertJsonMissingPath('values.store_name');

    // インボイス登録番号 Txxxx → 破棄
    fakeAnthropic(['quantity' => 5, 'store_name' => 'T1234567890123', 'confidence' => '高']);
    $this->actingAs($user)->postJson("/garage/{$bike->id}/ocr/fuel", [
        'image' => UploadedFile::fake()->image('r.jpg', 400, 400), 'type' => 'receipt',
    ])->assertOk()->assertJsonMissingPath('values.store_name');

    // 電話番号風 → 破棄
    fakeAnthropic(['quantity' => 5, 'store_name' => 'TEL 03-1234-5678', 'confidence' => '高']);
    $this->actingAs($user)->postJson("/garage/{$bike->id}/ocr/fuel", [
        'image' => UploadedFile::fake()->image('r.jpg', 400, 400), 'type' => 'receipt',
    ])->assertOk()->assertJsonMissingPath('values.store_name');
});

it('keeps a normal store_name that contains a short number (e.g. 246号店)', function () {
    fakeAnthropic(['quantity' => 5, 'store_name' => 'ENEOS 246号店', 'confidence' => '高']);
    $user = User::factory()->create();
    $bike = ocrBike($user);

    $this->actingAs($user)->postJson("/garage/{$bike->id}/ocr/fuel", [
        'image' => UploadedFile::fake()->image('r.jpg', 400, 400), 'type' => 'receipt',
    ])->assertOk()->assertJsonPath('values.store_name', 'ENEOS 246号店');
});

it('owner can extract odometer from a meter photo', function () {
    fakeAnthropic(['odometer' => 12345, 'quantity' => null, 'cost' => null, 'date' => null, 'confidence' => '中']);
    $user = User::factory()->create();
    $bike = ocrBike($user);

    $this->actingAs($user)->postJson("/garage/{$bike->id}/ocr/fuel", [
        'image' => UploadedFile::fake()->image('meter.jpg', 1000, 600),
        'type' => 'odometer',
    ])
        ->assertOk()
        ->assertJsonPath('values.odometer', 12345)
        ->assertJsonMissingPath('values.quantity');
});

it('a future date is dropped (likely misread) and left for manual entry', function () {
    fakeAnthropic(['odometer' => null, 'quantity' => 4.2, 'cost' => null, 'date' => '2099-01-01', 'confidence' => '中']);
    $user = User::factory()->create();
    $bike = ocrBike($user);

    $this->actingAs($user)->postJson("/garage/{$bike->id}/ocr/fuel", [
        'image' => UploadedFile::fake()->image('r.jpg', 800, 600),
        'type' => 'receipt',
    ])
        ->assertOk()
        ->assertJsonPath('values.quantity', 4.2)
        ->assertJsonMissingPath('values.filled_at');
});

it('a non-owner cannot use OCR on someone elses bike (404)', function () {
    fakeAnthropic(['quantity' => 5, 'confidence' => '高']);
    $bike = ocrBike(User::factory()->create());

    $this->actingAs(User::factory()->create())
        ->postJson("/garage/{$bike->id}/ocr/fuel", [
            'image' => UploadedFile::fake()->image('r.jpg', 400, 400),
            'type' => 'receipt',
        ])
        ->assertNotFound();
});

it('rejects an invalid capture type', function () {
    $user = User::factory()->create();
    $bike = ocrBike($user);

    $this->actingAs($user)->postJson("/garage/{$bike->id}/ocr/fuel", [
        'image' => UploadedFile::fake()->image('r.jpg', 400, 400),
        'type' => 'selfie',
    ])->assertStatus(422);
});

it('requires an image', function () {
    $user = User::factory()->create();
    $bike = ocrBike($user);

    $this->actingAs($user)->postJson("/garage/{$bike->id}/ocr/fuel", ['type' => 'receipt'])
        ->assertStatus(422);
});

it('returns a graceful 502 when the model output is unparseable', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => 'すみません、読み取れませんでした']]], 200),
    ]);
    $user = User::factory()->create();
    $bike = ocrBike($user);

    $this->actingAs($user)->postJson("/garage/{$bike->id}/ocr/fuel", [
        'image' => UploadedFile::fake()->image('r.jpg', 400, 400),
        'type' => 'receipt',
    ])->assertStatus(502)->assertJsonStructure(['error']);
});

it('returns 502 when the vision API errors', function () {
    Http::fake(['api.anthropic.com/*' => Http::response('upstream error', 500)]);
    $user = User::factory()->create();
    $bike = ocrBike($user);

    $this->actingAs($user)->postJson("/garage/{$bike->id}/ocr/fuel", [
        'image' => UploadedFile::fake()->image('r.jpg', 400, 400),
        'type' => 'odometer',
    ])->assertStatus(502);
});

it('is disabled when ocr_enabled is off', function () {
    config(['garage.ocr_enabled' => false]);
    $user = User::factory()->create();
    $bike = ocrBike($user);

    $this->actingAs($user)->postJson("/garage/{$bike->id}/ocr/fuel", [
        'image' => UploadedFile::fake()->image('r.jpg', 400, 400),
        'type' => 'receipt',
    ])->assertNotFound();
});

it('requires authentication', function () {
    $bike = ocrBike(User::factory()->create());

    $this->postJson("/garage/{$bike->id}/ocr/fuel", [
        'image' => UploadedFile::fake()->image('r.jpg', 400, 400),
        'type' => 'receipt',
    ])->assertUnauthorized();
});
