<x-layout>
    <x-slot:title>
        @if($keyword) 「{{ $keyword }}」の検索結果 @else 車両一覧 @endif - MotoHub
    </x-slot:title>

    <x-slot:styles>
        {{-- 外部CSSファイルを読み込み --}}
        <link rel="stylesheet" href="{{ asset('css/bike-search.css') }}">
    </x-slot:styles>

    <x-slot:navigation>
        <x-navigation :totalListingsCount="$totalListingsCount" :showSearch="true" :keyword="$keyword" />
    </x-slot:navigation>

    <div class="bg-gray-50 min-h-screen py-8">
        <div class="max-w-7xl mx-auto px-4 flex flex-col lg:flex-row gap-8">
            
            <aside id="filter-sidebar" class="w-full lg:w-72 flex-shrink-0">
                <div id="filter-overlay" class="lg:hidden"></div>
                <div class="filter-sidebar-container filter-sidebar-content bg-white rounded-2xl shadow-sm border border-gray-100 flex flex-col">
                    <div class="p-5 border-b border-gray-50 flex items-center justify-between">
                        <h3 class="text-xs font-black text-black flex items-center gap-2 uppercase tracking-widest italic">
                            <i data-lucide="filter" class="w-4 h-4 text-blue-500"></i> 絞り込み条件
                        </h3>
                        <div class="flex items-center gap-4">
                            <a href="{{ route('bikes.search', ['keyword' => $keyword]) }}" class="text-[10px] font-bold text-gray-400 hover:text-blue-600 transition-colors uppercase">リセット</a>
                            <button id="close-filter" class="lg:hidden p-1 text-gray-400"><i data-lucide="x" class="w-5 h-5"></i></button>
                        </div>
                    </div>

                    <form action="{{ route('bikes.search') }}" method="GET" id="filter-form" class="p-6 space-y-10 overflow-y-auto">
                        <input type="hidden" name="keyword" value="{{ $keyword }}">
                        <input type="hidden" id="sort-hidden-input" name="sort" value="{{ $sort }}">

                        <!-- 価格スライダー -->
                        <div class="filter-group">
                            <div class="flex justify-between items-end mb-4">
                                <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest italic tracking-wider">価格</label>
                                <div class="text-xs font-black text-blue-600 tracking-tighter">
                                    <span id="label-min-price"></span> 〜 <span id="label-max-price"></span>
                                </div>
                            </div>
                            <div class="range-slider-container" id="slider-price">
                                <div class="slider-track"></div>
                                <div class="slider-progress"></div>
                                <input type="range" class="range-input range-min" name="min_price" min="0" max="{{ $meta['price']['max'] ?? 300 }}" value="{{ $filters['min_price'] ?? 0 }}" step="5">
                                <input type="range" class="range-input range-max" name="max_price" min="0" max="{{ $meta['price']['max'] ?? 300 }}" value="{{ $filters['max_price'] ?? ($meta['price']['max'] ?? 300) }}" step="5">
                            </div>
                        </div>  

                        <!-- 走行距離スライダー -->
                        <div class="filter-group">
                            <div class="flex justify-between items-end mb-4">
                                <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest italic tracking-wider">走行距離</label>
                                <div class="text-xs font-black text-blue-600 tracking-tighter">
                                    <span id="label-min-mileage"></span> 〜 <span id="label-max-mileage"></span>
                                </div>
                            </div>
                            <div class="range-slider-container" id="slider-mileage">
                                <div class="slider-track"></div>
                                <div class="slider-progress"></div>
                                <input type="range" class="range-input range-min" name="min_mileage" min="0" max="{{ $meta['mileage']['max'] ?? 50000 }}" value="{{ $filters['min_mileage'] ?? 0 }}" step="1000">
                                <input type="range" class="range-input range-max" name="max_mileage" min="0" max="{{ $meta['mileage']['max'] ?? 50000 }}" value="{{ $filters['max_mileage'] ?? ($meta['mileage']['max'] ?? 50000) }}" step="1000">
                            </div>
                        </div>

                        <!-- 年式スライダー -->
                        <div class="filter-group">
                            <div class="flex justify-between items-end mb-4">
                                <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest italic tracking-wider">年式</label>
                                <div class="text-xs font-black text-blue-600 tracking-tighter">
                                    <span id="label-min-year"></span> 〜 <span id="label-max-year"></span>
                                </div>
                            </div>
                            <div class="range-slider-container" id="slider-year">
                                <div class="slider-track"></div>
                                <div class="slider-progress"></div>
                                <input type="range" class="range-input range-min" name="min_year" min="{{ $meta['year']['min'] ?? 1990 }}" max="{{ $meta['year']['max'] ?? date('Y') }}" value="{{ $filters['min_year'] ?? ($meta['year']['min'] ?? 1990) }}" step="1">
                                <input type="range" class="range-input range-max" name="max_year" min="{{ $meta['year']['min'] ?? 1990 }}" max="{{ $meta['year']['max'] ?? date('Y') }}" value="{{ $filters['max_year'] ?? ($meta['year']['max'] ?? date('Y')) }}" step="1">
                            </div>
                        </div>

                        <!-- モバイル専用：検索ボタン（件数をリアルタイム更新） -->
                        <div class="pt-4 lg:hidden">
                            <button type="submit" id="mobile-search-btn" class="w-full bg-[#5392f9] text-white font-black py-4 rounded-2xl text-[11px] uppercase tracking-[0.1em] shadow-xl shadow-blue-100 active:scale-95 transition-all flex items-center justify-center gap-2">
                                <span>この条件で検索</span>
                                <span id="mobile-hit-count" class="bg-white/20 px-2 py-0.5 rounded text-[10px] min-w-[3rem]">
                                    ({{ number_format($pagination['total']) }}台)
                                </span>
                            </button>
                        </div>
                    </form>
                </div>
            </aside>                        

            <!-- 2. メインコンテンツ -->
            <div class="flex-1">
                <div class="mb-8 flex flex-col sm:flex-row sm:items-end justify-between gap-4">
                    <div>
                        <h2 class="text-2xl font-black text-black tracking-tighter italic">
                            @if($keyword) 「{{ $keyword }}」 @else 車両一覧 @endif 
                            <span class="text-xs text-gray-400 font-bold ml-2 not-italic">({{ number_format($pagination['total']) }}台)</span>
                        </h2>
                    </div>
                    
                    <div class="flex items-center gap-3">
                        <button id="open-filter" class="lg:hidden flex-shrink-0 flex items-center gap-2 bg-white border border-gray-100 rounded-xl px-4 py-2.5 text-xs font-black shadow-sm active:bg-gray-50 transition-all">
                            <i data-lucide="sliders-horizontal" class="w-4 h-4 text-blue-500"></i>
                            <span>絞り込み</span>
                        </button>

                        {{-- ✨ カスタムドロップダウンUI --}}
                        <div class="relative flex-1">
                            <!-- 表示用ボタン -->
                            <button type="button" id="custom-sort-btn" class="flex items-center justify-between bg-white border border-gray-100 rounded-xl px-4 py-2.5 shadow-sm hover:border-blue-500 transition-all">
                                <span id="custom-sort-label" class="text-xs font-black text-gray-800">
                                    @switch($sort)
                                        @case('price_asc') 価格の安い順 @break
                                        @case('price_desc') 価格の高い順 @break
                                        @case('mileage_asc') 走行距離が少ない @break
                                        @case('mileage_desc') 走行距離が多い @break
                                        @case('year_desc') 年式が新しい @break
                                        @case('year_asc') 年式が古い @break
                                        @default 新着順
                                    @endswitch
                                </span>
                                <i data-lucide="chevron-down" class="w-4 h-4 text-gray-400"></i>
                            </button>

                            <!-- 選択肢リスト (スマホで大きく見せる) -->
                            <div id="custom-sort-menu" class="hidden absolute right-0 top-full mt-2 w-full sm:w-64 bg-white rounded-2xl shadow-2xl border border-gray-100 z-[200] overflow-hidden">
                                <div class="py-2">
                                    @php
                                        $options = [
                                            'latest' => '新着順',
                                            'price_asc' => '価格の安い順',
                                            'price_desc' => '価格の高い順',
                                            'mileage_asc' => '走行距離が少ない',
                                            'mileage_desc' => '走行距離が多い',
                                            'year_desc' => '年式が新しい',
                                            'year_asc' => '年式が古い',
                                        ];
                                    @endphp
                                    @foreach($options as $value => $label)
                                    <button type="button" class="dropdown-item w-full text-left px-5 py-4 sm:py-3 hover:bg-blue-50 transition-colors flex items-center justify-between group" data-value="{{ $value }}">
                                        <span class="text-xs font-bold text-gray-700 group-hover:text-blue-600 {{ $sort === $value ? 'text-blue-600' : '' }}">
                                            {{ $label }}
                                        </span>
                                        @if($sort === $value)
                                            <i data-lucide="check" class="w-4 h-4 text-blue-600"></i>
                                        @endif
                                    </button>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ✨【新設】価格相場パネル --}}
                @if(isset($stats['avg']) && $stats['avg'] && $stats['count'] > 0)
                <div class="mb-8 bg-white rounded-2xl border border-blue-100 shadow-sm overflow-hidden flex flex-col sm:flex-row animate-in fade-in slide-in-from-top-4 duration-500">
                    {{-- 左：ラベルエリア (中央寄せに調整) --}}
                    <div class="bg-blue-600 px-6 py-4 sm:w-48 flex flex-col justify-center items-center text-center text-white">
                        <span class="text-[10px] font-black uppercase tracking-widest opacity-80 mb-1">Market Report</span>
                        <span class="text-sm font-black italic">価格相場</span>
                    </div>
                    
                    {{-- 中：統計数値エリア --}}
                    <div class="flex-1 grid grid-cols-3 divide-x divide-gray-50 text-center py-4">
                        <div class="px-2">
                            <span class="block text-[9px] font-black text-gray-400 uppercase mb-1">平均総額</span>
                            <span class="text-xl font-black text-blue-600 tabular-nums italic">{{ $stats['avg'] }}<small class="text-[10px] ml-0.5 font-bold not-italic">万円</small></span>
                        </div>
                        <div class="px-2">
                            <span class="block text-[9px] font-black text-gray-400 uppercase mb-1">最安値</span>
                            <span class="text-xl font-black text-gray-800 tabular-nums italic">{{ $stats['min'] }}<small class="text-[10px] ml-0.5 font-bold not-italic">万円</small></span>
                        </div>
                        <div class="px-2">
                            <span class="block text-[9px] font-black text-gray-400 uppercase mb-1">最高値</span>
                            <span class="text-xl font-black text-gray-800 tabular-nums italic">{{ $stats['max'] }}<small class="text-[10px] ml-0.5 font-bold not-italic">万円</small></span>
                        </div>
                    </div>

                    {{-- 右：台数注釈（デスクトップのみ） --}}
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
                        <div class="bg-white rounded-2xl overflow-hidden shadow-sm hover:shadow-2xl transition-all duration-500 flex flex-col group border border-gray-100 relative cursor-pointer bike-card">
                            <a href="{{ $listing['url'] }}" target="_blank" rel="noopener noreferrer" class="absolute inset-0 z-20"></a>
                            
                            {{-- 画像エリア --}}
                            <div class="aspect-[4/3] relative overflow-hidden bg-gray-50">
                                @if(!empty($listing['images']) && isset($listing['images'][0]))
                                    <img src="{{ $listing['images'][0] }}" class="bike-img" alt="">
                                @endif
                                
                                {{-- お気に入りボタン --}}
                                <button class="wishlist-btn absolute top-3 left-3 z-30 w-9 h-9 bg-white/80 backdrop-blur rounded-full flex items-center justify-center text-gray-400 shadow-sm hover:scale-110 active:scale-90 transition-all border border-white/50" data-id="{{ $listing['id'] }}">
                                    <i data-lucide="heart" class="w-4 h-4"></i>
                                </button>
                                
                                {{-- 出典元サイトバッジ --}}
                                <div class="absolute top-3 right-3 z-10 bg-white/90 backdrop-blur-sm px-2 py-1 rounded-lg flex items-center gap-1.5 border border-white/20 shadow-sm">
                                    <img src="https://www.google.com/s2/favicons?domain={{ $listing['source_domain'] ?? 'google.com' }}&sz=32" class="w-3 h-3 rounded-sm" alt="">
                                    <span class="text-[8px] font-black text-gray-500">{{ $listing['source'] }}</span>
                                </div>
                            </div>

                            <div class="p-5 flex-grow flex flex-col">
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="text-[9px] font-black text-blue-600 bg-blue-50 px-2 py-0.5 rounded uppercase">{{ $listing['maker'] }}</span>
                                    <span class="text-[9px] font-black text-gray-500 bg-gray-100 px-2 py-0.5 rounded uppercase">{{ $listing['condition'] }}</span>
                                </div>
                                <h3 class="text-sm font-black text-gray-800 mb-4 line-clamp-2 leading-tight group-hover:text-blue-600 transition-colors">{{ $listing['name'] }}</h3>
                                
                                {{-- 車両スペック詳細 --}}
                                <div class="grid grid-cols-2 gap-y-2.5 gap-x-2 text-[10px] font-bold text-gray-400 mb-6">
                                    <div class="flex items-center gap-1.5">
                                        <i data-lucide="calendar" class="w-3.5 h-3.5 text-gray-300"></i>
                                        <span>{{ $listing['model_year'] }}</span>
                                    </div>
                                    <div class="flex items-center gap-1.5">
                                        <i data-lucide="gauge" class="w-3.5 h-3.5 text-gray-300"></i>
                                        <span>{{ $listing['mileage'] }}</span>
                                    </div>
                                    <div class="flex items-center gap-1.5">
                                        <i data-lucide="droplet" class="w-3.5 h-3.5 text-gray-300"></i>
                                        <span>{{ $listing['displacement'] }}</span>
                                    </div>
                                    <div class="flex items-center gap-1.5">
                                        <i data-lucide="wrench" class="w-3.5 h-3.5 text-gray-300"></i>
                                        <span class="truncate">修復歴: {{ $listing['repair_history'] }}</span>
                                    </div>
                                </div>

                                {{-- 価格とショップ情報 --}}
                                <div class="mt-auto bg-gray-50 p-4 rounded-xl border border-gray-100 group-hover:bg-blue-50 transition-all duration-300">
                                    <div class="flex justify-between items-end mb-3">
                                        <div>
                                            <span class="text-[8px] font-black text-gray-400 block uppercase tracking-tighter">支払総額</span>
                                            <div class="text-red-500 font-black italic">
                                                <span class="text-2xl tracking-tighter">{{ $listing['total_price'] }}</span><span class="text-[10px] ml-0.5">万円</span>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <span class="text-[8px] font-black text-gray-400 block uppercase">本体価格</span>
                                            <div class="text-gray-700 font-black italic">
                                                <span class="text-lg tracking-tighter">{{ $listing['base_price'] }}</span><span class="text-[9px] ml-0.5">万円</span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="pt-2 border-t border-gray-200/50 flex items-center gap-1.5 text-[9px] text-gray-400 font-bold group-hover:text-blue-400 transition-colors">
                                        <i data-lucide="store" class="w-3 h-3"></i>
                                        <span class="truncate">{{ $listing['store_name'] }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="col-span-full py-40 text-center">
                            <i data-lucide="search-x" class="w-16 h-16 text-gray-200 mx-auto mb-6"></i>
                            <p class="text-gray-400 font-black uppercase tracking-widest text-xs">No matching bikes found</p>
                        </div>
                    @endforelse
                </div>

                {{-- ページネーション (機能維持) --}}
                @if($pagination['last_page'] > 1)
                <div class="mt-20 flex flex-col items-center gap-6 w-full">
                    <nav class="flex justify-center items-center gap-1 sm:gap-2 max-w-full">
                        @if($pagination['prev_url'])
                        <a href="{{ $pagination['prev_url'] }}" class="w-8 h-8 sm:w-10 sm:h-10 flex items-center justify-center rounded-lg bg-white border border-gray-200 text-gray-400 hover:border-black hover:text-black transition-all">
                            <i data-lucide="chevron-left" class="w-4 h-4 sm:w-5 h-5"></i>
                        </a>
                        @endif

                        @foreach($pagination['pages'] as $page)
                            @if($page['is_dot'])
                                <span class="px-0.5 text-gray-300 text-xs">...</span>
                            @else
                                <a href="{{ $page['url'] }}" 
                                   class="w-8 h-8 sm:w-10 sm:h-10 flex items-center justify-center rounded-lg font-black text-[10px] sm:text-sm transition-all {{ $page['is_active'] ? 'bg-black text-white shadow-lg' : 'bg-white border border-gray-200 text-gray-400 hover:border-black' }}">
                                    {{ $page['label'] }}
                                </a>
                            @endif
                        @endforeach

                        @if($pagination['next_url'])
                        <a href="{{ $pagination['next_url'] }}" class="w-8 h-8 sm:w-10 sm:h-10 flex items-center justify-center rounded-lg bg-white border border-gray-200 text-gray-400 hover:border-black hover:text-black transition-all">
                            <i data-lucide="chevron-right" class="w-4 h-4 sm:w-5 h-5"></i>
                        </a>
                        @endif
                    </nav>
                </div>
                @endif

            </div>
        </div>
    </div>

    {{-- スライダー本体のロジック --}}
    <script src="{{ asset('js/range-slider.js') }}"></script>
    <script src="{{ asset('js/custom-dropdown.js') }}"></script>
</x-layout>