<?php

declare(strict_types=1);

use App\Models\Poi;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * 市区町村一覧（poi_area/city）から詳細ページ(gs.show/konbini.show/senshajo.show)への内部リンク。
 * nearbyPois() の空間クエリ（SQLite不可）を避けるため各市に10件以上作る。
 */

uses(RefreshDatabase::class);

$clOsm = 0;

function clPoi(string $type, string $prefecture, string $city, string $code): Poi
{
    global $clOsm;
    $clOsm++;

    return Poi::forceCreate([
        'osm_id' => 5000 + $clOsm,
        'type' => $type,
        'name' => "P{$clOsm}",
        'latitude' => 40.8 + $clOsm * 0.001,
        'longitude' => 140.3,
        'prefecture' => $prefecture,
        'city' => $city,
        'municipality_code' => $code,
    ]);
}

it('GS市区町村一覧は各行を gs.show へリンクする', function () {
    for ($i = 0; $i < 11; $i++) {
        clPoi('gas_station', '青森県', 'つがる市', '02209');
    }
    $target = Poi::where('type', 'gas_station')->first();

    $this->get(route('gs.city', ['青森県', 'つがる市']))
        ->assertOk()
        ->assertSee(route('gs.show', ['青森県', 'つがる市', $target->id]), false);
});

it('コンビニ市区町村一覧は各行を konbini.show へリンクする', function () {
    for ($i = 0; $i < 11; $i++) {
        clPoi('convenience_store', '青森県', 'つがる市', '02209');
    }
    $target = Poi::where('type', 'convenience_store')->first();

    $this->get(route('konbini.city', ['青森県', 'つがる市']))
        ->assertOk()
        ->assertSee(route('konbini.show', ['青森県', 'つがる市', $target->id]), false);
});

it('洗車場一覧は従来どおり senshajo.show へリンクする（回帰）', function () {
    for ($i = 0; $i < 11; $i++) {
        clPoi('car_wash', '青森県', 'つがる市', '02209');
    }
    $target = Poi::where('type', 'car_wash')->first();

    $this->get(route('senshajo.city', ['青森県', 'つがる市']))
        ->assertOk()
        ->assertSee(route('senshajo.show', ['青森県', 'つがる市', $target->id]), false);
});
