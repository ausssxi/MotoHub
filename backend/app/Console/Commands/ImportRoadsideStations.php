<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\RoadsideStation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * 道の駅データを it-social.net API / CSV から取り込む（Wikipedia由来のデータを含む）。
 *
 * ★台帳の名称ポリシー: 道の駅の「名称(name)」は国土交通省の公式一覧を正とし、
 *   本コマンドは名称の正としない。よって既存行の name は上書きせず、name は新規作成時のみ設定する。
 *   （summary / image_url / has_* 系は本コマンドが更新するため、公式一覧との整合を崩しうる。
 *    意図した実行のみ許すため --allow-overwrite を必須とする。）
 */
final class ImportRoadsideStations extends Command
{
    protected $signature = 'roadside-stations:import
                            {--source=api : データソース (api|csv)}
                            {--file= : CSVファイルのパス (デフォルト: storage/app/roadside_stations.csv)}
                            {--pref= : 都道府県コード (01-47) で絞り込み}
                            {--allow-overwrite : 台帳の上書き更新を許可（未指定は警告して中止）}';

    protected $description = '道の駅データをインポート（API個別取得 or CSV一括読み込み）';

    private const API_BASE = 'https://it-social.net/roadside_station/json/';
    private const USER_AGENT = 'MotoHubBot/1.0 (+https://motohub.jp/)';
    private const SLEEP_SEC = 3;
    private const RETRY_WAIT_SEC = 30;
    private const MAX_RETRIES = 3;
    private const CONSECUTIVE_404_LIMIT = 10;

    // ── CSV用ヘッダーマッピング ──────────────────────────
    private const HEADER_MAP = [
        'iclt:ID'              => 'id',
        'ID'                   => 'id',
        'iclt:名称'            => 'name',
        '名称'                 => 'name',
        'iclt:通称'            => 'nickname',
        '通称'                 => 'nickname',
        'iclt:住所'            => 'address',
        '住所'                 => 'address',
        'geo:lat'              => 'lat',
        '緯度'                 => 'lat',
        'geo:long'             => 'lng',
        '経度'                 => 'lng',
        'iclt:都道府県'        => 'prefecture',
        '都道府県'             => 'prefecture',
        'iclt:都道府県コード'  => 'pref_code',
        '都道府県コード'       => 'pref_code',
        'iclt:市区町村'        => 'city',
        '市区町村'             => 'city',
        'rsst:登録路線'        => 'route',
        '登録路線'             => 'route',
        'local:指定年'         => 'designated_year',
        '指定年'               => 'designated_year',
        'local:Wikipedia'      => 'wikipedia',
        'Wikipedia'            => 'wikipedia',
        'iclt:Webサイト'       => 'website',
        'Webサイト'            => 'website',
        'local:画像'           => 'image',
        '画像'                 => 'image',
        'iclt:概要'            => 'summary',
        '概要'                 => 'summary',
        'iclt:状態'            => 'status',
        '状態'                 => 'status',
        'rsst:ATM'             => 'atm',
        'ATM'                  => 'atm',
        'rsst:レストラン'      => 'restaurant',
        'レストラン'           => 'restaurant',
        'rsst:軽食喫茶'        => 'cafe',
        '軽食喫茶'             => 'cafe',
        'rsst:温泉施設'        => 'onsen',
        '温泉施設'             => 'onsen',
        'rsst:キャンプ場等'    => 'camp',
        'キャンプ場等'         => 'camp',
        'rsst:展望台'          => 'observatory',
        '展望台'               => 'observatory',
        'rsst:ガソリンスタンド' => 'gas_station',
        'ガソリンスタンド'     => 'gas_station',
        'rsst:EV充電施設'      => 'ev_charging',
        'EV充電施設'           => 'ev_charging',
        'rsst:無線LAN'         => 'wifi',
        '無線LAN'              => 'wifi',
        'rsst:シャワー'        => 'shower',
        'シャワー'             => 'shower',
        'rsst:ショップ'        => 'shop',
        'ショップ'             => 'shop',
    ];

