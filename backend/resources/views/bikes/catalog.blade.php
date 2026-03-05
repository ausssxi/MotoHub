<x-layout>
    {{-- SEO用タイトル・説明文の設定 --}}
    <x-slot:title>{{ $pageInfo['title'] }} | MotoHub</x-slot:title>
    <x-slot:metaDescription>{{ $pageInfo['description'] }}</x-slot:metaDescription>

    @if($pagination['total'] === 0)
        <x-slot:robotsMeta>noindex, follow</x-slot:robotsMeta>
    @endif

    <x-slot:styles>
        <link rel="preload" href="{{ asset('css/bike-search.css') }}" as="style" onload="this.onload=null;this.rel='stylesheet'">
        <noscript><link rel="stylesheet" href="{{ asset('css/bike-search.css') }}"></noscript>
    </x-slot:styles>

    <x-slot:scripts>
        <script src="{{ asset('js/search/sidebar.js') }}" defer></script>
        <script src="{{ asset('js/compare/manager.js') }}" defer></script>
        <script src="{{ asset('js/compare/ui.js') }}" defer></script>
    </x-slot:scripts>

    <x-slot:navigation>
        <x-navigation :showSearch="true" />
    </x-slot:navigation>

    <div class="bg-gray-50 min-h-screen">

        {{-- SEOヘッダーセクション --}}
        <div class="bg-white border-b border-gray-200 pt-8 pb-10 px-4">
            <div class="max-w-7xl mx-auto">
                {{-- パンくずリスト --}}
                <nav class="flex text-xs font-bold text-gray-400 mb-6" aria-label="Breadcrumb">
                    <ol class="flex items-center space-x-2">
                        <li><a href="/" class="hover:text-gray-600 transition-colors">HOME</a></li>
                        <li><span class="text-gray-300">＞</span></li>
                        <li><a href="{{ route('bikes.search') }}" class="hover:text-gray-600 transition-colors">バイク検索</a></li>
                        <li><span class="text-gray-300">＞</span></li>
                        <li><span class="text-gray-800">{{ $pageInfo['target_name'] }}</span></li>
                    </ol>
                </nav>

                {{-- パンくずJSON-LD --}}
                <script type="application/ld+json">
                {
                    "@context": "https://schema.org",
                    "@type": "BreadcrumbList",
                    "itemListElement": [
                        {
                            "@type": "ListItem",
                            "position": 1,
                            "name": "HOME",
                            "item": "{{ url('/') }}"
                        },
                        {
                            "@type": "ListItem",
                            "position": 2,
                            "name": "バイク検索",
                            "item": "{{ route('bikes.search') }}"
                        },
                        {
                            "@type": "ListItem",
                            "position": 3,
                            "name": "{{ $pageInfo['target_name'] }}"
                        }
                    ]
                }
                </script>

                {{-- H1見出し --}}
                <h1 class="text-2xl sm:text-3xl font-black text-gray-900 mb-3 tracking-tight">
                    {!! $pageInfo['h1_html'] !!}
                </h1>

                <div class="prose prose-sm max-w-4xl text-gray-500 leading-relaxed bg-gray-50 p-5 rounded-2xl border border-gray-100">
                    <p class="mb-2">
                        {{ $pageInfo['description'] }}
                    </p>
                    <p class="text-xs">
                        現在 <strong class="text-blue-600">{{ number_format($pagination['total']) }}台</strong> の {{ $pageInfo['target_name'] }} が掲載されています。
                        最新の在庫状況や価格相場、年式・走行距離などの詳細スペックを比較して、あなたにピッタリの1台を見つけましょう。
                    </p>
                </div>
            </div>
        </div>

        <div class="py-8">
            <div class="max-w-7xl mx-auto px-4">

                {{-- 結果件数 --}}
                <div class="mb-6 flex items-baseline gap-2">
                    <span class="text-2xl font-black text-black">{{ number_format($pagination['total']) }}</span>
                    <span class="text-sm font-bold text-gray-500">台の車両がヒットしました</span>
                </div>

                {{-- 車両グリッド --}}
                <div id="results-grid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                    @forelse ($items as $listing)
                        @include('bikes.partials.bike_card', ['listing' => $listing, 'isFirstView' => $loop->index < 4])
                    @empty
                        <div class="col-span-full py-20 text-center bg-white rounded-3xl border border-gray-100 shadow-sm">
                            <i data-lucide="search-x" class="w-16 h-16 text-gray-200 mx-auto mb-4"></i>
                            <h3 class="text-lg font-black text-gray-800 mb-2">現在、この条件の車両は在庫切れです</h3>
                            <p class="text-gray-400 font-bold text-sm mb-6">条件を少し広げて探してみましょう。</p>

                            <a href="{{ route('bikes.search') }}" class="inline-flex items-center gap-2 bg-blue-600 text-white px-6 py-3 rounded-xl font-bold text-sm hover:bg-blue-700 transition-colors shadow-md">
                                <i data-lucide="search" class="w-4 h-4"></i> 全車両を検索する
                            </a>
                        </div>
                    @endforelse
                </div>

                {{-- ページネーション --}}
                @if($pagination['last_page'] > 1)
                <div class="mt-16 flex justify-center">
                    <div class="flex items-center gap-2">
                        @if($pagination['prev_url'])
                            <a href="{{ $pagination['prev_url'] }}" class="w-10 h-10 flex items-center justify-center rounded-lg bg-white border border-gray-200 hover:border-black transition"><i data-lucide="chevron-left" class="w-4 h-4"></i></a>
                        @endif
                        @if(isset($pagination['pages']))
                            @foreach($pagination['pages'] as $page)
                                @if($page['is_dot'])
                                    <span class="px-1 text-gray-300">...</span>
                                @else
                                    <a href="{{ $page['url'] }}" class="w-10 h-10 flex items-center justify-center rounded-lg font-black text-sm transition {{ $page['is_active'] ? 'bg-black text-white shadow-lg' : 'bg-white border border-gray-200 text-gray-400 hover:border-black' }}">
                                        {{ $page['label'] }}
                                    </a>
                                @endif
                            @endforeach
                        @endif
                        @if($pagination['next_url'])
                            <a href="{{ $pagination['next_url'] }}" class="w-10 h-10 flex items-center justify-center rounded-lg bg-white border border-gray-200 hover:border-black transition"><i data-lucide="chevron-right" class="w-4 h-4"></i></a>
                        @endif
                    </div>
                </div>
                @endif

                {{-- 関連カタログページへのリンク --}}
                <div class="mt-20 pt-10 border-t border-gray-200">
                    <h3 class="text-lg font-black text-gray-900 mb-6 flex items-center gap-2">
                        <i data-lucide="link-2" class="w-5 h-5 text-blue-500"></i>
                        関連する条件から探す
                    </h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        {{-- メーカー別カタログ --}}
                        <div>
                            <h4 class="text-xs font-black text-gray-400 uppercase tracking-widest mb-3 border-b border-gray-100 pb-2">メーカーから探す</h4>
                            <ul class="space-y-2">
                                @foreach(['honda' => 'ホンダ', 'yamaha' => 'ヤマハ', 'kawasaki' => 'カワサキ', 'suzuki' => 'スズキ'] as $mfrSlug => $mfrName)
                                    <li><a href="{{ route('bikes.catalog', $mfrSlug) }}" class="text-sm font-bold text-gray-600 hover:text-blue-600 hover:underline">▶︎ {{ $mfrName }}の中古バイク</a></li>
                                @endforeach
                            </ul>
                        </div>

                        {{-- 排気量別カタログ --}}
                        <div>
                            <h4 class="text-xs font-black text-gray-400 uppercase tracking-widest mb-3 border-b border-gray-100 pb-2">排気量から探す</h4>
                            <ul class="space-y-2">
                                <li><a href="{{ route('bikes.catalog', '50cc') }}" class="text-sm font-bold text-gray-600 hover:text-blue-600 hover:underline">▶︎ 50cc以下（原付）</a></li>
                                <li><a href="{{ route('bikes.catalog', '125cc') }}" class="text-sm font-bold text-gray-600 hover:text-blue-600 hover:underline">▶︎ 125cc以下（小型）</a></li>
                                <li><a href="{{ route('bikes.catalog', '250cc') }}" class="text-sm font-bold text-gray-600 hover:text-blue-600 hover:underline">▶︎ 250ccバイク</a></li>
                                <li><a href="{{ route('bikes.catalog', '400cc') }}" class="text-sm font-bold text-gray-600 hover:text-blue-600 hover:underline">▶︎ 400cc以下（中型）</a></li>
                            </ul>
                        </div>
                    </div>

                    {{-- メーカー×排気量 組み合わせリンク --}}
                    <div class="mt-8">
                        <h4 class="text-xs font-black text-gray-400 uppercase tracking-widest mb-3 border-b border-gray-100 pb-2">メーカー × 排気量で探す</h4>
                        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2">
                            @foreach(['honda' => 'ホンダ', 'yamaha' => 'ヤマハ', 'kawasaki' => 'カワサキ', 'suzuki' => 'スズキ'] as $mSlug => $mName)
                                @foreach([50, 125, 250, 400] as $cc)
                                    <a href="{{ route('bikes.category_displacement', ['mfrSlug' => $mSlug, 'ccSlug' => $cc . 'cc']) }}"
                                       class="text-xs font-bold text-gray-500 hover:text-blue-600 hover:bg-blue-50 px-3 py-2 rounded-lg border border-gray-100 hover:border-blue-200 transition-colors text-center">
                                        {{ $mName }} {{ $cc }}cc
                                    </a>
                                @endforeach
                            @endforeach
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</x-layout>
