<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Models\BlogTag;
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

    public function show(string $slug, MarkdownService $markdown, BlogRelatedPostService $relatedService)
    {
        $post = BlogPost::where('slug', $slug)
            ->published()
            ->with(['author', 'tags', 'series'])
            ->firstOrFail();

        $html = $markdown->toHtml($post->body);
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

        return view('blog.show', compact('post', 'html', 'toc', 'relatedPosts', 'seriesNav'));
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
