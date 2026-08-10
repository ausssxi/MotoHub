<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Listing;
use App\Models\Shop;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * Webike（ウェビック）由来の取得済み画像ファイルの実体をストレージから削除する単発の是正措置。
 *
 * 背景: 権利者・株式会社リバークレイン（ウェビック）より 2026-08-10 付で「取得済の画像も含めた
 * 掲載の停止」を要請され承諾。表示抑止は済み（Listing::IMAGE_SUPPRESSED_SITE_IDS=[3] /
 * Shop::SUPPRESSED_IMAGE_HOST_KEYWORDS=['webike-cdn']）。本コマンドは残る「画像の実体」を消す。
 *
 * 安全設計:
 *  - 既定は dry-run。--execute が無い限り1バイトも削除しない。
 *  - listings は 'listings/webike' ディレクトリ単位で削除する。webike由来の在庫画像は定義上すべて
 *    この配下にあり、他サイト（goobike/bds）のファイルは混在しない。ディレクトリ文字列が
 *    'listings/webike' と完全一致することを削除直前に検証（定数書き換え事故の防止）。
 *  - shops は対象 shop_id のディレクトリ（shops/{id%100 zero-pad2}/{id}）配下のみを削除。
 *    'shop-user/'（ユーザー投稿画像）は絶対に削除しない。ディレクトリが shop_id から組み立てた
 *    ものと完全一致することを検証してから空ディレクトリを削除。
 *  - DB の値（local_image_paths / local_image_path / image_urls）は変更しない（監査・再検証のため保持）。
 *  - 冪等: ディレクトリ/ファイルが無くてもエラーにせず「対象なし」として計上。
 *  - --target=verify は読み取り専用。--execute があっても絶対に削除しない。
 *
 * ストレージの前提（STEP 1 調査に基づく）:
 *  - listings 画像 … public（storage/app/public/listings/webike/...）と R2（r2_images ディスク・
 *    同一相対パスがオブジェクトキー）の両方に存在しうる → 両ディスクをディレクトリ単位で削除。
 *    R2 は size() が1オブジェクト1API呼び出しになるためサイズ計測はしない（件数のみ）。
 *  - shops 画像    … ローカル（public）のみ。R2 へは未移行（listings:migrate-images-to-r2 は
 *    listings/ だけを対象）→ public のみ削除。
 *  - ogp（在庫用）… public の ogp/deals/v2/{listing_id}.png。DealChartService は価格チャート＋
 *    テキストのみで販売店/在庫写真を一切埋め込まない（DealOgpController も「販売店写真は使わない」）。
 *    ＝在庫OGPに先方画像は含まれないため、--target=all からは外し、--target=ogp 明示時のみ実行（掃除目的）。
 */
class PurgeWebikeImages extends Command
{
    protected $signature = 'webike:purge-images
        {--execute : 実際に削除する（未指定時は dry-run＝既定）}
        {--target=all : all|listings|shops|ogp|verify（all は listings + shops。ogp と verify は明示指定時のみ）}
        {--chunk=500 : DBチャンクサイズ}';

    protected $description = 'Webike由来の取得済み画像ファイルを削除する単発是正（既定 dry-run・--execute で実行）';

    /** 掲載停止対象サイト（sites: 3=Webike）。 */
    private const WEBIKE_SITE_ID = 3;

    /** listings（webike）画像のルートディレクトリ。ここを丸ごと消す。 */
    private const LISTING_WEBIKE_DIR = 'listings/webike';

    /** ユーザー投稿画像の接頭辞（絶対に削除しない）。 */
    private const USER_IMAGE_PREFIX = 'shop-user/';

    /** 在庫用OGPの保存ディレクトリ。 */
    private const OGP_DEAL_DIR = 'ogp/deals/v2';

    /** 進捗ログを出す間隔（レコード件数）。 */
    private const LOG_EVERY = 500;

    /** verify で列挙する残存例の最大件数。 */
    private const VERIFY_SAMPLE_LIMIT = 20;

