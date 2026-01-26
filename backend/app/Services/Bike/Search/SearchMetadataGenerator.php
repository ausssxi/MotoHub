<?php

declare(strict_types=1);

namespace App\Services\Bike\Search;

use App\Repositories\Bike\ListingStatsRepository;

final class SearchMetadataGenerator
{
    public function __construct(
        private readonly ListingStatsRepository $statsRepo // ✨ 変更: Statsリポジトリを使用
    ) {}

    /**
     * スライダー用のメタデータを生成
     */
    public function generate(?string $keyword, ?string $prefecture, array $filters): array
    {
        // 範囲フィルタを除外して「その車種の全体像」を取得
        // (例: 35万円以下で絞り込んでいても、スライダーの右端は全体の最大値にするため)
        $rangeKeys = ['min_price', 'max_price', 'min_mileage', 'max_mileage', 'min_year', 'max_year'];
        $cleanFilters = array_diff_key($filters, array_flip($rangeKeys));

        // StatsRepoを使って最小・最大値を取得
        $stats = $this->statsRepo->getMinMaxStats($keyword, $prefecture, $cleanFilters);

        $rawPrice = $stats->max_price ? (int) ceil($stats->max_price / 10000) : 0;
        $rawMileage = (int) ($stats->max_mileage ?? 0);

        return [
            'price' => [
                'min' => 0,
                'max' => $this->roundUpPrice($rawPrice),
                'raw_max' => $stats->max_price // 生の値も保持
            ],
            'mileage' => [
                'min' => 0,
                'max' => $this->roundUpMileage($rawMileage),
                'raw_max' => $stats->max_mileage
            ],
            'year' => [
                'min' => (int) ($stats->min_year ?? 1990),
                'max' => (int) ($stats->max_year ?? (int) date('Y')),
            ]
        ];
    }

    /**
     * ✨ 追加: 検索クエリ用のUIリミット値を計算して返す
     * コントローラー側でこれを呼び出し、ListingRepositoryに渡します
     */
    public function calculateUiLimits(array $meta): array
    {
        return [
            // メタデータの計算済みMAX値を使用
            'max_price' => $meta['price']['max'] ?? 300,
            'max_mileage' => $meta['mileage']['max'] ?? 50000,
        ];
    }

    /**
     * 統計データのフォーマット
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

    // --- 以下、プライベートヘルパーメソッド ---

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