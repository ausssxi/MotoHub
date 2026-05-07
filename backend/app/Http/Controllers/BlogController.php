<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Services\Blog\ShortcodeService;
use App\Services\BlogRelatedPostService;
use App\Services\MarkdownService;
use Illuminate\Http\Request;

class BlogController extends Controller
{
    public function index(Request $request)
    {
        $posts = BlogPost::published()
            ->with(['author', 'tags', 'series'])
            ->orderByDesc('published_at')
            ->paginate(config('blog.per_page', 12));

        return view('blog.index', compact('posts'));
    }

    public function show(string $slug, Request $request, MarkdownService $markdown, ShortcodeService $shortcode, BlogRelatedPostService $relatedService)
    {
        $post = BlogPost::where('slug', $slug)
            ->published()
            ->with(['author', 'tags', 'series'])
            ->firstOrFail();

        // Bot/Crawlerはビューカウントしない
        $userAgent = $request->userAgent() ?? '';
        $isBot = preg_match('/bot|crawl|spider|slurp|facebookexternalhit|semrushbot|ahrefsbot|yandex/i', $userAgent);

        // PVカウント（セッションで30分間の重複除外）
        if (!$isBot) {
            $sessionKey = "blog_viewed_{$post->id}";
            if (!$request->session()->has($sessionKey)) {
                $post->increment('view_count');
                $request->session()->put($sessionKey, true);
                $request->session()->put("{$sessionKey}_at", now()->timestamp);
            } else {
                $viewedAt = $request->session()->get("{$sessionKey}_at", 0);
                if (now()->timestamp - $viewedAt >= 1800) {
                    $post->increment('view_count');
                    $request->session()->put("{$sessionKey}_at", now()->timestamp);
                }
            }
        }

        $html = $markdown->toHtml($post->body);
        $shortcodeResult = $shortcode->processShortcodes($html);
        $html = $shortcodeResult['html'];
        $hasMap = $shortcodeResult['hasMap'] || ($post->latitude && $post->longitude);
        $toc = $markdown->generateToc($post->body);
        $relatedPosts = $relatedService->getRelatedPosts($post);

        // シリーズナビゲーション
        $seriesNav = null;
        if ($post->series_id) {
            $seriesPosts = $post->series->publishedPosts()->get();
            $currentIndex = $seriesPosts->search(fn($p) => $p->id === $post->id);
            $seriesNav = [
                'series' => $post->series,
                'posts' => $seriesPosts,
                'current' => $currentIndex,
                'prev' => $currentIndex > 0 ? $seriesPosts[$currentIndex - 1] : null,
                'next' => $currentIndex < $seriesPosts->count() - 1 ? $seriesPosts[$currentIndex + 1] : null,
                'total' => $seriesPosts->count(),
            ];
        }

        // 前後の記事
        $prevPost = BlogPost::where('status', 'published')->where('id', '<', $post->id)->orderByDesc('id')->first();
        $nextPost = BlogPost::where('status', 'published')->where('id', '>', $post->id)->orderBy('id')->first();

        return view('blog.show', compact('post', 'html', 'hasMap', 'toc', 'relatedPosts', 'seriesNav', 'prevPost', 'nextPost'));
    }

    public function byTag(string $slug)
    {
        $tag = BlogTag::where('slug', $slug)->firstOrFail();

        $posts = $tag->posts()
            ->published()
            ->with(['author', 'tags'])
            ->orderByDesc('published_at')
            ->paginate(config('blog.per_page', 12));

        return view('blog.tag', compact('tag', 'posts'));
    }
}
