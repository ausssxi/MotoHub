<x-layout>
    <x-slot:title>ツーリングガイド管理 | MotoHub</x-slot:title>

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
        </style>
    </x-slot:styles>

    <div class="max-w-7xl mx-auto px-4 py-8">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold text-gray-900">ツーリングガイド管理</h1>
            <a href="{{ route('admin.touring.create') }}"
               class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition">
                + 新規ガイド作成
            </a>
        </div>

        @if(session('success'))
            <div class="mb-4 p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg">
                {{ session('success') }}
            </div>
        @endif

        <div class="flex gap-4 mb-6">
            <form method="GET" class="flex gap-2">
                <select name="status" class="rounded-lg border-gray-300 text-sm" onchange="this.form.submit()">
                    <option value="">全てのステータス</option>
                    <option value="draft" @selected(request('status')==='draft')>下書き</option>
                    <option value="published" @selected(request('status')==='published')>公開済</option>
                </select>
            </form>
        </div>

        <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
            <table class="blog-admin-table">
                <thead>
                    <tr>
                        <th>タイトル</th>
                        <th>都道府県</th>
                        <th>難易度</th>
                        <th>ステータス</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($guides as $guide)
                        <tr class="hover:bg-gray-50">
                            <td>
                                <a href="{{ route('admin.touring.edit', $guide->id) }}" class="text-blue-600 hover:underline font-medium">
                                    {{ Str::limit($guide->title, 50) }}
                                </a>
                                <div class="text-xs text-gray-400 mt-1">{{ $guide->slug }}</div>
                            </td>
                            <td class="text-sm text-gray-600">{{ $guide->prefecture }}</td>
                            <td class="text-sm text-gray-600">{{ $guide->difficulty }}</td>
                            <td>
                                <span class="status-badge status-{{ $guide->status }}">
                                    {{ ['draft' => '下書き', 'published' => '公開済'][$guide->status] }}
                                </span>
                            </td>
                            <td>
                                <div class="flex gap-2">
                                    <a href="{{ route('admin.touring.edit', $guide->id) }}" class="text-sm text-blue-600 hover:underline">編集</a>
                                    @if($guide->status === 'published')
                                        <a href="{{ route('touring.show', $guide->slug) }}" target="_blank" class="text-sm text-green-600 hover:underline">表示</a>
                                    @endif
                                    <form method="POST" action="{{ route('admin.touring.destroy', $guide->id) }}"
                                          onsubmit="return confirm('このガイドを削除しますか？')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-sm text-red-600 hover:underline">削除</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-12 text-gray-400">ツーリングガイドがありません</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-6">
            {{ $guides->withQueryString()->links() }}
        </div>
    </div>
</x-layout>
