<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogSeries;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BlogSeriesController extends Controller
{
    public function index()
    {
        $seriesList = BlogSeries::withCount('posts')->latest()->paginate(20);

        return view('admin.blog.series.index', compact('seriesList'));
    }

    public function create()
    {
        return view('admin.blog.series.form');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:blog_series,slug',
            'description' => 'nullable|string',
            'eyecatch_image' => 'nullable|image|max:5120',
            'status' => 'required|in:active,completed,archived',
        ]);

        if ($request->hasFile('eyecatch_image')) {
            $validated['eyecatch_image'] = $request->file('eyecatch_image')
                ->store('blog/series', config('blog.image_disk'));
        }

        BlogSeries::create($validated);

        return redirect()->route('admin.blog.series.index')
            ->with('success', 'シリーズを作成しました。');
    }

    public function edit(int $id)
    {
        $series = BlogSeries::findOrFail($id);

        return view('admin.blog.series.form', compact('series'));
    }

    public function update(Request $request, int $id)
    {
        $series = BlogSeries::findOrFail($id);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:blog_series,slug,' . $series->id,
            'description' => 'nullable|string',
            'eyecatch_image' => 'nullable|image|max:5120',
            'status' => 'required|in:active,completed,archived',
        ]);

        if ($request->hasFile('eyecatch_image')) {
            if ($series->eyecatch_image) {
                Storage::disk(config('blog.image_disk'))->delete($series->eyecatch_image);
            }
            $validated['eyecatch_image'] = $request->file('eyecatch_image')
                ->store('blog/series', config('blog.image_disk'));
        }

        $series->update($validated);

        return redirect()->route('admin.blog.series.index')
            ->with('success', 'シリーズを更新しました。');
    }

    public function destroy(int $id)
    {
        $series = BlogSeries::findOrFail($id);
        $series->delete();

        return redirect()->route('admin.blog.series.index')
            ->with('success', 'シリーズを削除しました。');
    }
}
