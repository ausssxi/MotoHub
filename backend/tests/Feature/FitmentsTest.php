<?php

use App\Models\BikeModel;
use App\Models\Manufacturer;
use App\Models\ModelFitment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

function fitModel(string $name, ?string $slug = null): BikeModel
{
    $mfr = Manufacturer::firstWhere('slug', 'test-mfr');
    if (! $mfr) {
        $mfr = new Manufacturer(['slug' => 'test-mfr']); // name は fillable でないため直接代入
        $mfr->name = 'テストメーカー';
        $mfr->save();
    }

    return BikeModel::create(['manufacturer_id' => $mfr->id, 'name' => $name, 'slug' => $slug]);
}

function fitCsv(array $dataRows): string
{
    $header = 'bike_model_id,model_name_check,model_slug,task,frame_code,year_range,oem_part_no,recommended_part_no,compatibles,spec,source_1_name,source_1_url,source_2_name,source_2_url,verified_at,note';
    $path = tempnam(sys_get_temp_dir(), 'fit').'.csv';
    file_put_contents($path, $header."\n".implode("\n", $dataRows)."\n");

    return $path;
}

function fitVerified(BikeModel $m, array $attrs = []): ModelFitment
{
    return ModelFitment::create(array_merge([
        'bike_model_id' => $m->id, 'task' => 'battery', 'frame_code' => 'AF00', 'year_range' => '03〜07',
        'recommended_part_no' => 'TEST-0000', 'verified_at' => '2026-07-01',
    ], $attrs));
}

// ─────────── 公開ゲート ───────────

it('404s when the model has no verified fitment (slug exists but data unverified)', function () {
    fitModel('未検証モデル', 'unverified-model');

    $this->get('/maintenance/unverified-model/battery')->assertNotFound();
});

it('renders 200 with part numbers and frame codes when verified rows exist', function () {
    $m = fitModel('テスト車', 'test-bike');
    fitVerified($m, ['frame_code' => 'AF62', 'recommended_part_no' => 'TEST-0000', 'oem_part_no' => 'TEST-OEM0']);

    $this->get('/maintenance/test-bike/battery')
        ->assertOk()
        ->assertSee('TEST-0000')
        ->assertSee('AF62');
});

it('hides rows with a null verified_at', function () {
    $m = fitModel('混在モデル', 'mixed-model');
    fitVerified($m, ['frame_code' => 'AF01', 'recommended_part_no' => 'TEST-SHOWN']);
    ModelFitment::create(['bike_model_id' => $m->id, 'task' => 'battery', 'frame_code' => 'AF02',
        'recommended_part_no' => 'TEST-HIDDEN', 'verified_at' => null]);

    $res = $this->get('/maintenance/mixed-model/battery')->assertOk();
    $res->assertSee('TEST-SHOWN');
    $res->assertDontSee('TEST-HIDDEN');
});

it('404s for an unsupported task (route whereIn)', function () {
    $m = fitModel('プラグ未対応', 'plug-model');
    fitVerified($m);

    $this->get('/maintenance/plug-model/plug')->assertNotFound();
});

// ─────────── import ───────────

it('dry-run does not change the database', function () {
    fitModel('テストモデル'); // fixture の bike_model_id=1・name一致

    $code = Artisan::call('fitments:import', ['path' => base_path('tests/fixtures/fitments_test.csv'), '--dry-run' => true]);

    expect($code)->toBe(0)
        ->and(ModelFitment::count())->toBe(0)
        ->and(BikeModel::find(1)->slug)->toBeNull(); // slug も設定されない
});

it('is idempotent: re-running the same CSV yields no diff', function () {
    fitModel('テストモデル');
    $csv = base_path('tests/fixtures/fitments_test.csv');

    Artisan::call('fitments:import', ['path' => $csv]);
    $after1 = ModelFitment::count();
    Artisan::call('fitments:import', ['path' => $csv]);

    expect($after1)->toBe(1)
        ->and(ModelFitment::count())->toBe(1)
        ->and(BikeModel::find(1)->slug)->toBe('test-model');
});

it('parses compatibles and spec mini-notation into JSON', function () {
    fitModel('テストモデル');
    Artisan::call('fitments:import', ['path' => base_path('tests/fixtures/fitments_test.csv')]);

    $f = ModelFitment::first();
    expect($f->compatible_part_nos)->toBe([
        ['brand' => '台湾ユアサ', 'part_no' => 'TEST-0001'],
        ['brand' => '古河電池', 'part_no' => 'TEST-0002'],
    ])->and($f->spec)->toBe(['voltage' => '12V', 'capacity' => '4Ah', 'type' => 'VRLA']);
});

it('skips a row whose model_name_check does not match', function () {
    fitModel('正しい名前', 'correct-slug');
    $csv = fitCsv(['1,間違った名前,x-slug,battery,AF00,,,TEST-0000,,,,,,,2026-07-01,']);

    $code = Artisan::call('fitments:import', ['path' => $csv]);

    expect($code)->toBe(2)                    // 警告ありは非ゼロ
        ->and(ModelFitment::count())->toBe(0);
});

