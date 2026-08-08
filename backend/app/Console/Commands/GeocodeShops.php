<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Shop;
use App\Services\GsiGeocodingService;
use Illuminate\Console\Command;

/**
 * ショップ（shops）の住所から緯度経度を取得する（国土地理院 GSI）。
 *
 * - 自前 Http::get はやめ、GsiGeocodingService::geocode() に委譲する（県代表点への誤ピンを弾く品質ガード付き）。
 * - shops.address は「東京都 港区 芝3-16-1」のように都道府県と市区町村の間に空白を持つ行が多い。
 *   旧実装は空白で分割して先頭要素だけ（＝「東京都」）を送っていたため、都庁など自治体代表点に固まっていた。
 *   → 空白は「切り捨てる区切り」ではなく「除去して連結するもの」として扱い、
 *     address から prefecture / city を先頭一致で剥がして $street を作る（stripCity）。
 * - 保存するのは latitude / longitude の2カラムのみ。name / address / city / prefecture 等は絶対に更新しない。
 *   ShopObserver が address から city を再計算するため、座標バッチでの巻き添え更新を避けて saveQuietly() で保存する。
 * - null / 範囲外は「未取得のまま」にする。もっともらしく間違った粗い座標を保存しない。
 * - --dry-run では API は呼ぶが DB 書き込みは一切しない。
 *
 * ※ 既定（オプション無し）は「座標が欠けている行だけを対象に DB へ書き込む」。
 *   bootstrap/app.php で毎週火曜03:30に引数なしで実行されるため、この既定挙動を変えない。
 */
class GeocodeShops extends Command
{
    protected $signature = 'shops:geocode
        {--dry-run : DB へ書き込まず、取得結果のみ表示}
        {--pref= : prefecture で対象を絞る}
        {--limit= : 対象件数の上限}
        {--force : 座標が入っている行も再取得する}
        {--spaced-only : 住所に空白を含む行だけを対象にする}
        {--sleep=1000 : 1件ごとの待機ミリ秒（GSIは公共API）}';

    protected $description = 'ショップの住所から緯度経度を取得する（国土地理院ジオコーディング）';

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
        $spacedOnly = (bool) $this->option('spaced-only');
        $sleepMs = (int) $this->option('sleep');

        $query = Shop::query()
            ->whereNotNull('address')
            ->where('address', '!=', '');

        // 座標が欠けている行だけを対象（--force で再取得）。OR を明示的に括る。
        if (! $force) {
            $query->where(function ($q) {
                $q->whereNull('latitude')->orWhereNull('longitude');
            });
        }

        // 住所に半角/全角の空白を含む行だけに限定（今回の修正対象を絞り込むため）。
        if ($spacedOnly) {
            $query->where(function ($q) {
                $q->where('address', 'like', '% %')
                    ->orWhere('address', 'like', '%　%');
            });
        }

        if ($pref !== null && $pref !== '') {
            $query->where('prefecture', $pref);
        }

        $query->orderBy('id');

        if ($limit !== null) {
            $query->limit($limit);
        }

        $shops = $query->get();

        if ($shops->isEmpty()) {
            $this->info('対象のショップはありません。');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->info('[DRY RUN] DB へは書き込みません。');
        }
        $this->info(count($shops).'件を処理します。');

        $ok = 0;
        $failed = 0;
        $outOfRange = 0;
        $cityMissing = 0;
        $cityMissingIds = [];

