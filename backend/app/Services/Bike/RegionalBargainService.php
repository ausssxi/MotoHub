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
 *  - 信用バンド: 10% ≤ 割引 ≤ maxPctForMedian(中央値) のときだけ表示。
 *    <10は非ニュース。上限は中央値で二段階（100万以下=75% / 100万超=50%）。超過はデータ異常で非表示。
 * のすべてを満たす時だけ値を返す。それ以外は null（=バッジ非表示）。
 */
final class RegionalBargainService
{
    /** 全国行がこの台数未満なら基準として使わない（薄い母数の暴れを防ぐ）。 */
    public const ROBUST_N = 20;

    /** 表示する最小割引率(%)。これ未満は誤差・非ニュースとして出さない。 */
    public const MIN_PCT = 10;

    /**
     * 割引率の上限(%)を中央値で二段階に切り替える（94%等の異常ガードと「本物の激安」の両立）。
     *  - 中央値 <= MEDIAN_STRICT_THRESHOLD（安い実用車・原付）: MAX_PCT_LOW まで許容。
     *    アドレス110等は中央値比 60%超の本物の激安が普通にあるため救う。
     *  - 中央値 >  MEDIAN_STRICT_THRESHOLD（高額・旧車大型）: MAX_PCT_HIGH（従来どおり厳格）。
     *    高pctはほぼ誤価格・部品取り・車種誤マッチの異常なので確実に弾く。
     */
    public const MEDIAN_STRICT_THRESHOLD = 1000000; // 100万円（円）
    public const MAX_PCT_LOW = 75;                  // 中央値100万以下
    public const MAX_PCT_HIGH = 50;                 // 中央値100万超（94%再発防止の物理ガード）

    /** 合理的な価格下限(円)。これ未満は部品取り・誤登録の疑いで対象外。 */
    public const MIN_PRICE = 50000;

    /**
     * 中央値(円)に応じた割引率の上限(%)。fromStat/sortScore が共有する正本。
     */
    public static function maxPctForMedian(int $median): int
    {
        return $median > self::MEDIAN_STRICT_THRESHOLD
            ? self::MAX_PCT_HIGH
            : self::MAX_PCT_LOW;
    }

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
        if ($pct < self::MIN_PCT || $pct > self::maxPctForMedian($median)) {
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
     * お買い得順ソート/ランキング用の連続スコア [0, MAX_PCT]。
     * 表示バッジ(fromStat)と同じ正本（全国中央値・robust20・上限は中央値で二段階maxPctForMedian・下限5万）を
     * 共有しつつ、ソート向けに2点だけ異なる:
     *   - 表示の「10%未満は非表示」は付けない（小割引も順位に反映）。
     *   - 表示の「上限超は非表示(null)」に対し、ソートは「上限超は 0」。
     *     ⚠️ clampして上限値にすると異常(94%)が最高値=上位固定で不満が再発するため、0で除外する。
     * 割高(中央値超=負)も 0（中立）。値が連続なので Meilisearch の sortable/rankingRules にそのまま流せる。
     */
    public static function sortScore(?ModelRegionPriceStat $stat, ?int $totalPrice): float
    {
        if ($stat === null || $totalPrice === null) {
            return 0.0;
        }
        if ($totalPrice < self::MIN_PRICE) {
            return 0.0;
        }
        if ((int) $stat->listing_count < self::ROBUST_N) {
            return 0.0;
        }

        $median = (int) $stat->median_price;
        if ($median <= 0) {
            return 0.0;
        }

        $pct = ($median - $totalPrice) / $median * 100;

        // 異常(中央値帯ごとの上限超)はランクさせない（clampしない＝最高値に居座らせない）。割高(<0)も中立。
        if ($pct > self::maxPctForMedian($median) || $pct < 0) {
            return 0.0;
        }

        return round($pct, 1);
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
