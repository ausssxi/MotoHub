<?php

declare(strict_types=1);

namespace App\Services\Bike;

use App\Models\MarketPriceLog;
use App\Repositories\Bike\ListingStatsRepository;
use Illuminate\Support\Collection;

final class PriceStatsService
{
    public function __construct(
        private readonly ListingStatsRepository $statsRepo
    ) {}

    /**
     * 車種ごとの価格統計と分布データを取得
     */
    public function getModelStats(int $bikeModelId): array
    {
        // ListingStatsRepository から取得
        $prices = $this->statsRepo->getValidTotalPricesByModelId($bikeModelId);

        if ($prices->isEmpty()) {
            return [];
        }
        
        // 1. 基本統計量（万円単位に変換）
        $min = $prices->min() / 10000;
        $max = $prices->max() / 10000;
        $avg = $prices->avg() / 10000;

        // 2. ヒストグラム（分布）データの作成
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
     * 買取相場・リセールバリューの推計データを取得
     */
    public function getResaleStats(int $bikeModelId): array
    {
        $prices = $this->statsRepo->getValidTotalPricesByModelId($bikeModelId);

        if ($prices->isEmpty()) {
            return [];
        }

        $avg = $prices->avg(); // 円単位
        
        // 簡易ロジック: 販売価格の 40%〜65% を買取相場レンジとする
        // ※実際は車種人気や状態によりますが、目安として表示
        $resaleMin = floor($avg * 0.4 / 10000) * 10000;
        $resaleMax = floor($avg * 0.65 / 10000) * 10000;

        return [
            'market_avg' => round($avg / 10000, 1), // 万円
            'resale_min' => round($resaleMin / 10000, 1),
            'resale_max' => round($resaleMax / 10000, 1),
            'data_count' => $prices->count(),
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

    /**
     * 価格推移データを取得
     */
    public function getPriceHistory(int $bikeModelId): array
    {
        // 過去1年分のデータを取得
        $logs = MarketPriceLog::where('bike_model_id', $bikeModelId)
            ->where('recorded_at', '>=', now()->subYear())
            ->orderBy('recorded_at', 'asc')
            ->get();

        // データが少なすぎる場合（導入直後など）は、デモ用に過去データを擬似生成して返す
        // ※本番運用でデータが溜まってきたら、このifブロックは削除してください
        if ($logs->count() < 2) {
            return $this->generateDemoHistory($logs->last());
        }

        return [
            'labels' => $logs->map(fn($l) => $l->recorded_at->format('Y/m'))->toArray(),
            'prices' => $logs->map(fn($l) => round($l->avg_price / 10000, 1))->toArray(),
            'trend' => $this->analyzeTrend($logs),
        ];
    }

    /**
     * トレンド分析（買い時判定）
     */
    private function analyzeTrend($logs): array
    {
        if ($logs->isEmpty()) return ['status' => 'unknown', 'message' => 'データ収集中です'];

        $first = $logs->first()->avg_price;
        $last = $logs->last()->avg_price;
        
        $diff = $last - $first;
        $rate = $first > 0 ? ($diff / $first) * 100 : 0;

        if ($rate > 5) {
            return ['status' => 'up', 'message' => '価格上昇中！早めの検討がおすすめ'];
        } elseif ($rate < -5) {
            return ['status' => 'down', 'message' => '値下がり傾向！今が買い時かも？'];
        } else {
            return ['status' => 'flat', 'message' => '相場は安定しています'];
        }
    }

    /**
     * デモデータ生成 (データ不足時用)
     */
    private function generateDemoHistory($currentStat): array
    {
        if (!$currentStat) return [];

        $basePrice = $currentStat->avg_price / 10000;
        $data = [];
        $labels = [];
        
        // 半年前から現在まで
        for ($i = 6; $i >= 0; $i--) {
            $labels[] = now()->subMonths($i)->format('Y/m');
            // ランダムに変動させる (-5% ~ +5%)
            $fluctuation = $basePrice * (rand(-5, 5) / 100); 
            $data[] = round($basePrice + $fluctuation, 1);
        }
        
        // 最新の値をセット
        $data[count($data)-1] = round($basePrice, 1);

        return [
            'labels' => $labels,
            'prices' => $data,
            'trend' => ['status' => 'flat', 'message' => '現在データを収集中です'],
        ];
    }

}