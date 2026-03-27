<?php

namespace App\Http\Controllers;

use App\Models\BlogSeries;

class BlogSeriesController extends Controller
{
    public function show(string $slug)
    {
        $series = BlogSeries::where('slug', $slug)->firstOrFail();

        $posts = $series->publishedPosts()
            ->with(['author', 'tags'])
            ->get();

        return view('blog.series.show', compact('series', 'posts'));
    }
}