        foreach ($shops as $shop) {
            $prefecture = (string) $shop->prefecture;
            $city = (string) $shop->city;

            // city が空だと GsiGeocodingService の「title に city が含まれること」照合が働かず
            // 粗い座標（県/市代表点）を弾けないため、処理せずスキップする。
            if (trim($city) === '') {
                $cityMissing++;
                if (count($cityMissingIds) < 20) {
                    $cityMissingIds[] = $shop->id;
                }

                continue;
            }

            // stripCity には「元の city（郡付き）」を渡す。address には郡付きで入っているため。
            $street = $this->buildStreet($prefecture, $city, (string) $shop->address);

            // GSI 照合用の city は郡を落とした値（title は郡抜きで返るため）。
            $geocodeCity = $this->geocodeCity($city);

            // 実際に GSI へ渡る文字列（prefecture + city + street を連結）。
            $sent = $prefecture.$geocodeCity.$street;

            $result = $geocoder->geocode($prefecture, $geocodeCity, $street);

            if ($result === null) {
                $failed++;
                $this->line("{$shop->name} / 送信=「{$sent}」 / failed");
                $this->pause($sleepMs);

                continue;
            }

            $lat = (float) $result['lat'];
            $lng = (float) $result['lng'];

            if ($lat < self::LAT_MIN || $lat > self::LAT_MAX || $lng < self::LNG_MIN || $lng > self::LNG_MAX) {
                $outOfRange++;
                $this->line("{$shop->name} / 送信=「{$sent}」 / out_of_range({$lat},{$lng})");
                $this->pause($sleepMs);

                continue;
            }

            $ok++;
            $this->line("{$shop->name} / 送信=「{$sent}」 / ok({$lat},{$lng})");

            if (! $dryRun) {
                // 座標2カラムのみ更新。ShopObserver の巻き添え（address→city 再計算）を避けて saveQuietly。
                $shop->latitude = $lat;
                $shop->longitude = $lng;
                $shop->saveQuietly();
            }

            $this->pause($sleepMs);
        }

        $this->newLine();
        $this->info(sprintf(
            '対象: %d件 / 成功: %d件 / 失敗: %d件 / city欠落スキップ: %d件 / 範囲外: %d件',
            count($shops),
            $ok,
            $failed,
            $cityMissing,
            $outOfRange,
        ));

        if ($cityMissingIds !== []) {
            $this->line('city欠落の id（最大20件）: '.implode(', ', $cityMissingIds));
        }

        if ($dryRun) {
            $this->newLine();
            $this->warn('DRY RUN のため実際の更新は行っていません。');
        }

        return self::SUCCESS;
    }

    /**
     * address から GSI へ渡す street を作る。
     *
     * a. 全角英数字・全角スペースを半角化
     * b. 半角/全角の空白をすべて除去（分割して先頭を取るのではなく、除去して連結）
     * c. 先頭が prefecture で始まっていれば剥がす
     * d. 続いて先頭が city（政令市の区を含む）で始まっていれば剥がす
     *
     * 例: prefecture=東京都 / city=港区 / address=「東京都 港区 芝3-16-1」→ 芝3-16-1
     */
    private function buildStreet(string $prefecture, string $city, string $address): string
    {
        // a. 全角英数字（a）と全角スペース（s）を半角化
        $street = mb_convert_kana($address, 'as');
        // b. 半角・全角の空白をすべて除去
        $street = preg_replace('/[\s\x{3000}]+/u', '', $street) ?? $street;
        $street = trim($street);

        // c. 先頭の prefecture を剥がす
        if ($prefecture !== '' && str_starts_with($street, $prefecture)) {
            $street = mb_substr($street, mb_strlen($prefecture));
        }

        // d. 先頭の city を剥がす（政令市の区にも対応）
        $street = $this->stripCity($city, $street);

        return trim($street);
    }

    /**
     * street 先頭の city を剥がす。
     *
     * city 全体（例: 横浜市都筑区）で始まればそれを剥がす。
     * そうでなく、政令市の区名末尾（例: 都筑区）だけで始まる場合はその区名を剥がす。
     * GsiGeocodingService の A-1（address が city / 区名で始まる場合の重複除去）と同じ考え方。
     */
    private function stripCity(string $city, string $street): string
    {
        if ($city !== '' && str_starts_with($street, $city)) {
            return mb_substr($street, mb_strlen($city));
        }

        if (preg_match('/市(.+?区)$/u', $city, $m) === 1) {
            $cityTail = $m[1];
            if (str_starts_with($street, $cityTail)) {
                return mb_substr($street, mb_strlen($cityTail));
            }
        }

        return $street;
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
