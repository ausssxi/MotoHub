<x-layout>
    <x-slot:title>{{ isset($series) ? 'シリーズ編集' : '新規シリーズ' }} | MotoHub Blog</x-slot:title>

    <x-slot:navigation>
        <x-navigation :showSearch="false" />
    </x-slot:navigation>

    <div class="max-w-3xl mx-auto px-4 py-8">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold">{{ isset($series) ? 'シリーズを編集' : '新規シリーズ作成' }}</h1>
            <a href="{{ route('admin.blog.series.index') }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; シリーズ一覧</a>
        </div>

        @if($errors->any())
            <div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg">
                <ul class="list-disc list-inside">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST"
              action="{{ isset($series) ? route('admin.blog.series.update', $series->id) : route('admin.blog.series.store') }}"
              enctype="multipart/form-data"
              class="bg-white rounded-xl shadow-sm border p-6 space-y-6">
            @csrf
            @if(isset($series)) @method('PUT') @endif

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">シリーズ名</label>
                <input type="text" name="title" value="{{ old('title', $series->title ?? '') }}" required
                       class="w-full rounded-lg border-gray-300">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Slug（空欄で自動生成）</label>
                <input type="text" name="slug" value="{{ old('slug', $series->slug ?? '') }}"
                       class="w-full rounded-lg border-gray-300 text-sm">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">説明</label>
                <textarea name="description" rows="4" class="w-full rounded-lg border-gray-300 text-sm">{{ old('description', $series->description ?? '') }}</textarea>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">アイキャッチ画像</label>
                @if(isset($series) && $series->eyecatch_image)
                    <div class="mb-2">
                        <img src="{{ rtrim(config('blog.image_base_url'), '/') . '/' . $series->eyecatch_image }}" alt="" class="h-24 rounded-lg object-cover">
                    </div>
                @endif
                <input type="file" name="eyecatch_image" accept="image/*"
                       class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">状態</label>
                <select name="status" class="w-full rounded-lg border-gray-300 text-sm">
                    <option value="active" @selected(old('status', $series->status ?? 'active') === 'active')>連載中</option>
                    <option value="completed" @selected(old('status', $series->status ?? '') === 'completed')>完結</option>
                    <option value="archived" @selected(old('status', $series->status ?? '') === 'archived')>アーカイブ</option>
                </select>
            </div>

            <div class="flex gap-4">
                <button type="submit" class="px-6 py-3 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition">
                    {{ isset($series) ? '更新する' : '作成する' }}
                </button>
                <a href="{{ route('admin.blog.series.index') }}" class="px-6 py-3 bg-gray-100 text-gray-700 font-medium rounded-lg hover:bg-gray-200 transition">
                    キャンセル
                </a>
            </div>
        </form>
    </div>
</x-layout>
