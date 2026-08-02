<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\RentalGarage;
use App\Support\AddressNormalizer;
use App\Support\JapanCityPrefecture;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * rental_garages のジオコーディング（精度優先）。
 *
 * ★重要（実測に基づく設計）:
 *   共有の GsiGeocodingService は geocode(prefecture, city, address) が pref+city を必ず前置するが、
 *   本テーブルの address は既に「愛知県名古屋市…」のフル住所を保持しているため二重前置になり、
 *   GSI が「愛知県名古屋市」等の代表点（市役所）に丸まってしまう（多数の重複座標の原因）。
 *   → 本コマンドでは GSI エンドポイントを直接叩き、フル住所をそのまま渡す。加えて title を見て
 *     「市区町村どまりの代表点」を弾く。GsiGeocodingService は他機能が使うため変更しない。
 */
final class GeocodeRentalGarages extends Command
{
    protected $signature = 'rental_garage:geocode
        {--retry-failed : geocode_status=failed も対象に含める}
        {--retry-approximate : geocode_status=approximate も対象に含める}
        {--force : 全レコードを対象にし、座標を破棄して引き直す}
        {--limit= : 処理件数上限}
        {--sleep=1000 : 1件ごとの待機ミリ秒（既定1秒）}';

    protected $description = 'rental_garages の住所をジオコーディングし座標を埋める（GSI→Nominatim→切り詰めの順・代表点を弾く）';

    private const GSI_ENDPOINT = 'https://msearch.gsi.go.jp/address-search/AddressSearch';
    private const NOMINATIM_ENDPOINT = 'https://nominatim.openstreetmap.org/search';
    private const GSI_USER_AGENT = 'MotoHubBot/1.0 (+https://motohub.jp/)';
    private const NOMINATIM_USER_AGENT = 'MotoHub/1.0 (https://motohub.jp; rental garage map)';

    // 日本の緯度経度おおよその範囲（範囲外は誤ジオコーディングとして out_of_range 扱い）。
    private const JP_LAT_MIN = 20.0;
    private const JP_LAT_MAX = 46.0;
    private const JP_LNG_MIN = 122.0;
    private const JP_LNG_MAX = 154.0;

