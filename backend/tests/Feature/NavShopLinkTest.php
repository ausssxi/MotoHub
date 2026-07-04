<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows a ショップを探す nav link pointing to /shops/area in both PC and mobile nav', function () {
    $res = $this->get('/')->assertOk();

    // ラベルが存在し、/shops/area を指す
    $res->assertSee('ショップを探す');
    $res->assertSee('href="'.route('shops.area.index').'"', false);

    $html = $res->getContent();

    // PC ナビ（x-navigation）とモバイルナビ（x-bottom-nav）の両方に含まれる
    expect(substr_count($html, 'ショップを探す'))->toBeGreaterThanOrEqual(2);
    expect(substr_count($html, 'href="'.route('shops.area.index').'"'))->toBeGreaterThanOrEqual(2);
});

it('renders shop area/repair pages 200 with the nav present', function () {
    $this->get(route('shops.area.index'))->assertOk()->assertSee('ショップを探す');
    $this->get(route('shops.repair.index'))->assertOk()->assertSee('ショップを探す');
});
