<?php

use App\Models\BikeModel;
use App\Models\Manufacturer;
use App\Models\ModelFitment;
use App\Models\MyBike;
use App\Models\TroubleEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush(); // publishedFitmentModels のキャッシュがテスト間で残らないように
});

function flModel(string $name, string $slug): BikeModel
{
    $mfr = Manufacturer::firstWhere('slug', 'test-mfr');
    if (! $mfr) {
        $mfr = new Manufacturer(['slug' => 'test-mfr']);
        $mfr->name = 'テストメーカー';
        $mfr->save();
    }

    return BikeModel::create(['manufacturer_id' => $mfr->id, 'name' => $name, 'slug' => $slug]);
}

function flFitment(BikeModel $m, ?string $verifiedAt = '2026-07-01'): ModelFitment
{
    return ModelFitment::create([
        'bike_model_id' => $m->id, 'task' => 'battery', 'frame_code' => 'AF00',
        'recommended_part_no' => 'TEST-0000', 'verified_at' => $verifiedAt,
    ]);
}

// ─────────── 公開車種の注入 ───────────

it('injects only published (verified) models into the page', function () {
    $pub = flModel('公開車', 'pub-model');
    flFitment($pub); // verified
    $unpub = flModel('未検証車', 'unpub-model');
    flFitment($unpub, null); // verified_at null

    $res = $this->get('/trouble')->assertOk();
    $res->assertSee('"slug":"pub-model"', false);        // 公開車種は select データに乗る
    $res->assertDontSee('"slug":"unpub-model"', false);  // 未検証は fitment データに乗らない（navリンクは別物）
});

it('renders an empty published list when no verified models exist', function () {
    $m = flModel('未検証のみ', 'only-unverified');
    flFitment($m, null);

    $this->get('/trouble')->assertOk()->assertSee('models: {"battery":[]', false);
});

// ─────────── config ───────────

it('connects fitment_task on battery/plug/oil, and seizure→oil (prevention)', function () {
    expect(config('diagnosis.cards.battery.fitment_task'))->toBe('battery')
        ->and(config('diagnosis.cards.plug.fitment_task'))->toBe('plug')
        ->and(config('diagnosis.cards.oil.fitment_task'))->toBe('oil')
        ->and(config('diagnosis.cards.seizure.fitment_task'))->toBe('oil') // 焼き付き予防＝オイル
        ->and(config('diagnosis.cards.tire.fitment_task') ?? null)->toBeNull()
        ->and(config('diagnosis.cards.fuel_carb.fitment_task') ?? null)->toBeNull();
});

it('strengthens the seizure advice (3 sections) and keeps verdict=shop', function () {
    $advice = config('diagnosis.cards.seizure.advice');
    expect($advice)->toContain('【すぐに】')->toContain('【見分け】')->toContain('【費用の目安】')
        ->and($advice)->toContain("\n")                       // 改行を含む長文
        ->and($advice)->not->toContain('冷えたら')            // 再始動を促さない（安全設計）
        ->and(config('diagnosis.cards.seizure.verdict'))->toBe('shop'); // diy化しない
});

it('renders whitespace-pre-line on the advice block (multi-line advice) and task-neutral fitment copy', function () {
    $res = $this->get('/trouble')->assertOk();
    $res->assertSee('whitespace-pre-line', false);       // 改行反映CSS
    $res->assertSee('の適合情報を見る', false);           // 個人化リンクが task 非依存に
    $res->assertDontSee('適合バッテリーを見る', false);   // battery固定文言が消えた
});

// ─────────── 個人化（マイバイク一致）───────────

it('injects a personalized bike for a logged-in user whose mybike matches a published model', function () {
    $pub = flModel('公開車', 'pub-model');
    flFitment($pub);

    $user = User::factory()->create();
    MyBike::create(['user_id' => $user->id, 'bike_model_id' => $pub->id, 'name' => 'わたしの相棒']);

    $res = $this->actingAs($user)->get('/trouble')->assertOk();
    // userBikes[battery] に1件以上（entryは display_name から始まる）
    $res->assertSee('userBikes: {"battery":[{"display_name"', false);
});

it('falls back to empty userBikes when the mybike is not a published model', function () {
    flFitment(flModel('公開車', 'pub-model')); // 公開車種はある
    $unpub = flModel('未公開車', 'unpub-model'); // ユーザーの車は未公開

    $user = User::factory()->create();
    MyBike::create(['user_id' => $user->id, 'bike_model_id' => $unpub->id, 'name' => 'マイ車']);

    $this->actingAs($user)->get('/trouble')->assertOk()
        ->assertSee('userBikes: {"battery":[]', false);
});

it('has empty userBikes for guests', function () {
    flFitment(flModel('公開車', 'pub-model'));

    $this->get('/trouble')->assertOk()->assertSee('userBikes: []', false);
});

// ─────────── 計測 ───────────

it('accepts cta=fitment on the track endpoint', function () {
    expect(TroubleEvent::CTAS)->toContain('fitment');

    $this->post('/trouble/track', [
        'session_id' => '11111111-2222-3333-4444-555555555555',
        'event' => 'cta_clicked', 'symptom' => 'battery', 'card' => 'battery', 'verdict' => 'diy', 'cta' => 'fitment',
    ])->assertNoContent();

    expect(TroubleEvent::where('cta', 'fitment')->exists())->toBeTrue();
});

it('shows a fitment row in trouble:report', function () {
    TroubleEvent::create(['session_id' => '11111111-2222-3333-4444-555555555555', 'event' => 'verdict_shown', 'symptom' => 'battery', 'card' => 'battery', 'verdict' => 'diy', 'created_at' => now()]);
    TroubleEvent::create(['session_id' => '11111111-2222-3333-4444-555555555555', 'event' => 'cta_clicked', 'symptom' => 'battery', 'card' => 'battery', 'verdict' => 'diy', 'cta' => 'fitment', 'created_at' => now()]);

    Artisan::call('trouble:report', ['--days' => 1]);
    expect(Artisan::output())->toContain('fitment');
});