    public function handle(): int
    {
        $target = (string) $this->option('target');
        if (! in_array($target, ['all', 'listings', 'shops', 'ogp', 'verify'], true)) {
            $this->error("--target は all|listings|shops|ogp|verify のいずれかを指定してください（指定: {$target}）。");

            return self::FAILURE;
        }

        $chunkRaw = $this->option('chunk');
        if (! is_numeric($chunkRaw) || (int) $chunkRaw <= 0) {
            $this->error('--chunk は正の整数で指定してください。');

            return self::FAILURE;
        }
        $chunk = (int) $chunkRaw;

        $execute = (bool) $this->option('execute');

        // R2 が使えるか（listings のみ R2 も対象）。endpoint 未設定ならローカルのみに縮退。
        $r2Enabled = ! empty(config('filesystems.disks.r2_images.endpoint'));

        // verify は読み取り専用。--execute があっても削除しない。
        if ($target === 'verify') {
            $this->info('=== VERIFY（読み取り専用・残存チェック） ===');
            if (! $r2Enabled) {
                $this->warn('r2_images の endpoint が未設定のため、R2 の残存件数チェックはスキップします。');
            }
            $this->newLine();
            $this->verify($chunk, $r2Enabled);

            return self::SUCCESS;
        }

        $this->info($execute ? '=== EXECUTE（実削除） ===' : '=== DRY RUN（削除しません・集計のみ） ===');
        $this->line('対象: '.$target);
        if (! $r2Enabled) {
            $this->warn('r2_images の endpoint が未設定のため、listings は public（ローカル）のみを対象にします。');
        }
        $this->newLine();

        $rows = [];

        if ($target === 'all' || $target === 'listings') {
            $rows = array_merge($rows, $this->purgeListings($execute, $r2Enabled));
        }
        if ($target === 'all' || $target === 'shops') {
            $rows[] = $this->purgeShops($execute, $chunk);
        }
        if ($target === 'ogp') {
            // ogp は在庫OGP（チャート画像）で先方画像を含まないため、--target=all には含めない。
            $rows[] = $this->purgeOgp($execute, $chunk);
        }

        $this->printSummary($rows, $execute);

        return self::SUCCESS;
    }

    /**
     * (A) listings: 'listings/webike' を public / r2_images からディレクトリ単位で削除。
     *
     * @return array<int, array<string, mixed>> サマリ行（ディスク別）
     */
    private function purgeListings(bool $execute, bool $r2Enabled): array
    {
        $this->line('--- listings（'.self::LISTING_WEBIKE_DIR.' をディレクトリ単位削除）---');

        $rows = [];

        // public（ローカル）: 件数＋サイズを計測して削除。
        $rows[] = $this->purgeListingDir(Storage::disk('public'), 'listings/public', $execute, true);

        // r2_images: 件数のみ（size は1オブジェクト1APIで高コストのため計測しない）。
        if ($r2Enabled) {
            $rows[] = $this->purgeListingDir(Storage::disk('r2_images'), 'listings/r2', $execute, false);
        } else {
            $this->warn('  listings/r2: r2_images 未設定のためスキップ。');
            $rows[] = [
                'label' => 'listings/r2', 'listed' => null, 'deleted' => null,
                'remaining' => null, 'skipped' => null, 'errors' => 0, 'bytes' => null, 'note' => 'R2未設定・スキップ',
            ];
        }

        $this->newLine();

        return $rows;
    }

