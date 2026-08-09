<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Listing;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * R2 へ転送済みの「売却済み在庫」画像（storage/app/public/listings/...）をローカルから削除する。
 * listings:migrate-images-to-r2（転送）の後始末に相当し、削除処理は転送とは別コマンドに分離する。
 *
 * ■ なぜ「売却済み在庫」だけを対象にするのか（2026-08-07 の実測に基づく設計）
 *   scraper/common/pipelines.py は os.path.exists(filepath) でダウンロード要否を判定するため、
 *   稼働在庫（is_sold_out=0）のローカル画像を消すと、次回クロールで取得元サイトから即座に
 *   再取得が走ってしまう。実際 8/7 夜に全在庫のローカル画像を削除したところ、翌朝までに
 *   約112,000件の再取得が発生した（＝削除が無意味になるうえ、取得元サイトへ不要な負荷）。
 *   一方、売却済み在庫（is_sold_out=1）はクロール対象から外れるため再取得が起きず、
 *   売却済み画像 約409,000枚（約14GB）は再取得ゼロで恒久的に削減できた。
 *   → 本コマンドは DB を「is_sold_out=1」で絞った listing だけを起点に処理し、稼働在庫の
 *     ファイルには決して触れない。
 *
 * 安全設計:
 *  - 配信元が r2_images でない（ローカル配信に戻っている）場合は絶対に削除しない。
 *  - 起点は is_sold_out=1 の listing のみ。稼働在庫（is_sold_out=0）は SQL の時点で除外。
 *  - 売却直後の再出品での取りこぼしを避けるため、last_seen_at が --days 日より前のものだけ。
 *  - R2 に同名かつ同サイズが存在するファイルだけを削除対象にする。R2 未確認は削除しない。
 *  - 触れるのは各 listing の local_image_paths に記録されたファイルのみ。
 */
class PruneLocalListingImages extends Command
{
    protected $signature = 'listings:prune-local-images
        {--execute : 実際に削除する（未指定は dry-run）}
        {--days=14 : 売却（last_seen_at）からこの日数より前の在庫だけを対象にする}
        {--site= : 対象サイトを限定（goobike / bds / webike）}
        {--limit= : 評価する売却済み在庫の件数上限}';

    protected $description = 'R2へ転送済みの「売却済み」在庫画像をローカルから削除（既定 dry-run・--execute で実行）。稼働在庫は対象外';

    /** 進捗ログを出す間隔（listing 件数） */
    private const LOG_EVERY = 500;

    /** DB からの取り出し単位 */
    private const CHUNK = 500;

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

        // 安全装置3: --days は正の整数（既定 14）。売却直後の再出品を避けるため少し寝かせる。
        $daysRaw = $this->option('days');
        if (! is_numeric($daysRaw) || (int) $daysRaw <= 0) {
            $this->error('--days は正の整数（日数）で指定してください。中止します。');

            return self::FAILURE;
        }
        $days = (int) $daysRaw;

        $dryRun = ! (bool) $this->option('execute');
        $siteFilter = $this->option('site');
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;

        // last_seen_at がこの時刻より前の売却済み在庫だけを対象にする。
        $cutoff = now()->subDays($days);

        $local = Storage::disk('public');
        $r2 = Storage::disk('r2_images');

        // ★ 起点は「売却済み在庫」のみ。is_sold_out=0（稼働在庫）はこの where で除外され、
        //   以降のファイル操作には一切載らない（＝稼働在庫の画像は絶対に削除されない）。
        $query = Listing::query()
            ->where('is_sold_out', true)
            ->whereNotNull('last_seen_at')
            ->where('last_seen_at', '<', $cutoff)
            ->whereNotNull('local_image_paths');

        $sitePrefix = null;
        if ($siteFilter !== null && $siteFilter !== '') {
            // local_image_paths は "listings/{site}/{shard}/{id}/{n}.jpg" 形式で保存されている。
            $sitePrefix = "listings/{$siteFilter}/";
        }

        $this->info(($dryRun ? '[DRY RUN] ' : '')
            ."対象: 売却済み(is_sold_out=1) かつ last_seen_at < {$cutoff->toDateTimeString()}"
            .($siteFilter ? " / サイト {$siteFilter}" : ''));

