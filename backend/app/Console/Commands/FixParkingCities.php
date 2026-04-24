<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BikeParking;
use App\Services\Parking\AddressParser;
use Illuminate\Console\Command;

class FixParkingCities extends Command
{
    protected $signature = 'parking:fix-cities
        {--dry-run : 保存せず確認のみ}
        {--prefecture= : 特定の都道府県のみ対象}';

    protected $description = 'bike_parkingsのcity/prefectureをaddressから再パースして修正します';

    public function handle(AddressParser $parser): void
    {
        $dryRun = (bool) $this->option('dry-run');
        $prefFilter = $this->option('prefecture');

        if ($dryRun) {
            $this->info('[DRY RUN] データは保存されません。');
        }

        $query = BikeParking::whereNotNull('address')->where('address', '!=', '');

        if ($prefFilter) {
            $query->where('prefecture', $prefFilter);
        }

        $rows = $query->get();
        $fixed = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $parsed = $parser->parse($row->address);

            // パーサーが空prefを返した場合は既存値を維持
            $newPref = $parsed['prefecture'] ?: $row->prefecture;

            // パーサーが空cityを返した場合、既存cityが有効な市区町村なら維持、
            // 町名(本町等)のような不正値は空にクリア
            $newCity = $parsed['city'];
            if ($newCity === '' && $row->city !== '') {
                $newCity = (preg_match('/[市区]$/u', $row->city) || mb_strpos($row->city, '郡') !== false)
                    ? $row->city
                    : '';
            }

            if ($row->prefecture === $newPref && $row->city === $newCity) {
                $skipped++;
                continue;
            }

            if ($dryRun) {
                $this->line("ID:{$row->id} [{$row->prefecture}|{$row->city}] → [{$newPref}|{$newCity}]");
            } else {
                $row->update(['prefecture' => $newPref, 'city' => $newCity]);
            }
            $fixed++;
        }

        $this->info("完了: 修正={$fixed}, スキップ={$skipped}");
    }
}