    /**
     * 1ディスクの 'listings/webike' をディレクトリ単位で削除する。
     *
     * @return array<string, mixed>
     */
    private function purgeListingDir(Filesystem $disk, string $label, bool $execute, bool $measureSize): array
    {
        $dir = self::LISTING_WEBIKE_DIR;

        // 一覧（allFiles は再帰。R2 では list 操作のページングで安価）。
        $before = $disk->allFiles($dir);
        $beforeCount = count($before);

        $bytes = null;
        if ($measureSize) {
            $bytes = 0;
            foreach ($before as $file) {
                try {
                    $bytes += (int) $disk->size($file);
                } catch (\Throwable $e) {
                    // サイズ取得失敗は無視（合計の目安のため）。
                }
            }
        }

        if ($beforeCount === 0) {
            $this->line("  {$label}: 対象なし（{$dir} は空 or 不存在）");

            return [
                'label' => $label, 'listed' => 0, 'deleted' => 0, 'remaining' => 0,
                'skipped' => 0, 'errors' => 0, 'bytes' => $measureSize ? 0 : null,
                'note' => $measureSize ? null : 'サイズ計測なし',
            ];
        }

        $deleted = $beforeCount;   // dry-run: 削除予定 = 現在の件数
        $remaining = $beforeCount; // dry-run: 未削除

        if ($execute) {
            // 安全検証: 操作対象ディレクトリが定数と完全一致すること（書き換え事故の防止）。
            $this->assertListingWebikeDir($dir);

            $disk->deleteDirectory($dir);

            $after = $disk->allFiles($dir);
            $remaining = count($after);
            $deleted = $beforeCount - $remaining;
        }

        $this->line(sprintf(
            '  %s: 一覧 %s / %s %s / 残存 %s%s',
            $label,
            number_format($beforeCount),
            $execute ? '削除' : '削除予定',
            number_format($deleted),
            number_format($remaining),
            $measureSize ? ' / サイズ '.$this->humanBytes((int) $bytes) : ' / サイズ計測なし'
        ));
        $this->flushOutput();

        return [
            'label' => $label, 'listed' => $beforeCount, 'deleted' => $deleted, 'remaining' => $remaining,
            'skipped' => 0, 'errors' => 0, 'bytes' => $bytes,
            'note' => $measureSize ? null : 'サイズ計測なし',
        ];
    }

    /**
     * (B) shops（image_url LIKE %webike-cdn%）: 対象店のローカル画像ディレクトリ配下を public から削除。
     * DB に path が無くても過去取得ファイルが残る可能性があるため、ディレクトリ配下を走査する。
     * 全ファイル削除後、空になった shops/{shard}/{id} ディレクトリ自体も削除する。
     *
     * @return array<string, mixed>
     */
    private function purgeShops(bool $execute, int $chunk): array
    {
        $this->line('--- shops（image_url LIKE %webike-cdn%）---');

        $public = Storage::disk('public');
        $s = $this->newStats();

        Shop::query()
            ->where('image_url', 'like', '%webike-cdn%')
            ->select(['id', 'local_image_path'])
            ->chunkById($chunk, function ($shops) use ($execute, $public, &$s): void {
                foreach ($shops as $shop) {
                    $s['scanned']++;

                    // image_syncer.py の規則: shops/{id%100 zero-pad2}/{id}
                    $shard = str_pad((string) ($shop->id % 100), 2, '0', STR_PAD_LEFT);
                    $dir = "shops/{$shard}/{$shop->id}";

                    // 候補ファイル: 店ディレクトリ配下の全ファイル ＋ DB の local_image_path（生値）。
                    $candidates = $public->exists($dir) ? $public->allFiles($dir) : [];

                    $dbPath = $shop->getRawOriginal('local_image_path');
                    if (is_string($dbPath) && $dbPath !== '') {
                        $dbPath = ltrim($dbPath, '/');
                        if (! in_array($dbPath, $candidates, true)) {
                            $candidates[] = $dbPath;
                        }
                    }

                    $allInDir = true; // このループ内の全候補が dir/ 配下だったか（空ディレクトリ削除の可否判定）。

                    foreach ($candidates as $file) {
                        $file = ltrim((string) $file, '/');

                        // ユーザー投稿画像は絶対に消さない。
                        if (str_starts_with($file, self::USER_IMAGE_PREFIX)) {
                            $s['skipped']++;
                            $allInDir = false;
                            $this->warn("  スキップ（shop-user 保護・shop #{$shop->id}）: {$file}");

                            continue;
                        }

                        // 検証: 'shops/' 始まり かつ この店の {dir}/ 配下であること（他店・他用途の巻き込み防止）。
                        if (! str_starts_with($file, 'shops/') || ! str_starts_with($file, $dir.'/')) {
                            $s['skipped']++;
                            $allInDir = false;
                            $this->warn("  スキップ（検証不合格・shop #{$shop->id}）: {$file}");

                            continue;
                        }

                        // shops はローカル（public）のみ（R2 へは未移行）。
                        $this->deleteOne($public, $file, $execute, $s);
                    }

                    // 全ファイルを消し切った後、空になった店ディレクトリ自体も削除。
                    // 削除前に、ディレクトリ文字列が shop_id から組み立てたものと完全一致することを検証。
                    if ($execute && $allInDir && $public->exists($dir)) {
                        $expected = 'shops/'.str_pad((string) ($shop->id % 100), 2, '0', STR_PAD_LEFT).'/'.$shop->id;
                        if ($dir === $expected && count($public->allFiles($dir)) === 0) {
                            $public->deleteDirectory($dir);
                        }
                    }
                }

                if ($s['scanned'] % self::LOG_EVERY === 0) {
                    $this->progressLine('shops', $s);
                }
            });

        $this->progressLine('shops', $s, true);
        $this->newLine();

        return [
            'label' => 'shops', 'listed' => $s['targeted'], 'deleted' => $s['deleted'],
            'remaining' => $s['missing'], 'skipped' => $s['skipped'], 'errors' => $s['errors'],
            'bytes' => $s['bytes'], 'scanned' => $s['scanned'], 'note' => null,
        ];
    }

