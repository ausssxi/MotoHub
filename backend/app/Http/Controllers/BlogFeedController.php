<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use Illuminate\Http\Response;

class BlogFeedController extends Controller
{
    public function rss(): Response
    {
        $posts = BlogPost::published()
            ->with('author')
            ->orderByDesc('published_at')
            ->limit(20)
            ->get();

        $content = view('blog.feed', compact('posts'))->render();

        return response($content, 200)
            ->header('Content-Type', 'application/rss+xml; charset=UTF-8');
    }
}
