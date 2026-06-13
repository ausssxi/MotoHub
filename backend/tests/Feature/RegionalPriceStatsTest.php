<?php

declare(strict_types=1);

use App\Models\BikeModel;
use App\Models\Manufacturer;
use App\Models\ModelRegionPriceStat;
use App\Services\Bike\RegionalPriceService;
use Illuminate\Support\Facades\DB;

function regionTestSiteId(): int
{
    // static は使わない: RefreshDatabase で各テスト毎にDBが再生成されるため、
    // 都度 fresh DB を引いて存在しなければ作る（古いidを掴むとFK違反になる）。
    $existing = DB::table('sites')->where('name', 'TestSite')->value('id');
    if ($existing) {
        return (int) $existing;
    }

    return DB::table('sites')->insertGetId(['name' => 'TestSite', 'created_at' => now(), 'updated_at' => now()]);
}

function makeShop(string $prefecture): int
{
    $uid = uniqid('', true);

    return DB::table('shops')->insertGetId([
        'name' => 'shop-' . $prefecture . '-' . $uid,
        'address' => $prefecture . 'テスト住所' . $uid,
        'prefecture' => $prefecture,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/** 1つの shop に複数価格の active 在庫を作る */
function seedListings(int $shopId, int $modelId, array $prices): void
{
    foreach ($prices as $price) {
        DB::table('listings')->insert([
            'site_id' => regionTestSiteId(),
            'shop_id' => $shopId,
            'bike_model_id' => $modelId,
            'total_price' => $price,
            'is_sold_out' => false,
            'source_url' => 'https://example.test/' . uniqid('l', true),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

function regionTestModel(): BikeModel
{
    $mfr = Manufacturer::forceCreate(['name' => 'Honda', 'slug' => 'honda']);

    return BikeModel::create(['manufacturer_id' => $mfr->id, 'name' => 'PCX', 'slug' => 'pcx']);
}

it('computes median/count with per-shop cap, gate and national row', function () {
    $model = regionTestModel();

    // 関東(東京都) 単一shopに6件を id順(=投入順)で作成: 100k..500k, 9.9M。
    // per-shopキャップ5は id順で先頭5件を採用するため、6件目の 9.9M(外れ値)が除外される。
    $kanto = makeShop('東京都');
    seedListings($kanto, $model->id, [100000, 200000, 300000, 400000, 500000, 9900000]);

    // 近畿(大阪府) 5件: 600k..1000k
    $kinki = makeShop('大阪府');
    seedListings($kinki, $model->id, [600000, 700000, 800000, 900000, 1000000]);

    // 中国(広島県) 4件 → ゲート未達で保存されない（が全国には4件寄与）
    $chugoku = makeShop('広島県');
    seedListings($chugoku, $model->id, [100000, 100000, 100000, 100000]);

    $this->artisan('stats:regional-prices')->assertSuccessful();

    $rows = ModelRegionPriceStat::where('bike_model_id', $model->id)->get()->keyBy('region_block');

    // (d) per-shopキャップ: 関東は6件中先頭5件(id順)、6件目の9.9Mは除外 → median=300k, count=5
    expect($rows['関東']->listing_count)->toBe(5);
    expect((int) $rows['関東']->median_price)->toBe(300000);

    // (a)(b) 近畿: median=800k, count=5
    expect($rows['近畿']->listing_count)->toBe(5);
    expect((int) $rows['近畿']->median_price)->toBe(800000);

    // (c) ゲート: 中国(4件)は保存されない
    expect($rows->has('中国'))->toBeFalse();

    // (e) 全国: 関東5 + 近畿5 + 中国4 = 14件、median=(300k+400k)/2=350k
    expect($rows['全国']->listing_count)->toBe(14);
    expect((int) $rows['全国']->median_price)->toBe(350000);
});

it('is idempotent (truncate + reinsert) on repeated runs', function () {
    $model = regionTestModel();
    $a = makeShop('東京都');
    seedListings($a, $model->id, [100000, 200000, 300000, 400000, 500000]);
    $b = makeShop('大阪府');
    seedListings($b, $model->id, [100000, 200000, 300000, 400000, 500000]);

    $this->artisan('stats:regional-prices')->assertSuccessful();
    $first = ModelRegionPriceStat::count();
    $this->artisan('stats:regional-prices')->assertSuccessful();
    $second = ModelRegionPriceStat::count();

    expect($second)->toBe($first);
    expect($first)->toBeGreaterThan(0);
});

it('service hides the section when fewer than 2 blocks have data', function () {
    $model = regionTestModel();
    // 関東のみ5件 → 1ブロックのみ
    $only = makeShop('東京都');
    seedListings($only, $model->id, [100000, 200000, 300000, 400000, 500000]);

    $this->artisan('stats:regional-prices')->assertSuccessful();

    $display = app(RegionalPriceService::class)->getForModel($model);

    expect($display['regions'])->toBe([]);
    expect($display['headline'])->toBeNull();
});

it('service returns ordered blocks + headline when 2+ blocks have data', function () {
    $model = regionTestModel();
    seedListings(makeShop('東京都'), $model->id, [200000, 300000, 400000, 500000, 600000]); // 関東 median 400k
    seedListings(makeShop('大阪府'), $model->id, [100000, 100000, 100000, 100000, 100000]); // 近畿 median 100k

    $this->artisan('stats:regional-prices')->assertSuccessful();

    $display = app(RegionalPriceService::class)->getForModel($model);

    expect(collect($display['regions'])->pluck('block')->all())->toBe(['関東', '近畿']); // block_order順
    expect($display['national'])->not->toBeNull();
    expect($display['headline'])->toContain('関東'); // 高値ブロック
    expect($display['headline'])->toContain('近畿'); // 安値ブロック
});
