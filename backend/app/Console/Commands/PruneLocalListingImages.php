<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * R2 へ転送済みの在庫画像（storage/app/public/listings/...）をローカルから削除する。
 * listings:migrate-images-to-r2（転送）の後始末に相当し、削除処理は転送とは別コマンドに分離する。
 *
 * 安全設計:
 *  - 配信元が r2_images でない（ローカル配信に戻っている）場合は絶対に削除しない。
 *  - R2 に同名かつ同サイズが存在するファイルだけを削除対象にする。R2 未確認は削除しない。
 *  - 更新が新しいファイル（既定 48時間以内）は削除しない。6時の転送（--since-hours=30）より
 *    必ず広い時間窓を取る。lastModified が取れない場合も安全側（削除しない）に倒す。
 *  - 350,000ディレクトリ規模のため allFiles で全件一括展開しない。
 *    site → shard 単位で遅延列挙し、shard ごとにファイルを処理する。
 *  - 触れるのは listings/ 配下のみ。ogp/ shops/ models/ blog/ 等には一切触れない。
 */
class PruneLocalListingImages extends Command
{
    protected $signature = 'listings:prune-local-images
        {--execute : 実際に削除する（未指定は dry-run）}
        {--site= : 対象サイトを限定（goobike / bds / webike）}
        {--older-than-hours=48 : この時間より古い更新のファイルだけを対象にする}
        {--limit= : 評価するファイル数の上限}';

    protected $description = 'R2へ転送済みの在庫画像（listings/...）をローカルから削除（既定 dry-run・--execute で実行）';

    /** 進捗ログを出す間隔（ファイル数） */
    private const LOG_EVERY = 500;

