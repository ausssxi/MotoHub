<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Listing;
use Illuminate\Console\Command;

final class SyncFlaggedListings extends Command
{
    protected $signature = 'scout:sync-flagged';

    protected $description = 'needs_reindex=1のListingだけMeilisearchに同期';

    public function handle(): void
    {
        $query = Listing::where('needs_reindex', true);
        $count = $query->count();

        if ($count === 0) {
            $this->info('同期対象なし');
            return;
        }

        $this->info("{$count}件を同期中...");

        $query->chunk(500, function ($listings) {
            $listings->searchable();
            Listing::whereIn('id', $listings->pluck('id'))
                ->update(['needs_reindex' => false]);
        });

        $this->info('完了');
    }
}
