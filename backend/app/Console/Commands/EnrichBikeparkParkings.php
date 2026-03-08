<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BikeParking;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class EnrichBikeparkParkings extends Command
{
    protected $signature = 'parking:enrich-bikepark
        {--limit= : 処理件数の上限}
        {--force : 既に詳細取得済みのデータも再取得}
        {--dry-run : 保存せず確認のみ}';

    protected $description = 'bikepark.in の個別ページから駐車場の詳細情報を取得してDBを更新します';

    private const DETAIL_URL = 'https://bikepark.in/detail.cgi';
    private const USER_AGENT = 'MotoHub/1.0 (https://www.motohub.jp)';
    private const REQUEST_INTERVAL_US = 2000000; // 2秒（個人サイトなので配慮）

    private int $updated = 0;
    private int $skipped = 0;
    private int $failed = 0;

    public function handle(): void
    {
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $force = $this->option('force');
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('[DRY RUN] データは保存されません。');
        }

        // bikepark.inソースの駐車場を取得
        $query = BikeParking::where('source_url', 'like', '%bikepark.in%');

        if (!$force) {
            // capacity が未設定のもののみ対象（詳細未取得の指標）
            $query->whereNull('capacity')->orWhere(function ($q) {
                $q->where('source_url', 'like', '%bikepark.in%')
                    ->where('capacity', 0);
            });
        }

        $query->orderBy('id');

        if ($limit) {
            $query->limit($limit);
        }

        $parkings = $query->get();

        if ($parkings->isEmpty()) {
            $this->info('対象の駐車場がありません。--force で全件再取得できます。');

            return;
        }

        $this->info("対象: {$parkings->count()}件");

        $bar = $this->output->createProgressBar($parkings->count());
        $bar->start();

        foreach ($parkings as $parking) {
            $this->enrichParking($parking, $dryRun);
            $bar->advance();

            usleep(self::REQUEST_INTERVAL_US);
        }

        $bar->finish();
        $this->newLine(2);

        $this->info('========================================');
        $this->info("完了！ 更新: {$this->updated}件 / スキップ: {$this->skipped}件 / 失敗: {$this->failed}件");
    }

    private function enrichParking(BikeParking $parking, bool $dryRun): void
    {
        // source_url から ID を抽出
        if (!preg_match('/ID=(\d+)/', $parking->source_url, $m)) {
            $this->skipped++;

            return;
        }

        $bikeparkId = $m[1];

        try {
            $response = Http::withHeaders([
                'User-Agent' => self::USER_AGENT,
            ])->timeout(15)->get(self::DETAIL_URL, [
                'ID' => $bikeparkId,
            ]);

            if (!$response->successful()) {
                $this->failed++;

                return;
            }

            $body = $response->body();

            // Shift_JIS → UTF-8 変換
            $utf8 = mb_convert_encoding($body, 'UTF-8', 'SJIS-win');

            $data = $this->parseDetailPage($utf8);

            if (empty($data)) {
                $this->skipped++;

                return;
            }

            if ($dryRun) {
                $info = [];
                foreach ($data as $key => $value) {
                    $info[] = "{$key}={$value}";
                }
                $this->newLine();
                $this->line("  [DRY] {$parking->name} | " . implode(' | ', $info));
            } else {
                $parking->update($data);
            }

            $this->updated++;
        } catch (\Exception $e) {
            $this->failed++;
        }
    }

    /**
     * detail.cgi のHTMLから詳細情報をパース
     *
     * HTMLは「ラベル： 値<br>」形式のプレーンテキスト構造
     */
    private function parseDetailPage(string $html): array
    {
        $data = [];

        // 最寄り駅
        if (preg_match('/最寄り駅[：:]\s*(.+?)(?:<br|<\/)/iu', $html, $m)) {
            $station = trim(strip_tags($m[1]));
            if ($station !== '' && $station !== '-') {
                // notes に最寄り駅情報を追加
                $data['notes_station'] = $station;
            }
        }

        // 台数
        if (preg_match('/台数[：:]\s*(\d+)\s*台/iu', $html, $m)) {
            $data['capacity'] = (int) $m[1];
        }

        // 入出庫時間
        if (preg_match('/入出庫時間[：:]\s*(.+?)(?:<br|<\/)/iu', $html, $m)) {
            $hours = trim(strip_tags($m[1]));
            if ($hours !== '' && $hours !== '-') {
                $data['available_hours'] = $hours;

                // 24時間判定
                if (preg_match('/24\s*時間/u', $hours)) {
                    $data['available_24h'] = true;
                }
            }
        }

        // 問い合わせ先
        if (preg_match('/問い合わせ先[：:]\s*(.+?)(?:<br|<\/)/iu', $html, $m)) {
            $tel = trim(strip_tags($m[1]));
            if ($tel !== '' && $tel !== '-') {
                $data['tel'] = $tel;
            }
        }

        // 備考
        if (preg_match('/備考[：:]\s*(.+?)(?:<br|<\/)/iu', $html, $m)) {
            $notes = trim(strip_tags($m[1]));
            if ($notes !== '' && $notes !== '-') {
                $data['bikepark_notes'] = $notes;
            }
        }

        // notes_station と bikepark_notes を notes にまとめる
        $notesParts = [];
        if (isset($data['notes_station'])) {
            $notesParts[] = '最寄り駅: ' . $data['notes_station'];
            unset($data['notes_station']);
        }
        if (isset($data['bikepark_notes'])) {
            $notesParts[] = $data['bikepark_notes'];
            unset($data['bikepark_notes']);
        }
        if ($notesParts) {
            $data['notes'] = implode("\n", $notesParts);
        }

        return $data;
    }
}
