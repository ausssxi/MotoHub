<x-layout>
    <x-slot:title>{{ $tag->name }} の記事一覧 | MotoHub Blog</x-slot:title>
    <x-slot:metaDescription>{{ $tag->description ?? $tag->name . 'に関する記事一覧' }}</x-slot:metaDescription>
    <x-slot:canonical>{{ url('/blog/tag/' . $tag->slug) }}</x-slot:canonical>

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
        {{-- パンくず --}}
        <nav class="text-sm text-gray-400 mb-6">
            <a href="{{ url('/') }}" class="hover:text-gray-600">ホーム</a>
            <span class="mx-1">/</span>
            <a href="{{ route('blog.index') }}" class="hover:text-gray-600">ブログ</a>
            <span class="mx-1">/</span>
            <span class="text-gray-600">{{ $tag->name }}</span>
        </nav>

        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">
                <span class="text-blue-600">#</span> {{ $tag->name }}
            </h1>
            @if($tag->description)
                <p class="mt-2 text-gray-600">{{ $tag->description }}</p>
            @endif
            <p class="mt-1 text-sm text-gray-400">{{ $posts->total() }}件の記事</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @forelse($posts as $post)
                <article class="blog-card bg-white rounded-xl border overflow-hidden">
                    <a href="{{ route('blog.show', $post->slug) }}">
                        @if($post->eyecatch_image)
                            <img src="{{ $post->getEyecatchUrl() }}" alt="{{ $post->title }}"
                                 class="blog-eyecatch w-full" loading="lazy" onerror="handleImageError(this)">
                        @else
                            <div class="blog-eyecatch w-full bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center">
                                <span class="text-white text-4xl font-bold opacity-30">MH</span>
                            </div>
                        @endif
                    </a>
                    <div class="p-4">
                        @if($post->tags->isNotEmpty())
                            <div class="flex flex-wrap gap-1 mb-2">
                                @foreach($post->tags->take(3) as $t)
                                    <a href="{{ route('blog.tag', $t->slug) }}"
                                       class="text-xs px-2 py-0.5 rounded-full {{ $t->id === $tag->id ? 'bg-blue-600 text-white' : 'bg-blue-50 text-blue-700 hover:bg-blue-100' }}">
                                        {{ $t->name }}
                                    </a>
                                @endforeach
                            </div>
                        @endif
                        <h2 class="text-lg font-bold mb-2">
                            <a href="{{ route('blog.show', $post->slug) }}" class="text-gray-900 hover:text-blue-600 transition">{{ $post->title }}</a>
                        </h2>
                        <p class="text-sm text-gray-600 mb-3 line-clamp-2">{{ $post->excerpt }}</p>
                        <div class="flex items-center justify-between text-xs text-gray-400">
                            <span>{{ $post->published_at->format('Y.m.d') }}</span>
                            <span>{{ $post->reading_time_minutes }}分で読める</span>
                        </div>
                    </div>
                </article>
            @empty
                <div class="col-span-full text-center py-16 text-gray-400">
                    <p class="text-lg">このタグの記事はまだありません</p>
                </div>
            @endforelse
        </div>

        <div class="mt-10">{{ $posts->links() }}</div>
    </div>
</x-layout>
