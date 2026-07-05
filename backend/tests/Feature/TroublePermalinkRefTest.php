<?php

use App\Models\TroubleEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

const PR_SID = '11111111-2222-3333-4444-555555555555';

// ─────────── A: 結果パーマリンク（noindex ＋ 着地ロジック配線）───────────

it('renders and noindexes a card permalink landing (?symptom=&card=)', function () {
    $res = $this->get('/trouble?symptom=engine-wont-start&card=battery')->assertOk();
    $res->assertSee('content="noindex"', false);        // 薄ページ防止
    // カード着地ロジックが配線されている（Alpine init）
    $res->assertSee("params.get('card')", false);
    $res->assertSee("'deeplink_card'", false);
});

it('noindexes a card-only permalink (?card= without symptom)', function () {
    $this->get('/trouble?card=battery')->assertOk()->assertSee('content="noindex"', false);
});

it('does not error and still noindexes on an invalid card key', function () {
    // 不正 card でもサーバは200（JSがトップにフォールバック）。card付きなので noindex は出す。
    $this->get('/trouble?card=___invalid___')->assertOk()->assertSee('content="noindex"', false);
});

it('does NOT noindex the top page or a symptom-only deeplink', function () {
    $this->get('/trouble')->assertOk()->assertDontSee('content="noindex"', false);
    $this->get('/trouble?symptom=engine-wont-start')->assertOk()->assertDontSee('content="noindex"', false);
});

// ─────────── B: 入口別 ref 計測 ───────────

it('records ref on the track endpoint', function () {
    $this->post('/trouble/track', [
        'session_id' => PR_SID, 'event' => 'symptom_selected', 'symptom' => 'battery', 'ref' => 'article-77',
    ])->assertNoContent();

    expect(TroubleEvent::sole()->ref)->toBe('article-77');
});

it('still works without ref (backward compatible)', function () {
    $this->post('/trouble/track', [
        'session_id' => PR_SID, 'event' => 'symptom_selected', 'symptom' => 'battery',
    ])->assertNoContent();

    expect(TroubleEvent::sole()->ref)->toBeNull();
});

it('sanitizes ref (strips unsafe chars, caps length)', function () {
    $this->post('/trouble/track', [
        'session_id' => PR_SID, 'event' => 'symptom_selected', 'symptom' => 'battery',
        'ref' => '<x>drop"/'.str_repeat('a', 80),
    ])->assertNoContent();

    $ref = TroubleEvent::sole()->ref;
    expect($ref)->not->toContain('<')
        ->and($ref)->not->toContain('"')
        ->and(mb_strlen($ref))->toBeLessThanOrEqual(50);
});

it('accepts the deeplink_card source for card landings', function () {
    $this->post('/trouble/track', [
        'session_id' => PR_SID, 'event' => 'symptom_selected', 'symptom' => 'battery',
        'card' => 'battery', 'source' => 'deeplink_card',
    ])->assertNoContent();

    expect(TroubleEvent::sole()->source)->toBe('deeplink_card');
});

// ─────────── B: report ⑥ ───────────

it('adds a ref funnel section to trouble:report', function () {
    // ref=article-77 のセッションが 選択→判定表示 で完走
    TroubleEvent::create(['session_id' => PR_SID, 'event' => 'symptom_selected', 'symptom' => 'battery', 'ref' => 'article-77', 'created_at' => now()]);
    TroubleEvent::create(['session_id' => PR_SID, 'event' => 'verdict_shown', 'symptom' => 'battery', 'card' => 'battery', 'verdict' => 'diy', 'ref' => 'article-77', 'created_at' => now()]);
    // ref 無しの直接流入
    TroubleEvent::create(['session_id' => 'bbbbbbbb-0000-0000-0000-000000000002', 'event' => 'symptom_selected', 'symptom' => 'battery', 'created_at' => now()]);

    Artisan::call('trouble:report', ['--days' => 1]);
    $out = Artisan::output();

    expect($out)->toContain('入口別')
        ->and($out)->toContain('article-77')
        ->and($out)->toContain('(直接/不明)');
});
