<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Listing;

/**
 * ローン「月々の目安」の算出（サーバー描画・純関数）。
 *
 * ・元利均等返済。式・既定パラメータは JS 計算機(public/js/bikes/loan-simulator.js)と揃える。
 * ・数字は創作せず config/loan.php の「例」金利で試算するだけ（媒介・斡旋はしない）。
 * ・価格解決（総額 ?? 本体）と min_price 判定はここに集約し、Blade は表示のみに保つ。
 */
final class LoanEstimate
{
    /**
     * 生（円）の Listing から月々目安を算出。表示不可（価格欠損 / min_price未満）なら null。
     *
     * @return array{price:int, apr:float, round_unit:int, source_note:string, checked_at:string, rows:array<int,array{term:int,monthly:int,total:int}>}|null
     */
    public static function forListing(Listing $listing): ?array
    {
        $priceYen = self::resolvePriceYen($listing);
        if ($priceYen === null) {
            return null;
        }

        return self::build($priceYen);
    }

    /**
     * 試算価格（円）を解決。総額(total_price)優先→本体(price)フォールバック。
     * 数値でない / min_price 未満 は null（＝ブロック非表示）。
     */
    private static function resolvePriceYen(Listing $listing): ?int
    {
        // 生モデルの total_price/price は円。総額が null/0 のとき本体へフォールバック。
        $raw = $listing->total_price ?: $listing->price;
        if (! is_numeric($raw)) {
            return null;
        }

        $yen = (int) $raw;
        if ($yen < (int) config('loan.min_price', 50000)) {
            return null;
        }

        return $yen;
    }

    /**
     * 試算価格（円）から各回数の月々・支払総額を算出。
     *
     * @return array{price:int, apr:float, round_unit:int, source_note:string, checked_at:string, rows:array<int,array{term:int,monthly:int,total:int}>}
     */
    public static function build(int $priceYen): array
    {
        $apr = (float) config('loan.sample_apr', 4.9);
        $unit = max(1, (int) config('loan.round_unit', 1));

        /** @var array<int,int> $terms */
        $terms = array_map('intval', (array) config('loan.terms', [24, 36, 60]));

        // JS計算機と完全一致させるため、価格の基準を JS と同じ「万円に丸めた整数 × 10000」に揃える。
        // （JSは data-total-price = 総額(万円・小数1桁) を parseInt で整数万円化してから ×10000 する）
        $baseYen = (int) round($priceYen / 10000, 1) * 10000;

        $r = $apr / 100 / 12; // 月利

        $rows = [];
        foreach ($terms as $n) {
            if ($n <= 0) {
                continue;
            }
            if ($r > 0) {
                $pow = (1 + $r) ** $n;
                $monthly = $baseYen * $r * $pow / ($pow - 1); // 元利均等
            } else {
                $monthly = $baseYen / $n;
            }
            $monthly = self::floorTo($monthly, $unit);   // JS: Math.floor 相当
            $rows[] = ['term' => $n, 'monthly' => $monthly, 'total' => $monthly * $n];
        }

        return [
            'price' => $baseYen,
            'apr' => $apr,
            'round_unit' => $unit,
            'source_note' => (string) config('loan.source_note', ''),
            'checked_at' => (string) config('loan.checked_at', ''),
            'rows' => $rows,
        ];
    }

    private static function floorTo(float $value, int $unit): int
    {
        if ($unit <= 1) {
            return (int) floor($value);
        }

        return (int) (floor($value / $unit) * $unit);
    }
}
