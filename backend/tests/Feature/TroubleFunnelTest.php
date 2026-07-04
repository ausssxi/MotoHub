<?php

use App\Models\TroubleEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

const SID = '11111111-2222-3333-4444-555555555555';

// ─────────── A: track endpoint ───────────

it('stores the four valid event types', function () {
    $events = [
        ['event' => 'symptom_selected', 'symptom' => 'battery', 'source' => 'deeplink'],
        ['event' => 'step_answered', 'symptom' => 'battery', 'step' => 'batt__symptom', 'answer' => 'battery'],
        ['event' => 'verdict_shown', 'symptom' => 'battery', 'step' => 'battery', 'verdict' => 'diy'],
        ['event' => 'cta_clicked', 'symptom' => 'battery', 'verdict' => 'diy', 'cta' => 'parts'],
    ];

    foreach ($events as $payload) {
        $this->post('/trouble/track', array_merge(['session_id' => SID], $payload))
            ->assertNoContent(); // 204
    }

    expect(TroubleEvent::count())->toBe(4);
    expect(TroubleEvent::where('event', 'symptom_selected')->first()->source)->toBe('deeplink');
    expect(TroubleEvent::where('event', 'cta_clicked')->first()->cta)->toBe('parts');
});

it('swallows an out-of-whitelist event without storing (204)', function () {
    $this->post('/trouble/track', ['session_id' => SID, 'event' => 'hacker_event'])
        ->assertNoContent();

    expect(TroubleEvent::count())->toBe(0);
});

it('nulls out out-of-whitelist field values but still stores a valid event', function () {
    $this->post('/trouble/track', [
        'session_id' => SID,
        'event' => 'verdict_shown',
        'symptom' => 'not-a-real-symptom',
        'verdict' => 'not-a-real-verdict',
        'cta' => 'evil',
    ])->assertNoContent();

    $row = TroubleEvent::sole();
    expect($row->event)->toBe('verdict_shown')
        ->and($row->symptom)->toBeNull()
        ->and($row->verdict)->toBeNull()
        ->and($row->cta)->toBeNull();
});

it('requires a valid uuid session_id', function () {
    $this->post('/trouble/track', ['session_id' => 'nope', 'event' => 'symptom_selected', 'symptom' => 'battery'])
        ->assertNoContent();

    expect(TroubleEvent::count())->toBe(0);
});

it('sanitizes the answer field to a short token', function () {
    $this->post('/trouble/track', [
        'session_id' => SID, 'event' => 'step_answered', 'symptom' => 'battery',
        'step' => 'batt__symptom', 'answer' => '<script>x</script>',
    ])->assertNoContent();

    // 記号は除去され英数のみ残る（PIIになり得ない）
    expect(TroubleEvent::sole()->answer)->toBe('scriptxscript');
});

it('rate-limits the track route (throttle:60,1)', function () {
    $middleware = Route::getRoutes()->getByName('trouble.track')->gatherMiddleware();
    expect($middleware)->toContain('throttle:60,1');
});

// ─────────── A-4: prune ───────────

it('prunes only rows older than 180 days', function () {
    TroubleEvent::create(['session_id' => SID, 'event' => 'symptom_selected', 'created_at' => now()->subDays(200)]);
    TroubleEvent::create(['session_id' => SID, 'event' => 'symptom_selected', 'created_at' => now()->subDays(10)]);

    $this->artisan('trouble:prune')->assertSuccessful();

    expect(TroubleEvent::count())->toBe(1);
});

// ─────────── B: report ───────────

it('reports completion rate and cta ctr correctly', function () {
    // battery: 3 selected (1 deeplink), 2 verdict_shown(diy), 1 parts click
    TroubleEvent::create(['session_id' => SID, 'event' => 'symptom_selected', 'symptom' => 'battery', 'source' => 'deeplink', 'created_at' => now()]);
    TroubleEvent::create(['session_id' => SID, 'event' => 'symptom_selected', 'symptom' => 'battery', 'created_at' => now()]);
    TroubleEvent::create(['session_id' => SID, 'event' => 'symptom_selected', 'symptom' => 'battery', 'created_at' => now()]);
    TroubleEvent::create(['session_id' => SID, 'event' => 'verdict_shown', 'symptom' => 'battery', 'verdict' => 'diy', 'created_at' => now()]);
    TroubleEvent::create(['session_id' => SID, 'event' => 'verdict_shown', 'symptom' => 'battery', 'verdict' => 'diy', 'created_at' => now()]);
    TroubleEvent::create(['session_id' => SID, 'event' => 'cta_clicked', 'symptom' => 'battery', 'verdict' => 'diy', 'cta' => 'parts', 'created_at' => now()]);

    $code = Illuminate\Support\Facades\Artisan::call('trouble:report', ['--days' => 1]);
    $out = Illuminate\Support\Facades\Artisan::output();

    expect($code)->toBe(0)
        ->and($out)->toContain('battery')
        ->and($out)->toContain('66.7%') // 完走率 2/3
        ->and($out)->toContain('50%');  // CTA CTR 1/2
});

// ─────────── C: parts mapping ───────────

it('has parts fields only on sellable causes', function () {
    // 売れる部品のある原因
    expect(config('diagnosis.cards.battery.parts_keyword'))->toBe('バイク バッテリー')
        ->and(config('diagnosis.cards.plug.parts_keyword'))->not->toBeNull()
        ->and(config('diagnosis.cards.tire.parts_keyword'))->not->toBeNull()
        ->and(config('diagnosis.cards.oil.parts_keyword'))->not->toBeNull();

    // 売るものが無い原因はフィールドを持たない
    expect(config('diagnosis.cards.gas_empty.parts_keyword'))->toBeNull()
        ->and(config('diagnosis.cards.switch.parts_keyword'))->toBeNull()
        ->and(config('diagnosis.cards.unknown.parts_keyword'))->toBeNull();
});

// ─────────── D + regression: page render ───────────

it('renders /trouble with tracking, deeplink support, parts CTA and existing CTAs', function () {
    $res = $this->get('/trouble')->assertOk();

    // 計測基盤（URLは @js でスラッシュがエスケープされるため変数名で確認）
    $res->assertSee('window.__troubleTrackUrl', false);
    $res->assertSee('sendBeacon', false);
    $res->assertSee("params.get('symptom')", false); // ?symptom= ディープリンク
    // パーツCTA（config JSON に parts_keyword が乗る／compareリンク・CTA計測）
    $res->assertSee('parts_keyword', false);
    $res->assertSee('encodeURIComponent(card.parts_keyword)', false);
    $res->assertSee("trackCta('parts')", false);
    // 既存CTA（回帰）— href は素の Blade 出力なのでルートそのもので確認
    $res->assertSee(route('shops.repair.index'), false);
    $res->assertSee('近くの整備・修理店を探す');
});

it('renders /trouble with a deeplink symptom without error', function () {
    $this->get('/trouble?symptom=engine-wont-start')->assertOk();
    $this->get('/trouble?symptom=___invalid___')->assertOk(); // 不正スラッグでもエラーにしない
});
