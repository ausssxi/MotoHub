<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Models\BlogSeries;
use App\Models\BlogTag;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BlogPostController extends Controller
{
    use AuthorizesRequests;
    public function index(Request $request)
    {
        $query = BlogPost::with(['author', 'series', 'tags'])->latest();

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('body', 'like', "%{$search}%");
            });
        }

        $posts = $query->paginate(20);

        return view('admin.blog.posts.index', compact('posts'));
    }

    public function create()
    {
        $series = BlogSeries::orderBy('title')->get();
        $tags = BlogTag::orderBy('name')->get();

        return view('admin.blog.posts.create', compact('series', 'tags'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:blog_posts,slug',
            'body' => 'required|string',
            'excerpt' => 'nullable|string',
            'eyecatch_image' => 'nullable|image|max:5120',
            'series_id' => 'nullable|exists:blog_series,id',
            'series_order' => 'nullable|integer|min:0',
            'status' => 'required|in:draft,published,scheduled',
            'published_at' => 'nullable|date',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:300',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
        ]);

        // アイキャッチ画像の保存
        $eyecatchPath = null;
        if ($request->hasFile('eyecatch_image')) {
            $eyecatchPath = $request->file('eyecatch_image')
                ->store('blog/images/' . date('Y/m'), config('blog.image_disk'));
        }

        $post = BlogPost::create([
            'author_id' => $request->user()->id,
            'title' => $validated['title'],
            'slug' => $validated['slug'] ?: null,
            'body' => $validated['body'],
            'excerpt' => $validated['excerpt'] ?? null,
            'eyecatch_image' => $eyecatchPath,
            'series_id' => $validated['series_id'] ?? null,
            'series_order' => $validated['series_order'] ?? null,
            'status' => $validated['status'],
            'published_at' => $validated['status'] === 'draft' ? null : ($validated['published_at'] ?? now()),
            'meta_title' => $validated['meta_title'] ?? null,
            'meta_description' => $validated['meta_description'] ?? null,
        ]);

        if (!empty($validated['tags'])) {
            $tagIds = collect($validated['tags'])->map(fn ($name) =>
                BlogTag::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name])->id
            )->toArray();
            $post->tags()->sync($tagIds);
        }

        return redirect()->route('admin.blog.posts.index')
            ->with('success', '記事を作成しました。');
    }

    public function edit(int $id)
    {
        $post = BlogPost::with('tags')->findOrFail($id);
        $this->authorize('update', $post);

        $series = BlogSeries::orderBy('title')->get();
        $tags = BlogTag::orderBy('name')->get();

        return view('admin.blog.posts.edit', compact('post', 'series', 'tags'));
    }

    public function update(Request $request, int $id)
    {
        $post = BlogPost::findOrFail($id);
        $this->authorize('update', $post);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:blog_posts,slug,' . $post->id,
            'body' => 'required|string',
            'excerpt' => 'nullable|string',
            'eyecatch_image' => 'nullable|image|max:5120',
            'series_id' => 'nullable|exists:blog_series,id',
            'series_order' => 'nullable|integer|min:0',
            'status' => 'required|in:draft,published,scheduled',
            'published_at' => 'nullable|date',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:300',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
        ]);

        // アイキャッチ画像の更新
        if ($request->hasFile('eyecatch_image')) {
            // 旧画像の削除
            if ($post->eyecatch_image) {
                Storage::disk(config('blog.image_disk'))->delete($post->eyecatch_image);
            }
            $validated['eyecatch_image'] = $request->file('eyecatch_image')
                ->store('blog/images/' . date('Y/m'), config('blog.image_disk'));
        } else {
            unset($validated['eyecatch_image']);
        }

        if (empty($validated['slug'])) {
            unset($validated['slug']);
        }

        if ($validated['status'] !== 'draft' && empty($validated['published_at'])) {
            $validated['published_at'] = $post->published_at ?? now();
        }

        unset($validated['tags']);
        $post->update($validated);

        $tagNames = $request->input('tags', []);
        $tagIds = collect($tagNames)->map(fn ($name) =>
            BlogTag::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name])->id
        )->toArray();
        $post->tags()->sync($tagIds);

        return redirect()->route('admin.blog.posts.edit', $post->id)
            ->with('success', '記事を更新しました。');
    }

    public function destroy(int $id)
    {
        $post = BlogPost::findOrFail($id);
        $this->authorize('delete', $post);

        $post->delete(); // SoftDeletes

        return redirect()->route('admin.blog.posts.index')
            ->with('success', '記事をゴミ箱に移動しました。');
    }
}
