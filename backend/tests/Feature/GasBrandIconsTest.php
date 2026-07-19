<?php

declare(strict_types=1);

use App\Models\Poi;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * GS（Poi type=gas_station）の運営分類（地図ピンの色分け）。
 *
 *   固有色7: eneos/idemitsu/cosmo/ja-ss/hokuren/kygnus/solato
 *   'other'  : 実在の独立系GS屋号（共通色グレー）
 *   'exclude': 明確な非GS → ペイロードから除外
 *   null     : brand 空/不明 → 従来の赤⛽
 * 詳しくは Poi::gasBrand() / config/gas.php。
 */

// ─────────── 固有ブランドの表記ゆれ吸収（統合履歴込み） ───────────

it('bundles ENEOS lineage notations (Esso/Mobil/JOMO/新日石/三菱石油) into eneos', function () {
    foreach (['ENEOS', 'エネオス', 'EneJet', 'エッソ', 'Esso', 'Mobil', 'モービル', 'ゼネラル', 'JOMO', '新日石', '三菱石油'] as $b) {
        expect(Poi::gasBrand($b))->toBe('eneos', "{$b} は eneos に束ねる");
    }
});

it('bundles 出光 lineage (apollostation/昭和シェル/Shell) into idemitsu', function () {
    foreach (['出光', '出光興産', 'IDEMITSU', 'apollostation', 'アポロステーション', '昭和シェル', 'Shell', 'シェル'] as $b) {
        expect(Poi::gasBrand($b))->toBe('idemitsu', "{$b} は idemitsu に束ねる");
    }
});

it('does NOT pull 三菱商事系 / Mitsubishi into eneos (only 三菱石油)', function () {
    expect(Poi::gasBrand('三菱商事エネルギー'))->toBe('other')
        ->and(Poi::gasBrand('三菱商事石油'))->toBe('other')
        ->and(Poi::gasBrand('三菱商事'))->toBe('other')
        ->and(Poi::gasBrand('三菱'))->toBe('other')
        ->and(Poi::gasBrand('Mitsubishi'))->toBe('other');
});

// ─────────── 誤爆ガード（JA / cosmo） ───────────

it('resolves 農協系 to ja-ss without swallowing latin ja (JAF/JAL/japan)', function () {
    // 拾う: bare JA(348件) / JA-SS表記ゆれ / JAおきなわ等 / 全農
    expect(Poi::gasBrand('JA'))->toBe('ja-ss')
        ->and(Poi::gasBrand('JA-SS'))->toBe('ja-ss')
        ->and(Poi::gasBrand('JASS'))->toBe('ja-ss')
        ->and(Poi::gasBrand('JASS-PORT'))->toBe('ja-ss')
        ->and(Poi::gasBrand('JAおきなわ'))->toBe('ja-ss')
        ->and(Poi::gasBrand('JA児湯'))->toBe('ja-ss')
        ->and(Poi::gasBrand('JA全農'))->toBe('ja-ss');
    // 巻き込まない: latin ja で始まる別語
    expect(Poi::gasBrand('JAF'))->not->toBe('ja-ss')
        ->and(Poi::gasBrand('Japan Energy'))->not->toBe('ja-ss');
});

it('resolves コスモ but treats ambiguous Cosmos as other (safe side)', function () {
    expect(Poi::gasBrand('コスモ石油'))->toBe('cosmo')
        ->and(Poi::gasBrand('コスモ'))->toBe('cosmo')
        ->and(Poi::gasBrand('Cosmo'))->toBe('cosmo')
        ->and(Poi::gasBrand('Cosmos'))->toBe('other'); // コスモス薬品の疑い＝固有色にしない
});

it('resolves the remaining major brands', function () {
    expect(Poi::gasBrand('ホクレン'))->toBe('hokuren')
        ->and(Poi::gasBrand('キグナス石油'))->toBe('kygnus')
        ->and(Poi::gasBrand('KYGNUS'))->toBe('kygnus')
        ->and(Poi::gasBrand('SOLATO'))->toBe('solato')
        ->and(Poi::gasBrand('太陽石油'))->toBe('solato');
});

// ─────────── 非GS除外 / other / null ───────────

it('marks clearly non-GS brands as exclude (小売/車/水素/都市ガス/ハングル/ノイズ)', function () {
    foreach (['LAWSON', 'ローソン', '7-Eleven', 'コストコ', 'Costco', 'Isuzu', 'いすゞ', 'オートバックス',
        'ビバホーム', 'bing', 'navi', 'イワタニ水素', '岩谷', '東京ガス', 'アストモス',
        '현대오일뱅크', '에쓰오일', 'SK주유소'] as $b) {
        expect(Poi::gasBrand($b))->toBe('exclude', "{$b} は非GS除外");
    }
});

it('keeps independent 屋号 as other and unknown/empty as null (赤⛽)', function () {
    expect(Poi::gasBrand('カーエネクス'))->toBe('other')
        ->and(Poi::gasBrand('三河石油商会'))->toBe('other') // 各地の独立屋号
        ->and(Poi::gasBrand(null))->toBeNull()
        ->and(Poi::gasBrand(''))->toBeNull()
        ->and(Poi::gasBrand('   '))->toBeNull();
});

it('exposes the unified brand name via gasOperatorLabel', function () {
    expect(Poi::gasOperatorLabel('エッソ', 'eneos'))->toBe('ENEOS')       // 生=エッソでも統一名
        ->and(Poi::gasOperatorLabel('昭和シェル', 'idemitsu'))->toBe('出光')
        ->and(Poi::gasOperatorLabel('三河石油商会', 'other'))->toBe('三河石油商会') // 10字以内はそのまま
        ->and(Poi::gasOperatorLabel(null, null))->toBeNull()
        ->and(Poi::gasOperatorLabel('LAWSON', 'exclude'))->toBeNull();
});

// ─────────── マップAPI ペイロード ───────────

function gasPoi(string $name, ?string $brand, string $type = 'gas_station'): Poi
{
    return Poi::create([
        'osm_id' => random_int(1, 1_000_000_000),
        'type' => $type, 'name' => $name, 'brand' => $brand,
        'latitude' => 35.68, 'longitude' => 139.76, 'address' => 'addr',
    ]);
}

it('tags gas_station rows with gas_brand + gas_operator and drops excluded non-GS', function () {
    gasPoi('エネオス東京SS', 'ENEOS');
    gasPoi('独立系スタンド', 'カーエネクス');
    gasPoi('ローソン店内', 'LAWSON');            // 非GS → 除外
    gasPoi('セブン◯◯店', 'セブンイレブン', 'convenience_store'); // GS以外はそのまま

    $rows = collect($this->getJson('/api/pois?type=gas_station,convenience_store&ne_lat=36&ne_lng=140&sw_lat=35&sw_lng=139')->assertOk()->json());

    $eneos = $rows->firstWhere('name', 'エネオス東京SS');
    $indie = $rows->firstWhere('name', '独立系スタンド');

    expect($eneos['gas_brand'])->toBe('eneos')
        ->and($eneos['gas_operator'])->toBe('ENEOS')
        ->and($indie['gas_brand'])->toBe('other')
        ->and($indie['gas_operator'])->toBe('カーエネクス')
        ->and($rows->firstWhere('name', 'ローソン店内'))->toBeNull() // 非GSは地図に出さない
        ->and($rows->firstWhere('name', 'セブン◯◯店'))->not->toBeNull(); // コンビニは素通し
});
