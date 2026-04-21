<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BikeParking;
use App\Models\Station;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class LinkParkingsToStations extends Command
{
    protected $signature = 'station:link-parkings
        {--radius=0.5 : Search radius in km}
        {--dry-run : Preview without saving}';

    protected $description = '駐車場のnotes/name/addressから最寄り駅を抽出し、station_idを紐付け';

    public function handle(): int
    {
        $radius = (float) $this->option('radius');
        $stations = Station::all();

        if ($stations->isEmpty()) {
            $this->error('駅データがありません。先に station:import を実行してください。');

            return self::FAILURE;
        }

        $this->info("駅数: {$stations->count()} / 半径: {$radius}km");

        // 全駅の名前リストを構築（名前→Station[]のマップ）
        $stationsByName = [];
        foreach ($stations as $station) {
            $stationsByName[$station->name][] = $station;
        }

        $parkings = BikeParking::active()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get();

        $this->info("対象駐車場: {$parkings->count()}件");

        $bar = $this->output->createProgressBar($parkings->count());
        $linked = 0;
        $skipped = 0;

        foreach ($parkings as $parking) {
            $bar->advance();

            $station = $this->findNearestStation($parking, $stations, $stationsByName, $radius);

            if (! $station) {
                $skipped++;

                continue;
            }

            if ($this->option('dry-run')) {
                if ($linked < 20) {
                    $this->newLine();
                    $this->info("[DRY] {$parking->name} → {$station->name}駅");
                }
                $linked++;

                continue;
            }

            $parking->update(['station_id' => $station->id]);
            $linked++;
        }

        $bar->finish();
        $this->newLine(2);

        if ($this->option('dry-run')) {
            $this->warn("DRY RUN - 紐付け対象: {$linked}件 / 対象外: {$skipped}件");
        } else {
            $this->info("完了: 紐付け {$linked}件 / 対象外: {$skipped}件");
        }

        return self::SUCCESS;
    }

    /**
     * 駐車場に最も近い駅を探す
     * 1. notes/name/addressに駅名が含まれていればその駅を優先
     * 2. なければ距離で最寄りを検索
     */
    private function findNearestStation(
        BikeParking $parking,
        \Illuminate\Database\Eloquent\Collection $stations,
        array $stationsByName,
        float $radiusKm,
    ): ?Station {
        // テキストマッチ: notes, name, addressから駅名を検索
        $searchText = implode(' ', array_filter([
            $parking->notes,
            $parking->name,
            $parking->address,
        ]));

        if ($searchText) {
            $matched = null;
            $matchedDist = PHP_FLOAT_MAX;

            foreach ($stationsByName as $stationName => $stationList) {
                if (mb_strlen($stationName) < 2) {
                    continue; // 1文字の駅名はノイズが多いのでスキップ
                }
                if (mb_strpos($searchText, $stationName.'駅') !== false || mb_strpos($searchText, $stationName) !== false) {
                    // テキストに駅名を含む → 最も近い同名駅を選択
                    foreach ($stationList as $s) {
                        $dist = $this->haversine(
                            $parking->latitude, $parking->longitude,
                            $s->latitude, $s->longitude
                        );
                        if ($dist < $matchedDist && $dist <= $radiusKm * 3) {
                            // テキストマッチは半径を緩めに（3倍まで許容）
                            $matchedDist = $dist;
                            $matched = $s;
                        }
                    }
                }
            }

            if ($matched) {
                return $matched;
            }
        }

        // 距離ベースで最寄り駅を検索
        $nearest = null;
        $nearestDist = PHP_FLOAT_MAX;

        foreach ($stations as $station) {
            $dist = $this->haversine(
                $parking->latitude, $parking->longitude,
                $station->latitude, $station->longitude
            );
            if ($dist < $nearestDist && $dist <= $radiusKm) {
                $nearestDist = $dist;
                $nearest = $station;
            }
        }

        return $nearest;
    }

    private function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371; // km
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
