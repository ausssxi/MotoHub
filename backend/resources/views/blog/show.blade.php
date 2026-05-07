<x-layout>
    <x-slot:title>{{ $post->meta_title ?? $post->title }} | MotoHub Blog</x-slot:title>
    <x-slot:metaDescription>{{ $post->meta_description ?? $post->excerpt }}</x-slot:metaDescription>
    <x-slot:canonical>{{ url('/blog/' . $post->slug) }}</x-slot:canonical>
    <x-slot:ogImage>{{ route('blog.ogp', $post->slug) }}</x-slot:ogImage>

    <x-slot:navigation>
        <x-navigation />
    </x-slot:navigation>

    <x-slot:styles>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/highlight.js@11/styles/github.min.css">

        @if(!empty($hasMap))
            <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
            <link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.css" />
        @endif

        {{-- JSON-LD 構造化データ --}}
        <script type="application/ld+json">
        {
            "@@context": "https://schema.org",
            "@@type": "BlogPosting",
            "headline": "{{ e($post->title) }}",
            @if($post->eyecatch_image)
            "image": "{{ url($post->getEyecatchUrl()) }}",
            @endif
            "datePublished": "{{ $post->published_at->toIso8601String() }}",
            "dateModified": "{{ $post->updated_at->toIso8601String() }}",
            "author": {
                "@@type": "Person",
                "name": "{{ e($post->author->name ?? 'MotoHub') }}"
            },
            "publisher": {
                "@@type": "Organization",
                "name": "MotoHub",
                "logo": {
                    "@@type": "ImageObject",
                    "url": "{{ asset('favicon-96x96.png') }}"
                }
            },
            "description": "{{ e($post->meta_description ?? $post->excerpt) }}",
            "mainEntityOfPage": {
                "@@type": "WebPage",
                "@@id": "{{ url('/blog/' . $post->slug) }}"
            }
        }
        </script>

        <style>
            .blog-content h2 { font-size: 1.5rem; font-weight: 700; margin: 2rem 0 1rem; padding-bottom: 0.5rem; border-bottom: 2px solid #e5e7eb; }
            .blog-content h3 { font-size: 1.25rem; font-weight: 600; margin: 1.5rem 0 0.75rem; }
            .blog-content p { margin-bottom: 1.25rem; line-height: 1.9; color: #374151; }
            .blog-content ul, .blog-content ol { margin: 1rem 0; padding-left: 1.5rem; }
            .blog-content li { margin-bottom: 0.375rem; line-height: 1.8; }
            .blog-content ul li { list-style: disc; }
            .blog-content ol li { list-style: decimal; }
            .blog-content code { background: #f3f4f6; padding: 0.125rem 0.375rem; border-radius: 0.25rem; font-size: 0.875rem; color: #dc2626; }
            .blog-content pre { background: #1e293b; color: #e2e8f0; padding: 1.25rem; border-radius: 0.75rem; overflow-x: auto; margin: 1.5rem 0; }
            .blog-content pre code { background: transparent; color: inherit; padding: 0; }
            .blog-content blockquote { border-left: 4px solid #3b82f6; padding: 1rem 1.25rem; margin: 1.5rem 0; background: #eff6ff; border-radius: 0 0.5rem 0.5rem 0; color: #1e40af; }
            .blog-content img { max-width: 100%; border-radius: 0.75rem; margin: 1.5rem 0; }
            .blog-content a { color: #2563eb; text-decoration: underline; text-underline-offset: 2px; }
            .blog-content a:hover { color: #1d4ed8; }
            .blog-content table { width: 100%; border-collapse: collapse; margin: 1.5rem 0; display: block; overflow-x: auto; -webkit-overflow-scrolling: touch; font-size: 14px; }
            .blog-content th, .blog-content td { border: 1px solid #d1d5db; padding: 0.625rem 0.875rem; text-align: left; }
            .blog-content th { background: #f9fafb; font-weight: 600; }
            @media (max-width: 640px) {
                .blog-content table { font-size: 12px; }
                .blog-content th, .blog-content td { padding: 6px 8px; white-space: nowrap; }
            }
            .blog-content hr { margin: 2rem 0; border-color: #e5e7eb; }

            /* 目次レイアウト */
            .article-layout { display: flex; gap: 40px; }
            .article-toc-sidebar {
                width: 200px;
                flex-shrink: 0;
                position: sticky;
                top: 80px;
                align-self: flex-start;
                max-height: calc(100vh - 100px);
                overflow-y: auto;
            }
            .article-toc-sidebar ol { list-style: none; padding-left: 0; margin: 0; border-left: 2px solid #e5e7eb; }
            .article-toc-sidebar li { padding-left: 12px; margin-bottom: 4px; }
            .article-toc-sidebar ol ol li { padding-left: 24px; }
            .article-toc-sidebar a { color: #6b7280; text-decoration: none; font-size: 13px; line-height: 1.6; }
            .article-toc-sidebar a:hover { color: #2563eb; }
            .article-toc-inline { display: none; }
            .article-toc-inline .toc { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 0.75rem; padding: 1.25rem 1.5rem; margin-bottom: 2rem; }
            .article-toc-inline .toc ol { list-style: none; padding-left: 0; margin: 0; }
            .article-toc-inline .toc > ol > li { margin-bottom: 0.375rem; }
            .article-toc-inline .toc ol ol { padding-left: 1.25rem; margin-top: 0.25rem; }
            .article-toc-inline .toc a { color: #4b5563; text-decoration: none; font-size: 0.875rem; }
            .article-toc-inline .toc a:hover { color: #2563eb; }
            .article-main { flex: 1; min-width: 0; }

            @media (max-width: 900px) {
                .article-toc-sidebar { display: none; }
                .article-toc-inline { display: block; }
                .article-layout { flex-direction: column; gap: 0; }
            }

            .series-nav { background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 0.75rem; padding: 1.25rem; margin-bottom: 2rem; }

            /* 関連記事カード */
            .related-posts-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; align-items: stretch; }
            .related-post-card { display: flex; flex-direction: column; height: 100%; }
            .related-post-card img { width: 100%; height: 160px; object-fit: cover; }
            .related-post-body { flex: 1; }
            @media (max-width: 768px) { .related-posts-grid { grid-template-columns: 1fr; } }
        </style>
    </x-slot:styles>

    <div class="max-w-6xl mx-auto px-4 py-8">
        {{-- パンくずリスト --}}
        <nav class="text-sm text-gray-400 mb-6">
            <a href="{{ url('/') }}" class="hover:text-gray-600">ホーム</a>
            <span class="mx-1">/</span>
            <a href="{{ route('blog.index') }}" class="hover:text-gray-600">ブログ</a>
            <span class="mx-1">/</span>
            <span class="text-gray-600">{{ Str::limit($post->title, 30) }}</span>
        </nav>

        <article>
            {{-- ヘッダー --}}
            <header class="mb-8">
                {{-- タグ --}}
                @if($post->tags->isNotEmpty())
                    <div class="flex flex-wrap gap-2 mb-3">
                        @foreach($post->tags as $tag)
                            <a href="{{ route('blog.tag', $tag->slug) }}"
                               class="text-xs px-3 py-1 bg-blue-50 text-blue-700 rounded-full hover:bg-blue-100 transition">
                                {{ $tag->name }}
                            </a>
                        @endforeach
                    </div>
                @endif

                <h1 class="text-3xl md:text-4xl font-bold text-gray-900 leading-tight mb-4">
                    {{ $post->title }}
                </h1>

                <div class="flex items-center gap-4 text-sm text-gray-500">
                    <span>{{ $post->author->name ?? 'MotoHub' }}</span>
                    <span>{{ $post->published_at->format('Y年m月d日') }}</span>
                    <span>{{ $post->reading_time_minutes }}分で読める</span>
                    <span>👁 {{ number_format($post->view_count) }} views</span>
                </div>
            </header>

            {{-- アイキャッチ画像 --}}
            @if($post->eyecatch_image)
                <div class="mb-8">
                    <img src="{{ $post->getEyecatchUrl() }}" alt="{{ $post->title }}"
                         class="w-full rounded-xl" onerror="handleImageError(this)">
                </div>
            @endif

            {{-- 位置情報マップ（lat/lngがあれば自動表示） --}}
            @if($post->latitude && $post->longitude)
                <div class="mb-8 rounded-xl overflow-hidden border border-gray-200 shadow-sm">
                    <div class="riders-map-embed"
                         data-lat="{{ $post->latitude }}"
                         data-lng="{{ $post->longitude }}"
                         data-zoom="13"
                         data-layers="all"
                         style="height:350px; width:100%;"></div>
                    <div class="flex items-center gap-2 px-4 py-2.5 bg-gray-50 text-xs text-gray-500">
                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        <span>{{ number_format($post->latitude, 5) }}, {{ number_format($post->longitude, 5) }}</span>
                        <a href="{{ route('riders.map') }}?lat={{ $post->latitude }}&lng={{ $post->longitude }}&zoom=14"
                           class="ml-auto text-cyan-600 hover:text-cyan-800 font-medium">ライダーズマップで見る &rarr;</a>
                    </div>
                </div>
            @endif

            {{-- シリーズナビゲーション --}}
            @if($seriesNav)
                <div class="series-nav">
                    <div class="flex items-center justify-between mb-2">
                        <a href="{{ route('blog.series.show', $seriesNav['series']->slug) }}" class="font-bold text-blue-800 hover:underline">
                            {{ $seriesNav['series']->title }}
                        </a>
                        <span class="text-sm text-blue-600">第{{ $seriesNav['current'] + 1 }}回 / 全{{ $seriesNav['total'] }}回</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        @if($seriesNav['prev'])
                            <a href="{{ route('blog.show', $seriesNav['prev']->slug) }}" class="text-blue-600 hover:underline">&larr; 前の記事</a>
                        @else
                            <span></span>
                        @endif
                        @if($seriesNav['next'])
                            <a href="{{ route('blog.show', $seriesNav['next']->slug) }}" class="text-blue-600 hover:underline">次の記事 &rarr;</a>
                        @endif
                    </div>
                </div>
            @endif

            {{-- 目次 + 本文レイアウト --}}
            <div class="article-layout">
                {{-- 左: 目次サイドバー（デスクトップのみ） --}}
                @if($toc)
                    <aside class="article-toc-sidebar">
                        {!! $toc !!}
                    </aside>
                @endif

                {{-- 右: 記事本文 --}}
                <div class="article-main">
                    {{-- モバイル用の折りたたみ目次 --}}
                    @if($toc)
                        <div class="article-toc-inline">
                            <details class="toc" open>
                                <summary class="font-bold text-gray-700 cursor-pointer mb-2">目次</summary>
                                {!! $toc !!}
                            </details>
                        </div>
                    @endif

                    {{-- 本文 --}}
                    <div class="blog-content">
                        {!! $html !!}
                    </div>
                </div>
            </div>

            {{-- シリーズ全記事リスト --}}
            @if($seriesNav)
                <details class="mt-8 bg-gray-50 rounded-xl border p-4">
                    <summary class="font-bold text-gray-700 cursor-pointer">「{{ $seriesNav['series']->title }}」の全記事</summary>
                    <ol class="mt-3 space-y-2">
                        @foreach($seriesNav['posts'] as $idx => $sp)
                            <li class="flex gap-2 {{ $sp->id === $post->id ? 'font-bold text-blue-700' : 'text-gray-600' }}">
                                <span class="text-gray-400">{{ $idx + 1 }}.</span>
                                @if($sp->id === $post->id)
                                    {{ $sp->title }}（この記事）
                                @else
                                    <a href="{{ route('blog.show', $sp->slug) }}" class="hover:text-blue-600 hover:underline">{{ $sp->title }}</a>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                </details>
            @endif
            {{-- シェアボタン --}}
            <div class="mt-10 pt-6 border-t border-gray-200">
                <div class="flex items-center gap-3">
                    <span class="text-sm text-gray-500">共有:</span>
                    <a href="https://twitter.com/intent/tweet?url={{ urlencode(url('/blog/' . $post->slug)) }}&text={{ urlencode($post->title) }}"
                       target="_blank"
                       rel="noopener noreferrer"
                       class="inline-flex items-center gap-2 bg-black text-white px-4 py-2 rounded-lg hover:bg-gray-800 transition text-sm">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
                        </svg>
                        Xで共有
                    </a>
                </div>
            </div>
        </article>

        {{-- 前の記事・次の記事 --}}
        @if($prevPost || $nextPost)
            <nav class="mt-10 pt-6 border-t border-gray-200 grid grid-cols-2 gap-4">
                <div>
                    @if($prevPost)
                        <a href="{{ route('blog.show', $prevPost->slug) }}" class="group block">
                            <span class="text-xs text-gray-400 group-hover:text-blue-500 transition">&larr; 前の記事</span>
                            <span class="block text-sm font-bold text-gray-700 group-hover:text-blue-600 transition line-clamp-2 mt-1">{{ $prevPost->title }}</span>
                        </a>
                    @endif
                </div>
                <div class="text-right">
                    @if($nextPost)
                        <a href="{{ route('blog.show', $nextPost->slug) }}" class="group block">
                            <span class="text-xs text-gray-400 group-hover:text-blue-500 transition">次の記事 &rarr;</span>
                            <span class="block text-sm font-bold text-gray-700 group-hover:text-blue-600 transition line-clamp-2 mt-1">{{ $nextPost->title }}</span>
                        </a>
                    @endif
                </div>
            </nav>
        @endif

        {{-- 関連記事 --}}
        @if($relatedPosts->isNotEmpty())
            <section class="mt-12 pt-8 border-t">
                <h2 class="text-xl font-bold mb-6">関連記事</h2>
                <div class="related-posts-grid">
                    @foreach($relatedPosts as $related)
                        <a href="{{ route('blog.show', $related->slug) }}" class="related-post-card bg-white rounded-lg border overflow-hidden hover:shadow-md transition">
                            @if($related->eyecatch_image)
                                <img src="{{ $related->getEyecatchUrl() }}" alt="" class="rounded-t-lg" loading="lazy" onerror="handleImageError(this)">
                            @endif
                            <div class="related-post-body p-4">
                                <h3 class="font-bold text-sm text-gray-900 line-clamp-2">{{ $related->title }}</h3>
                                <div class="text-xs text-gray-400 mt-2">{{ $related->published_at->format('Y.m.d') }}</div>
                            </div>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- 駐車場エリアリンク --}}
        <div class="mt-8 bg-gray-50 rounded-2xl p-5 border border-gray-200 flex items-center justify-between hover:shadow-md transition-shadow">
            <div class="flex items-center gap-3">
                <span class="text-2xl">🅿️</span>
                <span class="text-sm font-black text-gray-800">バイク駐車場をエリアから探す</span>
            </div>
            <a href="{{ route('parking.area.index') }}" class="text-xs font-bold text-green-600 hover:text-green-800 flex items-center gap-1">
                エリア一覧 <i data-lucide="arrow-right" class="w-4 h-4"></i>
            </a>
        </div>
    </div>

    <x-slot:scripts>
        <script src="https://cdn.jsdelivr.net/npm/highlight.js@11/highlight.min.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                document.querySelectorAll('.blog-content pre code').forEach(el => hljs.highlightElement(el));
            });
        </script>
        @if(!empty($hasMap))
            <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
            <script src="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.js"></script>
            <script src="{{ asset('js/blog/embedded-map.js') }}"></script>
        @endif
    </x-slot:scripts>
</x-layout>