    /**
     * (C) ogp（--target=ogp 明示時のみ）: site_id=3 の listing に対応する在庫OGP png を public から削除。
     * 在庫OGPはチャート画像で先方画像を含まないため、コンプライアンス目的ではなく純粋なディスク掃除。
     * 遅延再生成される（DealOgpController）。
     *
     * @return array<string, mixed>
     */
    private function purgeOgp(bool $execute, int $chunk): array
    {
        $this->line('--- ogp（在庫OGP・site_id='.self::WEBIKE_SITE_ID.'）※先方画像は含まないため掃除目的 ---');

        $public = Storage::disk('public');
        $s = $this->newStats();

        Listing::query()
            ->where('site_id', self::WEBIKE_SITE_ID)
            ->select(['id'])
            ->chunkById($chunk, function ($listings) use ($execute, $public, &$s): void {
                foreach ($listings as $listing) {
                    $s['scanned']++;

                    $path = self::OGP_DEAL_DIR."/{$listing->id}.png";

                    // 検証: 'ogp/deals/v2/' 配下であること（構築済みだが明示検証）。
                    if (! str_starts_with($path, self::OGP_DEAL_DIR.'/')) {
                        $s['skipped']++;

                        continue;
                    }

                    $this->deleteOne($public, $path, $execute, $s);
                }

                if ($s['scanned'] % self::LOG_EVERY === 0) {
                    $this->progressLine('ogp', $s);
                }
            });

        $this->progressLine('ogp', $s, true);
        $this->newLine();

        return [
            'label' => 'ogp', 'listed' => $s['targeted'], 'deleted' => $s['deleted'],
            'remaining' => $s['missing'], 'skipped' => $s['skipped'], 'errors' => $s['errors'],
            'bytes' => $s['bytes'], 'scanned' => $s['scanned'], 'note' => null,
        ];
    }

