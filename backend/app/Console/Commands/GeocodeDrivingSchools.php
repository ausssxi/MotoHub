<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\DrivingSchool;
use App\Services\GsiGeocodingService;
use Illuminate\Console\Command;

/**
 * 教習所（driving_schools）の住所から緯度経度を取得する（国土地理院 GSI）。
 *
 * - address が非NULL・非空の行のみ対象。通常は latitude が NULL の行だけ。--force で再取得。
 * - GsiGeocodingService::geocode() は内部で prefecture.city.address を区切りなしで連結するため、
 *   DB の address（city を含む「横浜市鶴見区上末吉2-7-1」等）をそのまま渡すと city が二重になる。
 *   → 渡す前に address から city 部分を切り落として $street を作る（下記 stripCity）。
 * - 保存するのは latitude / longitude / geocoded_at / geocode_status の4カラムのみ。
 *   address / city / name / prefecture 等の既存カラムは絶対に更新しない。削除もしない。
 * - --dry-run では API は呼ぶが DB 書き込みは一切しない。
 */
class GeocodeDrivingSchools extends Command
{
    protected $signature = 'schools:geocode
        {--dry-run : DB へ書き込まず、取得結果のみ表示}
        {--pref= : prefecture_slug で対象を絞る}
        {--limit= : 対象件数の上限}
        {--force : latitude が入っている行も再取得する}
        {--sleep=1000 : 1件ごとの待機ミリ秒（GSIは公共API）}';

    protected $description = '教習所の住所から緯度経度を取得する（国土地理院ジオコーディング）';

    /** 日本の緯度の妥当範囲。 */
    private const LAT_MIN = 20.0;

    private const LAT_MAX = 46.0;

    /** 日本の経度の妥当範囲。 */
    private const LNG_MIN = 122.0;

    private const LNG_MAX = 154.0;

    public function handle(GsiGeocodingService $geocoder): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $pref = $this->option('pref');
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;
        $force = (bool) $this->option('force');
        $sleepMs = (int) $this->option('sleep');

        $query = DrivingSchool::query()
            ->whereNotNull('address')
            ->where('address', '!=', '');

        if (! $force) {
            $query->whereNull('latitude');
        }

        if ($pref !== null && $pref !== '') {
            $query->where('prefecture_slug', $pref);
        }

        $query->orderBy('id');

        if ($limit !== null) {
            $query->limit($limit);
        }

        $schools = $query->get();

        if ($schools->isEmpty()) {
            $this->info('対象の教習所はありません。');

            return self::SUCCESS;
        }

        $this->info(($dryRun ? '[dry-run] ' : '').count($schools).'件を処理します。');

        $ok = 0;
        $failed = 0;
        $outOfRange = 0;
        $skipped = 0;

        foreach ($schools as $school) {
            $street = $this->stripCity((string) $school->city, (string) $school->address);

            if ($street === '') {
                $skipped++;
                $this->line("skipped(street empty): {$school->name}");

                continue;
            }

            // GsiGeocodingService が内部で prefecture.city.street を連結する。
            $sent = (string) $school->prefecture.$this->geocodeCity((string) $school->city).$street;

            $result = $geocoder->geocode((string) $school->prefecture, $this->geocodeCity((string) $school->city), $street);

            if ($result === null) {
                $failed++;
                $this->line("{$school->name} / 送信=「{$sent}」 / failed");

                if (! $dryRun) {
                    $school->geocode_status = 'failed';
                    $school->geocoded_at = now();
                    $school->save();
                }

                $this->pause($sleepMs);

                continue;
            }

            $lat = (float) $result['lat'];
            $lng = (float) $result['lng'];

            if ($lat < self::LAT_MIN || $lat > self::LAT_MAX || $lng < self::LNG_MIN || $lng > self::LNG_MAX) {
                $outOfRange++;
                $this->line("{$school->name} / 送信=「{$sent}」 / out_of_range({$lat},{$lng})");

                if (! $dryRun) {
                    $school->geocode_status = 'out_of_range';
                    $school->geocoded_at = now();
                    $school->save();
                }

                $this->pause($sleepMs);

                continue;
            }

            $ok++;
            $this->line("{$school->name} / 送信=「{$sent}」 / ok({$lat},{$lng})");

            if (! $dryRun) {
                $school->latitude = $lat;
                $school->longitude = $lng;
                $school->geocode_status = 'ok';
                $school->geocoded_at = now();
                $school->save();
            }

            $this->pause($sleepMs);
        }

        $this->newLine();
        $this->info(sprintf(
            '%sok: %d件 / failed: %d件 / out_of_range: %d件 / skipped: %d件',
            $dryRun ? '[dry-run] ' : '',
            $ok,
            $failed,
            $outOfRange,
            $skipped,
        ));

        return self::SUCCESS;
    }

    /**
     * address から city 部分を切り落とし、番地以降（street）を返す。
     *
     * address 内に city が含まれていれば、その「最初の出現位置＋city長」までを切り落とす。
     * 含まれていなければ address をそのまま返す。
     * 例: city=横浜市鶴見区 / address=横浜市鶴見区上末吉2-7-1 → 上末吉2-7-1
     */
    private function stripCity(string $city, string $address): string
    {
        $address = trim($address);

        if ($city !== '') {
            $pos = mb_strpos($address, $city);
            if ($pos !== false) {
                return trim(mb_substr($address, $pos + mb_strlen($city)));
            }
        }

        return $address;
    }

    /**
     * GSI へ照合させる city 値を返す。
     *
     * 国土地理院APIは町村の住所で郡を省いた title を返すため、city=「足柄上郡開成町」だと
     * GsiGeocodingService の「city が title に含まれること」照合に失敗する。
     * 郡を含み末尾が町/村の場合のみ郡を除いた値（開成町）を照合用に使う。座標は同一。
     * 「郡山市」のように郡を含むが町村で終わらない市名は誤除去しない（末尾町/村限定）。
     * stripCity（address からの city 除去）は従来どおり元の city を使い、これは照合用のみ。
     */
    private function geocodeCity(string $city): string
    {
        if (preg_match('/^.+?郡(.+[町村])$/u', $city, $m) === 1) {
            return $m[1];
        }

        return $city;
    }

    /** GSI は公共APIのため1件ごとに待機する。 */
    private function pause(int $ms): void
    {
        if ($ms > 0) {
            usleep($ms * 1000);
        }
    }
}
