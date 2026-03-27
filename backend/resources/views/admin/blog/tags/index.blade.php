<x-layout>
    <x-slot:title>タグ管理 | MotoHub Blog</x-slot:title>

    <x-slot:navigation>
        <x-navigation :showSearch="false" />
    </x-slot:navigation>

    <div class="max-w-5xl mx-auto px-4 py-8">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold">タグ管理</h1>
            <a href="{{ route('admin.blog.posts.index') }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; 記事管理</a>
        </div>

        @if(session('success'))
            <div class="mb-4 p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg">{{ session('success') }}</div>
        @endif

        {{-- 新規タグ作成フォーム --}}
        <div class="bg-white rounded-xl shadow-sm border p-4 mb-6">
            <form method="POST" action="{{ route('admin.blog.tags.store') }}" class="flex gap-3 items-end">
                @csrf
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-600 mb-1">タグ名</label>
                    <input type="text" name="name" required placeholder="新しいタグ名"
                           class="w-full rounded-lg border-gray-300 text-sm">
                </div>
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-600 mb-1">Slug（空欄で自動）</label>
                    <input type="text" name="slug" placeholder="tag-slug"
                           class="w-full rounded-lg border-gray-300 text-sm">
                </div>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 whitespace-nowrap">
                    追加
                </button>
            </form>
        </div>

        {{-- タグ一覧 --}}
        <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
            <table class="w-full">
                <thead>
                    <tr class="bg-gray-50 text-left text-xs font-semibold text-gray-500 uppercase">
                        <th class="px-4 py-3">タグ名</th>
                        <th class="px-4 py-3">Slug</th>
                        <th class="px-4 py-3">記事数</th>
                        <th class="px-4 py-3">操作</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100" id="tagsBody">
                    @forelse($tags as $tag)
                        <tr class="hover:bg-gray-50" id="tagRow{{ $tag->id }}">
                            <td class="px-4 py-3">
                                <span class="tag-name font-medium">{{ $tag->name }}</span>
                                {{-- インライン編集フォーム --}}
                                <form method="POST" action="{{ route('admin.blog.tags.update', $tag->id) }}"
                                      class="tag-edit-form hidden flex gap-2">
                                    @csrf @method('PUT')
                                    <input type="text" name="name" value="{{ $tag->name }}" class="rounded border-gray-300 text-sm flex-1">
                                    <input type="text" name="slug" value="{{ $tag->slug }}" class="rounded border-gray-300 text-sm flex-1">
                                    <button type="submit" class="text-sm text-blue-600">保存</button>
                                    <button type="button" class="text-sm text-gray-500 cancel-edit">取消</button>
                                </form>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-500">{{ $tag->slug }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ $tag->posts_count }}件</td>
                            <td class="px-4 py-3">
                                <div class="flex gap-2">
                                    <button type="button" class="text-sm text-blue-600 hover:underline edit-tag-btn">編集</button>
                                    <form method="POST" action="{{ route('admin.blog.tags.destroy', $tag->id) }}"
                                          onsubmit="return confirm('タグ「{{ $tag->name }}」を削除しますか？')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-sm text-red-600 hover:underline">削除</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center py-12 text-gray-400">タグがありません</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-6">{{ $tags->links() }}</div>
    </div>

    <x-slot:scripts>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                document.querySelectorAll('.edit-tag-btn').forEach(btn => {
                    btn.addEventListener('click', () => {
                        const row = btn.closest('tr');
                        row.querySelector('.tag-name').classList.add('hidden');
                        row.querySelector('.tag-edit-form').classList.remove('hidden');
                        btn.classList.add('hidden');
                    });
                });

                document.querySelectorAll('.cancel-edit').forEach(btn => {
                    btn.addEventListener('click', () => {
                        const row = btn.closest('tr');
                        row.querySelector('.tag-name').classList.remove('hidden');
                        row.querySelector('.tag-edit-form').classList.add('hidden');
                        row.querySelector('.edit-tag-btn').classList.remove('hidden');
                    });
                });
            });
        </script>
    </x-slot:scripts>
</x-layout>
