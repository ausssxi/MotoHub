<x-layout>
    {{-- SEO用タイトル・説明文の設定 --}}
    <x-slot:title>{{ $pageInfo['title'] }} | MotoHub</x-slot:title>
    <x-slot:metaDescription>{{ $pageInfo['description'] }}</x-slot:metaDescription>

    {{-- ★追加: 在庫0件の場合はGoogleにインデックスさせない（サイト全体の評価低下を防ぐ） --}}
    @if($pagination['total'] === 0)
        <x-slot:head>
            <meta name="robots" content="noindex, follow">
        </x-slot:head>
    @endif

    <x-slot:styles>
        <link rel="stylesheet" href="{{ asset('css/bike-search.css') }}">
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
                        現在、{{ $prefecture }}エリアにて <strong class="text-blue-600">{{ number_format($pagination['total']) }}台</strong> の {{ $pageInfo['target_name'] }} が掲載されています。
                        最新の在庫状況や価格相場、年式・走行距離などの詳細スペックを比較して、あなたにピッタリの1台を見つけましょう。
                        条件をさらに絞り込んで、お得な車両を検索することも可能です。
                    </p>
                </div>
            </div>
        </div>

        <div class="py-8">
            <div class="max-w-7xl mx-auto px-4 flex flex-col lg:flex-row gap-8">
                
                {{-- サイドバー --}}
                <aside class="w-full lg:w-64 flex-shrink-0">
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 sticky top-24">
                        <h3 class="text-xs font-black text-gray-400 uppercase tracking-widest mb-4">エリアを変更する</h3>
                        <div class="grid grid-cols-2 gap-2">
                            @foreach(['東京', '神奈川', '埼玉', '千葉', '大阪', '愛知', '福岡', '北海道'] as $pref)
                                <a href="{{ route('bikes.landing', ['prefecture' => $pref, 'slug' => request()->slug]) }}" 
                                   class="text-xs font-bold text-center py-2.5 rounded-lg border border-gray-100 bg-gray-50 hover:bg-blue-50 hover:border-blue-200 text-gray-600 hover:text-blue-600 transition {{ $prefecture == $pref ? 'bg-blue-600 border-blue-600 text-white hover:bg-blue-700 hover:text-white pointer-events-none shadow-md' : '' }}">
                                    {{ $pref }}
                                </a>
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
                        <span class="text-2xl font-black text-black">{{ number_format($pagination['total']) }}</span>
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

                    {{-- ★追加: SEOクローラー用・回遊率UPの巨大リンクブロック --}}
                    <div class="mt-20 pt-10 border-t border-gray-200">
                        <h3 class="text-lg font-black text-gray-900 mb-6 flex items-center gap-2">
                            <i data-lucide="link-2" class="w-5 h-5 text-blue-500"></i>
                            関連する条件から探す
                        </h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                            {{-- ブロック1: この地域の別のバイク --}}
                            <div>
                                <h4 class="text-xs font-black text-gray-400 uppercase tracking-widest mb-3 border-b border-gray-100 pb-2">{{ $prefecture }} のその他のバイク</h4>
                                <ul class="space-y-2">
                                    <li><a href="{{ route('bikes.search', ['prefecture' => $prefecture, 'category_id' => 1]) }}" class="text-sm font-bold text-gray-600 hover:text-blue-600 hover:underline">▶︎ {{ $prefecture }} × ネイキッド</a></li>
                                    <li><a href="{{ route('bikes.search', ['prefecture' => $prefecture, 'category_id' => 2]) }}" class="text-sm font-bold text-gray-600 hover:text-blue-600 hover:underline">▶︎ {{ $prefecture }} × スーパースポーツ</a></li>
                                    <li><a href="{{ route('bikes.search', ['prefecture' => $prefecture, 'category_id' => 3]) }}" class="text-sm font-bold text-gray-600 hover:text-blue-600 hover:underline">▶︎ {{ $prefecture }} × アメリカン/クルーザー</a></li>
                                    <li><a href="{{ route('bikes.search', ['prefecture' => $prefecture, 'category_id' => 4]) }}" class="text-sm font-bold text-gray-600 hover:text-blue-600 hover:underline">▶︎ {{ $prefecture }} × オフロード</a></li>
                                </ul>
                            </div>
                            
                            {{-- ブロック2: 排気量別 --}}
                            <div>
                                <h4 class="text-xs font-black text-gray-400 uppercase tracking-widest mb-3 border-b border-gray-100 pb-2">{{ $prefecture }} の排気量別</h4>
                                <ul class="space-y-2">
                                    <li><a href="{{ route('bikes.search', ['prefecture' => $prefecture, 'max_displacement' => 50]) }}" class="text-sm font-bold text-gray-600 hover:text-blue-600 hover:underline">▶︎ {{ $prefecture }} × 50cc以下（原付）</a></li>
                                    <li><a href="{{ route('bikes.search', ['prefecture' => $prefecture, 'min_displacement' => 51, 'max_displacement' => 125]) }}" class="text-sm font-bold text-gray-600 hover:text-blue-600 hover:underline">▶︎ {{ $prefecture }} × 51cc〜125cc（小型）</a></li>
                                    <li><a href="{{ route('bikes.search', ['prefecture' => $prefecture, 'min_displacement' => 126, 'max_displacement' => 400]) }}" class="text-sm font-bold text-gray-600 hover:text-blue-600 hover:underline">▶︎ {{ $prefecture }} × 126cc〜400cc（中型）</a></li>
                                    <li><a href="{{ route('bikes.search', ['prefecture' => $prefecture, 'min_displacement' => 401]) }}" class="text-sm font-bold text-gray-600 hover:text-blue-600 hover:underline">▶︎ {{ $prefecture }} × 401cc以上（大型）</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</x-layout>