    public function handle(): int
    {
        if (! $this->option('allow-overwrite')) {
            $this->warn('このコマンドは Wikipedia 由来のデータで台帳を更新します。名称は上書きしませんが、summary・image_url・has_* 系は上書きされます。公式一覧との整合を崩す可能性があるため、意図して実行する場合のみ --allow-overwrite を付けてください。');

            return self::FAILURE;
        }

        return match ($this->option('source')) {
            'csv'   => $this->handleCsv(),
            'api'   => $this->handleApi(),
            default => $this->handleApi(),
        };
    }

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    //  API モード
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

    private function handleApi(): int
    {
        $prefFilter = $this->option('pref')
            ? str_pad($this->option('pref'), 2, '0', STR_PAD_LEFT)
            : null;

        $prefStart = $prefFilter ? (int) $prefFilter : 1;
        $prefEnd = $prefFilter ? (int) $prefFilter : 47;

        $upserted = 0;
        $skipped = 0;

        for ($pref = $prefStart; $pref <= $prefEnd; $pref++) {
            $prefCode = str_pad((string) $pref, 2, '0', STR_PAD_LEFT);
            $this->info("── 都道府県: {$prefCode} ──");

            $consecutive404 = 0;

            for ($seq = 1; $seq <= 999; $seq++) {
                $code = $prefCode . str_pad((string) $seq, 3, '0', STR_PAD_LEFT);

                $data = $this->fetchStation($code);

                if ($data === null) {
                    $consecutive404++;
                    if ($consecutive404 >= self::CONSECUTIVE_404_LIMIT) {
                        $this->line("  {$code}: {$consecutive404}件連続404 → 次の都道府県へ");
                        break;
                    }
                    continue;
                }

                $consecutive404 = 0;

                $status = (string) ($data['状態'] ?? '');
                $name = (string) ($data['名称'] ?? '');

                if ($status !== '' && $status !== '営業中') {
                    $this->line("  {$code}: {$name}（{$status}）→ スキップ");
                    $skipped++;
                    continue;
                }

                $this->upsertFromJson($code, $data);
                $upserted++;
                $this->line("  {$code}: {$name} ✓");

                if ($upserted % 100 === 0) {
                    $this->info("  ... {$upserted}件処理済み");
                }
            }
        }

        $this->newLine();
        $this->info("完了: {$upserted}件 登録/更新, {$skipped}件 スキップ");
        $this->info("DB総数: " . RoadsideStation::count() . '件');

        return self::SUCCESS;
    }

    /**
     * 1件のJSONを取得。404ならnull、HTMLレスポンスなら30秒待ちリトライ。
     */
    private function fetchStation(string $code): ?array
    {
        $url = self::API_BASE . $code . '.json';

        for ($attempt = 1; $attempt <= self::MAX_RETRIES; $attempt++) {
            sleep(self::SLEEP_SEC);

            try {
                $response = Http::withHeaders(['User-Agent' => self::USER_AGENT])->timeout(15)->get($url);
            } catch (\Throwable $e) {
                $this->warn("  {$code}: 接続エラー ({$e->getMessage()}) → リトライ {$attempt}/" . self::MAX_RETRIES);
                sleep(self::RETRY_WAIT_SEC);
                continue;
            }

            if ($response->status() === 404) {
                return null;
            }

            $body = $response->body();

            // HTMLレスポンス検知 → レート制限の可能性
            if (str_starts_with(ltrim($body), '<!DOCTYPE') || str_starts_with(ltrim($body), '<html')) {
                $this->warn("  {$code}: HTMLレスポンス検知 → {$attempt}/" . self::MAX_RETRIES . " 30秒待機...");
                sleep(self::RETRY_WAIT_SEC);
                continue;
            }

            $json = $this->fixJson($body);
            $data = json_decode($json, true);

            if (! is_array($data)) {
                $this->warn("  {$code}: JSONパースエラー → スキップ");

                return null;
            }

            return $data;
        }

        $this->error("  {$code}: リトライ上限 → スキップ");

        return null;
    }