        $seen = 0;              // 評価した売却済み listing 数
        $filesDeleted = 0;      // 削除（dry-run では「削除予定」）ファイル数
        $freedBytes = 0;        // 解放（予定）サイズ
        $r2Unconfirmed = 0;     // R2に無い or サイズ不一致で削除しなかった件数
        $missingLocal = 0;      // ローカルに既に無かった件数
        $emptyDirsDeleted = 0;  // 削除した空の掲載IDディレクトリ数
        $errors = 0;
        $reachedLimit = false;

        $query->orderBy('id')->chunkById(self::CHUNK, function ($listings) use (
            $local, $r2, $dryRun, $sitePrefix, $limit,
            &$seen, &$filesDeleted, &$freedBytes, &$r2Unconfirmed,
            &$missingLocal, &$emptyDirsDeleted, &$errors, &$reachedLimit
        ) {
            foreach ($listings as $listing) {
                if ($limit !== null && $seen >= $limit) {
                    $reachedLimit = true;

                    return false; // これ以上チャンクを進めない
                }

                $paths = $listing->local_image_paths;
                if (! is_array($paths) || $paths === []) {
                    continue;
                }

                // --site 指定時は当該サイトのパスだけに絞る。
                if ($sitePrefix !== null) {
                    $paths = array_values(array_filter(
                        $paths,
                        fn ($p) => is_string($p) && str_starts_with($p, $sitePrefix)
                    ));
                    if ($paths === []) {
                        continue;
                    }
                }

                $seen++;
                $idDirs = [];

                foreach ($paths as $file) {
                    if (! is_string($file) || $file === '') {
                        continue;
                    }

                    try {
                        if (! $local->exists($file)) {
                            $missingLocal++;

                            continue;
                        }

                        $localSize = $local->size($file);

                        // R2 に同名かつ同サイズがあるときだけ削除対象。無い/違うなら絶対に削除しない。
                        if ($r2->exists($file) && $r2->size($file) === $localSize) {
                            $freedBytes += $localSize;

                            if (! $dryRun) {
                                $local->delete($file);
                                $idDirs[dirname($file)] = true;
                            }
                            $filesDeleted++;
                        } else {
                            $r2Unconfirmed++;
                        }
                    } catch (\Throwable $e) {
                        $errors++;
                        $this->warn("エラー: {$file} - {$e->getMessage()}");
                    }
                }

                // 空になった掲載IDディレクトリだけを掃除する（実削除時のみ）。
                if (! $dryRun) {
                    foreach (array_keys($idDirs) as $idDir) {
                        try {
                            if ($local->files($idDir) === [] && $local->directories($idDir) === []) {
                                $local->deleteDirectory($idDir);
                                $emptyDirsDeleted++;
                            }
                        } catch (\Throwable $e) {
                            // ディレクトリ掃除の失敗は致命的でないので記録のみ。
                            $this->warn("空ディレクトリ削除失敗: {$idDir} - {$e->getMessage()}");
                        }
                    }
                }

                if ($seen % self::LOG_EVERY === 0) {
                    $this->line(sprintf(
                        '  ...売却済み在庫 %s 件評価 / %s %s ファイル / R2未確認 %s%s',
                        number_format($seen),
                        $dryRun ? '削除予定' : '削除',
                        number_format($filesDeleted),
                        number_format($r2Unconfirmed),
                        $errors > 0 ? " / エラー {$errors} 件" : ''
                    ));
                }
            }

            return true;
        });

        $this->newLine();
        $this->info('==== 集計 ====');
        $this->line('評価した売却済み在庫  : '.number_format($seen));
        $this->line(($dryRun ? '削除予定' : '削除').'ファイル数    : '.number_format($filesDeleted));
        $this->line(($dryRun ? '解放予定' : '解放').'サイズ        : '.$this->humanBytes($freedBytes));
        $this->line('R2未確認でスキップ    : '.number_format($r2Unconfirmed));
        $this->line('ローカルに既に無し    : '.number_format($missingLocal));
        $this->line('削除した空ディレクトリ: '.number_format($emptyDirsDeleted));
        if ($errors > 0) {
            $this->warn('エラー件数            : '.number_format($errors));
        }
        if ($reachedLimit) {
            $this->comment("--limit={$limit} に達したため打ち切りました（未評価の売却済み在庫が残っています）。");
        }

        // R2未確認が出た＝6時の転送が失敗している兆候の可能性。ログに残す。
        if ($r2Unconfirmed > 0) {
            Log::warning("listings:prune-local-images R2未確認が {$r2Unconfirmed} 件（6時の転送失敗の兆候の可能性）");
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
