<x-layout>
    {{-- SEO用タイトル・説明文の設定（KPIデータを組み込み） --}}
    @php
        $kpiCount = $landingKpi['total_count'] ?? $pagination['total'] ?? 0;
        $kpiAvg = $landingKpi['avg_price'] ?? null;
        $kpiMin = $landingKpi['min_price'] ?? null;

        // title: 車種名を先頭 + 台数・相場を付与（60文字以内を意識）
        $seoTitle = $pageInfo['target_name'] . 'の中古バイク(' . $prefecture . ')';
        if ($kpiCount > 0 && $kpiAvg) {
            $seoTitle .= ' | ' . number_format($kpiCount) . '台掲載・相場' . $kpiAvg . '万円';
        } elseif ($kpiCount > 0) {
            $seoTitle .= ' | ' . number_format($kpiCount) . '台掲載';
        }
        $seoTitle .= ' | MotoHub';

        // description: 具体的な数字入り（120文字以内）
        if ($kpiCount > 0 && $kpiMin) {
            $seoDescription = $pageInfo['target_name'] . 'の中古バイクを' . $prefecture . 'で探すならMotoHub。'
                . number_format($kpiCount) . '台掲載中、価格' . $kpiMin . '万円〜。年式・走行距離・価格で比較できます。';
        } else {
            $seoDescription = $pageInfo['description'];
        }
    @endphp
    <x-slot:title>{{ $seoTitle }}</x-slot:title>
    <x-slot:metaDescription>{{ $seoDescription }}</x-slot:metaDescription>

    {{-- noindex閾値: 車種(model)ページは5台、メーカー/カテゴリ/排気量は10台。
         sitemap(GenerateSitemap)の収録閾値と必ず一致させること（薄いページをsitemapに載せない）。 --}}
    @php $noindexThreshold = (($pageInfo['type'] ?? '') === 'model') ? 5 : 10; @endphp
    @if($kpiCount < $noindexThreshold)
        <x-slot:robotsMeta>noindex, follow</x-slot:robotsMeta>
    @endif

    <x-slot:styles>
        {{-- CSSの非同期読み込み（レンダリングブロック完全解除） --}}
        <link rel="preload" href="{{ asset('css/bike-search.css') }}?v={{ asset_buster(public_path('css/bike-search.css')) }}" as="style" onload="this.onload=null;this.rel='stylesheet'">
        <noscript><link rel="stylesheet" href="{{ asset('css/bike-search.css') }}?v={{ asset_buster(public_path('css/bike-search.css')) }}"></noscript>

        {{-- JSON-LD ��ンくずリスト --}}
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
                    "name": "{{ $pageInfo['target_name'] }}",
                    "item": "{{ url()->current() }}"
                }
            ]
        }
        </script>

        {{-- JSON-LD ItemList（最大10件） --}}
        @if(!empty($items) && count($items) > 0)
        <script type="application/ld+json">
        {
            "@@context": "https://schema.org",
            "@@type": "ItemList",
            "name": "{{ $pageInfo['target_name'] }}の中古バイク（{{ $prefecture }}）",
            "numberOfItems": {{ $kpiCount }},
            "itemListElement": [
                @foreach(collect($items)->take(10) as $i => $item)
                {
                    "@@type": "ListItem",
                    "position": {{ $i + 1 }},
                    "item": {
                        "@@type": "Product",
                        "name": "{{ $item['title'] ?? ($item->title ?? '') }}",
                        "url": "{{ route('bikes.show', ['id' => $item['id'] ?? ($item->id ?? 0)]) }}"
                        @if(($item['total_price'] ?? ($item->total_price ?? 0)) > 0)
                        ,"offers": {
                            "@@type": "Offer",
                            "price": "{{ $item['total_price'] ?? $item->total_price }}",
                            "priceCurrency": "JPY",
                            "availability": "https://schema.org/InStock"
                        }
                        @endif
                    }
                }@if(!$loop->last),@endif
                @endforeach
            ]
        }
        </script>
        @endif
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
        
        {{-- SEOヘッダーセクション（コンテンツのリッチ化） --}}
        <div class="bg-white border-b border-gray-200 pt-8 pb-10 px-4">
            <div class="max-w-7xl mx-auto">
                {{-- パンくずリスト（クローラーの重要な道標） --}}
                <nav class="flex text-xs font-bold text-gray-400 mb-6" aria-label="Breadcrumb">
                    <ol class="flex items-center space-x-2">
                        <li><a href="/" class="hover:text-gray-600 transition-colors">HOME</a></li>
                        <li><span class="text-gray-300">＞</span></li>
                        <li><a href="{{ route('bikes.prefectures') }}" class="hover:text-gray-600 transition-colors">地域から探す</a></li>
                        <li><span class="text-gray-300">＞</span></li>
                        <li><a href="{{ route('bikes.search', ['prefecture' => $prefecture]) }}" class="hover:text-gray-600 transition-colors">{{ $prefecture }}</a></li>
                        <li><span class="text-gray-300">＞</span></li>
                        <li><span class="text-gray-800">{{ $pageInfo['target_name'] }}</span></li>
                    </ol>
                </nav>

                {{-- H1見出し --}}
                <h1 class="text-2xl sm:text-3xl font-black text-gray-900 mb-3 tracking-tight">
                    {!! $pageInfo['h1_html'] !!}
                </h1>
                
                {{-- ★追加: コンテンツ量を水増しし、独自性を高めるテキスト --}}
                <div class="prose prose-sm max-w-4xl text-gray-500 leading-relaxed bg-gray-50 p-5 rounded-2xl border border-gray-100">
                    <p class="mb-2">
                        {{ $pageInfo['description'] }}
                    </p>
                    <p class="text-xs">
                        現在、{{ $prefecture }}エリアにて <strong class="text-blue-600">{{ number_format($landingKpi['total_count'] ?? $pagination['total']) }}台</strong> の {{ $pageInfo['target_name'] }} が掲載されています。
                        最新の在庫状況や価格相場、年式・走行距離などの詳細スペックを比較して、あなたにピッタリの1台を見つけましょう。
                        条件をさらに絞り込んで、お得な車両を検索することも可能です。
                    </p>
                </div>
            </div>
        </div>

        {{-- KPIブロック --}}
        @if(($landingKpi['total_count'] ?? 0) > 0)
        <div class="bg-white border-b border-gray-100">
            <div class="max-w-7xl mx-auto px-4 py-6">
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
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
                        <div class="text-xs font-bold text-gray-400 mb-1">平均走行距離</div>
                        <div class="text-2xl font-black text-gray-700">{{ $landingKpi['avg_mileage'] ?? '-' }}<span class="text-sm font-bold text-gray-400 ml-1">万km</span></div>
                    </div>
                    <div class="bg-gray-50 rounded-2xl p-4 text-center">
                        <div class="text-xs font-bold text-gray-400 mb-1">平均年式</div>
                        <div class="text-2xl font-black text-gray-700">{{ $landingKpi['avg_year'] ?? '-' }}<span class="text-sm font-bold text-gray-400 ml-1">年</span></div>
                    </div>
                    <div class="bg-gray-50 rounded-2xl p-4 text-center">
                        <div class="text-xs font-bold text-gray-400 mb-1">{{ ($landingKpi['kpi_mode'] ?? '') === 'model_year' ? '最多年式' : '人気No.1' }}</div>
                        <div class="text-lg font-black text-gray-900 truncate">{{ $landingKpi['top_model'] ?? '-' }}</div>
                    </div>
                </div>
            </div>
        </div>
        @endif

        {{-- TOP3ブロック（車種ページ=年式別、それ以外=車種別） --}}
        @if(!empty($landingKpi['top_models']))
        <div class="bg-white border-b border-gray-100">
            <div class="max-w-7xl mx-auto px-4 py-6">
                <h2 class="text-base font-black text-gray-900 mb-4 flex items-center gap-2">
                    <i data-lucide="trophy" class="w-5 h-5 text-yellow-500"></i>
                    @if(($landingKpi['kpi_mode'] ?? '') === 'model_year')
                        {{ $prefecture }}の{{ $pageInfo['target_name'] }} 年式別 TOP3
                    @else
                        {{ $prefecture }}の{{ $pageInfo['target_name'] }} 人気車種 TOP3
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
                    <h2 class="text-base font-black text-gray-900 mb-4">💰 価格帯分布</h2>
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

        {{-- 年式分布ブロック --}}
        @if(!empty($landingKpi['year_distribution']))
        <div class="bg-white border-b border-gray-100">
            <div class="max-w-7xl mx-auto px-4 py-6">
                <div class="bg-gray-50 rounded-2xl p-6">
                    <h2 class="text-base font-black text-gray-900 mb-4">📅 年式分布</h2>
                    <div class="space-y-3">
                        @foreach($landingKpi['year_distribution'] as $band)
                        <div class="flex items-center gap-3">
                            <div class="w-24 text-xs font-bold text-gray-600 text-right flex-shrink-0">{{ $band['label'] }}</div>
                            <div class="flex-1 bg-gray-200 rounded-full h-5 overflow-hidden">
                                <div class="h-full rounded-full {{ $band['is_max'] ? 'bg-green-600' : 'bg-green-500' }}" style="width: {{ $band['bar_width'] }}%"></div>
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
                        <h3 class="text-xs font-black text-gray-400 uppercase tracking-widest mb-4">エリアを変更する</h3>
                        <div class="grid grid-cols-2 gap-2">
                            @foreach(['東京', '神奈川', '埼玉', '千葉', '大阪', '愛知', '福岡', '北海道'] as $pref)
                                @if($prefecture == $pref)
                                    <span class="text-xs font-bold text-center py-2.5 rounded-lg border border-blue-200 bg-blue-100 text-blue-700 shadow-sm cursor-default">
                                        {{ $pref }}
                                    </span>
                                @else
                                    <a href="{{ route('bikes.landing', ['prefecture' => $pref, 'slug' => request()->slug]) }}"
                                       class="text-xs font-bold text-center py-2.5 rounded-lg border border-gray-100 bg-gray-50 hover:bg-blue-50 hover:border-blue-200 text-gray-600 hover:text-blue-600 transition">
                                        {{ $pref }}
                                    </a>
                                @endif
                            @endforeach
                        </div>
                        <div class="mt-4 pt-4 border-t border-gray-100 text-center">
                            <a href="{{ route('bikes.prefectures') }}" class="text-xs font-bold text-blue-600 hover:underline flex items-center justify-center gap-1">
                                <i data-lucide="map" class="w-3 h-3"></i> すべての地域を見る
                            </a>
                        </div>
                    </div>
                </aside>

                {{-- メインコンテンツ（検索結果一覧） --}}
                <div class="flex-1">
                    {{-- 結果件数 --}}
                    <div class="mb-6 flex items-baseline gap-2">
                        <span class="text-2xl font-black text-black">{{ number_format($landingKpi['total_count'] ?? $pagination['total']) }}</span>
                        <span class="text-sm font-bold text-gray-500">台の車両がヒットしました</span>
                    </div>

                    {{-- 車両グリッド --}}
                    <div id="results-grid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                        @forelse ($items as $listing)
                            {{-- ★修正: 最初の4件（0〜3）は 'isFirstView' => true として高速読み込みさせる --}}
                            @include('bikes.partials.bike_card', ['listing' => $listing, 'isFirstView' => $loop->index < 4])
                        @empty
                            <div class="col-span-full py-20 text-center bg-white rounded-3xl border border-gray-100 shadow-sm">
                                <i data-lucide="search-x" class="w-16 h-16 text-gray-200 mx-auto mb-4"></i>
                                <h3 class="text-lg font-black text-gray-800 mb-2">現在、この条件の車両は在庫切れです</h3>
                                <p class="text-gray-400 font-bold text-sm mb-6">別の地域や、条件を少し広げて探してみましょう。</p>
                                
                                <div class="flex flex-wrap justify-center gap-3">
                                    <a href="{{ route('bikes.search', ['prefecture' => $prefecture]) }}" class="inline-flex items-center gap-2 bg-blue-600 text-white px-6 py-3 rounded-xl font-bold text-sm hover:bg-blue-700 transition-colors shadow-md">
                                        <i data-lucide="map-pin" class="w-4 h-4"></i> {{ $prefecture }}の全車両を見る
                                    </a>
                                    <a href="{{ route('bikes.search', ['keyword' => request()->slug]) }}" class="inline-flex items-center gap-2 bg-gray-900 text-white px-6 py-3 rounded-xl font-bold text-sm hover:bg-gray-800 transition-colors shadow-md">
                                        <i data-lucide="globe" class="w-4 h-4"></i> 全国の {{ $pageInfo['target_name'] }} を探す
                                    </a>
                                </div>
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

                    {{-- 地域相場の一文（モデルLP・県が属するブロックがrobustな時のみ。薄い断定は出さない） --}}
                    @if(!empty($regionalNote['text']) || !empty($regionalNote['region_price_url']))
                    <div class="mt-10 bg-green-50 rounded-2xl p-5 border border-green-100">
                        @if(!empty($regionalNote['text']))
                        <p class="text-sm text-gray-700 leading-relaxed flex items-start gap-2">
                            <i data-lucide="map-pinned" class="w-4 h-4 mt-0.5 text-green-600 shrink-0"></i>
                            <span>{{ $regionalNote['text'] }}</span>
                        </p>
                        @endif
                        @if(!empty($regionalNote['region_price_url']))
                        <a href="{{ $regionalNote['region_price_url'] }}" class="inline-flex items-center gap-1.5 mt-3 text-sm font-bold text-green-700 hover:text-green-900 transition-colors">
                            エリア別相場をくわしく見る
                            <i data-lucide="arrow-right" class="w-4 h-4"></i>
                        </a>
                        @endif
                    </div>
                    @endif

                    {{-- 車種ページ導線（モデルLPのみ・詳細/相場/スペックへ） --}}
                    @if(($pageInfo['type'] ?? '') === 'model' && !empty($pageInfo['model_url']))
                    <div class="mt-12">
                        <a href="{{ $pageInfo['model_url'] }}" class="inline-flex items-center gap-2 text-sm font-black text-blue-600 hover:text-blue-800 hover:underline">
                            ▶︎ {{ $pageInfo['target_name'] }}の詳細・相場・スペックを見る
                            <i data-lucide="arrow-right" class="w-4 h-4"></i>
                        </a>
                    </div>
                    @endif

                    {{-- 関連リンクブロック（動的生成） --}}
                    @if(!empty($relatedLinks['same_area']) || !empty($relatedLinks['other_areas']))
                    <div class="mt-20 pt-10 border-t border-gray-200">
                        <h3 class="text-lg font-black text-gray-900 mb-6 flex items-center gap-2">
                            <i data-lucide="link-2" class="w-5 h-5 text-blue-500"></i>
                            関連する条件から探す
                        </h3>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                            {{-- ブロック1: 同地域の他メーカー/カテゴリ --}}
                            @if(!empty($relatedLinks['same_area']))
                            <div>
                                <h4 class="text-xs font-black text-gray-400 uppercase tracking-widest mb-3 border-b border-gray-100 pb-2">{{ $prefecture }} のその他のバイク</h4>
                                <ul class="space-y-2">
                                    @foreach($relatedLinks['same_area'] as $link)
                                    <li>
                                        <a href="{{ $link['url'] }}" class="text-sm font-bold text-gray-600 hover:text-blue-600 hover:underline flex items-center gap-2">
                                            ▶︎ {{ $link['label'] }}
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-gray-100 text-gray-500">{{ number_format($link['count']) }}台</span>
                                        </a>
                                    </li>
                                    @endforeach
                                </ul>
                            </div>
                            @endif

                            {{-- ブロック2: 同ターゲットの他地域 --}}
                            @if(!empty($relatedLinks['other_areas']))
                            <div>
                                <h4 class="text-xs font-black text-gray-400 uppercase tracking-widest mb-3 border-b border-gray-100 pb-2">他の地域で探す</h4>
                                <ul class="space-y-2">
                                    @foreach($relatedLinks['other_areas'] as $link)
                                    <li>
                                        <a href="{{ $link['url'] }}" class="text-sm font-bold text-gray-600 hover:text-blue-600 hover:underline flex items-center gap-2">
                                            ▶︎ {{ $link['label'] }}
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

                    {{-- カテゴリLP導線 --}}
                    <div class="mt-10 pt-6 border-t border-gray-100">
                        <h4 class="text-xs font-black text-gray-400 uppercase tracking-widest mb-3">カテゴリ別で探す</h4>
                        <div class="flex flex-wrap gap-2">
                            @foreach([
                                '50' => '50cc以下',
                                '125' => '51〜125cc',
                                '250' => '126〜250cc',
                                '400' => '251〜400cc',
                                '750' => '401〜750cc',
                                'over750' => '751cc以上',
                            ] as $ccSlug => $ccLabel)
                                <a href="{{ route('bikes.category_cc', ['slug' => $ccSlug]) }}"
                                   class="px-3 py-1.5 rounded-lg bg-gray-50 border border-gray-100 text-xs font-bold text-gray-600 hover:bg-blue-50 hover:border-blue-200 hover:text-blue-600 transition-colors">
                                    {{ $ccLabel }}
                                </a>
                            @endforeach
                        </div>
                        <div class="flex flex-wrap gap-2 mt-3">
                            @foreach([
                                'naked' => 'ネイキッド',
                                'scooter' => 'スクーター',
                                'american' => 'アメリカン',
                                'sport' => 'スポーツ',
                                'offroad' => 'オフロード',
                                'tourer' => 'ツアラー',
                                'classic' => 'クラシック',
                                'adventure' => 'アドベンチャー',
                            ] as $typeSlug => $typeLabel)
                                <a href="{{ route('bikes.category_type', ['slug' => $typeSlug]) }}"
                                   class="px-3 py-1.5 rounded-lg bg-gray-50 border border-gray-100 text-xs font-bold text-gray-600 hover:bg-blue-50 hover:border-blue-200 hover:text-blue-600 transition-colors">
                                    {{ $typeLabel }}
                                </a>
                            @endforeach
                        </div>
                    </div>

                </div>
            </div>
        </div>

        {{-- SEOテキスト（導入文） --}}
        @if(($landingKpi['total_count'] ?? 0) > 0)
        <div class="bg-white border-t border-gray-100">
            <div class="max-w-7xl mx-auto px-4 py-10">
                <h2 class="text-lg font-black text-gray-900 mb-4">{{ $prefecture }}で{{ $pageInfo['target_name'] }}をお探しの方へ</h2>
                <div class="prose prose-sm max-w-none text-gray-600 leading-relaxed">
                    <p>
                        {{ $prefecture }}の{{ $pageInfo['target_name'] }}中古バイクは現在{{ number_format($landingKpi['total_count']) }}台掲載中。
                        平均価格は{{ $landingKpi['avg_price'] ?? '-' }}万円、最安値は{{ $landingKpi['min_price'] ?? '-' }}万円からご覧いただけます。
                        @if($landingKpi['avg_mileage'] ?? null)
                            平均走行距離は{{ $landingKpi['avg_mileage'] }}万kmです。
                        @endif
                    </p>
                    <p class="mt-2">
                        MotoHubでは複数のバイクショップの在庫を一括で比較・検討でき、価格・走行距離・年式で並び替えが可能です。
                        気になるバイクはお気に入りに保存して、じっくり��較検討してください。
                    </p>
                </div>
            </div>
        </div>
        @endif
    </div>
</x-layout>