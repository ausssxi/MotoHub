<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─────────── /parking/area の登録カード（ショップ同型・クリック可能） ───────────

it('shows a clickable register card linking to parking.create on /parking/area', function () {
    $html = $this->get('/parking/area')->assertOk()->getContent();

    // カード全体が parking.create への <a>（クリック可能）
    expect($html)->toContain('href="'.route('parking.create').'"')
        ->toContain('停めた駐車場が見つからない？')
        ->toContain('掲載されていない駐車場を登録する');
});

it('renders the register card with build-present classes (not the purged bg-emerald-600)', function () {
    $html = $this->get('/parking/area')->assertOk()->getContent();

    expect($html)->toContain('bg-emerald-50');           // カード背景（コンパイル済みCSSに存在＝可視）
    expect(str_contains($html, 'bg-emerald-600'))->toBeFalse(); // パージ済みクラスは使わない
});

it('leaves no non-clickable orphan register text on /parking/area', function () {
    // 旧・宙に浮いたテキスト（div内の説明文）が残っていない
    $this->get('/parking/area')->assertOk()
        ->assertDontSee('まだ掲載されていない駐車場を登録できます。');
});

// ─────────── /parking/search 0件CTA の色修正 ───────────

it('renders the parking search zero-hit CTA as a solid green button', function () {
    $html = $this->get('/parking/search?q='.urlencode('存在しない駐車場XYZ'))->assertOk()->getContent();

    expect($html)->toContain('見つかりませんでした')
        ->toContain('bg-green-600')
        ->toContain('href="'.route('parking.create').'"');
    expect(str_contains($html, 'bg-emerald-600'))->toBeFalse();
});

// ─────────── 登録フローは auth（マップ/ショップと同じ流れ） ───────────

it('routes the register CTA to the auth-gated parking.create (guest → login)', function () {
    $this->get(route('parking.create'))->assertRedirect(route('login'));
});
