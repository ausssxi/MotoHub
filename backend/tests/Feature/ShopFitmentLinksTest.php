<?php

use App\Models\BikeModel;
use App\Models\Manufacturer;
use App\Models\ModelFitment;
use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush(); // publishedTaskMap の 24h キャッシュがテスト間で残らないように
});

function sflShop(): Shop
{
    return Shop::create([
        'name' => 'テスト整備店', 'prefecture' => '東京都', 'city' => '世田谷区',
        'address' => 'sf-'.uniqid(), 'shop_type' => 'dealer', 'source' => Shop::SOURCE_SCRAPER,
    ]);
}

function sflModel(string $name, string $slug): BikeModel
{
    $mfr = Manufacturer::firstWhere('slug', 'honda');
    if (! $mfr) {
        $mfr = new Manufacturer(['slug' => 'honda']);
        $mfr->name = 'ホンダ';
        $mfr->save();
    }

    return BikeModel::create(['manufacturer_id' => $mfr->id, 'name' => $name, 'slug' => $slug]);
}

function sflVerify(BikeModel $m, string $task, ?string $partNo = null): void
{
    ModelFitment::create([
        'bike_model_id' => $m->id, 'task' => $task, 'frame_code' => 'AF00',
        'recommended_part_no' => $partNo ?? 'DUMMY-PN', 'verified_at' => '2026-07-01',
    ]);
}

function sflStock(Shop $shop, BikeModel $m, bool $soldOut = false): void
{
    $siteId = DB::table('sites')->where('name', 'TestSite')->value('id')
        ?? DB::table('sites')->insertGetId(['name' => 'TestSite', 'created_at' => now(), 'updated_at' => now()]);

    DB::table('listings')->insert([
        'site_id' => $siteId, 'shop_id' => $shop->id, 'bike_model_id' => $m->id,
        'total_price' => 300000, 'is_sold_out' => $soldOut,
        'source_url' => 'https://e.test/'.uniqid(), 'created_at' => now(), 'updated_at' => now(),
    ]);
}

// ─────────── 表示・リンク ───────────

it('shows the block with correct fitment links for an in-stock published model', function () {
    $shop = sflShop();
    $m = sflModel('ジャイロキャノピー', 'gyro-canopy');
    sflVerify($m, 'battery', 'YTZ7S');
    sflVerify($m, 'oil');
    sflStock($shop, $m);

    $res = $this->get("/shops/{$shop->id}")->assertOk();
    $res->assertSee('取扱車種の適合パーツ早見表')
        ->assertSee('ホンダ ジャイロキャノピー')
        ->assertSee(route('fitments.show', ['bikeModel' => 'gyro-canopy', 'task' => 'battery']), false)
        ->assertSee(route('fitments.show', ['bikeModel' => 'gyro-canopy', 'task' => 'oil']), false);
});

it('hides the block (no heading) when in-stock models have no published fitment', function () {
    $shop = sflShop();
    $m = sflModel('無適合車', 'no-fitment');
    sflStock($shop, $m); // 適合なし

    $this->get("/shops/{$shop->id}")->assertOk()
        ->assertDontSee('取扱車種の適合パーツ早見表');
});

it('hides the block when the shop has zero stock', function () {
    $shop = sflShop();
    $m = sflModel('在庫なし車', 'no-stock');
    sflVerify($m, 'battery'); // 適合はあるが在庫が無い

    $this->get("/shops/{$shop->id}")->assertOk()
        ->assertDontSee('取扱車種の適合パーツ早見表');
});

it('shows only the published task chips (battery only, no plug/oil)', function () {
    $shop = sflShop();
    $m = sflModel('バッテリーのみ車', 'battery-only');
    sflVerify($m, 'battery'); // battery のみ公開
    sflStock($shop, $m);

    $res = $this->get("/shops/{$shop->id}")->assertOk();
    $res->assertSee(route('fitments.show', ['bikeModel' => 'battery-only', 'task' => 'battery']), false)
        ->assertDontSee(route('fitments.show', ['bikeModel' => 'battery-only', 'task' => 'plug']), false)
        ->assertDontSee(route('fitments.show', ['bikeModel' => 'battery-only', 'task' => 'oil']), false);
});

// ─────────── カニバリ防止 ───────────

it('never leaks part numbers into the shop page (cannibalization guard)', function () {
    $shop = sflShop();
    $m = sflModel('品番テスト車', 'partno-test');
    sflVerify($m, 'battery', 'YTZ7S'); // 品番を持つ
    sflStock($shop, $m);

    $this->get("/shops/{$shop->id}")->assertOk()
        ->assertDontSee('YTZ7S'); // 品番はフィード/店ページに出さない（適合表ページに一本化）
});

// ─────────── 在庫スコープ一致 ───────────

it('excludes models that are only sold-out (inventory scope match)', function () {
    // 売切れ在庫のある店ページは SQLite で描画不可（getShopExpensesStats の DATEDIFF が MySQL専用）。
    // buildShopFitmentLinks が使う在庫スコープ = Listing::active()（is_sold_out=false）が
    // 売切れを除外することをクエリレベルで固定する（表示中在庫に無い＝ブロックに出ない、の根拠）。
    $shop = sflShop();
    $m = sflModel('売切れのみ車', 'soldout-only');
    sflVerify($m, 'battery');
    sflStock($shop, $m, soldOut: true); // 売切れ在庫のみ

    $activeModelIds = \App\Models\Listing::where('shop_id', $shop->id)->active()->pluck('bike_model_id');
    expect($activeModelIds)->not->toContain($m->id); // 表示中在庫に売切れ車種は含まれない
});

// ─────────── サービス単体 ───────────

it('publishedTaskMap returns only verified, slugged model×task', function () {
    $m1 = sflModel('公開車', 'pub-a');
    sflVerify($m1, 'battery');
    sflVerify($m1, 'oil');

    $m2 = sflModel('未検証車', 'unverified-a');
    ModelFitment::create(['bike_model_id' => $m2->id, 'task' => 'battery', 'recommended_part_no' => 'X', 'verified_at' => null]);

    $noSlug = BikeModel::create(['manufacturer_id' => $m1->manufacturer_id, 'name' => 'slug無し車', 'slug' => null]);
    sflVerify($noSlug, 'battery');

    $map = app(\App\Services\Fitment\FitmentSummaryService::class)->publishedTaskMap();

    expect($map[$m1->id])->toContain('battery')->toContain('oil')
        ->and($map)->not->toHaveKey($m2->id)      // 未検証は出ない
        ->and($map)->not->toHaveKey($noSlug->id); // slug無しは出ない（到達不能）
});
