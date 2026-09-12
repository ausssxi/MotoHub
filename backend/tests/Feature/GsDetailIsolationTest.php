<?php

declare(strict_types=1);

use App\Models\Poi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

/*
 * GS詳細ページの「離島 vs 未計算」表示の回帰テスト（本番 佐久市の誤表示バグ）。
 *
 * 未計算(nearest_computed_at IS NULL)の行を「この付近に他のGSはありません」と誤表示しないことを固定する。
 * この不具合は poi:fetch(毎晩) が未計算行を増やすたび再発しうるので、テストで固定する。
 *
 * 詳細ページは周辺施設で ST_Distance_Sphere を使う（SQLite不可）ため、その空間クエリ結果だけ
 * キャッシュキー gs_detail_nearby:v2:{id} に事前投入して回避する。離島/次のGS判定はキャッシュ外なので
 * このテストで実際のHTML描画まで検証できる。
 */

uses(RefreshDatabase::class);

$isoOsm = 0;

/**
 * @param  array<string, mixed>  $overrides
 */
function isoPoi(array $overrides = []): Poi
{
    global $isoOsm;
    $isoOsm++;

    return Poi::forceCreate(array_merge([
        'osm_id' => 1000 + $isoOsm,
        'type' => 'gas_station',
        'name' => "ISO-GS-{$isoOsm}",
        'latitude' => 36.2 + $isoOsm * 0.001,
        'longitude' => 138.4,
        'prefecture' => '長野県',
        'city' => '佐久市',
        'municipality_code' => '20217',
    ], $overrides));
}

/** show() 内の周辺施設（空間クエリ）を実行させないよう、キャッシュを空で事前投入する。 */
function seedGsNearbyCache(Poi $poi): void
{
    Cache::put("gs_detail_nearby:v2:{$poi->id}", [[], [], null], 600);
}

it('未計算のGS（nearest_computed_at=NULL）は「他にありません」を出さない（本番バグの固定）', function () {
    // 昨夜 poi:fetch が追加したばかりで未計算、の状態を再現。
    $poi = isoPoi(['nearest_computed_at' => null, 'nearest_same_type_id' => null, 'nearest_same_type_m' => null]);
    seedGsNearbyCache($poi);

    $res = $this->get(route('gs.show', ['長野県', '佐久市', $poi->id]));

    $res->assertOk()
        ->assertDontSee('この付近に他のガソリンスタンドはありません')
        ->assertDontSee('半径100km以内に別のガソリンスタンドが見当たりません')
        ->assertDontSee('次のガソリンスタンドまで');
});

it('本物の離島（計算済み＆近傍なし）は「他にありません」を出す', function () {
    $poi = isoPoi(['nearest_computed_at' => now(), 'nearest_same_type_id' => null, 'nearest_same_type_m' => null]);
    seedGsNearbyCache($poi);

    $this->get(route('gs.show', ['長野県', '佐久市', $poi->id]))
        ->assertOk()
        ->assertSee('この付近に他のガソリンスタンドはありません');
});

it('計算済みで近傍ありは「次のGSまで◯km」を出す（離島表示は出さない）', function () {
    $neighbor = isoPoi(['name' => '隣のGS', 'city' => '小諸市', 'municipality_code' => '20208']);
    $poi = isoPoi([
        'nearest_computed_at' => now(),
        'nearest_same_type_id' => $neighbor->id,
        'nearest_same_type_m' => 500,
    ]);
    seedGsNearbyCache($poi);

    $this->get(route('gs.show', ['長野県', '佐久市', $poi->id]))
        ->assertOk()
        ->assertSee('次のガソリンスタンドまで約0.5km')
        ->assertDontSee('この付近に他のガソリンスタンドはありません');
});

it('Poi の離島判定は「計算済み」を必須にする', function () {
    $uncomputed = isoPoi(['nearest_computed_at' => null, 'nearest_same_type_id' => null]);
    $island = isoPoi(['nearest_computed_at' => now(), 'nearest_same_type_id' => null]);
    $hasNeighbor = isoPoi(['nearest_computed_at' => now(), 'nearest_same_type_id' => 12345]);

    expect($uncomputed->isGenuinelyIsolated())->toBeFalse()
        ->and($uncomputed->nearestComputed())->toBeFalse()
        ->and($island->isGenuinelyIsolated())->toBeTrue()
        ->and($hasNeighbor->isGenuinelyIsolated())->toBeFalse();

    // スコープも同条件（未計算は除外・離島のみ）。
    $isolatedIds = Poi::query()->genuinelyIsolated()->pluck('id')->all();
    expect($isolatedIds)->toContain($island->id)
        ->and($isolatedIds)->not->toContain($uncomputed->id)
        ->and($isolatedIds)->not->toContain($hasNeighbor->id);
});
