<?php

declare(strict_types=1);

namespace App\Services\Bike;

use App\Models\BikeModel;
use App\Models\Listing;
use App\Models\SeoCompare;
use Illuminate\Support\Facades\Cache;

/**
 * 車種比較ページ用のロジッククラス
 */
final class SeoCompareService
{
    /**
     * 両車種の在庫KPIを集計（キャッシュ付き）
     */
    public function computeCompareKpi(BikeModel $m1, BikeModel $m2): array
    {
        $cacheKey = 'compare_kpi_v1_' . $m1->id . '_' . $m2->id;

        return Cache::remember($cacheKey, 3600, function () use ($m1, $m2) {
            return [
                'model1' => $this->buildModelKpi($m1),
                'model2' => $this->buildModelKpi($m2),
            ];
        });
    }

    /**
     * 同排気量帯・同カテゴリの他の比較ペアを取得
     */
    public function getRelatedComparisons(BikeModel $m1, BikeModel $m2): array
    {
        return SeoCompare::active()
            ->ordered()
            ->where('slug', '!=', $this->makeSlug($m1, $m2))
            ->where(function ($q) use ($m1, $m2) {
                $q->whereIn('model1_id', [$m1->id, $m2->id])
                  ->orWhereIn('model2_id', [$m1->id, $m2->id]);
            })
            ->with(['model1.manufacturer', 'model2.manufacturer'])
            ->limit(10)
            ->get()
            ->map(fn (SeoCompare $c) => [
                'slug' => $c->slug,
                'label' => $c->model1->name . ' vs ' . $c->model2->name,
                'url' => $c->url,
            ])
            ->toArray();
    }

    /**
     * 単一車種のKPIを集計
     */
    private function buildModelKpi(BikeModel $model): array
    {
        $query = Listing::query()
            ->where('bike_model_id', $model->id)
            ->where('is_sold_out', false)
            ->where('total_price', '>', 0);

        $totalCount = (clone $query)->count();

        $priceStats = (clone $query)->selectRaw('
            AVG(total_price) as avg_price,
            MIN(total_price) as min_price,
            MAX(total_price) as max_price
        ')->first();

        return [
            'total_count' => $totalCount,
            'avg_price' => $priceStats->avg_price ? number_format((float) ($priceStats->avg_price / 10000), 1) : null,
            'min_price' => $priceStats->min_price ? number_format((float) ($priceStats->min_price / 10000), 1) : null,
            'max_price' => $priceStats->max_price ? number_format((float) ($priceStats->max_price / 10000), 1) : null,
            'price_distribution' => $this->buildPriceDistribution($query, $totalCount),
        ];
    }

    /**
     * 価格帯分布を集計
     */
    private function buildPriceDistribution(mixed $query, int $totalCount): array
    {
        if ($totalCount === 0) {
            return [];
        }

        $bands = [
            ['label' => '〜10万円',   'min' => 0,       'max' => 100000],
            ['label' => '10〜20万円',  'min' => 100000,  'max' => 200000],
            ['label' => '20〜30万円',  'min' => 200000,  'max' => 300000],
            ['label' => '30〜50万円',  'min' => 300000,  'max' => 500000],
            ['label' => '50〜80万円',  'min' => 500000,  'max' => 800000],
            ['label' => '80〜100万円', 'min' => 800000,  'max' => 1000000],
            ['label' => '100万円〜',   'min' => 1000000, 'max' => null],
        ];

        $caseWhen = 'CASE';
        foreach ($bands as $i => $band) {
            if ($band['max'] === null) {
                $caseWhen .= " WHEN total_price >= {$band['min']} THEN {$i}";
            } else {
                $caseWhen .= " WHEN total_price >= {$band['min']} AND total_price < {$band['max']} THEN {$i}";
            }
        }
        $caseWhen .= ' END';

        $rows = (clone $query)
            ->selectRaw("{$caseWhen} as band_idx, COUNT(*) as cnt")
            ->groupByRaw($caseWhen)
            ->get()
            ->keyBy('band_idx');

        $maxCount = $rows->max('cnt') ?: 1;

        $distribution = [];
        foreach ($bands as $i => $band) {
            $count = (int) ($rows->get($i)?->cnt ?? 0);
            if ($count === 0) {
                continue;
            }
            $distribution[] = [
                'label' => $band['label'],
                'count' => $count,
                'percent' => round($count / $totalCount * 100, 1),
                'bar_width' => round($count / $maxCount * 100),
                'is_max' => $count === $maxCount,
            ];
        }

        return $distribution;
    }

    private function makeSlug(BikeModel $m1, BikeModel $m2): string
    {
        return ($m1->slug ?? (string) $m1->id) . '-vs-' . ($m2->slug ?? (string) $m2->id);
    }
}
