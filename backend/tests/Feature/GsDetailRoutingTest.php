<?php

declare(strict_types=1);

use App\Models\Poi;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * GS詳細ページのルーティング回帰テスト（宣言順・短縮URL・senshajo挙動の非変更）。
 *
 * 詳細ページ本体（show）の描画は nearbyFacilities 等が ST_Distance_Sphere を使うため
 * SQLite では実行できず、既存の senshajo.show 同様ここでは検証しない（本番/MySQLで確認）。
 * ここは空間クエリを含まない経路（short / prefecture / 宣言順）だけを担保する。
 */

uses(RefreshDatabase::class);

$osmSeq = 0;

/**
 * @param  array<string, mixed>  $overrides
 */
function gsPoi(array $overrides = []): Poi
{
    global $osmSeq;
    $osmSeq++;

    // region 列（prefecture/city/municipality_code）は $fillable 外なので forceCreate。
    return Poi::forceCreate(array_merge([
        'osm_id' => $osmSeq,
        'type' => 'gas_station',
        'name' => "GS-{$osmSeq}",
        'latitude' => 35.4 + $osmSeq * 0.001,
        'longitude' => 139.6,
        'prefecture' => '神奈川県',
        'city' => '横浜市西区',
        'municipality_code' => '14103',
    ], $overrides));
}

it('gs.show / gs.short ルートが登録されている', function () {
    expect(app('router')->getRoutes()->hasNamedRoute('gs.show'))->toBeTrue()
        ->and(app('router')->getRoutes()->hasNamedRoute('gs.short'))->toBeTrue();
});

it('/gs/{id} は正規URL(gs.show)へ301リダイレクトする', function () {
    $poi = gsPoi();

    $this->get('/gs/'.$poi->id)
        ->assertStatus(301)
        ->assertRedirect(route('gs.show', ['神奈川県', '横浜市西区', $poi->id]));
});

it('prefecture/city が欠ける行は一覧へ302で逃がす', function () {
    $poi = gsPoi(['prefecture' => null, 'city' => null, 'municipality_code' => null]);

    $this->get('/gs/'.$poi->id)
        ->assertStatus(302)
        ->assertRedirect(route('gs.index'));
});

it('別種別の id を /gs/{id} に与えると404', function () {
    $konbini = gsPoi(['type' => 'convenience_store']);

    $this->get('/gs/'.$konbini->id)->assertNotFound();
});

it('存在しない id は404', function () {
    $this->get('/gs/999999')->assertNotFound();
});

it('宣言順: /gs/{都道府県} は short に吸われず prefecture ページを返す', function () {
    gsPoi(); // 神奈川県に1件

    // 日本語都道府県は whereNumber('id') の short に一致せず prefecture へ。200 が返れば吸われていない。
    $this->get('/gs/'.rawurlencode('神奈川県'))->assertOk();
});

it('senshajo.short の挙動は不変: GSの id を与えると従来どおり404', function () {
    $gs = gsPoi();

    // 汎用化した short() でも、種別不一致（car_wash に GS id）は404のまま。
    $this->get('/senshajo/'.$gs->id)->assertNotFound();
});

it('senshajo.short は car_wash の正規URLへ301（回帰）', function () {
    $wash = gsPoi(['type' => 'car_wash', 'city' => '横浜市中区']);

    $this->get('/senshajo/'.$wash->id)
        ->assertStatus(301)
        ->assertRedirect(route('senshajo.show', ['神奈川県', '横浜市中区', $wash->id]));
});
