<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BikeParking;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ImportBikeparkParkings extends Command
{
    protected $signature = 'parking:import-bikepark
        {--dry-run : 保存せず確認のみ}
        {--limit= : 取得上限件数}';

    protected $description = 'bikepark.in からバイク駐車場データを取得してDBに保存します';

    private const API_URL = 'https://bikepark.in/api.pl';
    private const DETAIL_URL = 'https://bikepark.in/detail.cgi';
    private const USER_AGENT = 'MotoHub/1.0 (https://www.motohub.jp)';
    private const REQUEST_INTERVAL_US = 2000000; // 2秒（個人サイトなので配慮）

    // 日本全国を網羅するための座標グリッド（主要都市・エリア）
    private const SCAN_POINTS = [
        // 北海道
        [43.06, 141.35], [43.77, 142.37], [42.92, 143.20], [41.77, 140.73],
        // 東北
        [40.82, 140.74], [39.70, 141.15], [38.27, 140.87], [39.72, 140.10],
        [38.24, 140.36], [37.75, 140.47],
        // 関東
        [36.34, 140.45], [36.57, 139.88], [36.39, 139.06], [35.86, 139.65],
        [35.61, 140.12], [35.68, 139.77], [35.45, 139.64], [35.69, 139.70],
        [35.63, 139.88], [35.73, 139.65], [35.33, 139.55], [35.47, 139.63],
        // 中部
        [37.90, 139.02], [36.70, 137.21], [36.59, 136.63], [36.07, 136.22],
        [35.66, 138.57], [36.23, 138.18], [35.39, 136.72], [34.98, 138.38],
        [35.18, 136.91], [35.17, 136.88],
        // 近畿
        [34.73, 136.51], [35.00, 135.87], [35.02, 135.76], [34.69, 135.52],
        [34.69, 135.18], [34.69, 135.83], [34.23, 135.17], [34.65, 135.50],
        [34.70, 135.50],
        // 中国・四国
        [35.50, 134.24], [35.47, 133.05], [34.66, 133.93], [34.40, 132.46],
        [34.19, 131.47], [34.07, 134.56], [34.34, 134.04], [33.84, 132.77],
        [33.56, 133.53],
        // 九州・沖縄
        [33.61, 130.42], [33.25, 130.30], [32.74, 129.87], [32.79, 130.74],
        [33.24, 131.61], [31.91, 131.42], [31.56, 130.56], [26.34, 127.68],
        [33.59, 130.40],
    ];

    private int $created = 0;
    private int $skipped = 0;
    private int $duplicated = 0;
    private int $failed = 0;
    private array $seenIds = [];

    public function handle(): void
    {
        $dryRun = $this->option('dry-run');
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;

        if ($dryRun) {
            $this->info('[DRY RUN] データは保存されません。');
        }

        $this->info('bikepark.in からデータを取得開始...');
        $this->info('スキャンポイント: ' . count(self::SCAN_POINTS) . '箇所');

        $bar = $this->output->createProgressBar(count(self::SCAN_POINTS));
        $bar->start();

        foreach (self::SCAN_POINTS as $point) {
            if ($limit && $this->created >= $limit) {
                break;
            }

            $this->fetchAndProcess($point[0], $point[1], $dryRun, $limit);
            $bar->advance();

            usleep(self::REQUEST_INTERVAL_US);
        }

        $bar->finish();
        $this->newLine(2);

        $this->info('========================================');
        $this->info("完了！ 新規: {$this->created}件 / 重複: {$this->duplicated}件 / スキップ: {$this->skipped}件 / 失敗: {$this->failed}件");
    }

    private function fetchAndProcess(float $lat, float $lng, bool $dryRun, ?int $limit): void
    {
        try {
            $response = Http::withHeaders([
                'User-Agent' => self::USER_AGENT,
            ])->timeout(15)->get(self::API_URL, [
                'lat' => $lat,
                'lng' => $lng,
            ]);

            if (!$response->successful()) {
                $this->failed++;
                return;
            }

            $items = $response->json();
            if (!is_array($items)) {
                return;
            }

            foreach ($items as $item) {
                if ($limit && $this->created >= $limit) {
                    return;
                }

                // 既に処理済みのIDはスキップ
                $id = $item['ID'] ?? null;
                if (!$id || isset($this->seenIds[$id])) {
                    continue;
                }
                $this->seenIds[$id] = true;

                $this->processItem($item, $dryRun);
            }
        } catch (\Exception $e) {
            $this->failed++;
        }
    }

    private function processItem(array $item, bool $dryRun): void
    {
        $name = $item['name'] ?? '';
        $address = $item['address'] ?? '';
        $fee = $item['fee'] ?? '';

        if (empty($name)) {
            $this->skipped++;
            return;
        }

        // DMS座標を10進数に変換
        $lat = $this->dmsToDecimal($item['nl'] ?? '');
        $lng = $this->dmsToDecimal($item['el'] ?? '');

        if ($lat === null || $lng === null) {
            $this->skipped++;
            return;
        }

        // 重複チェック
        if ($this->isDuplicate($name, $address, $lat, $lng)) {
            $this->duplicated++;
            return;
        }

        // 都道府県と市区町村を抽出
        $prefecture = $this->extractPrefecture($address);
        $city = $this->extractCity($address);

        $sourceUrl = self::DETAIL_URL . '?ID=' . ($item['ID'] ?? '');

        $data = [
            'name' => $name,
            'address' => $address,
            'latitude' => $lat,
            'longitude' => $lng,
            'prefecture' => $prefecture,
            'city' => $city,
            'parking_type' => $this->detectParkingType($item['type'] ?? ''),
            'description' => $fee,
            'source_url' => $sourceUrl,
            'is_active' => true,
        ];

        // 料金パース
        $this->parseFee($fee, $data);

        if ($dryRun) {
            $this->line("  [DRY] {$name} | {$address} | lat={$lat} lng={$lng}");
        } else {
            try {
                BikeParking::create($data);
            } catch (\Exception $e) {
                $this->failed++;
                return;
            }
        }

        $this->created++;
    }

    /**
     * DMS座標（度.分.秒.小数）を10進数に変換
     * 例: "35.41.05.98" → 35 + 41/60 + 5.98/3600
     */
    private function dmsToDecimal(string $dms): ?float
    {
        $parts = explode('.', $dms);
        if (count($parts) < 3) {
            return null;
        }

        $degrees = (int) $parts[0];
        $minutes = (int) $parts[1];
        // 秒と小数秒を結合
        $seconds = (float) ($parts[2] . '.' . ($parts[3] ?? '0'));

        return $degrees + $minutes / 60.0 + $seconds / 3600.0;
    }

    /**
     * 重複チェック（名前の正規化 + 50m以内の座標チェック）
     */
    private function isDuplicate(string $name, string $address, float $lat, float $lng): bool
    {
        // 名前完全一致
        if (BikeParking::where('name', $name)->exists()) {
            return true;
        }

        // 名前の正規化後の一致（パフォーマンスのため座標近傍に限定）
        $normalizedName = $this->normalizeName($name);
        $nearby = BikeParking::whereBetween('latitude', [$lat - 0.005, $lat + 0.005])
            ->whereBetween('longitude', [$lng - 0.005, $lng + 0.005])
            ->get();

        foreach ($nearby as $p) {
            if ($this->normalizeName($p->name) === $normalizedName) {
                return true;
            }
        }

        // 座標が50m以内の駐車場があるか（緯度経度の50m ≒ 約0.00045度）
        $veryNearby = BikeParking::whereBetween('latitude', [$lat - 0.00045, $lat + 0.00045])
            ->whereBetween('longitude', [$lng - 0.00045, $lng + 0.00045])
            ->exists();

        return $veryNearby;
    }

    private function normalizeName(string $name): string
    {
        $name = mb_convert_kana($name, 'as');
        $name = preg_replace('/[\s　]+/u', '', $name);
        $name = str_replace(['（', '）', '【', '】'], ['(', ')', '(', ')'], $name);

        return mb_strtolower($name);
    }

    private function extractPrefecture(string $address): string
    {
        if (preg_match('/^(北海道|東京都|大阪府|京都府|.{2,3}県)/u', $address, $m)) {
            return $m[1];
        }

        return '';
    }

    private function extractCity(string $address): string
    {
        if (preg_match('/([\p{Han}\p{Hiragana}\p{Katakana}]+?[市区町村郡])/u', $address, $m)) {
            return $m[1];
        }

        return '';
    }

    private function detectParkingType(string $type): string
    {
        return match ($type) {
            '3', '4' => 'bicycle_shared',
            default => 'bike_only',
        };
    }

    private function parseFee(string $fee, array &$data): void
    {
        if (empty($fee)) {
            return;
        }

        // 無料判定
        if (preg_match('/無料/', $fee) && !preg_match('/\d+円/', $fee)) {
            $data['is_free'] = true;

            return;
        }

        // 時間料金
        if (preg_match('/(\d+)分[^\d]*?(\d+)円/', $fee, $m)) {
            $minutes = (int) $m[1];
            $price = (int) $m[2];
            if ($minutes > 0) {
                $data['price_per_hour'] = (int) round($price * 60 / $minutes);
            }
        } elseif (preg_match('/(\d+)時間[^\d]*?(\d+)円/', $fee, $m)) {
            $hours = (int) $m[1];
            $price = (int) $m[2];
            if ($hours > 0) {
                $data['price_per_hour'] = (int) round($price / $hours);
            }
        }

        // 日額
        if (preg_match('/24時間[^\d]*(\d[\d,]*)円/', $fee, $m)) {
            $data['price_per_day'] = (int) str_replace(',', '', $m[1]);
        } elseif (preg_match('/最大[^\d]*(\d[\d,]*)円/', $fee, $m)) {
            $data['price_per_day'] = (int) str_replace(',', '', $m[1]);
        }

        // 月額
        if (preg_match('/月[極額]?\s*(\d[\d,]*)円/', $fee, $m)) {
            $data['price_per_month'] = (int) str_replace(',', '', $m[1]);
        }
    }
}
