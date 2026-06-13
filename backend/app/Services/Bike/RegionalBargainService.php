<?php

declare(strict_types=1);

namespace App\Services\Bike;

use App\Models\ModelRegionPriceStat;

/**
 * 在庫カード等の「お得」バッジを、全国中央値(total_price)基準で算出する。
 *
 * 旧 bargain_score は「平均(外れ値無防備)＋走行距離ボーナス」の多因子で、
 * 「相場より約94%お得」のような暴れ値・誤表示を出していた。本サービスは:
 *  - 基準 = model_region_price_stats の region_block='全国' 行（中央値・per-shopキャップ5）
 *  - 比較は total_price 同士（フィールドミスマッチを断つ）
 *  - 頑健ゲート: 全国 listing_count >= ROBUST_N(20) のときだけ
 *  - 信用バンド: 10% ≤ 割引 ≤ 50% のときだけ表示（<10は非ニュース、>50はデータ異常で非表示）
 * のすべてを満たす時だけ値を返す。それ以外は null（=バッジ非表示）。
 */
final class RegionalBargainService
{
    /** 全国行がこの台数未満なら基準として使わない（薄い母数の暴れを防ぐ）。 */
    public const ROBUST_N = 20;

    /** 表示する最小割引率(%)。これ未満は誤差・非ニュースとして出さない。 */
    public const MIN_PCT = 10;

    /** 表示する最大割引率(%)。これ超は誤価格・誤マッチ等の異常として出さない（94%再発防止の物理ガード）。 */
    public const MAX_PCT = 50;

    /** 合理的な価格下限(円)。これ未満は部品取り・誤登録の疑いで対象外。 */
    public const MIN_PRICE = 50000;

    /**
     * eager-load 済みの全国stat（追加クエリなし）から算出する。カード用（N+1なし）。
     *
     * @return array{pct: int, median: int, median_man: string, diff_man: string}|null
     */
    public static function fromStat(?ModelRegionPriceStat $stat, ?int $totalPrice): ?array
    {
        if ($stat === null || $totalPrice === null) {
            return null;
        }
        if ($totalPrice < self::MIN_PRICE) {
            return null;
        }
        if ((int) $stat->listing_count < self::ROBUST_N) {
            return null;
        }

        $median = (int) $stat->median_price;
        if ($median <= 0) {
            return null;
        }

        $pct = ($median - $totalPrice) / $median * 100;
        if ($pct < self::MIN_PCT || $pct > self::MAX_PCT) {
            return null;
        }

        return [
            'pct' => (int) round($pct),
            'median' => $median,
            'median_man' => number_format($median / 10000, 1),
            'diff_man' => number_format(($median - $totalPrice) / 10000, 1),
        ];
    }

    /**
     * 単体listing用（OG画像・共有テキスト等）。全国行を1クエリ取得して算出する。
     *
     * @return array{pct: int, median: int, median_man: string, diff_man: string}|null
     */
    public function forListing(?int $bikeModelId, ?int $totalPrice): ?array
    {
        if (! $bikeModelId) {
            return null;
        }

        $stat = ModelRegionPriceStat::where('bike_model_id', $bikeModelId)
            ->where('region_block', config('regions.national_label', '全国'))
            ->first();

        return self::fromStat($stat, $totalPrice);
    }
}
