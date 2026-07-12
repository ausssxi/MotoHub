<?php

use App\Models\BikeModel;
use App\Models\Manufacturer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::flush()); // areaIndex は 1h キャッシュのため毎テスト新鮮化

function seedKanagawaStock(int $n = 10): BikeModel
{
    $mfr = new Manufacturer(['slug' => 'honda']);
    $mfr->name = 'ホンダ';
    $mfr->save();

    $model = BikeModel::create(['manufacturer_id' => $mfr->id, 'name' => 'エリアテスト車', 'slug' => 'area-test']);

    $siteId = DB::table('sites')->where('name', 'TestSite')->value('id')
        ?? DB::table('sites')->insertGetId(['name' => 'TestSite', 'created_at' => now(), 'updated_at' => now()]);
    $shopId = DB::table('shops')->insertGetId(['name' => '神奈川テスト店', 'address' => '神奈川県横浜市1-2-3', 'prefecture' => '神奈川県', 'created_at' => now(), 'updated_at' => now()]);

    $rows = [];
    for ($i = 0; $i < $n; $i++) {
        $rows[] = [
            'site_id' => $siteId, 'shop_id' => $shopId, 'bike_model_id' => $model->id, 'manufacturer_id' => $mfr->id,
            'is_sold_out' => false, 'total_price' => 300000,
            'source_url' => 'https://e.test/area-'.$i, 'created_at' => now(), 'updated_at' => now(),
        ];
    }
    DB::table('listings')->insert($rows);

    return $model;
}

it('renders the area index with 中古-framed freshness title and live count', function () {
    seedKanagawaStock(10);

    $this->get('/bikes/area/神奈川')
        ->assertOk()
        ->assertSee('相場・在庫を毎日更新')   // title の鮮度シグナル（中古主軸）
        ->assertSee('最終更新');              // ライブ鮮度表示
});

it('emits BreadcrumbList and ItemList structured data on the area index', function () {
    seedKanagawaStock(10);

    $res = $this->get('/bikes/area/神奈川')->assertOk();
    $res->assertSee('BreadcrumbList')
        ->assertSee('ItemList')
        ->assertSee('エリアテスト車'); // ItemList に人気車種（striking-distance landing への内部リンク）
});

it('shows neighbouring-prefecture hub links (same region)', function () {
    seedKanagawaStock(10);

    // 神奈川は関東ブロック → 東京など同地方の他県リンクが出る（ハブ&スポーク）
    $this->get('/bikes/area/神奈川')
        ->assertOk()
        ->assertSee('近隣エリアの中古バイク')
        ->assertSee('/bikes/area/'.rawurlencode('東京'), false);
});

it('registers prefecture top pages in the sitemap generator', function () {
    $src = file_get_contents(app_path('Console/Commands/GenerateSitemap.php'));

    expect($src)->toContain("route('bikes.area_index', \$pref)") // 県トップをサイトマップに明示登録
        ->toContain('$prefTotals');                              // 10台以上ガード
});
