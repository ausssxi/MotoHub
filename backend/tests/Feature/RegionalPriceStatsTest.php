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

it('builds a spread narrative per band (>=20 / 10-19 / <10) and null for robust<2', function () {
    $svc = app(RegionalPriceService::class);
    $national = ['median' => 1180000, 'median_man' => '118.0', 'count' => 40];

    // pct>=20帯
    $big = ['pct' => 22, 'diff_man' => '24.0', 'high' => ['block' => '関東', 'median_man' => '130.0'], 'low' => ['block' => '近畿', 'median_man' => '106.0']];
    $t1 = $svc->buildSpreadNarrative('CB400SF', $big, $national);
    expect($t1)->toContain('地域差が大きい');
    expect($t1)->toContain('近畿（約106.0万円）');
    expect($t1)->toContain('関東（約130.0万円）');
    expect($t1)->toContain('24.0万円（22%）');

    // 10<=pct<20帯
    $mid = ['pct' => 13, 'diff_man' => '29.1', 'high' => ['block' => '近畿', 'median_man' => '231.8'], 'low' => ['block' => '九州沖縄', 'median_man' => '202.7']];
    $t2 = $svc->buildSpreadNarrative('ハヤブサ', $mid, $national);
    expect($t2)->toContain('やや地域差');
    expect($t2)->toContain('九州沖縄が安め');
    expect($t2)->toContain('近畿が高め');

    // pct<10帯
    $small = ['pct' => 7, 'diff_man' => '2.5', 'high' => ['block' => '九州沖縄', 'median_man' => '39.9'], 'low' => ['block' => '中国', 'median_man' => '37.4']];
    $t3 = $svc->buildSpreadNarrative('PCX', $small, ['median' => 383000, 'median_man' => '38.3', 'count' => 760]);
    expect($t3)->toContain('全国でほぼ一様');
    expect($t3)->toContain('38.3万円前後');

    // robust<2 → null
    expect($svc->buildSpreadNarrative('Foo', null, $national))->toBeNull();
});

it('exposes spread_narrative through getForModel (present when spread, null otherwise)', function () {
    $model = regionTestModel();
    seedBlock($model->id, '東京都', 4, 5, 1300000); // 関東 robust
    seedBlock($model->id, '大阪府', 4, 5, 1000000); // 近畿 robust（spread大）

    $this->artisan('stats:regional-prices')->assertSuccessful();
    $d = app(RegionalPriceService::class)->getForModel($model);

    expect($d['spread_narrative'])->not->toBeNull();
    expect($d['spread_narrative'])->toContain($model->name);
});

// ---- 地域差・独立ページ (Task 3) ----

function gatedRegionModel(): BikeModel
{
    $model = regionTestModel(); // Honda / PCX / slug=pcx
    seedBlock($model->id, '東京都', 4, 5, 1300000); // 関東 20台 130万(高)
    seedBlock($model->id, '大阪府', 4, 5, 1000000); // 近畿 20台 100万(安)
    seedBlock($model->id, '愛知県', 4, 5, 1150000); // 中部 20台 115万 → robust3, spread26%
    return $model;
}

it('diff_man uses display-rounded high/low medians', function () {
    $model = gatedRegionModel();
    $this->artisan('stats:regional-prices')->assertSuccessful();
    $sp = app(RegionalPriceService::class)->getForModel($model)['spread'];

    // 130.0 - 100.0 = 30.0（画面の引き算と一致）
    expect($sp['diff_man'])->toBe('30.0');
    $hi = (float) $sp['high']['median_man'];
    $lo = (float) $sp['low']['median_man'];
    expect((float) $sp['diff_man'])->toBe(round($hi - $lo, 1));
});

it('region-price page returns 200 with content for a gated model', function () {
    $model = gatedRegionModel();
    $this->artisan('stats:regional-prices')->assertSuccessful();

    $this->get('/bikes/region-price/pcx')
        ->assertOk()
        ->assertSee('PCXの中古価格', false)
        ->assertSee('エリア別 中古相場（中央値）')
        ->assertSee('push-area-spread-' . $model->id, false)
        ->assertSee('詳細・スペック・相場推移');
});

it('region-price page 404s when below gate (only 2 robust blocks)', function () {
    $model = regionTestModel();
    seedBlock($model->id, '東京都', 4, 5, 1300000);
    seedBlock($model->id, '大阪府', 4, 5, 1250000); // robust2 <3 → ゲート外
    $this->artisan('stats:regional-prices')->assertSuccessful();

    $this->get('/bikes/region-price/pcx')->assertNotFound();
});

it('region-price page 404s when the model has no stats', function () {
    regionTestModel(); // listingなし → spread null
    $this->get('/bikes/region-price/pcx')->assertNotFound();
});

it('gatedRegionPriceModels returns gated models sorted by spread desc', function () {
    \Illuminate\Support\Facades\Cache::flush();
    $a = gatedRegionModel(); // spread 26%

    $mfr2 = Manufacturer::forceCreate(['name' => 'Yamaha', 'slug' => 'yamaha']);
    $b = BikeModel::create(['manufacturer_id' => $mfr2->id, 'name' => 'R1', 'slug' => 'r1']);
    seedBlock($b->id, '東京都', 4, 5, 2000000); // 関東 200万
    seedBlock($b->id, '大阪府', 4, 5, 1000000); // 近畿 100万
    seedBlock($b->id, '愛知県', 4, 5, 1500000); // 中部 150万 → spread 67%

    $this->artisan('stats:regional-prices')->assertSuccessful();
    \Illuminate\Support\Facades\Cache::flush();

    $gated = app(RegionalPriceService::class)->gatedRegionPriceModels(3, 20);

    expect(count($gated))->toBe(2);
    expect($gated[0]['model']->id)->toBe($b->id);  // 67% が先頭
    expect($gated[1]['model']->id)->toBe($a->id);  // 26% が次
    expect($gated[0]['spread']['pct'])->toBeGreaterThan($gated[1]['spread']['pct']);
});

// 注: model_detail 上のクロスリンク出/非出は spread(robust_block_count>=3 && pct>=20) 条件で
// ゲートされ、その spread 判定は上のテストで網羅済み。model_detail ページ全体の HTTP 描画は
// 既存の resale 集計が MySQL専用関数(DATEDIFF)を使い sqlite で落ちるためここでは描画しない
// （クロスリンクの出/非出はローカル実機 curl で gated=1 / non-gated=0 を確認済み）。
