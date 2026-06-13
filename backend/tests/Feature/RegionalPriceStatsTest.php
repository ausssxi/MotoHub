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

/** 1つの shop に複数価格の active 在庫を作る（投入順 = id順） */
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

/**
 * あるブロック(prefecture)に、shopCount店 × perShop台（全て同一価格）の在庫を作る。
 * per-shopキャップ5があるため perShop<=5 で「キャップ後台数 = shopCount*perShop」を作れる。
 */
function seedBlock(int $modelId, string $prefecture, int $shopCount, int $perShop, int $price): void
{
    for ($i = 0; $i < $shopCount; $i++) {
        seedListings(makeShop($prefecture), $modelId, array_fill(0, $perShop, $price));
    }
}

function regionTestModel(): BikeModel
{
    $mfr = Manufacturer::forceCreate(['name' => 'Honda', 'slug' => 'honda']);

    return BikeModel::create(['manufacturer_id' => $mfr->id, 'name' => 'PCX', 'slug' => 'pcx']);
}

it('computes median/count with per-shop cap, gate(10) and national row', function () {
    $model = regionTestModel();

    // 関東: 2店 → キャップ後10台、median=550k。外れ値9.9M(id6)はキャップで除外。
    //   東京都の店: id順6件 [100k..500k, 9.9M] → 先頭5件 [100k..500k]
    //   神奈川県の店: 5件すべて 600k
    $tokyo = makeShop('東京都');
    seedListings($tokyo, $model->id, [100000, 200000, 300000, 400000, 500000, 9900000]);
    seedListings(makeShop('神奈川県'), $model->id, [600000, 600000, 600000, 600000, 600000]);

    // 近畿: 9台（5+4）→ ゲート10未達で保存されない。が全国には9台寄与。価格すべて 700k
    seedListings(makeShop('大阪府'), $model->id, [700000, 700000, 700000, 700000, 700000]);
    seedListings(makeShop('京都府'), $model->id, [700000, 700000, 700000, 700000]);

    $this->artisan('stats:regional-prices')->assertSuccessful();

    $rows = ModelRegionPriceStat::where('bike_model_id', $model->id)->get()->keyBy('region_block');

    // (d) per-shopキャップ + (a)(b): 関東は先頭5件(id順)で9.9M除外 → median=550k, count=10
    expect($rows['関東']->listing_count)->toBe(10);
    expect((int) $rows['関東']->median_price)->toBe(550000);

    // (c) ゲート: 近畿(9台)は保存されない
    expect($rows->has('近畿'))->toBeFalse();

    // (e) 全国: 関東10 + 近畿9 = 19台（ゲート未達ブロックも全国には寄与）、median=600k
    expect($rows['全国']->listing_count)->toBe(19);
    expect((int) $rows['全国']->median_price)->toBe(600000);
});

it('is idempotent (delete + reinsert) on repeated runs', function () {
    $model = regionTestModel();
    seedBlock($model->id, '東京都', 2, 5, 300000); // 関東 10台
    seedBlock($model->id, '大阪府', 2, 5, 300000); // 近畿 10台

    $this->artisan('stats:regional-prices')->assertSuccessful();
    $first = ModelRegionPriceStat::count();
    $this->artisan('stats:regional-prices')->assertSuccessful();
    $second = ModelRegionPriceStat::count();

    expect($second)->toBe($first);
    expect($first)->toBeGreaterThan(0);
});

it('service hides the section when fewer than 2 blocks pass the display gate', function () {
    $model = regionTestModel();
    seedBlock($model->id, '東京都', 2, 5, 300000); // 関東 10台 → 表示
    seedBlock($model->id, '大阪府', 1, 5, 300000); // 近畿 5台 → ゲート10未達で非表示

    $this->artisan('stats:regional-prices')->assertSuccessful();

    $display = app(RegionalPriceService::class)->getForModel($model);

    expect($display['regions'])->toBe([]);
    expect($display['headline'])->toBeNull();
});