    /**
     * --target=verify（読み取り専用）: 削除後などに「残存ゼロ」を数値で確認する。
     */
    private function verify(int $chunk, bool $r2Enabled): void
    {
        $public = Storage::disk('public');

        // (1) listings: DB(site_id=3) の local_image_paths のうち public に実在するものを数える。
        $this->line('--- verify: listings（public 実在チェック）---');
        $existing = 0;
        $scanned = 0;
        $samples = [];
        Listing::query()
            ->where('site_id', self::WEBIKE_SITE_ID)
            ->select(['id', 'local_image_paths'])
            ->chunkById($chunk, function ($listings) use ($public, &$existing, &$scanned, &$samples): void {
                foreach ($listings as $listing) {
                    $scanned++;
                    foreach ($this->decodePaths($listing->getRawOriginal('local_image_paths')) as $path) {
                        $path = ltrim((string) $path, '/');
                        if ($public->exists($path)) {
                            $existing++;
                            if (count($samples) < self::VERIFY_SAMPLE_LIMIT) {
                                $samples[] = "listing #{$listing->id}: {$path}";
                            }
                        }
                    }
                }
                if ($scanned % self::LOG_EVERY === 0) {
                    $this->line('  ...走査 '.number_format($scanned).' 件 / public 残存 '.number_format($existing));
                    $this->flushOutput();
                }
            });
        $this->line('  listings(public): 走査 '.number_format($scanned).' 件 / DB記載パスの public 残存 '.number_format($existing).' 件');

        // R2 は allFiles の件数だけで確認（1件ずつ exists は叩かない）。
        if ($r2Enabled) {
            $r2Count = count(Storage::disk('r2_images')->allFiles(self::LISTING_WEBIKE_DIR));
            $this->line('  listings(r2): '.self::LISTING_WEBIKE_DIR.' の残存オブジェクト数 '.number_format($r2Count).' 件');
        } else {
            $this->line('  listings(r2): r2_images 未設定のためスキップ');
        }
        // public 側のディレクトリ残存も件数で確認。
        $publicDirCount = count($public->allFiles(self::LISTING_WEBIKE_DIR));
        $this->line('  listings(public dir): '.self::LISTING_WEBIKE_DIR.' の残存ファイル数 '.number_format($publicDirCount).' 件');

        if ($samples !== []) {
            $this->newLine();
            $this->warn('  public に残存している例（最大'.self::VERIFY_SAMPLE_LIMIT.'件）:');
            foreach ($samples as $line) {
                $this->line('    - '.$line);
            }
        }
        $this->newLine();

        // (2) shops: 対象店ディレクトリにファイルが残っていないか。
        $this->line('--- verify: shops（店ディレクトリ残存チェック）---');
        $shopScanned = 0;
        $shopsWithFiles = 0;
        $shopFileTotal = 0;
        $shopSamples = [];
        Shop::query()
            ->where('image_url', 'like', '%webike-cdn%')
            ->select(['id'])
            ->chunkById($chunk, function ($shops) use ($public, &$shopScanned, &$shopsWithFiles, &$shopFileTotal, &$shopSamples): void {
                foreach ($shops as $shop) {
                    $shopScanned++;
                    $shard = str_pad((string) ($shop->id % 100), 2, '0', STR_PAD_LEFT);
                    $dir = "shops/{$shard}/{$shop->id}";
                    $files = $public->exists($dir) ? $public->allFiles($dir) : [];
                    $n = count($files);
                    if ($n > 0) {
                        $shopsWithFiles++;
                        $shopFileTotal += $n;
                        if (count($shopSamples) < self::VERIFY_SAMPLE_LIMIT) {
                            $shopSamples[] = "shop #{$shop->id}: {$dir}/（{$n} ファイル）";
                        }
                    }
                }
                if ($shopScanned % self::LOG_EVERY === 0) {
                    $this->line('  ...走査 '.number_format($shopScanned).' 店 / 残存 '.number_format($shopsWithFiles).' 店');
                    $this->flushOutput();
                }
            });
        $this->line('  shops: 走査 '.number_format($shopScanned).' 店 / 画像残存 '.number_format($shopsWithFiles).' 店 / 残存ファイル計 '.number_format($shopFileTotal).' 件');
        if ($shopSamples !== []) {
            $this->warn('  残存している店の例（最大'.self::VERIFY_SAMPLE_LIMIT.'件）:');
            foreach ($shopSamples as $line) {
                $this->line('    - '.$line);
            }
        }

        $this->newLine();
        $allClear = ($existing === 0) && ($publicDirCount === 0) && ($shopsWithFiles === 0)
            && (! $r2Enabled || ($r2Count ?? 0) === 0);
        if ($allClear) {
            $this->info('✅ 公開URL観点での残存はありません（listings public/r2・shops いずれも 0）。');
        } else {
            $this->warn('⚠️ 残存があります。上記の件数を確認してください。');
        }
    }

