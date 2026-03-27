<x-layout>
    <x-slot:title>ブログ | MotoHub</x-slot:title>
    <x-slot:metaDescription>MotoHubのブログ。バイクに関する最新情報、レビュー、メンテナンス情報をお届けします。</x-slot:metaDescription>
    <x-slot:canonical>{{ url('/blog') }}</x-slot:canonical>

    <x-slot:navigation>
        <x-navigation />
    </x-slot:navigation>

    <x-slot:styles>
        <style>
            .blog-card { transition: transform 0.2s ease, box-shadow 0.2s ease; }
            .blog-card:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0,0,0,0.1); }
            .blog-eyecatch { aspect-ratio: 16/9; object-fit: cover; }
        </style>
    </x-slot:styles>

    <div class="max-w-6xl mx-auto px-4 py-8">
        {{-- ヘッダー --}}
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">ブログ</h1>
            <p class="mt-2 text-gray-600">バイクに関する最新情報、レビュー、開発記をお届けします</p>
        </div>

        {{-- 記事一覧 --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @forelse($posts as $post)
                <article class="blog-card bg-white rounded-xl border overflow-hidden">
                    {{-- アイキャッチ --}}
                    <a href="{{ route('blog.show', $post->slug) }}">
                        @if($post->eyecatch_image)
                            <img src="{{ $post->getEyecatchUrl() }}" alt="{{ $post->title }}"
                                 class="blog-eyecatch w-full" loading="lazy"
                                 onerror="handleImageError(this)">
                        @else
                            <div class="blog-eyecatch w-full bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center">
                                <span class="text-white text-4xl font-bold opacity-30">MH</span>
                            </div>
                        @endif
                    </a>

                    <div class="p-4">
                        {{-- タグ --}}
                        @if($post->tags->isNotEmpty())
                            <div class="flex flex-wrap gap-1 mb-2">
                                @foreach($post->tags->take(3) as $tag)
                                    <a href="{{ route('blog.tag', $tag->slug) }}"
                                       class="text-xs px-2 py-0.5 bg-blue-50 text-blue-700 rounded-full hover:bg-blue-100">
                                        {{ $tag->name }}
                                    </a>
                                @endforeach
                            </div>
                        @endif

                        {{-- タイトル --}}
                        <h2 class="text-lg font-bold mb-2">
                            <a href="{{ route('blog.show', $post->slug) }}" class="text-gray-900 hover:text-blue-600 transition">
                                {{ $post->title }}
                            </a>
                        </h2>

                        {{-- 抜粋 --}}
                        <p class="text-sm text-gray-600 mb-3 line-clamp-2">{{ $post->excerpt }}</p>

                        {{-- メタ情報 --}}
                        <div class="flex items-center justify-between text-xs text-gray-400">
                            <span>{{ $post->published_at->format('Y.m.d') }}</span>
                            <span>{{ $post->reading_time_minutes }}分で読める</span>
                        </div>
                    </div>
                </article>
            @empty
                <div class="col-span-full text-center py-16 text-gray-400">
                    <p class="text-lg">まだ記事がありません</p>
                </div>
            @endforelse
        </div>

        {{-- ページネーション --}}
        <div class="mt-10">
            {{ $posts->links() }}
        </div>
    </div>
</x-layout>
