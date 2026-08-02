<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\RentalGarage;
use App\Services\GsiGeocodingService;
use Illuminate\Console\Command;

final class GeocodeRentalGarages extends Command
{
    protected $signature = 'rental_garage:geocode
        {--retry-failed : geocode_status=failed も対象に含める}
        {--limit= : 処理件数上限}
        {--sleep=1000 : 1件ごとの待機ミリ秒（既定1秒）}';

    protected $description = 'rental_garages の住所を GSI でジオコーディングし座標を埋める';

    // 日本の緯度経度おおよその範囲（範囲外は誤ジオコーディングとして out_of_range 扱い）。
    private const JP_LAT_MIN = 20.0;
    private const JP_LAT_MAX = 46.0;
    private const JP_LNG_MIN = 122.0;
    private const JP_LNG_MAX = 154.0;

    public function handle(GsiGeocodingService $geocoder): int
    {
        $statuses = ['pending'];
        if ($this->option('retry-failed')) {
            $statuses[] = 'failed';
        }

        $sleepMs = (int) $this->option('sleep');
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;

        $query = RentalGarage::query()
            ->whereIn('geocode_status', $statuses)
            ->orderBy('id');
        if ($limit !== null) {
            $query->limit($limit);
        }
        $records = $query->get();

        $totalTargets = $records->count();
        if ($totalTargets === 0) {
            $this->info('対象レコードなし（geocode_status: '.implode('/', $statuses).'）。');

            return self::SUCCESS;
        }

        $this->info("ジオコーディング対象: {$totalTargets} 件");

        $ok = 0;
        $failed = 0;
        $outOfRange = 0;
        $processed = 0;

        foreach ($records as $garage) {
            $pref = (string) ($garage->prefecture ?? '');
            $city = (string) ($garage->city ?? '');
            $address = (string) ($garage->address ?? '');

            // 1段階目: 住所そのまま。
            $coords = $geocoder->geocode($pref, $city, $address);

            // 2段階目: GSI が市区名検証で null を返すことがあるので、市区町村まで切り詰めて再試行。
            // geocode($pref, '', $city) は「県+市区町村」クエリになり市区名検証がスキップされる。
            if ($coords === null && $city !== '') {
                $coords = $geocoder->geocode($pref, '', $city);
            }

            if ($coords === null) {
                $garage->geocode_status = 'failed';
                $garage->save();
                $failed++;
            } elseif (
                $coords['lat'] < self::JP_LAT_MIN || $coords['lat'] > self::JP_LAT_MAX
                || $coords['lng'] < self::JP_LNG_MIN || $coords['lng'] > self::JP_LNG_MAX
            ) {
                // 日本の範囲外 → 座標は入れず out_of_range。
                $garage->geocode_status = 'out_of_range';
                $garage->save();
                $outOfRange++;
            } else {
                $garage->latitude = $coords['lat'];
                $garage->longitude = $coords['lng'];
                $garage->geocode_status = 'ok';
                $garage->save();
                $ok++;
            }

            $processed++;
            if ($processed % 50 === 0) {
                $this->line("  {$processed}/{$totalTargets} 件処理（ok {$ok} / failed {$failed} / out_of_range {$outOfRange}）");
            }

            if ($sleepMs > 0) {
                usleep($sleepMs * 1000);
            }
        }

        $this->newLine();
        $this->info('===== 完了 =====');
        $this->info("ok: {$ok} / failed: {$failed} / out_of_range: {$outOfRange}（合計 {$processed}）");

        return self::SUCCESS;
    }
}