    /**
     * 1ファイルを指定ディスクから削除（冪等・dry-run 対応）。統計を更新する。
     *
     * @param  array<string, int>  $s
     */
    private function deleteOne(Filesystem $disk, string $path, bool $execute, array &$s): void
    {
        $s['targeted']++;

        try {
            if (! $disk->exists($path)) {
                $s['missing']++;

                return;
            }

            $size = (int) $disk->size($path);
            $s['bytes'] += $size;

            if ($execute) {
                $disk->delete($path);
            }
            $s['deleted']++;
        } catch (\Throwable $e) {
            // 取得/削除失敗は握りつぶさず警告に計上（冪等性は exists で担保済み）。
            $s['errors']++;
            $this->warn("  エラー: {$path} - {$e->getMessage()}");
        }
    }

    /**
     * listings 削除対象ディレクトリが定数と完全一致することを保証する（安全検証）。
     */
    private function assertListingWebikeDir(string $dir): void
    {
        if ($dir !== self::LISTING_WEBIKE_DIR) {
            throw new \RuntimeException("安全検証失敗: listings 削除対象ディレクトリが不正です（{$dir}）。処理を中止します。");
        }
    }

    /**
     * @param  ?string  $raw  DBの生JSON文字列（getRawOriginal）
     * @return array<int, string>
     */
    private function decodePaths(?string $raw): array
    {
        if (! is_string($raw) || $raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? array_values(array_filter($decoded, 'is_string')) : [];
    }

    /** @return array<string, int> */
    private function newStats(): array
    {
        return [
            'scanned' => 0,   // 走査したDBレコード数
            'targeted' => 0,  // 検証を通過し削除対象にしたファイル数
            'deleted' => 0,   // 実在し削除した（dry-run では削除予定）
            'missing' => 0,   // 既に無い
            'skipped' => 0,   // 検証不合格 / 保護
            'errors' => 0,    // 例外
            'bytes' => 0,     // 削除（予定）ファイルの合計サイズ
        ];
    }

    /**
     * @param  array<string, int>  $s
     */
    private function progressLine(string $label, array $s, bool $final = false): void
    {
        $this->line(sprintf(
            '  %s%s: 走査 %s / 対象 %s / 削除 %s / 既に無い %s / スキップ %s%s / 合計 %s',
            $final ? '[完了] ' : '...',
            $label,
            number_format($s['scanned']),
            number_format($s['targeted']),
            number_format($s['deleted']),
            number_format($s['missing']),
            number_format($s['skipped']),
            $s['errors'] > 0 ? ' / エラー '.number_format($s['errors']) : '',
            $this->humanBytes($s['bytes'])
        ));

        $this->flushOutput();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function printSummary(array $rows, bool $execute): void
    {
        $this->newLine();
        $this->info('==== サマリ（'.($execute ? 'EXECUTE' : 'DRY RUN').'）====');

        $fmt = static fn ($v): string => $v === null ? '-' : number_format((int) $v);

        $tableRows = [];
        $totBytes = 0;
        foreach ($rows as $r) {
            $bytesCell = $r['bytes'] === null
                ? ($r['note'] ?? 'サイズ計測なし')
                : $this->humanBytes((int) $r['bytes']);
            if ($r['bytes'] !== null) {
                $totBytes += (int) $r['bytes'];
            }

            $tableRows[] = [
                $r['label'],
                $fmt($r['scanned'] ?? null),
                $fmt($r['listed'] ?? null),
                $fmt($r['deleted'] ?? null),
                $fmt($r['remaining'] ?? null),
                $fmt($r['skipped'] ?? null),
                ($r['errors'] ?? 0) > 0 ? number_format((int) $r['errors']) : '-',
                $bytesCell,
            ];
        }

        $this->table(
            ['対象', '走査', '一覧', ($execute ? '削除' : '削除予定'), '残存/既に無い', 'スキップ', 'エラー', '合計サイズ'],
            $tableRows
        );
        $this->line('削減（予定）合計サイズ: '.$this->humanBytes($totBytes).'（listings/r2 はサイズ計測対象外）');

        if (! $execute) {
            $this->newLine();
            $this->comment('DRY RUN のため実際の削除は行っていません。--execute で実削除します。');
        }
    }

    /**
     * PHP はパイプ出力を約8KBバッファするため、進捗を可視化できるよう明示フラッシュする。
     */
    private function flushOutput(): void
    {
        if (ob_get_level() > 0) {
            @ob_flush();
        }
        @flush();
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
