<x-layout>
    {{-- SEO用タイトル・説明文の設定 --}}
    <x-slot:title>{{ $pageInfo['title'] }} | {{ number_format($landingKpi['total_count'] ?? 0) }}台掲載 | MotoHub</x-slot:title>
    <x-slot:metaDescription>{{ $pageInfo['description'] }}</x-slot:metaDescription>

    @if(($landingKpi['total_count'] ?? 0) < 10)
        <x-slot:robotsMeta>noindex, follow</x-slot:robotsMeta>
    @endif

    <x-slot:styles>
        <link rel="preload" href="{{ asset('css/bike-search.css') }}?v={{ asset_buster(public_path('css/bike-search.css')) }}" as="style" onload="this.onload=null;this.rel='stylesheet'">
        <noscript><link rel="stylesheet" href="{{ asset('css/bike-search.css') }}?v={{ asset_buster(public_path('css/bike-search.css')) }}"></noscript>

        {{-- JSON-LD パンくずリスト（@をBladeディレクティブと誤認させないため@@でエスケープ） --}}
        <script type="application/ld+json">
        {
            "@@context": "https://schema.org",
            "@@type": "BreadcrumbList",
            "itemListElement": [
                {
                    "@@type": "ListItem",
                    "position": 1,
                    "name": "HOME",
                    "item": "{{ url('/') }}"
                },
                {
                    "@@type": "ListItem",
                    "position": 2,
                    "name": "地域から探す",
                    "item": "{{ route('bikes.prefectures') }}"
                },
                {
                    "@@type": "ListItem",
                    "position": 3,
                    "name": "{{ $prefecture }}",
                    "item": "{{ route('bikes.search', ['prefecture' => $prefecture]) }}"
                },
                {
                    "@@type": "ListItem",
                    "position": 4,
                    "name": "{{ $city }}",
                    "item": "{{ url()->current() }}"
                }
            ]
        }
        </script>
    </x-slot:styles>

    <x-slot:scripts>
        <script src="{{ asset('js/search/sidebar.js') }}?v={{ asset_buster(public_path('js/search/sidebar.js')) }}" defer></script>
        <script src="{{ asset('js/compare/manager.js') }}?v={{ asset_buster(public_path('js/compare/manager.js')) }}" defer></script>
        <script src="{{ asset('js/compare/ui.js') }}?v={{ asset_buster(public_path('js/compare/ui.js')) }}" defer></script>
    </x-slot:scripts>

    <x-slot:navigation>
        <x-navigation :showSearch="true" />
    </x-slot:navigation>

    <div class="bg-gray-50 min-h-screen">

        {{-- SEOヘッダーセクション --}}
        <div class="bg-white border-b border-gray-200 pt-8 pb-10 px-4">
            <div class="max-w-7xl mx-auto">
                {{-- パンくずリスト: HOME > 地域から探す > {都道府県} > {市区町村} > {メーカー/車種名} --}}
                <nav class="flex text-xs font-bold text-gray-400 mb-6" aria-label="Breadcrumb">
                    <ol class="flex items-center space-x-2">
                        <li><a href="/" class="hover:text-gray-600 transition-colors">HOME</a></li>
                        <li><span class="text-gray-300">＞</span></li>
                        <li><a href="{{ route('bikes.prefectures') }}" class="hover:text-gray-600 transition-colors">地域から探す</a></li>
                        <li><span class="text-gray-300">＞</span></li>
                        <li><a href="{{ route('bikes.search', ['prefecture' => $prefecture]) }}" class="hover:text-gray-600 transition-colors">{{ $prefecture }}</a></li>
                        <li><span class="text-gray-300">＞</span></li>
                        <li><span class="text-gray-500">{{ $city }}</span></li>
                        <li><span class="text-gray-300">＞</span></li>
                        <li><span class="text-gray-800">{{ $pageInfo['target_name'] }}</span></li>
                    </ol>
                </nav>

                {{-- H1見出し --}}
                <h1 class="text-2xl sm:text-3xl font-black text-gray-900 mb-3 tracking-tight">
                    {!! $pageInfo['h1_html'] !!}
                </h1>

                <div class="prose prose-sm max-w-4xl text-gray-500 leading-relaxed bg-gray-50 p-5 rounded-2xl border border-gray-100">
                    <p class="mb-2">
                        {{ $pageInfo['description'] }}
                    </p>
                    <p class="text-xs">
                        現在、{{ $prefecture }}{{ $city }}エリアにて <strong class="text-blue-600">{{ number_format($landingKpi['total_count'] ?? 0) }}台</strong> の {{ $pageInfo['target_name'] }} が掲載されています。
                        最新の在庫状況や価格相場、年式・走行距離などの詳細スペックを比較して、あなたにピッタリの1台を見つけましょう。
                    </p>
                </div>
            </div>
        </div>

        {{-- KPIブロック --}}
        @if(($landingKpi['total_count'] ?? 0) > 0)
        <div class="bg-white border-b border-gray-100">
            <div class="max-w-7xl mx-auto px-4 py-6">
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="bg-gray-50 rounded-2xl p-4 text-center">
                        <div class="text-xs font-bold text-gray-400 mb-1">掲載台数</div>
                        <div class="text-2xl font-black text-gray-900">{{ number_format($landingKpi['total_count']) }}<span class="text-sm font-bold text-gray-400 ml-1">台</span></div>
                    </div>
                    <div class="bg-gray-50 rounded-2xl p-4 text-center">
                        <div class="text-xs font-bold text-gray-400 mb-1">平均価格</div>
                        <div class="text-2xl font-black text-blue-600">{{ $landingKpi['avg_price'] ?? '-' }}<span class="text-sm font-bold text-gray-400 ml-1">万円</span></div>
                    </div>
                    <div class="bg-gray-50 rounded-2xl p-4 text-center">
                        <div class="text-xs font-bold text-gray-400 mb-1">最安値</div>
                        <div class="text-2xl font-black text-green-600">{{ $landingKpi['min_price'] ?? '-' }}<span class="text-sm font-bold text-gray-400 ml-1">万円</span></div>
                    </div>
                    <div class="bg-gray-50 rounded-2xl p-4 text-center">
                        <div class="text-xs font-bold text-gray-400 mb-1">{{ ($landingKpi['kpi_mode'] ?? '') === 'model_year' ? '最多年式' : '人気No.1' }}</div>
                        <div class="text-lg font-black text-gray-900 truncate">{{ $landingKpi['top_model'] ?? '-' }}</div>
                    </div>
                </div>
            </div>
        </div>
        @endif

        {{-- TOP3ブロック --}}
        @if(!empty($landingKpi['top_models']))
        <div class="bg-white border-b border-gray-100">
            <div class="max-w-7xl mx-auto px-4 py-6">
                <h2 class="text-base font-black text-gray-900 mb-4 flex items-center gap-2">
                    <i data-lucide="trophy" class="w-5 h-5 text-yellow-500"></i>
                    @if(($landingKpi['kpi_mode'] ?? '') === 'model_year')
                        {{ $city }}の{{ $pageInfo['target_name'] }} 年式別 TOP3
                    @else
                        {{ $city }}の{{ $pageInfo['target_name'] }} 人気車種 TOP3
                    @endif
                </h2>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    @foreach($landingKpi['top_models'] as $i => $model)
                    <div class="flex items-center gap-4 bg-gray-50 rounded-2xl p-4">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0 font-black text-white {{ $i === 0 ? 'bg-yellow-500' : ($i === 1 ? 'bg-gray-400' : 'bg-amber-700') }}">
                            {{ $i + 1 }}
                        </div>
                        <div class="min-w-0">
                            <div class="font-black text-gray-900 text-sm truncate">{{ $model['name'] }}</div>
                            <div class="text-xs text-gray-500 font-bold">
                                {{ $model['count'] }}台掲載
                                @if($model['avg_price'])
                                    / 平均{{ $model['avg_price'] }}万円
                                @endif
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif

        {{-- 価格帯分布ブロック --}}
        @if(!empty($landingKpi['price_distribution']))
        <div class="bg-white border-b border-gray-100">
            <div class="max-w-7xl mx-auto px-4 py-6">
                <div class="bg-gray-50 rounded-2xl p-6">
                    <h2 class="text-base font-black text-gray-900 mb-4">価格帯分布</h2>
                    <div class="space-y-3">
                        @foreach($landingKpi['price_distribution'] as $band)
                        <div class="flex items-center gap-3">
                            <div class="w-24 text-xs font-bold text-gray-600 text-right flex-shrink-0">{{ $band['label'] }}</div>
                            <div class="flex-1 bg-gray-200 rounded-full h-5 overflow-hidden">
                                <div class="h-full rounded-full {{ $band['is_max'] ? 'bg-blue-600' : 'bg-blue-500' }}" style="width: {{ $band['bar_width'] }}%"></div>
                            </div>
                            <div class="w-16 text-xs font-bold text-gray-500 flex-shrink-0">{{ number_format($band['count']) }}台</div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
        @endif

        <div class="py-8">
            <div class="max-w-7xl mx-auto px-4 flex flex-col lg:flex-row gap-8">

                {{-- サイドバー --}}
                <aside class="w-full lg:w-64 flex-shrink-0">
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 sticky top-24">
                        <h3 class="text-xs font-black text-gray-400 uppercase tracking-widest mb-4">並び替え</h3>
                        <div class="grid grid-cols-2 gap-2">
                            @foreach(['latest' => '新着順', 'price_asc' => '安い順', 'price_desc' => '高い順', 'mileage_asc' => '走行距離順'] as $key => $label)
                                @if($sort === $key)
                                    <span class="text-xs font-bold text-center py-2.5 rounded-lg border border-blue-200 bg-blue-100 text-blue-700 shadow-sm cursor-default">
                                        {{ $label }}
                                    </span>
                                @else
                                    <a href="{{ route('bikes.city_landing', ['prefecture' => $prefecture, 'city' => $city, 'slug' => $slug, 'sort' => $key]) }}"
                                       class="text-xs font-bold text-center py-2.5 rounded-lg border border-gray-100 bg-gray-50 hover:bg-blue-50 hover:border-blue-200 text-gray-600 hover:text-blue-600 transition">
                                        {{ $label }}
                                    </a>
                                @endif
                            @endforeach
                        </div>

                        <div class="mt-4 pt-4 border-t border-gray-100">
                            <h3 class="text-xs font-black text-gray-400 uppercase tracking-widest mb-3">都道府県ページ</h3>
                            <a href="{{ route('bikes.landing', ['prefecture' => $prefecture, 'slug' => $slug]) }}"
                               class="text-xs font-bold text-blue-600 hover:underline flex items-center gap-1">
                                <i data-lucide="map-pin" class="w-3 h-3"></i> {{ $prefecture }}全域で探す
                            </a>
                        </div>

                        <div class="mt-4 pt-4 border-t border-gray-100 text-center">
                            <a href="{{ route('bikes.prefectures') }}" class="text-xs font-bold text-blue-600 hover:underline flex items-center justify-center gap-1">
                                <i data-lucide="map" class="w-3 h-3"></i> すべての地域を見る
                            </a>
                        </div>
                    </div>
                </aside>

                {{-- メインコンテンツ --}}
                <div class="flex-1">
                    <div class="mb-6 flex items-baseline gap-2">
                        <span class="text-2xl font-black text-black">{{ number_format($listings->total()) }}</span>
                        <span class="text-sm font-bold text-gray-500">台の車両がヒットしました</span>
                    </div>

                    {{-- 車両グリッド --}}
                    <div id="results-grid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                        @forelse ($listings as $listing)
                            @include('bikes.partials.bike_card', ['listing' => $listing, 'isFirstView' => $loop->index < 4])
                        @empty
                            <div class="col-span-full py-20 text-center bg-white rounded-3xl border border-gray-100 shadow-sm">
                                <i data-lucide="search-x" class="w-16 h-16 text-gray-200 mx-auto mb-4"></i>
                                <h3 class="text-lg font-black text-gray-800 mb-2">現在、この条件の車両は在庫切れです</h3>
                                <p class="text-gray-400 font-bold text-sm mb-6">別の地域や、条件を少し広げて探してみましょう。</p>

                                <div class="flex flex-wrap justify-center gap-3">
                                    <a href="{{ route('bikes.landing', ['prefecture' => $prefecture, 'slug' => $slug]) }}" class="inline-flex items-center gap-2 bg-blue-600 text-white px-6 py-3 rounded-xl font-bold text-sm hover:bg-blue-700 transition-colors shadow-md">
                                        <i data-lucide="map-pin" class="w-4 h-4"></i> {{ $prefecture }}全域で探す
                                    </a>
                                    <a href="{{ route('bikes.search') }}" class="inline-flex items-center gap-2 bg-gray-900 text-white px-6 py-3 rounded-xl font-bold text-sm hover:bg-gray-800 transition-colors shadow-md">
                                        <i data-lucide="globe" class="w-4 h-4"></i> 全国から探す
                                    </a>
                                </div>
                            </div>
                        @endforelse
                    </div>

                    {{-- ページネーション --}}
                    @if($listings->hasPages())
                    <div class="mt-16 flex justify-center">
                        {{ $listings->appends(['sort' => $sort])->links() }}
                    </div>
                    @endif

                    {{-- 関連リンクブロック --}}
                    @if(!empty($relatedLinks['same_city']) || !empty($relatedLinks['other_cities']))
                    <div class="mt-20 pt-10 border-t border-gray-200">
                        <h3 class="text-lg font-black text-gray-900 mb-6 flex items-center gap-2">
                            <i data-lucide="link-2" class="w-5 h-5 text-blue-500"></i>
                            関連する条件から探す
                        </h3>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                            {{-- ブロック1: 同市区町村の他メーカー/車種 --}}
                            @if(!empty($relatedLinks['same_city']))
                            <div>
                                <h4 class="text-xs font-black text-gray-400 uppercase tracking-widest mb-3 border-b border-gray-100 pb-2">{{ $city }} のその他のバイク</h4>
                                <ul class="space-y-2">
                                    @foreach($relatedLinks['same_city'] as $link)
                                    <li>
                                        <a href="{{ $link['url'] }}" class="text-sm font-bold text-gray-600 hover:text-blue-600 hover:underline flex items-center gap-2">
                                            {{ $link['label'] }}
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-gray-100 text-gray-500">{{ number_format($link['count']) }}台</span>
                                        </a>
                                    </li>
                                    @endforeach
                                </ul>
                            </div>
                            @endif

                            {{-- ブロック2: 同都道府県の他市区町村 --}}
                            @if(!empty($relatedLinks['other_cities']))
                            <div>
                                <h4 class="text-xs font-black text-gray-400 uppercase tracking-widest mb-3 border-b border-gray-100 pb-2">{{ $prefecture }} の他の市区町村</h4>
                                <ul class="space-y-2">
                                    @foreach($relatedLinks['other_cities'] as $link)
                                    <li>
                                        <a href="{{ $link['url'] }}" class="text-sm font-bold text-gray-600 hover:text-blue-600 hover:underline flex items-center gap-2">
                                            {{ $link['label'] }}
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-gray-100 text-gray-500">{{ number_format($link['count']) }}台</span>
                                        </a>
                                    </li>
                                    @endforeach
                                </ul>
                            </div>
                            @endif
                        </div>
                    </div>
                    @endif

                </div>
            </div>
        </div>
    </div>
</x-layout>
