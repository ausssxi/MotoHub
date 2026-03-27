<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogTag;
use Illuminate\Http\Request;

class BlogTagController extends Controller
{
    public function index()
    {
        $tags = BlogTag::withCount('posts')->orderBy('name')->paginate(50);

        return view('admin.blog.tags.index', compact('tags'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:blog_tags,name',
            'slug' => 'nullable|string|max:100|unique:blog_tags,slug',
            'description' => 'nullable|string',
        ]);

        BlogTag::create($validated);

        return redirect()->route('admin.blog.tags.index')
            ->with('success', 'タグを作成しました。');
    }

    public function update(Request $request, int $id)
    {
        $tag = BlogTag::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:blog_tags,name,' . $tag->id,
            'slug' => 'nullable|string|max:100|unique:blog_tags,slug,' . $tag->id,
            'description' => 'nullable|string',
        ]);

        $tag->update($validated);

        return redirect()->route('admin.blog.tags.index')
            ->with('success', 'タグを更新しました。');
    }

    public function destroy(int $id)
    {
        $tag = BlogTag::findOrFail($id);
        $tag->posts()->detach();
        $tag->delete();

        return redirect()->route('admin.blog.tags.index')
            ->with('success', 'タグを削除しました。');
    }
}
