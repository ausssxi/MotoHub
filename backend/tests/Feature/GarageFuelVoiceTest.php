<?php

declare(strict_types=1);

use App\Models\BikeModel;
use App\Models\Manufacturer;
use App\Models\MyBike;
use App\Models\User;
use Illuminate\Support\Facades\Http;

function voiceBike(User $user): MyBike
{
    $mfr = Manufacturer::where('slug', 'honda')->first() ?? Manufacturer::forceCreate(['name' => 'Honda', 'slug' => 'honda']);
    $model = BikeModel::where('slug', 'pcx')->first() ?? BikeModel::create(['manufacturer_id' => $mfr->id, 'name' => 'PCX', 'slug' => 'pcx']);

    return MyBike::create(['user_id' => $user->id, 'bike_model_id' => $model->id, 'name' => 'マイPCX', 'current_odometer' => 1000]);
}

function fakeVoiceParse(array $payload): void
{
    Http::fake([
        'api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => json_encode($payload)]]], 200),
    ]);
}

it('owner can parse a free-form transcript into odometer/quantity/cost (no date)', function () {
    fakeVoiceParse(['odometer' => 60000, 'quantity' => 10, 'cost' => 1500, 'date' => null, 'confidence' => '高']);
    $user = User::factory()->create();
    $bike = voiceBike($user);

    $res = $this->actingAs($user)->postJson("/garage/{$bike->id}/voice/fuel", [
        'transcript' => '6万キロ 10リットル 1500円',
    ]);

    $res->assertOk()
        ->assertJsonPath('confidence', '高')
        ->assertJsonPath('values.odometer', 60000)
        ->assertJsonPath('values.cost', 1500)
        ->assertJsonMissingPath('values.filled_at'); // 音声は日付を入れない（today デフォルト）
});

it('leaves unspoken fields out (partial fill)', function () {
    fakeVoiceParse(['odometer' => null, 'quantity' => 12.3, 'cost' => null, 'date' => null, 'confidence' => '中']);
    $user = User::factory()->create();
    $bike = voiceBike($user);

    $this->actingAs($user)->postJson("/garage/{$bike->id}/voice/fuel", ['transcript' => '12.3リットル入れた'])
        ->assertOk()
        ->assertJsonPath('values.quantity', 12.3)
        ->assertJsonMissingPath('values.odometer')
        ->assertJsonMissingPath('values.cost');
});

it('a non-owner cannot use voice on someone elses bike (404)', function () {
    fakeVoiceParse(['quantity' => 5, 'confidence' => '高']);
    $bike = voiceBike(User::factory()->create());

    $this->actingAs(User::factory()->create())
        ->postJson("/garage/{$bike->id}/voice/fuel", ['transcript' => '5リットル'])
        ->assertNotFound();
});

it('requires a transcript', function () {
    $user = User::factory()->create();
    $bike = voiceBike($user);

    $this->actingAs($user)->postJson("/garage/{$bike->id}/voice/fuel", [])
        ->assertStatus(422);
});

it('rejects an over-long transcript', function () {
    $user = User::factory()->create();
    $bike = voiceBike($user);

    $this->actingAs($user)->postJson("/garage/{$bike->id}/voice/fuel", ['transcript' => str_repeat('あ', 501)])
        ->assertStatus(422);
});

it('returns a graceful 502 when the parse output is unusable', function () {
    Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => 'よく聞き取れませんでした']]], 200)]);
    $user = User::factory()->create();
    $bike = voiceBike($user);

    $this->actingAs($user)->postJson("/garage/{$bike->id}/voice/fuel", ['transcript' => 'ノイズ'])
        ->assertStatus(502)->assertJsonStructure(['error']);
});

it('is disabled when voice_enabled is off', function () {
    config(['garage.voice_enabled' => false]);
    $user = User::factory()->create();
    $bike = voiceBike($user);

    $this->actingAs($user)->postJson("/garage/{$bike->id}/voice/fuel", ['transcript' => '5リットル'])
        ->assertNotFound();
});

it('requires authentication', function () {
    $bike = voiceBike(User::factory()->create());

    $this->postJson("/garage/{$bike->id}/voice/fuel", ['transcript' => '5リットル'])
        ->assertUnauthorized();
});
