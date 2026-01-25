<?php

declare(strict_types=1);

namespace App\Services\Bike\Search;

use App\Repositories\Bike\ListingRepository;

/**
 * 検索結果の統計情報（スライダーの上限値など）を計算するクラス
 */
final class SearchMetadataGenerator
{
    public function __construct(
        private readonly ListingRepository $listingRepo
    ) {}

    /**
     * スライダー用のメタデータを生成
     */
    public function generate(?string $keyword, ?string $prefecture, array $filters): array
    {
        // 範囲フィルタを除外して「その車種の全体像」を取得
        $rangeKeys = ['min_price', 'max_price', 'min_mileage', 'max_mileage', 'min_year', 'max_year'];
        $cleanFilters = array_diff_key($filters, array_flip($rangeKeys));

        $stats = $this->listingRepo->getMinMaxStats($keyword, $prefecture, $cleanFilters);

        $rawPrice = $stats->max_price ? (int) ceil($stats->max_price / 10000) : 0;
        $rawMileage = (int) ($stats->max_mileage ?? 0);

        return [
            'price' => [
                'min' => 0,
                'max' => $this->roundUpPrice($rawPrice)
            ],
            'mileage' => [
                'min' => 0,
                'max' => $this->roundUpMileage($rawMileage)
            ],
            'year' => [
                'min' => (int) ($stats->min_year ?? 1990),
                'max' => (int) ($stats->max_year ?? (int) date('Y')),
            ]
        ];
    }

    /**
     * 平均・最小・最大価格のフォーマット
     */
    public function formatStats(object $stats): array
    {
        return [
            'avg'   => $stats->avg_price ? number_format((float)($stats->avg_price / 10000), 1) : null,
            'min'   => $stats->min_price ? number_format((float)($stats->min_price / 10000), 1) : null,
            'max'   => $stats->max_price ? number_format((float)($stats->max_price / 10000), 1) : null,
            'count' => $stats->count,
        ];
    }

    private function roundUpPrice(int $price): int
    {
        if ($price <= 0) return 300;
        if ($price <= 50) return 50;
        if ($price <= 100) return 100;
        if ($price <= 200) return 200;
        if ($price <= 300) return 300;
        if ($price <= 500) return 500;
        return (int) ceil($price / 100) * 100;
    }

    private function roundUpMileage(int $mileage): int
    {
        if ($mileage <= 0) return 50000;
        if ($mileage <= 10000) return 10000;
        if ($mileage <= 30000) return 30000;
        if ($mileage <= 50000) return 50000;
        if ($mileage <= 100000) return 100000;
        return (int) ceil($mileage / 50000) * 50000;
    }
}