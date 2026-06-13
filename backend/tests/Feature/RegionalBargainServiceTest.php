<?php

declare(strict_types=1);

use App\Http\Resources\Bike\ListingResource;
use App\Models\BikeModel;
use App\Models\Listing;
use App\Models\Manufacturer;
use App\Models\ModelRegionPriceStat;
use App\Services\Bike\RegionalBargainService;
use Illuminate\Support\Facades\DB;

function bargainStat(int $median, int $count): ModelRegionPriceStat
{
    return new ModelRegionPriceStat([
        'region_block' => '全国',
        'median_price' => $median,
        'listing_count' => $count,
    ]);
}

it('returns a median-based discount within the 10-50% band for robust stats', function () {
    // median 100万・全国30台、total 80万 → 20%お得
    $r = RegionalBargainService::fromStat(bargainStat(1000000, 30), 800000);

    expect($r)->not->toBeNull();
    expect($r['pct'])->toBe(20);
    expect($r['median_man'])->toBe('100.0');
    expect($r['diff_man'])->toBe('20.0');
});

it('suppresses the badge when discount exceeds 50% (the 94% bug guard)', function () {
    // 旧バグ再現: 中央値100万に対し total 6万 = 94%。新ロジックは「異常」として非表示。
    expect(RegionalBargainService::fromStat(bargainStat(1000000, 30), 60000))->toBeNull();
});

it('suppresses the badge below 10% discount (non-news)', function () {
    expect(RegionalBargainService::fromStat(bargainStat(1000000, 30), 950000))->toBeNull(); // 5%
});

it('includes exact 10% and 50% boundaries', function () {
    expect(RegionalBargainService::fromStat(bargainStat(1000000, 30), 900000)['pct'])->toBe(10);
    expect(RegionalBargainService::fromStat(bargainStat(1000000, 30), 500000)['pct'])->toBe(50);
});

it('suppresses the badge when the national row is not robust (n < 20)', function () {
    expect(RegionalBargainService::fromStat(bargainStat(1000000, 19), 800000))->toBeNull();
});

it('suppresses the badge for null stat, null price, or price below floor', function () {
    expect(RegionalBargainService::fromStat(null, 800000))->toBeNull();
    expect(RegionalBargainService::fromStat(bargainStat(1000000, 30), null))->toBeNull();
    expect(RegionalBargainService::fromStat(bargainStat(1000000, 30), 40000))->toBeNull(); // < MIN_PRICE
});

it('does not show a badge when the listing is above the median (only お得 side)', function () {
    expect(RegionalBargainService::fromStat(bargainStat(1000000, 30), 1100000))->toBeNull();
});

it('ListingResource exposes region_bargain end-to-end when nationalPriceStat is eager-loaded', function () {
    $mfr = Manufacturer::forceCreate(['name' => 'Honda', 'slug' => 'honda']);
    $model = BikeModel::create(['manufacturer_id' => $mfr->id, 'name' => 'PCX', 'slug' => 'pcx']);
    ModelRegionPriceStat::create([
        'bike_model_id' => $model->id,
        'region_block' => '全国',
        'median_price' => 1000000,
        'listing_count' => 30,
        'computed_at' => now(),
    ]);
    $siteId = DB::table('sites')->insertGetId(['name' => 'TestSite', 'created_at' => now(), 'updated_at' => now()]);

    $okId = DB::table('listings')->insertGetId([
        'site_id' => $siteId, 'bike_model_id' => $model->id, 'total_price' => 800000,
        'is_sold_out' => false, 'source_url' => 'https://e.test/ok', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $anomalyId = DB::table('listings')->insertGetId([
        'site_id' => $siteId, 'bike_model_id' => $model->id, 'total_price' => 60000, // 94% → 非表示
        'is_sold_out' => false, 'source_url' => 'https://e.test/anom', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $ok = Listing::with('bikeModel.nationalPriceStat')->find($okId);
    $anomaly = Listing::with('bikeModel.nationalPriceStat')->find($anomalyId);

    $okArr = (new ListingResource($ok))->resolve();
    $anomalyArr = (new ListingResource($anomaly))->resolve();

    expect($okArr['region_bargain'])->not->toBeNull();
    expect($okArr['region_bargain']['pct'])->toBe(20);
    expect($okArr['bargain_info']['is_bargain'])->toBeTrue();

    // 旧式なら94%お得と暴れたが、新式では非表示
    expect($anomalyArr['region_bargain'])->toBeNull();
    expect($anomalyArr['bargain_info'])->toBeNull();
});

it('ListingResource omits region_bargain when nationalPriceStat is NOT loaded (safe default)', function () {
    $mfr = Manufacturer::forceCreate(['name' => 'Honda', 'slug' => 'honda']);
    $model = BikeModel::create(['manufacturer_id' => $mfr->id, 'name' => 'PCX', 'slug' => 'pcx']);
    ModelRegionPriceStat::create([
        'bike_model_id' => $model->id, 'region_block' => '全国',
        'median_price' => 1000000, 'listing_count' => 30, 'computed_at' => now(),
    ]);
    $siteId = DB::table('sites')->insertGetId(['name' => 'TestSite', 'created_at' => now(), 'updated_at' => now()]);
    $id = DB::table('listings')->insertGetId([
        'site_id' => $siteId, 'bike_model_id' => $model->id, 'total_price' => 800000,
        'is_sold_out' => false, 'source_url' => 'https://e.test/x', 'created_at' => now(), 'updated_at' => now(),
    ]);

    // bikeModel はロードするが nationalPriceStat は意図的に未ロード
    $listing = Listing::with('bikeModel')->find($id);
    $arr = (new ListingResource($listing))->resolve();

    expect($arr['region_bargain'])->toBeNull();
});
