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

// ─────────── A: feedback イベント ＋ card ───────────

it('stores a feedback event with yes/no, card, symptom and verdict', function () {
    foreach (['yes', 'no'] as $ans) {
        $this->post('/trouble/track', [
            'session_id' => SID, 'event' => 'feedback',
            'symptom' => 'battery', 'card' => 'battery', 'verdict' => 'diy', 'answer' => $ans,
        ])->assertNoContent();
    }

    $rows = TroubleEvent::where('event', 'feedback')->get();
    expect($rows)->toHaveCount(2)
        ->and($rows->pluck('answer')->all())->toContain('yes', 'no')
        ->and($rows->first()->card)->toBe('battery')
        ->and($rows->first()->verdict)->toBe('diy');
});

it('accepts feedback only with a yes/no answer (others null)', function () {
    $this->post('/trouble/track', [
        'session_id' => SID, 'event' => 'feedback', 'symptom' => 'battery', 'card' => 'battery', 'answer' => 'maybe',
    ])->assertNoContent();

    $row = TroubleEvent::where('event', 'feedback')->sole();
    expect($row->answer)->toBeNull()      // 不正値は null 落とし
        ->and($row->card)->toBe('battery'); // 他フィールドは保存
});

it('records card on verdict_shown and cta_clicked, nulling non-card values', function () {
    $this->post('/trouble/track', ['session_id' => SID, 'event' => 'verdict_shown', 'symptom' => 'battery', 'card' => 'battery', 'verdict' => 'diy'])->assertNoContent();
    $this->post('/trouble/track', ['session_id' => SID, 'event' => 'cta_clicked', 'card' => 'plug', 'cta' => 'parts'])->assertNoContent();
    $this->post('/trouble/track', ['session_id' => SID, 'event' => 'verdict_shown', 'card' => 'not-a-card'])->assertNoContent();

    expect(TroubleEvent::where('event', 'verdict_shown')->where('card', 'battery')->exists())->toBeTrue()
        ->and(TroubleEvent::where('event', 'cta_clicked')->where('card', 'plug')->exists())->toBeTrue()
        ->and(TroubleEvent::where('event', 'verdict_shown')->whereNull('card')->where('created_at', '>=', now()->subMinute())->exists())->toBeTrue();
});

it('keeps feedback in the event whitelist alongside the original four', function () {
    expect(TroubleEvent::EVENTS)->toContain('symptom_selected', 'step_answered', 'verdict_shown', 'cta_clicked', 'feedback');
});

// ─────────── B: report ⑤ 解決フィードバック ───────────

it('reports solution-feedback positive rate per symptom/card with distinct sessions', function () {
    // battery/battery: sessionA yes(x2=distinct1), sessionB yes, sessionC no → yes2 no1 = 66.7%
    TroubleEvent::create(['session_id' => 'aaaaaaaa-0000-0000-0000-000000000001', 'event' => 'feedback', 'symptom' => 'battery', 'card' => 'battery', 'verdict' => 'diy', 'answer' => 'yes', 'created_at' => now()]);
    TroubleEvent::create(['session_id' => 'aaaaaaaa-0000-0000-0000-000000000001', 'event' => 'feedback', 'symptom' => 'battery', 'card' => 'battery', 'verdict' => 'diy', 'answer' => 'yes', 'created_at' => now()]); // 同一session連打
    TroubleEvent::create(['session_id' => 'bbbbbbbb-0000-0000-0000-000000000002', 'event' => 'feedback', 'symptom' => 'battery', 'card' => 'battery', 'verdict' => 'diy', 'answer' => 'yes', 'created_at' => now()]);
    TroubleEvent::create(['session_id' => 'cccccccc-0000-0000-0000-000000000003', 'event' => 'feedback', 'symptom' => 'battery', 'card' => 'battery', 'verdict' => 'diy', 'answer' => 'no', 'created_at' => now()]);

    Illuminate\Support\Facades\Artisan::call('trouble:report', ['--days' => 1]);
    $out = Illuminate\Support\Facades\Artisan::output();

    expect($out)->toContain('解決フィードバック')
        ->and($out)->toContain('66.7%'); // distinct yes2 / (yes2+no1)
});

// ─────────── B: config article_anchor ───────────

