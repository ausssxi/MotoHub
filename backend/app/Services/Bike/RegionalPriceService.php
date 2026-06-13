<?php

declare(strict_types=1);

namespace App\Services\Bike;

use App\Models\BikeModel;
use App\Models\ModelRegionPriceStat;

/**
 * モデルページの「地域別中古相場（中央値）」表示用データを組み立てる。
 * 事前計算テーブル(model_region_price_stats)を読むだけ（集計は stats:regional-prices）。
 */
final class RegionalPriceService
{
    /** 地域比較が成立する最低ブロック数。これ未満はセクションごと非表示。 */
    private const MIN_BLOCKS = 2;

    /**
     * 見出しの「高め/割安」断定に使える最低台数。これ未満のブロックは中央値が振れやすく、
     * 偶然の高/安を"事実"として掲げると94%バッジと同じ失敗になるため判定母数から除外。
     * 表自体の表示ゲート(MIN_LISTINGS=10, コマンド側)とは別の、より厳しい頑健閾値。
     */
    private const ROBUST_N = 20;

    /**
     * @return array{regions: array<int, array{block: string, median: int, median_man: string, count: int}>,
     *               national: array{median: int, median_man: string, count: int}|null,
     *               headline: string|null}
     */
    public function getForModel(BikeModel $model): array
    {
        $empty = ['regions' => [], 'national' => null, 'headline' => null];

        $rows = ModelRegionPriceStat::where('bike_model_id', $model->id)
            ->get()
            ->keyBy('region_block');

        if ($rows->isEmpty()) {
            return $empty;
        }

        $blockOrder = (array) config('regions.block_order', []);
        $nationalLabel = (string) config('regions.national_label', '全国');

        $regions = [];
        foreach ($blockOrder as $block) {
            if (! isset($rows[$block])) {
                continue;
            }
            $regions[] = $this->row($block, $rows[$block]);
        }

        // 地域比較が成立しない（ブロック1個以下）なら出さない
        if (count($regions) < self::MIN_BLOCKS) {
            return $empty;
        }

        $national = isset($rows[$nationalLabel])
            ? $this->row($nationalLabel, $rows[$nationalLabel])
            : null;

        return [
            'regions' => $regions,
            'national' => $national,
            'headline' => $this->buildHeadline($model, $regions, $national),
        ];
    }

    /**
     * @return array{block: string, median: int, median_man: string, count: int, robust: bool}
     */
    private function row(string $block, ModelRegionPriceStat $stat): array
    {
        return [
            'block' => $block,
            'median' => (int) $stat->median_price,
            'median_man' => number_format($stat->median_price / 10000, 1),
            'count' => (int) $stat->listing_count,
            // 頑健ブロック（n≥ROBUST_N）か。bladeの参考値マーク・見出し判定に使う。
            'robust' => (int) $stat->listing_count >= self::ROBUST_N,
        ];
    }

    /**
     * 実データ由来の独自文。
     * 「高め/割安」の断定は頑健ブロック（n≥ROBUST_N）からのみ選ぶ。薄いブロックの
     * 偶然の高/安を主張に格上げしない（94%バッジの二の舞回避）。
     * 頑健ブロックが2つ未満なら比較表現は出さず、全国中央値のみ述べる。
     *
     * @param  array<int, array{block: string, median: int, count: int}>  $regions
     * @param  array{median: int, median_man: string, count: int}|null  $national
     */
    private function buildHeadline(BikeModel $model, array $regions, ?array $national): ?string
    {
        $robust = array_values(array_filter($regions, fn ($r) => $r['count'] >= self::ROBUST_N));

        if (count($robust) >= self::MIN_BLOCKS) {
            usort($robust, fn ($a, $b) => $a['median'] <=> $b['median']);
            $cheapest = $robust[0];
            $priciest = $robust[count($robust) - 1];

            if ($cheapest['block'] !== $priciest['block']) {
                return "「{$model->name}」の地域別中古相場（中央値・支払総額）。"
                    ."{$priciest['block']}が高めの約{$this->man($priciest['median'])}万円、"
                    ."{$cheapest['block']}が割安傾向の約{$this->man($cheapest['median'])}万円です。"
                    .'購入時は近隣ブロックの相場と件数も確認しましょう。';
            }
        }

        // 頑健ブロックが足りない → 断定を避け、全国中央値のみ述べる
        if ($national !== null) {
            return "「{$model->name}」の地域別中古相場（中央値・支払総額）。"
                ."全国の中央値は約{$national['median_man']}万円です。"
                .'地域差は掲載台数が十分な地域から順次ご案内します。';
        }

        return null;
    }

    private function man(int $yen): string
    {
        return number_format($yen / 10000, 1);
    }
}
