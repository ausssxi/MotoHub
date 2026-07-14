<?php

use App\Models\BikeModel;
use App\Models\Manufacturer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::flush());

function ilHonda(): Manufacturer
{
    $m = new Manufacturer(['slug' => 'honda']);
    $m->name = 'ホンダ';
    $m->save();

    return $m;
}

function ilModel(Manufacturer $mfr, string $name, string $slug): BikeModel
{
    return BikeModel::create(['manufacturer_id' => $mfr->id, 'name' => $name, 'slug' => $slug]);
}

// ───────── モデルページの相互リンク partial ─────────

it('shows the "this is a shinkijun" banner on a target (Lite) model page', function () {
    $m = ilModel(ilHonda(), 'スーパーカブ110 lite', 'super-cub-110-lite');
    $html = view('bikes.partials.shinkijun-link', ['model' => $m])->render();

    expect($html)->toContain('このモデルは新基準原付です')
        ->toContain(route('shinkijun_gentsuki'));
});

it('shows the "which models are shinkijun" banner on a base/old-50cc model page', function () {
    $honda = ilHonda();
    $base = ilModel($honda, 'スーパーカブ110', 'super-cub-110');
    $old = ilModel($honda, 'スーパーカブ50', 'super-cub-50');

    foreach ([$base, $old] as $m) {
        $html = view('bikes.partials.shinkijun-link', ['model' => $m])->render();
        expect($html)->toContain('新基準原付として乗れるモデルは？')
            ->toContain(route('shinkijun_gentsuki'));
    }
});

it('renders nothing on an unrelated model page (no false link)', function () {
    $m = ilModel(ilHonda(), 'CBR1000RR', 'cbr1000rr');
    $html = trim(view('bikes.partials.shinkijun-link', ['model' => $m])->render());

    expect($html)->toBe('')
        ->and($html)->not->toContain('shinkijun-gentsuki');
});

it('wires the partial into the model detail overview tab', function () {
    $src = file_get_contents(resource_path('views/bikes/model_detail.blade.php'));
    expect($src)->toContain("@include('bikes.partials.shinkijun-link'");
});

// ───────── 排気量別一覧（/bikes/cc/50・/125）→ ハブ ─────────

it('links the 50cc and 125cc displacement pages to the hub, but not 250cc', function () {
    $hubUrl = route('shinkijun_gentsuki');

    $this->get(route('bikes.category_cc', ['slug' => '50']))->assertOk()->assertSee($hubUrl);
    $this->get(route('bikes.category_cc', ['slug' => '125']))->assertOk()->assertSee($hubUrl);
    // 250cc は新基準原付と無関係なので誘導しない
    $this->get(route('bikes.category_cc', ['slug' => '250']))->assertOk()->assertDontSee('新基準原付の対象モデル・相場はこちら');
});

// ───────── /ranking → ハブ ─────────

it('links the ranking page to the hub', function () {
    $this->get('/ranking')->assertOk()->assertSee(route('shinkijun_gentsuki'));
});

// ───────── ハブの4層比較 → 排気量別一覧（相互リンクを閉じる） ─────────

it('links the comparison rows back to the displacement pages', function () {
    $honda = ilHonda();
    // base(110cc) と old50 に在庫を持たせて比較行を出す
    foreach ([['スーパーカブ110', 'super-cub-110'], ['スーパーカブ50', 'super-cub-50']] as [$name, $slug]) {
        $model = ilModel($honda, $name, $slug);
        $siteId = DB::table('sites')->where('name', 'TS')->value('id') ?? DB::table('sites')->insertGetId(['name' => 'TS', 'created_at' => now(), 'updated_at' => now()]);
        $shopId = DB::table('shops')->insertGetId(['name' => '店', 'address' => '東京都1', 'prefecture' => '東京都', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('listings')->insert([
            'site_id' => $siteId, 'shop_id' => $shopId, 'bike_model_id' => $model->id, 'manufacturer_id' => $honda->id,
            'is_sold_out' => false, 'total_price' => 300000, 'source_url' => 'https://e.test/'.$slug, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    $this->get('/shinkijun-gentsuki')
        ->assertOk()
        ->assertSee(route('bikes.category_cc', ['slug' => '125']), false)
        ->assertSee(route('bikes.category_cc', ['slug' => '50']), false);
});
