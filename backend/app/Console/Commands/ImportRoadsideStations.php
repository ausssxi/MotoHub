<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\RoadsideStation;
use Illuminate\Console\Command;

final class ImportRoadsideStations extends Command
{
    protected $signature = 'roadside-stations:import
                            {--file= : CSVファイルのパス (デフォルト: storage/app/roadside_stations.csv)}
                            {--pref= : 都道府県コード (01-47) で絞り込み}';

    protected $description = 'LinkData.orgのCSVファイルから道の駅データを一括インポート';

    /**
     * CSVヘッダー名 → 内部キーのマッピング
     * プレフィックス付き・なし両方に対応
     */
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

            // station_code がない行はスキップ
            if ($code === '' || ! preg_match('/^\d{5}$/', $code)) {
                $skipped++;
                continue;
            }

            // 都道府県フィルタ
            if ($prefFilter && ! str_starts_with($code, $prefFilter)) {
                continue;
            }

            // 営業中以外はスキップ
            if ($status !== '' && $status !== '営業中') {
                $this->line("  {$code}: {$name}（{$status}）→ スキップ");
                $skipped++;
                continue;
            }

            $this->upsertFromRow($code, $row, $colMap);
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

    /**
     * CSVヘッダーから内部キー → カラムインデックスのマップを構築
     */
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

    /**
     * 施設フラグ変換: 「ある」or「1」→ true, それ以外 → false
     */
    private function hasFlag(array $row, array $colMap, string $key): bool
    {
        if (! isset($colMap[$key])) {
            return false;
        }

        $val = trim($row[$colMap[$key]] ?? '');

        return $val === 'ある' || $val === '1';
    }

    private function col(array $row, array $colMap, string $key): string
    {
        if (! isset($colMap[$key])) {
            return '';
        }

        return trim($row[$colMap[$key]] ?? '');
    }

    private function upsertFromRow(string $code, array $row, array $colMap): void
    {
        RoadsideStation::updateOrCreate(
            ['station_code' => $code],
            [
                'name'            => $this->col($row, $colMap, 'name'),
                'nickname'        => $this->col($row, $colMap, 'nickname') ?: null,
                'address'         => $this->col($row, $colMap, 'address') ?: null,
                'latitude'        => (float) ($this->col($row, $colMap, 'lat') ?: 0),
                'longitude'       => (float) ($this->col($row, $colMap, 'lng') ?: 0),
                'prefecture'      => $this->col($row, $colMap, 'prefecture') ?: null,
                'city'            => $this->col($row, $colMap, 'city') ?: null,
                'route'           => $this->col($row, $colMap, 'route') ?: null,
                'image_url'       => $this->col($row, $colMap, 'image') ?: null,
                'summary'         => $this->col($row, $colMap, 'summary') ?: null,
                'website_url'     => $this->col($row, $colMap, 'website') ?: null,
                'wikipedia_url'   => $this->col($row, $colMap, 'wikipedia') ?: null,
                'has_atm'         => $this->hasFlag($row, $colMap, 'atm'),
                'has_restaurant'  => $this->hasFlag($row, $colMap, 'restaurant') || $this->hasFlag($row, $colMap, 'cafe'),
                'has_onsen'       => $this->hasFlag($row, $colMap, 'onsen'),
                'has_ev_charging' => $this->hasFlag($row, $colMap, 'ev_charging'),
                'has_wifi'        => $this->hasFlag($row, $colMap, 'wifi'),
                'has_shower'      => $this->hasFlag($row, $colMap, 'shower'),
                'has_camp'        => $this->hasFlag($row, $colMap, 'camp'),
                'has_gas_station' => $this->hasFlag($row, $colMap, 'gas_station'),
                'has_observatory' => $this->hasFlag($row, $colMap, 'observatory'),
                'has_shop'        => $this->hasFlag($row, $colMap, 'shop'),
                'designated_year' => ($y = $this->col($row, $colMap, 'designated_year')) !== '' ? (int) $y : null,
            ]
        );
    }
}
