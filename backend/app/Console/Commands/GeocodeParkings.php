<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BikeParking;
use App\Services\GsiGeocodingService;
use Illuminate\Console\Command;

/**
 * バイク駐車場（bike_parkings）の住所から緯度経度を取得する。
 *
 * ★forward ジオコーディングは国土地理院(GSI)のみを使う（GsiGeocodingService 経由）。
 *   理由: Nominatim/OSM はバルクジオコーディングを利用規約で禁止、Google 系は ToS 違反・データ保持制限。
 *   旧実装は Nominatim を直接叩いていたため撤去し、住所正規化・市区町村補正・県代表点ガードを
 *   共有する GsiGeocodingService に統一した（shops:geocode / schools:geocode と同作法）。
 *
 * - 失敗は geocode_failed_at に記録し、既定では再試行しない（poi:geocode と同方式）。
 *   --retry-failed 指定時のみ失敗済みも対象に含める。
 * - GSI は公共APIのため1件ごとに待機する（--sleep 既定1000ms）。短縮しない。
 * - 保存するのは latitude / longitude / geocode_failed_at のみ（saveQuietly）。
 * - null / 日本範囲外は「未取得のまま」にし、もっともらしく間違った粗い座標は保存しない。
 */
class GeocodeParkings extends Command
{
    protected $signature = 'parking:geocode
        {--limit=5000 : 1回の実行で処理する最大件数}
        {--retry-failed : geocode_failed_at が記録済みの失敗行も対象に含める}
        {--force : 座標取得済みのデータも再取得する}
        {--sleep=1000 : 1件ごとの待機ミリ秒（GSIは公共API）}';

    protected $description = 'バイク駐車場の住所から緯度経度を取得する（国土地理院ジオコーディング）';

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

        $query = BikeParking::query()
            ->whereNotNull('address')
            ->where('address', '!=', '');

        // 座標が欠けている行だけを対象（--force で再取得）。
        if (! $force) {
            $query->where(function ($q) {
                $q->whereNull('latitude')->orWhereNull('longitude');
            });
        }

        // 既定では失敗記録済み(geocode_failed_at IS NOT NULL)を除外し、毎回同じ失敗行を叩かない。
        // --retry-failed のときだけ失敗行も対象に含める。
        if (! $retryFailed) {
            $query->whereNull('geocode_failed_at');
        }

        // orderBy('id') で毎回同じ先頭を舐めず、処理を前へ進める。
        $parkings = $query->orderBy('id')->limit($limit)->get();

        if ($parkings->isEmpty()) {
            $this->info('対象となる駐車場はありません。');

            return self::SUCCESS;
        }

        $this->info("{$parkings->count()}件の駐車場を座標変換します（国土地理院）...");

        $ok = 0;
        $failed = 0;
        $outOfRange = 0;
        $addressIncomplete = 0;

        foreach ($parkings as $parking) {
            $prefecture = (string) ($parking->prefecture ?? '');
            $city = (string) ($parking->city ?? '');
            $address = (string) ($parking->address ?? '');

            // prefecture / city が空（NULL・空文字・空白のみ）の行は GSI へ問い合わせず、
            // geocode_failed_at も記録せずスキップする（shops:geocode と同作法）。
            // 失敗として記録すると、後から city が補完されても --retry-failed 無しでは永久に再試行されず
            // 取りこぼしが固定化するため。HTTPは発生しないので GSI 負荷は増えない（件数だけ可視化）。
            if (trim($prefecture) === '' || trim($city) === '') {
                $addressIncomplete++;

                continue;
            }

            // GsiGeocodingService が内部で prefecture + city + address を連結し、
            // 住所正規化・市区町村補正・county/区の重複除去・県代表点ガードを適用する。
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
                // 成功: 座標を保存し、過去の失敗記録はクリア。saveQuietly で余計なイベントを起こさない。
                $parking->latitude = $lat;
                $parking->longitude = $lng;
                $parking->geocode_failed_at = null;
                $parking->saveQuietly();
                $ok++;
            } else {
                // 失敗/範囲外: geocode_failed_at を刻んで既定では次回以降の対象から外す。
                if ($lat === null || $lng === null) {
                    $failed++;
                }
                $parking->geocode_failed_at = now();
                $parking->saveQuietly();
            }

            $this->pause($sleepMs);
        }

        $this->newLine();
        $this->info(sprintf(
            '完了！ 対象 %d件 / 成功 %d件 / 失敗 %d件 / 範囲外 %d件 / 住所情報不足スキップ %d件',
            $parkings->count(),
            $ok,
            $failed,
            $outOfRange,
            $addressIncomplete,
        ));

        return self::SUCCESS;
    }

    /** GSI は公共APIのため1件ごとに待機する（shops:geocode / poi:geocode と同作法）。 */
    private function pause(int $ms): void
    {
        if ($ms > 0) {
            usleep($ms * 1000);
        }
    }
}
