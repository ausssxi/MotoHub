<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\BikeNews;
use App\Models\BlogPost;
use Illuminate\Http\Response;

final class FeedController extends Controller
{
    /**
     * オリジナルニュース専用RSSフィード
     * /feed/news
     */
    public function news(): Response
    {
        $items = BikeNews::where('source', 'MotoHub')
            ->whereNotNull('published_at')
            ->orderByDesc('published_at')
            ->limit(20)
            ->get();

        $content = view('feed.news', compact('items'))->render();

        return response($content, 200)
            ->header('Content-Type', 'application/rss+xml; charset=UTF-8');
    }

    /**
     * ブログRSSフィード
     * /feed/blog
     */
    public function blog(): Response
    {
        $posts = BlogPost::published()
            ->with('author')
            ->orderByDesc('published_at')
            ->limit(20)
            ->get();

        $content = view('feed.blog', compact('posts'))->render();

        return response($content, 200)
            ->header('Content-Type', 'application/rss+xml; charset=UTF-8');
    }

    /**
     * 統合RSSフィード（ニュース＋ブログ）
     * /feed/original
     */
    public function original(): Response
    {
        $newsItems = BikeNews::where('source', 'MotoHub')
            ->whereNotNull('published_at')
            ->orderByDesc('published_at')
            ->limit(20)
            ->get()
            ->map(fn (BikeNews $item) => [
                'title' => $item->title,
                'link' => route('news.show', $item->id),
                'description' => mb_substr(strip_tags($item->content ?? ''), 0, 200),
                'pubDate' => $item->published_at,
                'author' => 'MotoHub',
                'category' => 'バイク相場',
            ]);

        $blogItems = BlogPost::published()
            ->with('author')
            ->orderByDesc('published_at')
            ->limit(20)
            ->get()
            ->map(fn (BlogPost $post) => [
                'title' => $post->title,
                'link' => url('/blog/' . $post->slug),
                'description' => $post->excerpt
                    ? BlogPost::stripMarkdown($post->excerpt)
                    : \Illuminate\Support\Str::limit(BlogPost::stripMarkdown($post->body), 200),
                'pubDate' => $post->published_at,
                'author' => $post->author?->name ?? 'MotoHub',
                'category' => 'ブログ',
            ]);

        $items = $newsItems->merge($blogItems)
            ->sortByDesc('pubDate')
            ->take(20)
            ->values();

        $content = view('feed.original', compact('items'))->render();

        return response($content, 200)
            ->header('Content-Type', 'application/rss+xml; charset=UTF-8');
    }
}
