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

        // 全社合計の集計。
        $totalInactive = 0;    // 開店前で is_active=false
        $totalPrefNull = 0;    // 都道府県が判定できなかった
        $totalSaveFailed = 0;  // 1レコードの保存に失敗した件数
        $totalNeedsReview = 0; // dry-run で必須項目欠落として要確認になった件数
        $totalDeactivated = 0; // 全件再取得で未出現となり非公開化した件数（掲載終了/シグナル喪失）
        $saveFailures = [];    // 表示用: ['source_url'=>, 'msg'=>] を最大10件まで

        foreach ($keys as $key) {
            /** @var AbstractRentalGarageScraper $scraper */
            $scraper = app($map[$key]);
            $label = $scraper->label();
            $this->info("=== [{$label}] 取得開始".($dryRun ? '（dry-run）' : '').' ===');

            $total = 0;
            $new = 0;
            $updated = 0;
            $skipped = 0;     // 既存が source=user のためスキップ
            $inactive = 0;    // 開店前で is_active=false
            $prefNull = 0;    // 都道府県が判定できなかった
            $saveFailed = 0;  // 保存失敗
            $needsReview = 0; // dry-run 必須項目欠落
            $seen = [];       // 今回 yield された source_url（全件突合による非公開化に使う）

            $fetchFailed = false;
            try {
                foreach ($scraper->fetch($limit) as $row) {
                    $total++;
                    if (isset($row['source_url']) && $row['source_url'] !== '') {
                        $seen[] = $row['source_url'];
                    }

                    // 既存レコード（ソフトデリート済みも含めて source_url で照合）。
                    $existing = RentalGarage::withTrashed()
                        ->where('source_url', $row['source_url'] ?? '')
                        ->first();

                    // ユーザー投稿はスクレイピングで壊さない（is_active も触らない・丸ごとスキップ）。
                    if ($existing !== null && $existing->source === 'user') {
                        $skipped++;
                        if ($dryRun) {
                            $this->line("  [skip:user] {$row['name']}");
                        }

                        continue;
                    }

                    // is_active はスクレイパーが付与（開店前=false）。未設定なら公開扱い。
                    $rowActive = (bool) ($row['is_active'] ?? true);
                    if (! $rowActive) {
                        $inactive++;
                    }
                    if (($row['prefecture'] ?? null) === null) {
                        $prefNull++;
                    }

                    if ($dryRun) {
                        // 保存はしないまま必須項目の欠落を検出して警告。
                        $missing = [];
                        if (($row['name'] ?? '') === '') {
                            $missing[] = 'name';
                        }
                        if (($row['address'] ?? '') === '') {
                            $missing[] = 'address';
                        }
                        if (($row['prefecture'] ?? null) === null) {
                            $missing[] = 'prefecture';
                        }
                        if (($row['source_url'] ?? '') === '') {
                            $missing[] = 'source_url';
                        }
                        if ($missing !== []) {
                            $needsReview++;
                        }

                        $existing !== null ? $updated++ : $new++;
                        $this->line(sprintf(
                            ' %s %s %s / %s / %s〜%s円 / %s / %s%s',
                            $missing !== [] ? '!' : ' ',
                            $existing !== null ? '[update]' : '[new]',
                            $row['name'] ?? '',
                            $row['garage_type'] ?? '',
                            $row['monthly_fee_min'] ?? '?',
                            $row['monthly_fee_max'] ?? '?',
                            $row['address'] ?? '',
                            $rowActive ? '公開' : '非公開(開店予定)',
                            $missing !== [] ? '  [欠落: '.implode(',', $missing).']' : ''
                        ));

                        continue;
                    }

                    // 1レコードの保存失敗で社全体を止めない。失敗しても次へ。
                    try {
                        if ($existing !== null) {
                            $data = $row + ['source' => 'official'];
                            // 住所が変わった場合のみ再ジオコーディング対象へ戻す。同じなら座標を保持。
                            // ただしスクレイパーが座標を提供している行（geocode_status を含む＝'source'）は
                            // 権威データなので pending に戻さない。
                            if (! isset($row['geocode_status']) && $existing->address !== ($row['address'] ?? null)) {
                                $data['geocode_status'] = 'pending';
                            }
                            // is_active は「非公開方向」だけ自動化する（全社共通）。
                            // スクレイプが開店前(OPEN予定→is_active=false)を検出したときのみ false へ上書きし、
                            // 通常営業扱い(true)なら更新データから外して既存の is_active を維持する。
                            // 狙い: 手動で非公開にした運用判断を、定期実行が黙って再公開しないため
                            // （誤検知による非公開は復旧できるが、出すべきでないものの公開は取り返しがつかない）。
                            if ($rowActive) {
                                unset($data['is_active']);
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
                    } catch (\Throwable $e) {
                        $saveFailed++;
                        if (count($saveFailures) < 10) {
                            $saveFailures[] = [
                                'source_url' => $row['source_url'] ?? '(no url)',
                                'msg' => $this->summarizeException($e),
                            ];
                        }
                    }
                }
            } catch (\Throwable $e) {
                // 取得処理自体が落ちた（ジェネレータ例外など）＝この社は失敗扱い。
                $this->error("[{$label}] 取得処理エラー: {$e->getMessage()}");
                $fetchFailed = true;
            }

            $totalInactive += $inactive;
            $totalPrefNull += $prefNull;
            $totalSaveFailed += $saveFailed;
            $totalNeedsReview += $needsReview;

            // 成功/失敗判定: 取得0件 または 取得処理が例外 のときのみ失敗。
            // 一部レコードの保存失敗は失敗扱いにしない（件数はサマリに出す）。
            if ($fetchFailed || $total === 0) {
                if (! $fetchFailed) {
                    $this->warn("[{$label}] 取得0件でした");
                }
                $failed[] = ['label' => $label];
            } else {
                // 全件フル取得が成功したときのみ、今回未出現の official 行を非公開化する
                // （バイクシグナル喪失・掲載終了。削除はしない）。--limit / dry-run の部分取得では行わない。
                if (! $dryRun && $limit === null) {
                    $deactivated = RentalGarage::query()
                        ->where('operator', $label)
                        ->where('source', 'official')
                        ->where('is_active', true)
                        ->whereNotIn('source_url', $seen)
                        ->update(['is_active' => false]);
                    if ($deactivated > 0) {
                        $totalDeactivated += $deactivated;
                        $this->warn("[{$label}] 未出現のため非公開化（掲載終了/シグナル喪失）: {$deactivated}件");
                    }
                }

                $this->info(sprintf(
                    '[%s] %d件 登録/更新（新規 %d / 更新 %d / スキップ %d / 非公開 %d / 保存失敗 %d / 都道府県null %d）',
                    $label,
                    $total,
                    $new,
                    $updated,
                    $skipped,
                    $inactive,
                    $saveFailed,
                    $prefNull
                ));
                $succeeded[] = ['label' => $label];
            }
        }

        $this->printSummary($succeeded, $failed, [
            'inactive' => $totalInactive,
            'prefNull' => $totalPrefNull,
            'saveFailed' => $totalSaveFailed,
            'saveFailures' => $saveFailures,
            'needsReview' => $totalNeedsReview,
            'deactivated' => $totalDeactivated,
            'dryRun' => $dryRun,
        ]);

        return empty($failed) ? self::SUCCESS : self::FAILURE;
    }

    /**
     * 例外メッセージを1行・120字までに要約。
     */
    private function summarizeException(\Throwable $e): string
    {
        $msg = preg_replace('/\s+/', ' ', $e->getMessage()) ?? $e->getMessage();

        return mb_strlen($msg) > 120 ? mb_substr($msg, 0, 120).'…' : $msg;
    }

    /**
     * 実行サマリ（成功／失敗の社一覧・各種件数）を出力（FetchPois::printSummary と同流儀）。
     *
     * @param  array<int, array{label: string}>  $succeeded
     * @param  array<int, array{label: string}>  $failed
     * @param  array{inactive:int, prefNull:int, saveFailed:int, saveFailures:array<int, array{source_url:string, msg:string}>, needsReview:int, deactivated:int, dryRun:bool}  $stats
     */
    private function printSummary(array $succeeded, array $failed, array $stats): void
    {
        $this->newLine();
        $this->info('===== 実行サマリ =====');

        $this->info('成功: '.count($succeeded).' 社');
        foreach ($succeeded as $s) {
            $this->line("  ✓ {$s['label']}");
        }

        $this->info("うち開店前で非公開: {$stats['inactive']}件");
        $this->info("未出現のため非公開化（掲載終了/シグナル喪失）: {$stats['deactivated']}件");
        $this->info("都道府県が判定できなかった: {$stats['prefNull']}件");
        $this->info("保存に失敗: {$stats['saveFailed']}件");

        // 保存失敗の内訳（最大10件、11件目以降はまとめる）。
        if (! empty($stats['saveFailures'])) {
            foreach ($stats['saveFailures'] as $sf) {
                $this->line("  - {$sf['source_url']} : {$sf['msg']}");
            }
            $rest = $stats['saveFailed'] - count($stats['saveFailures']);
            if ($rest > 0) {
                $this->line("  … 他 {$rest} 件");
            }
        }

        if ($stats['dryRun']) {
            $this->warn("要確認: {$stats['needsReview']}件（必須項目の欠落）");
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