it('headline picks high/low only from robust blocks (n>=20), ignoring thin noise blocks', function () {
    $model = regionTestModel();

    // 頑健ブロック2つ（各20台）
    seedBlock($model->id, '東京都', 4, 5, 1300000); // 関東 20台 median 130.0万（高値・頑健）
    seedBlock($model->id, '大阪府', 4, 5, 1060000); // 近畿 20台 median 106.0万（安値・頑健）
    // 薄いノイズブロック（10台・n<20）: CB1300四国の「156万 n=8」相当。表には出るが見出しには使わない
    seedBlock($model->id, '香川県', 2, 5, 1560000); // 四国 10台 median 156.0万（参考）

    $this->artisan('stats:regional-prices')->assertSuccessful();

    $display = app(RegionalPriceService::class)->getForModel($model);

    // 四国(10台)は表示されるが robust=false（参考値）
    $blocks = collect($display['regions'])->keyBy('block');
    expect($blocks->has('四国'))->toBeTrue();
    expect($blocks['四国']['robust'])->toBeFalse();
    expect($blocks['関東']['robust'])->toBeTrue();

    // 見出しは頑健ブロックのみから選定 → 関東(高)/近畿(安)。四国(噪)は出ない
    expect($display['headline'])->toContain('関東');
    expect($display['headline'])->toContain('近畿');
    expect($display['headline'])->not->toContain('四国');
    expect($display['headline'])->toContain('130.0万円'); // 関東(高値・頑健)
    expect($display['headline'])->toContain('106.0万円'); // 近畿(安値・頑健)
});

it('headline falls back to national-only when fewer than 2 robust blocks', function () {
    $model = regionTestModel();

    // 表示はされる(各10台)が、どちらも n<20 で非頑健 → 比較断定はしない
    seedBlock($model->id, '東京都', 2, 5, 1200000); // 関東 10台（参考）
    seedBlock($model->id, '大阪府', 2, 5, 1000000); // 近畿 10台（参考）

    $this->artisan('stats:regional-prices')->assertSuccessful();

    $display = app(RegionalPriceService::class)->getForModel($model);

    expect($display['regions'])->toHaveCount(2);
    expect($display['headline'])->not->toContain('高め');
    expect($display['headline'])->not->toContain('割安');
    expect($display['headline'])->toContain('全国の中央値');
});

it('excludes catch-all (name LIKE) and explicit non-bike model_ids from stats', function () {
    $mfr = Manufacturer::forceCreate(['name' => 'Honda', 'slug' => 'honda']);

    // 実車種（保存される）
    $real = BikeModel::create(['manufacturer_id' => $mfr->id, 'name' => 'PCX', 'slug' => 'pcx']);
    // キャッチオール（name LIKE 'その他' で除外）
    $catchAll = BikeModel::create(['manufacturer_id' => $mfr->id, 'name' => 'ホンダ その他', 'slug' => 'honda-other']);
    // 非バイク（明示 model_id で除外）
    $nonBike = BikeModel::create(['manufacturer_id' => $mfr->id, 'name' => '除雪機', 'slug' => 'snow']);

    // 明示idは実在テストidに差し替え（config既定の1536等はテストDBに無いため）
    config(['regions.catch_all_exclusions' => ['name_like' => ['その他'], 'model_ids' => [$nonBike->id]]]);

    // 3モデルとも保存ゲート(10台)を通る量を投入
    foreach ([$real, $catchAll, $nonBike] as $m) {
        seedBlock($m->id, '東京都', 2, 5, 300000); // 関東 10台
        seedBlock($m->id, '大阪府', 2, 5, 300000); // 近畿 10台
    }

    $this->artisan('stats:regional-prices')->assertSuccessful();

    expect(ModelRegionPriceStat::where('bike_model_id', $real->id)->exists())->toBeTrue();
    expect(ModelRegionPriceStat::where('bike_model_id', $catchAll->id)->exists())->toBeFalse();
    expect(ModelRegionPriceStat::where('bike_model_id', $nonBike->id)->exists())->toBeFalse();
});

it('derives spread from robust regions (high/low vs national median)', function () {
    $model = regionTestModel();
    seedBlock($model->id, '東京都', 4, 5, 1300000); // 関東 20台 robust median 130万
    seedBlock($model->id, '大阪府', 4, 5, 1060000); // 近畿 20台 robust median 106万
    // 全国 = 40台, median 118万 → spread=(130-106)/118=20%

    $this->artisan('stats:regional-prices')->assertSuccessful();
    $spread = app(RegionalPriceService::class)->getForModel($model)['spread'];

    expect($spread)->not->toBeNull();
    expect($spread['pct'])->toBe(20);
    expect($spread['robust_block_count'])->toBe(2);
    expect($spread['high']['block'])->toBe('関東');
    expect($spread['low']['block'])->toBe('近畿');
});

it('returns null spread when fewer than 2 robust blocks (thin block ignored)', function () {
    $model = regionTestModel();
    seedBlock($model->id, '東京都', 4, 5, 1300000); // 関東 20台 robust
    seedBlock($model->id, '大阪府', 2, 5, 1060000); // 近畿 10台 表示はされるが非robust

    $this->artisan('stats:regional-prices')->assertSuccessful();
    $display = app(RegionalPriceService::class)->getForModel($model);

    expect($display['regions'])->toHaveCount(2); // 表示は2ブロック
    expect($display['spread'])->toBeNull();      // robustは1つ → spreadなし
});
