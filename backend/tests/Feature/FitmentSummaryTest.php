<?php

use App\Models\BikeModel;
use App\Models\Manufacturer;
use App\Models\ModelFitment;
use App\Services\Fitment\FitmentSummaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function fsModel(string $name = 'テスト車', string $slug = 'test-model'): BikeModel
{
    $mfr = Manufacturer::firstWhere('slug', 'honda');
    if (! $mfr) {
        $mfr = new Manufacturer(['slug' => 'honda']);
        $mfr->name = 'ホンダ';
        $mfr->save();
    }

    return BikeModel::create(['manufacturer_id' => $mfr->id, 'name' => $name, 'slug' => $slug]);
}

function fsFitment(BikeModel $m, string $task, array $attrs = []): ModelFitment
{
    return ModelFitment::create(array_merge([
        'bike_model_id' => $m->id, 'task' => $task, 'frame_code' => 'AF00',
        'recommended_part_no' => 'TEST-0000', 'verified_at' => '2026-07-01',
    ], $attrs));
}

function fsSummary(BikeModel $m): array
{
    return app(FitmentSummaryService::class)->forModel($m);
}

// ─────────── サマリ生成 ───────────

it('returns tasks in config order and skips tasks with no verified rows', function () {
    $m = fsModel();
    fsFitment($m, 'oil', ['recommended_part_no' => '10W-30', 'spec' => ['jaso' => 'MA', 'capacity_change' => '0.8L']]);
    fsFitment($m, 'battery', ['spec' => ['voltage' => '12V', 'type' => 'VRLA(MF)']]);
    // plug は verified 行なし

    $rows = fsSummary($m);
    expect($rows)->toHaveCount(2)
        ->and(array_column($rows, 'task'))->toBe(['battery', 'oil']); // config順（battery→oil、plug無し）
});

it('summarizes battery by voltage/type + 型式数 (no part numbers)', function () {
    $m = fsModel();
    fsFitment($m, 'battery', ['frame_code' => 'A', 'recommended_part_no' => 'TEST-BATTPN', 'spec' => ['voltage' => '12V', 'type' => 'VRLA(MF)']]);
    fsFitment($m, 'battery', ['frame_code' => 'B', 'recommended_part_no' => 'TEST-BATTPN2', 'spec' => ['voltage' => '12V', 'type' => 'VRLA(MF)']]);

    $s = fsSummary($m)[0]['summary'];
    expect($s)->toContain('12V')->toContain('VRLA(MF)')->toContain('2型式を掲載')
        ->and($s)->not->toContain('TEST-BATTPN'); // 品番は要約に出ない
});

it('summarizes oil with jaso/viscosity/change-capacity', function () {
    $m = fsModel();
    fsFitment($m, 'oil', ['recommended_part_no' => '10W-30', 'spec' => ['jaso' => 'MA', 'capacity_change' => '0.8L']]);

    expect(fsSummary($m)[0]['summary'])->toBe('JASO MA・10W-30・交換時0.8L');
});

it('shows 型式による when a key differs across frames', function () {
    $m = fsModel();
    fsFitment($m, 'oil', ['frame_code' => 'JK46', 'recommended_part_no' => '10W-30', 'spec' => ['jaso' => 'MB', 'capacity_change' => '0.7L']]);
    fsFitment($m, 'oil', ['frame_code' => 'JF58', 'recommended_part_no' => '10W-30', 'spec' => ['jaso' => 'MB', 'capacity_change' => '0.65L']]);

    expect(fsSummary($m)[0]['summary'])->toBe('JASO MB・10W-30・交換量は型式による');
});

it('falls back to a count-only summary when spec keys are missing', function () {
    $m = fsModel();
    fsFitment($m, 'battery', ['spec' => null]);

    expect(fsSummary($m)[0]['summary'])->toBe('適合表を掲載中（1型式）');
});

it('excludes unverified rows and returns empty when none verified', function () {
    $m = fsModel();
    ModelFitment::create(['bike_model_id' => $m->id, 'task' => 'battery', 'recommended_part_no' => 'TEST-0000', 'verified_at' => null]);

    expect(fsSummary($m))->toBe([]);
});

it('builds a clean /maintenance/{slug}/{task} url without query params and a search-word anchor', function () {
    $m = fsModel('グロム', 'grom');
    fsFitment($m, 'plug', ['spec' => ['heat' => '8', 'plugs' => '1']]);

    $row = fsSummary($m)[0];
    expect($row['url'])->toEndWith('/maintenance/grom/plug')
        ->and($row['url'])->not->toContain('?')
        ->and($row['anchor'])->toBe('グロムのプラグ品番・適合表を見る')
        ->and($row['summary'])->toBe('熱価8番・1本｜1型式を掲載');
});

// ─────────── カニバリ回帰（サマリの出力が唯一の fitment データ源）───────────

it('never emits battery/plug part numbers, compatibles or notes in the summary output', function () {
    // 車種詳細ページの fitment データは全てこのサービス出力経由（旧品番カードは撤去済み）。
    // よってサービスが品番/互換/備考を一切出さなければ、ページにも出ない。
    $m = fsModel('回帰車', 'reg-cani');
    fsFitment($m, 'battery', [
        'recommended_part_no' => 'TEST-0000',
        'compatible_part_nos' => [['brand' => 'テスト', 'part_no' => 'TEST-COMPAT']],
        'spec' => ['voltage' => '12V', 'type' => 'VRLA(MF)'],
        'note_public' => '公開備考', 'note_internal' => '内部メモ秘密',
    ]);
    fsFitment($m, 'plug', ['recommended_part_no' => 'TEST-PLUGPN', 'spec' => ['heat' => '8', 'plugs' => '1']]);

    $blob = json_encode(fsSummary($m), JSON_UNESCAPED_UNICODE);
    expect($blob)->toContain('バッテリー型番・適合表を見る') // (サニティ: アンカーは出ている)
        ->and($blob)->not->toContain('TEST-0000')     // battery 推奨品番
        ->and($blob)->not->toContain('TEST-COMPAT')   // 互換品番
        ->and($blob)->not->toContain('TEST-PLUGPN')   // plug 推奨品番
        ->and($blob)->not->toContain('内部メモ秘密')   // note_internal
        ->and($blob)->not->toContain('公開備考');      // note_public も転記しない
});
