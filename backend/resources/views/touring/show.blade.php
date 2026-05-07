<x-layout>
    <x-slot:title>{{ $guide->title }} | ツーリングガイド | MotoHub</x-slot:title>
    <x-slot:metaDescription>{{ $guide->excerpt }}</x-slot:metaDescription>
    <x-slot:canonical>{{ url('/touring/' . $guide->slug) }}</x-slot:canonical>

    <x-slot:navigation>
        <x-navigation />
    </x-slot:navigation>

    <x-slot:styles>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/highlight.js@11/styles/github.min.css">
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
        <link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.css" />

        <script type="application/ld+json">
        {
            "@@context": "https://schema.org",
            "@@type": "Article",
            "headline": "{{ e($guide->title) }}",
            "datePublished": "{{ $guide->published_at->toIso8601String() }}",
            "dateModified": "{{ $guide->updated_at->toIso8601String() }}",
            "author": {
                "@@type": "Person",
                "name": "{{ e($guide->author->name ?? 'MotoHub') }}"
            },
            "publisher": {
                "@@type": "Organization",
                "name": "MotoHub",
                "logo": {
                    "@@type": "ImageObject",
                    "url": "{{ asset('favicon-96x96.png') }}"
                }
            },
            "description": "{{ e($guide->excerpt) }}",
            "mainEntityOfPage": {
                "@@type": "WebPage",
                "@@id": "{{ url('/touring/' . $guide->slug) }}"
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

            .article-layout { display: flex; gap: 40px; }
            .article-toc-sidebar {
                width: 200px; flex-shrink: 0; position: sticky; top: 80px;
                align-self: flex-start; max-height: calc(100vh - 100px); overflow-y: auto;
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
        </style>
    </x-slot:styles>

    <div class="max-w-6xl mx-auto px-4 py-8">
        {{-- パンくずリスト --}}
        <nav class="text-sm text-gray-400 mb-6">
            <a href="{{ url('/') }}" class="hover:text-gray-600">ホーム</a>
            <span class="mx-1">/</span>
            <a href="{{ route('touring.index') }}" class="hover:text-gray-600">ツーリングガイド</a>
            <span class="mx-1">/</span>
            <span class="text-gray-600">{{ Str::limit($guide->title, 30) }}</span>
        </nav>

        <article>
            {{-- ヘッダー --}}
            <header class="mb-8">
                <h1 class="text-3xl md:text-4xl font-bold text-gray-900 leading-tight mb-4">
                    {{ $guide->title }}
                </h1>

                {{-- 情報バッジ --}}
                <div class="flex flex-wrap items-center gap-2 mb-4">
                    @php
                        $difficultyColors = [
                            '初級' => 'bg-green-100 text-green-700',
                            '中級' => 'bg-yellow-100 text-yellow-700',
                            '上級' => 'bg-red-100 text-red-700',
                        ];
                    @endphp
                    <span class="inline-flex items-center px-2.5 py-1 text-xs font-bold rounded-full bg-blue-100 text-blue-700">
                        {{ $guide->prefecture }}
                    </span>
                    <span class="inline-flex items-center px-2.5 py-1 text-xs font-bold rounded-full {{ $difficultyColors[$guide->difficulty] ?? 'bg-gray-100 text-gray-700' }}">
                        {{ $guide->difficulty }}
                    </span>
                    @if($guide->distance_km)
                        <span class="inline-flex items-center px-2.5 py-1 text-xs font-bold rounded-full bg-gray-100 text-gray-700">
                            {{ $guide->distance_km }}km
                        </span>
                    @endif
                    @if($guide->duration_text)
                        <span class="inline-flex items-center px-2.5 py-1 text-xs font-bold rounded-full bg-gray-100 text-gray-700">
                            {{ $guide->duration_text }}
                        </span>
                    @endif
                    @if($guide->best_season)
                        <span class="inline-flex items-center px-2.5 py-1 text-xs font-bold rounded-full bg-cyan-100 text-cyan-700">
                            {{ $guide->best_season }}
                        </span>
                    @endif
                </div>

                <div class="flex items-center gap-4 text-sm text-gray-500">
                    <span>{{ $guide->author->name ?? 'MotoHub' }}</span>
                    <span>{{ $guide->published_at->format('Y年m月d日') }}</span>
                    <span>{{ $guide->reading_time_minutes }}分で読める</span>
                </div>
            </header>

            {{-- マップ自動埋め込み --}}
            <div class="mb-8 rounded-xl overflow-hidden border border-gray-200 shadow-sm">
                <div class="riders-map-embed"
                     data-lat="{{ $guide->latitude }}"
                     data-lng="{{ $guide->longitude }}"
                     data-zoom="{{ $guide->zoom_level }}"
                     data-layers="gas_station,parking,michi_no_eki"
                     style="height:350px; width:100%;"></div>
                <div class="flex items-center gap-2 px-4 py-2.5 bg-gray-50 text-xs text-gray-500">
                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    <span>{{ number_format($guide->latitude, 5) }}, {{ number_format($guide->longitude, 5) }}</span>
                    <a href="{{ route('riders.map') }}?lat={{ $guide->latitude }}&lng={{ $guide->longitude }}&zoom=14"
                       class="ml-auto text-cyan-600 hover:text-cyan-800 font-medium">ライダーズマップで見る &rarr;</a>
                </div>
            </div>

            {{-- 目次 + 本文レイアウト --}}
            <div class="article-layout">
                @if($toc)
                    <aside class="article-toc-sidebar">
                        {!! $toc !!}
                    </aside>
                @endif

                <div class="article-main">
                    @if($toc)
                        <div class="article-toc-inline">
                            <details class="toc" open>
                                <summary class="font-bold text-gray-700 cursor-pointer mb-2">目次</summary>
                                {!! $toc !!}
                            </details>
                        </div>
                    @endif

                    <div class="blog-content">
                        {!! $html !!}
                    </div>
                </div>
            </div>

            {{-- シェアボタン --}}
            <div class="mt-10 pt-6 border-t border-gray-200">
                <div class="flex items-center gap-3">
                    <span class="text-sm text-gray-500">共有:</span>
                    <a href="https://twitter.com/intent/tweet?url={{ urlencode(url('/touring/' . $guide->slug)) }}&text={{ urlencode($guide->title) }}"
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
    </div>

    <x-slot:scripts>
        <script src="https://cdn.jsdelivr.net/npm/highlight.js@11/highlight.min.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                document.querySelectorAll('.blog-content pre code').forEach(el => hljs.highlightElement(el));
            });
        </script>
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
        <script src="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.js"></script>
        <script src="{{ asset('js/blog/embedded-map.js') }}"></script>
    </x-slot:scripts>
</x-layout>
