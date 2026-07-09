<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─────────── (a) 初回オンボーディングカード ───────────

it('renders the onboarding card copy and both CTAs', function () {
    $res = $this->get('/riders-map')->assertOk();
    $res->assertSee('このマップでできること')
        ->assertSee('バイク駐車場・給油ポイント・道の駅・ツーリング記事が地図に重なります')
        ->assertSee('AIにルートを提案してもらう')
        ->assertSee('ツーリング記事から探す');
});

it('fires the existing AI-route action (no new function) and links the article CTA to /touring', function () {
    $res = $this->get('/riders-map')->assertOk();
    // 主CTAは既存 #btn-ai-route をクリックするだけ（新規関数を作っていない）
    $res->assertSee("getElementById('btn-ai-route').click()", false)
        ->assertSee('id="btn-ai-route"', false)          // 発火対象が実在
        ->assertSee(route('touring.index'), false);      // 従CTAは /touring へ
});

it('uses the infinite-dismiss localStorage key for the onboarding card', function () {
    $this->get('/riders-map')->assertOk()
        ->assertSee('riders_map_onboarding_dismissed_at', false);
});

// ─────────── (b) 常設の使い方セクション（常時DOM・SEO） ───────────

it('always renders the usage section body in the DOM (not display:none)', function () {
    $res = $this->get('/riders-map')->assertOk();
    $res->assertSee('ライダーズマップの使い方')                                   // h2
        ->assertSee('ツーリングの計画から当日のナビまでを1枚の地図で完結')       // 本文
        ->assertSee('表示する情報を切り替える')                                   // h3
        ->assertSee('行き先を探す')
        ->assertSee('ルートを引く')
        ->assertSee('ツーリング記事から計画する')
        ->assertDontSee('style="display:none"', false);                          // 折りたたみでも display:none にしない
});

it('mentions ピン留め and ガイドを書く only as existence notes (not CTAs)', function () {
    $this->get('/riders-map')->assertOk()
        ->assertSee('「ピン留め」')
        ->assertSee('「ガイドを書く」機能もあります');
});

// ─────────── 回帰: 既存マップUIが不変 ───────────

it('keeps the existing map controls intact (regression)', function () {
    $res = $this->get('/riders-map')->assertOk();
    $res->assertSee('地名・住所で検索（例：渋谷、橋本駅）') // 検索プレースホルダ
        ->assertSee('AIルート提案')                        // 既存アクション
        ->assertSee('ルート作成')
        ->assertSee('id="map"', false)                     // 地図コンテナ
        ->assertSee('layers-changed', false);              // レイヤートグルのイベント
});
