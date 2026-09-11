<?php

declare(strict_types=1);

use App\Models\Poi;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * poi:compute-nearest（最近傍の事前計算）テスト。
 * SQLite では PHP Haversine 経路を通る（本番 MySQL は ST_Distance_Sphere・同義）。
 */

uses(RefreshDatabase::class);

$osm = 0;

/**
 * @param  array<string, mixed>  $overrides
 */
function makePoi(string $type, float $lat, float $lng, array $overrides = []): Poi
{
    global $osm;
    $osm++;

    // prefecture 等の region 列は $fillable 外（AssignPoiMunicipality が直接代入する）ため forceCreate。
    return Poi::forceCreate(array_merge([
        'osm_id' => $osm,
        'type' => $type,
        'name' => "poi-{$osm}",
        'latitude' => $lat,
        'longitude' => $lng,
    ], $overrides));
}

it('段階的に矩形を広げて最も近い同種別POIを記録する', function () {
    // A の近傍は C(約222m)。B(約1.1km)より近い。
    $a = makePoi('gas_station', 35.0000, 139.0000);
    $c = makePoi('gas_station', 35.0020, 139.0000); // ~222m 北
    $b = makePoi('gas_station', 35.0100, 139.0000); // ~1113m 北

    $this->artisan('poi:compute-nearest', ['--type' => 'gas_station'])->assertExitCode(0);

    $a->refresh();
    expect($a->nearest_same_type_id)->toBe($c->id)
        ->and($a->nearest_same_type_m)->toBeGreaterThan(200)
        ->and($a->nearest_same_type_m)->toBeLessThan(260)
        ->and($a->nearest_computed_at)->not->toBeNull();

    // B の近傍は C（1kmボックスで C を拾い、A は範囲外）。
    $b->refresh();
    expect($b->nearest_same_type_id)->toBe($c->id);
});

it('同種別だけを対象にし、別種別は無視する', function () {
    $gs = makePoi('gas_station', 35.0000, 139.0000);
    makePoi('convenience_store', 35.00001, 139.00001); // 目の前だが別種別
    $farGs = makePoi('gas_station', 35.0300, 139.0000); // ~3.3km

    $this->artisan('poi:compute-nearest', ['--type' => 'gas_station'])->assertExitCode(0);

    expect($gs->refresh()->nearest_same_type_id)->toBe($farGs->id);
});

it('3km以上離れていれば孤立として数えられる（サイトマップ選別の土台）', function () {
    $d = makePoi('convenience_store', 35.0000, 139.5000);
    $e = makePoi('convenience_store', 35.0400, 139.5000); // ~4.45km

    $this->artisan('poi:compute-nearest', ['--type' => 'convenience_store'])->assertExitCode(0);

    expect($d->refresh()->nearest_same_type_m)->toBeGreaterThanOrEqual(3000);
    expect(Poi::where('nearest_same_type_m', '>=', 3000)->count())->toBe(2); // D, E とも孤立
});

it('100km以内に同種別が無ければ nearest は null（離島など）', function () {
    $lone = makePoi('car_wash', 35.0000, 139.0000);

    $this->artisan('poi:compute-nearest', ['--type' => 'car_wash'])->assertExitCode(0);

    $lone->refresh();
    expect($lone->nearest_same_type_id)->toBeNull()
        ->and($lone->nearest_same_type_m)->toBeNull()
        ->and($lone->nearest_computed_at)->not->toBeNull(); // 計算済みフラグは立つ（再処理しない）
});

it('既定は未計算行のみ・--force で計算済みも再計算する（resume）', function () {
    $a = makePoi('gas_station', 35.0000, 139.0000);
    $b = makePoi('gas_station', 35.0020, 139.0000);

    // 事前に a を計算済みに（別の値）しておく。
    $a->update(['nearest_same_type_id' => null, 'nearest_same_type_m' => 99999, 'nearest_computed_at' => now()]);

    // 既定実行では a はスキップ、b だけ計算される。
    $this->artisan('poi:compute-nearest', ['--type' => 'gas_station'])->assertExitCode(0);
    expect($a->refresh()->nearest_same_type_m)->toBe(99999) // 触られていない
        ->and($b->refresh()->nearest_same_type_id)->toBe($a->id);

    // --force で a も再計算され、正しい近傍(b)になる。
    $this->artisan('poi:compute-nearest', ['--type' => 'gas_station', '--force' => true])->assertExitCode(0);
    expect($a->refresh()->nearest_same_type_id)->toBe($b->id)
        ->and($a->nearest_same_type_m)->toBeLessThan(260);
});

it('--limit で処理件数を打ち切る（残りは未計算のまま）', function () {
    makePoi('gas_station', 35.0000, 139.0000);
    makePoi('gas_station', 35.0020, 139.0000);
    makePoi('gas_station', 35.0040, 139.0000);

    $this->artisan('poi:compute-nearest', ['--type' => 'gas_station', '--limit' => 2])->assertExitCode(0);

    expect(Poi::whereNotNull('nearest_computed_at')->count())->toBe(2)
        ->and(Poi::whereNull('nearest_computed_at')->count())->toBe(1);
});

it('--prefecture で対象を絞る', function () {
    $kanagawaA = makePoi('gas_station', 35.4000, 139.6000, ['prefecture' => '神奈川県']);
    makePoi('gas_station', 35.4020, 139.6000, ['prefecture' => '神奈川県']);
    $tokyo = makePoi('gas_station', 35.6800, 139.7600, ['prefecture' => '東京都']);

    $this->artisan('poi:compute-nearest', ['--prefecture' => '神奈川県'])->assertExitCode(0);

    expect($kanagawaA->refresh()->nearest_computed_at)->not->toBeNull()
        ->and($tokyo->refresh()->nearest_computed_at)->toBeNull();
});

it('不正な --type はエラーで終了する', function () {
    $this->artisan('poi:compute-nearest', ['--type' => 'bogus'])->assertExitCode(1);
});
