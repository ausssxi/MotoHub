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
