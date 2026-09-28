<?php

use App\Models\RentalBikePrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/**
 * 2-3: 料金の鮮度スコープ（70日）と、同額でも fetched_at が更新されることの回帰。
 */
afterEach(fn () => Carbon::setTestNow());

it('scopeFresh keeps <=70日 and hides >70日 (69=表示 / 70=表示 / 71=非表示)', function () {
    Carbon::setTestNow('2026-10-01 12:00:00');

    foreach (['原付' => 69, '125cc' => 70, '250cc' => 71] as $class => $days) {
        RentalBikePrice::create([
            'company_slug' => 'nirinsho',
            'vehicle_class' => $class,
            'plan' => 'daily',
            'plan_label' => '1日（当日返却）',
            'price_yen' => 3000,
            'price_is_from' => false,
            'note' => null,
            'source_url' => 'https://example.test/',
            'fetched_at' => now()->subDays($days),
        ]);
    }

    $fresh = RentalBikePrice::query()->fresh()->pluck('vehicle_class')->all();

    expect($fresh)->toContain('原付')      // 69日 → 表示
        ->and($fresh)->toContain('125cc')  // 70日ちょうど → 表示（境界を含む）
        ->and($fresh)->not->toContain('250cc'); // 71日 → 非表示
});

it('updates fetched_at on re-run even when the price is unchanged (異常でない限り)', function () {
    $fee = <<<'HTML'
    <table>
      <tr><th>クラス</th><th>基本料金</th><th>追加料金</th></tr>
      <tr><td>4時間</td><td>8時間</td><td>24時間</td><td>1時間ごと</td><td>24時間ごと</td></tr>
      <tr><td>～50cc</td><td>3,000円</td><td>3,500円</td><td>4,500円</td><td>1,000円</td><td>3,000円</td></tr>
    </table>
    HTML;
    Http::fake(['bike-rental.yamaha-motor.co.jp/*' => Http::response($fee, 200)]);

    Carbon::setTestNow('2026-10-02 05:10:00');
    $this->artisan('rental-bike:fetch-price yamaha')->assertSuccessful();

    $row = RentalBikePrice::where('company_slug', 'yamaha')->where('vehicle_class', '原付')->firstOrFail();
    expect($row->price_yen)->toBe(4500)
        ->and($row->fetched_at->toDateTimeString())->toBe('2026-10-02 05:10:00');

    // 1ヶ月後、同じ料金でもう一度取得 → 金額は不変・fetched_at は更新される。
    Carbon::setTestNow('2026-11-02 05:10:00');
    $this->artisan('rental-bike:fetch-price yamaha')->assertSuccessful();

    $row->refresh();
    expect($row->price_yen)->toBe(4500)                               // 金額は同じ
        ->and($row->fetched_at->toDateTimeString())->toBe('2026-11-02 05:10:00'); // ★fetched_at は前へ進む
});
