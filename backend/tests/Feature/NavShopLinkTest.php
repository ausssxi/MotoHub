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

it('drops the 相場/その他 header menus straight below their trigger (left-aligned)', function () {
    $html = $this->get('/')->assertOk()->getContent();

    // トリガーの外側コンテナに relative（絶対配置の基準＝トリガー自身）
    expect(substr_count($html, 'hidden md:flex relative'))->toBeGreaterThanOrEqual(2);

    // 相場・その他のドロップダウン本体だけが真下・左揃え（absolute left-0 top-full）で出る
    // （通知ベル/アカウントメニューの right-0 は右端配置で妥当なため対象外＝2件ちょうど）
    expect(substr_count($html, 'absolute left-0 top-full mt-1 w-48'))->toBe(2);
});
