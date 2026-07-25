<?php

declare(strict_types=1);

use App\Models\Poi;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * コンビニ（Poi type=convenience_store）の運営分類（地図ピンの色分け）。GSと同型。
 *
 *   固有色7: seven/familymart/lawson/ministop/daily-yamazaki/seicomart/newdays
 *   'other'  : その他チェーン（ポプラ/Heart・in 等）
 *   'exclude': 閉店タグ(disused:) → ペイロードから除外
 *   null     : brand 空/不明 → 従来アイコン
 * 詳しくは Poi::cvsBrand() / config/convenience.php。
 */

it('bundles all セブン notations (ハイフン有無/英字カナ) into seven', function () {
    foreach (['7-ELEVEN', '7 ELEVEN', 'セブン-イレブン', 'セブンイレブン', 'セブン-イレブン・ジャパン', 'セブンイレブンジャパン'] as $b) {
        expect(Poi::cvsBrand($b))->toBe('seven', "{$b} は seven");
    }
});

it('bundles FamilyMart notations and サークルK/サンクス into familymart', function () {
    foreach (['FamilyMart', 'ファミリーマート', '株式会社ファミリーマート', 'サークルK', 'sunkus サンクス', 'Sankus'] as $b) {
        expect(Poi::cvsBrand($b))->toBe('familymart', "{$b} は familymart");
    }
});

it('bundles all ローソン派生 (STORE100/NATURAL/スリーエフ/ポプラ/toks/JP) into lawson', function () {
    foreach (['LAWSON', 'ローソン', 'LAWSON STORE 100', 'ローソンストア100', 'NATURAL LAWSON', 'ナチュラルローソン',
        'LAWSON+スリーエフ', 'ローソン・スリーエフ', 'LAWSON+ポプラ', 'LAWSON+toks', '株式会社ローソン',
        'ローソン;Lawson', 'JPローソン', 'ローソン100'] as $b) {
        expect(Poi::cvsBrand($b))->toBe('lawson', "{$b} は lawson");
    }
});

it('resolves ministop notations incl Mini-stop (ハイフン)', function () {
    expect(Poi::cvsBrand('MINISTOP'))->toBe('ministop')
        ->and(Poi::cvsBrand('ミニストップ'))->toBe('ministop')
        ->and(Poi::cvsBrand('Mini-stop'))->toBe('ministop');
});

it('resolves the remaining regional brands', function () {
    expect(Poi::cvsBrand('Daily YAMAZAKI'))->toBe('daily-yamazaki')
        ->and(Poi::cvsBrand('デイリーヤマザキ'))->toBe('daily-yamazaki')
        ->and(Poi::cvsBrand('Seicomart'))->toBe('seicomart')
        ->and(Poi::cvsBrand('セイコーマート'))->toBe('seicomart')
        ->and(Poi::cvsBrand('セイコーマート(Seicomart)'))->toBe('seicomart')
        ->and(Poi::cvsBrand('NewDays'))->toBe('newdays');
});

it('keeps ポプラ / Heart・in as other, excludes disused, empty→null', function () {
    expect(Poi::cvsBrand('ポプラ'))->toBe('other')
        ->and(Poi::cvsBrand('POPLAR'))->toBe('other')
        ->and(Poi::cvsBrand('Heart・in'))->toBe('other')
        ->and(Poi::cvsBrand('disused:セブン-イレブン'))->toBe('exclude') // 閉店タグ
        ->and(Poi::cvsBrand(null))->toBeNull()
        ->and(Poi::cvsBrand(''))->toBeNull();
});

it('exposes the unified brand name via cvsOperatorLabel', function () {
    expect(Poi::cvsOperatorLabel('7-ELEVEN', 'seven'))->toBe('セブン-イレブン')
        ->and(Poi::cvsOperatorLabel('サークルK', 'familymart'))->toBe('ファミリーマート')
        ->and(Poi::cvsOperatorLabel('ポプラ', 'other'))->toBe('ポプラ')
        ->and(Poi::cvsOperatorLabel(null, null))->toBeNull()
        ->and(Poi::cvsOperatorLabel('disused:x', 'exclude'))->toBeNull();
});

// ─────────── マップAPI ペイロード ───────────

function cvsPoi(string $name, ?string $brand, string $type = 'convenience_store'): Poi
{
    return Poi::create([
        'osm_id' => random_int(1, 1_000_000_000),
        'type' => $type, 'name' => $name, 'brand' => $brand,
        'latitude' => 35.68, 'longitude' => 139.76, 'address' => 'addr',
    ]);
}

it('tags convenience rows with cvs_brand + cvs_operator and drops disused; GS untouched', function () {
    cvsPoi('セブン渋谷店', '7-ELEVEN');
    cvsPoi('ポプラ広島店', 'ポプラ');
    cvsPoi('閉店セブン', 'disused:セブン-イレブン'); // 除外
    cvsPoi('エネオスSS', 'ENEOS', 'gas_station');    // 別type＝コンビニ判定を受けない

    $rows = collect($this->getJson('/api/pois?type=convenience_store,gas_station&ne_lat=36&ne_lng=140&sw_lat=35&sw_lng=139')->assertOk()->json());

    $seven = $rows->firstWhere('name', 'セブン渋谷店');
    $poplar = $rows->firstWhere('name', 'ポプラ広島店');
    $gas = $rows->firstWhere('name', 'エネオスSS');

    expect($seven['cvs_brand'])->toBe('seven')
        ->and($seven['cvs_operator'])->toBe('セブン-イレブン')
        ->and($poplar['cvs_brand'])->toBe('other')
        ->and($poplar['cvs_operator'])->toBe('ポプラ')
        ->and($rows->firstWhere('name', '閉店セブン'))->toBeNull() // disused は地図に出さない
        ->and($gas['gas_brand'])->toBe('eneos')                    // GSは従来通りgas_brand
        ->and($gas)->not->toHaveKey('cvs_brand');                  // GSにcvs_brandは付かない
});
