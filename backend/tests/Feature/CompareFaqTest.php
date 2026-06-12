<?php

declare(strict_types=1);

use App\Models\BikeModel;
use App\Services\Bike\SeoCompareService;

function faqModel(array $attrs): BikeModel
{
    $m = new BikeModel();
    foreach ($attrs as $k => $v) {
        $m->setAttribute($k, $v);
    }

    return $m;
}

it('generates price, shaken and beginner FAQ from full data', function () {
    $m1 = faqModel(['id' => 1, 'name' => 'レブル250', 'displacement' => 249, 'seat_height' => 690, 'weight' => 170]);
    $m2 = faqModel(['id' => 2, 'name' => 'GB350', 'displacement' => 348, 'seat_height' => 760, 'weight' => 178]);
    $kpi = [
        'model1' => ['median_price' => '45.0'],
        'model2' => ['median_price' => '52.0'],
    ];

    $faq = app(SeoCompareService::class)->buildFaq($m1, $m2, $kpi);
    $joined = collect($faq)->map(fn ($f) => $f['q'] . $f['a'])->implode("\n");

    expect($faq)->toHaveCount(3);
    // 安い方（レブル250、差7.0万円）
    expect($joined)->toContain('7.0万円安く');
    // 250cc境界の車検差
    expect($joined)->toContain('250ccを境に');
    // 足つき・取り回しともレブル250が有利
    expect($joined)->toContain('レブル250のほうが');
});

it('omits questions when the underlying data is missing', function () {
    // 価格中央値のみ存在、スペックは無し → 価格の1問だけ
    $m1 = faqModel(['id' => 1, 'name' => 'A']);
    $m2 = faqModel(['id' => 2, 'name' => 'B']);
    $kpi = [
        'model1' => ['median_price' => '30.0'],
        'model2' => ['median_price' => '30.0'],
    ];

    $faq = app(SeoCompareService::class)->buildFaq($m1, $m2, $kpi);

    expect($faq)->toHaveCount(1);
    expect($faq[0]['a'])->toContain('ほぼ同水準');
});

it('omits the price question when median is unavailable', function () {
    $m1 = faqModel(['id' => 1, 'name' => 'A', 'displacement' => 150]);
    $m2 = faqModel(['id' => 2, 'name' => 'B', 'displacement' => 150]);
    $kpi = [
        'model1' => ['median_price' => '-'],
        'model2' => ['median_price' => null],
    ];

    $faq = app(SeoCompareService::class)->buildFaq($m1, $m2, $kpi);

    // 排気量の1問のみ（価格は出ない）
    expect($faq)->toHaveCount(1);
    expect($faq[0]['q'])->toContain('排気量');
});
