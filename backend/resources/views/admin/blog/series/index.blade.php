<x-layout>
    <x-slot:title>シリーズ管理 | MotoHub Blog</x-slot:title>

    <x-slot:navigation>
        <x-navigation :showSearch="false" />
    </x-slot:navigation>

    <div class="max-w-5xl mx-auto px-4 py-8">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold">シリーズ管理</h1>
            <div class="flex gap-3">
                <a href="{{ route('admin.blog.series.create') }}"
                   class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition">
                    + 新規シリーズ
                </a>
                <a href="{{ route('admin.blog.posts.index') }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; 記事管理</a>
            </div>
        </div>

        @if(session('success'))
            <div class="mb-4 p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg">{{ session('success') }}</div>
        @endif

        <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
            <table class="w-full">
                <thead>
                    <tr class="bg-gray-50 text-left text-xs font-semibold text-gray-500 uppercase">
                        <th class="px-4 py-3">シリーズ名</th>
                        <th class="px-4 py-3">状態</th>
                        <th class="px-4 py-3">記事数</th>
                        <th class="px-4 py-3">操作</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($seriesList as $series)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <a href="{{ route('admin.blog.series.edit', $series->id) }}" class="text-blue-600 hover:underline font-medium">
                                    {{ $series->title }}
                                </a>
                                <div class="text-xs text-gray-400">{{ $series->slug }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-0.5 rounded-full text-xs font-medium
                                    {{ $series->status === 'active' ? 'bg-green-100 text-green-700' : ($series->status === 'completed' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600') }}">
                                    {{ ['active' => '連載中', 'completed' => '完結', 'archived' => 'アーカイブ'][$series->status] }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ $series->posts_count }}件</td>
                            <td class="px-4 py-3">
                                <div class="flex gap-2">
                                    <a href="{{ route('admin.blog.series.edit', $series->id) }}" class="text-sm text-blue-600 hover:underline">編集</a>
                                    <form method="POST" action="{{ route('admin.blog.series.destroy', $series->id) }}"
                                          onsubmit="return confirm('このシリーズを削除しますか？')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-sm text-red-600 hover:underline">削除</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center py-12 text-gray-400">シリーズがありません</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-6">{{ $seriesList->links() }}</div>
    </div>
</x-layout>
