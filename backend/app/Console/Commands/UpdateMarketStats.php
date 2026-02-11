<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\BikeModel;
use App\Models\Listing;
use App\Models\BikeModelMarketStat;
use App\Models\MarketPriceLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class UpdateMarketStats extends Command
{
    protected $signature = 'bikes:update-market-stats';
    protected $description = '全車種の市場価格統計（分布データ含む）を再計算して保存します';

    public function handle(): void
    {
        $this->info('市場価格の集計を開始します（分布データの作成を含む）...');

        // 現在出品されている全車種のIDを取得
        $modelIds = Listing::where('is_sold_out', false)
            ->whereNotNull('bike_model_id')
            ->distinct()
            ->pluck('bike_model_id');

        $this->output->progressStart($modelIds->count());
        $today = now()->format('Y-m-d');
        
        foreach ($modelIds as $modelId) {
            // この車種の有効な価格リストを取得 (異常値を除外)
            $prices = Listing::where('bike_model_id', $modelId)
                ->where('is_sold_out', false)
                ->whereNotNull('total_price')
                ->where('total_price', '>', 10000)      // 1万円以上
                ->where('total_price', '<', 50000000)   // 5000万円以下
                ->pluck('total_price');

            // データが少なすぎる場合は信頼性が低いため保存しない
            if ($prices->count() < 2) {
                $this->output->progressAdvance();
                continue;
            }

            // 基本統計の計算
            $avgPrice = (int)$prices->avg();
            $minPrice = (int)$prices->min();
            $maxPrice = (int)$prices->max();
            $count = $prices->count();

            // ★グラフ表示用の分布（ヒストグラム）データを事前計算
            $distribution = $this->calculateDistribution($prices);

            // 1. 最新の統計情報を更新
            // distribution_data カラムにJSONとして保存することで、詳細表示を爆速にします
            BikeModelMarketStat::updateOrCreate(
                ['bike_model_id' => $modelId],
                [
                    'avg_price' => $avgPrice,
                    'min_price' => $minPrice,
                    'max_price' => $maxPrice,
                    'listing_count' => $count,
                    'distribution_data' => $distribution,
                ]
            );

            // 2. 履歴ログに保存 (時系列データ用)
            MarketPriceLog::updateOrCreate(
                [
                    'bike_model_id' => $modelId,
                    'recorded_at' => $today
                ],
                [
                    'avg_price' => $avgPrice,
                    'min_price' => $minPrice,
                    'max_price' => $maxPrice,
                    'listing_count' => $count,
                ]
            );
            
            $this->output->progressAdvance();
        }

        $this->output->progressFinish();
        $this->info('全車種の集計と分布データの作成が完了しました！');
    }

    /**
     * 価格リストからヒストグラム（分布）データを生成します。
     * 車種ごとの価格帯の広さに応じて、自動的に刻み幅(step)を調整します。
     */
    private function calculateDistribution(Collection $prices): array
    {
        $minVal = (int) floor($prices->min() / 10000); // 万円単位
        $maxVal = (int) ceil($prices->max() / 10000);
        
        $diff = $maxVal - $minVal;

        // 価格差に応じて適切な刻み幅(万円)を決定
        if ($diff <= 50) {
            $step = 5;   // 差が50万以内なら5万刻み
        } elseif ($diff <= 100) {
            $step = 10;  // 差が100万以内なら10万刻み
        } elseif ($diff <= 300) {
            $step = 20;  // 差が300万以内なら20万刻み
        } else {
            $step = 50;  // それ以上は50万刻み
        }

        $dist = [];
        // 各価格がどのステップに属するかを正規化して計算
        $pricesInMan = $prices->map(fn($p) => floor(($p / 10000) / $step) * $step);
        
        $minRange = (int)$pricesInMan->min();
        $maxRange = (int)$pricesInMan->max();

        // 最小範囲から最大範囲まで、ステップごとに台数を集計
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
}