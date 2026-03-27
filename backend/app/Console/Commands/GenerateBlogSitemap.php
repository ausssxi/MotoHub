<?php

namespace App\Console\Commands;

use App\Models\BlogPost;
use App\Models\BlogSeries;
use App\Models\BlogTag;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class GenerateBlogSitemap extends Command
{
    protected $signature = 'blog:generate-sitemap';

    protected $description = 'ブログ用サイトマップ(sitemap-blog.xml)を生成';

    public function handle(): int
    {
        $baseUrl = rtrim(config('app.url'), '/');

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        // ブログトップ
        $xml .= $this->urlEntry("{$baseUrl}/blog", now()->toW3cString(), 'daily', '0.8');

        // 公開済み記事
        $posts = BlogPost::published()->orderByDesc('published_at')->get();
        foreach ($posts as $post) {
            $xml .= $this->urlEntry(
                "{$baseUrl}/blog/{$post->slug}",
                $post->updated_at->toW3cString(),
                'weekly',
                '0.7'
            );
        }

        // シリーズ（公開記事があるもの）
        $seriesList = BlogSeries::whereHas('publishedPosts')->get();
        foreach ($seriesList as $series) {
            $xml .= $this->urlEntry(
                "{$baseUrl}/blog/series/{$series->slug}",
                $series->updated_at->toW3cString(),
                'weekly',
                '0.6'
            );
        }

        // タグ（公開記事があるもの）
        $tags = BlogTag::whereHas('posts', fn ($q) => $q->published())->get();
        foreach ($tags as $tag) {
            $xml .= $this->urlEntry(
                "{$baseUrl}/blog/tag/{$tag->slug}",
                $tag->updated_at->toW3cString(),
                'weekly',
                '0.5'
            );
        }

        $xml .= '</urlset>' . "\n";

        $path = config('blog.sitemap.path', 'sitemap-blog.xml');
        Storage::disk('public')->put($path, $xml);

        $this->info("サイトマップを生成しました: {$path}");
        $this->info("記事: {$posts->count()}件, シリーズ: {$seriesList->count()}件, タグ: {$tags->count()}件");

        return self::SUCCESS;
    }

    private function urlEntry(string $loc, string $lastmod, string $changefreq, string $priority): string
    {
        return "  <url>\n" .
               "    <loc>{$loc}</loc>\n" .
               "    <lastmod>{$lastmod}</lastmod>\n" .
               "    <changefreq>{$changefreq}</changefreq>\n" .
               "    <priority>{$priority}</priority>\n" .
               "  </url>\n";
    }
}
