<?php

declare(strict_types=1);

namespace App\Services\Bike;

use App\Models\MarketPriceLog;
use App\Models\BikeModelMarketStat;
use App\Repositories\Bike\ListingStatsRepository;
use Illuminate\Support\Collection;

final class PriceStatsService
{
    /**
     * 1リクエスト内での重複クエリを防ぐためのキャッシュ
     */
    private array $runtimeCache = [];

    public function __construct(
        private readonly ListingStatsRepository $statsRepo
    ) {}

    /**
     * 車種ごとの価格統計と分布データを取得
     */
    public function getModelStats(int $bikeModelId): array
    {
        // 1. 統計データを取得 (キャッシュまたはDBから1行だけ)
        $cached = $this->getMarketStat($bikeModelId);

        // キャッシュに分布データ(JSON)が入っていれば、即座にそれを返す
        if ($cached && $cached->listing_count > 0 && !empty($cached->distribution_data)) {
            return [
                'count' => (int)$cached->listing_count,
                'min'   => round($cached->min_price / 10000, 1),
                'max'   => round($cached->max_price / 10000, 1),
                'avg'   => round($cached->avg_price / 10000, 1),
                'distribution' => $cached->distribution_data,
            ];
        }

        // 2. キャッシュがない場合のみライブ計算を実行 (非常に稀なケースにする)
        return $this->calculateLiveStats($bikeModelId);
    }

    /**
     * 買取相場・リセールバリューの推計データを取得
     */
    public function getResaleStats(int $bikeModelId): array
    {
        $cached = $this->getMarketStat($bikeModelId);
        
        if ($cached && $cached->avg_price > 0) {
            $avg = $cached->avg_price;
            $count = $cached->listing_count;
        } else {
            // キャッシュがない場合のみDBから集計（重い）
            $prices = $this->statsRepo->getValidTotalPricesByModelId($bikeModelId);
            if ($prices->isEmpty()) return [];
            $avg = $prices->avg();
            $count = $prices->count();
        }

        // 買取相場計算 (40%〜65%)
        $resaleMin = floor($avg * 0.4 / 10000) * 10000;
        $resaleMax = floor($avg * 0.65 / 10000) * 10000;

        return [
            'market_avg' => round($avg / 10000, 1),
            'resale_min' => round($resaleMin / 10000, 1),
            'resale_max' => round($resaleMax / 10000, 1),
            'data_count' => (int)$count,
        ];
    }

    /**
     * 指定された車種の最新統計データを取得 (ランタイムキャッシュ付き)
     */
    private function getMarketStat(int $bikeModelId): ?BikeModelMarketStat
    {
        if (!isset($this->runtimeCache[$bikeModelId])) {
            $this->runtimeCache[$bikeModelId] = BikeModelMarketStat::where('bike_model_id', $bikeModelId)->first();
        }
        return $this->runtimeCache[$bikeModelId];
    }

    /**
     * 価格履歴の取得
     */
    public function getPriceHistory(int $bikeModelId): array
    {
        $logs = MarketPriceLog::where('bike_model_id', $bikeModelId)
            ->where('recorded_at', '>=', now()->subYear())
            ->orderBy('recorded_at', 'asc')
            ->get();

        if ($logs->count() < 2) {
            $current = MarketPriceLog::where('bike_model_id', $bikeModelId)->latest('recorded_at')->first();
            return $this->generateDemoHistory($current);
        }

        return [
            'labels' => $logs->map(fn($l) => $l->recorded_at->format('Y/m'))->toArray(),
            'prices' => $logs->map(fn($l) => round($l->avg_price / 10000, 1))->toArray(),
            'trend' => $this->analyzeTrend($logs),
        ];
    }

    /**
     * ライブ計算（フォールバック用：通常は実行されない）
     */
    private function calculateLiveStats(int $bikeModelId): array
    {
        $prices = $this->statsRepo->getValidTotalPricesByModelId($bikeModelId);

        if ($prices->isEmpty()) {
            return ['count' => 0, 'min' => 0, 'max' => 0, 'avg' => 0, 'distribution' => []];
        }

        $min = $prices->min() / 10000;
        $max = $prices->max() / 10000;
        $avg = $prices->avg() / 10000;
        $step = $this->calculateStep($min, $max);

        return [
            'count' => $prices->count(),
            'min'   => round($min, 1),
            'max'   => round($max, 1),
            'avg'   => round($avg, 1),
            'distribution' => $this->createDistribution($prices, $step),
        ];
    }

    private function calculateStep($min, $max): int
    {
        $diff = $max - $min;
        if ($diff <= 50) return 5;
        if ($diff <= 100) return 10;
        if ($diff <= 300) return 20;
        return 50;
    }

    private function createDistribution(Collection $prices, int $step): array
    {
        $dist = [];
        $pricesInMan = $prices->map(fn($p) => floor(($p / 10000) / $step) * $step);
        $minRange = (int)$pricesInMan->min();
        $maxRange = (int)$pricesInMan->max();

        for ($i = $minRange; $i <= $maxRange; $i += $step) {
            $count = $pricesInMan->filter(fn($p) => $p == $i)->count();
            $dist[] = [
                'range_min' => $i * 10000,
                'range_max' => ($i + $step) * 10000,
                'label' => "{$i}~" . ($i + $step) . "万",
                'count' => $count
            ];
        }
        return $dist;
    }

    private function analyzeTrend($logs): array
    {
        if ($logs->isEmpty()) return ['status' => 'unknown', 'message' => 'データ収集中です'];
        $first = $logs->first()->avg_price;
        $last = $logs->last()->avg_price;
        $diff = $last - $first;
        $rate = $first > 0 ? ($diff / $first) * 100 : 0;

        if ($rate > 5) return ['status' => 'up', 'message' => '価格上昇中！早めの検討がおすすめ'];
        if ($rate < -5) return ['status' => 'down', 'message' => '値下がり傾向！今が買い時かも？'];
        return ['status' => 'flat', 'message' => '相場は安定しています'];
    }

    private function generateDemoHistory($currentStat): array
    {
        $basePrice = $currentStat ? ($currentStat->avg_price / 10000) : 50;
        $data = []; $labels = [];
        for ($i = 6; $i >= 0; $i--) {
            $labels[] = now()->subMonths($i)->format('Y/m');
            $fluctuation = $basePrice * (rand(-5, 5) / 100); 
            $data[] = round($basePrice + $fluctuation, 1);
        }
        $data[count($data)-1] = round($basePrice, 1);
        return [
            'labels' => $labels,
            'prices' => $data,
            'trend' => ['status' => 'flat', 'message' => '現在データを収集中です'],
        ];
    }
}