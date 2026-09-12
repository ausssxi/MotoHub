<?php

declare(strict_types=1);

use App\Services\News\ModelImpactTitleBuilder;

// ─────────── build(): 4部構成と部品欠落 ───────────

it('builds the full 4-part title when every part is present', function () {
    $title = ModelImpactTitleBuilder::build(
        'Z900RS',
        '147.5',
        '886',
        'OVER Racingが新マフラー発売',
        '2026年8月',
    );

    expect($title)->toBe('Z900RSの中古相場、平均147.5万円・在庫886台｜OVER Racingが新マフラー発売（2026年8月）');
});

it('omits the numbers clause when numbers are missing', function () {
    $title = ModelImpactTitleBuilder::build('Z900RS', null, null, 'ポッシュフェイスが新カバー発売', '2026年8月');

    expect($title)->toBe('Z900RSの中古相場｜ポッシュフェイスが新カバー発売（2026年8月）');
});

it('omits the trigger clause when trigger cannot be summarized', function () {
    $title = ModelImpactTitleBuilder::build('Z900RS', '147.5', '886', null, '2026年8月');

    expect($title)->toBe('Z900RSの中古相場、平均147.5万円・在庫886台（2026年8月）');
});

it('omits both when numbers and trigger are missing', function () {
    $title = ModelImpactTitleBuilder::build('PCX', null, null, '', '2026年9月');

    expect($title)->toBe('PCXの中古相場（2026年9月）');
});

it('returns null when the model name is missing so no article is produced', function () {
    expect(ModelImpactTitleBuilder::build(null, '147.5', '886', 'trigger', '2026年8月'))->toBeNull();
    expect(ModelImpactTitleBuilder::build('   ', '147.5', '886', 'trigger', '2026年8月'))->toBeNull();
});

it('never leaks placeholder characters like ～ or blanks into the title', function () {
    foreach ([
        ModelImpactTitleBuilder::build('Ninja 250', null, null, '〜', '2026年8月'),
        ModelImpactTitleBuilder::build('Ninja 250', '', '', '  ', '2026年8月'),
        ModelImpactTitleBuilder::build('Ninja 250', '0', '0', '注目の', '2026年8月'),
    ] as $title) {
        expect($title)->not->toContain('〜');
        expect($title)->not->toContain('～');
        expect($title)->not->toContain('｜（');
        expect($title)->not->toContain('平均万円');
        expect($title)->not->toContain('在庫台');
    }
});

it('treats zero numbers as missing', function () {
    $title = ModelImpactTitleBuilder::build('Ninja 250', '0', '0', null, '2026年8月');

    expect($title)->toBe('Ninja 250の中古相場（2026年8月）');
});

// ─────────── buildNewModel(): 真の新型のときだけ ───────────

it('builds the new-model title with the current-stock clause', function () {
    $title = ModelImpactTitleBuilder::buildNewModel('Z900RS', '147.5', '886', '2026年8月');

    expect($title)->toBe('Z900RSに新型が登場｜現行型の中古相場は平均147.5万円・在庫886台（2026年8月）');
});

it('drops the stock clause in new-model title when numbers are missing', function () {
    $title = ModelImpactTitleBuilder::buildNewModel('Z900RS', null, null, '2026年8月');

    expect($title)->toBe('Z900RSに新型が登場（2026年8月）');
});

// ─────────── buildDateFallback() ───────────

it('builds a date-based fallback title', function () {
    $title = ModelImpactTitleBuilder::buildDateFallback('Z900RS', '2026年8月31日');

    expect($title)->toBe('Z900RSの中古相場レポート（2026年8月31日時点）');
    expect(ModelImpactTitleBuilder::buildDateFallback(null, '2026年8月31日'))->toBeNull();
});

// ─────────── sanitizeTrigger() ───────────

it('drops empty or placeholder-only triggers', function () {
    expect(ModelImpactTitleBuilder::sanitizeTrigger(null))->toBeNull();
    expect(ModelImpactTitleBuilder::sanitizeTrigger(''))->toBeNull();
    expect(ModelImpactTitleBuilder::sanitizeTrigger('　'))->toBeNull();
    expect(ModelImpactTitleBuilder::sanitizeTrigger('〜'))->toBeNull();
});

it('does not strip evaluative words (partial-match damage avoided)', function () {
    // 「注目」等を部分一致で消さない。文をそのまま保つ。
    expect(ModelImpactTitleBuilder::sanitizeTrigger('注目度が高まるZ900発表'))
        ->toBe('注目度が高まるZ900発表');
});

