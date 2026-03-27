<?php

namespace App\Console\Commands;

use App\Services\CloudflareCacheService;
use Illuminate\Console\Command;

class PurgeBlogCache extends Command
{
    protected $signature = 'blog:purge-cache
                            {--all : ゾーン全体のキャッシュをパージ}
                            {--url=* : パージする特定のURL}';

    protected $description = 'Cloudflareのブログキャッシュをパージ';

    public function handle(CloudflareCacheService $cache): int
    {
        if ($this->option('all')) {
            $this->info('ゾーン全体のキャッシュをパージしています...');
            if ($cache->purgeByPrefix('/blog')) {
                $this->info('キャッシュをパージしました。');
                return self::SUCCESS;
            }
            $this->error('キャッシュパージに失敗しました。Cloudflare設定を確認してください。');
            return self::FAILURE;
        }

        $urls = $this->option('url');
        if (!empty($urls)) {
            $this->info(count($urls) . '件のURLをパージしています...');
            foreach ($urls as $url) {
                $this->line("  {$url}");
            }
            if ($cache->purgeUrls($urls)) {
                $this->info('キャッシュをパージしました。');
                return self::SUCCESS;
            }
            $this->error('キャッシュパージに失敗しました。Cloudflare設定を確認してください。');
            return self::FAILURE;
        }

        $this->warn('オプションを指定してください:');
        $this->line('  --all              ゾーン全体をパージ');
        $this->line('  --url=<URL>        特定URLをパージ（複数指定可）');

        return self::SUCCESS;
    }
}
