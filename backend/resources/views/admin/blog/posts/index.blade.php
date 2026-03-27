<x-layout>
    <x-slot:title>記事管理 | MotoHub Blog</x-slot:title>

    <x-slot:navigation>
        <x-navigation :showSearch="false" />
    </x-slot:navigation>

    <x-slot:styles>
        <style>
            .blog-admin-table { width: 100%; border-collapse: collapse; }
            .blog-admin-table th,
            .blog-admin-table td { padding: 0.75rem 1rem; text-align: left; border-bottom: 1px solid #e5e7eb; }
            .blog-admin-table th { background: #f9fafb; font-weight: 600; font-size: 0.75rem; text-transform: uppercase; color: #6b7280; }
            .status-badge { padding: 0.125rem 0.5rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 500; }
            .status-draft { background: #f3f4f6; color: #374151; }
            .status-published { background: #d1fae5; color: #065f46; }
            .status-scheduled { background: #dbeafe; color: #1e40af; }
        </style>
    </x-slot:styles>

    <div class="max-w-7xl mx-auto px-4 py-8">
        {{-- ヘッダー --}}
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold text-gray-900">記事管理</h1>
            <a href="{{ route('admin.blog.posts.create') }}"
               class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition">
                + 新規記事作成
            </a>
        </div>

        {{-- フラッシュメッセージ --}}
        @if(session('success'))
            <div class="mb-4 p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg">
                {{ session('success') }}
            </div>
        @endif

        {{-- フィルター --}}
        <div class="flex gap-4 mb-6">
            <form method="GET" class="flex gap-2 flex-1">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="タイトルで検索..."
                       class="flex-1 rounded-lg border-gray-300 text-sm focus:ring-blue-500 focus:border-blue-500">
                <select name="status" class="rounded-lg border-gray-300 text-sm">
                    <option value="">全てのステータス</option>
                    <option value="draft" @selected(request('status')==='draft')>下書き</option>
                    <option value="published" @selected(request('status')==='published')>公開済</option>
                    <option value="scheduled" @selected(request('status')==='scheduled')>予約</option>
                </select>
                <button type="submit" class="px-4 py-2 bg-gray-100 text-gray-700 text-sm rounded-lg hover:bg-gray-200">検索</button>
            </form>
            <div class="flex gap-2">
                <a href="{{ route('admin.blog.series.index') }}" class="px-4 py-2 text-sm text-gray-600 border rounded-lg hover:bg-gray-50">シリーズ</a>
                <a href="{{ route('admin.blog.tags.index') }}" class="px-4 py-2 text-sm text-gray-600 border rounded-lg hover:bg-gray-50">タグ</a>
            </div>
        </div>

        {{-- 記事一覧テーブル --}}
        <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
            <table class="blog-admin-table">
                <thead>
                    <tr>
                        <th>タイトル</th>
                        <th>ステータス</th>
                        <th>著者</th>
                        <th>シリーズ</th>
                        <th>公開日</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($posts as $post)
                        <tr class="hover:bg-gray-50">
                            <td>
                                <a href="{{ route('admin.blog.posts.edit', $post->id) }}" class="text-blue-600 hover:underline font-medium">
                                    {{ Str::limit($post->title, 50) }}
                                </a>
                                <div class="text-xs text-gray-400 mt-1">{{ $post->slug }}</div>
                            </td>
                            <td>
                                <span class="status-badge status-{{ $post->status }}">
                                    {{ ['draft' => '下書き', 'published' => '公開済', 'scheduled' => '予約'][$post->status] }}
                                </span>
                            </td>
                            <td class="text-sm text-gray-600">{{ $post->author->name ?? '-' }}</td>
                            <td class="text-sm text-gray-600">{{ $post->series->title ?? '-' }}</td>
                            <td class="text-sm text-gray-500">{{ $post->published_at?->format('Y/m/d H:i') ?? '-' }}</td>
                            <td>
                                <div class="flex gap-2">
                                    <a href="{{ route('admin.blog.posts.edit', $post->id) }}" class="text-sm text-blue-600 hover:underline">編集</a>
                                    @if($post->status === 'published')
                                        <a href="{{ route('blog.show', $post->slug) }}" target="_blank" class="text-sm text-green-600 hover:underline">表示</a>
                                    @endif
                                    <form method="POST" action="{{ route('admin.blog.posts.destroy', $post->id) }}"
                                          onsubmit="return confirm('この記事を削除しますか？')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-sm text-red-600 hover:underline">削除</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-12 text-gray-400">記事がありません</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- ページネーション --}}
        <div class="mt-6">
            {{ $posts->withQueryString()->links() }}
        </div>
    </div>
</x-layout>
