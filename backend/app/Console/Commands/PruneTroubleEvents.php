<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\TroubleEvent;
use Illuminate\Console\Command;

/**
 * 症状診断ファネルの計測イベントを 180 日で削除（保持期間）。
 */
final class PruneTroubleEvents extends Command
{
    protected $signature = 'trouble:prune {--days=180 : この日数より古い行を削除}';

    protected $description = 'trouble_events の古い行を削除（既定180日）';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $cutoff = now()->subDays($days);

        $total = 0;
        do {
            $deleted = TroubleEvent::query()
                ->where('created_at', '<', $cutoff)
                ->limit(1000)
                ->delete();
            $total += $deleted;
        } while ($deleted > 0);

        $this->info("trouble_events 削除: {$total} 件（{$days} 日より古い行）");

        return self::SUCCESS;
    }
}
