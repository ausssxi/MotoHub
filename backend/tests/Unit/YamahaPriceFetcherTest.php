<?php

use App\Support\RentalBike\PriceFetchers\YamahaPriceFetcher;

/**
 * ヤマハ料金フェッチャの単体テスト（★外部アクセスなし・DB非依存）。
 * 料金表(先頭table)から「24時間」列を取り、排気量クラス→MotoHub5車格に対応させる。
 * 大型は 401cc～ を代表値にし is_from=true（「¥○○〜」＋注記）。801cc～・EXクラスは取り込まない。
 */
$fee = <<<'HTML'
<table>
  <tr><th>クラス</th><th>基本料金</th><th>追加料金</th></tr>
  <tr><td>4時間</td><td>8時間</td><td>24時間</td><td>1時間ごと</td><td>24時間ごと</td></tr>
  <tr><td>EXクラス</td><td>21,500円</td><td>23,500円</td><td>26,500円</td><td>3,000円</td><td>19,500円</td></tr>
  <tr><td>801cc～</td><td>14,500円</td><td>16,000円</td><td>19,000円</td><td>2,500円</td><td>13,500円</td></tr>
  <tr><td>401cc～</td><td>13,000円</td><td>14,500円</td><td>17,000円</td><td>2,500円</td><td>12,000円</td></tr>
  <tr><td>251cc～</td><td>10,000円</td><td>11,000円</td><td>12,500円</td><td>2,000円</td><td>10,000円</td></tr>
  <tr><td>126cc～</td><td>8,000円</td><td>9,000円</td><td>11,000円</td><td>2,000円</td><td>8,500円</td></tr>
  <tr><td>51cc～</td><td>4,500円</td><td>5,500円</td><td>6,500円</td><td>1,500円</td><td>4,500円</td></tr>
  <tr><td>～50cc</td><td>3,000円</td><td>3,500円</td><td>4,500円</td><td>1,000円</td><td>3,000円</td></tr>
</table>
HTML;

it('maps 排気量クラス to MotoHub 5車格 and takes the 24時間 price', function () use ($fee) {
    $rows = (new YamahaPriceFetcher)->extractPrices($fee);
    $byClass = collect($rows)->keyBy('vehicle_class');

    expect($rows)->toHaveCount(5)
        ->and($byClass['原付']['price_yen'])->toBe(4500)
        ->and($byClass['125cc']['price_yen'])->toBe(6500)
        ->and($byClass['250cc']['price_yen'])->toBe(11000)
        ->and($byClass['400cc']['price_yen'])->toBe(12500)
        ->and($byClass['大型']['price_yen'])->toBe(17000);

    foreach ($rows as $r) {
        expect($r['plan'])->toBe('daily')->and($r['plan_label'])->toBe('24時間');
    }
});

it('marks 大型(401cc～) as is_from with a note and excludes 801cc～ / EXクラス', function () use ($fee) {
    $rows = (new YamahaPriceFetcher)->extractPrices($fee);
    $large = collect($rows)->firstWhere('vehicle_class', '大型');

    expect($large['is_from'])->toBeTrue()
        ->and($large['note'])->toBe('車種により異なります')
        ->and($large['price_yen'])->toBe(17000);         // 401cc～ を代表（19,000/26,500 は取らない）

    // 原付〜400cc は is_from=false・note=null。
    foreach (['原付', '125cc', '250cc', '400cc'] as $c) {
        $row = collect($rows)->firstWhere('vehicle_class', $c);
        expect($row['is_from'])->toBeFalse()->and($row['note'])->toBeNull();
    }
});

it('returns [] when there is no 24時間 fee table', function () {
    expect((new YamahaPriceFetcher)->extractPrices('<table><tr><td>準備中</td></tr></table>'))->toBe([]);
});

it('exposes slug and source url', function () {
    $f = new YamahaPriceFetcher;
    expect($f->slug())->toBe('yamaha')
        ->and($f->sourceUrl())->toBe('https://bike-rental.yamaha-motor.co.jp/jp/bike/info/fee');
});
