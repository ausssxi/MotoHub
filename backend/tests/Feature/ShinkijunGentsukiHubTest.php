<?php

use App\Models\BikeModel;
use App\Models\Manufacturer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::flush()); // shinkijun_hub_v1 は 1h キャッシュ

function shinkijunModel(Manufacturer $mfr, string $name, string $slug, int $active, int $price = 350000): BikeModel
{
    $model = BikeModel::create(['manufacturer_id' => $mfr->id, 'name' => $name, 'slug' => $slug]);

    $siteId = DB::table('sites')->where('name', 'TestSite')->value('id')
        ?? DB::table('sites')->insertGetId(['name' => 'TestSite', 'created_at' => now(), 'updated_at' => now()]);
    $shopId = DB::table('shops')->insertGetId(['name' => 'テスト店', 'address' => '東京都テスト1-2-3', 'prefecture' => '東京都', 'created_at' => now(), 'updated_at' => now()]);

    $rows = [];
    for ($i = 0; $i < $active; $i++) {
        $rows[] = [
            'site_id' => $siteId, 'shop_id' => $shopId, 'bike_model_id' => $model->id, 'manufacturer_id' => $mfr->id,
            'is_sold_out' => false, 'total_price' => $price, 'mileage' => 60,
            'source_url' => 'https://e.test/sk-'.$model->id.'-'.$i, 'created_at' => now(), 'updated_at' => now(),
        ];
    }
    DB::table('listings')->insert($rows);

    return $model;
}

function shinkijunHonda(): Manufacturer
{
    $m = new Manufacturer(['slug' => 'honda']);
    $m->name = 'ホンダ';
    $m->save();

    return $m;
}

it('serves the shinkijun-gentsuki hub with H1, freshness and target model links', function () {
    $honda = shinkijunHonda();
    shinkijunModel($honda, 'スーパーカブ110 lite', 'super-cub-110-lite', 30, 350000); // 新基準Lite（新古車）
    shinkijunModel($honda, 'スーパーカブ110', 'super-cub-110', 40, 355000);            // 旧110cc中古
    shinkijunModel($honda, 'スーパーカブ50', 'super-cub-50', 20, 296000);              // 旧50cc中古

    $this->get('/shinkijun-gentsuki')
        ->assertOk()
        ->assertSee('新基準原付とは')                          // H1
        ->assertSee('最終更新')                                // 鮮度
        ->assertSee('スーパーカブ110 lite')                    // 対象モデル
        ->assertSee('/bikes/honda/super-cub-110-lite', false); // 車種ページへのスポークリンク
});

it('emits BreadcrumbList, ItemList and FAQPage structured data', function () {
    $honda = shinkijunHonda();
    shinkijunModel($honda, 'クロスカブ110 lite', 'cross-cub-110-lite', 25);

    $this->get('/shinkijun-gentsuki')
        ->assertOk()
        ->assertSee('BreadcrumbList')
        ->assertSee('ItemList')
        ->assertSee('FAQPage')
        ->assertSee('原付二種'); // 原付二種との違い（迷い語）を必ず載せる
});

it('shows the 4-layer real-inventory price comparison from actual stock', function () {
    $honda = shinkijunHonda();
    shinkijunModel($honda, 'スーパーカブ110 lite', 'super-cub-110-lite', 30, 350000);
    shinkijunModel($honda, 'スーパーカブ110', 'super-cub-110', 40, 355000);
    shinkijunModel($honda, 'スーパーカブ50', 'super-cub-50', 20, 296000);

    $this->get('/shinkijun-gentsuki')
        ->assertOk()
        ->assertSee('相場比較')
        ->assertSee('新基準原付（Lite）新古車')
        ->assertSee('原付二種）中古')
        ->assertSee('50cc（原付一種）中古')
        ->assertSee('実在庫30台'); // Lite の実在庫台数（創作でなく集計値）
});

it('does not fabricate stock for target models that have no listings', function () {
    $honda = shinkijunHonda();
    shinkijunModel($honda, 'スーパーカブ110 lite', 'super-cub-110-lite', 30);
    // dio110 lite は config の対象だがモデル自体が存在しない → 掲載しない（在庫◯台と偽らない）
    $this->get('/shinkijun-gentsuki')
        ->assertOk()
        ->assertDontSee('dio110 lite');
});

it('drops a target model that exists but has zero active stock (no fabricated tile)', function () {
    $honda = shinkijunHonda();
    shinkijunModel($honda, 'スーパーカブ110 lite', 'super-cub-110-lite', 12);
    // 在庫ゼロの対象モデルは resolveModel が null で落とす（在庫◯台と偽らない）
    $zero = BikeModel::create(['manufacturer_id' => $honda->id, 'name' => 'クロスカブ110 lite', 'slug' => 'cross-cub-110-lite']);
    expect((int) $zero->listings()->count())->toBe(0);

    // データ層で検証（ページ本文にはグローバル検索ナビが全車種名を出すためHTML文字列一致は使えない）
    $view = app(\App\Http\Controllers\ShinkijunGentsukiController::class)->show();
    $targetNames = collect($view->getData()['targets'])->pluck('name');

    expect($targetNames)->toContain('スーパーカブ110 lite')   // 在庫ありは載る
        ->not->toContain('クロスカブ110 lite');               // 在庫ゼロは対象一覧から除外

    $this->get('/shinkijun-gentsuki')->assertOk();
});

it('registers the shinkijun hub route in the sitemap generator', function () {
    $src = file_get_contents(app_path('Console/Commands/GenerateSitemap.php'));
    expect($src)->toContain("'route' => 'shinkijun_gentsuki'");
});
