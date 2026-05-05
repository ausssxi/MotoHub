<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Poi;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

final class FetchPois extends Command
{
    protected $signature = 'poi:fetch {--type= : gas_station, convenience_store, or michi_no_eki}';

    protected $description = 'Overpass APIからPOIデータ（ガソリンスタンド・コンビニ・道の駅）を取得';

    private const OVERPASS_URL = 'https://overpass-api.de/api/interpreter';

    private const REGIONS = [
        '北海道' => [41.3, 139.3, 45.6, 145.8],
        '東北'   => [36.8, 139.0, 41.5, 141.7],
        '関東'   => [34.8, 138.4, 37.0, 140.9],
        '中部'   => [34.6, 136.0, 37.8, 139.9],
        '近畿'   => [33.4, 134.5, 35.8, 136.9],
        '中国'   => [33.7, 130.8, 35.7, 134.4],
        '四国'   => [32.7, 132.0, 34.4, 134.8],
        '九州沖縄' => [24.0, 122.9, 34.3, 131.9],
    ];

    private const TYPE_QUERIES = [
        'gas_station' => '[out:json][timeout:120];(node["amenity"="fuel"]({bbox});way["amenity"="fuel"]({bbox}););out center;',
        'convenience_store' => '[out:json][timeout:120];(node["shop"="convenience"]["name"~"セブン|ローソン|ファミリーマート|ミニストップ|デイリーヤマザキ|セイコーマート|ポプラ|NewDays"]({bbox}););out center;',
        'michi_no_eki' => '[out:json][timeout:120];(node["name"~"道の駅"]({bbox});way["name"~"道の駅"]({bbox}););out center;',
    ];

    public function handle(): int
    {
        $typeOption = $this->option('type');
        $types = $typeOption
            ? [$typeOption]
            : array_keys(self::TYPE_QUERIES);

        foreach ($types as $type) {
            if (! isset(self::TYPE_QUERIES[$type])) {
                $this->error("不明なタイプ: {$type}");
                return self::FAILURE;
            }
        }

        $totalInserted = 0;

        foreach ($types as $type) {
            $this->info("=== {$type} の取得開始 ===");

            foreach (self::REGIONS as $regionName => $bbox) {
                $this->info("  [{$regionName}] リクエスト中...");

                $query = str_replace(
                    '{bbox}',
                    implode(',', $bbox),
                    self::TYPE_QUERIES[$type]
                );

                try {
                    $response = Http::timeout(120)
                        ->withHeaders([
                            'User-Agent' => 'MotoHub/1.0 (https://motohub.jp)',
                        ])
                        ->withBody('data=' . urlencode($query), 'application/x-www-form-urlencoded')
                        ->post(self::OVERPASS_URL);

                    if (! $response->successful()) {
                        $this->warn("  [{$regionName}] HTTPエラー: {$response->status()}");
                        sleep(10);
                        continue;
                    }

                    $data = $response->json();
                    $elements = $data['elements'] ?? [];
                    $count = 0;

                    foreach ($elements as $element) {
                        $lat = $element['lat'] ?? $element['center']['lat'] ?? null;
                        $lon = $element['lon'] ?? $element['center']['lon'] ?? null;

                        if (! $lat || ! $lon) {
                            continue;
                        }

                        $tags = $element['tags'] ?? [];

                        Poi::updateOrCreate(
                            ['osm_id' => $element['id'], 'type' => $type],
                            [
                                'name' => $tags['name'] ?? $tags['brand'] ?? '名称不明',
                                'latitude' => $lat,
                                'longitude' => $lon,
                                'address' => $this->buildAddress($tags),
                                'brand' => $tags['brand'] ?? $tags['operator'] ?? null,
                                'opening_hours' => $tags['opening_hours'] ?? null,
                            ]
                        );
                        $count++;
                    }

                    $totalInserted += $count;
                    $this->info("  [{$regionName}] {$count}件 登録/更新");
                } catch (\Exception $e) {
                    $this->error("  [{$regionName}] エラー: {$e->getMessage()}");
                }

                sleep(10);
            }
        }

        $this->info("完了: 合計 {$totalInserted} 件処理");

        return self::SUCCESS;
    }

    private function buildAddress(array $tags): ?string
    {
        $parts = array_filter([
            $tags['addr:province'] ?? $tags['addr:state'] ?? null,
            $tags['addr:city'] ?? null,
            $tags['addr:district'] ?? null,
            $tags['addr:quarter'] ?? null,
            $tags['addr:neighbourhood'] ?? null,
            $tags['addr:street'] ?? null,
            $tags['addr:housenumber'] ?? null,
        ]);

        return $parts ? implode('', $parts) : null;
    }
}
