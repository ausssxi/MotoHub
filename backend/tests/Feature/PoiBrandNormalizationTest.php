<?php

declare(strict_types=1);

use App\Models\Poi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

/*
 * pois.brand の表記ゆれ正規化（案A: 表示時に config 駆動で正規化名へ寄せる）。
 * config/gas.php・config/convenience.php の分類と、詳細ページ描画での適用を固定する。
 */

uses(RefreshDatabase::class);

$bnOsm = 0;

/**
 * @param  array<string, mixed>  $overrides
 */
function bnPoi(array $overrides = []): Poi
{
    global $bnOsm;
    $bnOsm++;

    return Poi::forceCreate(array_merge([
        'osm_id' => 3000 + $bnOsm,
        'type' => 'gas_station',
        'name' => null,
        'latitude' => 43.0 + $bnOsm * 0.001,
        'longitude' => 141.3,
        'prefecture' => '北海道',
        'city' => '札幌市中央区',
        'municipality_code' => '01101',
    ], $overrides));
}

// --- config 分類（表記ゆれ統一・企業統合の現状維持・4点の変更） ---

it('GS: 表記ゆれを同一ブランド名に統一する', function () {
    $label = fn (string $b) => Poi::gasOperatorLabel($b, Poi::gasBrand($b));

    expect($label('ENEOS'))->toBe('ENEOS')
        ->and($label('エネオス'))->toBe('ENEOS')
        ->and($label('IDEMITSU'))->toBe('出光')
        ->and($label('出光興産'))->toBe('出光')
        ->and($label('LAWSON'))->toBeNull(); // 非GS(コンビニ誤タグ)は exclude → ラベル無し
});

it('GS: 企業統合済みブランドは吸収先のまま（Esso/Mobil→ENEOS, 昭和シェル→出光）', function () {
    $label = fn (string $b) => Poi::gasOperatorLabel($b, Poi::gasBrand($b));

    // 物理転換済み・看板が残っていないため吸収先表示が正しい（方針: 現状維持）。
    expect($label('Esso'))->toBe('ENEOS')
        ->and($label('Mobil'))->toBe('ENEOS')
        ->and($label('ゼネラル'))->toBe('ENEOS')
        ->and($label('昭和シェル'))->toBe('出光')
        ->and($label('Shell'))->toBe('出光')
        ->and($label('apollostation'))->toBe('出光');
});

it('GS config変更①②③: コスモ石油・カーエネクス・コストコ・navi', function () {
    $label = fn (string $b) => Poi::gasOperatorLabel($b, Poi::gasBrand($b));

    // ① コスモ → コスモ石油
    expect($label('コスモ'))->toBe('コスモ石油')
        ->and($label('コスモ石油'))->toBe('コスモ石油')
        // ② カーエネクス（伊藤忠エネクス）を1ブランドに
        ->and($label('Carenex'))->toBe('カーエネクス')
        ->and($label('カーエネクス 金秀鋼材'))->toBe('カーエネクス')
        // ③ exclude から昇格したコストコ・navi は実在GSとして表示
        ->and($label('コストコ　ガスステーション'))->toBe('コストコ')
        ->and($label('KIRKLAND Signature'))->toBe('コストコ')
        ->and($label('navi 丸紅エネルギー'))->toBe('navi');

    // コストコ・navi はもう exclude ではない
    expect(Poi::gasBrand('コストコ　ガスステーション'))->not->toBe('exclude')
        ->and(Poi::gasBrand('navi'))->not->toBe('exclude');
    // bing / 誤タグ系は exclude のまま
    expect(Poi::gasBrand('bing'))->toBe('exclude')
        ->and(Poi::gasBrand('7-Eleven'))->toBe('exclude');
});

it('コンビニ config変更④: ローソンストア100 を lawson から分離', function () {
    $label = fn (string $b) => Poi::cvsOperatorLabel($b, Poi::cvsBrand($b));

    expect(Poi::cvsBrand('LAWSON STORE 100'))->toBe('lawson-store-100')
        ->and($label('LAWSON STORE 100'))->toBe('ローソンストア100')
        ->and($label('ローソンストア100'))->toBe('ローソンストア100')
        // 通常ローソン・派生はローソンのまま（STORE100 に食われない）
        ->and($label('LAWSON'))->toBe('ローソン')
        ->and($label('NATURAL LAWSON'))->toBe('ローソン')
        // 表記ゆれ統一の基本
        ->and($label('7-ELEVEN'))->toBe('セブン-イレブン')
        ->and($label('FamilyMart'))->toBe('ファミリーマート');
});