    public function handle(): int
    {
        $force = (bool) $this->option('force');
        $sleepMs = (int) $this->option('sleep');
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;

        $query = RentalGarage::query()->orderBy('id');
        if (! $force) {
            $statuses = ['pending'];
            if ($this->option('retry-failed')) {
                $statuses[] = 'failed';
            }
            if ($this->option('retry-approximate')) {
                $statuses[] = 'approximate';
            }
            $query->whereIn('geocode_status', $statuses);
        }
        if ($limit !== null) {
            $query->limit($limit);
        }
        $records = $query->get();

        $totalTargets = $records->count();
        if ($totalTargets === 0) {
            $this->info('対象レコードなし。');

            return self::SUCCESS;
        }

        $this->info("ジオコーディング対象: {$totalTargets} 件".($force ? '（--force: 全件引き直し）' : ''));

        $ok = 0;
        $approximate = 0;
        $failed = 0;
        $outOfRange = 0;
        $processed = 0;

        foreach ($records as $garage) {
            $pref = trim((string) ($garage->prefecture ?? ''));
            $addr = trim((string) ($garage->address ?? ''));

            // address が47都道府県のいずれかで始まっていない場合に限り prefecture を前置。
            // 既に都道府県で始まっていれば絶対に前置しない（pref+city 二重前置の再発防止）。
            $full = ($pref !== '' && $addr !== '' && ! JapanCityPrefecture::startsWithPrefecture($addr))
                ? $pref.$addr
                : $addr;

            $lat = null;
            $lng = null;
            $status = null;

            if ($full !== '') {
                // 段1: GSI にフル住所。番地レベル（代表点でない）title が返れば ok。
                $g = $this->gsiQuery($full);
                if ($g !== null && ! $this->isRepresentativeTitle($g['title'])) {
                    $lat = $g['lat'];
                    $lng = $g['lng'];
                    $status = 'ok';
                }

                // 段2: GSI が代表点/失敗なら Nominatim にフル住所。番地レベルなら ok。
                if ($status === null) {
                    $n = $this->nominatimQuery($full);
                    usleep(1_100_000); // Nominatim: 1秒1リクエスト厳守
                    if ($n !== null) {
                        $lat = $n['lat'];
                        $lng = $n['lng'];
                        $status = 'ok';
                    }
                }
            }

            // 段3: それでもだめなら市区町村まで切り詰めて GSI。取れたら座標は入れるが approximate。
            if ($status === null && $pref !== '' && $garage->city) {
                $g3 = $this->gsiQuery($pref.(string) $garage->city);
                if ($g3 !== null) {
                    $lat = $g3['lat'];
                    $lng = $g3['lng'];
                    $status = 'approximate';
                }
            }

            // 範囲チェック: 日本の外に落ちたら座標は入れず out_of_range。
            if ($status !== null && $this->outOfRange($lat, $lng)) {
                $status = 'out_of_range';
                $lat = null;
                $lng = null;
            }

            // 段4: 全部だめなら failed。
            if ($status === null) {
                $status = 'failed';
            }

            // 保存（--force/再試行で毎回引き直すため、失敗時は座標を破棄＝null で上書き）。
            $garage->latitude = $lat;
            $garage->longitude = $lng;
            $garage->geocode_status = $status;
            $garage->save();

            match ($status) {
                'ok' => $ok++,
                'approximate' => $approximate++,
                'out_of_range' => $outOfRange++,
                default => $failed++,
            };

            $processed++;
            if ($processed % 50 === 0) {
                $this->line("  {$processed}/{$totalTargets} 件（ok {$ok} / approx {$approximate} / failed {$failed} / range外 {$outOfRange}）");
            }

            if ($sleepMs > 0) {
                usleep($sleepMs * 1000);
            }
        }

        // 事後: 重複座標（2件以上が同一 lat/lng）を検査。住所が混在するグループのみ代表点として降格。
        ['demoted' => $demoted, 'preservedGroups' => $preservedGroups] = $this->demoteDuplicateCoordinates();

        $this->newLine();
        $this->info('===== 完了 =====');
        $this->info("処理: {$processed} 件 → ok {$ok} / approximate {$approximate} / failed {$failed} / out_of_range {$outOfRange}");
        $this->info("重複座標により approximate に降格: {$demoted} 件");
        $this->info("同一敷地（住所一致）とみなし降格を見送ったグループ: {$preservedGroups} 件");

        // テーブル全体の最終内訳。
        $breakdown = RentalGarage::query()
            ->selectRaw('geocode_status, COUNT(*) as c')
            ->groupBy('geocode_status')
            ->pluck('c', 'geocode_status');
        $this->info(sprintf(
            'テーブル全体: ok %d / approximate %d / failed %d / out_of_range %d / pending %d',
            (int) ($breakdown['ok'] ?? 0),
            (int) ($breakdown['approximate'] ?? 0),
            (int) ($breakdown['failed'] ?? 0),
            (int) ($breakdown['out_of_range'] ?? 0),
            (int) ($breakdown['pending'] ?? 0),
        ));

        return self::SUCCESS;
    }

