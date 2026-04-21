<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Station;
use Illuminate\Console\Command;

final class ImportStations extends Command
{
    protected $signature = 'station:import {--dry-run : Preview without saving}';

    protected $description = 'GeoJSONファイルから全国の駅データをインポート（同名近接駅の統合対応）';

    /** 都道府県庁所在地の座標（最近傍マッチ用） */
    private const PREFECTURE_CENTERS = [
        '北海道' => [43.0646, 141.3468],
        '青森県' => [40.8244, 140.7400],
        '岩手県' => [39.7036, 141.1527],
        '宮城県' => [38.2688, 140.8721],
        '秋田県' => [39.7186, 140.1024],
        '山形県' => [38.2404, 140.3633],
        '福島県' => [37.7503, 140.4676],
        '茨城県' => [36.3418, 140.4468],
        '栃木県' => [36.5657, 139.8836],
        '群馬県' => [36.3911, 139.0608],
        '埼玉県' => [35.8569, 139.6489],
        '千葉県' => [35.6047, 140.1233],
        '東京都' => [35.6762, 139.6503],
        '神奈川県' => [35.4478, 139.6425],
        '新潟県' => [37.9026, 139.0232],
        '富山県' => [36.6953, 137.2114],
        '石川県' => [36.5946, 136.6256],
        '福井県' => [36.0652, 136.2217],
        '山梨県' => [35.6642, 138.5684],
        '長野県' => [36.2321, 138.1812],
        '岐阜県' => [35.3912, 136.7222],
        '静岡県' => [34.9769, 138.3831],
        '愛知県' => [35.1802, 136.9066],
        '三重県' => [34.7303, 136.5086],
        '滋賀県' => [35.0045, 135.8686],
        '京都府' => [35.0214, 135.7556],
        '大阪府' => [34.6864, 135.5200],
        '兵庫県' => [34.6913, 135.1830],
        '奈良県' => [34.6851, 135.8329],
        '和歌山県' => [34.2260, 135.1675],
        '鳥取県' => [35.5039, 134.2378],
        '島根県' => [35.4723, 133.0505],
        '岡山県' => [34.6618, 133.9345],
        '広島県' => [34.3963, 132.4594],
        '山口県' => [34.1861, 131.4714],
        '徳島県' => [34.0658, 134.5593],
        '香川県' => [34.3401, 134.0434],
        '愛媛県' => [33.8416, 132.7656],
        '高知県' => [33.5597, 133.5311],
        '福岡県' => [33.6064, 130.4183],
        '佐賀県' => [33.2494, 130.2988],
        '長崎県' => [32.7448, 129.8737],
        '熊本県' => [32.7898, 130.7417],
        '大分県' => [33.2382, 131.6126],
        '宮崎県' => [31.9111, 131.4239],
        '鹿児島県' => [31.5602, 130.5581],
        '沖縄県' => [26.2124, 127.6809],
    ];

    /** 同名駅の近接判定閾値（約500m = 0.005度） */
    private const MERGE_THRESHOLD_DEG = 0.005;

    /** @var array<string, array{name: string, prefecture: string, slug: string}> (name|prefecture) => definition */
    private array $majorIndex = [];

    public function handle(): int
    {
        $path = storage_path('app/N02-24_Station.geojson');
        if (! file_exists($path)) {
            $this->error("GeoJSONファイルが見つかりません: {$path}");

            return self::FAILURE;
        }

        // config/stations.php から主要駅定義を読み込み
        $majorDefs = config('stations.major', []);
        foreach ($majorDefs as $def) {
            $key = $def['name'].'|'.$def['prefecture'];
            $this->majorIndex[$key] = $def;
        }
        $this->info('主要駅定義: '.count($this->majorIndex).'駅');

        $this->info('GeoJSON読み込み中...');
        $json = json_decode(file_get_contents($path), true);
        $features = $json['features'] ?? [];
        $this->info(count($features).' features');

        // ─── Phase 1: N02_005g でグルーピング ───
        $groups = [];
        foreach ($features as $feature) {
            $groupCode = $feature['properties']['N02_005g'] ?? null;
            if (! $groupCode) {
                continue;
            }
            $groups[$groupCode][] = $feature;
        }
        $this->info(count($groups).' groups (by N02_005g)');

        // ─── Phase 2: 各グループ → 中間レコード変換 ───
        $records = [];
        foreach ($groups as $groupCode => $groupFeatures) {
            $firstProps = $groupFeatures[0]['properties'];
            $name = $firstProps['N02_005'] ?? null;
            if (! $name) {
                continue;
            }

            $lineNames = [];
            $companyNames = [];
            $allCoords = [];

            foreach ($groupFeatures as $f) {
                $p = $f['properties'] ?? [];
                if (! empty($p['N02_003'])) {
                    $lineNames[$p['N02_003']] = true;
                }
                if (! empty($p['N02_004'])) {
                    $companyNames[$p['N02_004']] = true;
                }
                foreach ($f['geometry']['coordinates'] ?? [] as $c) {
                    if (is_array($c) && count($c) >= 2) {
                        $allCoords[] = $c;
                    }
                }
            }

            if (empty($allCoords)) {
                continue;
            }

            $lng = array_sum(array_column($allCoords, 0)) / count($allCoords);
            $lat = array_sum(array_column($allCoords, 1)) / count($allCoords);

            $records[] = [
                'group_code' => $groupCode,
                'name' => $name,
                'lat' => $lat,
                'lng' => $lng,
                'line_names' => $lineNames,
                'company_names' => $companyNames,
            ];
        }
        $this->info(count($records).' records (before merge)');

        // ─── Phase 3: 同名駅の近接統合（100m以内） ───
        $merged = $this->mergeNearbyStations($records);
        $this->info(count($merged).' stations (after merge)');

        // ─── Phase 4: DB書き込み ───
        $bar = $this->output->createProgressBar(count($merged));
        $created = 0;
        $updated = 0;
        $usedSlugs = [];

        foreach ($merged as $record) {
            $bar->advance();

            $prefecture = $this->detectPrefecture($record['lat'], $record['lng']);
            $majorDef = $this->findMajorDef($record['name'], $prefecture);
            $isMajor = $majorDef !== null;

            if ($isMajor) {
                $slug = $majorDef['slug'];
            } else {
                $slug = 'st-'.$record['group_code'];
            }

            // slug衝突回避
            if (isset($usedSlugs[$slug])) {
                $slug = 'st-'.$record['group_code'];
            }
            $usedSlugs[$slug] = true;

            $data = [
                'name' => $record['name'],
                'prefecture' => $prefecture,
                'latitude' => round($record['lat'], 7),
                'longitude' => round($record['lng'], 7),
                'line_names' => implode('/', array_keys($record['line_names'])),
                'company_names' => implode('/', array_keys($record['company_names'])),
                'is_major' => $isMajor,
            ];

            if ($this->option('dry-run')) {
                if ($isMajor) {
                    $this->newLine();
                    $this->info("[DRY] {$record['name']} ({$slug}) - {$prefecture} [{$data['line_names']}]");
                }

                continue;
            }

            $station = Station::updateOrCreate(['slug' => $slug], $data);

            if ($station->wasRecentlyCreated) {
                $created++;
            } else {
                $updated++;
            }
        }

        $bar->finish();
        $this->newLine(2);

        if ($this->option('dry-run')) {
            $this->warn('DRY RUN - データベースへの書き込みなし');
            $this->info('主要駅マッチ結果:');
            $matched = [];
            foreach ($merged as $r) {
                $pref = $this->detectPrefecture($r['lat'], $r['lng']);
                $def = $this->findMajorDef($r['name'], $pref);
                if ($def) {
                    $matched[] = $def['slug'];
                }
            }
            foreach ($this->majorIndex as $def) {
                $status = in_array($def['slug'], $matched) ? 'OK' : 'MISSING';
                $this->line("  [{$status}] {$def['name']} ({$def['prefecture']}) → {$def['slug']}");
            }
        } else {
            $this->info("完了: 作成 {$created} / 更新 {$updated}");
            $majorCount = Station::where('is_major', true)->count();
            $this->info("主要駅: {$majorCount}駅 (期待値: ".count($this->majorIndex).')');
        }

        return self::SUCCESS;
    }

