<?php

declare(strict_types=1);

use App\Models\Poi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

/*
 * コンビニ詳細ページ（フェーズ3）: ルーティング宣言順・短縮URL・離島出し分け・表示名一意化。
 *
 * 詳細ページの周辺施設は ST_Distance_Sphere（SQLite不可）を使うため、その結果だけ
 * konbini_detail_nearby:v2:{id} に事前投入して回避する。離島/次コンビニ/表示名はキャッシュ外なので描画まで検証できる。
 */

uses(RefreshDatabase::class);

$konOsm = 0;

/**
 * @param  array<string, mixed>  $overrides
 */
function konPoi(array $overrides = []): Poi
{
    global $konOsm;
    $konOsm++;

    return Poi::forceCreate(array_merge([
        'osm_id' => 2000 + $konOsm,
        'type' => 'convenience_store',
        'name' => "KON-{$konOsm}",
        'latitude' => 43.3 + $konOsm * 0.001,
        'longitude' => 142.3,
        'prefecture' => '北海道',
        'city' => '富良野市',
        'municipality_code' => '01229',
    ], $overrides));
}

function seedKonbiniCache(Poi $poi): void
{
    Cache::put("konbini_detail_nearby:v2:{$poi->id}", [[], [], null], 600);
}

it('konbini.show / konbini.short ルートが登録されている', function () {
    expect(app('router')->getRoutes()->hasNamedRoute('konbini.show'))->toBeTrue()
        ->and(app('router')->getRoutes()->hasNamedRoute('konbini.short'))->toBeTrue();
});

it('宣言順: /konbini/北海道 は short に吸われず prefecture ページを返す', function () {
    konPoi(); // 北海道に1件

    $this->get('/konbini/'.rawurlencode('北海道'))->assertOk();
});

it('/konbini/{id} は正規URL(konbini.show)へ301', function () {
    $poi = konPoi();

    $this->get('/konbini/'.$poi->id)
        ->assertStatus(301)
        ->assertRedirect(route('konbini.show', ['北海道', '富良野市', $poi->id]));
});

it('別種別の id を /konbini/{id} に与えると404（senshajo/gs 挙動と対称）', function () {
    $gs = konPoi(['type' => 'gas_station']);

    $this->get('/konbini/'.$gs->id)->assertNotFound();
});

it('未計算のコンビニは「他にありません」も「次のコンビニ」も出さない（佐久市の教訓）', function () {
    $poi = konPoi(['nearest_computed_at' => null, 'nearest_same_type_id' => null, 'nearest_same_type_m' => null]);
    seedKonbiniCache($poi);

    $this->get(route('konbini.show', ['北海道', '富良野市', $poi->id]))
        ->assertOk()
        ->assertDontSee('この付近に他のコンビニはありません')
        ->assertDontSee('次のコンビニ');
});

it('本物の離島（計算済み＆近傍なし）は「他にありません」を出す', function () {
    $poi = konPoi(['nearest_computed_at' => now(), 'nearest_same_type_id' => null, 'nearest_same_type_m' => null]);
    seedKonbiniCache($poi);

    $this->get(route('konbini.show', ['北海道', '富良野市', $poi->id]))
        ->assertOk()
        ->assertSee('この付近に他のコンビニはありません');
});

it('孤立コンビニ（>=3km）は「この先、次のコンビニまで約◯km」を出す', function () {
    $neighbor = konPoi(['name' => '隣コンビニ', 'city' => '美瑛町', 'municipality_code' => '01459']);
    $poi = konPoi([
        'nearest_computed_at' => now(),
        'nearest_same_type_id' => $neighbor->id,
        'nearest_same_type_m' => 4500,
    ]);
    seedKonbiniCache($poi);

    $this->get(route('konbini.show', ['北海道', '富良野市', $poi->id]))
        ->assertOk()
        ->assertSee('この先、次のコンビニまで約4.5km');
});

it('都市部（<3km）は「次のコンビニまで」を出さない', function () {
    $neighbor = konPoi(['name' => '近所コンビニ']);
    $poi = konPoi([
        'nearest_computed_at' => now(),
        'nearest_same_type_id' => $neighbor->id,
        'nearest_same_type_m' => 200,
    ]);
    seedKonbiniCache($poi);

    $this->get(route('konbini.show', ['北海道', '富良野市', $poi->id]))
        ->assertOk()
        ->assertDontSee('次のコンビニ');
});

it('brandから見出しを組む行は正規化名＋町名、name のある行はそのまま（name内は触らない）', function () {
    // name 無し・brand のみ → 正規化名(7-ELEVEN→セブン-イレブン)＋町名で一意化。
    $bare = konPoi(['name' => null, 'brand' => '7-ELEVEN', 'address' => '北海道富良野市朝日町1-1']);
    // name に具体的店名 → そのまま（町名併記もしない・name内置換もしない）。
    $named = konPoi(['name' => 'セブンイレブン 富良野朝日町店', 'brand' => '7-ELEVEN', 'address' => '北海道富良野市朝日町1-1']);
    seedKonbiniCache($bare);
    seedKonbiniCache($named);

    $this->get(route('konbini.show', ['北海道', '富良野市', $bare->id]))
        ->assertOk()->assertSee('セブン-イレブン（朝日町）');

    $this->get(route('konbini.show', ['北海道', '富良野市', $named->id]))
        ->assertOk()->assertSee('セブンイレブン 富良野朝日町店')->assertDontSee('富良野朝日町店（');
});

it('トイレの有無は書かない', function () {
    $poi = konPoi(['nearest_computed_at' => now(), 'nearest_same_type_id' => null]);
    seedKonbiniCache($poi);

    $this->get(route('konbini.show', ['北海道', '富良野市', $poi->id]))
        ->assertOk()->assertDontSee('トイレあり');
});
