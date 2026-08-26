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
            $this->reportFailure($cache);

            return self::FAILURE;
        }

        $urls = $this->option('url');
        if (! empty($urls)) {
            $this->info(count($urls).'件のURLをパージしています...');
            foreach ($urls as $url) {
                $this->line("  {$url}");
            }
            if ($cache->purgeUrls($urls)) {
                $this->info('キャッシュをパージしました。');

                return self::SUCCESS;
            }
            $this->reportFailure($cache);

            return self::FAILURE;
        }

        $this->warn('オプションを指定してください:');
        $this->line('  --all              ゾーン全体をパージ');
        $this->line('  --url=<URL>        特定URLをパージ（複数指定可）');

        return self::SUCCESS;
    }

    private function reportFailure(CloudflareCacheService $cache): void
    {
        $reason = $cache->getLastError() ?? '不明なエラー';
        $this->error('キャッシュパージに失敗しました: '.$reason);

        if (! $cache->isConfigured()) {
            $this->newLine();
            $this->line('backend/.env に以下を設定してください:');
            $this->line('  CLOUDFLARE_ZONE_ID=<対象ドメインのゾーンID>');
            $this->line('  CLOUDFLARE_API_TOKEN=<Zone.Cache Purge権限のAPIトークン>');
            $this->line('設定後: php artisan config:clear');
        }
    }
}