    /**
     * 同名駅で100m以内にあるレコードを統合
     */
    private function mergeNearbyStations(array $records): array
    {
        // 駅名別にグルーピング
        $byName = [];
        foreach ($records as $r) {
            $byName[$r['name']][] = $r;
        }

        $merged = [];
        foreach ($byName as $name => $group) {
            if (count($group) === 1) {
                $merged[] = $group[0];

                continue;
            }

            // クラスタリング: 近接レコードを統合
            $clusters = [];
            $used = array_fill(0, count($group), false);

            for ($i = 0; $i < count($group); $i++) {
                if ($used[$i]) {
                    continue;
                }

                $cluster = [$i];
                $used[$i] = true;

                // 他のレコードとの近接チェック
                for ($j = $i + 1; $j < count($group); $j++) {
                    if ($used[$j]) {
                        continue;
                    }

                    // クラスタ内のいずれかのレコードと100m以内なら統合
                    foreach ($cluster as $ci) {
                        $dLat = abs($group[$ci]['lat'] - $group[$j]['lat']);
                        $dLng = abs($group[$ci]['lng'] - $group[$j]['lng']);
                        if ($dLat <= self::MERGE_THRESHOLD_DEG && $dLng <= self::MERGE_THRESHOLD_DEG) {
                            $cluster[] = $j;
                            $used[$j] = true;

                            break;
                        }
                    }
                }

                $clusters[] = $cluster;
            }

            // 各クラスタを1レコードに統合
            foreach ($clusters as $clusterIndices) {
                $first = $group[$clusterIndices[0]];
                $mergedLineNames = $first['line_names'];
                $mergedCompanyNames = $first['company_names'];
                $latSum = $first['lat'];
                $lngSum = $first['lng'];

                for ($k = 1; $k < count($clusterIndices); $k++) {
                    $r = $group[$clusterIndices[$k]];
                    $mergedLineNames = array_merge($mergedLineNames, $r['line_names']);
                    $mergedCompanyNames = array_merge($mergedCompanyNames, $r['company_names']);
                    $latSum += $r['lat'];
                    $lngSum += $r['lng'];
                }

                $count = count($clusterIndices);
                $merged[] = [
                    'group_code' => $first['group_code'],
                    'name' => $name,
                    'lat' => $latSum / $count,
                    'lng' => $lngSum / $count,
                    'line_names' => $mergedLineNames,
                    'company_names' => $mergedCompanyNames,
                ];
            }
        }

        return $merged;
    }

    /**
     * (name, prefecture) で主要駅定義を検索
     */
    private function findMajorDef(string $name, string $prefecture): ?array
    {
        $key = $name.'|'.$prefecture;

        return $this->majorIndex[$key] ?? null;
    }

    private function detectPrefecture(float $lat, float $lng): string
    {
        $minDist = PHP_FLOAT_MAX;
        $nearest = '東京都';

        foreach (self::PREFECTURE_CENTERS as $pref => [$pLat, $pLng]) {
            $dist = ($lat - $pLat) ** 2 + ($lng - $pLng) ** 2;
            if ($dist < $minDist) {
                $minDist = $dist;
                $nearest = $pref;
            }
        }

        return $nearest;
    }
}
