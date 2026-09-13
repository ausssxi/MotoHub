<?php

declare(strict_types=1);

use App\Models\RoadsideStation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

/** @param array<string,mixed> $flags */
function station(array $flags = []): RoadsideStation
{
    static $code = 90000;
    $s = (new RoadsideStation)->forceFill(array_merge([
        'station_code' => (string) ($code++),
        'name' => 'テスト道の駅',
        'prefecture' => '群馬県',
        'city' => '利根郡川場村',
        'latitude' => 36.6,
        'longitude' => 139.1,
    ], $flags));
    $s->save();

    return $s;
}

// ─────────── facilityBadges()（希少度順・true のみ） ───────────

it('returns only true flags in rarity order (gas first)', function () {
    $s = station([
        'has_gas_station' => true, 'has_observatory' => true,
        'has_restaurant' => true, 'has_shop' => true,
        'has_onsen' => false, 'has_shower' => false,
    ]);

    expect($s->facilityBadges())->toBe(['ガソリンスタンド併設', '展望台', 'レストラン', '売店・物産']);
});

it('puts onsen before restaurant/shop (rarity order)', function () {
    $s = station(['has_onsen' => true, 'has_restaurant' => true, 'has_shop' => true]);

    expect($s->facilityBadges())->toBe(['温泉', 'レストラン', '売店・物産']);
});

it('returns an empty array when no flag is true', function () {
    expect(station()->facilityBadges())->toBe([]);
});

it('caps at the given limit', function () {
    $s = station([
        'has_gas_station' => true, 'has_shower' => true, 'has_camp' => true,
        'has_atm' => true, 'has_onsen' => true,
    ]);

    expect($s->facilityBadges(4))->toBe(['ガソリンスタンド併設', 'シャワー', 'キャンプ', 'ATM']);
});

// ─────────── コンポーネント <x-michinoeki-facilities> ───────────

it('renders only true badges, never 「なし」, highlights gas', function () {
    $s = station([
        'has_gas_station' => true, 'has_observatory' => true,
        'has_restaurant' => true, 'has_shop' => true,
    ]);

    $view = $this->blade('<x-michinoeki-facilities :station="$station" />', ['station' => $s]);

    $view->assertSee('ガソリンスタンド併設')->assertSee('展望台')->assertSee('レストラン')->assertSee('売店・物産');
    $view->assertDontSee('なし');
    $view->assertDontSee('温泉'); // false のものはマークアップにも出ない
    $view->assertSee('bg-blue-600', false); // GS 併設は1段強い色
});

it('renders nothing when there are no true flags', function () {
    $view = $this->blade('<x-michinoeki-facilities :station="$station" />', ['station' => station()]);

    expect(trim($view->__toString()))->toBe('');
});

it('accepts a precomputed badges array (touring side)', function () {
    $view = $this->blade('<x-michinoeki-facilities :badges="$badges" />', ['badges' => ['温泉', 'ATM']]);

    $view->assertSee('温泉')->assertSee('ATM');
});

// ─────────── 詳細ページ（roadside_nearby はキャッシュ事前投入で spatial を回避） ───────────

it('shows the facility badges block on the detail page without 「なし」', function () {
    $s = station([
        'has_gas_station' => true, 'has_observatory' => true,
        'has_restaurant' => true, 'has_shop' => true,
    ]);
    Cache::put("roadside_nearby_v3:{$s->station_code}", []); // buildNearby(spatial) をスキップ

    $res = $this->get('/michinoeki/'.$s->station_code)->assertOk();

    $res->assertSee('施設情報');
    $res->assertSee('ガソリンスタンド併設');
    $res->assertSee('上記以外の設備は未確認です');
    $res->assertDontSee('なし'); // ★「なし」の2値表示が消えていること
});

it('hides the whole facility block when all flags are false', function () {
    $s = station(); // 全 false
    Cache::put("roadside_nearby_v3:{$s->station_code}", []);

    $res = $this->get('/michinoeki/'.$s->station_code)->assertOk();

    $res->assertDontSee('施設情報');
    $res->assertDontSee('上記以外の設備は未確認です');
});
