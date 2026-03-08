<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BikeParking;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;

class ImportJmpsaParkings extends Command
{
    protected $signature = 'parking:import-jmpsa
        {--prefecture= : 都道府県コード(1-47)}
        {--dry-run : 保存せず確認のみ}
        {--skip-geocoding : 座標変換をスキップ（一覧から取得できない場合のみ影響）}
        {--limit= : 各都道府県の取得上限件数}';

    protected $description = 'JMPSAの全国バイク駐車場案内からデータを取得してDBに保存します';

    private const BASE_URL = 'https://www.jmpsa.or.jp';
    private const LIST_API = '/assets/module/maplist.php';
    private const USER_AGENT = 'MotoHub/1.0 (https://www.motohub.jp; bike parking map)';
    private const REQUEST_INTERVAL_US = 1100000; // 1.1秒

    private const PREFECTURES = [
        1 => '北海道', 2 => '青森県', 3 => '岩手県', 4 => '宮城県',
        5 => '秋田県', 6 => '山形県', 7 => '福島県', 8 => '茨城県',
        9 => '栃木県', 10 => '群馬県', 11 => '埼玉県', 12 => '千葉県',
        13 => '東京都', 14 => '神奈川県', 15 => '新潟県', 16 => '富山県',
        17 => '石川県', 18 => '福井県', 19 => '山梨県', 20 => '長野県',
        21 => '岐阜県', 22 => '静岡県', 23 => '愛知県', 24 => '三重県',
        25 => '滋賀県', 26 => '京都府', 27 => '大阪府', 28 => '兵庫県',
        29 => '奈良県', 30 => '和歌山県', 31 => '鳥取県', 32 => '島根県',
        33 => '岡山県', 34 => '広島県', 35 => '山口県', 36 => '徳島県',
        37 => '香川県', 38 => '愛媛県', 39 => '高知県', 40 => '福岡県',
        41 => '佐賀県', 42 => '長崎県', 43 => '熊本県', 44 => '大分県',
        45 => '宮崎県', 46 => '鹿児島県', 47 => '沖縄県',
    ];

    private int $created = 0;
    private int $skipped = 0;
    private int $failed = 0;

    public function handle(): void
    {
        $dryRun = $this->option('dry-run');
        $prefCode = $this->option('prefecture');
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;

        if ($dryRun) {
            $this->info('[DRY RUN] データは保存されません。');
        }

        // 対象都道府県を決定
        if ($prefCode) {
            $code = (int) $prefCode;
            if (!isset(self::PREFECTURES[$code])) {
                $this->error("無効な都道府県コード: {$code}");
                return;
            }
            $targets = [$code => self::PREFECTURES[$code]];
        } else {
            $targets = self::PREFECTURES;
        }

        foreach ($targets as $code => $name) {
            $this->info('');
            $this->info("=== {$name} (area{$code}) ===");
            $this->importPrefecture($code, $name, $dryRun, $limit);
        }

        $this->newLine();
        $this->info("========================================");
        $this->info("完了！ 新規: {$this->created}件 / スキップ: {$this->skipped}件 / 失敗: {$this->failed}件");
    }

    private function importPrefecture(int $code, string $prefName, bool $dryRun, ?int $limit): void
    {
        // まず都道府県ページから総件数を取得
        $totalCount = $this->getTotalCount($code);
        if ($totalCount === null) {
            $this->warn("  {$prefName}: ページ取得失敗。スキップします。");
            return;
        }

        $this->info("  登録数: {$totalCount}件 (時間貸)");

        $maxPages = $limit ? (int) ceil($limit / 10) : (int) ceil($totalCount / 10);
        $imported = 0;

        for ($offset = 0; $offset < $maxPages; $offset++) {
            $items = $this->fetchListPage($code, $offset);

            if (empty($items)) {
                break;
            }

            foreach ($items as $item) {
                if ($limit && $imported >= $limit) {
                    break 2;
                }

                $this->processItem($item, $code, $prefName, $dryRun);
                $imported++;
            }

            usleep(self::REQUEST_INTERVAL_US);
        }

        $this->info("  {$prefName}: {$imported}件処理完了");
    }

