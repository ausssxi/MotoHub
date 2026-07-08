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

// ─────────── oil task（Phase3）───────────

it('serves an oil page (200 verified / 404 unverified) via the whereIn route', function () {
    $m = fitModel('オイル車', 'oil-bike');
    fitVerified($m, ['task' => 'oil', 'recommended_part_no' => '10W-30', 'spec' => ['jaso' => 'MB', 'capacity_change' => '0.8L']]);

    $this->get('/maintenance/oil-bike/oil')->assertOk()->assertSee('10W-30');

    $m2 = fitModel('オイル未検証', 'oil-unverified');
    ModelFitment::create(['bike_model_id' => $m2->id, 'task' => 'oil', 'recommended_part_no' => '10W-40', 'verified_at' => null]);
    $this->get('/maintenance/oil-unverified/oil')->assertNotFound();
});

it('shows oil H1, viscosity, JASO, change capacity and interval', function () {
    $m = fitModel('粘度車', 'visc-bike');
    fitVerified($m, ['task' => 'oil', 'recommended_part_no' => '10W-30',
        'spec' => ['jaso' => 'MB', 'capacity_change' => '0.8L', 'interval' => '6000km毎']]);

    $res = $this->get('/maintenance/visc-bike/oil')->assertOk();
    $res->assertSee('エンジンオイル（粘度・量）【型式別】'); // H1
    $res->assertSee('10W-30');       // 推奨粘度
    $res->assertSee('MB');           // JASO 列
    $res->assertSee('0.8L');         // 交換量 列
    $res->assertSee('交換目安: 6000km毎'); // 規格行
});

it('shows filter/strainer capacity labels only when those keys exist', function () {
    $mf = fitModel('フィルタ車', 'filter-bike');
    fitVerified($mf, ['task' => 'oil', 'recommended_part_no' => '10W-30', 'spec' => ['jaso' => 'MA', 'capacity_change' => '0.8L', 'capacity_filter' => '0.85L']]);
    $this->get('/maintenance/filter-bike/oil')->assertOk()->assertSee('フィルター交換時: 0.85L');

    $ms = fitModel('ストレーナ車', 'strainer-bike');
    fitVerified($ms, ['task' => 'oil', 'recommended_part_no' => '10W-30', 'spec' => ['jaso' => 'MB', 'capacity_change' => '0.7L', 'capacity_strainer' => '0.75L']]);
    $this->get('/maintenance/strainer-bike/oil')->assertOk()->assertSee('ストレーナー清掃時: 0.75L');
});

it('renders two oil price buttons (viscosity + genuine brand)', function () {
    $m = fitModel('2ボタンオイル車', 'oil-two-btn');
    fitVerified($m, ['task' => 'oil', 'recommended_part_no' => '10W-30',
        'compatible_part_nos' => [['brand' => 'ホンダ', 'part_no' => 'ウルトラE1']]]);

    $res = $this->get('/maintenance/oil-two-btn/oil')->assertOk();
    $res->assertSee('10W-30のオイルを比較');      // 標準（粘度）
    $res->assertSee('ホンダ ウルトラE1を比較');   // 純正銘柄フルネーム
});

it('connects the oil diagnosis card to the oil fitment page', function () {
    // 「オイル不足・劣化」カードから oil 適合ページへ導線（battery/plug と同型）。
    expect(config('diagnosis.cards.oil.fitment_task'))->toBe('oil')
        ->and(config('fitments.tasks.oil.label'))->toBe('エンジンオイル')
        // oil タスク自体は逆リンク（診断への戻り症状）を持たない設定は不変
        ->and(config('fitments.tasks.oil.trouble_symptom'))->toBeNull();
});

it('exposes published oil models to the diagnosis (oil card fitment_task)', function () {
    Illuminate\Support\Facades\Cache::flush();
    expect(config('diagnosis.cards.oil.fitment_task'))->toBe('oil');

    $m = fitModel('診断オイル車', 'diag-oil');
    fitVerified($m, ['task' => 'oil', 'recommended_part_no' => '10W-30']);

    // fitment グローバルに oil 公開車種が乗る＝結果画面のCTAが /maintenance/{slug}/oil に解決する
    $this->get('/trouble')->assertOk()->assertSee('"oil":[{"slug":"diag-oil"', false);
});

// ─────────── note 分離（公開/内部）───────────

it('imports note_public and note_internal from the new CSV format', function () {
    $m = fitModel('新形式車', 'newfmt');
    $header = 'bike_model_id,model_name_check,model_slug,task,frame_code,year_range,oem_part_no,recommended_part_no,compatibles,spec,source_1_name,source_1_url,source_2_name,source_2_url,verified_at,note_public,note_internal';
    $path = tempnam(sys_get_temp_dir(), 'fit').'.csv';
    file_put_contents($path, $header."\n{$m->id},新形式車,newfmt,battery,AF00,,,TEST-0000,,,,,,,2026-07-01,公開OK,内部NG\n");

    Artisan::call('fitments:import', ['path' => $path]);

    $f = ModelFitment::first();
    expect($f->note_public)->toBe('公開OK')
        ->and($f->note_internal)->toBe('内部NG')
        ->and($f->note)->toBeNull(); // 旧列は書かない
});

