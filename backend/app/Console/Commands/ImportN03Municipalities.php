<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * 国土数値情報 N03（行政区域）の行区切りGeoJSONを DB へ取り込む。
 *
 * 入力（実測）:
 *   - 既定パス storage_path('app/n03/N03-20260101.geojson')（約579MB）
 *   - 1フィーチャ1行の行区切りGeoJSON。フィーチャ行は '{ "type": "Feature"' で始まり、
 *     行末にカンマ（最終行のみ無し）。全125,130フィーチャ / 1,905市区町村。
 *   - properties: N03_001=都道府県 N03_002=支庁 N03_003=郡・政令市
 *                 N03_004=市区町村 N03_005=区 N03_007=コード5桁
 *   - 表示名 full_name = N03_003 + N03_004 + N03_005 の単純連結（null は空文字）。
 *
 * 【SRID は 0 で固定】geometry は ST_GeomFromGeoJSON(?, 1, 0) で投入する。4326 は使わない
 * （4326 だと MySQL が緯度・経度を入れ替え、ST_Contains(geom, POINT(経度,緯度)) の問い合わせと食い違う）。
 *
 * メモリ: 579MB を一括読みしない。fopen + fgets で1行ずつ処理する。
 */
class ImportN03Municipalities extends Command
{
    protected $signature = 'municipalities:import-n03
        {--file= : GeoJSONのパス}
        {--execute : 実際に書き込む（未指定は件数集計のみ）}';

    protected $description = '国土数値情報N03（行政区域）のGeoJSONを municipalities / municipality_polygons へ取り込む（既定は集計のみ）';

    /** ポリゴンのバッチINSERT件数（1行ずつは遅すぎるため）。 */
    private const POLYGON_BATCH = 200;

    public function handle(): int
    {
        $execute = (bool) $this->option('execute');
        $path = (string) ($this->option('file') ?: storage_path('app/n03/N03-20260101.geojson'));

        if (! is_file($path)) {
            $this->error("GeoJSONが見つかりません: {$path}");

            return self::FAILURE;
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            $this->error("GeoJSONを開けません: {$path}");

            return self::FAILURE;
        }

        if (! $execute) {
            $this->info('[集計のみ] --execute が無いため書き込みません。');
        }
        $this->info("読込: {$path}");

        // --execute の最初に polygons を空にして入れ直す（再実行可能に）。
        if ($execute) {
            DB::table('municipality_polygons')->truncate();
        }

        $start = microtime(true);
        $now = now();

        $featureCount = 0;
        $skipped = 0;
        $polygonCount = 0;
        /** @var array<string, array<string, mixed>> code をキーにした市区町村メタ（重複排除） */
        $municipalities = [];
        /** @var array<int, array{0: string, 1: string}> [code, geometryJson] のバッチ */
        $polygonBatch = [];

        while (($line = fgets($handle)) !== false) {
            $trimmed = trim($line);

            // フィーチャ行のみ対象。
            if (! str_starts_with($trimmed, '{ "type": "Feature"')) {
                continue;
            }

            $featureCount++;

            // 末尾カンマを除いてデコード。
            $json = rtrim($trimmed, ',');
            $feature = json_decode($json, true);
            if (! is_array($feature)) {
                $skipped++;

                continue;
            }

            $props = $feature['properties'] ?? [];
            $code = trim((string) ($props['N03_007'] ?? ''));

            // コード欠落はスキップ。
            if ($code === '') {
                $skipped++;

                continue;
            }

            // 市区町村メタ（同一コードが何度も現れるため上書き集約）。
            $county = (string) ($props['N03_003'] ?? '');
            $cityName = (string) ($props['N03_004'] ?? '');
            $ward = (string) ($props['N03_005'] ?? '');
            $municipalities[$code] = [
                'code' => $code,
                'prefecture' => (string) ($props['N03_001'] ?? ''),
                'branch' => ($b = (string) ($props['N03_002'] ?? '')) !== '' ? $b : null,
                'county' => $county !== '' ? $county : null,
                'city_name' => $cityName,
                'ward' => $ward !== '' ? $ward : null,
                'full_name' => $county.$cityName.$ward,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $polygonCount++;

            if ($execute) {
                $geometry = $feature['geometry'] ?? null;
                $geomJson = json_encode($geometry, JSON_UNESCAPED_UNICODE);
                if ($geomJson === false) {
                    // geometry が壊れている行は投入対象から外す（メタは既に集約済み）。
                    $polygonCount--;
                    $skipped++;

                    continue;
                }

                $polygonBatch[] = [$code, $geomJson];
                if (count($polygonBatch) >= self::POLYGON_BATCH) {
                    $this->flushPolygons($polygonBatch);
                    $polygonBatch = [];
                }
            }

            if ($featureCount % 10000 === 0) {
                $elapsed = round(microtime(true) - $start, 1);
                $this->line("  ... {$featureCount}フィーチャ処理 / 市区町村".count($municipalities)."件 / {$elapsed}秒");
            }
        }

        fclose($handle);

        if ($execute) {
            // 残りのポリゴンをフラッシュ。
            $this->flushPolygons($polygonBatch);
            $polygonBatch = [];

            // 市区町村メタを upsert（code をキーに）。1,905件程度なのでチャンクして流す。
            foreach (array_chunk(array_values($municipalities), 500) as $chunk) {
                DB::table('municipalities')->upsert(
                    $chunk,
                    ['code'],
                    ['prefecture', 'branch', 'county', 'city_name', 'ward', 'full_name', 'updated_at'],
                );
            }
        }

        $elapsed = round(microtime(true) - $start, 1);

        $this->newLine();
        $this->info(sprintf(
            '%sフィーチャ数: %d / 市区町村数: %d / ポリゴン%s: %d / スキップ: %d / 所要: %s秒',
            $execute ? '' : '[集計のみ] ',
            $featureCount,
            count($municipalities),
            $execute ? '投入' : '投入予定',
            $polygonCount,
            $skipped,
            $elapsed,
        ));

        if (! $execute) {
            $this->newLine();
            $this->warn('--execute が無いため実際の書き込みは行っていません。');
        }

        return self::SUCCESS;
    }

    /**
     * ポリゴンのバッチを1文でINSERTする。geometry は ST_GeomFromGeoJSON(?, 1, 0)（SRID 0）で投入。
     *
     * @param  array<int, array{0: string, 1: string}>  $batch  [code, geometryJson] の配列
     */
    private function flushPolygons(array $batch): void
    {
        if ($batch === []) {
            return;
        }

        $placeholders = [];
        $bindings = [];
        foreach ($batch as [$code, $geomJson]) {
            $placeholders[] = '(?, ST_GeomFromGeoJSON(?, 1, 0))';
            $bindings[] = $code;
            $bindings[] = $geomJson;
        }

        $sql = 'INSERT INTO municipality_polygons (`code`, `geom`) VALUES '.implode(', ', $placeholders);
        DB::statement($sql, $bindings);
    }
}