it('full-replaces a model/task group (frame codes absent from CSV disappear)', function () {
    $m = fitModel('置換モデル', 'replace-model');
    // 先にAF00を投入
    Artisan::call('fitments:import', ['path' => fitCsv(["{$m->id},置換モデル,replace-model,battery,AF00,,,TEST-0000,,,,,,,2026-07-01,"])]);
    expect(ModelFitment::where('frame_code', 'AF00')->exists())->toBeTrue();

    // AF11 のみのCSVで再取り込み → AF00 は消える
    Artisan::call('fitments:import', ['path' => fitCsv(["{$m->id},置換モデル,replace-model,battery,AF11,,,TEST-1111,,,,,,,2026-07-01,"])]);

    expect(ModelFitment::where('frame_code', 'AF00')->exists())->toBeFalse()
        ->and(ModelFitment::where('frame_code', 'AF11')->exists())->toBeTrue()
        ->and(ModelFitment::count())->toBe(1);
});

it('does not overwrite an existing different slug (warns instead)', function () {
    $m = fitModel('既存slugモデル', 'existing-slug');
    $csv = fitCsv(["{$m->id},既存slugモデル,new-slug,battery,AF00,,,TEST-0000,,,,,,,2026-07-01,"]);

    $code = Artisan::call('fitments:import', ['path' => $csv]);

    expect($code)->toBe(2)                            // 警告あり
        ->and($m->fresh()->slug)->toBe('existing-slug') // 上書きされない
        ->and(ModelFitment::count())->toBe(1);          // fitment自体は取り込む
});

// ─────────── plug task（Phase2）───────────

it('serves a plug page (200 verified / 404 unverified) via the whereIn route', function () {
    $m = fitModel('プラグ車', 'plug-bike');
    fitVerified($m, ['task' => 'plug', 'recommended_part_no' => 'TEST-PLUG0']);

    $this->get('/maintenance/plug-bike/plug')->assertOk()->assertSee('TEST-PLUG0');

    $m2 = fitModel('プラグ未検証', 'plug-unverified');
    ModelFitment::create(['bike_model_id' => $m2->id, 'task' => 'plug', 'recommended_part_no' => 'TEST-X', 'verified_at' => null]);
    $this->get('/maintenance/plug-unverified/plug')->assertNotFound();
});

it('shows plug spec labels (熱価/本数) and the plug H1 label', function () {
    $m = fitModel('熱価車', 'heat-bike');
    fitVerified($m, ['task' => 'plug', 'recommended_part_no' => 'TEST-PLUG0', 'spec' => ['heat' => '6', 'plugs' => '2']]);

    $res = $this->get('/maintenance/heat-bike/plug')->assertOk();
    $res->assertSee('プラグ型番と交換方法');     // task ラベル差し込み
    $res->assertSee('熱価: 6番');
    $res->assertSee('必要本数: 2本');
});

it('renders two price buttons when compatibles exist, one when not', function () {
    // 互換あり → 標準＋ブランドの2ボタン
    $m1 = fitModel('2ボタン車', 'two-btn');
    fitVerified($m1, ['recommended_part_no' => 'TEST-STD', 'compatible_part_nos' => [['brand' => 'テスト上位', 'part_no' => 'TEST-DX']]]);
    $res1 = $this->get('/maintenance/two-btn/battery')->assertOk();
    $res1->assertSee('価格を比較');
    $res1->assertSee('テスト上位の価格を比較');

    // 互換なし → 1ボタン（ブランドボタンは出ない）
    $m2 = fitModel('1ボタン車', 'one-btn');
    fitVerified($m2, ['recommended_part_no' => 'TEST-STD', 'compatible_part_nos' => null]);
    $res2 = $this->get('/maintenance/one-btn/battery')->assertOk();
    $res2->assertSee('価格を比較');
    $res2->assertDontSee('の価格を比較'); // battery primary は「価格を比較」なので二次ボタンのみが持つ語
});

it('uses the standard-plug primary label on plug pages', function () {
    $m = fitModel('標準ラベル車', 'std-label');
    fitVerified($m, ['task' => 'plug', 'recommended_part_no' => 'TEST-PLUG0']);

    $this->get('/maintenance/std-label/plug')->assertOk()->assertSee('標準プラグの価格を比較');
});

it('exposes published plug models to the diagnosis (plug card fitment_task)', function () {
    Illuminate\Support\Facades\Cache::flush();
    expect(config('diagnosis.cards.plug.fitment_task'))->toBe('plug');

    $m = fitModel('診断プラグ車', 'diag-plug');
    fitVerified($m, ['task' => 'plug', 'recommended_part_no' => 'TEST-PLUG0']);

    $this->get('/trouble')->assertOk()->assertSee('"plug":[{"slug":"diag-plug"', false);
});

it('regression: battery page still renders with the 2-button change', function () {
    $m = fitModel('回帰バッテリー車', 'reg-batt');
    fitVerified($m, ['recommended_part_no' => 'TEST-0000', 'frame_code' => 'AF62']);

    $this->get('/maintenance/reg-batt/battery')->assertOk()
        ->assertSee('TEST-0000')->assertSee('AF62')->assertSee('価格を比較');
});
