<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\RentalGarage;
use App\Services\RentalGarage\Scrapers\AbstractRentalGarageScraper;
use Illuminate\Console\Command;

final class FetchRentalGarages extends Command
{
    protected $signature = 'rental_garage:fetch
        {--operator= : 対象スクレイパーのkey（未指定は登録済み全社）}
        {--limit= : 1社あたりの取得件数上限（動作確認用）}
        {--dry-run : DBに書かず取得結果を表示するだけ}';

    protected $description = 'レンタルガレージ事業者サイトから物件を取得し rental_garages に取り込む';

    public function handle(): int
    {
        /** @var array<string, class-string<AbstractRentalGarageScraper>> $map */
        $map = config('rental_garages.scrapers', []);

        if (empty($map)) {
            $this->error('スクレイパーが1社も登録されていません（config/rental_garages.php）。');

            return self::FAILURE;
        }

        // --operator: 指定があれば単一社。不正値は有効keyを提示して終了。
        $operatorOption = $this->option('operator');
        if ($operatorOption !== null && $operatorOption !== '') {
            if (! isset($map[$operatorOption])) {
                $this->error("不明なoperator: {$operatorOption}");
                $this->line('有効なkey: '.implode(' / ', array_keys($map)));

                return self::FAILURE;
            }
            $keys = [$operatorOption];
        } else {
            $keys = array_keys($map);
        }

        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;
        $dryRun = (bool) $this->option('dry-run');

        $succeeded = []; // ['label'=>]
        $failed = [];    // ['label'=>]

        foreach ($keys as $key) {
            /** @var AbstractRentalGarageScraper $scraper */
            $scraper = app($map[$key]);
            $label = $scraper->label();
            $this->info("=== [{$label}] 取得開始".($dryRun ? '（dry-run）' : '').' ===');

            $total = 0;
            $new = 0;
            $updated = 0;
            $skipped = 0; // 既存が source=user のためスキップした件数

            try {
                foreach ($scraper->fetch($limit) as $row) {
                    $total++;

                    // 既存レコード（ソフトデリート済みも含めて source_url で照合）。
                    $existing = RentalGarage::withTrashed()
                        ->where('source_url', $row['source_url'])
                        ->first();

                    // ユーザー投稿はスクレイピングで壊さない。
                    if ($existing !== null && $existing->source === 'user') {
                        $skipped++;
                        if ($dryRun) {
                            $this->line("  [skip:user] {$row['name']}");
                        }

                        continue;
                    }

                    if ($dryRun) {
                        $existing !== null ? $updated++ : $new++;
                        $this->line(sprintf(
                            '  %s %s / %s / %s〜%s円 / %s',
                            $existing !== null ? '[update]' : '[new]',
                            $row['name'],
                            $row['garage_type'],
                            $row['monthly_fee_min'] ?? '?',
                            $row['monthly_fee_max'] ?? '?',
                            $row['address'] ?? ''
                        ));

                        continue;
                    }

                    if ($existing !== null) {
                        $data = $row + ['source' => 'official'];
                        // 住所が変わった場合のみ再ジオコーディング対象へ戻す。同じなら座標を保持
                        // （row に latitude/longitude/geocode_status を含めないため既存値は温存される）。
                        if ($existing->address !== $row['address']) {
                            $data['geocode_status'] = 'pending';
                        }
                        if ($existing->trashed()) {
                            $existing->restore();
                        }
                        $existing->update($data);
                        $updated++;
                    } else {
                        // 新規作成: geocode_status は DB既定の 'pending' のまま。
                        RentalGarage::create($row + ['source' => 'official']);
                        $new++;
                    }
                }

                $this->info(sprintf(
                    '[%s] %d件 登録/更新（新規 %d / 更新 %d / スキップ %d）',
                    $label,
                    $total,
                    $new,
                    $updated,
                    $skipped
                ));
                $succeeded[] = ['label' => $label];
            } catch (\Throwable $e) {
                $this->error("[{$label}] エラー: {$e->getMessage()}");
                $failed[] = ['label' => $label];
            }
        }

        $this->printSummary($succeeded, $failed);

        return empty($failed) ? self::SUCCESS : self::FAILURE;
    }

    /**
     * 実行サマリ（成功／失敗の社一覧）を出力（FetchPois::printSummary と同流儀）。
     *
     * @param  array<int, array{label: string}>  $succeeded
     * @param  array<int, array{label: string}>  $failed
     */
    private function printSummary(array $succeeded, array $failed): void
    {
        $this->newLine();
        $this->info('===== 実行サマリ =====');

        $this->info('成功: '.count($succeeded).' 社');
        foreach ($succeeded as $s) {
            $this->line("  ✓ {$s['label']}");
        }

        if (empty($failed)) {
            return;
        }

        $this->warn('失敗: '.count($failed).' 社');
        foreach ($failed as $f) {
            $this->line("  ✗ {$f['label']}");
        }

        $this->newLine();
        $this->warn('失敗分の再実行コマンド例:');
        foreach ($failed as $f) {
            $this->line("  php artisan rental_garage:fetch  # {$f['label']}");
        }
    }
}
