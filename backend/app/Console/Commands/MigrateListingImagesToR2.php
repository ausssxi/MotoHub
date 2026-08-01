<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * 在庫画像（storage/app/public/listings/...）をローカルから Cloudflare R2
 * （r2_images ディスク／バケット motohub-images）へ移行する。
 *
 * 安全設計:
 *  - ローカル元ファイルは絶対に削除しない。DB も一切書き換えない（純粋コピー）。
 *  - 冪等: R2 側に同名かつ同サイズのオブジェクトが既にあればスキップ（再実行安全）。
 *  - 350,000ディレクトリ規模のため allFiles で全件一括展開しない。
 *    site → shard 単位で遅延列挙し、shard ごとにファイルを処理する。
 *  - r2_images.endpoint 未設定なら早期エラーで停止（空ディスクへの誤書き込み防止）。
 *
 * ブログ用 blog:migrate-images-to-r2（MigrateImagesToR2）を参考にした在庫画像版。
 */
class MigrateListingImagesToR2 extends Command
{
    protected $signature = 'listings:migrate-images-to-r2
        {--dry-run : 実際にはコピーせず、対象件数・合計サイズ・スキップ予定数だけ集計}
        {--site= : 対象サイトを限定（goobike / bds / webike）}
        {--limit= : 評価するファイル数の上限（動作確認・部分移行用）}
        {--since-hours= : 指定時間以内に更新されたファイルだけを対象にする（日次の差分転送用）}';

    protected $description = '在庫画像（listings/...）をローカルからCloudflare R2（r2_images）へ移行';

    /** 進捗ログを出す間隔（ファイル数） */
    private const LOG_EVERY = 500;

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $siteFilter = $this->option('site');
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;

        // 差分転送用の時間窓（時間）。指定されたら正の整数のみ許可。
        $sinceHoursRaw = $this->option('since-hours');
        $sinceHours = null;
        if ($sinceHoursRaw !== null) {
            if (! is_numeric($sinceHoursRaw) || (int) $sinceHoursRaw <= 0) {
                $this->error('--since-hours は正の整数（時間）で指定してください。');

                return self::FAILURE;
            }
            $sinceHours = (int) $sinceHoursRaw;
        }

        // 指定時のみ、この時刻より古い更新のファイルは対象外にする。
        $cutoff = $sinceHours !== null ? time() - $sinceHours * 3600 : null;

        // 誤って空ディスクへ書かないよう、エンドポイント未設定なら即停止。
        if (empty(config('filesystems.disks.r2_images.endpoint'))) {
            $this->error('r2_images ディスクの endpoint が未設定です。R2_IMAGES_ENDPOINT を設定してください。');

            return self::FAILURE;
        }

        $source = Storage::disk('public');
        $target = Storage::disk('r2_images');

        // listings 配下の site（goobike/bds/webike）を列挙。--site 指定時は限定。
        $sites = collect($source->directories('listings'))
            ->map(fn ($dir) => basename($dir))
            ->when($siteFilter, fn ($c) => $c->filter(fn ($s) => $s === $siteFilter))
            ->values();

        if ($sites->isEmpty()) {
            $this->info($siteFilter
                ? "サイト '{$siteFilter}' の在庫画像は見つかりませんでした。"
                : '移行対象の在庫画像はありません（listings/ が空）。');

            return self::SUCCESS;
        }

        $this->info(($dryRun ? '[DRY RUN] ' : '')
            .'対象サイト: '.$sites->implode(', '));

        $transferred = 0;   // 実転送（dry-run では「転送予定」）
        $skipped = 0;       // 冪等スキップ
        $errors = 0;
        $totalBytes = 0;    // 転送対象の合計サイズ
        $seen = 0;          // 評価したファイル総数（limit 判定用）
        $outOfWindow = 0;   // --since-hours 指定時、更新が古く対象外にしたファイル数
        $reachedLimit = false;

        foreach ($sites as $site) {
            if ($reachedLimit) {
                break;
            }

            $sitePath = "listings/{$site}";

            // shard 単位で遅延列挙。shard ごとに allFiles するので一括展開しない。
            foreach ($source->directories($sitePath) as $shardDir) {
                if ($reachedLimit) {
                    break;
                }

                foreach ($source->allFiles($shardDir) as $file) {
                    if ($limit !== null && $seen >= $limit) {
                        $reachedLimit = true;
                        break;
                    }

                    // 差分転送: 更新が時間窓より古いファイルは対象外（R2へは一切問い合わせない）。
                    // lastModified が取れない場合は取りこぼし防止のため対象に含める側へ倒す。
                    if ($sinceHours !== null) {
                        try {
                            if ($source->lastModified($file) < $cutoff) {
                                $outOfWindow++;

                                continue;
                            }
                        } catch (\Throwable $e) {
                            // 取得失敗は握りつぶし、このファイルは対象に含める。
                        }
                    }

                    $seen++;

                    try {
                        $srcSize = $source->size($file);

                        // 冪等: R2 側に同名かつ同サイズが既にあればスキップ。
                        if ($target->exists($file) && $target->size($file) === $srcSize) {
                            $skipped++;
                        } else {
                            $totalBytes += $srcSize;

                            if (! $dryRun) {
                                $target->put($file, $source->get($file));
                            }
                            $transferred++;
                        }
                    } catch (\Throwable $e) {
                        $errors++;
                        $this->warn("エラー: {$file} - {$e->getMessage()}");
                    }

                    if ($seen % self::LOG_EVERY === 0) {
                        $this->line(sprintf(
                            '  ...評価 %d 件 / %s %d 件 / スキップ %d 件%s',
                            $seen,
                            $dryRun ? '転送予定' : '転送済',
                            $transferred,
                            $skipped,
                            $errors > 0 ? " / エラー {$errors} 件" : ''
                        ));
                    }
                }
            }
        }

        $this->newLine();
        $this->info('==== 集計 ====');
        $this->line('評価ファイル数        : '.number_format($seen));
        $this->line(($dryRun ? '転送予定' : '転送済み').'件数        : '.number_format($transferred));
        $this->line('スキップ（既存同一）  : '.number_format($skipped));
        if ($sinceHours !== null) {
            $this->line('対象外（更新が古い）  : '.number_format($outOfWindow));
        }
        $this->line(($dryRun ? '転送予定' : '転送対象').'合計サイズ  : '.$this->humanBytes($totalBytes));
        if ($errors > 0) {
            $this->warn('エラー件数            : '.number_format($errors));
        }
        if ($reachedLimit) {
            $this->comment("--limit={$limit} に達したため打ち切りました（未評価のファイルが残っています）。");
        }

        if ($dryRun) {
            $this->newLine();
            $this->comment('DRY RUN のため実際の転送は行っていません。');
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
