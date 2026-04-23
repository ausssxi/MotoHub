<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BikeParking;
use App\Models\Station;
use Illuminate\Console\Command;

final class SetStationCity extends Command
{
    protected $signature = 'station:set-city {--force : 既存のcity値も上書き}';

    protected $description = '駅の city カラムを近隣 bike_parkings から推定して設定';

    public function handle(): int
    {
        $query = Station::query();
        if (! $this->option('force')) {
            $query->where(fn ($q) => $q->whereNull('city')->orWhere('city', ''));
        }
        $stations = $query->get();

        $this->info("対象駅: {$stations->count()}駅");
        $bar = $this->output->createProgressBar($stations->count());

        $updated = 0;

        foreach ($stations as $station) {
            $nearbyParkings = BikeParking::selectRaw('
                city,
                (6371 * acos(cos(radians(?))
                    * cos(radians(latitude))
                    * cos(radians(longitude) - radians(?))
                    + sin(radians(?))
                    * sin(radians(latitude))
                )) AS distance_km
            ', [$station->latitude, $station->longitude, $station->latitude])
                ->having('distance_km', '<', 0.5)
                ->whereNotNull('city')
                ->where('city', '!=', '')
                ->get();

            if ($nearbyParkings->isNotEmpty()) {
                // 都道府県プレフィックスを除去してからグルーピング
                $cleaned = $nearbyParkings->map(function ($p) {
                    return $this->stripPrefecture($p->city);
                })->filter(fn ($c) => $c !== '');

                if ($cleaned->isNotEmpty()) {
                    $mostCommonCity = $cleaned
                        ->countBy()
                        ->sortDesc()
                        ->keys()
                        ->first();

                    $station->update(['city' => $mostCommonCity]);
                    $updated++;
                }
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("city 設定完了: {$updated}駅に付与");

        // サマリー
        $totalWithCity = Station::whereNotNull('city')->where('city', '!=', '')->count();
        $totalStations = Station::count();
        $this->info("全体: {$totalWithCity} / {$totalStations} 駅にcityあり");

        return self::SUCCESS;
    }

    /** 都道府県プレフィックスを除去 */
    private function stripPrefecture(string $city): string
    {
        // "東京都渋谷区" → "渋谷区", "神奈川県横浜市" → "横浜市"
        if (preg_match('/^(.{2,4}?[都道府県])(.+)$/u', $city, $m)) {
            return $m[2];
        }

        return $city;
    }
}
