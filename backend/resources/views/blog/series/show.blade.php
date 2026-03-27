<x-layout>
    <x-slot:title>{{ $series->title }} | MotoHub Blog</x-slot:title>
    <x-slot:metaDescription>{{ $series->description ?? $series->title . 'の記事一覧' }}</x-slot:metaDescription>
    <x-slot:canonical>{{ url('/blog/series/' . $series->slug) }}</x-slot:canonical>

    <x-slot:navigation>
        <x-navigation />
    </x-slot:navigation>

    <div class="max-w-4xl mx-auto px-4 py-8">
        {{-- パンくず --}}
        <nav class="text-sm text-gray-400 mb-6">
            <a href="{{ url('/') }}" class="hover:text-gray-600">ホーム</a>
            <span class="mx-1">/</span>
            <a href="{{ route('blog.index') }}" class="hover:text-gray-600">ブログ</a>
            <span class="mx-1">/</span>
            <span class="text-gray-600">{{ $series->title }}</span>
        </nav>

        {{-- シリーズヘッダー --}}
        <div class="mb-8">
            @if($series->eyecatch_image)
                <img src="{{ rtrim(config('blog.image_base_url'), '/') . '/' . $series->eyecatch_image }}"
                     alt="{{ $series->title }}" class="w-full rounded-xl mb-6 aspect-video object-cover"
                     onerror="handleImageError(this)">
            @endif

            <h1 class="text-3xl font-bold text-gray-900 mb-2">{{ $series->title }}</h1>

            @if($series->description)
                <p class="text-gray-600 mb-2">{{ $series->description }}</p>
            @endif

            <div class="flex gap-3 text-sm text-gray-400">
                <span>全{{ $posts->count() }}回</span>
                <span class="px-2 py-0.5 rounded-full text-xs font-medium
                    {{ $series->status === 'active' ? 'bg-green-100 text-green-700' : ($series->status === 'completed' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600') }}">
                    {{ ['active' => '連載中', 'completed' => '完結', 'archived' => 'アーカイブ'][$series->status] }}
                </span>
            </div>
        </div>

        {{-- 記事リスト --}}
        <div class="space-y-4">
            @forelse($posts as $index => $post)
                <a href="{{ route('blog.show', $post->slug) }}"
                   class="flex gap-4 items-start bg-white rounded-xl border p-4 hover:shadow-md transition group">

                    <div class="flex-shrink-0 w-10 h-10 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center font-bold text-sm">
                        {{ $index + 1 }}
                    </div>

                    <div class="flex-1 min-w-0">
                        <h2 class="font-bold text-gray-900 group-hover:text-blue-600 transition">{{ $post->title }}</h2>
                        <p class="text-sm text-gray-500 mt-1 line-clamp-2">{{ $post->excerpt }}</p>
                        <div class="flex gap-3 mt-2 text-xs text-gray-400">
                            <span>{{ $post->published_at->format('Y.m.d') }}</span>
                            <span>{{ $post->reading_time_minutes }}分</span>
                        </div>
                    </div>

                    @if($post->eyecatch_image)
                        <img src="{{ $post->getEyecatchUrl() }}" alt=""
                             class="w-24 h-16 rounded-lg object-cover flex-shrink-0 hidden sm:block"
                             loading="lazy" onerror="handleImageError(this)">
                    @endif
                </a>
            @empty
                <div class="text-center py-16 text-gray-400">
                    <p>このシリーズにはまだ記事がありません</p>
                </div>
            @endforelse
        </div>
    </div>
</x-layout>