it('drops triggers that are too long (>30)', function () {
    $long = str_repeat('あ', 40);
    expect(ModelImpactTitleBuilder::sanitizeTrigger($long))->toBeNull();
});

// ─────────── extractNumbers(): 表現ゆれ ───────────

it('extracts count and average price from pattern A', function () {
    $html = '<p>中古車は<strong>886台</strong>が掲載されており、平均価格は<strong>147.5万円</strong>です。</p>';

    expect(ModelImpactTitleBuilder::extractNumbers($html))->toBe(['count' => '886', 'avg' => '147.5']);
});

it('extracts count and average price from pattern B', function () {
    $html = '<p>掲載台数は<strong>879台</strong>と豊富で、平均価格は<strong>147.6万円</strong>です。</p>';

    expect(ModelImpactTitleBuilder::extractNumbers($html))->toBe(['count' => '879', 'avg' => '147.6']);
});

it('does not mistake min/max prices for the average', function () {
    $html = '<p>最安値は<strong>50万円</strong>、最高値は<strong>250万円</strong>。'
        .'掲載台数は<strong>120台</strong>、平均価格は<strong>98.3万円</strong>。</p>';

    expect(ModelImpactTitleBuilder::extractNumbers($html))->toBe(['count' => '120', 'avg' => '98.3']);
});

it('handles strong wrapping only the number', function () {
    $html = '<p>掲載台数は<strong>200台</strong>、平均価格は<strong>110</strong>万円です。</p>';

    expect(ModelImpactTitleBuilder::extractNumbers($html))->toBe(['count' => '200', 'avg' => '110']);
});

it('handles thousands separators in counts', function () {
    $html = '<p>中古車は<strong>1,234台</strong>が掲載、平均価格は<strong>88.0万円</strong>。</p>';

    expect(ModelImpactTitleBuilder::extractNumbers($html))->toBe(['count' => '1234', 'avg' => '88']);
});

it('returns null when numbers cannot be extracted', function () {
    expect(ModelImpactTitleBuilder::extractNumbers('<p>数字のない本文です。</p>'))->toBeNull();
});

// ─────────── 修正B: strong 無し・動詞ゆれ・誤抽出防止 ───────────

it('extracts numbers without strong tags and with the 流通 verb', function () {
    $html = '<p>中古市場では1019台が流通しており、平均価格は58.2万円となっています。</p>';

    expect(ModelImpactTitleBuilder::extractNumbers($html))->toBe(['count' => '1019', 'avg' => '58.2']);
});

it('does not pick 最安値 or 価格帯 prices as the average', function () {
    $html = '<p>中古車は120台が流通。最安値は98.8万円、価格帯は27.5万円から184.5万円。'
        .'平均は120万円です。</p>';

    $result = ModelImpactTitleBuilder::extractNumbers($html);
    expect($result['avg'])->toBe('120');
    expect($result['avg'])->not->toBe('98.8');
    expect($result['avg'])->not->toBe('27.5');
});

it('rejects an average when 平均 is not immediately followed by the number', function () {
    // 「平均を下回る最安値は98.8万円」は平均として拾わない。
    $html = '<p>掲載は200台。平均を下回る最安値は98.8万円です。</p>';

    expect(ModelImpactTitleBuilder::extractNumbers($html))->toBeNull();
});

// ─────────── 修正C: 全角ダッシュ正規化 ───────────

it('normalizes full-width minus and hyphen to ascii hyphen', function () {
    expect(ModelImpactTitleBuilder::normalizeDashes('YZF−R7'))->toBe('YZF-R7'); // U+2212
    expect(ModelImpactTitleBuilder::normalizeDashes('YZF－R7'))->toBe('YZF-R7'); // U+FF0D
});

it('never touches the long-vowel mark (ー)', function () {
    expect(ModelImpactTitleBuilder::normalizeDashes('スーパーカブ'))->toBe('スーパーカブ');
    expect(ModelImpactTitleBuilder::normalizeDashes('テネレ'))->toBe('テネレ');
    // 長音を含みつつ全角マイナスも含む場合、マイナスだけ直る。
    expect(ModelImpactTitleBuilder::normalizeDashes('スーパーカブ−C125'))->toBe('スーパーカブ-C125');
});

// ─────────── 修正D: 小文字のみラテン連続だけ大文字化 ───────────

it('uppercases lowercase-only latin runs but leaves mixed-case alone', function () {
    expect(ModelImpactTitleBuilder::formatModelName('z900rs'))->toBe('Z900RS');
    expect(ModelImpactTitleBuilder::formatModelName('シグナスx sr'))->toBe('シグナスX SR');
    expect(ModelImpactTitleBuilder::formatModelName('dio110・ベーシック'))->toBe('DIO110・ベーシック');
    expect(ModelImpactTitleBuilder::formatModelName('z900rsカフェ'))->toBe('Z900RSカフェ');
});

