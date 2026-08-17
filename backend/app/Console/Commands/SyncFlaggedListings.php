<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Listing;
use Illuminate\Console\Command;

final class SyncFlaggedListings extends Command
{
    protected $signature = 'scout:sync-flagged {--limit= : 1回の実行で処理する最大件数}';

    protected $description = 'needs_reindex=1のListingだけMeilisearchに同期';

    public function handle(): void
    {
        $limit = $this->option('limit') !== null
            ? max(1, (int) $this->option('limit'))
            : (int) config('scout.sync_flagged_limit', 20000);
        $sleepMs = (int) config('scout.sync_flagged_sleep_ms', 200);

        // toSearchableArray が読む relation を IN一括で eager-load（per-listingのN+1防止）。
        // bargain_score accessor が bikeModel.nationalPriceStat（全国中央値）を使うため必須。
        $query = Listing::where('needs_reindex', true)
            ->with(['shop', 'bikeModel.manufacturer', 'bikeModel.marketStats', 'bikeModel.nationalPriceStat', 'tags']);
        $count = $query->count();

        if ($count === 0) {
            $this->info('同期対象なし');

            return;
        }

        $this->info(min($count, $limit).'件を同期中...（対象 '.$count.'件 / 上限 '.$limit.'件）');

        // chunk(500) 構造は維持。処理合計が limit に達したら return false で打ち切る。
        $processed = 0;
        $query->chunk(500, function ($listings) use (&$processed, $limit, $sleepMs) {
            $listings->searchable();
            Listing::whereIn('id', $listings->pluck('id'))
                ->update(['needs_reindex' => false]);

            $processed += $listings->count();

            if ($processed >= $limit) {
                return false; // 上限到達で打ち切り
            }

            // Meilisearch の非同期インデックス構築を詰まらせないよう chunk 間に短い待機（0で無効）。
            if ($sleepMs > 0) {
                usleep($sleepMs * 1000);
            }
        });

        if ($processed >= $limit) {
            $remaining = Listing::where('needs_reindex', true)->count();
            $this->info("上限に達したため {$processed}件で終了。残り約{$remaining}件");
        } else {
            $this->info("完了（{$processed}件）");
        }
    }
}
