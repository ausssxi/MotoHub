<?php

declare(strict_types=1);

use App\Models\BikeModel;
use App\Models\Manufacturer;
use App\Models\MyBike;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

function aiBike(User $user, float $current = 20000): MyBike
{
    $mfr = Manufacturer::where('slug', 'honda')->first() ?? Manufacturer::forceCreate(['name' => 'Honda', 'slug' => 'honda']);
    $model = BikeModel::where('slug', 'pcx')->first() ?? BikeModel::create(['manufacturer_id' => $mfr->id, 'name' => 'PCX', 'slug' => 'pcx']);

    return MyBike::create(['user_id' => $user->id, 'bike_model_id' => $model->id, 'name' => 'マイPCX', 'initial_odometer' => 0, 'current_odometer' => $current]);
}

function fakeAi(array $payload): void
{
    Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => json_encode($payload)]]], 200)]);
}

it('maintenance OCR maps date/cost/vendor/odometer/title to form fields', function () {
    fakeAi(['date' => '2026-06-10', 'cost' => 3000, 'vendor' => 'ナップス 横浜', 'odometer' => 19500, 'title' => 'オイル交換', 'confidence' => '高']);
    $user = User::factory()->create();
    $bike = aiBike($user);

    $this->actingAs($user)->postJson("/garage/{$bike->id}/ocr/record", [
        'image' => UploadedFile::fake()->image('denpyo.jpg', 800, 1000), 'type' => 'maintenance',
    ])
        ->assertOk()
        ->assertJsonPath('values.maintained_at', '2026-06-10')
        ->assertJsonPath('values.cost', 3000)
        ->assertJsonPath('values.vendor', 'ナップス 横浜')
        ->assertJsonPath('values.odometer', 19500)
        ->assertJsonPath('values.title', 'オイル交換')
        ->assertJsonMissingPath('values.part_name');
});

it('custom OCR maps part_name/brand and omits maintenance title', function () {
    fakeAi(['date' => '2026-06-01', 'cost' => 80000, 'vendor' => '2りんかん', 'part_name' => 'ヨシムラ マフラー', 'brand' => 'YOSHIMURA', 'odometer' => null, 'confidence' => '高']);
    $user = User::factory()->create();
    $bike = aiBike($user);

    $this->actingAs($user)->postJson("/garage/{$bike->id}/ocr/record", [
        'image' => UploadedFile::fake()->image('r.jpg', 800, 1000), 'type' => 'custom',
    ])
        ->assertOk()
        ->assertJsonPath('values.part_name', 'ヨシムラ マフラー')
        ->assertJsonPath('values.brand', 'YOSHIMURA')
        ->assertJsonPath('values.cost', 80000)
        ->assertJsonMissingPath('values.title');
});

it('voice maintenance parses title/cost/vendor/odometer (no date)', function () {
    fakeAi(['date' => null, 'cost' => 3000, 'vendor' => 'ナップス', 'odometer' => 19000, 'title' => 'チェーン給油', 'confidence' => '中']);
    $user = User::factory()->create();
    $bike = aiBike($user);

    $this->actingAs($user)->postJson("/garage/{$bike->id}/voice/record", [
        'transcript' => 'チェーン給油 3000円 ナップス 1万9千キロ', 'type' => 'maintenance',
    ])
        ->assertOk()
        ->assertJsonPath('values.title', 'チェーン給油')
        ->assertJsonPath('values.odometer', 19000)
        ->assertJsonMissingPath('values.maintained_at'); // 音声は日付を入れない
});

it('PII safety-net drops a card/phone-like vendor and an over-long string', function () {
    $user = User::factory()->create();
    $bike = aiBike($user);

    fakeAi(['title' => 'オイル交換', 'vendor' => '1234 5678 9012 3456', 'confidence' => '高']);
    $this->actingAs($user)->postJson("/garage/{$bike->id}/ocr/record", [
        'image' => UploadedFile::fake()->image('r.jpg', 400, 400), 'type' => 'maintenance',
    ])->assertOk()->assertJsonMissingPath('values.vendor')->assertJsonPath('values.title', 'オイル交換');

    fakeAi(['title' => 'オイル交換', 'vendor' => 'TEL 03-1234-5678', 'confidence' => '高']);
    $this->actingAs($user)->postJson("/garage/{$bike->id}/ocr/record", [
        'image' => UploadedFile::fake()->image('r.jpg', 400, 400), 'type' => 'maintenance',
    ])->assertOk()->assertJsonMissingPath('values.vendor');
});

it('keeps a normal vendor that has a short number (e.g. 246号店)', function () {
    fakeAi(['vendor' => '2りんかん 246号店', 'confidence' => '高']);
    $user = User::factory()->create();
    $bike = aiBike($user);

    $this->actingAs($user)->postJson("/garage/{$bike->id}/ocr/record", [
        'image' => UploadedFile::fake()->image('r.jpg', 400, 400), 'type' => 'maintenance',
    ])->assertOk()->assertJsonPath('values.vendor', '2りんかん 246号店');
});

it('attaches the odometer guard (last_odometer + warning on a ~10x jump)', function () {
    fakeAi(['odometer' => 200000, 'title' => 'タイヤ交換', 'confidence' => '高']);
    $user = User::factory()->create();
    $bike = aiBike($user, 20000);

    $res = $this->actingAs($user)->postJson("/garage/{$bike->id}/ocr/record", [
        'image' => UploadedFile::fake()->image('r.jpg', 400, 400), 'type' => 'maintenance',
    ]);
    $res->assertOk()->assertJsonPath('last_odometer', 20000);
    expect($res->json('odometer_warning'))->toContain('端数');
});

it('record AI is owner-only (404), validates type, requires auth (no AI call)', function () {
    $user = User::factory()->create();
    $bike = aiBike($user);

    // 非所有者 404（getBikeDetail で弾く＝AI を呼ばない）
    $this->actingAs(User::factory()->create())->postJson("/garage/{$bike->id}/ocr/record", [
        'image' => UploadedFile::fake()->image('r.jpg', 400, 400), 'type' => 'maintenance',
    ])->assertNotFound();

    // 不正 type 422
    $this->actingAs($user)->postJson("/garage/{$bike->id}/ocr/record", [
        'image' => UploadedFile::fake()->image('r.jpg', 400, 400), 'type' => 'fuel',
    ])->assertStatus(422);
});

it('record AI requires authentication', function () {
    $bike = aiBike(User::factory()->create());

    $this->postJson("/garage/{$bike->id}/voice/record", ['transcript' => 'x', 'type' => 'maintenance'])
        ->assertUnauthorized();
});

it('record AI is graceful (502) when the model output is unparseable', function () {
    Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => '読み取れません']]], 200)]);
    $user = User::factory()->create();
    $bike = aiBike($user);

    $this->actingAs($user)->postJson("/garage/{$bike->id}/ocr/record", [
        'image' => UploadedFile::fake()->image('r.jpg', 400, 400), 'type' => 'maintenance',
    ])->assertStatus(502)->assertJsonStructure(['error']);
});

it('the garage page renders the record OCR/voice cards in both forms', function () {
    $user = User::factory()->create();
    $bike = aiBike($user);

    $this->actingAs($user)->get("/garage/{$bike->id}")
        ->assertOk()
        ->assertSee('伝票を撮る')                                       // 整備OCRボタン
        ->assertSee(route('mybikes.ocr.record', $bike->id), false)      // record OCR endpoint
        ->assertSee(route('mybikes.voice.record', $bike->id), false);   // record voice endpoint
});
