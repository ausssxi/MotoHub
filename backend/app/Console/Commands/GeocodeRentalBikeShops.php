<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\RentalBikeShop;
use App\Services\GsiGeocodingService;
use Illuminate\Console\Command;

/**
 * レンタルバイク店舗（rental_bike_shops）の住所から緯度経度を取得する。
 *
 * ★取得コマンド（rental-bike:fetch）とは分ける。★手動実行のみ（cron に載せない）。
 *
 * 既存のジオコーディングをそのまま使う（新規実装しない）:
 *   - forward は国土地理院(GSI)のみ。共有の GsiGeocodingService 経由（parking:geocode / shops:geocode と同作法）。
 *   - 失敗は geocode_failed_at に記録し、既定では再試行しない。--retry-failed のときだけ失敗行も対象。
 *   - prefecture / city が空の行は GSI へ問い合わせず、失敗記録もせずスキップ（後から補完されうるため）。
 *   - 日本範囲外・null は「未取得のまま」にし、もっともらしく間違った粗い座標は保存しない。
 *   - 座標が付かなくてもレコードは消さない（住所は正しい。地図に出せないだけ）。
 *   - GSI は公共APIのため1件ごとに待機する（--sleep 既定1000ms）。短縮しない・並列にしない。
 */
final class GeocodeRentalBikeShops extends Command
{
    protected $signature = 'rental-bike:geocode
        {--limit=1000 : 1回の実行で処理する最大件数}
        {--retry-failed : geocode_failed_at が記録済みの失敗行も対象に含める}
        {--force : 座標取得済みのデータも再取得する}
        {--sleep=1000 : 1件ごとの待機ミリ秒（GSIは公共API）}';

    protected $description = 'レンタルバイク店舗の住所から緯度経度を取得する（国土地理院ジオコーディング）';

    /** 日本の緯度経度の妥当範囲（範囲外は誤ジオコーディングとして未取得扱い）。 */
    private const LAT_MIN = 20.0;

    private const LAT_MAX = 46.0;

    private const LNG_MIN = 122.0;

    private const LNG_MAX = 154.0;

    public function handle(GsiGeocodingService $geocoder): int
    {
        $limit = (int) $this->option('limit');
        $force = (bool) $this->option('force');
        $retryFailed = (bool) $this->option('retry-failed');
        $sleepMs = (int) $this->option('sleep');

        $query = RentalBikeShop::query()
            ->whereNotNull('address')
            ->where('address', '!=', '');

        if (! $force) {
            $query->where(function ($q) {
                $q->whereNull('latitude')->orWhereNull('longitude');
            });
        }
        if (! $retryFailed) {
            $query->whereNull('geocode_failed_at');
        }

        $shops = $query->orderBy('id')->limit($limit)->get();

        if ($shops->isEmpty()) {
            $this->info('対象となる店舗はありません。');

            return self::SUCCESS;
        }

        $this->info("{$shops->count()}件の店舗を座標変換します（国土地理院）...");

        $ok = 0;
        $failed = 0;
        $outOfRange = 0;
        $addressIncomplete = 0;

        foreach ($shops as $shop) {
            $prefecture = (string) ($shop->prefecture ?? '');
            $city = (string) ($shop->city ?? '');
            $address = (string) ($shop->address ?? '');

            // prefecture / city が空の行は GSI へ問い合わせずスキップ（失敗記録もしない）。
            // 失敗記録すると、後から city が補完されても --retry-failed 無しでは永久に再試行されない。
            if (trim($prefecture) === '' || trim($city) === '') {
                $addressIncomplete++;

                continue;
            }

            $result = $geocoder->geocode($prefecture, $city, $address);
            $lat = $result['lat'] ?? null;
            $lng = $result['lng'] ?? null;

            $success = false;
            if ($lat !== null && $lng !== null) {
                if ($lat < self::LAT_MIN || $lat > self::LAT_MAX || $lng < self::LNG_MIN || $lng > self::LNG_MAX) {
                    $outOfRange++;
                } else {
                    $success = true;
                }
            }

            if ($success) {
                $shop->latitude = $lat;
                $shop->longitude = $lng;
                $shop->geocode_failed_at = null;
                $shop->saveQuietly();
                $ok++;
            } else {
                if ($lat === null || $lng === null) {
                    $failed++;
                }
                $shop->geocode_failed_at = now();
                $shop->saveQuietly();
            }

            $this->pause($sleepMs);
        }

        $this->newLine();
        $this->info(sprintf(
            '完了！ 対象 %d件 / 成功 %d件 / 失敗 %d件 / 範囲外 %d件 / 住所情報不足スキップ %d件',
            $shops->count(),
            $ok,
            $failed,
            $outOfRange,
            $addressIncomplete,
        ));

        return self::SUCCESS;
    }

    /** GSI は公共APIのため1件ごとに待機する（parking:geocode と同作法）。 */
    private function pause(int $ms): void
    {
        if ($ms > 0) {
            usleep($ms * 1000);
        }
    }
}
