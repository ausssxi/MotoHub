<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Listing;
use Illuminate\Support\Facades\DB;

class OptimizeSearchData extends Command
{
    protected $signature = 'bikes:optimize-search-data {--limit= : 1回の実行で処理する最大件数}';
    protected $description = '検索高速化のためにlistingsテーブルのカラムを更新します';

    public function handle(): void
    {
        $limit = $this->option('limit') !== null
            ? max(1, (int) $this->option('limit'))
            : (int) config('search_optimize.limit', 20000);
        $sleepMs = (int) config('search_optimize.sleep_ms', 200);

        $this->info('検索データの最適化を開始します...');

        // メモリ不足対策のためChunkで処理
        // BikeModelのリレーションを使ってデータを取得し、listingsにコピー
        // 掲載中(is_sold_out=0)を先に処理し、利用者に見える不具合を早く解消する（→ id昇順で決定的）。
        $query = Listing::with('bikeModel')->whereNull('manufacturer_id')
            ->orderBy('is_sold_out')
            ->orderBy('id');

        $count = $query->count();
        $bar = $this->output->createProgressBar(min($count, $limit));

        // chunk 構造は維持。処理合計が limit に達したら return false で打ち切る（scout:sync-flagged と同方式）。
        $processed = 0;
        $query->chunk(1000, function ($listings) use ($bar, &$processed, $limit, $sleepMs) {
            foreach ($listings as $listing) {
                if ($listing->bikeModel) {
                    // クエリビルダで直接更新して高速化
                    DB::table('listings')
                        ->where('id', $listing->id)
                        ->update([
                            'manufacturer_id' => $listing->bikeModel->manufacturer_id,
                            'category_id'     => $listing->bikeModel->category_id,
                            'displacement'    => $listing->bikeModel->displacement,
                            // denorm列を直したので、Meilisearchのフィルタ(manufacturer_id/category_id/displacement)に
                            // 反映させるため再インデックス対象にする（scout:sync-flagged が拾う）。
                            'needs_reindex'   => true,
                        ]);
                }
                $bar->advance();
                $processed++;
            }

            if ($processed >= $limit) {
                return false; // 上限到達で打ち切り
            }

            // DB/検索を詰まらせないよう chunk 間に短い待機（0で無効）。
            if ($sleepMs > 0) {
                usleep($sleepMs * 1000);
            }
        });

        $bar->finish();
        $this->newLine();

        if ($processed >= $limit) {
            $remaining = Listing::whereNull('manufacturer_id')->count();
            $this->info("上限に達したため {$processed}件で終了。残り約{$remaining}件");
        } else {
            $this->info("最適化が完了しました！（{$processed}件）");
        }
    }
}