it('has article_anchor only on cards with a clear target section', function () {
    // fuel_carb は専用記事 /blog/gentsuki-fuel-carb 公開に伴いアンカー 'additive' を復活
    expect(config('diagnosis.cards.fuel_carb.article'))->toBe('/blog/gentsuki-fuel-carb')
        ->and(config('diagnosis.cards.fuel_carb.article_anchor'))->toBe('additive');

    foreach (['battery', 'tire', 'fuel_carb', 'drivetrain', 'air_filter', 'cold', 'oil', 'headlight'] as $card) {
        expect(config("diagnosis.cards.{$card}.article_anchor"))->not->toBeNull("card {$card} should have an anchor");
    }
    foreach (['plug', 'switch', 'gas_empty', 'starter', 'unknown'] as $card) {
        expect(config("diagnosis.cards.{$card}.article_anchor"))->toBeNull("card {$card} should NOT have an anchor");
    }
});

// ─────────── A-3/B-2: result screen render ───────────

it('renders the feedback block and anchored article link builder', function () {
    $res = $this->get('/trouble')->assertOk();

    // フィードバックUI
    $res->assertSee('この診断で解決できましたか？');
    $res->assertSee("feedback('yes')", false);
    $res->assertSee("feedback('no')", false);
    // アンカー付き記事リンクの組み立て
    $res->assertSee('card.article_anchor', false);
    $res->assertSee('"article_anchor":"fix"', false); // config JSON に乗る（battery等）
});

// ─────────── A: 新症状 lights / stranded（孤児カード露出）───────────

it('exposes 8 symptoms including lights and stranded', function () {
    $symptoms = config('diagnosis.symptoms');
    expect($symptoms)->toHaveCount(8)
        ->and($symptoms)->toHaveKeys(['lights', 'stranded'])
        ->and($symptoms['lights']['root'])->toBe('lights__scope')
        ->and($symptoms['stranded']['root'])->toBe('stranded__safety');
});

it('reaches the previously-orphan cards from the new trees + oil from accel', function () {
    $cardOf = fn (string $node) => array_column(config("diagnosis.nodes.{$node}.options"), 'card');
    expect($cardOf('lights__scope'))->toContain('headlight', 'battery', 'switch')
        ->and($cardOf('stranded__safety'))->toContain('seizure', 'roadside', 'gas_empty')
        ->and($cardOf('accel__cause'))->toContain('oil');

    // すべての card が nodes から到達可能（孤児ゼロ）
    $reachable = [];
    foreach (config('diagnosis.nodes') as $node) {
        foreach ($node['options'] as $opt) {
            if (isset($opt['card'])) {
                $reachable[$opt['card']] = true;
            }
        }
    }
    $orphans = array_diff(array_keys(config('diagnosis.cards')), array_keys($reachable));
    expect($orphans)->toBeEmpty();
});

it('accepts the new symptom slugs at /trouble/track (dynamic whitelist)', function () {
    foreach (['lights', 'stranded'] as $slug) {
        $this->post('/trouble/track', ['session_id' => SID, 'event' => 'symptom_selected', 'symptom' => $slug, 'source' => 'deeplink'])
            ->assertNoContent();
    }
    expect(TroubleEvent::whereIn('symptom', ['lights', 'stranded'])->count())->toBe(2);
});

it('includes new symptoms in trouble:report', function () {
    TroubleEvent::create(['session_id' => SID, 'event' => 'symptom_selected', 'symptom' => 'lights', 'created_at' => now()]);
    TroubleEvent::create(['session_id' => SID, 'event' => 'verdict_shown', 'symptom' => 'lights', 'card' => 'headlight', 'verdict' => 'diy_then_shop', 'created_at' => now()]);

    Illuminate\Support\Facades\Artisan::call('trouble:report', ['--days' => 1]);
    expect(Illuminate\Support\Facades\Artisan::output())->toContain('lights');
});

it('passes 8 symptoms to the client and deep-links the new symptoms', function () {
    // 症状ラベルは @json 経由でクライアント描画されるため、キー（ASCII）で存在確認
    $res = $this->get('/trouble')->assertOk();
    $res->assertSee('"lights"', false);
    $res->assertSee('"stranded"', false);
    $res->assertSee('"lights__scope"', false);
    $res->assertSee('"stranded__safety"', false);

    $this->get('/trouble?symptom=lights')->assertOk();
    $this->get('/trouble?symptom=stranded')->assertOk();
});

// ─────────── Phase2: 焼き付き入口の新設（start__crank）───────────

it('splits start__crank into battery/starter/seizure/fuel in order', function () {
    $dest = array_map(
        fn ($o) => $o['card'] ?? ('next:'.$o['next']),
        config('diagnosis.nodes.start__crank.options')
    );
    // 1→battery / 2→starter / 3→seizure / 4→燃料(キュルキュル) / 5→燃料(キックのみ車)
    expect($dest)->toBe(['battery', 'starter', 'seizure', 'next:start__fuel', 'next:start__fuel']);
});