it('routes a legacy note-only CSV to note_internal (not exposed publicly)', function () {
    $m = fitModel('旧形式車', 'oldfmt');
    Artisan::call('fitments:import', ['path' => fitCsv(["{$m->id},旧形式車,oldfmt,battery,AF00,,,TEST-0000,,,,,,,2026-07-01,旧内部メモ"])]);

    $f = ModelFitment::first();
    expect($f->note_internal)->toBe('旧内部メモ')  // 旧 note は内部へ退避
        ->and($f->note_public)->toBeNull();          // 公開には漏れない
});

it('shows note_public on the page but never note_internal', function () {
    $m = fitModel('備考車', 'note-bike');
    fitVerified($m, ['note_public' => '公開する備考', 'note_internal' => '内部メモ秘密']);

    $res = $this->get('/maintenance/note-bike/battery')->assertOk();
    $res->assertSee('公開する備考');
    $res->assertDontSee('内部メモ秘密'); // 内部メモは絶対に露出しない
});

// ─────────── oil 交換費用の目安（cost 4列）───────────

it('imports the 4 cost columns from the new CSV format (empty→null)', function () {
    $m = fitModel('費用CSV車', 'cost-csv');
    $header = 'bike_model_id,model_name_check,model_slug,task,frame_code,year_range,oem_part_no,recommended_part_no,compatibles,spec,source_1_name,source_1_url,source_2_name,source_2_url,verified_at,note_public,note_internal,cost_oil_range,cost_shop_range,cost_diy_range,cost_updated_at';
    $path = tempnam(sys_get_temp_dir(), 'fit').'.csv';
    file_put_contents($path, $header."\n{$m->id},費用CSV車,cost-csv,oil,AF00,,,10W-30,,,,,,,2026-07-01,,,900〜1200円,2500〜3500円,,2026-07\n");

    Artisan::call('fitments:import', ['path' => $path]);

    $f = ModelFitment::first();
    expect($f->cost_oil_range)->toBe('900〜1200円')
        ->and($f->cost_shop_range)->toBe('2500〜3500円')
        ->and($f->cost_diy_range)->toBeNull()     // 空セル→NULL
        ->and($f->cost_updated_at)->toBe('2026-07');
});

it('imports a legacy CSV without cost columns leaving costs null (backward compat)', function () {
    $m = fitModel('旧CSV費用', 'oldcost');
    Artisan::call('fitments:import', ['path' => fitCsv(["{$m->id},旧CSV費用,oldcost,oil,AF00,,,10W-30,,,,,,,2026-07-01,備考"])]);

    $f = ModelFitment::first();
    expect($f->cost_oil_range)->toBeNull()->and($f->cost_shop_range)->toBeNull()
        ->and($f->recommended_part_no)->toBe('10W-30'); // 既存挙動不変
});

it('shows the oil cost block with ranges, freshness and disclaimer', function () {
    $m = fitModel('費用車', 'cost-bike');
    fitVerified($m, ['task' => 'oil', 'recommended_part_no' => '10W-30',
        'cost_oil_range' => '900〜1200円', 'cost_shop_range' => '2500〜3500円', 'cost_diy_range' => '1000〜1600円', 'cost_updated_at' => '2026-07']);

    $res = $this->get('/maintenance/cost-bike/oil')->assertOk();
    $res->assertSee('交換費用の目安')->assertSee('2026-07時点')
        ->assertSee('900〜1200円')->assertSee('2500〜3500円')->assertSee('1000〜1600円')
        ->assertSee('工賃は店舗・地域で変わります');
});

it('hides the cost block when no cost is set (no empty box)', function () {
    $m = fitModel('費用なし車', 'nocost-bike');
    fitVerified($m, ['task' => 'oil', 'recommended_part_no' => '10W-30']);

    $this->get('/maintenance/nocost-bike/oil')->assertOk()->assertDontSee('交換費用の目安');
});

it('shows only the filled cost rows', function () {
    $m = fitModel('一部費用車', 'partial-cost');
    fitVerified($m, ['task' => 'oil', 'recommended_part_no' => '10W-30', 'cost_shop_range' => '2500〜3500円']); // shop のみ

    $res = $this->get('/maintenance/partial-cost/oil')->assertOk();
    $res->assertSee('交換費用の目安')->assertSee('店で交換（総額）')->assertSee('2500〜3500円')
        ->assertDontSee('オイル代（部品のみ）')  // oil_range 空→行なし
        ->assertDontSee('自分で交換');            // diy 空→行なし
});

it('does not expose cost on the model detail summary (kanibari維持)', function () {
    $m = fitModel('カニバリ費用車', 'cani-cost');
    fitVerified($m, ['task' => 'oil', 'recommended_part_no' => '10W-30', 'cost_shop_range' => '9999円ダミー']);

    $blob = json_encode(app(\App\Services\Fitment\FitmentSummaryService::class)->forModel($m), JSON_UNESCAPED_UNICODE);
    expect($blob)->not->toContain('9999円ダミー'); // 費用は車種詳細サマリに出ない
});
