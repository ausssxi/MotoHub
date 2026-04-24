<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BikeParking;
use App\Models\Station;
use App\Services\Parking\AddressParser;
use Illuminate\Console\Command;

class FixStationCities extends Command
{
    protected $signature = 'station:fix-cities
        {--dry-run : 保存せず確認のみ}';

    protected $description = '駅のcityを、紐付き or 最寄りのbike_parkingsから推定して設定します';

    /** cityが有効な市区町村かどうか */
    private function isValidCity(?string $city): bool
    {
        if ($city === null || $city === '') {
            return false;
        }

        // 1文字（「町」「市」「区」等）は無効
        if (mb_strlen($city) < 2) {
            return false;
        }

        return (bool) preg_match('/[市区町村]$/u', $city);
    }

    public function handle(AddressParser $parser): void
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->info('[DRY RUN] データは保存されません。');
        }

        // 対象: cityがNULL/空/不正の全駅
        $stations = Station::get();
        $targets = $stations->filter(fn (Station $s) => !$this->isValidCity($s->city));

        $this->info("対象駅: {$targets->count()} / 全駅: {$stations->count()}");

        $fixed = 0;
        $skipped = 0;
        $bar = $this->output->createProgressBar($targets->count());
        $bar->start();

        foreach ($targets as $station) {
            $result = $this->inferCity($station, $parser);
            $bar->advance();

            if ($result === null) {
                $skipped++;
                continue;
            }

            [$newPref, $newCity] = $result;

            if ($station->city === $newCity && $station->prefecture === $newPref) {
                $skipped++;
                continue;
            }

            $updates = ['city' => $newCity];
            $prefChanged = '';
            if ($newPref !== '' && $station->prefecture !== $newPref) {
                $updates['prefecture'] = $newPref;
                $prefChanged = " pref:{$station->prefecture}→{$newPref}";
            }

            if ($dryRun) {
                $displayPref = $updates['prefecture'] ?? $station->prefecture;
                $oldCity = $station->city ?: '(空)';
                $this->newLine();
                $this->line("ID:{$station->id} {$station->name} [{$station->prefecture}|{$oldCity}] → [{$displayPref}|{$newCity}]{$prefChanged}");
            } else {
                $station->update($updates);
            }
            $fixed++;
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("完了: 修正={$fixed}, スキップ={$skipped}");
    }

    /**
     * 駅のcityを推定する
     * 1. station_id経由の紐付き駐車場から推定
     * 2. 緯度経度で最寄り駐車場から推定（半径1km）
     *
     * @return array{0: string, 1: string}|null [prefecture, city] or null
     */
    private function inferCity(Station $station, AddressParser $parser): ?array
    {
        // Step 1: station_id で紐付いた駐車場
        $parkings = BikeParking::where('station_id', $station->id)
            ->whereNotNull('address')
            ->where('address', '!=', '')
            ->get();

        $result = $this->voteCityFromParkings($parkings, $parser);
        if ($result !== null) {
            return $result;
        }

        // Step 2: 緯度経度で最寄り（±0.009度 ≈ 1km のバウンディングボックス）
        if ($station->latitude && $station->longitude) {
            $nearbyParkings = BikeParking::whereNotNull('address')
                ->where('address', '!=', '')
                ->whereBetween('latitude', [$station->latitude - 0.009, $station->latitude + 0.009])
                ->whereBetween('longitude', [$station->longitude - 0.009, $station->longitude + 0.009])
                ->orderByRaw('ABS(latitude - ?) + ABS(longitude - ?)', [$station->latitude, $station->longitude])
                ->limit(5)
                ->get();

            $result = $this->voteCityFromParkings($nearbyParkings, $parser);
            if ($result !== null) {
                return $result;
            }
        }

        return null;
    }

    /**
     * 駐車場コレクションから最頻city/prefを投票で決定
     *
     * @return array{0: string, 1: string}|null
     */
    private function voteCityFromParkings($parkings, AddressParser $parser): ?array
    {
        $cityVotes = [];
        $prefVotes = [];

        foreach ($parkings as $parking) {
            $parsed = $parser->parse($parking->address);
            $city = $parsed['city'];
            $pref = $parsed['prefecture'];

            if ($city !== '') {
                $cityVotes[$city] = ($cityVotes[$city] ?? 0) + 1;
            }
            if ($pref !== '') {
                $prefVotes[$pref] = ($prefVotes[$pref] ?? 0) + 1;
            }
        }

        if (empty($cityVotes)) {
            return null;
        }

        arsort($cityVotes);
        arsort($prefVotes);

        return [
            !empty($prefVotes) ? array_key_first($prefVotes) : '',
            array_key_first($cityVotes),
        ];
    }
}
