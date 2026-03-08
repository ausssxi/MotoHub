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
        {--limit= : 取得上限件数}
        {--start=1 : 開始ID}
        {--end=3900 : 終了ID}';

    protected $description = 'bikepark.in の個別ページ(ID=1~3900)を巡回してバイク駐車場データを取得します';

    private const DETAIL_URL = 'https://bikepark.in/detail.cgi';
    private const USER_AGENT = 'MotoHub/1.0 (https://www.motohub.jp)';
    private const REQUEST_INTERVAL_US = 2000000; // 2秒（個人サイトなので配慮）

    private int $created = 0;
    private int $skipped = 0;
    private int $duplicated = 0;
    private int $failed = 0;
    private int $notFound = 0;

    public function handle(): void
    {
        $dryRun = $this->option('dry-run');
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $startId = (int) $this->option('start');
        $endId = (int) $this->option('end');

        if ($dryRun) {
            $this->info('[DRY RUN] データは保存されません。');
        }

        // 既に取得済みのbikepark.in IDを収集（スキップ用）
        $existingIds = BikeParking::where('source_url', 'like', '%bikepark.in%')
            ->pluck('source_url')
            ->map(function ($url) {
                preg_match('/ID=(\d+)/', $url, $m);

                return $m[1] ?? null;
            })
            ->filter()
            ->flip()
            ->toArray();

        $this->info("bikepark.in ID={$startId}〜{$endId} を巡回開始...");
        $this->info('取得済み: ' . count($existingIds) . '件（スキップ対象）');

        $totalIds = $endId - $startId + 1;
        $bar = $this->output->createProgressBar($totalIds);
        $bar->start();

        for ($id = $startId; $id <= $endId; $id++) {
            if ($limit && $this->created >= $limit) {
                break;
            }

            // 既に取得済みならスキップ（リクエスト不要）
            if (isset($existingIds[(string) $id])) {
                $this->skipped++;
                $bar->advance();
                continue;
            }

            $this->fetchAndProcess($id, $dryRun);
            $bar->advance();

            usleep(self::REQUEST_INTERVAL_US);
        }

        $bar->finish();
        $this->newLine(2);

        $this->info('========================================');
        $this->info("完了！ 新規: {$this->created}件 / 重複(JMPSA等): {$this->duplicated}件 / 取得済スキップ: {$this->skipped}件 / 存在しないID: {$this->notFound}件 / 失敗: {$this->failed}件");
    }

    private function fetchAndProcess(int $id, bool $dryRun): void
    {
        try {
            $response = Http::withHeaders([
                'User-Agent' => self::USER_AGENT,
            ])->timeout(15)->get(self::DETAIL_URL, [
                'ID' => $id,
            ]);

            if (!$response->successful()) {
                $this->failed++;

                return;
            }

            $body = $response->body();

            // Shift_JIS → UTF-8
            $html = mb_convert_encoding($body, 'UTF-8', 'SJIS-win');

            // ページが存在しないか確認（タイトルに駐輪場名が無い場合）
            if (!preg_match('/駐輪場詳細/u', $html)) {
                $this->notFound++;

                return;
            }

            $data = $this->parseDetailPage($html, $id);
            if ($data === null) {
                $this->notFound++;

                return;
            }

            // 重複チェック（JMPSAデータ等との重複）
            if ($this->isDuplicate($data['name'], $data['address'] ?? '', $data['latitude'] ?? 0, $data['longitude'] ?? 0)) {
                $this->duplicated++;

                return;
            }

            if ($dryRun) {
                $this->newLine();
                $this->line("  [DRY] ID={$id} | {$data['name']} | {$data['address']} | cap={$data['capacity']} | {$data['available_hours']}");
            } else {
                try {
                    BikeParking::create($data);
                } catch (\Exception $e) {
                    $this->failed++;

                    return;
                }
            }

            $this->created++;
        } catch (\Exception $e) {
            $this->failed++;
        }
    }

    /**
     * detail.cgi のHTMLから全情報をパース
     */
    private function parseDetailPage(string $html, int $id): ?array
    {
        // 名称
        $name = $this->extractField($html, '名称');
        if (empty($name)) {
            return null;
        }

        // 住所
        $address = $this->extractField($html, '住所') ?? '';

        // 座標（Google Maps リンクから取得）
        $lat = null;
        $lng = null;
        if (preg_match('/q=([\d.-]+),([\d.-]+)/', $html, $m)) {
            $lat = (float) $m[1];
            $lng = (float) $m[2];
        } elseif (preg_match('/lat=([\d.-]+)&lon=([\d.-]+)/', $html, $m)) {
            $lat = (float) $m[1];
            $lng = (float) $m[2];
        }

        if ($lat === null || $lng === null) {
            return null;
        }

        // 各フィールドを抽出
        $fee = $this->extractField($html, '料金') ?? '';
        $capacity = null;
        $capacityRaw = $this->extractField($html, '台数');
        if ($capacityRaw && preg_match('/(\d+)\s*台/', $capacityRaw, $m)) {
            $capacity = (int) $m[1];
        }
        $availableHours = $this->extractField($html, '入出庫時間');
        $station = $this->extractField($html, '最寄り駅');
        $tel = $this->extractField($html, '問い合わせ先');
        $notes = $this->extractField($html, '備考');

        // 都道府県と市区町村
        $prefecture = $this->extractPrefecture($address);
        $city = $this->extractCity($address);

        $data = [
            'name' => $name,
            'address' => $address,
            'latitude' => $lat,
            'longitude' => $lng,
            'prefecture' => $prefecture,
            'city' => $city,
            'parking_type' => 'bike_only',
            'description' => $fee,
            'source_url' => self::DETAIL_URL . '?ID=' . $id,
            'is_active' => true,
        ];

        if ($capacity) {
            $data['capacity'] = $capacity;
        }
        if ($availableHours && $availableHours !== '-') {
            $data['available_hours'] = $availableHours;
            if (preg_match('/24\s*時間/u', $availableHours)) {
                $data['available_24h'] = true;
            }
        }
        if ($tel && $tel !== '-') {
            $data['tel'] = $tel;
        }

        // notes にまとめる
        $notesParts = [];
        if ($station && $station !== '-') {
            $notesParts[] = '最寄り駅: ' . $station;
        }
        if ($notes && $notes !== '-') {
            $notesParts[] = $notes;
        }
        if ($notesParts) {
            $data['notes'] = implode("\n", $notesParts);
        }

        // 料金パース
        $this->parseFee($fee, $data);

        return $data;
    }

    /**
     * 「ラベル： 値<br>」形式からフィールド値を抽出
     */
    private function extractField(string $html, string $label): ?string
    {
        // 「ラベル： 値」のパターン（<br> or </ で終端）
        $pattern = '/' . preg_quote($label, '/') . '[：:]\s*(.+?)(?:<br|<\/)/iu';
        if (preg_match($pattern, $html, $m)) {
            $value = trim(strip_tags($m[1]));

            return $value !== '' ? $value : null;
        }

        return null;
    }

    /**
     * 重複チェック（名前の正規化 + 50m以内の座標チェック）
     */
    private function isDuplicate(string $name, string $address, float $lat, float $lng): bool
    {
        if ($lat == 0 || $lng == 0) {
            return false;
        }

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

        // 座標が50m以内の駐車場があるか
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

    private function parseFee(string $fee, array &$data): void
    {
        if (empty($fee)) {
            return;
        }

        if (preg_match('/無料/', $fee) && !preg_match('/\d+円/', $fee)) {
            $data['is_free'] = true;

            return;
        }

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

        if (preg_match('/24時間[^\d]*(\d[\d,]*)円/', $fee, $m)) {
            $data['price_per_day'] = (int) str_replace(',', '', $m[1]);
        } elseif (preg_match('/最大[^\d]*(\d[\d,]*)円/', $fee, $m)) {
            $data['price_per_day'] = (int) str_replace(',', '', $m[1]);
        }

        if (preg_match('/月[極額]?\s*(\d[\d,]*)円/', $fee, $m)) {
            $data['price_per_month'] = (int) str_replace(',', '', $m[1]);
        }
    }
}
