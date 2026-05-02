<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Shop;
use App\Services\Parking\AddressParser;
use Illuminate\Console\Command;

class BackfillShopCity extends Command
{
    protected $signature = 'shops:backfill-city {--force : 既にcityが設定済みのshopも上書きする}';
    protected $description = 'AddressParserを使って全shopsのcityカラムをバックフィルします';

    public function handle(AddressParser $parser): int
    {
        $query = Shop::whereNotNull('address')->where('address', '!=', '');

        if (! $this->option('force')) {
            $query->whereNull('city');
        }

        $total = $query->count();
        if ($total === 0) {
            $this->info('バックフィル対象のshopはありません。');
            return self::SUCCESS;
        }

        $this->info("対象: {$total} shops");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $updated = 0;
        $failed = 0;

        $query->chunkById(500, function ($shops) use ($parser, $bar, &$updated, &$failed) {
            foreach ($shops as $shop) {
                $result = $parser->parse($shop->address);
                if ($result['city'] !== '') {
                    $shop->updateQuietly(['city' => $result['city']]);
                    $updated++;
                } else {
                    $failed++;
                }
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info("完了: 更新={$updated}, パース失敗={$failed}");

        return self::SUCCESS;
    }
}