    /**
     * 壊れたJSONを修復: BOM除去、空値→0、カンマ補完
     */
    private function fixJson(string $raw): string
    {
        // BOM除去
        $json = ltrim($raw, "\xEF\xBB\xBF");

        // 空値 → 0  (例: "就労体験型受入道の駅": )
        $json = preg_replace('/:\s*\n/', ": 0\n", $json);
        $json = preg_replace('/:\s*$/', ': 0', $json);

        // 行末カンマ補完: 値の後に改行→次行が "キー": の場合にカンマ追加
        $json = preg_replace('/([0-9"\]}])\s*\n(\s*")/', "$1,\n$2", $json);

        return $json;
    }

    private function upsertFromJson(string $code, array $data): void
    {
        $str = fn (string $key): string => trim((string) ($data[$key] ?? ''));
        $flag = fn (string $key): bool => ((int) ($data[$key] ?? 0)) === 1;

        // APIは経度・緯度ラベルが逆（経度=lat値, 緯度=lng値）
        $lat = (float) ($data['経度'] ?? 0);
        $lng = (float) ($data['緯度'] ?? 0);

        $website = $str('Webサイト1') ?: $str('Webサイト2') ?: $str('Webサイト3') ?: $str('Webサイト4');

        // name は公式一覧を正とするため更新側から除外（新規作成時のみ設定）。
        $payload = [
            'nickname'        => $str('通称') ?: null,
            'address'         => $str('住所') ?: null,
            'latitude'        => $lat,
            'longitude'       => $lng,
            'prefecture'      => $str('都道府県') ?: null,
            'city'            => $str('市区町村') ?: null,
            'route'           => $str('登録路線') ?: null,
            'image_url'       => $str('画像') ?: null,
            'summary'         => $str('概要') ?: null,
            'website_url'     => $website ?: null,
            'wikipedia_url'   => $str('Wikipedia') ?: null,
            'has_atm'         => $flag('ATM'),
            'has_restaurant'  => $flag('レストラン') || $flag('軽食喫茶'),
            'has_onsen'       => $flag('温泉施設'),
            'has_ev_charging' => $flag('EV充電施設'),
            'has_wifi'        => $flag('無線LAN'),
            'has_shower'      => $flag('シャワー'),
            'has_camp'        => $flag('キャンプ場等'),
            'has_gas_station' => $flag('ガソリンスタンド'),
            'has_observatory' => $flag('展望台'),
            'has_shop'        => $flag('ショップ'),
            'designated_year' => ($y = (int) ($data['指定年'] ?? 0)) > 0 ? $y : null,
        ];

        $station = RoadsideStation::firstOrNew(['station_code' => $code]);
        if (! $station->exists) {
            $station->name = $str('名称'); // 新規作成時のみ。既存の name は触らない。
        }
        $station->fill($payload)->save();
    }

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    //  CSV モード
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

    private function handleCsv(): int
    {
        $file = $this->option('file')
            ?? storage_path('app/roadside_stations.csv');

        if (! file_exists($file)) {
            $this->error("ファイルが見つかりません: {$file}");
            $this->line('LinkData.org からCSVをダウンロードしてください:');
            $this->line('  http://linkdata.org/work/rdf1s2861i');

            return self::FAILURE;
        }

        $prefFilter = $this->option('pref')
            ? str_pad($this->option('pref'), 2, '0', STR_PAD_LEFT)
            : null;

        $handle = fopen($file, 'r');
        if (! $handle) {
            $this->error("ファイルを開けません: {$file}");

            return self::FAILURE;
        }

        // BOM除去 & ヘッダー行読み込み
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $headers = fgetcsv($handle);
        if (! $headers) {
            $this->error('ヘッダー行を読み込めません');
            fclose($handle);

            return self::FAILURE;
        }

        $colMap = $this->buildColumnMap($headers);

        if (! isset($colMap['id'], $colMap['name'])) {
            $this->error('必須カラム（ID, 名称）がヘッダーに見つかりません');
            $this->line('検出されたヘッダー: ' . implode(', ', $headers));
            fclose($handle);

            return self::FAILURE;
        }

        $upserted = 0;
        $skipped = 0;
        $lineNum = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $lineNum++;

            if (count($row) < 2) {
                $skipped++;
                continue;
            }

            $code = trim($row[$colMap['id']] ?? '');
            $status = isset($colMap['status']) ? trim($row[$colMap['status']] ?? '') : '';
            $name = trim($row[$colMap['name']] ?? '');

            if ($code === '' || ! preg_match('/^\d{5}$/', $code)) {
                $skipped++;
                continue;
            }

            if ($prefFilter && ! str_starts_with($code, $prefFilter)) {
                continue;
            }

            if ($status !== '' && $status !== '営業中') {
                $this->line("  {$code}: {$name}（{$status}）→ スキップ");
                $skipped++;
                continue;
            }

            $this->upsertFromCsvRow($code, $row, $colMap);
            $upserted++;

            if ($upserted % 100 === 0) {
                $this->info("  ... {$upserted}件処理済み");
            }
        }

