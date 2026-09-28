<?php

use App\Support\RentalBike\PriceFetchers\NirinshoPriceFetcher;

/**
 * 二輪処料金フェッチャの単体テスト（★外部アクセスなし・DB非依存）。
 * 50cc/125cc は基本料金表の「通常クラス・1日」。250cc/400cc/大型 は通年の通常表が無く、
 * 夏季料金/冬季料金の「1日」が一致するときだけ通年1日として取り込み（季節料金ありの注記）。
 */
$base = <<<'HTML'
<table>
  <tr><td>基本料金</td><td>1日</td><td>1週間</td><td>1ヶ月</td></tr>
  <tr><td>50cc</td><td>特クラス</td><td>3,300円</td><td>6,600円</td><td>11,000円</td></tr>
  <tr><td>通常クラス</td><td>2,200円</td><td>9,900円</td></tr>
  <tr><td>125cc</td><td>特クラス</td><td>4,400円</td><td>9,900円</td><td>15,950円</td></tr>
  <tr><td>通常クラス</td><td>3,300円</td><td>13,200円</td></tr>
</table>
HTML;

$summer = <<<'HTML'
<table>
  <tr><td>夏季料金</td><td>1日</td><td>追加1日</td><td>1週間</td></tr>
  <tr><td>250cc</td><td>7,040円</td><td>6,600円</td><td>27,500円</td></tr>
  <tr><td>400cc</td><td>9,020円</td><td>7,700円</td><td>33,000円</td></tr>
  <tr><td>大型</td><td>10,670円</td><td>8,800円</td><td>38,500円</td></tr>
</table>
HTML;

$winter = <<<'HTML'
<table>
  <tr><td>冬季料金</td><td>1日</td><td>追加1日</td><td>1週間</td></tr>
  <tr><td>250cc</td><td>7,040円</td><td>2,750円</td><td>16,500円</td></tr>
  <tr><td>400cc</td><td>9,020円</td><td>3,850円</td><td>19,250円</td></tr>
  <tr><td>大型</td><td>10,670円</td><td>4,400円</td><td>19,250円</td></tr>
</table>
HTML;

it('takes 通常クラス「1日」for 50cc/125cc and season-invariant「1日」for 250/400/大型', function () use ($base, $summer, $winter) {
    $rows = (new NirinshoPriceFetcher)->extractPrices($base.$summer.$winter);
    $byClass = collect($rows)->keyBy('vehicle_class');

    expect($rows)->toHaveCount(5)
        ->and($byClass['原付']['price_yen'])->toBe(2200)      // 50cc 通常クラス（特クラス3,300は取らない）
        ->and($byClass['125cc']['price_yen'])->toBe(3300)
        ->and($byClass['250cc']['price_yen'])->toBe(7040)
        ->and($byClass['400cc']['price_yen'])->toBe(9020)
        ->and($byClass['大型']['price_yen'])->toBe(10670);

    // 車格順で返る。
    expect(array_column($rows, 'vehicle_class'))->toBe(['原付', '125cc', '250cc', '400cc', '大型']);

    // 行ごとの note は付けない（季節の但し書きは詳細ページ下部の一括注記で出す）。
    foreach (['原付', '125cc', '250cc', '400cc', '大型'] as $c) {
        expect($byClass[$c]['note'])->toBeNull();
    }
    foreach ($rows as $r) {
        expect($r['plan'])->toBe('daily')->and($r['plan_label'])->toBe('1日（当日返却）')->and($r['is_from'])->toBeFalse();
    }
});

it('excludes 250/400/大型 when only one season is present (needs 夏季=冬季 to confirm 通年)', function () use ($base, $summer) {
    $rows = (new NirinshoPriceFetcher)->extractPrices($base.$summer);

    // 夏季だけでは通年と確定できないので 50cc/125cc のみ。
    expect(array_column($rows, 'vehicle_class'))->toBe(['原付', '125cc']);
});

it('excludes a class when 夏季 and 冬季 の1日 が食い違う', function () use ($base, $summer) {
    $winterDiff = str_replace('<td>250cc</td><td>7,040円</td>', '<td>250cc</td><td>9,999円</td>', str_replace('夏季料金', '冬季料金', $summer));
    $rows = (new NirinshoPriceFetcher)->extractPrices($base.$summer.$winterDiff);
    $classes = array_column($rows, 'vehicle_class');

    expect($classes)->not->toContain('250cc')   // 1日が食い違う→取り込まない
        ->and($classes)->toContain('400cc')      // 一致するものは残る
        ->and($classes)->toContain('大型');
});

it('exposes slug and source url', function () {
    $f = new NirinshoPriceFetcher;
    expect($f->slug())->toBe('nirinsho')
        ->and($f->sourceUrl())->toBe('https://www.bike-rental.jp/price/');
});
