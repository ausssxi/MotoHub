<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Listing;
use Illuminate\Support\Facades\DB;

class OptimizeSearchData extends Command
{
    protected $signature = 'bikes:optimize-search-data';
    protected $description = '検索高速化のためにlistingsテーブルのカラムを更新します';

    public function handle(): void
    {
        $this->info('検索データの最適化を開始します...');

        // メモリ不足対策のためChunkで処理
        // BikeModelのリレーションを使ってデータを取得し、listingsにコピー
        $query = Listing::with('bikeModel')->whereNull('manufacturer_id');
        
        $count = $query->count();
        $bar = $this->output->createProgressBar($count);

        $query->chunk(1000, function ($listings) use ($bar) {
            foreach ($listings as $listing) {
                if ($listing->bikeModel) {
                    // クエリビルダで直接更新して高速化
                    DB::table('listings')
                        ->where('id', $listing->id)
                        ->update([
                            'manufacturer_id' => $listing->bikeModel->manufacturer_id,
                            'category_id'     => $listing->bikeModel->category_id,
                            'displacement'    => $listing->bikeModel->displacement,
                        ]);
                }
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info('最適化が完了しました！');
    }
}