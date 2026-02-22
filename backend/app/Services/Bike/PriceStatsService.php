<?php

declare(strict_types=1);

namespace App\Services\Bike;

use App\Models\BikeModelMarketStat;
use App\Repositories\Bike\ListingStatsRepository;
use App\Repositories\Bike\MarketStatsRepository;
use Illuminate\Support\Collection;

final class PriceStatsService
{
    /**
     * 1リクエスト内での重複クエリを防ぐためのキャッシュ
     */
    private array $runtimeCache = [];

    public function __construct(
        private readonly ListingStatsRepository $listingStatsRepo, 
        private readonly MarketStatsRepository $marketStatsRepo
    ) {}

    /**
     * 車種ごとの価格統計と分布データを取得し、現在の車両価格との比較も行う
     */
    public function getModelStats(int $bikeModelId, ?float $currentPriceYen = null): array
    {
        // 1. 統計データを取得 (リポジトリ経由)
        $cached = $this->getMarketStat($bikeModelId);

        $stats = [];
        // キャッシュに分布データが入っていればそれを使う
        if ($cached && $cached->listing_count > 0 && !empty($cached->distribution_data)) {
            $stats = [
                'count' => (int)$cached->listing_count,
                'min'   => round($cached->min_price / 10000, 1),
                'max'   => round($cached->max_price / 10000, 1),
                'avg'   => round($cached->avg_price / 10000, 1),
                'distribution' => $cached->distribution_data,
            ];
        } else {
            // キャッシュがない場合のみライブ計算を実行
            $stats = $this->calculateLiveStats($bikeModelId);
        }

        // 2. 現在の価格との比較（rank, diff）を計算
        $stats['rank'] = 'unknown';
        $stats['diff'] = 0;

        // ★修正ポイント: コントローラーから渡ってくる円単位(例:500000)の金額を、万円単位(50.0)に直して比較
        $currentPriceMan = ($currentPriceYen !== null && $currentPriceYen > 0) ? $currentPriceYen / 10000 : 0;

        if (isset($stats['avg']) && $stats['avg'] > 0 && $currentPriceMan > 0) {
            $avg = $stats['avg'];
            $stats['diff'] = round($currentPriceMan - $avg, 1);

            // S: 10%以上安い, A: 平均より安い, B: 平均より10%未満高い, C: それ以上
            if ($currentPriceMan < $avg * 0.9) {
                $stats['rank'] = 'S';
            } elseif ($currentPriceMan < $avg) {
                $stats['rank'] = 'A';
            } elseif ($currentPriceMan < $avg * 1.1) {
                $stats['rank'] = 'B';
            } else {
                $stats['rank'] = 'C';
            }
        }

        return $stats;
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
            $prices = $this->listingStatsRepo->getValidTotalPricesByModelId($bikeModelId);
            if ($prices->isEmpty()) return [];
            $avg = $prices->avg();
            $count = $prices->count();
        }

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
     * 買取相場の条件付きシミュレーション
     */
    public function estimatePurchasePrice(int $bikeModelId, ?int $year = null): array
    {
        $query = \App\Models\Listing::where('bike_model_id', $bikeModelId)
            ->where('is_sold_out', false)
            ->whereNotNull('total_price')
            ->where('total_price', '>', 10000);

        if ($year) {
            $query->where('model_year', $year);
        }

        $prices = $query->pluck('total_price');
        
        return $this->calculateEstimateLogic($prices, $year, $bikeModelId); 
    }

    private function getMarketStat(int $bikeModelId): ?BikeModelMarketStat
    {
        if (!isset($this->runtimeCache[$bikeModelId])) {
            $this->runtimeCache[$bikeModelId] = $this->marketStatsRepo->getLatestStat($bikeModelId);
        }
        return $this->runtimeCache[$bikeModelId];
    }

    public function getPriceHistory(int $bikeModelId): array
    {
        $logs = $this->marketStatsRepo->getLogs($bikeModelId);

        if ($logs->count() < 2) {
            $current = $this->marketStatsRepo->getLatestLog($bikeModelId);
            return $this->generateDemoHistory($current);
        }

        return [
            'labels' => $logs->map(fn($l) => $l->recorded_at->format('Y/m'))->toArray(),
            'prices' => $logs->map(fn($l) => round($l->avg_price / 10000, 1))->toArray(),
            'trend' => $this->analyzeTrend($logs),
        ];
    }

    private function calculateLiveStats(int $bikeModelId): array
    {
        $prices = $this->listingStatsRepo->getValidTotalPricesByModelId($bikeModelId);

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
    
    private function calculateEstimateLogic($prices, $year, $bikeModelId): array
    {
        $isFallback = false;
        if ($prices->count() < 3 && $year) {
             $prices = $this->listingStatsRepo->getValidTotalPricesByModelId($bikeModelId);
             $isFallback = true;
        }
        
        if ($prices->isEmpty()) {
            return ['status' => 'empty'];
        }

        $avgRetail = (int)$prices->avg();
        $rateMin = 0.40;
        $rateMax = 0.65;
        $min = floor($avgRetail * $rateMin / 10000) * 10000;
        $max = floor($avgRetail * $rateMax / 10000) * 10000;

        return [
            'status' => 'success',
            'retail_avg' => number_format(round($avgRetail / 10000, 1), 1),
            'purchase_min' => number_format($min / 10000),
            'purchase_max' => number_format($max / 10000),
            'data_count' => $prices->count(),
            'is_fallback' => $isFallback,
        ];
    }
}