// --- 詳細ページ描画への適用（name は触らない・brand由来のみ正規化） ---

it('GS詳細: brand由来の見出し・ブランド欄は正規化名になる', function () {
    $poi = bnPoi(['name' => null, 'brand' => 'エネオス', 'address' => '北海道札幌市中央区南1条西5-1']);
    Cache::put("gs_detail_nearby:v2:{$poi->id}", [[], [], null], 600);

    $this->get(route('gs.show', ['北海道', '札幌市中央区', $poi->id]))
        ->assertOk()
        ->assertSee('ENEOS（南1条西）') // h1: 正規化名＋町名
        ->assertDontSee('エネオス');    // 生の表記ゆれは出ない
});

it('GS詳細: name のある行は h1 をそのまま出し、ブランド欄だけ正規化', function () {
    $poi = bnPoi(['name' => 'エネオス 南1条SS', 'brand' => 'エネオス', 'address' => '北海道札幌市中央区南1条西5-1']);
    Cache::put("gs_detail_nearby:v2:{$poi->id}", [[], [], null], 600);

    // name は一切いじらない（h1 はそのまま）。ブランド欄は正規化名。
    $this->get(route('gs.show', ['北海道', '札幌市中央区', $poi->id]))
        ->assertOk()
        ->assertSee('エネオス 南1条SS') // h1（name）は無変更
        ->assertSee('ENEOS');          // 「ブランド」欄は正規化
});

it('GS詳細: name が素のブランド名そのもの（完全一致）なら正規化する', function () {
    $poi = bnPoi(['name' => 'エネオス', 'brand' => 'エネオス', 'address' => '青森県つがる市柏0-1', 'prefecture' => '青森県', 'city' => 'つがる市', 'municipality_code' => '02209']);
    Cache::put("gs_detail_nearby:v2:{$poi->id}", [[], [], null], 600);

    $this->get(route('gs.show', ['青森県', 'つがる市', $poi->id]))
        ->assertOk()->assertSee('ENEOS')->assertDontSee('エネオス');
});

it('GS詳細: 部分一致は誤爆しない（エネオス安波給油所はそのまま）', function () {
    $poi = bnPoi(['name' => 'エネオス安波給油所', 'brand' => 'エネオス', 'address' => '青森県つがる市柏0-1', 'prefecture' => '青森県', 'city' => 'つがる市', 'municipality_code' => '02209']);
    Cache::put("gs_detail_nearby:v2:{$poi->id}", [[], [], null], 600);

    // patterns の「エネオス」を含むが完全一致でないので name はそのまま（h1 無変更）。
    $this->get(route('gs.show', ['青森県', 'つがる市', $poi->id]))
        ->assertOk()->assertSee('エネオス安波給油所');
});

it('GS詳細: ガード系 name=JA も完全一致で JA-SS に正規化', function () {
    $poi = bnPoi(['name' => 'JA', 'brand' => '', 'address' => '青森県つがる市柏0-1', 'prefecture' => '青森県', 'city' => 'つがる市', 'municipality_code' => '02209']);
    Cache::put("gs_detail_nearby:v2:{$poi->id}", [[], [], null], 600);

    $this->get(route('gs.show', ['青森県', 'つがる市', $poi->id]))
        ->assertOk()->assertSee('JA-SS');
});

it('市区町村一覧: ブランドバッジが正規化される（typeを含めて解決）', function () {
    // nearbyPois() の空間クエリ（SQLite不可）を避けるため同一市区町村に10件以上作る。
    for ($i = 0; $i < 9; $i++) {
        bnPoi(['name' => "GS{$i}", 'brand' => null, 'prefecture' => '青森県', 'city' => 'つがる市', 'municipality_code' => '02209']);
    }
    bnPoi(['name' => 'apollostation 牛潟SS', 'brand' => 'apollostation', 'prefecture' => '青森県', 'city' => 'つがる市', 'municipality_code' => '02209']);
    bnPoi(['name' => 'エネオス安波給油所', 'brand' => 'エネオス', 'prefecture' => '青森県', 'city' => 'つがる市', 'municipality_code' => '02209']);

    $this->get(route('gs.city', ['青森県', 'つがる市']))
        ->assertOk()
        ->assertSee('apollostation 牛潟SS') // 店名（display）はそのまま
        ->assertSee('出光')                 // バッジ: apollostation → 出光（正規化）
        ->assertSee('ENEOS');               // バッジ: エネオス → ENEOS（正規化）
});