    public function handle(): int
    {
        // 安全装置1: 配信元が r2_images でなければ中止（ローカル配信中に消すと画像が全滅する）。
        $listingDisk = config('filesystems.listing_image_disk');
        if ($listingDisk !== 'r2_images') {
            $this->error("配信元が r2_images ではありません（現在: {$listingDisk}）。中止します。");

            return self::FAILURE;
        }

        // 安全装置2: r2_images の endpoint 未設定なら中止（MigrateListingImagesToR2 と同じ guard）。
        if (empty(config('filesystems.disks.r2_images.endpoint'))) {
            $this->error('r2_images ディスクの endpoint が未設定です。R2_IMAGES_ENDPOINT を設定してください。中止します。');

            return self::FAILURE;
        }

        // 安全装置3: --older-than-hours は正の整数（既定 48）。6時の転送窓（30h）より必ず広く取る。
        $olderThanRaw = $this->option('older-than-hours');
        if (! is_numeric($olderThanRaw) || (int) $olderThanRaw <= 0) {
            $this->error('--older-than-hours は正の整数（時間）で指定してください。中止します。');

            return self::FAILURE;
        }
        $olderThanHours = (int) $olderThanRaw;

        $dryRun = ! (bool) $this->option('execute');
        $siteFilter = $this->option('site');
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;

        // この時刻より新しい更新のファイルは削除しない。
        $cutoff = time() - $olderThanHours * 3600;

        $local = Storage::disk('public');
        $r2 = Storage::disk('r2_images');

        // listings 配下の site（goobike/bds/webike）を列挙。--site 指定時は限定。
        $sites = collect($local->directories('listings'))
            ->map(fn ($dir) => basename($dir))
            ->when($siteFilter, fn ($c) => $c->filter(fn ($s) => $s === $siteFilter))
            ->values();

        if ($sites->isEmpty()) {
            $this->info($siteFilter
                ? "サイト '{$siteFilter}' の在庫画像は見つかりませんでした。"
                : '削除対象の在庫画像はありません（listings/ が空）。');

            return self::SUCCESS;
        }

        $this->info(($dryRun ? '[DRY RUN] ' : '')
            ."対象サイト: {$sites->implode(', ')} / {$olderThanHours}時間より古い更新が対象");

        $deleted = 0;           // 削除（dry-run では「削除予定」）
        $freedBytes = 0;        // 解放（予定）サイズ
        $r2Unconfirmed = 0;     // R2に無い or サイズ不一致で削除しなかった件数
        $tooNew = 0;            // 更新が新しく（または取得失敗で）対象外にした件数
        $emptyDirsDeleted = 0;  // 削除した空の掲載IDディレクトリ数
        $errors = 0;
        $seen = 0;              // 評価したファイル総数（limit 判定用）
        $reachedLimit = false;

        foreach ($sites as $site) {
            if ($reachedLimit) {
                break;
            }

            $sitePath = "listings/{$site}";

            // shard 単位で遅延列挙。shard ごとに allFiles するので一括展開しない。
            foreach ($local->directories($sitePath) as $shardDir) {
                if ($reachedLimit) {
                    break;
                }

                foreach ($local->allFiles($shardDir) as $file) {
                    if ($limit !== null && $seen >= $limit) {
                        $reachedLimit = true;
                        break;
                    }

                    // 更新が新しいファイルは削除しない。lastModified が取れない場合も
                    // 安全側（＝削除対象から外す）に倒してスキップする。
                    try {
                        if ($local->lastModified($file) > $cutoff) {
                            $tooNew++;

                            continue;
                        }
                    } catch (\Throwable $e) {
                        $tooNew++;

                        continue;
                    }

                    $seen++;

                    try {
                        $localSize = $local->size($file);

                        // R2 に同名かつ同サイズがあるときだけ削除対象。無い/違うなら絶対に削除しない。
                        if ($r2->exists($file) && $r2->size($file) === $localSize) {
                            $freedBytes += $localSize;

                            if (! $dryRun) {
                                $local->delete($file);

                                // 空になった掲載IDディレクトリ（shard の直下）だけを掃除する。
                                // shard ディレクトリ・サイトディレクトリは削除しない。
                                $idDir = dirname($file);
                                if ($idDir !== $shardDir
                                    && dirname($idDir) === $shardDir
                                    && $local->files($idDir) === []
                                    && $local->directories($idDir) === []) {
                                    $local->deleteDirectory($idDir);
                                    $emptyDirsDeleted++;
                                }
                            }
                            $deleted++;
                        } else {
                            // R2未確認（未転送 or サイズ不一致）→ 削除しない。
                            $r2Unconfirmed++;
                        }
                    } catch (\Throwable $e) {
                        $errors++;
                        $this->warn("エラー: {$file} - {$e->getMessage()}");
                    }

                    if ($seen % self::LOG_EVERY === 0) {
                        $this->line(sprintf(
                            '  ...評価 %d 件 / %s %d 件 / R2未確認 %d 件%s',
                            $seen,
                            $dryRun ? '削除予定' : '削除',
                            $deleted,
                            $r2Unconfirmed,
                            $errors > 0 ? " / エラー {$errors} 件" : ''
                        ));
                    }
                }
            }
        }

        $this->newLine();
        $this->info('==== 集計 ====');
        $this->line('評価ファイル数          : '.number_format($seen));
        $this->line(($dryRun ? '削除予定' : '削除').'件数            : '.number_format($deleted));
        $this->line(($dryRun ? '解放予定' : '解放').'サイズ          : '.$this->humanBytes($freedBytes));
        $this->line('R2未確認でスキップ      : '.number_format($r2Unconfirmed));
        $this->line('新しすぎてスキップ      : '.number_format($tooNew));
        $this->line('削除した空ディレクトリ数: '.number_format($emptyDirsDeleted));
        if ($errors > 0) {
            $this->warn('エラー件数              : '.number_format($errors));
        }
        if ($reachedLimit) {
            $this->comment("--limit={$limit} に達したため打ち切りました（未評価のファイルが残っています）。");
        }

        // R2未確認が出た＝6時の転送が失敗している兆候の可能性。ログに残す。
        if ($r2Unconfirmed > 0) {
            Log::warning("listings:prune-local-images R2未確認が {$r2Unconfirmed} 件（6時の転送失敗の兆候の可能性）");
        }

        if ($dryRun) {
            $this->newLine();
            $this->comment('DRY RUN のため実際の削除は行っていません。');
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
