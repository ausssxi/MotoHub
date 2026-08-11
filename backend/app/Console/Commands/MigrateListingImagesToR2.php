<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * 画像（storage/app/public/listings/... と models/...）をローカルから Cloudflare R2
 * （r2_images ディスク／バケット motohub-images）へ移行する。
 *
 * --target で対象ツリーを選ぶ:
 *   listings（既定） … 在庫画像 listings/{site}/{shard}/{id}/{n}.{ext}
 *   models            … 車種画像 models/{shard}/{id}/{n}.{ext}
 * 転送・冪等判定・進捗・集計は両者で完全に共通なので、コマンドを分けずに列挙だけ分岐する。
 * （車種画像は8,774ファイル/150MBと小さく、在庫画像用の安全策をそのまま使える）
 *
 * 安全設計:
 *  - ローカル元ファイルは絶対に削除しない。DB も一切書き換えない（純粋コピー）。
 *    切替後に問題が出たら .env を戻すだけで復旧できるよう、ローカルは常に残す。
 *  - 冪等: R2 側に同名かつ同サイズのオブジェクトが既にあればスキップ（再実行安全）。
 *  - 350,000ディレクトリ規模のため allFiles で全件一括展開しない。
 *    listings は site → shard、models は shard 単位で遅延列挙する。
 *  - r2_images.endpoint 未設定なら早期エラーで停止（空ディスクへの誤書き込み防止）。
 *
 * ブログ用 blog:migrate-images-to-r2（MigrateImagesToR2）を参考にした在庫画像版。
 */
class MigrateListingImagesToR2 extends Command
{
    protected $signature = 'listings:migrate-images-to-r2
        {--target=listings : 対象ツリー（listings | models）}
        {--dry-run : 実際にはコピーせず、対象件数・合計サイズ・スキップ予定数だけ集計}
        {--site= : 対象サイトを限定（goobike / bds / webike）。--target=listings のときのみ有効}
        {--limit= : 評価するファイル数の上限（動作確認・部分移行用）}
        {--since-hours= : 指定時間以内に更新されたファイルだけを対象にする（日次の差分転送用）}';

    protected $description = '画像（listings/... または models/...）をローカルからCloudflare R2（r2_images）へ移行';

    /** --target で受け付ける値 */
    private const TARGETS = ['listings', 'models'];

    /** 進捗ログを出す間隔（ファイル数） */
    private const LOG_EVERY = 500;

    public function handle(): int
    {
        // 変数名は $targetTree。$target は下で R2 側のディスクに使っているため衝突させない。
        $targetTree = (string) $this->option('target');
        if (! in_array($targetTree, self::TARGETS, true)) {
            $this->error('--target は '.implode(' か ', self::TARGETS).' を指定してください（指定値: '.$targetTree.'）');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $siteFilter = $this->option('site');
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;

        if ($targetTree === 'models' && $siteFilter !== null) {
            $this->warn('--site は --target=listings 専用のため無視します。');
            $siteFilter = null;
        }

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

        // 走査する shard ディレクトリを列挙する。target ごとに違うのはここだけで、
        // 以降のファイル処理（冪等判定・転送・集計）は完全に共通。
        //   listings: listings/{site}/{shard}
        //   models  : models/{shard}
        $shardDirs = $this->shardDirectories($source, $targetTree, $siteFilter);

        if ($shardDirs === []) {
            $this->info($siteFilter
                ? "サイト '{$siteFilter}' の画像は見つかりませんでした。"
                : "移行対象の画像はありません（{$targetTree}/ が空）。");

            return self::SUCCESS;
        }

        $this->info(($dryRun ? '[DRY RUN] ' : '')
            ."対象: {$targetTree}/ （shard ".number_format(count($shardDirs)).' 件）'
            .($siteFilter !== null ? " / サイト: {$siteFilter}" : ''));

        $transferred = 0;   // 実転送（dry-run では「転送予定」）
        $skipped = 0;       // 冪等スキップ
        $errors = 0;
        $totalBytes = 0;    // 転送対象の合計サイズ
        $seen = 0;          // 評価したファイル総数（limit 判定用）
        $outOfWindow = 0;   // --since-hours 指定時、更新が古く対象外にしたファイル数
        $reachedLimit = false;

        // shard 単位で処理する。shard ごとに allFiles するので全件一括展開はしない。
        foreach ($shardDirs as $shardDir) {
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

    /**
     * 走査対象の shard ディレクトリ一覧を返す。target ごとの違いはここに閉じ込める。
     *   listings: listings/{site}/{shard}（--site で site を限定できる）
     *   models  : models/{shard}
     *
     * 返すのはディレクトリだけ（ファイルは呼び出し側が shard ごとに allFiles する）。
     * 在庫画像は35万ディレクトリ規模だが、shard 階層はその1/1000以下なので一覧化して問題ない。
     *
     * @param  \Illuminate\Contracts\Filesystem\Filesystem  $source
     * @return list<string>
     */
    private function shardDirectories($source, string $targetTree, ?string $siteFilter): array
    {
        if ($targetTree === 'models') {
            return array_values($source->directories('models'));
        }

        $sites = collect($source->directories('listings'))
            ->map(fn ($dir) => basename($dir))
            ->when($siteFilter, fn ($c) => $c->filter(fn ($s) => $s === $siteFilter))
            ->values();

        $dirs = [];
        foreach ($sites as $site) {
            foreach ($source->directories("listings/{$site}") as $shardDir) {
                $dirs[] = $shardDir;
            }
        }

        return $dirs;
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
