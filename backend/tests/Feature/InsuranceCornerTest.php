<?php

declare(strict_types=1);

use App\Models\BikeModel;
use App\Support\InsuranceClassifier;

/**
 * 保険コーナー Phase 1（/hoken ハブ＋車種ページ維持費ブロック）。
 * ★正直さ: 公的固定額のみ出典付きで表示・任意保険の額は創作しない・URL未設定でCTA非表示。
 */

// ---- 区分判定（排気量＋新基準原付） ----

it('classifies displacement into the correct insurance/tax bracket', function () {
    $cases = [
        [50, 'gentsuki_1', 2000],
        [90, 'gentsuki_2_90', 2000],
        [125, 'gentsuki_2_125', 2400],
        [250, 'kei_nirin', 3600],
        [400, 'kogata_nirin', 6000],
        [1000, 'kogata_nirin', 6000],
    ];
    foreach ($cases as [$cc, $key, $tax]) {
        $model = new BikeModel(['name' => "test-{$cc}", 'displacement' => $cc]);
        $b = InsuranceClassifier::bracketForModel($model);
        expect($b['key'])->toBe($key)
            ->and($b['tax'])->toBe($tax);
    }
});

it('treats a 新基準原付 model as 原付一種扱い regardless of its 125cc displacement', function () {
    $name = config('shinkijun.target_models')[0]; // 例: スーパーカブ110 lite
    $model = new BikeModel(['name' => $name, 'displacement' => 110]);
    $b = InsuranceClassifier::bracketForModel($model);

    expect($b['key'])->toBe('shinkijun')
        ->and($b['tax'])->toBe(2000)          // 原付一種と同じ
        ->and($b['family_tokuyaku'])->toBeTrue();
});

it('returns null when displacement is unknown (never guesses an amount)', function () {
    $model = new BikeModel(['name' => 'unknown', 'displacement' => 0]);
    expect(InsuranceClassifier::bracketForModel($model))->toBeNull();
});

// ---- 車種ページ維持費ブロック（partial） ----

it('renders the maintenance-cost block with the correct fixed amounts and no 任意保険 price', function () {
    $model = new BikeModel(['name' => 'テスト250', 'displacement' => 250]);
    $html = view('bikes.partials.maintenance-cost', ['model' => $model])->render();

    expect($html)->toContain('軽自動車税')
        ->toContain('3,600')                 // 軽二輪の税
        ->toContain('7,100')                 // 軽二輪 自賠責12ヶ月（公表固定額）
        ->toContain('最終確認')
        ->toContain('損害保険料率算出機構')     // 出典
        ->toContain('2026年11月')            // 改定注記
        ->toContain(route('hoken'))            // 任意保険はハブへ内部リンク
        ->toContain('一律の目安額はありません'); // 任意保険の額は出さない（実装の文言に一致）
});

it('shows 原付一種扱い + ファミリーバイク特約 for a 新基準原付 model page block', function () {
    $name = config('shinkijun.target_models')[0];
    $model = new BikeModel(['name' => $name, 'displacement' => 110]);
    $html = view('bikes.partials.maintenance-cost', ['model' => $model])->render();

    expect($html)->toContain('2,000')                 // 原付一種の税
        ->toContain('ファミリーバイク特約')
        ->toContain('原付一種として扱われます');
});

it('renders nothing when displacement is unknown (no fabricated data)', function () {
    $model = new BikeModel(['name' => 'unknown', 'displacement' => 0]);
    $html = trim(view('bikes.partials.maintenance-cost', ['model' => $model])->render());
    expect($html)->toBe('');
});

// ---- ハブ /hoken ----

