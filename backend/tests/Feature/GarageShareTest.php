<?php

declare(strict_types=1);

use App\Models\BikeModel;
use App\Models\Manufacturer;
use App\Models\MyBike;
use App\Models\User;
use App\Services\MyBike\MyBikeService;
use Illuminate\Support\Facades\Storage;

function shareBike(array $bikeAttrs = [], array $userAttrs = []): MyBike
{
    $user = User::factory()->create(array_merge(['name' => '本名タロウ', 'review_display_name' => 'rider_x'], $userAttrs));
    $mfr = Manufacturer::where('slug', 'honda')->first() ?? Manufacturer::forceCreate(['name' => 'Honda', 'slug' => 'honda']);
    $model = BikeModel::where('slug', 'pcx')->first() ?? BikeModel::create(['manufacturer_id' => $mfr->id, 'name' => 'PCX160', 'slug' => 'pcx']);

    return MyBike::create(array_merge([
        'user_id' => $user->id, 'bike_model_id' => $model->id, 'name' => 'マイPCX',
        'is_public' => true, 'initial_odometer' => 0, 'current_odometer' => 12345,
    ], $bikeAttrs));
}

// ---- OGPメタタグ ----

it('public garage page emits OGP/Twitter card meta pointing at the dynamic image', function () {
    $bike = shareBike();

    $this->get(route('garage.public.show', $bike->id))
        ->assertOk()
        ->assertSee('property="og:image"', false)
        ->assertSee(route('garage.public.ogp', $bike->id), false)            // og:image = 動的OGP
        ->assertSee('name="twitter:card" content="summary_large_image"', false)
        ->assertSee('rider_x', false);                                       // 公開ハンドル
});

it('never leaks the real name in the public page meta/body', function () {
    $bike = shareBike();

    $res = $this->get(route('garage.public.show', $bike->id))->assertOk();
    expect($res->getContent())->not->toContain('本名タロウ');
});

it('public page shows the X share + copy-link controls', function () {
    $bike = shareBike();

    $this->get(route('garage.public.show', $bike->id))
        ->assertOk()
        ->assertSee('Xでシェア')
        ->assertSee('リンクをコピー')
        ->assertSee('twitter.com/intent/tweet', false);
});

// ---- privacy: 非公開はシェア不可 ----

it('private garage returns 404 for both the public page and the OGP image', function () {
    $bike = shareBike(['is_public' => false]);

    $this->get(route('garage.public.show', $bike->id))->assertNotFound();
    $this->get(route('garage.public.ogp', $bike->id))->assertNotFound();
});

// ---- OGP画像生成 ----

it('renders a PNG OGP image for a public garage (graceful with no photo / no records)', function () {
    Storage::fake('public');
    $bike = shareBike(['current_odometer' => 0]); // 空データ（写真なし・記録なし）

    $res = $this->get(route('garage.public.ogp', $bike->id))->assertOk();
    expect($res->headers->get('Content-Type'))->toBe('image/png');
    // 生成された PNG が実在し PNG マジックナンバーを持つ（空データ・写真なしでも壊れず生成）
    $file = Storage::disk('public')->allFiles('ogp/garage/v1/'.$bike->id)[0];
    expect(substr(Storage::disk('public')->get($file), 0, 4))->toBe("\x89PNG");
});

it('renders a PNG OGP image with records present (stats path)', function () {
    Storage::fake('public');
    $bike = shareBike();
    $svc = app(MyBikeService::class);
    $svc->recordFuel($bike, ['filled_at' => '2026-01-01', 'odometer' => 12000, 'quantity' => 5, 'is_full_tank' => 1]);
    $svc->recordFuel($bike, ['filled_at' => '2026-02-01', 'odometer' => 12345, 'quantity' => 5, 'is_full_tank' => 1]);
    $svc->recordMaintenance($bike, ['maintained_at' => '2026-02-01', 'title' => 'オイル交換', 'odometer' => 12345, 'cost' => 3000]);

    $this->get(route('garage.public.ogp', $bike->id))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');
});

it('caches the OGP image (second request reuses the cached file)', function () {
    Storage::fake('public');
    $bike = shareBike();

    $this->get(route('garage.public.ogp', $bike->id))->assertOk();
    $files = Storage::disk('public')->allFiles('ogp/garage/v1/'.$bike->id);
    expect($files)->toHaveCount(1);

    $this->get(route('garage.public.ogp', $bike->id))->assertOk();
    expect(Storage::disk('public')->allFiles('ogp/garage/v1/'.$bike->id))->toHaveCount(1); // 増えない＝キャッシュ命中
});

// ---- shareStats（単一ソース） ----

it('garageShareStats uses the public handle, not the real name', function () {
    $bike = shareBike();
    $stats = app(MyBikeService::class)->garageShareStats($bike->fresh()->load(['user', 'bikeModel.manufacturer']));

    expect($stats['handle'])->toBe('rider_x')
        ->and($stats['handle'])->not->toBe('本名タロウ')
        ->and($stats['odometer'])->toBe(12345)
        ->and($stats['manufacturer'])->toBe('Honda');
});
