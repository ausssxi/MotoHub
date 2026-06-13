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
        // toSearchableArray が読む relation を IN一括で eager-load（per-listingのN+1防止）。
        // bargain_score accessor が bikeModel.nationalPriceStat（全国中央値）を使うため必須。
        $query = Listing::where('needs_reindex', true)
            ->with(['shop', 'bikeModel.manufacturer', 'bikeModel.marketStats', 'bikeModel.nationalPriceStat', 'tags']);
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
