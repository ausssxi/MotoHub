<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BikeParking;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;

class EnrichJmpsaParkings extends Command
{
    protected $signature = 'parking:enrich-jmpsa
        {--prefecture= : 都道府県コード(1-47)。指定なしで全件}
        {--limit= : 処理件数の上限}
        {--force : 既に詳細取得済みのデータも再取得}
        {--dry-run : 保存せず確認のみ}';

    protected $description = 'JMPSAの個別ページから駐車場の詳細情報を取得してDBを更新します';

    private const BASE_URL = 'https://www.jmpsa.or.jp';
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

    private int $updated = 0;
    private int $skipped = 0;
    private int $failed = 0;

    public function handle(): void
    {
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $prefCode = $this->option('prefecture');

        if ($dryRun) {
            $this->info('[DRY RUN] データは保存されません。');
        }

        // 対象レコードを取得
        $query = BikeParking::whereNotNull('source_url')
            ->where('source_url', 'like', '%jmpsa.or.jp%');

        if ($prefCode) {
            $code = (int) $prefCode;
            if (!isset(self::PREFECTURES[$code])) {
                $this->error("無効な都道府県コード: {$code}");
                return;
            }
            $prefName = self::PREFECTURES[$code];
            $query->where('prefecture', $prefName);
            $this->info("対象: {$prefName}");
        }

        if (!$force) {
            // tel が null のもの（まだ詳細取得していないもの）のみ対象
            $query->whereNull('tel')->whereNull('management_company');
        }

        if ($limit) {
            $query->limit($limit);
        }

        $parkings = $query->get();
        $total = $parkings->count();
        $this->info("対象件数: {$total}件");

        if ($total === 0) {
            $this->info('処理対象なし。終了します。');
            return;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        foreach ($parkings as $parking) {
            $this->enrichParking($parking, $dryRun);
            $bar->advance();
            usleep(self::REQUEST_INTERVAL_US);
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("========================================");
        $this->info("完了！ 更新: {$this->updated}件 / スキップ: {$this->skipped}件 / 失敗: {$this->failed}件");
    }

    private function enrichParking(BikeParking $parking, bool $dryRun): void
    {
        $url = $parking->source_url;

        try {
            $response = Http::withHeaders(['User-Agent' => self::USER_AGENT])
                ->timeout(15)
                ->get($url);

            if (!$response->successful()) {
                $this->newLine();
                $this->warn("  HTTP {$response->status()}: {$parking->name} ({$url})");
                $this->failed++;
                return;
            }

            $data = $this->parseDetailPage($response->body());

            if (empty($data)) {
                $this->skipped++;
                return;
            }

            if ($dryRun) {
                $this->newLine();
                $this->line("  [DRY] {$parking->name}");
                foreach ($data as $key => $value) {
                    $this->line("    {$key}: {$value}");
                }
                $this->updated++;
                return;
            }

            $parking->update($data);
            $this->updated++;
        } catch (\Exception $e) {
            $this->newLine();
            $this->warn("  エラー: {$parking->name} - {$e->getMessage()}");
            $this->failed++;
        }
    }

    /**
     * 個別ページのHTMLから詳細情報をパースする
     * 構造: <table class="p-parking-detail-table"> の中に
     *        <tr><th>ラベル</th><td>値</td></tr>
     */
    private function parseDetailPage(string $html): array
    {
        $crawler = new Crawler($html);
        $data = [];

        $table = $crawler->filter('table.p-parking-detail-table');
        if ($table->count() === 0) {
            return [];
        }

        // th → td のペアを読み取る
        $fields = [];
        $table->filter('tr.p-parking-detail-table-tr')->each(function (Crawler $row) use (&$fields) {
            $th = $row->filter('th.p-parking-detail-table-ttl');
            $td = $row->filter('td.p-parking-detail-table-txt');
            if ($th->count() > 0 && $td->count() > 0) {
                $label = trim($th->text());
                $value = trim($td->text());
                $fields[$label] = $value;
            }
        });

        // TEL（<a href="tel:..."> からテキスト取得）
        if (isset($fields['TEL']) && !empty($fields['TEL'])) {
            $data['tel'] = $fields['TEL'];
        }

        // 駐車場形態
        if (isset($fields['駐車場形態']) && !empty($fields['駐車場形態'])) {
            $data['parking_form'] = $fields['駐車場形態'];
        }

        // 定休日
        if (isset($fields['定休日']) && !empty($fields['定休日'])) {
            $data['closed_days'] = $fields['定休日'];
        }

        // 利用可能時間
        if (isset($fields['利用可能時間']) && !empty($fields['利用可能時間'])) {
            $data['available_hours'] = $fields['利用可能時間'];
            // 24時間判定
            if (preg_match('/24\s*時間|00.*24|0:00.*24:00/', $fields['利用可能時間'])) {
                $data['available_24h'] = true;
            }
        }

        // 料金（時間貸）
        if (isset($fields['料金（時間貸）']) && !empty($fields['料金（時間貸）'])) {
            $data['price_detail'] = $fields['料金（時間貸）'];
            // 既存の price_per_hour/day 更新も試みる
            $this->parsePriceToNumeric($fields['料金（時間貸）'], $data);
        }

        // 料金（月極）— price_detail に追記
        if (isset($fields['料金（月極）']) && !empty($fields['料金（月極）'])) {
            $monthlyText = $fields['料金（月極）'];
            if (isset($data['price_detail'])) {
                $data['price_detail'] .= ' ／ 月極: ' . $monthlyText;
            } else {
                $data['price_detail'] = '月極: ' . $monthlyText;
            }
            // 月極価格を数値化
            if (preg_match('/([\d,]+)\s*円/', $monthlyText, $m)) {
                $data['price_per_month'] = (int) str_replace(',', '', $m[1]);
            }
        }

        // 収容台数
        if (isset($fields['収容台数']) && !empty($fields['収容台数'])) {
            if (preg_match('/(\d+)\s*台/', $fields['収容台数'], $m)) {
                $data['capacity'] = (int) $m[1];
            }
        }

        // 車両制限
        if (isset($fields['車両制限']) && !empty($fields['車両制限'])) {
            $data['vehicle_restriction'] = $fields['車両制限'];
        }

        // 備考
        if (isset($fields['備考']) && !empty($fields['備考'])) {
            $data['notes'] = $fields['備考'];
        }

        // 管理会社
        if (isset($fields['管理会社']) && !empty($fields['管理会社'])) {
            $data['management_company'] = $fields['管理会社'];
        }

        // 最終更新日
        if (isset($fields['最終更新日']) && !empty($fields['最終更新日'])) {
            $date = $this->parseJapaneseDate($fields['最終更新日']);
            if ($date) {
                $data['jmpsa_updated_at'] = $date;
            }
        }

        return $data;
    }

    /**
     * 料金テキストから数値を抽出して price_per_hour / price_per_day にセット
     */
    private function parsePriceToNumeric(string $text, array &$data): void
    {
        // 「無料」判定
        if (preg_match('/無料/', $text) && !preg_match('/\d+円/', $text)) {
            $data['is_free'] = true;
            return;
        }

        // 時間料金: "30分100円" "1時間200円" "60分100円"
        if (preg_match('/(\d+)分\s*(\d[\d,]*)円/', $text, $m)) {
            $minutes = (int) $m[1];
            $price = (int) str_replace(',', '', $m[2]);
            if ($minutes > 0) {
                $data['price_per_hour'] = (int) round($price * 60 / $minutes);
            }
        } elseif (preg_match('/(\d+)時間\s*(\d[\d,]*)円/', $text, $m)) {
            $hours = (int) $m[1];
            $price = (int) str_replace(',', '', $m[2]);
            if ($hours > 0) {
                $data['price_per_hour'] = (int) round($price / $hours);
            }
        }

        // 日額/最大: "24時間最大800円" "1日1000円" "最大1,200円"
        if (preg_match('/24時間[^\d]*(\d[\d,]*)円/', $text, $m)) {
            $data['price_per_day'] = (int) str_replace(',', '', $m[1]);
        } elseif (preg_match('/1日[^\d]*(\d[\d,]*)円/', $text, $m)) {
            $data['price_per_day'] = (int) str_replace(',', '', $m[1]);
        } elseif (preg_match('/最大[^\d]*(\d[\d,]*)円/', $text, $m)) {
            $data['price_per_day'] = (int) str_replace(',', '', $m[1]);
        }
    }

    /**
     * "2021年6月15日" → "2021-06-15"
     */
    private function parseJapaneseDate(string $text): ?string
    {
        if (preg_match('/(\d{4})年(\d{1,2})月(\d{1,2})日/', $text, $m)) {
            return sprintf('%04d-%02d-%02d', (int) $m[1], (int) $m[2], (int) $m[3]);
        }
        return null;
    }
}
