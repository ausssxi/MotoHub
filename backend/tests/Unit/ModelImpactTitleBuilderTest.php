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

it('drops evaluative-only or empty triggers', function () {
    expect(ModelImpactTitleBuilder::sanitizeTrigger(null))->toBeNull();
    expect(ModelImpactTitleBuilder::sanitizeTrigger(''))->toBeNull();
    expect(ModelImpactTitleBuilder::sanitizeTrigger('　'))->toBeNull();
    expect(ModelImpactTitleBuilder::sanitizeTrigger('〜'))->toBeNull();
    expect(ModelImpactTitleBuilder::sanitizeTrigger('注目の'))->toBeNull();
});

it('strips evaluative words from an otherwise valid trigger', function () {
    expect(ModelImpactTitleBuilder::sanitizeTrigger('注目のOVER Racingが新マフラー発売'))
        ->toBe('OVER Racingが新マフラー発売');
});

it('drops triggers that are too long to be a summary', function () {
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

// ─────────── formatMan() は本文と桁を合わせる ───────────

it('formats man-yen consistently with the body (drops trailing zero)', function () {
    expect(ModelImpactTitleBuilder::formatMan(147.5))->toBe('147.5');
    expect(ModelImpactTitleBuilder::formatMan(147.0))->toBe('147');
    expect(ModelImpactTitleBuilder::formatMan(88.04))->toBe('88');
});