        fclose($handle);

        $this->newLine();
        $this->info("完了: {$upserted}件 登録/更新, {$skipped}件 スキップ");
        $this->info("DB総数: " . RoadsideStation::count() . '件');

        return self::SUCCESS;
    }

    private function buildColumnMap(array $headers): array
    {
        $map = [];
        foreach ($headers as $index => $header) {
            $header = trim($header);
            if (isset(self::HEADER_MAP[$header]) && ! isset($map[self::HEADER_MAP[$header]])) {
                $map[self::HEADER_MAP[$header]] = $index;
            }
        }

        return $map;
    }

    private function csvFlag(array $row, array $colMap, string $key): bool
    {
        if (! isset($colMap[$key])) {
            return false;
        }

        $val = trim($row[$colMap[$key]] ?? '');

        return $val === 'ある' || $val === '1';
    }

    private function csvCol(array $row, array $colMap, string $key): string
    {
        if (! isset($colMap[$key])) {
            return '';
        }

        return trim($row[$colMap[$key]] ?? '');
    }

    private function upsertFromCsvRow(string $code, array $row, array $colMap): void
    {
        // name は公式一覧を正とするため更新側から除外（新規作成時のみ設定）。
        $payload = [
            'nickname'        => $this->csvCol($row, $colMap, 'nickname') ?: null,
            'address'         => $this->csvCol($row, $colMap, 'address') ?: null,
            'latitude'        => (float) ($this->csvCol($row, $colMap, 'lat') ?: 0),
            'longitude'       => (float) ($this->csvCol($row, $colMap, 'lng') ?: 0),
            'prefecture'      => $this->csvCol($row, $colMap, 'prefecture') ?: null,
            'city'            => $this->csvCol($row, $colMap, 'city') ?: null,
            'route'           => $this->csvCol($row, $colMap, 'route') ?: null,
            'image_url'       => $this->csvCol($row, $colMap, 'image') ?: null,
            'summary'         => $this->csvCol($row, $colMap, 'summary') ?: null,
            'website_url'     => $this->csvCol($row, $colMap, 'website') ?: null,
            'wikipedia_url'   => $this->csvCol($row, $colMap, 'wikipedia') ?: null,
            'has_atm'         => $this->csvFlag($row, $colMap, 'atm'),
            'has_restaurant'  => $this->csvFlag($row, $colMap, 'restaurant') || $this->csvFlag($row, $colMap, 'cafe'),
            'has_onsen'       => $this->csvFlag($row, $colMap, 'onsen'),
            'has_ev_charging' => $this->csvFlag($row, $colMap, 'ev_charging'),
            'has_wifi'        => $this->csvFlag($row, $colMap, 'wifi'),
            'has_shower'      => $this->csvFlag($row, $colMap, 'shower'),
            'has_camp'        => $this->csvFlag($row, $colMap, 'camp'),
            'has_gas_station' => $this->csvFlag($row, $colMap, 'gas_station'),
            'has_observatory' => $this->csvFlag($row, $colMap, 'observatory'),
            'has_shop'        => $this->csvFlag($row, $colMap, 'shop'),
            'designated_year' => ($y = $this->csvCol($row, $colMap, 'designated_year')) !== '' ? (int) $y : null,
        ];

        $station = RoadsideStation::firstOrNew(['station_code' => $code]);
        if (! $station->exists) {
            $station->name = $this->csvCol($row, $colMap, 'name'); // 新規作成時のみ
        }
        $station->fill($payload)->save();
    }
}
