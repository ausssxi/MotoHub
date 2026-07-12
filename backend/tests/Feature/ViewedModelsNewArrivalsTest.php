<?php

use App\Models\BikeModel;
use App\Models\Manufacturer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function vmMfr(string $slug, string $name): Manufacturer
{
    $m = new Manufacturer(['slug' => $slug]);
    $m->name = $name;
    $m->save();

    return $m;
}

function vmModel(Manufacturer $mfr, string $name, string $slug): BikeModel
{
    return BikeModel::create(['manufacturer_id' => $mfr->id, 'name' => $name, 'slug' => $slug]);
}

function vmStock(BikeModel $model, int $active, int $sold = 0): void
{
    $siteId = DB::table('sites')->where('name', 'TestSite')->value('id')
        ?? DB::table('sites')->insertGetId(['name' => 'TestSite', 'created_at' => now(), 'updated_at' => now()]);

    $rows = [];
    for ($i = 0; $i < $active + $sold; $i++) {
        $rows[] = [
            'site_id' => $siteId,
            'bike_model_id' => $model->id,
            'is_sold_out' => $i >= $active,
            'total_price' => 300000,
            'source_url' => 'https://e.test/'.$model->id.'-'.$i,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
    DB::table('listings')->insert($rows);
}

// ─────────── 在庫数バッチ取得エンドポイント ───────────

it('returns live active stock counts keyed by mfrSlug/modelSlug, excluding sold-out', function () {
    $honda = vmMfr('honda', 'ホンダ');
    $yamaha = vmMfr('yamaha', 'ヤマハ');
    vmStock(vmModel($honda, 'レブル250', 'rebel-250'), active: 3, sold: 1);
    vmStock(vmModel($yamaha, 'YZF-R25', 'yzf-r25'), active: 1);

    $this->getJson('/api/bikes/viewed-stock?models=honda/rebel-250,yamaha/yzf-r25')
        ->assertOk()
        ->assertExactJson(['honda/rebel-250' => 3, 'yamaha/yzf-r25' => 1]);
});

it('includes resolved models with zero stock and omits unknown slugs', function () {
    $honda = vmMfr('honda', 'ホンダ');
    vmModel($honda, 'レブル250', 'rebel-250'); // 在庫0

    $this->getJson('/api/bikes/viewed-stock?models=honda/rebel-250,honda/does-not-exist')
        ->assertOk()
        ->assertExactJson(['honda/rebel-250' => 0]);
});

it('does not leak counts across manufacturers sharing a model slug', function () {
    $honda = vmMfr('honda', 'ホンダ');
    $yamaha = vmMfr('yamaha', 'ヤマハ');
    // 名前は bike_models.name がグローバルユニークのため別名にするが、slug は両者とも 'x'（メーカー内ユニーク）
    vmStock(vmModel($honda, 'HondaX', 'x'), active: 5);
    vmStock(vmModel($yamaha, 'YamahaX', 'x'), active: 2);

    // honda/x のみ要求 → yamaha/x の在庫は混入しない
    $this->getJson('/api/bikes/viewed-stock?models=honda/x')
        ->assertOk()
        ->assertExactJson(['honda/x' => 5]);
});

it('caps processing at 10 models', function () {
    $honda = vmMfr('honda', 'ホンダ');
    $slugs = [];
    for ($i = 1; $i <= 12; $i++) {
        vmStock(vmModel($honda, 'M'.$i, 'm-'.$i), active: 1);
        $slugs[] = 'honda/m-'.$i;
    }

    $res = $this->getJson('/api/bikes/viewed-stock?models='.implode(',', $slugs))->assertOk();
    expect(count($res->json()))->toBeLessThanOrEqual(10);
});

it('uses a constant number of queries regardless of model count (no N+1)', function () {
    $honda = vmMfr('honda', 'ホンダ');
    for ($i = 1; $i <= 6; $i++) {
        vmStock(vmModel($honda, 'M'.$i, 'm-'.$i), active: 1);
    }

    DB::enableQueryLog();
    $this->getJson('/api/bikes/viewed-stock?models=honda/m-1,honda/m-2')->assertOk();
    $q2 = count(DB::getQueryLog());
    DB::flushQueryLog();
    $this->getJson('/api/bikes/viewed-stock?models=honda/m-1,honda/m-2,honda/m-3,honda/m-4,honda/m-5,honda/m-6')->assertOk();
    $q6 = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($q6)->toBe($q2); // 車種数が2→6でもクエリ本数は不変
});

it('returns empty when no models are given', function () {
    $this->getJson('/api/bikes/viewed-stock')->assertOk()->assertExactJson([]);
});

// ─────────── 記録/表示の配線（静的） ───────────

it('wires the widget on the home page and the recorder on the model page', function () {
    $home = file_get_contents(resource_path('views/bikes/index.blade.php'));
    expect($home)->toContain('id="viewed-models-widget"')
        ->toContain('id="viewed-models-section"')
        ->toContain('id="viewed-models-clear"')   // 履歴クリア導線
        ->toContain('js/viewed-models.js');

    $detail = file_get_contents(resource_path('views/bikes/model_detail.blade.php'));
    expect($detail)->toContain('window.__viewedModel')  // 記録用データ埋め込み
        ->toContain('js/viewed-models.js');
});
