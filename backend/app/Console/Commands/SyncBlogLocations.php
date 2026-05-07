<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BlogPost;
use Illuminate\Console\Command;

class SyncBlogLocations extends Command
{
    protected $signature = 'blog:sync-locations {--force : 既にlat/lngが設定済みの記事も上書きする}';
    protected $description = 'ブログ記事本文の[riders-map]ショートコードからlat/lngを抽出してDBに保存します';

    public function handle(): int
    {
        $query = BlogPost::whereNotNull('body')->where('body', 'like', '%[riders-map %');

        if (! $this->option('force')) {
            $query->where(function ($q) {
                $q->whereNull('latitude')->orWhereNull('longitude');
            });
        }

        $posts = $query->get();

        if ($posts->isEmpty()) {
            $this->info('対象の記事はありません。');
            return self::SUCCESS;
        }

        $this->info("対象: {$posts->count()} 記事");

        $updated = 0;
        foreach ($posts as $post) {
            if (! preg_match('/\[riders-map\s+([^\]]+)\]/', $post->body, $matches)) {
                continue;
            }

            preg_match_all('/(\w+)=["\']?([^"\'\s]+)["\']?/', $matches[1], $params, PREG_SET_ORDER);
            $parsed = [];
            foreach ($params as $param) {
                $parsed[$param[1]] = $param[2];
            }

            if (! isset($parsed['lat'], $parsed['lng'])) {
                $this->warn("  スキップ: [{$post->id}] {$post->title} — lat/lngパラメータなし");
                continue;
            }

            $lat = (float) $parsed['lat'];
            $lng = (float) $parsed['lng'];

            // 直接更新（modelイベントを発火させない）
            BlogPost::withoutTimestamps(function () use ($post, $lat, $lng) {
                $post->updateQuietly(['latitude' => $lat, 'longitude' => $lng]);
            });

            $updated++;
            $this->line("  更新: [{$post->id}] {$post->title} → lat={$lat}, lng={$lng}");
        }

        $this->info("完了: {$updated} 記事を更新しました。");

        return self::SUCCESS;
    }
}
