<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class FixSoldOutAnomaly extends Command
{
    protected $signature = 'listings:fix-sold-out-anomaly {--dry-run : 実行せずに対象件数のみ表示}';

    protected $description = '5/20の一括sold_out誤判定を修正（GooBike site_id=1）';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        // GooBike(site_id=1) で 2026-05-20 に sold_out になった listings のうち、
        // last_seen_at が 2026-05-19 以降（= 直前まで生きていた = 誤判定）
        $query = DB::table('listings')
            ->where('site_id', 1)
            ->where('is_sold_out', true)
            ->whereDate('updated_at', '2026-05-20')
            ->where('last_seen_at', '>=', '2026-05-19 00:00:00');

        $count = $query->count();

        if ($count === 0) {
            $this->info('対象データはありませんでした。');
            return self::SUCCESS;
        }

        $this->warn("対象: {$count}件（GooBike, 5/20 sold_out, last_seen >= 5/19）");

        if ($dryRun) {
            $this->info('[dry-run] 実際の更新はスキップしました。');
            return self::SUCCESS;
        }

        if (! $this->confirm('これらを is_sold_out=false に戻しますか？')) {
            $this->info('キャンセルしました。');
            return self::SUCCESS;
        }

        $updated = DB::table('listings')
            ->where('site_id', 1)
            ->where('is_sold_out', true)
            ->whereDate('updated_at', '2026-05-20')
            ->where('last_seen_at', '>=', '2026-05-19 00:00:00')
            ->update([
                'is_sold_out' => false,
                'needs_reindex' => true,
            ]);

        $this->info("{$updated}件を is_sold_out=false に復元しました。");
        $this->info('scout:sync-flagged を実行して Meilisearch を同期してください。');

        return self::SUCCESS;
    }
}
