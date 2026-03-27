<?php

namespace App\Console\Commands;

use App\Models\BlogPost;
use App\Models\BlogSeries;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MigrateImagesToR2 extends Command
{
    protected $signature = 'blog:migrate-images-to-r2 {--dry-run : 実際にはコピーせず対象ファイルのリストのみ表示}';

    protected $description = 'ブログ画像をローカルストレージからCloudflare R2へ移行';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        if (!config('filesystems.disks.r2')) {
            $this->error('R2ディスクが設定されていません。config/filesystems.php に r2 ディスクを追加してください。');
            return self::FAILURE;
        }

        $sourceDisk = Storage::disk('public');
        $targetDisk = Storage::disk('r2');

        // blog/ 以下のすべてのファイルを取得
        $files = $sourceDisk->allFiles('blog');

        if (empty($files)) {
            $this->info('移行対象の画像はありません。');
            return self::SUCCESS;
        }

        $this->info(count($files) . '件のファイルが見つかりました。');

        if ($dryRun) {
            foreach ($files as $file) {
                $this->line("  [DRY RUN] {$file}");
            }
            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar(count($files));
        $errors = 0;

        foreach ($files as $file) {
            try {
                $content = $sourceDisk->get($file);
                $targetDisk->put($file, $content);
                $bar->advance();
            } catch (\Exception $e) {
                $errors++;
                $this->newLine();
                $this->warn("エラー: {$file} - {$e->getMessage()}");
            }
        }

        $bar->finish();
        $this->newLine(2);

        $migrated = count($files) - $errors;
        $this->info("{$migrated}件のファイルをR2に移行しました。");

        if ($errors > 0) {
            $this->warn("{$errors}件のエラーが発生しました。");
        }

        $this->newLine();
        $this->info('移行後の手順:');
        $this->line('1. .env で BLOG_IMAGE_DISK=r2 に変更');
        $this->line('2. .env で BLOG_IMAGE_BASE_URL をR2のURLに変更');
        $this->line('3. php artisan config:cache を実行');

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }
}
