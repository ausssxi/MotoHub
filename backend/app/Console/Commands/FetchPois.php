<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Poi;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

final class FetchPois extends Command
{
    protected $signature = 'poi:fetch {--type= : gas_station, convenience_store, michi_no_eki, or car_wash}';

    protected $description = 'Overpass APIからPOIデータ（ガソリンスタンド・コンビニ・道の駅・洗車場）を取得';

    private const OVERPASS_URL = 'https://overpass-api.de/api/interpreter';

    // OSM の node / way は id 名前空間が別のため、同一 type 内で node と way が同一 id だと
    // (osm_id, type) unique 制約で衝突する。car_wash の way/relation はこの値でオフセットして分離する。
    // node id < ~1.3e10 / way id < ~2e9 に対し 1e13 は十分離れており、bigInteger の範囲内。
    private const WAY_ID_OFFSET = 10_000_000_000_000;

    // 畜産の防疫設備（バイクの洗車場ではない）。名称に含む要素は取り込まない。
    private const CAR_WASH_EXCLUDE = '車両消毒槽';

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
        // 洗車場（amenity=car_wash）。way が全体の半数超のため node/way 両方を取得し、
        // way は座標を持たないので「out center tags;」で center 座標とタグを得る。
        'car_wash' => '[out:json][timeout:120];(node["amenity"="car_wash"]({bbox});way["amenity"="car_wash"]({bbox}););out center tags;',
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
        $skippedDisinfection = 0; // 車両消毒槽としてスキップした件数（car_wash）

        foreach ($types as $type) {
            $this->info("=== {$type} の取得開始 ===");

            foreach (self::REGIONS as $regionName => $bbox) {
                $this->info("  [{$regionName}] リクエスト中...");

                $query = str_replace(
                    '{bbox}',
                    implode(',', $bbox),
                    self::TYPE_QUERIES[$type]
                );

                // 生成した Overpass クエリは -v で確認できる（実際に叩かずに文字列を検証したい場合用）。
                if ($this->getOutput()->isVerbose()) {
                    $this->line("  Overpass query: {$query}");
                }

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

                        // 畜産の防疫設備（車両消毒槽）はバイクの洗車場ではないため除外。
                        if (isset($tags['name']) && mb_strpos($tags['name'], self::CAR_WASH_EXCLUDE) !== false) {
                            $skippedDisinfection++;
                            continue;
                        }

                        Poi::updateOrCreate(
                            ['osm_id' => $this->resolveOsmId($element, $type), 'type' => $type],
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
        if ($skippedDisinfection > 0) {
            $this->info("除外（車両消毒槽）: {$skippedDisinfection} 件スキップ");
        }

        return self::SUCCESS;
    }

    /**
     * 保存する osm_id を決める。
     *
     * OSM の node と way は id 名前空間が別で、同一 type 内で衝突し得る。
     * 既存タイプ（gas_station / convenience_store / michi_no_eki）の保存済み osm_id を
     * 壊さないよう、オフセット適用は car_wash に限定する。node はそのまま、way/relation は
     * WAY_ID_OFFSET を加えて分離する。
     */
    private function resolveOsmId(array $element, string $type): int
    {
        $id = (int) $element['id'];

        if ($type === 'car_wash' && ($element['type'] ?? 'node') !== 'node') {
            return self::WAY_ID_OFFSET + $id;
        }

        return $id;
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
