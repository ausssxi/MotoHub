<?php

declare(strict_types=1);

use App\Http\Controllers\Poi\PoiAreaController;
use App\Models\Poi;
use App\Support\PoiDisplayResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** @param array<string,mixed> $attrs */
function drPoi(array $attrs): Poi
{
    return (new Poi)->forceFill($attrs);
}

/** 詳細ページと同じ結果か（PoiAreaController::resolveDisplay をリフレクションで叩く）。 */
function controllerResolveDisplay(Poi $poi, string $type, string $pref, string $city): string
{
    $c = new PoiAreaController;
    $m = new ReflectionMethod($c, 'resolveDisplay');
    $m->setAccessible(true);

    return $m->invoke($c, $poi, $type, $pref, $city, null);
}

$pref = '東京都';
$city = '千代田区';
$addr = '東京都千代田区丸の内'; // townPart => 丸の内

it('normalizes a bare brand name and appends the town (GS)', function () use ($pref, $city, $addr) {
    $r = new PoiDisplayResolver;
    $poi = drPoi(['type' => 'gas_station', 'name' => 'エネオス', 'address' => $addr]);

    expect($r->resolve($poi, 'gas_station', $pref, $city))->toBe('ENEOS（丸の内）');
});

it('keeps a specific store name untouched', function () use ($pref, $city, $addr) {
    $r = new PoiDisplayResolver;
    $poi = drPoi(['type' => 'gas_station', 'name' => 'エネオス安波給油所', 'address' => $addr]);

    expect($r->resolve($poi, 'gas_station', $pref, $city))->toBe('エネオス安波給油所');
});

it('falls back to the normalized brand + town when name is empty', function () use ($pref, $city, $addr) {
    $r = new PoiDisplayResolver;

    expect($r->resolve(drPoi(['type' => 'gas_station', 'brand' => 'エネオス', 'address' => $addr]), 'gas_station', $pref, $city))
        ->toBe('ENEOS（丸の内）');
    expect($r->resolve(drPoi(['type' => 'convenience_store', 'brand' => '7-ELEVEN', 'address' => $addr]), 'convenience_store', $pref, $city))
        ->toBe('セブン-イレブン（丸の内）');
});

it('builds a car-wash facility label when there is no name/brand', function () use ($pref, $city, $addr) {
    $r = new PoiDisplayResolver;
    $poi = drPoi(['type' => 'car_wash', 'address' => $addr, 'self_service' => 'yes', 'automated' => 'yes']);

    expect($r->resolve($poi, 'car_wash', $pref, $city))->toBe('コイン洗車場（丸の内）');
});

it('matches PoiAreaController::resolveDisplay exactly (detail page unchanged)', function () use ($pref, $city, $addr) {
    $r = new PoiDisplayResolver;

    $cases = [
        ['gas_station', ['type' => 'gas_station', 'name' => 'エネオス', 'address' => $addr]],
        ['gas_station', ['type' => 'gas_station', 'name' => 'エネオス安波給油所', 'address' => $addr]],
        ['gas_station', ['type' => 'gas_station', 'brand' => 'エネオス', 'address' => $addr]],
        ['convenience_store', ['type' => 'convenience_store', 'brand' => '7-ELEVEN', 'address' => $addr]],
        ['car_wash', ['type' => 'car_wash', 'address' => $addr, 'self_service' => 'yes']],
        ['car_wash', ['type' => 'car_wash', 'name' => 'ガンガン洗車', 'address' => $addr]],
    ];

    foreach ($cases as [$type, $attrs]) {
        $poi = drPoi($attrs);
        expect($r->resolve($poi, $type, $pref, $city))
            ->toBe(controllerResolveDisplay($poi, $type, $pref, $city));
    }
});
