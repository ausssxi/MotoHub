<?php

declare(strict_types=1);

namespace App\Services\Bike;

use App\Repositories\Bike\ListingRepository;
use Illuminate\Support\Collection;

final class PriceStatsService
{
    public function __construct(
        private readonly ListingRepository $listingRepo
    ) {}

    /**
     * 車種ごとの価格統計と分布データを取得
     */
    public function getModelStats(int $bikeModelId): array
    {
        // リポジトリから価格リスト（整数）を取得
        $prices = $this->listingRepo->getValidTotalPricesByModelId($bikeModelId);

        if ($prices->isEmpty()) {
            return [];
        }
        
        // 1. 基本統計量（万円単位に変換）
        $min = $prices->min() / 10000;
        $max = $prices->max() / 10000;
        $avg = $prices->avg() / 10000;

        // 2. ヒストグラム（分布）データの作成
        // 価格帯の刻み幅を動的に決定（例: 5万円刻み、10万円刻み）
        $step = $this->calculateStep($min, $max);
        $distribution = $this->createDistribution($prices, $step);

        return [
            'count' => $prices->count(),
            'min'   => round($min, 1),
            'max'   => round($max, 1),
            'avg'   => round($avg, 1),
            'distribution' => $distribution,
            'step' => $step, // グラフのラベル生成に使用
        ];
    }

    /**
     * 価格差に応じて刻み幅を決定
     */
    private function calculateStep($min, $max): int
    {
        $diff = $max - $min;
        if ($diff <= 50) return 5;   // 差が50万以内なら5万刻み
        if ($diff <= 100) return 10; // 差が100万以内なら10万刻み
        if ($diff <= 300) return 20; // 差が300万以内なら20万刻み
        return 50;                   // それ以上は50万刻み
    }

    /**
     * 価格分布配列の生成
     */
    private function createDistribution(Collection $prices, int $step): array
    {
        $dist = [];
        // 万円単位で計算
        $pricesInMan = $prices->map(fn($p) => floor(($p / 10000) / $step) * $step);

        $minRange = $pricesInMan->min();
        $maxRange = $pricesInMan->max();

        // 最小〜最大までステップごとに枠を作って埋める
        for ($i = $minRange; $i <= $maxRange; $i += $step) {
            $key = (string)$i;
            $count = $pricesInMan->filter(fn($p) => $p == $i)->count();
            $dist[] = [
                'range_min' => $i,
                'range_max' => $i + $step,
                'label' => "{$i}~" . ($i + $step) . "万円",
                'count' => $count
            ];
        }

        return $dist;
    }
}