    /**
     * GSI AddressSearch にクエリし、先頭候補の title と座標を返す。見つからなければ null。
     *
     * @return array{lat: float, lng: float, title: string}|null
     */
    private function gsiQuery(string $q): ?array
    {
        try {
            $response = Http::withHeaders(['User-Agent' => self::GSI_USER_AGENT])
                ->timeout(5)
                ->get(self::GSI_ENDPOINT, ['q' => $q]);

            if (! $response->successful()) {
                return null;
            }

            $features = $response->json();
            if (! is_array($features) || count($features) === 0) {
                return null;
            }

            $coords = $features[0]['geometry']['coordinates'] ?? null;
            if (! is_array($coords) || count($coords) < 2) {
                return null;
            }

            return [
                'lat' => (float) $coords[1], // GeoJSON は [経度, 緯度]
                'lng' => (float) $coords[0],
                'title' => (string) ($features[0]['properties']['title'] ?? ''),
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Nominatim にクエリ（GeocodeParkings と同流儀）。番地レベル（house_number か road あり）のときだけ座標を返す。
     *
     * @return array{lat: float, lng: float}|null
     */
    private function nominatimQuery(string $q): ?array
    {
        try {
            $response = Http::withHeaders(['User-Agent' => self::NOMINATIM_USER_AGENT])
                ->timeout(10)
                ->get(self::NOMINATIM_ENDPOINT, [
                    'format' => 'json',
                    'q' => $q,
                    'countrycodes' => 'jp',
                    'limit' => 1,
                    'addressdetails' => 1,
                ]);

            if (! $response->successful() || empty($response->json())) {
                return null;
            }

            $result = $response->json()[0];
            $detail = $result['address'] ?? [];

            // 番地レベル判定: house_number か road（街路）があれば採用。市区町村どまりは不採用。
            if (! isset($detail['house_number']) && ! isset($detail['road'])) {
                return null;
            }

            return [
                'lat' => (float) $result['lat'],
                'lng' => (float) $result['lon'],
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * GSI の title が「市区町村どまりの代表点」か。番地・丁目・号・数字を一切含まなければ代表点とみなす。
     * （実測: 代表点 title は「愛知県名古屋市」「東京都足立区」等で数字を含まない。番地レベルは必ず数字/丁目/番地/号を含む。）
     */
    private function isRepresentativeTitle(string $title): bool
    {
        if (preg_match('/[0-9０-９]/u', $title)) {
            return false;
        }
        if (preg_match('/(丁目|番地|番|号)/u', $title)) {
            return false;
        }

        return true;
    }

    private function outOfRange(float $lat, float $lng): bool
    {
        return $lat < self::JP_LAT_MIN || $lat > self::JP_LAT_MAX
            || $lng < self::JP_LNG_MIN || $lng > self::JP_LNG_MAX;
    }

    /**
     * 同一 (latitude, longitude) を2件以上持つ座標グループを検査する。
     * グループ内の正規化後 address が全件同一なら「同一敷地の別棟」とみなし降格しない。
     * 1件でも住所が異なれば代表点とみなし、そのグループの 'ok' 行を 'approximate' に降格する。
     *
     * @return array{demoted: int, preservedGroups: int}
     */
    private function demoteDuplicateCoordinates(): array
    {
        $dupes = RentalGarage::query()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->selectRaw('latitude, longitude, COUNT(*) as c')
            ->groupBy('latitude', 'longitude')
            ->havingRaw('COUNT(*) >= 2')
            ->get();

        $demoted = 0;
        $preservedGroups = 0;

        foreach ($dupes as $d) {
            $rows = RentalGarage::query()
                ->where('latitude', $d->latitude)
                ->where('longitude', $d->longitude)
                ->get(['id', 'address']);

            // 正規化後の住所が全件一致か（前後空白を除いて厳密一致）。
            $normalized = $rows
                ->map(fn ($r) => trim(AddressNormalizer::normalize((string) $r->address)))
                ->unique();

            if ($normalized->count() === 1) {
                // 全件同一住所＝同一敷地の別棟（松戸秋山 1〜3号店等）。降格しない。
                $preservedGroups++;

                continue;
            }

            // 住所が混在＝代表点（市役所等）。ok を approximate に降格。
            $demoted += RentalGarage::query()
                ->where('latitude', $d->latitude)
                ->where('longitude', $d->longitude)
                ->where('geocode_status', 'ok')
                ->update(['geocode_status' => 'approximate']);
        }

        return ['demoted' => $demoted, 'preservedGroups' => $preservedGroups];
    }
}
