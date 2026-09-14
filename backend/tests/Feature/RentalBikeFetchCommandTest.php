<?php

use App\Models\RentalBikeShop;
use App\Support\RentalBike\ShopFetcher;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

/**
 * ダミーの Fetcher（★外部アクセスしない）。固定の店舗配列を返す。
 */
final class FakeRentalBikeFetcher implements ShopFetcher
{
    /** @var array<int, array<string, mixed>> */
    public static array $rows = [];

    public function slug(): string
    {
        return 'fake';
    }

    public function company(): string
    {
        return 'ダミー社';
    }

    public function officialUrl(): string
    {
        return 'https://example.test/';
    }

    public function fetch(): array
    {
        return self::$rows;
    }
}

beforeEach(function () {
    FakeRentalBikeFetcher::$rows = [
        [
            'external_id' => null,
            'name' => 'ダミー千葉店',
            'postal_code' => '285-0856',
            'address' => '千葉県佐倉市井野町60-41',
            'prefecture' => '千葉県',
            'city' => '佐倉市',
            'tel' => '043-420-8464',
            'opening_hours' => null,
            'official_url' => 'https://example.test/chiba',
        ],
        [
            'external_id' => null,
            'name' => 'ダミー江戸川店',
            'postal_code' => '133-0057',
            'address' => '東京都江戸川区西小岩1-4-7',
            'prefecture' => '東京都',
            'city' => '江戸川区',
            'tel' => null,
            'opening_hours' => null,
            'official_url' => 'https://example.test/edogawa',
        ],
    ];

    config(['rental_bike.fetchers' => ['fake' => FakeRentalBikeFetcher::class]]);
});

it('writes nothing to the DB on --dry-run', function () {
    $this->artisan('rental-bike:fetch', ['company' => 'fake', '--dry-run' => true])
        ->assertSuccessful();

    expect(RentalBikeShop::count())->toBe(0);
});

it('creates rows on a real run and stays idempotent on re-run (no duplicates)', function () {
    $this->artisan('rental-bike:fetch', ['company' => 'fake'])->assertSuccessful();
    expect(RentalBikeShop::count())->toBe(2);

    // 同じデータで2回目 → 更新されるだけで重複しない。
    $this->artisan('rental-bike:fetch', ['company' => 'fake'])->assertSuccessful();
    expect(RentalBikeShop::count())->toBe(2);

    $shop = RentalBikeShop::where('name', 'ダミー千葉店')->firstOrFail();
    expect($shop->prefecture)->toBe('千葉県')
        ->and($shop->city)->toBe('佐倉市')
        ->and($shop->company_slug)->toBe('fake')
        ->and($shop->is_active)->toBeTrue()
        ->and($shop->fetched_at)->not->toBeNull();
});

it('has no image-related columns on the table', function () {
    foreach (['image', 'image_url', 'images', 'photo', 'photos', 'logo', 'thumbnail', 'thumb', 'img'] as $col) {
        expect(Schema::hasColumn('rental_bike_shops', $col))->toBeFalse();
    }
});

it('keeps the record when geocoding fails (only stamps geocode_failed_at)', function () {
    // GSI が何も返さない → ジオコーディング失敗。
    Http::fake(['*' => Http::response([], 200)]);

    RentalBikeShop::create([
        'company' => 'ダミー社',
        'company_slug' => 'fake',
        'name' => 'ダミー千葉店',
        'postal_code' => '285-0856',
        'address' => '千葉県佐倉市井野町60-41',
        'prefecture' => '千葉県',
        'city' => '佐倉市',
        'official_url' => 'https://example.test/chiba',
        'dedup_key' => RentalBikeShop::makeDedupKey('fake', null, 'ダミー千葉店', '千葉県佐倉市井野町60-41'),
    ]);

    $this->artisan('rental-bike:geocode', ['--sleep' => 0])->assertSuccessful();

    // 失敗しても消えない。座標は付かず、失敗時刻だけが刻まれる。
    expect(RentalBikeShop::count())->toBe(1);
    $shop = RentalBikeShop::firstOrFail();
    expect($shop->latitude)->toBeNull()
        ->and($shop->longitude)->toBeNull()
        ->and($shop->geocode_failed_at)->not->toBeNull();
});