it('reaches seizure from BOTH start__crank and stranded (二重経路の整合)', function () {
    $fromCrank = collect(config('diagnosis.nodes.start__crank.options'))->pluck('card')->contains('seizure');
    $fromStranded = collect(config('diagnosis.nodes.stranded__safety.options'))->pluck('card')->contains('seizure');
    expect($fromCrank)->toBeTrue()->and($fromStranded)->toBeTrue();
});

it('keeps the 3-point seizure discriminator and the limited starter wording', function () {
    $opts = collect(config('diagnosis.nodes.start__crank.options'));
    $seizure = $opts->firstWhere('card', 'seizure')['label'];
    $starter = $opts->firstWhere('card', 'starter')['label'];

    // 異音・急停止・重い/回らない の3点を薄めない
    expect($seizure)->toContain('異音')->toContain('急に止')->toContain('重い')
        // starter は「無音・無反応・押し歩きは重くない」に限定（焼き付きと分離）
        ->and($starter)->toContain('無音・無反応')->toContain('押し歩きは重くない');
});

it('does not change the existing battery/starter/fuel landings of start__crank', function () {
    $opts = collect(config('diagnosis.nodes.start__crank.options'));
    expect($opts->where('card', 'battery')->count())->toBe(1)   // カチカチ→battery 据え置き
        ->and($opts->where('card', 'starter')->count())->toBe(1) // 無反応→starter 据え置き
        ->and($opts->firstWhere('next', 'start__fuel'))->not->toBeNull(); // キュルキュル→燃料 据え置き
});

// ─────────── 第2弾: かからない系カードの本文強化 ＋ cold→battery ───────────

it('strengthens the かからない系 card advice into multi-section text (verdicts unchanged)', function () {
    foreach (['battery', 'starter', 'fuel_carb', 'plug', 'switch', 'cold'] as $c) {
        expect(config("diagnosis.cards.{$c}.advice"))->toContain("\n"); // 複数ブロック化
    }
    // verdict は不変
    expect(config('diagnosis.cards.battery.verdict'))->toBe('diy')
        ->and(config('diagnosis.cards.starter.verdict'))->toBe('shop')
        ->and(config('diagnosis.cards.fuel_carb.verdict'))->toBe('diy_then_shop')
        ->and(config('diagnosis.cards.plug.verdict'))->toBe('check_then_shop')
        ->and(config('diagnosis.cards.switch.verdict'))->toBe('diy')
        ->and(config('diagnosis.cards.cold.verdict'))->toBe('diy');
});

it('keeps the safety phrases in the advice (dilution guard)', function () {
    expect(config('diagnosis.cards.fuel_carb.advice'))->toContain('火気厳禁') // ガソリンは保守的
        ->and(config('diagnosis.cards.battery.advice'))->toContain('ショート') // 端子ショート注意
        ->and(config('diagnosis.cards.plug.advice'))->toContain('締めすぎ')    // ネジ山注意
        ->and(config('diagnosis.cards.starter.advice'))->toContain('無理せず店へ');
});

it('keeps seizure(Phase1) advice untouched', function () {
    expect(config('diagnosis.cards.seizure.advice'))->toContain('【すぐに】')->toContain('【費用の目安】');
});

// ─────────── 取りこぼし救済: キックのみ車 ＋「かかりにくい」 ───────────

it('adds a kick-only path from start__crank to start__fuel (not battery/starter)', function () {
    $kick = collect(config('diagnosis.nodes.start__crank.options'))
        ->first(fn ($o) => str_contains($o['label'] ?? '', 'キックのみ'));
    expect($kick)->not->toBeNull()
        ->and($kick['next'] ?? null)->toBe('start__fuel') // 燃料・プラグ系へ
        ->and($kick['card'] ?? null)->toBeNull();          // セル/バッテリー始動系へ誤誘導しない
});

it('keeps the existing crank landings intact and appends the kick path (regression)', function () {
    $dest = collect(config('diagnosis.nodes.start__crank.options'))
        ->map(fn ($o) => $o['card'] ?? ('next:'.$o['next']))->all();
    // battery/starter/seizure/→fuel(キュルキュル) 不変、末尾にキック→fuel が増えるのみ
    expect($dest)->toBe(['battery', 'starter', 'seizure', 'next:start__fuel', 'next:start__fuel']);
});

it('keeps start__fuel neutral (fuel_carb/plug/unknown・kick cars can answer)', function () {
    $dest = collect(config('diagnosis.nodes.start__fuel.options'))->pluck('card')->all();
    expect($dest)->toBe(['fuel_carb', 'plug', 'unknown']);
});

it('adds the かかりにくい guidance to start__gate help', function () {
    expect(config('diagnosis.nodes.start__gate.help'))->toContain('かかりにくい')->toContain('かかっても不安定');
});
