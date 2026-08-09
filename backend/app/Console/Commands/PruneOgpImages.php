<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * OGP画像キャッシュ（storage/app/public/ogp/...）の古いファイルを掃除する。
 *
 * OGP画像は DealOgpController / BlogOgpController 等が「ファイルが無ければ生成し、
 * あればそれを返す」遅延生成キャッシュ（generate-if-missing）として作っている。
 * そのため古いキャッシュを消しても次のアクセスで再生成される（画像が出なくなることはない）。
 *
 * 背景: 掃除の仕組みが無く月2GBずつ増え続けていた（実測 3万ファイル・2.4GB）。
 *
 * 安全設計（listings:prune-local-images と同じ作法）:
 *  - 既定は dry-run。--execute を付けたときだけ実際に削除する。
 *  - 触れるのは ogp/ 配下のみ。
 *  - .gitignore などの隠しファイル（basename が「.」始まり）は対象外。
 *  - lastModified が取れないファイルは安全側（削除しない）に倒す。
 */
class PruneOgpImages extends Command
{
    protected $signature = 'ogp:prune
        {--execute : 実際に削除する（未指定は dry-run）}
        {--days=90 : この日数より古い更新のファイルを対象にする}';

    protected $description = 'OGP画像キャッシュ（ogp/...）の古いファイルを削除（既定 dry-run・--execute で実行）。再生成されるので安全';

    /** 対象ディレクトリ（public ディスク基準） */
    private const OGP_DIR = 'ogp';

    /** 進捗ログを出す間隔（ファイル数） */
    private const LOG_EVERY = 2000;

    public function handle(): int
    {
        // --days は正の整数（既定 90）。
        $daysRaw = $this->option('days');
        if (! is_numeric($daysRaw) || (int) $daysRaw <= 0) {
            $this->error('--days は正の整数（日数）で指定してください。中止します。');

            return self::FAILURE;
        }
        $days = (int) $daysRaw;

        $dryRun = ! (bool) $this->option('execute');

        $disk = Storage::disk('public');

        if (! $disk->exists(self::OGP_DIR)) {
            $this->info('OGPディレクトリ（ogp/）が存在しないため、対象はありません。');

            return self::SUCCESS;
        }

        // この時刻より古い更新のファイルを削除対象にする。
        $cutoff = time() - $days * 86400;

        $this->info(($dryRun ? '[DRY RUN] ' : '')
            ."対象: ogp/ 配下 / {$days}日より古い更新のファイル");

        $deleted = 0;       // 削除（dry-run では「削除予定」）
        $freedBytes = 0;    // 解放（予定）サイズ
        $kept = 0;          // 新しすぎる・隠しファイル等で残した件数
        $errors = 0;
        $seen = 0;

        foreach ($disk->allFiles(self::OGP_DIR) as $file) {
            $seen++;

            // .gitignore などの隠しファイル（basename が「.」始まり）は対象外。
            if (str_starts_with(basename($file), '.')) {
                $kept++;

                continue;
            }

            // 更新が新しいファイルは削除しない。lastModified が取れない場合も
            // 安全側（＝削除対象から外す）に倒してスキップする。
            try {
                if ($disk->lastModified($file) >= $cutoff) {
                    $kept++;

                    continue;
                }
            } catch (\Throwable $e) {
                $kept++;

                continue;
            }

            try {
                $size = $disk->size($file);

                if (! $dryRun) {
                    $disk->delete($file);
                }

                $deleted++;
                $freedBytes += $size;
            } catch (\Throwable $e) {
                $errors++;
                $this->warn("エラー: {$file} - {$e->getMessage()}");
            }

            if ($seen % self::LOG_EVERY === 0) {
                $this->line(sprintf(
                    '  ...評価 %s 件 / %s %s 件 / 解放%s %s%s',
                    number_format($seen),
                    $dryRun ? '削除予定' : '削除',
                    number_format($deleted),
                    $dryRun ? '予定' : '',
                    $this->humanBytes($freedBytes),
                    $errors > 0 ? " / エラー {$errors} 件" : ''
                ));
            }
        }

        $this->newLine();
        $this->info('==== 集計 ====');
        $this->line('評価ファイル数    : '.number_format($seen));
        $this->line(($dryRun ? '削除予定' : '削除').'件数      : '.number_format($deleted));
        $this->line(($dryRun ? '解放予定' : '解放').'サイズ    : '.$this->humanBytes($freedBytes));
        $this->line('残したファイル数  : '.number_format($kept));
        if ($errors > 0) {
            $this->warn('エラー件数        : '.number_format($errors));
        }

        if ($dryRun) {
            $this->newLine();
            $this->comment('DRY RUN のため実際の削除は行っていません。--execute で削除します。');
        }

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function humanBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        $n = (float) $bytes;
        while ($n >= 1024 && $i < count($units) - 1) {
            $n /= 1024;
            $i++;
        }

        return sprintf('%.2f %s', $n, $units[$i]);
    }
}
