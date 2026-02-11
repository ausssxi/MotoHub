<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\BikeModel;
use App\Models\Listing;
use App\Models\BikeModelMarketStat;
use Illuminate\Support\Facades\DB;

class UpdateMarketStats extends Command
{
    protected $signature = 'bikes:update-market-stats';
    protected $description = '全車種の市場価格統計（平均・最安・最高）を再計算して保存します';

    public function handle(): void
    {
        $this->info('市場価格の集計を開始します...');

        // listingsテーブルから bike_model_id ごとに集計
        // 異常値（0円や10億円など）を除外して集計
        $stats = Listing::select(
                'bike_model_id',
                DB::raw('AVG(total_price) as avg_val'),
                DB::raw('MIN(total_price) as min_val'),
                DB::raw('MAX(total_price) as max_val'),
                DB::raw('COUNT(*) as count_val')
            )
            ->where('is_sold_out', false)
            ->whereNotNull('bike_model_id')
            ->whereNotNull('total_price')
            ->where('total_price', '>', 10000)      // 1万円以上
            ->where('total_price', '<', 50000000)   // 5000万円以下
            ->groupBy('bike_model_id')
            ->get();

        $this->output->progressStart($stats->count());

        foreach ($stats as $stat) {
            // データが少なすぎる場合は信頼性が低いため保存しない（または別途フラグを立てる）
            if ($stat->count_val < 2) {
                $this->output->progressAdvance();
                continue;
            }

            BikeModelMarketStat::updateOrCreate(
                ['bike_model_id' => $stat->bike_model_id],
                [
                    'avg_price' => (int)$stat->avg_val,
                    'min_price' => (int)$stat->min_val,
                    'max_price' => (int)$stat->max_val,
                    'listing_count' => (int)$stat->count_val,
                ]
            );
            
            $this->output->progressAdvance();
        }

        $this->output->progressFinish();
        $this->info('集計完了！');
    }
}