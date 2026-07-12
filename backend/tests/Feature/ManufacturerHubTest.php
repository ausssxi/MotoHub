<?php

use App\Models\BikeModel;
use App\Models\Manufacturer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::flush()); // manufacturer_hub は 1h キャッシュ

function hubManufacturer(string $slug = 'harley-davidson', string $name = 'ハーレーダビッドソン'): Manufacturer
{
    $m = new Manufacturer(['slug' => $slug]);
    $m->name = $name;
    $m->save();

    return $m;
}

function hubModelWithStock(Manufacturer $mfr, string $name, string $slug, int $active): BikeModel
{
    $model = BikeModel::create(['manufacturer_id' => $mfr->id, 'name' => $name, 'slug' => $slug]);

    $siteId = DB::table('sites')->where('name', 'TestSite')->value('id')
        ?? DB::table('sites')->insertGetId(['name' => 'TestSite', 'created_at' => now(), 'updated_at' => now()]);
    $shopId = DB::table('shops')->insertGetId(['name' => 'テスト店', 'address' => '東京都テスト1-2-3', 'prefecture' => '東京都', 'created_at' => now(), 'updated_at' => now()]);

    $rows = [];
    for ($i = 0; $i < $active; $i++) {
        $rows[] = [
            'site_id' => $siteId, 'shop_id' => $shopId, 'bike_model_id' => $model->id, 'manufacturer_id' => $mfr->id,
            'is_sold_out' => false, 'total_price' => 800000,
            'source_url' => 'https://e.test/hub-'.$model->id.'-'.$i, 'created_at' => now(), 'updated_at' => now(),
        ];
    }
    DB::table('listings')->insert($rows);

    return $model;
}

// ─────────── メーカーハブ（404解消・厚いハブ） ───────────

it('serves a clean indexable manufacturer hub (was 404) with count title and freshness', function () {
    $hd = hubManufacturer();
    hubModelWithStock($hd, 'fxsts スプリンガーソフテイル', 'fxsts', 8);
    hubModelWithStock($hd, 'flstfb ファットボーイロー', 'flstfb', 6);

    $this->get('/bikes/harley-davidson')
        ->assertOk()
        ->assertSee('ハーレーダビッドソンの中古バイク一覧【14台】相場・人気モデル')  // 中古主軸title＋件数
        ->assertSee('最終更新')                                                    // 鮮度
        ->assertSee('fxsts');                                                      // スポークへの内部リンク
});

it('emits BreadcrumbList and ItemList structured data on the hub', function () {
    $hd = hubManufacturer();
    hubModelWithStock($hd, 'fxst ソフテイル', 'fxst', 12);

    $this->get('/bikes/harley-davidson')
        ->assertOk()
        ->assertSee('BreadcrumbList')
        ->assertSee('ItemList')
        ->assertSee('/bikes/harley-davidson/fxst', false); // ItemList/タイルが車種ページを指す
});

it('404s for an unknown manufacturer slug (defensive catch-all)', function () {
    $this->get('/bikes/does-not-exist-maker')->assertNotFound();
});

it('does not shadow numeric or literal /bikes routes', function () {
    // 英字1セグは maker hub、数字は show、リテラルは各自 → ルート解決の相互排他
    expect(route('bikes.manufacturer_hub', ['makerSlug' => 'harley-davidson']))->toEndWith('/bikes/harley-davidson');

    $this->get('/bikes/models')->assertOk();  // リテラルは shadow されない
});

// ─────────── パンくず張替（モデル→メーカーハブ） ───────────

it('repoints the model breadcrumb (html + json-ld) to the manufacturer hub', function () {
    $modelDetail = file_get_contents(resource_path('views/bikes/model_detail.blade.php'));
    $jsonld = file_get_contents(resource_path('views/components/jsonld/breadcrumb-model.blade.php'));

    // 両方が manufacturer_hub を指し、slug無しは検索URLへフォールバック
    expect($modelDetail)->toContain("route('bikes.manufacturer_hub'")
        ->and($jsonld)->toContain("route('bikes.manufacturer_hub'")
        ->and($jsonld)->toContain('$model->manufacturer->slug'); // フォールバック分岐
});

it('registers manufacturer hubs in the sitemap generator', function () {
    $src = file_get_contents(app_path('Console/Commands/GenerateSitemap.php'));

    expect($src)->toContain("route('bikes.manufacturer_hub'")
        ->toContain('$makerCounts');
});
