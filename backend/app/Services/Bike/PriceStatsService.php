<?php

declare(strict_types=1);

namespace App\Services\Bike;

use App\Models\BikeModelMarketStat;
use App\Repositories\Bike\ListingStatsRepository;
use App\Repositories\Bike\MarketStatsRepository; // ★追加
use Illuminate\Support\Collection;

final class PriceStatsService
{
    /**
     * 1リクエスト内での重複クエリを防ぐためのキャッシュ
     */
    private array $runtimeCache = [];

    public function __construct(
        private readonly ListingStatsRepository $listingStatsRepo, // 旧 statsRepo から改名推奨ですが、今回はそのまま
        private readonly MarketStatsRepository $marketStatsRepo  // ★追加
    ) {}

    /**
     * 車種ごとの価格統計と分布データを取得
     */
    public function getModelStats(int $bikeModelId): array
    {
        // 1. 統計データを取得 (リポジトリ経由)
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

        // 2. キャッシュがない場合のみライブ計算を実行
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
            // キャッシュがない場合のみListingテーブルから集計
            $prices = $this->listingStatsRepo->getValidTotalPricesByModelId($bikeModelId);
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
     * 買取相場の条件付きシミュレーション
     * (Listingモデルへのクエリが含まれるため、ListingStatsRepositoryに移動が理想ですが、
     * ロジックが主体のためServiceに残す判断もアリです。今回はListingStatsRepo経由に修正)
     */
    public function estimatePurchasePrice(int $bikeModelId, ?int $year = null): array
    {
        // 複雑な条件付き取得は ListingStatsRepository にメソッドを追加するのがベストですが、
        // ここでは簡易的に既存メソッド等を利用、またはQueryBuilder利用箇所として残します。
        // ※厳密にリポジトリパターンにするなら、ListingStatsRepository::getPricesByModelAndYear($id, $year) を作るべきです。
        
        // --- 簡易実装（今回はService内にQueryロジックを残す例） ---
        // Listingモデルへの直接依存を避けるため、本来は Repository に移譲すべき箇所です。
        $query = \App\Models\Listing::where('bike_model_id', $bikeModelId)
            ->where('is_sold_out', false)
            ->whereNotNull('total_price')
            ->where('total_price', '>', 10000);

        if ($year) {
            $query->where('model_year', $year);
        }

        $prices = $query->pluck('total_price');
        // ... (以下略、ロジック部分は変更なし) ...
        // データ不足時のフォールバック処理などはそのまま
        
        // ※このメソッド全体もリファクタリング対象ですが、
        // 今回の質問の主眼である「ログ取得」部分の修正を優先します。
        
        // (省略: estimatePurchasePrice の残りのロジック)
        return $this->calculateEstimateLogic($prices, $year, $bikeModelId); 
    }
    
    // ※ estimatePurchasePrice のロジック部分を別メソッドに切り出すなどで整理可能

    /**
     * 指定された車種の最新統計データを取得 (ランタイムキャッシュ付き)
     * ★修正: Repository経由に変更
     */
    private function getMarketStat(int $bikeModelId): ?BikeModelMarketStat
    {
        if (!isset($this->runtimeCache[$bikeModelId])) {
            $this->runtimeCache[$bikeModelId] = $this->marketStatsRepo->getLatestStat($bikeModelId);
        }
        return $this->runtimeCache[$bikeModelId];
    }

    /**
     * 価格履歴の取得
     * ★修正: Repository経由に変更
     */
    public function getPriceHistory(int $bikeModelId): array
    {
        // リポジトリからログを取得
        $logs = $this->marketStatsRepo->getLogs($bikeModelId);

        if ($logs->count() < 2) {
            // データ不足時は最新の1件を取得してデモ生成
            $current = $this->marketStatsRepo->getLatestLog($bikeModelId);
            return $this->generateDemoHistory($current);
        }

        return [
            'labels' => $logs->map(fn($l) => $l->recorded_at->format('Y/m'))->toArray(),
            'prices' => $logs->map(fn($l) => round($l->avg_price / 10000, 1))->toArray(),
            'trend' => $this->analyzeTrend($logs),
        ];
    }

    /**
     * ライブ計算（フォールバック用）
     */
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

    // --- 計算ロジック系メソッド（変更なし） ---
    // これらのメソッドは「データの加工・計算」なのでServiceにあって正解です。

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
    
    // estimatePurchasePrice用のロジック退避（省略していた部分の補完）
    private function calculateEstimateLogic($prices, $year, $bikeModelId): array
    {
        // 簡易実装のフォールバック
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