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
    protected $description = '市場統計の更新とお買い得スコアの再計算を行います';

    public function handle(): void
    {
        $this->info('市場価格の集計とお買い得スコアの計算を開始します...');

        $modelIds = Listing::where('is_sold_out', false)
            ->whereNotNull('bike_model_id')
            ->distinct()
            ->pluck('bike_model_id');

        $this->output->progressStart($modelIds->count());
        $today = now()->format('Y-m-d');

        foreach ($modelIds as $modelId) {
            // 対象データを取得（価格と走行距離）
            // 走行距離が極端に短い/長いデータや、価格異常値を除外するフィルタも検討可能
            $listings = Listing::where('bike_model_id', $modelId)
                ->where('is_sold_out', false)
                ->whereNotNull('total_price')
                ->where('total_price', '>', 10000)
                ->where('total_price', '<', 50000000)
                ->select('id', 'total_price', 'mileage')
                ->get();

            if ($listings->count() < 2) {
                Listing::where('bike_model_id', $modelId)->update(['bargain_score' => 0]);
                $this->output->progressAdvance();
                continue;
            }

            // 統計計算
            $prices = $listings->pluck('total_price');
            $mileages = $listings->whereNotNull('mileage')->pluck('mileage');

            $avgPrice = (int)$prices->avg();
            $minPrice = (int)$prices->min();
            $maxPrice = (int)$prices->max();
            $count = $prices->count();
            
            // ★走行距離の平均も計算（データがない場合は0）
            $avgMileage = $mileages->isNotEmpty() ? (int)$mileages->avg() : 0;

            // 統計テーブル更新
            BikeModelMarketStat::updateOrCreate(
                ['bike_model_id' => $modelId],
                [
                    'avg_price' => $avgPrice,
                    'min_price' => $minPrice,
                    'max_price' => $maxPrice,
                    'listing_count' => $count,
                    'distribution_data' => $this->calculateDistribution($prices),
                ]
            );

            // ★改良版: お買い得スコアの計算
            // SQL一括更新だと複雑な計算が難しいため、PHP側で計算してID指定で更新します
            // 件数が多いと少し時間がかかりますが、精度優先です
            foreach ($listings as $item) {
                // 1. 価格スコア (平均より安いほどプラス)
                $priceScore = 0;
                if ($avgPrice > 0) {
                    $priceScore = ($avgPrice - $item->total_price) / $avgPrice * 100;
                }

                // 2. 距離スコア (平均より走っていないほどプラス)
                $mileageScore = 0;
                if ($avgMileage > 0 && $item->mileage !== null) {
                    // 距離の影響度は価格の半分くらいにする調整係数 (0.5)
                    $mileageScore = (($avgMileage - $item->mileage) / $avgMileage * 100) * 0.5;
                }

                // 合計スコア
                $totalScore = $priceScore + $mileageScore;

                // DB更新 (個別にupdateすると遅いので、実運用ではバルクアップデートが推奨されますが、
                // 今回はシンプルに1件ずつ更新します。件数が数万件レベルなら許容範囲です)
                DB::table('listings')
                    ->where('id', $item->id)
                    ->update(['bargain_score' => max(0, round($totalScore, 2))]);
            }

            // 履歴ログ保存
            MarketPriceLog::updateOrCreate(
                ['bike_model_id' => $modelId, 'recorded_at' => $today],
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
        $this->info('集計完了！');
    }

    /**
     * 価格リストからヒストグラム（分布）データを生成します。
     */
    private function calculateDistribution(Collection $prices): array
    {
        $minVal = (int) floor($prices->min() / 10000); // 万円単位
        $maxVal = (int) ceil($prices->max() / 10000);
        
        // 価格差に応じて刻み幅(step)を決める
        $diff = $maxVal - $minVal;
        
        if ($diff <= 50) {
            $step = 5;
        } elseif ($diff <= 100) {
            $step = 10;
        } elseif ($diff <= 300) {
            $step = 20;
        } else {
            $step = 50;
        }

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
}