<?php

declare(strict_types=1);

use App\Models\BikeModel;
use App\Models\Listing;
use App\Models\Manufacturer;
use App\Models\ModelRegionPriceStat;
use App\Services\Bike\RegionalBargainService;
use Illuminate\Support\Facades\DB;

function sortStat(int $median, int $count): ModelRegionPriceStat
{
    return new ModelRegionPriceStat(['region_block' => '全国', 'median_price' => $median, 'listing_count' => $count]);
}

// ---- sortScore: 連続値 [0,50]、表示バッジとの差別化（10%下限なし／>50は0） ----

it('returns the discount pct for a robust 45% deal', function () {
    expect(RegionalBargainService::sortScore(sortStat(1000000, 30), 550000))->toBe(45.0);
});

it('keeps small discounts for sorting (6% -> 6, unlike the hidden badge)', function () {
    expect(RegionalBargainService::sortScore(sortStat(1000000, 30), 940000))->toBe(6.0);
});

it('REGRESSION: a 94% anomaly scores 0, NOT clamped to 50', function () {
    // 旧バグの核心: clampすると50=最高値で上位固定。0で除外する。
    expect(RegionalBargainService::sortScore(sortStat(1000000, 30), 60000))->toBe(0.0);
});

it('keeps the exact 50% boundary but drops just over it', function () {
    expect(RegionalBargainService::sortScore(sortStat(1000000, 30), 500000))->toBe(50.0); // 50%
    expect(RegionalBargainService::sortScore(sortStat(1000000, 30), 499000))->toBe(0.0);  // 50.1% → 0
});

it('scores 0 when above the median (割高 is neutral)', function () {
    expect(RegionalBargainService::sortScore(sortStat(1000000, 30), 1100000))->toBe(0.0);
});

it('scores 0 for non-robust national rows (n < 20)', function () {
    expect(RegionalBargainService::sortScore(sortStat(1000000, 10), 550000))->toBe(0.0);
});

it('scores 0 for missing national row, null price, or price below floor', function () {
    expect(RegionalBargainService::sortScore(null, 550000))->toBe(0.0);
    expect(RegionalBargainService::sortScore(sortStat(1000000, 30), null))->toBe(0.0);
    expect(RegionalBargainService::sortScore(sortStat(1000000, 30), 40000))->toBe(0.0); // < 5万
});

// ---- accessor 経由 + N+1ガード ----

it('the bargain_score accessor returns the median sort score', function () {
    $mfr = Manufacturer::forceCreate(['name' => 'Honda', 'slug' => 'honda']);
    $model = BikeModel::create(['manufacturer_id' => $mfr->id, 'name' => 'PCX', 'slug' => 'pcx']);
    ModelRegionPriceStat::create([
        'bike_model_id' => $model->id, 'region_block' => '全国',
        'median_price' => 1000000, 'listing_count' => 30, 'computed_at' => now(),
    ]);
    $siteId = DB::table('sites')->insertGetId(['name' => 'TestSite', 'created_at' => now(), 'updated_at' => now()]);

    $id = DB::table('listings')->insertGetId([
        'site_id' => $siteId, 'bike_model_id' => $model->id, 'total_price' => 550000,
        'is_sold_out' => false, 'source_url' => 'https://e.test/a', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $listing = Listing::with('bikeModel.nationalPriceStat')->find($id);
    expect($listing->bargain_score)->toBe(45.0);
});

it('eager-loaded nationalPriceStat means the accessor fires NO extra queries (N+1 guard)', function () {
    $mfr = Manufacturer::forceCreate(['name' => 'Honda', 'slug' => 'honda']);
    $model = BikeModel::create(['manufacturer_id' => $mfr->id, 'name' => 'PCX', 'slug' => 'pcx']);
    ModelRegionPriceStat::create([
        'bike_model_id' => $model->id, 'region_block' => '全国',
        'median_price' => 1000000, 'listing_count' => 30, 'computed_at' => now(),
    ]);
    $siteId = DB::table('sites')->insertGetId(['name' => 'TestSite', 'created_at' => now(), 'updated_at' => now()]);
    foreach ([550000, 60000, 1100000, 940000] as $i => $price) {
        DB::table('listings')->insert([
            'site_id' => $siteId, 'bike_model_id' => $model->id, 'total_price' => $price,
            'is_sold_out' => false, 'source_url' => 'https://e.test/n' . $i, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    // scout:sync-flagged と同じ eager-load
    $listings = Listing::where('bike_model_id', $model->id)
        ->with(['shop', 'bikeModel.manufacturer', 'bikeModel.marketStats', 'bikeModel.nationalPriceStat', 'tags'])
        ->orderBy('id')
        ->get();

    DB::enableQueryLog();
    DB::flushQueryLog();
    $scores = $listings->map(fn ($l) => $l->bargain_score)->all();
    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    // 4台分のスコア算出で追加クエリ0 = N+1なし
    expect($queryCount)->toBe(0);
    // 45% / 94%異常→0 / 割高→0 / 6%
    expect($scores)->toBe([45.0, 0.0, 0.0, 6.0]);
});