it('renders the /hoken hub with the 早見表, FAQ schema and sources', function () {
    $html = $this->get(route('hoken'))->assertOk()->getContent();

    expect($html)->toContain('排気量別・固定費の早見表')
        ->toContain('2,000')                     // 原付一種 税
        ->toContain('6,910')                     // 原付 自賠責12ヶ月
        ->toContain('FAQPage')                   // FAQ schema
        ->toContain('BreadcrumbList')
        ->toContain('最終確認')
        ->toContain(route('shinkijun_gentsuki'))      // 内部リンク（ハブ→新基準原付）
        ->toContain(route('bikes.category_cc', '250')); // 内部リンク（ハブ→cc）
});

it('hides the affiliate CTA when no url is configured, and shows it with PR + sponsored rel when set', function () {
    // 未設定＝偽ボタンを出さない
    config(['insurance.affiliate.url' => '']);
    $html = $this->get(route('hoken'))->assertOk()->getContent();
    expect($html)->not->toContain('無料で一括見積もり');

    // 設定時のみ表示・PR表記＋rel="nofollow sponsored"
    config(['insurance.affiliate.url' => 'https://example.com/hoken-mitsumori']);
    $html2 = $this->get(route('hoken'))->assertOk()->getContent();
    expect($html2)->toContain('条件を入れて比較する（無料）')  // CTAラベル（相場表の直後・指示書の新文言）
        ->toContain('https://example.com/hoken-mitsumori')
        ->toContain('rel="nofollow sponsored noopener"')
        ->toContain('PR');
});

it('renders the voluntary-insurance market table: 3 displacement categories, 20等級 only for 30代+, sample-size notes for young riders', function () {
    $html = $this->get(route('hoken'))->assertOk()->getContent();

    // 相場表が3排気量区分すべて描画されること
    expect($html)->toContain('任意保険の相場（年齢・等級別）')
        ->toContain('125cc以下')
        ->toContain('125cc超〜250cc以下')
        ->toContain('250cc超');

    // 6等級は全年齢帯に値がある（若年含む）
    expect($html)->toContain('81,092')   // 125cc以下・20歳以下・新規6等級
        ->toContain('140,629');          // 250cc超・20歳以下・新規6等級

    // 20等級は30代以上にしか値を持たない → 30代の値は描画される
    expect($html)->toContain('11,656')   // 125cc以下・30代・20等級
        ->toContain('14,680');           // 250cc超・30代・20等級

    // 個数カウントは相場表セクションだけに限定する。ページ全体を数えると、早見表など他ブロックの
    // 「—」（例: PR2で追加予定の自賠責改定・小型二輪48/60ヶ月セル）を巻き込んで壊れるため、
    // 見出し「任意保険の相場」〜次セクション「任意保険の選び方の要点」の範囲を切り出して数える。
    $start = mb_strpos($html, '任意保険の相場（年齢・等級別）');
    $end = mb_strpos($html, '任意保険の選び方の要点');
    expect($start)->not->toBeFalse()
        ->and($end)->not->toBeFalse();
    $marketSection = mb_substr($html, $start, $end - $start);

    // 若年（20歳以下・21〜25歳・26〜29歳）の20等級セルは「—」。3区分×3行＝ちょうど9個。
    // （若年20等級に数値が混じったり、30代以上が「—」になれば個数が変わり検知できる）
    expect(substr_count($marketSection, '—'))->toBe(9);

    // 「※サンプル少」は 20歳以下・21〜25歳のみ。3区分×2行＝ちょうど6個。
    expect(substr_count($marketSection, '※サンプル少'))->toBe(6);
});

it('renders the family-bike-tokuyaku cost comparison amounts', function () {
    $html = $this->get(route('hoken'))->assertOk()->getContent();

    expect($html)->toContain('約10,000円')   // ファミリーバイク特約・自損事故型
        ->toContain('約30,000円')            // ファミリーバイク特約・人身傷害型
        ->toContain('約40,000円');           // バイク保険単体（20代）
});

it('links from the shinkijun hub to the insurance hub (bidirectional)', function () {
    $html = $this->get(route('shinkijun_gentsuki'))->assertOk()->getContent();
    expect($html)->toContain(route('hoken'));
});
