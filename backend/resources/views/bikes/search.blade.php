<x-layout>
    {{-- ★ 1. タイトルの動的生成（URLから直接取得する確実な方法） --}}
    <x-slot:title>
        @if(request()->filled('prefecture') && request()->filled('category'))
           【最新】{{ request('prefecture') }}の{{ request('category') }}在庫一覧・中古バイク検索 | MotoHub
        @elseif(request()->filled('prefecture'))
           【最新】{{ request('prefecture') }}の中古・新車バイク在庫一覧 | MotoHub
        @elseif(request()->filled('category'))
           【最新】{{ request('category') }}の中古・新車バイク在庫一覧 | MotoHub
        @else
            {{ $pageTitle }} | MotoHub
        @endif
    </x-slot:title>

    {{-- ★ 2. メタディスクリプション（検索結果の説明文）の強化 --}}
    <x-slot:metaDescription>
        @if(request()->filled('prefecture') && request()->filled('category'))
            {{ request('prefecture') }}で販売中の{{ request('category') }}の中古バイク・新車情報。GooBikeやBDSなど複数サイトの在庫から、価格や年式、走行距離などで絞り込んで比較・検索ができます。あなたにピッタリの1台を見つけましょう！
        @elseif(request()->filled('prefecture'))
            {{ request('prefecture') }}で販売中の中古バイク・新車情報。お住まいの地域にある複数店舗の在庫を一括で比較・検索できます。
        @else
            {{ $pageTitle }}の検索結果ページです。日本最大級のバイク一括検索サイトMotoHubで、全国の中古バイク・新車を価格や排気量、メーカーで条件を絞って比較できます。
        @endif
    </x-slot:metaDescription>

    @if($pagination['total'] === 0)
        <x-slot:robotsMeta>noindex, follow</x-slot:robotsMeta>
    @endif

    <x-slot:canonical>{{ route('bikes.search', request()->only(['keyword', 'manufacturer_id', 'bike_model_id', 'prefecture', 'tag'])) }}</x-slot:canonical>

    <x-slot:styles>
        <x-jsonld.breadcrumb-search :filters="$filters ?? []" :pageTitle="$pageTitle ?? ''" />
        <link rel="stylesheet" href="{{ asset('css/bike-search.css') }}">
    </x-slot:styles>

    <x-slot:scripts>
        <script src="{{ asset('js/search/sidebar.js') }}" defer></script>
        <script src="{{ asset('js/common/custom-dropdown.js') }}" defer></script>
        <script src="{{ asset('js/compare/manager.js') }}?v={{ filemtime(public_path('js/compare/manager.js')) }}" defer></script>
        <script src="{{ asset('js/compare/ui.js') }}?v={{ filemtime(public_path('js/compare/ui.js')) }}" defer></script>
        <script src="{{ asset('js/search/save_condition.js') }}" defer></script>
        <script src="{{ asset('js/search/infinite-scroll.js') }}" defer></script>
        @guest
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                var DISMISS_KEY = 'line_banner_dismissed_at';
                var DISMISS_MS = 7 * 24 * 60 * 60 * 1000;

                function isDismissed() {
                    var ts = localStorage.getItem(DISMISS_KEY);
                    return ts && (Date.now() - parseInt(ts, 10)) < DISMISS_MS;
                }

                function dismissAndHide(el) {
                    localStorage.setItem(DISMISS_KEY, Date.now().toString());
                    el.style.transition = 'opacity .3s, transform .3s';
                    el.style.opacity = '0';
                    el.style.transform = 'translateY(10px)';
                    setTimeout(function() { el.remove(); }, 300);
                }

                // インラインカード（15件目後）
                var inlineCard = document.getElementById('search-line-inline-card');
                if (inlineCard) {
                    if (isDismissed() || document.getElementById('promo-pageview-popup')) {
                        inlineCard.remove();
                    } else {
                        window._dismissLineInlineCard = function() { dismissAndHide(inlineCard); };
                    }
                }

                // 上部バナーコンテナ
                var topBanner = document.getElementById('search-line-banner');
                if (topBanner && isDismissed()) {
                    topBanner.remove();
                } else if (typeof RegistrationPromo !== 'undefined') {
                    RegistrationPromo.showSearchBanner('search-line-banner');
                }
            });
        </script>
        @endguest
    </x-slot:scripts>

    <x-slot:navigation>
        <x-navigation :showSearch="true" :keyword="$keyword" />
    </x-slot:navigation>

    <div class="bg-gray-50 min-h-screen py-8">
        <div class="max-w-7xl mx-auto px-4 flex flex-col lg:flex-row gap-8">
            
            <!-- 1. サイドバー（絞り込み機能） -->
            <aside id="filter-sidebar" class="w-full lg:w-72 flex-shrink-0">
                <div id="filter-overlay" class="lg:hidden"></div>
                <div class="filter-sidebar-container filter-sidebar-content bg-white rounded-2xl shadow-sm border border-gray-100 flex flex-col">
                    <div class="p-5 border-b border-gray-50 flex items-center justify-between">
                        <h3 class="text-xs font-black text-black flex items-center gap-2 uppercase tracking-widest italic">
                            <i data-lucide="filter" class="w-4 h-4 text-blue-500"></i> 絞り込み条件
                        </h3>
                        <div class="flex items-center gap-4">
                            <a href="{{ route('bikes.search', ['keyword' => $keyword]) }}" class="text-[10px] font-bold text-gray-400 hover:text-blue-600 transition-colors uppercase">条件クリア</a>
                            <button id="close-filter" class="lg:hidden p-1 text-gray-400"><i data-lucide="x" class="w-5 h-5"></i></button>
                        </div>
                    </div>

                    <form action="{{ route('bikes.search') }}" method="GET" id="filter-form" class="p-6 space-y-8 overflow-y-auto">
                        <input type="hidden" name="keyword" value="{{ $keyword }}">
                        <input type="hidden" id="sort-hidden-input" name="sort" value="{{ $sort }}">

                        <!-- 人気のこだわり条件（タグ） -->
                        @php $currentTag = request('tag'); @endphp
                        <div class="filter-group">
                            <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest italic mb-3 block">人気のこだわり条件</label>
                            <div class="flex flex-wrap gap-2">
                                @foreach($popularTags as $tag)
                                    @php
                                        $nextTag = ($currentTag === $tag) ? null : $tag;
                                        $url = route('bikes.search', array_merge(request()->except(['page', 'tag']), ['tag' => $nextTag]));
                                    @endphp
                                    <a href="{{ $url }}" 
                                       class="px-3 py-1.5 rounded-lg text-[10px] font-bold transition border {{ ($currentTag === $tag) ? 'bg-blue-50 text-blue-600 border-blue-200 shadow-sm' : 'bg-white text-gray-600 border-gray-100 hover:border-blue-300 hover:bg-blue-50' }}">
                                        #{{ $tag }}
                                    </a>
                                @endforeach
                            </div>
                        </div>

                        <!-- 都道府県 -->
                        <div class="filter-group">
                            <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest italic mb-2 block">地域</label>
                            <div class="relative">
                                <select name="prefecture" class="w-full bg-gray-50 border border-gray-100 rounded-xl px-4 py-3 text-sm font-bold focus:ring-2 focus:ring-blue-500 outline-none appearance-none pr-10">
                                    <option value="">すべての地域</option>
                                    @foreach($prefectures as $pref)
                                        @php
                                            $count = $facets['prefecture'][$pref] ?? 0;
                                            $countText = $count > 0 ? " ({$count}台)" : "";
                                        @endphp
                                        <option value="{{ $pref }}" {{ ($filters['prefecture'] ?? '') == $pref ? 'selected' : '' }}>
                                            {{ $pref }}{{ $countText }}
                                        </option>
                                    @endforeach
                                </select>
                                <i data-lucide="map-pin" class="w-4 h-4 absolute right-3 top-1/2 -translate-y-1/2 text-gray-300 pointer-events-none"></i>
                            </div>
                        </div>

                        <!-- コンディション & 修復歴 -->
                        <div class="space-y-6">
                            <div class="filter-group">
                                <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest italic mb-2 block">コンディション</label>
                                <div class="flex bg-gray-100 p-1 rounded-xl">
                                    @foreach(['' => 'すべて', '0' => '中古', '1' => '新車'] as $val => $label)
                                    @php
                                        $countHtml = '';
                                        if ($val !== '') {
                                            $c = $facets['is_new'][$val] ?? 0;
                                            if($c > 0) $countHtml = "<span class='text-[8px] opacity-70 ml-0.5'>({$c})</span>";
                                        }
                                    @endphp
                                    <label class="flex-1 text-center cursor-pointer">
                                        <input type="radio" name="is_new" value="{{ $val }}" class="hidden peer" 
                                            @checked((string)($filters['is_new'] ?? '') === (string)$val)>
                                        <span class="block py-2 text-[10px] font-black rounded-lg transition peer-checked:bg-white peer-checked:text-blue-600 peer-checked:shadow-sm text-gray-500">
                                            {{ $label }}{!! $countHtml !!}
                                        </span>
                                    </label>
                                    @endforeach
                                </div>
                            </div>

                            <div class="filter-group">
                                <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest italic mb-2 block">修復歴</label>
                                <div class="flex bg-gray-100 p-1 rounded-xl">
                                    @foreach(['' => 'すべて', '0' => 'なし', '1' => 'あり'] as $val => $label)
                                    @php
                                        $countHtml = '';
                                        if ($val !== '') {
                                            $c = $facets['has_repair_history'][$val] ?? 0;
                                            if($c > 0) $countHtml = "<span class='text-[8px] opacity-70 ml-0.5'>({$c})</span>";
                                        }
                                    @endphp
                                    <label class="flex-1 text-center cursor-pointer">
                                        <input type="radio" name="has_repair_history" value="{{ $val }}" class="hidden peer" 
                                            @checked((string)($filters['has_repair_history'] ?? '') === (string)$val)>
                                        <span class="block py-2 text-[10px] font-black rounded-lg transition peer-checked:bg-white peer-checked:text-blue-600 peer-checked:shadow-sm text-gray-500">
                                            {{ $label }}{!! $countHtml !!}
                                        </span>
                                    </label>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        <!-- メーカー・車種 ドリルダウン -->
                        <div class="space-y-4 pt-4 border-t border-gray-50">
                            <div class="filter-group">
                                <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest italic mb-2 block">メーカー</label>
                                <div class="relative">
                                    <select name="manufacturer_id" id="manufacturer-select" class="w-full bg-gray-50 border border-gray-100 rounded-xl px-4 py-3 text-sm font-bold focus:ring-2 focus:ring-blue-500 outline-none appearance-none pr-10">
                                        <option value="">指定なし</option>
                                        @foreach($manufacturers as $m)
                                            <option value="{{ $m->id }}" {{ (string)($filters['manufacturer_id'] ?? '') === (string)$m->id ? 'selected' : '' }}>{{ $m->name }}</option>
                                        @endforeach
                                    </select>
                                    <i data-lucide="chevron-down" class="w-4 h-4 absolute right-3 top-1/2 -translate-y-1/2 text-gray-300 pointer-events-none"></i>
                                </div>
                            </div>

                            <div class="filter-group {{ empty($filters['manufacturer_id']) ? 'opacity-40' : '' }}" id="model-select-container">
                                <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest italic mb-2 block">車種</label>
                                <div class="relative">
                                    <select name="bike_model_id" id="model-select" 
                                            data-selected-id="{{ $filters['bike_model_id'] ?? '' }}"
                                            class="w-full bg-gray-50 border border-gray-100 rounded-xl px-4 py-3 text-sm font-bold focus:ring-2 focus:ring-blue-500 outline-none appearance-none pr-10" {{ empty($filters['manufacturer_id']) ? 'disabled' : '' }}>
                                        <option value="">すべての車種</option>
                                        @foreach($models as $model)
                                            <option value="{{ $model->id }}" {{ (string)($filters['bike_model_id'] ?? '') === (string)$model->id ? 'selected' : '' }}>{{ $model->name }}</option>
                                        @endforeach
                                    </select>
                                    <i data-lucide="chevron-down" class="w-4 h-4 absolute right-3 top-1/2 -translate-y-1/2 text-gray-300 pointer-events-none"></i>
                                </div>
                            </div>
                        </div>

                        <!-- 既存のスライダー (価格・走行距離・年式) -->
                        <div class="space-y-10 pt-4 border-t border-gray-50">
                            <div class="filter-group">
                                <div class="flex justify-between items-end mb-4">
                                    <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest italic tracking-wider">価格</label>
                                    <div class="text-xs font-black text-blue-600 tracking-tighter"><span id="label-min-price"></span> 〜 <span id="label-max-price"></span></div>
                                </div>
                                <div class="range-slider-container" id="slider-price">
                                    <div class="slider-track"></div>
                                    <div class="slider-progress"></div>
                                    <input type="range" class="range-input range-min" name="min_price" min="0" max="{{ $meta['price']['max'] ?? 300 }}" value="{{ $filters['min_price'] ?? 0 }}" step="5">
                                    <input type="range" class="range-input range-max" name="max_price" min="0" max="{{ $meta['price']['max'] ?? 300 }}" value="{{ $filters['max_price'] ?? ($meta['price']['max'] ?? 300) }}" step="5">
                                </div>
                            </div>

                            <div class="filter-group">
                                <div class="flex justify-between items-end mb-4">
                                    <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest italic tracking-wider">走行距離</label>
                                    <div class="text-xs font-black text-blue-600 tracking-tighter"><span id="label-min-mileage"></span> 〜 <span id="label-max-mileage"></span></div>
                                </div>
                                <div class="range-slider-container" id="slider-mileage">
                                    <div class="slider-track"></div>
                                    <div class="slider-progress"></div>
                                    <input type="range" class="range-input range-min" name="min_mileage" min="0" max="{{ $meta['mileage']['max'] ?? 50000 }}" value="{{ $filters['min_mileage'] ?? 0 }}" step="1000">
                                    <input type="range" class="range-input range-max" name="max_mileage" min="0" max="{{ $meta['mileage']['max'] ?? 50000 }}" value="{{ $filters['max_mileage'] ?? ($meta['mileage']['max'] ?? 50000) }}" step="1000">
                                </div>
                            </div>

                            <div class="filter-group">
                                <div class="flex justify-between items-end mb-4">
                                    <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest italic tracking-wider">年式</label>
                                    <div class="text-xs font-black text-blue-600 tracking-tighter"><span id="label-min-year"></span> 〜 <span id="label-max-year"></span></div>
                                </div>
                                <div class="range-slider-container" id="slider-year">
                                    <div class="slider-track"></div>
                                    <div class="slider-progress"></div>
                                    <input type="range" class="range-input range-min" name="min_year" min="{{ $meta['year']['min'] ?? 1990 }}" max="{{ $meta['year']['max'] ?? date('Y') }}" value="{{ $filters['min_year'] ?? ($meta['year']['min'] ?? 1990) }}" step="1">
                                    <input type="range" class="range-input range-max" name="max_year" min="{{ $meta['year']['min'] ?? 1990 }}" max="{{ $meta['year']['max'] ?? date('Y') }}" value="{{ $filters['max_year'] ?? ($meta['year']['max'] ?? date('Y')) }}" step="1">
                                </div>
                            </div>
                        </div>

                        <!-- モバイル用検索ボタン -->
                        <div class="pt-4 lg:hidden">
                            <button type="submit" class="w-full bg-[#5392f9] text-white font-black py-4 rounded-2xl text-[11px] uppercase tracking-widest shadow-xl shadow-blue-100 active:scale-95 transition flex items-center justify-center gap-2">
                                <span>条件を適用する</span>
                                <span class="bg-white/20 px-2 py-0.5 rounded text-[10px] min-w-[3rem]" id="mobile-hit-count">
                                    ({{ number_format($pagination['total']) }}台)
                                </span>
                            </button>
                        </div>

                        <div class="mt-8 pt-6 border-t border-gray-100">
                            <div class="text-center mb-3">
                                <span class="text-[10px] font-bold text-blue-600 bg-blue-50 px-2 py-1 rounded-full">
                                    <i data-lucide="bell" class="w-3 h-3 inline mr-0.5"></i>新着・値下げ通知を受け取る
                                </span>
                            </div>
    
                            @auth
                                {{-- ログイン済み: 条件保存ボタン --}}
                                <button type="button" id="save-search-btn" class="w-full bg-white border-2 border-blue-600 text-blue-600 hover:bg-blue-50 font-black py-3 rounded-xl transition flex items-center justify-center gap-2 shadow-sm group">
                                    <i data-lucide="bookmark" class="w-4 h-4 group-hover:fill-current"></i>
                                    この条件を保存する
                                </button>

                                @if(auth()->user()->hasLineLinked())
                                    {{-- LINE連携済み --}}
                                    <div class="mt-3 flex items-center gap-2 bg-green-50 border border-green-100 rounded-xl px-3 py-2">
                                        <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="#06C755"><path d="M12 2C6.48 2 2 5.88 2 10.54c0 4.07 3.42 7.49 8.05 8.44.31.07.73.21.84.48.1.25.06.63.03.88l-.14.83c-.04.25-.2.97.85.53s5.61-3.31 7.66-5.67C21.03 13.86 22 12.28 22 10.54 22 5.88 17.52 2 12 2z"/></svg>
                                        <span class="text-[10px] font-bold text-green-700">値下げ通知はLINEに届きます</span>
                                    </div>
                                @else
                                    {{-- LINE未連携: 連携を促す --}}
                                    <a href="{{ route('auth.line.redirect') }}" class="mt-3 w-full flex items-center justify-center gap-2 py-2.5 rounded-xl font-bold text-white text-xs shadow-sm transition-colors active:scale-95" style="background-color: #06C755;">
                                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="white"><path d="M12 2C6.48 2 2 5.88 2 10.54c0 4.07 3.42 7.49 8.05 8.44.31.07.73.21.84.48.1.25.06.63.03.88l-.14.83c-.04.25-.2.97.85.53s5.61-3.31 7.66-5.67C21.03 13.86 22 12.28 22 10.54 22 5.88 17.52 2 12 2z"/></svg>
                                        LINE連携で通知をもっと便利に
                                    </a>
                                    <p class="text-[9px] text-gray-400 mt-1.5 text-center">
                                        メール通知に加え、LINEでも即時受信できます
                                    </p>
                                @endif
                            @else
                                {{-- 未ログイン: LINE登録を最優先で表示 --}}
                                <a href="/auth/line/redirect" class="w-full flex items-center justify-center gap-2 py-3 rounded-xl font-bold text-white text-sm shadow-sm transition-colors active:scale-95" style="background-color: #06C755;">
                                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="white"><path d="M12 2C6.48 2 2 5.88 2 10.54c0 4.07 3.42 7.49 8.05 8.44.31.07.73.21.84.48.1.25.06.63.03.88l-.14.83c-.04.25-.2.97.85.53s5.61-3.31 7.66-5.67C21.03 13.86 22 12.28 22 10.54 22 5.88 17.52 2 12 2z"/></svg>
                                    LINEで新着通知を受け取る
                                </a>
                                <div class="mt-2 flex items-center gap-2">
                                    <a href="{{ route('login') }}" class="flex-1 text-center bg-gray-100 hover:bg-gray-200 text-gray-500 font-bold py-2 rounded-lg transition text-[10px]">
                                        ログイン
                                    </a>
                                    <a href="{{ route('register') }}" class="flex-1 text-center bg-gray-100 hover:bg-gray-200 text-gray-500 font-bold py-2 rounded-lg transition text-[10px]">
                                        メールで登録
                                    </a>
                                </div>
                                <p class="text-[9px] text-gray-400 mt-2 text-center">
                                    無料登録で、条件に合うバイクの入荷・値下げを即通知
                                </p>
                            @endauth
                        </div>
                    </form>
                </div>
            </aside>

            <!-- 2. メインコンテンツ -->
            <div class="flex-1 min-w-0">

                {{-- LINE通知バナー（未ログイン時にJSで表示） --}}
                @guest
                    <div id="search-line-banner" class="mb-6"></div>
                @endguest

                <div class="mb-8 flex flex-col sm:flex-row sm:items-end justify-between gap-4">
                    <div>
                        <h2 class="text-2xl font-black text-black tracking-tighter italic">
                            {{ $pageTitle }}
                            <span class="text-xs text-gray-400 font-bold ml-2 not-italic">({{ number_format($pagination['total']) }}台)</span>
                        </h2>
                    </div>
                    
                    <div class="flex items-center gap-3">
                        <button id="open-filter" class="lg:hidden flex-shrink-0 flex items-center gap-2 bg-white border border-gray-100 rounded-xl px-4 py-2.5 text-xs font-black shadow-sm active:bg-gray-50 transition">
                            <i data-lucide="sliders-horizontal" class="w-4 h-4 text-blue-500"></i>
                            <span>絞り込み</span>
                        </button>

                        <div class="relative flex-1 sm:w-64">
                            <button type="button" id="custom-sort-btn" class="w-full flex items-center justify-between bg-white border border-gray-100 rounded-xl px-4 py-2.5 shadow-sm hover:border-blue-500 transition">
                                <span id="custom-sort-label" class="text-xs font-black text-gray-800">
                                    {{ $sortOptions[$sort] ?? '新着順' }}
                                </span>
                                <i data-lucide="chevron-down" class="w-4 h-4 text-gray-400"></i>
                            </button>

                            <div id="custom-sort-menu" class="hidden absolute right-0 top-full mt-2 w-full bg-white rounded-2xl shadow-2xl border border-gray-100 z-[200] overflow-hidden">
                                <div class="py-2">
                                    @foreach($sortOptions as $value => $label)
                                    <button type="button" class="dropdown-item w-full text-left px-5 py-4 sm:py-3 hover:bg-blue-50 flex items-center justify-between group" data-value="{{ $value }}">
                                        <span class="text-xs font-bold text-gray-700 group-hover:text-blue-600 {{ $sort === $value ? 'text-blue-600' : '' }}">{{ $label }}</span>
                                        @if($sort === $value) <i data-lucide="check" class="w-4 h-4 text-blue-600"></i> @endif
                                    </button>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- 閲覧履歴ウィジェット --}}
                @include('bikes.partials.history_widget', ['widgetId' => 'search-history-widget'])

                {{-- 市場相場パネル --}}
                @if(isset($stats['avg']) && $stats['avg'] && $stats['count'] > 0)
                <div class="mb-8 bg-white rounded-2xl border border-blue-100 shadow-sm overflow-hidden flex flex-col sm:flex-row animate-in fade-in slide-in-from-top-4 duration-500">
                    <div class="bg-blue-600 px-6 py-4 sm:w-48 flex flex-col justify-center items-center text-center text-white">
                        <span class="text-[10px] font-black tracking-widest opacity-80 mb-1">Market Report</span>
                        <span class="text-sm font-black italic">価格相場</span>
                    </div>
                    
                    <div class="flex-1 grid grid-cols-3 divide-x divide-gray-50 text-center py-4">
                        <div class="px-1 sm:px-2">
                            <span class="block text-[9px] font-black text-gray-400 uppercase mb-1">平均総額</span>
                            <span class="text-base sm:text-xl font-black text-blue-600 tabular-nums italic whitespace-nowrap">
                                {{ $stats['avg'] }}<small class="text-[9px] sm:text-[10px] ml-0.5 font-bold not-italic">万円</small>
                            </span>
                        </div>
                        <div class="px-1 sm:px-2">
                            <span class="block text-[9px] font-black text-gray-400 uppercase mb-1">最安値</span>
                            <span class="text-base sm:text-xl font-black text-gray-800 tabular-nums italic whitespace-nowrap">
                                {{ $stats['min'] }}<small class="text-[9px] sm:text-[10px] ml-0.5 font-bold not-italic">万円</small>
                            </span>
                        </div>
                        <div class="px-1 sm:px-2">
                            <span class="block text-[9px] font-black text-gray-400 uppercase mb-1">最高値</span>
                            <span class="text-base sm:text-xl font-black text-gray-800 tabular-nums italic whitespace-nowrap">
                                {{ $stats['max'] }}<small class="text-[9px] sm:text-[10px] ml-0.5 font-bold not-italic">万円</small>
                            </span>
                        </div>
                    </div>

                    <div class="hidden xl:flex items-center pr-6 pl-4 border-l border-gray-50">
                        <p class="text-[9px] text-gray-400 leading-tight font-bold">
                            ※現在の条件に一致する<br>
                            <span class="text-blue-500 font-black">{{ number_format($stats['count']) }}台</span> の平均
                        </p>
                    </div>
                </div>
                @endif

                {{-- 結果グリッド --}}
                <div id="results-grid" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-6">
                    @forelse ($items as $listing)
                        {{-- ★ここを修正！ $loop->index < 4 を渡す --}}
                        @include('bikes.partials.bike_card', ['listing' => $listing, 'isFirstView' => $loop->index < 4])

                        {{-- 6件目の後に愛車ガレージCTAを挿入 --}}
                        @if($loop->index === 5)
                        <div class="col-span-full bg-pink-50 rounded-2xl p-5 border border-pink-100 text-center">
                            <p class="text-sm font-bold text-gray-800 mb-1">バイクを持っている方へ</p>
                            <a href="{{ route('garage.public.index') }}" class="text-xs text-pink-600 font-bold hover:underline">
                                愛車ガレージで燃費・整備を記録しませんか？ →
                            </a>
                        </div>
                        @endif

                        {{-- 15件目の後にLINE通知カードを挿入（ゲストのみ） --}}
                        @if($loop->index === 14)
                            @guest
                            <div id="search-line-inline-card" class="col-span-full bg-gradient-to-r from-[#06C755]/5 to-[#06C755]/10 border-2 border-[#06C755]/20 rounded-2xl p-5 sm:p-6 flex flex-col sm:flex-row items-center gap-4 sm:gap-6 relative">
                                <button onclick="window._dismissLineInlineCard && window._dismissLineInlineCard()" aria-label="閉じる" class="absolute top-2 right-2 w-7 h-7 flex items-center justify-center bg-white/80 hover:bg-white rounded-full border border-gray-200 transition-colors">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="#6b7280" stroke-width="2.5" stroke-linecap="round" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                                <div class="flex items-center gap-3 shrink-0">
                                    <div class="w-12 h-12 bg-[#06C755] rounded-xl flex items-center justify-center shadow-lg shadow-green-200">
                                        <svg class="w-7 h-7" viewBox="0 0 24 24" fill="white"><path d="M12 2C6.48 2 2 5.88 2 10.54c0 4.07 3.42 7.49 8.05 8.44.31.07.73.21.84.48.1.25.06.63.03.88l-.14.83c-.04.25-.2.97.85.53s5.61-3.31 7.66-5.67C21.03 13.86 22 12.28 22 10.54 22 5.88 17.52 2 12 2z"/></svg>
                                    </div>
                                </div>
                                <div class="flex-1 text-center sm:text-left">
                                    <p class="text-sm font-black text-gray-800 mb-1">この条件の新着・値下げをLINEでお知らせ</p>
                                    <p class="text-[11px] text-gray-500 font-bold">条件に合うバイクが入荷・値下げされたら即通知。見逃しゼロ！</p>
                                </div>
                                <a href="/auth/line/redirect" class="shrink-0 inline-flex items-center gap-2 px-6 py-3 rounded-xl font-black text-white text-sm shadow-lg shadow-green-200 transition-all hover:opacity-90 active:scale-95" style="background-color: #06C755;">
                                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="white"><path d="M12 2C6.48 2 2 5.88 2 10.54c0 4.07 3.42 7.49 8.05 8.44.31.07.73.21.84.48.1.25.06.63.03.88l-.14.83c-.04.25-.2.97.85.53s5.61-3.31 7.66-5.67C21.03 13.86 22 12.28 22 10.54 22 5.88 17.52 2 12 2z"/></svg>
                                    LINEで通知を受け取る
                                </a>
                            </div>
                            @endguest
                        @endif
                    {{-- results-grid の @empty ブロックを以下のようにリッチ化します --}}
                    @empty
                        <div class="col-span-full">
                            {{-- 既存の「見つかりませんでした」メッセージを少しコンパクトに --}}
                            <div class="bg-white rounded-3xl p-8 sm:p-12 text-center border border-gray-100 shadow-sm mb-8">
                                <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-4 text-gray-300">
                                    <i data-lucide="search-x" class="w-8 h-8"></i>
                                </div>
                                <h3 class="text-lg font-black text-gray-800 mb-2">条件に合うバイクが見つかりませんでした</h3>
                                <p class="text-sm text-gray-400 font-bold mb-8">もう少し条件を広げて探してみませんか？</p>
            
                                @if(!empty($relaxSuggestions))
                                    <div class="flex flex-wrap justify-center gap-3">
                                        @foreach($relaxSuggestions as $suggest)
                                            <a href="{{ $suggest['url'] }}" class="inline-flex items-center gap-2 bg-gray-50 hover:bg-blue-50 text-gray-600 hover:text-blue-600 px-5 py-2.5 rounded-xl text-xs font-black border border-gray-100 hover:border-blue-200 transition-all">
                                                <i data-lucide="{{ $suggest['icon'] }}" class="w-4 h-4"></i>
                                                {{ $suggest['label'] }} ({{ number_format($suggest['count']) }}台)
                                            </a>
                                        @endforeach
                                    </div>
                                @endif
                            </div>

                            {{-- ★新設：診断へのレスキュー導線カード --}}
                            <div class="relative overflow-hidden bg-gradient-to-br from-gray-900 to-blue-950 rounded-3xl p-8 sm:p-10 shadow-2xl text-center">
                                {{-- 背景装飾 --}}
                                <div class="absolute top-0 left-0 w-full h-full opacity-10 pointer-events-none">
                                    <div class="absolute -top-24 -left-24 w-64 h-64 bg-blue-500 rounded-full blur-3xl"></div>
                                    <div class="absolute -bottom-24 -right-24 w-64 h-64 bg-indigo-500 rounded-full blur-3xl"></div>
                                </div>

                                <div class="relative z-10">
                                    <div class="inline-flex items-center gap-2 bg-blue-500/20 text-blue-300 px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest mb-6 border border-blue-500/30">
                                        <i data-lucide="sparkles" class="w-3 h-3"></i> AI Concierge
                                    </div>
                
                                    <h4 class="text-xl sm:text-2xl font-black text-white mb-4 leading-tight">
                                        こだわりを少し横に置いて、<br>
                                        <span class="text-transparent bg-clip-text bg-gradient-to-r from-blue-400 to-cyan-300">「あなたに合うスタイル」</span>から探しませんか？
                                    </h4>
                
                                    <p class="text-sm text-gray-200 font-bold mb-8 max-w-lg mx-auto leading-relaxed drop-shadow-sm">
                                        車種名が決まっていない方も大歓迎。5つの質問に答えるだけで、MotoHubのAIがあなたの理想に最も近いバイクを3台提案します。
                                    </p>

                                    <a href="/shindan" class="inline-flex items-center gap-3 bg-white text-gray-900 px-8 py-4 rounded-2xl font-black text-sm hover:bg-blue-50 transition-all shadow-xl active:scale-95 group">
                                        今すぐバイク診断をはじめる
                                        <i data-lucide="arrow-right" class="w-5 h-5 group-hover:translate-x-1 transition-transform"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endempty
                </div>

                {{-- おすすめ車種セクション --}}
                @include('bikes.partials.search_recommendations')

                {{-- さらに読み込むボタン --}}
                @if($pagination['next_url'])
                <div id="load-more-container" class="mt-12 text-center pb-8">
                    <button id="load-more-btn" data-next-url="{{ $pagination['next_url'] }}" class="group bg-white border-2 border-gray-200 text-gray-700 hover:border-blue-500 hover:text-blue-600 hover:bg-blue-50 font-black py-4 px-12 rounded-full shadow-sm hover:shadow-md transition inline-flex items-center justify-center gap-2 active:scale-95 w-full sm:w-auto">
                        <span id="load-more-text">さらに車両を見る</span>
                        <i data-lucide="chevron-down" id="load-more-icon" class="w-5 h-5 group-hover:translate-y-1 transition-transform"></i>
                        <i data-lucide="loader-2" id="load-more-spinner" class="w-5 h-5 animate-spin hidden text-blue-600"></i>
                    </button>
                </div>
                @endif

                {{-- 従来のページネーション --}}
                @if($pagination['last_page'] > 1)
                <div id="classic-pagination" class="mt-20 flex-col items-center gap-6 w-full hidden">
                    <nav class="flex justify-center items-center gap-1 sm:gap-2">
                        @if($pagination['prev_url'])
                        <a href="{{ $pagination['prev_url'] }}" class="w-10 h-10 flex items-center justify-center rounded-lg bg-white border border-gray-200 text-gray-400 hover:border-black transition"><i data-lucide="chevron-left" class="w-5 h-5"></i></a>
                        @endif
                        @foreach($pagination['pages'] as $page)
                            @if($page['is_dot']) <span class="px-1 text-gray-300">...</span>
                            @else <a href="{{ $page['url'] }}" class="w-10 h-10 flex items-center justify-center rounded-lg font-black text-sm transition {{ $page['is_active'] ? 'bg-black text-white shadow-lg' : 'bg-white border border-gray-200 text-gray-400 hover:border-black' }}">{{ $page['label'] }}</a>
                            @endif
                        @endforeach
                        @if($pagination['next_url'])
                        <a href="{{ $pagination['next_url'] }}" class="w-10 h-10 flex items-center justify-center rounded-lg bg-white border border-gray-200 text-gray-400 hover:border-black transition"><i data-lucide="chevron-right" class="w-5 h-5"></i></a>
                        @endif
                    </nav>
                </div>
                <noscript>
                    <style>#classic-pagination { display: flex; } #load-more-container { display: none; }</style>
                </noscript>
                @endif
                {{-- SEO強化用の内部リンク（エリア×条件のクモの巣） --}}
                @if(request()->filled('prefecture'))
                <div class="mt-16 bg-white rounded-3xl p-6 sm:p-8 border border-gray-100 shadow-sm animate-in fade-in duration-500">
                    <h3 class="text-sm sm:text-base font-black text-gray-800 mb-5 flex items-center gap-2">
                        {{-- ★修正: request('prefecture') に変更 --}}
                        <i data-lucide="map" class="w-5 h-5 text-blue-500"></i> {{ request('prefecture') }}の他のバイクを探す
                    </h3>
                    
                    <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-5 gap-3">
                        @foreach(['原付スクーター', 'ネイキッド', 'アメリカン', 'スポーツ/レプリカ', 'オフロード'] as $type)
                            {{-- ★修正: request('prefecture') に変更 --}}
                            <a href="{{ route('bikes.search', ['prefecture' => request('prefecture'), 'keyword' => $type]) }}" 
                               class="text-[10px] sm:text-xs font-bold text-gray-600 hover:text-blue-600 bg-gray-50 hover:bg-blue-50 px-3 py-3 rounded-xl transition-colors border border-gray-100 text-center flex items-center justify-center group shadow-sm">
                                <span class="group-hover:scale-105 transition-transform">{{ $type }}</span>
                            </a>
                        @endforeach
                    </div>

                    <div class="mt-6 pt-5 border-t border-gray-50 text-right">
                        <a href="{{ route('bikes.prefectures') }}" class="inline-flex items-center text-xs font-bold text-blue-600 hover:text-blue-800 transition-colors py-2 px-3 hover:bg-blue-50 rounded-lg">
                            全国の地域から探す <i data-lucide="chevron-right" class="w-4 h-4 ml-0.5"></i>
                        </a>
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
    
    @php
        $listingIds = collect($items)->pluck('id');
    @endphp
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const searchState = {
                ids: @json($listingIds),
                listUrl: "{{ request()->fullUrl() }}"
            };
            sessionStorage.setItem('motohub_search_state', JSON.stringify(searchState));
        });
    </script>
</x-layout>