it('does not globally uppercase names that already contain uppercase', function () {
    expect(ModelImpactTitleBuilder::formatModelName('Ninja 250'))->toBe('Ninja 250');
    expect(ModelImpactTitleBuilder::formatModelName('CBR400R'))->toBe('CBR400R');
    // C→D の順で、Ninja ZX−4rr は Ninja ZX-4RR になる（Ninja は大文字を含むので不変）。
    expect(ModelImpactTitleBuilder::formatModelName('Ninja ZX−4rr'))->toBe('Ninja ZX-4RR');
});

// ─────────── 修正A（改）: h3 はできるだけそのまま使う ───────────

it('uses the first h3 as-is (no model-name or evaluative-word removal)', function () {
    $html = '<h3>新型レブル250にEクラッチ搭載</h3><p>ホンダから2026年モデルのレブル250が発表されました。</p>';

    expect(ModelImpactTitleBuilder::triggerFromContent($html))->toBe('新型レブル250にEクラッチ搭載');
});

it('collapses whitespace but keeps the wording intact', function () {
    $html = "<h3>カワサキ「Ninja 250」\n 2027年モデルが発表</h3>";

    expect(ModelImpactTitleBuilder::triggerFromContent($html))->toBe('カワサキ「Ninja 250」 2027年モデルが発表');
});

it('does not turn 注目度 into 度 (no substring evaluative removal)', function () {
    $html = '<h3>注目度が高まる新型が登場</h3>';

    expect(ModelImpactTitleBuilder::triggerFromContent($html))->toBe('注目度が高まる新型が登場');
});

it('returns null when there is no h3', function () {
    expect(ModelImpactTitleBuilder::triggerFromContent('<p>h3 のない本文</p>'))->toBeNull();
});

it('truncates an over-long h3 only right after 、 or 。 without ellipsis', function () {
    $html = '<h3>各社の新型情報をまとめた特集です、続きは本文で詳しく解説する長い見出しの文章になります</h3>';

    $trigger = ModelImpactTitleBuilder::triggerFromContent($html);
    expect($trigger)->toBe('各社の新型情報をまとめた特集です');
    expect($trigger)->not->toContain('…');
});

it('omits the trigger when an over-long h3 cannot be cut safely (no punctuation)', function () {
    // 31文字・「、」「。」なし → どこでも安全に切れない → 省略。
    $html = '<h3>ポッシュフェイス製スプロケットカバーでZ900RSがさらに進化</h3>';

    expect(ModelImpactTitleBuilder::triggerFromContent($html))->toBeNull();
});

it('never cuts inside brackets (no dangling opening bracket)', function () {
    $html = '<h3>各社が動いた特集「新型Ninja、ついに登場」を詳しく解説する長い見出しの文章です</h3>';

    // 唯一の「、」は「」の内側なので切れない → 省略。
    expect(ModelImpactTitleBuilder::triggerFromContent($html))->toBeNull();
});

it('never produces a trigger ending with a separator like ／', function () {
    $html = '<h3>今週の新型情報まとめはこちら／、さらに続きます各社の動向を追った特集記事です</h3>';

    $trigger = ModelImpactTitleBuilder::triggerFromContent($html);
    if ($trigger !== null) {
        expect(mb_substr($trigger, -1))->not->toBe('／');
    } else {
        expect($trigger)->toBeNull();
    }
});

it('never produces a trigger ending with a particle', function () {
    $html = '<h3>最新モデルの詳細情報はこちらが、続きを読むと各社の戦略が見えてくる特集記事です</h3>';

    $trigger = ModelImpactTitleBuilder::triggerFromContent($html);
    // 「、」の手前が「が」で終わるため切れない → 省略。
    expect($trigger)->toBeNull();
});

// ─────────── 修正E: 「その他」カテゴリは車種でない ───────────

it('keeps the その他 prefix so callers can skip non-model categories', function () {
    expect(ModelImpactTitleBuilder::formatModelName('その他(251〜400cc)'))->toStartWith('その他');
});

// ─────────── formatMan() は本文と桁を合わせる ───────────

it('formats man-yen consistently with the body (drops trailing zero)', function () {
    expect(ModelImpactTitleBuilder::formatMan(147.5))->toBe('147.5');
    expect(ModelImpactTitleBuilder::formatMan(147.0))->toBe('147');
    expect(ModelImpactTitleBuilder::formatMan(88.04))->toBe('88');
});
