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
            'headline' => $this->buildHeadline($model, $regions),
        ];
    }

    /**
     * @return array{block: string, median: int, median_man: string, count: int}
     */
    private function row(string $block, ModelRegionPriceStat $stat): array
    {
        return [
            'block' => $block,
            'median' => (int) $stat->median_price,
            'median_man' => number_format($stat->median_price / 10000, 1),
            'count' => (int) $stat->listing_count,
        ];
    }

    /**
     * 実データ由来の独自文。最高値ブロックと最安値ブロックを名指しして差別化する。
     *
     * @param  array<int, array{block: string, median: int, count: int}>  $regions
     */
    private function buildHeadline(BikeModel $model, array $regions): ?string
    {
        if (count($regions) < self::MIN_BLOCKS) {
            return null;
        }

        usort($regions, fn ($a, $b) => $a['median'] <=> $b['median']);
        $cheapest = $regions[0];
        $priciest = $regions[count($regions) - 1];

        if ($cheapest['block'] === $priciest['block']) {
            return null;
        }

        return "「{$model->name}」の地域別中古相場（中央値・支払総額）。"
            ."{$priciest['block']}が高めの約{$this->man($priciest['median'])}万円、"
            ."{$cheapest['block']}が割安傾向の約{$this->man($cheapest['median'])}万円です。"
            .'購入時は近隣ブロックの相場と件数も確認しましょう。';
    }

    private function man(int $yen): string
    {
        return number_format($yen / 10000, 1);
    }
}
