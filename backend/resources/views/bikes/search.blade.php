<x-layout>
    <x-slot:title>
        @if($keyword) 「{{ $keyword }}」の検索結果 @else 車両一覧 @endif - MotoHub
    </x-slot:title>

    <x-slot:styles>
        <link rel="stylesheet" href="{{ asset('css/bike-search.css') }}">
    </x-slot:styles>

    <x-slot:navigation>
        <x-navigation :totalListingsCount="$totalListingsCount" :showSearch="true" :keyword="$keyword" />
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

                        <!-- 都道府県 -->
                        <div class="filter-group">
                            <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest italic mb-2 block">地域</label>
                            <div class="relative">
                                <select name="prefecture" class="w-full bg-gray-50 border border-gray-100 rounded-xl px-4 py-3 text-sm font-bold focus:ring-2 focus:ring-blue-500 outline-none appearance-none pr-10">
                                    <option value="">すべての地域</option>
                                    @foreach($prefectures as $pref)
                                        <option value="{{ $pref }}" {{ ($filters['prefecture'] ?? '') == $pref ? 'selected' : '' }}>{{ $pref }}</option>
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
                                    <label class="flex-1 text-center cursor-pointer">
                                        <input type="radio" name="is_new" value="{{ $val }}" class="hidden peer" 
                                            @checked((string)($filters['is_new'] ?? '') === (string)$val)>
                                        <span class="block py-2 text-[10px] font-black rounded-lg transition-all peer-checked:bg-white peer-checked:text-blue-600 peer-checked:shadow-sm text-gray-500">{{ $label }}</span>
                                    </label>
                                    @endforeach
                                </div>
                            </div>

                            <div class="filter-group">
                                <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest italic mb-2 block">修復歴</label>
                                <div class="flex bg-gray-100 p-1 rounded-xl">
                                    @foreach(['' => 'すべて', '0' => 'なし', '1' => 'あり'] as $val => $label)
                                    <label class="flex-1 text-center cursor-pointer">
                                        <input type="radio" name="has_repair_history" value="{{ $val }}" class="hidden peer" 
                                            @checked((string)($filters['has_repair_history'] ?? '') === (string)$val)>
                                        <span class="block py-2 text-[10px] font-black rounded-lg transition-all peer-checked:bg-white peer-checked:text-blue-600 peer-checked:shadow-sm text-gray-500">{{ $label }}</span>
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

                        <!-- 💡 【修正】モバイル・タブレット版のみ表示。PC版(lg:1024px以上)はオートリロードされるため非表示 -->
                        <div class="pt-4 lg:hidden">
                            <button type="submit" class="w-full bg-[#5392f9] text-white font-black py-4 rounded-2xl text-[11px] uppercase tracking-widest shadow-xl shadow-blue-100 active:scale-95 transition-all flex items-center justify-center gap-2">
                                <span>条件を適用する</span>
                                <span class="bg-white/20 px-2 py-0.5 rounded text-[10px] min-w-[3rem]" id="mobile-hit-count">
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

                        <div class="relative flex-1 sm:w-64">
                            <button type="button" id="custom-sort-btn" class="w-full flex items-center justify-between bg-white border border-gray-100 rounded-xl px-4 py-2.5 shadow-sm hover:border-blue-500 transition-all">
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

                {{-- 市場相場パネル --}}
                @if(isset($stats['avg']) && $stats['avg'] && $stats['count'] > 0)
                <div class="mb-8 bg-white rounded-2xl border border-blue-100 shadow-sm overflow-hidden flex flex-col sm:flex-row animate-in fade-in slide-in-from-top-4 duration-500">
                    <div class="bg-blue-600 px-6 py-4 sm:w-48 flex flex-col justify-center items-center text-center text-white">
                        <span class="text-[10px] font-black uppercase tracking-widest opacity-80 mb-1">Market Report</span>
                        <span class="text-sm font-black italic">価格相場</span>
                    </div>
                    
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
                            
                            <div class="aspect-[4/3] relative overflow-hidden bg-gray-50">
                                @if(!empty($listing['images']) && isset($listing['images'][0]))
                                    <img src="{{ $listing['images'][0] }}" class="bike-img w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" alt="">
                                @endif
                                
                                <button class="wishlist-btn absolute top-3 left-3 z-30 w-9 h-9 bg-white/80 backdrop-blur rounded-full flex items-center justify-center text-gray-400 shadow-sm hover:scale-110 active:scale-90 transition-all border border-white/50" data-id="{{ $listing['id'] }}">
                                    <i data-lucide="heart" class="w-4 h-4"></i>
                                </button>
                                
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
                                
                                <div class="grid grid-cols-2 gap-y-2.5 gap-x-2 text-[10px] font-bold text-gray-400 mb-6">
                                    <div class="flex items-center gap-1.5"><i data-lucide="calendar" class="w-3.5 h-3.5 text-gray-300"></i><span>{{ $listing['model_year'] }}</span></div>
                                    <div class="flex items-center gap-1.5"><i data-lucide="gauge" class="w-3.5 h-3.5 text-gray-300"></i><span>{{ $listing['mileage'] }}</span></div>
                                    <div class="flex items-center gap-1.5"><i data-lucide="droplet" class="w-3.5 h-3.5 text-gray-300"></i><span>{{ $listing['displacement'] }}</span></div>
                                    <div class="flex items-center gap-1.5"><i data-lucide="wrench" class="w-3.5 h-3.5 text-gray-300"></i><span class="truncate">修復歴: {{ $listing['repair_history'] }}</span></div>
                                </div>

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
                                        <i data-lucide="store" class="w-3 h-3"></i><span class="truncate">{{ $listing['store_name'] }}</span>
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

                {{-- ページネーション --}}
                @if($pagination['last_page'] > 1)
                <div class="mt-20 flex flex-col items-center gap-6 w-full">
                    <nav class="flex justify-center items-center gap-1 sm:gap-2">
                        @if($pagination['prev_url'])
                        <a href="{{ $pagination['prev_url'] }}" class="w-10 h-10 flex items-center justify-center rounded-lg bg-white border border-gray-200 text-gray-400 hover:border-black transition-all"><i data-lucide="chevron-left" class="w-5 h-5"></i></a>
                        @endif
                        @foreach($pagination['pages'] as $page)
                            @if($page['is_dot']) <span class="px-1 text-gray-300">...</span>
                            @else <a href="{{ $page['url'] }}" class="w-10 h-10 flex items-center justify-center rounded-lg font-black text-sm transition-all {{ $page['is_active'] ? 'bg-black text-white shadow-lg' : 'bg-white border border-gray-200 text-gray-400 hover:border-black' }}">{{ $page['label'] }}</a>
                            @endif
                        @endforeach
                        @if($pagination['next_url'])
                        <a href="{{ $pagination['next_url'] }}" class="w-10 h-10 flex items-center justify-center rounded-lg bg-white border border-gray-200 text-gray-400 hover:border-black transition-all"><i data-lucide="chevron-right" class="w-5 h-5"></i></a>
                        @endif
                    </nav>
                </div>
                @endif
            </div>
        </div>
    </div>
    <script src="{{ asset('js/bike-search-sidebar.js') }}"></script>
    <script src="{{ asset('js/custom-dropdown.js') }}"></script>
</x-layout>