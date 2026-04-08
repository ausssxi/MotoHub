<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BikeNews;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class FetchNewsThumbnails extends Command
{
    protected $signature = 'news:fetch-thumbnails';
    protected $description = 'サムネイルがないニュースのOG画像を一括取得';

    public function handle(): void
    {
        $total = BikeNews::whereNull('thumbnail_url')->count();
        $this->info("サムネイル未取得のニュース: {$total} 件");

        if ($total === 0) {
            $this->info('処理するニュースはありません。');
            return;
        }

        $updated = 0;
        $failed = 0;

        BikeNews::whereNull('thumbnail_url')->chunk(100, function ($newsItems) use (&$updated, &$failed) {
            foreach ($newsItems as $news) {
                $ogImage = $this->fetchOgImage($news->url);

                if ($ogImage) {
                    $news->thumbnail_url = $ogImage;
                    $news->save();
                    $updated++;
                    $this->line("  ✓ [{$news->id}] {$ogImage}");
                } else {
                    $failed++;
                    $this->line("  ✗ [{$news->id}] OG画像なし: " . \Illuminate\Support\Str::limit($news->url, 60));
                }

                // レート制限: 1秒待機
                sleep(1);
            }
        });

        $this->info("完了: {$updated} 件取得 / {$failed} 件取得失敗");
    }

    private function fetchOgImage(string $url): ?string
    {
        try {
            $response = Http::timeout(5)
                ->withHeaders(['User-Agent' => 'MotoHub/1.0'])
                ->get($url);

            if ($response->failed()) {
                return null;
            }

            $body = $response->body();
            if (preg_match('/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']/i', $body, $m)) {
                return $m[1];
            }
            if (preg_match('/<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:image["\']/i', $body, $m)) {
                return $m[1];
            }

            return null;
        } catch (\Throwable) {
            return null;
        }
    }
}
