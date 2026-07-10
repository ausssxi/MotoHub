<?php

use App\Models\BikeModel;
use App\Models\Manufacturer;
use App\Models\SeoCompare;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

function cmpModel(string $name, string $slug): BikeModel
{
    $mfr = Manufacturer::firstWhere('slug', 'honda') ?? tap(new Manufacturer(['slug' => 'honda']), function ($m) {
        $m->name = 'ホンダ';
        $m->save();
    });

    return BikeModel::create(['manufacturer_id' => $mfr->id, 'name' => $name, 'slug' => $slug]);
}

function cmpPair(BikeModel $a, BikeModel $b, string $slug, bool $active = true): SeoCompare
{
    return SeoCompare::create([
        'model1_id' => $a->id, 'model2_id' => $b->id,
        'slug' => $slug, 'is_active' => $active, 'sort_order' => 0,
    ]);
}

// ─────────── 優先1: model_detail → 関連比較（注入クエリ・出し分け） ───────────

it('finds active compares that include a given model (both sides of the pair)', function () {
    $a = cmpModel('レブル250', 'rebel-250');
    $b = cmpModel('GB350', 'gb350');
    cmpPair($a, $b, 'rebel-250-vs-gb350');

    // model_detail が注入する relatedCompares と同じクエリ
    $forA = SeoCompare::active()->where(fn ($q) => $q->where('model1_id', $a->id)->orWhere('model2_id', $a->id))->get();
    $forB = SeoCompare::active()->where(fn ($q) => $q->where('model1_id', $b->id)->orWhere('model2_id', $b->id))->get();
    expect($forA)->toHaveCount(1)->and($forB)->toHaveCount(1); // どちら側の車種からも引ける
});

it('returns no compares for a model that is in no pair, and excludes inactive pairs', function () {
    $a = cmpModel('孤立車', 'lonely');
    $b = cmpModel('相方', 'partner');
    cmpPair($a, $b, 'lonely-vs-partner', active: false); // 非アクティブ

    $none = SeoCompare::active()->where(fn ($q) => $q->where('model1_id', $a->id)->orWhere('model2_id', $a->id))->get();
    expect($none)->toHaveCount(0); // 非アクティブは出さない＝セクション非表示
});

it('model_detail blade gates the compare section on relatedCompares and links to the hub', function () {
    $blade = file_get_contents(resource_path('views/bikes/model_detail.blade.php'));
    expect($blade)->toContain('$relatedCompares')            // 出し分けガード
        ->toContain('の比較')                                // セクション見出し
        ->toContain("route('bikes.model_compare_hub')");     // ハブへの導線
});

// ─────────── 優先3: 比較ページ → 車種詳細（逆方向リンク） ───────────

it('shows reverse links from the compare page to each model detail', function () {
    $a = cmpModel('レブル250', 'rebel-250');
    $b = cmpModel('GB350', 'gb350');
    cmpPair($a, $b, 'rebel-250-vs-gb350');

    $res = $this->get('/bikes/compare/rebel-250-vs-gb350')->assertOk();
    $res->assertSee($a->seo_url, false)   // model1 の詳細へ
        ->assertSee($b->seo_url, false)   // model2 の詳細へ
        ->assertSee('の詳細');
});

// ─────────── 優先2: ハブ入口（フッター・sitemap・ルート） ───────────

it('links to the compare hub from the footer', function () {
    // フッターを持つ任意の描画可能ページ
    $this->get('/parking/area')->assertOk()
        ->assertSee(route('bikes.model_compare_hub'), false)
        ->assertSee('バイク車種を比較する');
});

it('exposes the compare hub route and it renders', function () {
    expect(Route::has('bikes.model_compare_hub'))->toBeTrue();
    $this->get('/bikes/compare')->assertOk();
});

// ─────────── リンク先が実在（404を作らない） ───────────

it('points related-compare links at existing compare pages', function () {
    $a = cmpModel('レブル250', 'rebel-250');
    $b = cmpModel('GB350', 'gb350');
    $pair = cmpPair($a, $b, 'rebel-250-vs-gb350');

    expect($pair->url)->toBe(route('bikes.model_compare', 'rebel-250-vs-gb350'));
    $this->get('/bikes/compare/rebel-250-vs-gb350')->assertOk(); // 404 を作らない
});