    private function getTotalCount(int $code): ?int
    {
        try {
            $response = Http::withHeaders(['User-Agent' => self::USER_AGENT])
                ->get(self::BASE_URL . "/society/parking/area{$code}/");

            if (!$response->successful()) {
                return null;
            }

            // var count=7591; のパターンから件数を取得
            if (preg_match('/var\s+count\s*=\s*(\d+)/', $response->body(), $m)) {
                return (int) $m[1];
            }

            return 0;
        } catch (\Exception $e) {
            $this->error("  ページ取得エラー: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * AJAX APIから一覧データを取得し、HTMLをパースして配列で返す
     */
    private function fetchListPage(int $code, int $offset): array
    {
        try {
            $response = Http::withHeaders(['User-Agent' => self::USER_AGENT])
                ->get(self::BASE_URL . self::LIST_API, [
                    'id' => $code,
                    'types' => 1,
                    'offset' => $offset,
                    'vs' => '1,1,1,0',
                    'sect' => '',
                    'locations' => '',
                    'search' => '',
                ]);

            if (!$response->successful() || empty(trim($response->body()))) {
                return [];
            }

            return $this->parseListHtml($response->body(), $code);
        } catch (\Exception $e) {
            $this->warn("  API取得エラー (offset={$offset}): {$e->getMessage()}");
            return [];
        }
    }

    /**
     * 一覧HTMLをパースして駐車場データの配列を返す
     */
    private function parseListHtml(string $html, int $code): array
    {
        $crawler = new Crawler($html);
        $items = [];

        $crawler->filter('li.p-parking-prefecture-list-item')->each(function (Crawler $node) use (&$items, $code) {
            try {
                $item = [];

                // 駐車場名とリンク
                $titleNode = $node->filter('.p-parking-prefecture-map-ttl a');
                if ($titleNode->count() === 0) {
                    return;
                }
                $item['name'] = trim($titleNode->text());
                // <span class="m-arrow"> を除去
                $item['name'] = preg_replace('/\s*$/', '', $item['name']);

                $href = $titleNode->attr('href');
                $item['source_url'] = self::BASE_URL . $href;

                // 住所
                $addressNode = $node->filter('.p-parking-prefecture-map-txt');
                $item['address'] = $addressNode->count() ? trim($addressNode->text()) : '';

                // 料金
                $item['price_text'] = '';
                $node->filter('.p-parking-prefecture-table-txt-box')->each(function (Crawler $box) use (&$item) {
                    $label = $box->filter('.p-parking-prefecture-table-ttl')->text('');
                    $value = $box->filter('.p-parking-prefecture-table-txt')->text('');
                    if (str_contains($label, '料金')) {
                        $item['price_text'] = trim($value);
                    }
                });

                // Google Maps iframe から緯度経度を取得
                $iframe = $node->filter('iframe');
                if ($iframe->count() > 0) {
                    $src = $iframe->attr('src') ?? '';
                    // q=35.6888090538351,139.693930923711 のパターン
                    if (preg_match('/q=([\d.-]+),([\d.-]+)/', $src, $m)) {
                        $item['latitude'] = (float) $m[1];
                        $item['longitude'] = (float) $m[2];
                    }
                }

                $items[] = $item;
            } catch (\Exception $e) {
                // 個別アイテムのパースエラーはスキップ
            }
        });

        return $items;
    }

    private function processItem(array $item, int $code, string $prefName, bool $dryRun): void
    {
        if (empty($item['name']) || empty($item['address'])) {
            $this->failed++;
            return;
        }

        // 重複チェック（source_url or 名前+住所）
        $exists = BikeParking::where('source_url', $item['source_url'])
            ->orWhere(function ($q) use ($item) {
                $q->where('name', $item['name'])
                    ->where('address', $item['address']);
            })
            ->exists();

        if ($exists) {
            $this->skipped++;
            return;
        }

        // データ整形（住所に都道府県が含まれている場合は二重付与を防止）
        $address = $item['address'];
        if (!str_starts_with($address, $prefName)) {
            $address = $prefName . $address;
        }

        $data = [
            'name' => $item['name'],
            'address' => $address,
            'prefecture' => $prefName,
            'city' => $this->extractCity($item['address']),
            'parking_type' => 'bike_only',
            'source_url' => $item['source_url'],
            'is_active' => true,
        ];

        // 座標
        if (!empty($item['latitude']) && !empty($item['longitude'])) {
            $data['latitude'] = $item['latitude'];
            $data['longitude'] = $item['longitude'];
        }

        // 料金パース
        $this->parsePriceText($item['price_text'] ?? '', $data);

        if ($dryRun) {
            $lat = $data['latitude'] ?? '?';
            $lng = $data['longitude'] ?? '?';
            $this->line("  [DRY] {$data['name']} | {$data['address']} | lat={$lat} lng={$lng}");
        } else {
            try {
                BikeParking::create($data);
            } catch (\Exception $e) {
                $this->warn("  保存失敗: {$data['name']} - {$e->getMessage()}");
                $this->failed++;
                return;
            }
        }

        $this->created++;
    }

    /**
     * 料金テキストからprice_per_hour/day/monthを抽出
     */
    private function parsePriceText(string $text, array &$data): void
    {
        if (empty($text)) {
            return;
        }

        // 「無料」判定
        if (preg_match('/無料/', $text) && !preg_match('/\d+円/', $text)) {
            $data['is_free'] = true;
            return;
        }

        // 時間料金: "30分100円" "1時間200円" "60分100円" "15分142円"
        if (preg_match('/(\d+)分\s*(\d+)円/', $text, $m)) {
            $minutes = (int) $m[1];
            $price = (int) $m[2];
            if ($minutes > 0) {
                $data['price_per_hour'] = (int) round($price * 60 / $minutes);
            }
        } elseif (preg_match('/(\d+)時間\s*(\d+)円/', $text, $m)) {
            $hours = (int) $m[1];
            $price = (int) $m[2];
            if ($hours > 0) {
                $data['price_per_hour'] = (int) round($price / $hours);
            }
        }

        // 日額: "24時間最大800円" "1日1000円"
        if (preg_match('/24時間[^\d]*(\d[\d,]*)円/', $text, $m)) {
            $data['price_per_day'] = (int) str_replace(',', '', $m[1]);
        } elseif (preg_match('/1日[^\d]*(\d[\d,]*)円/', $text, $m)) {
            $data['price_per_day'] = (int) str_replace(',', '', $m[1]);
        } elseif (preg_match('/最大[^\d]*(\d[\d,]*)円/', $text, $m)) {
            $data['price_per_day'] = (int) str_replace(',', '', $m[1]);
        }

        // 月額: "月極12000円" "月額5000円"
        if (preg_match('/月[極額]?\s*(\d[\d,]*)円/', $text, $m)) {
            $data['price_per_month'] = (int) str_replace(',', '', $m[1]);
        }
    }

    /**
     * 住所から市区町村名を抽出
     */
    private function extractCity(string $address): string
    {
        // 「○○市」「○○区」「○○町」「○○村」「○○郡」
        if (preg_match('/([\p{Han}\p{Hiragana}\p{Katakana}]+?[市区町村郡])/u', $address, $m)) {
            return $m[1];
        }

        return '';
    }
}
