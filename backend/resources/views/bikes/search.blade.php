<x-layout>
    {{-- 1. タイトルの設定 (ブラウザタブ用) --}}
    <x-slot:title>
        @if($prefecture && $keyword)
            {{ $prefecture }}の「{{ $keyword }}」の検索結果
        @elseif($prefecture)
            {{ $prefecture }}の車両一覧
        @elseif($keyword)
            「{{ $keyword }}」の検索結果
        @else
            車両一覧
        @endif
        - MotoHub
    </x-slot:title>

    {{-- 2. 共通ナビゲーションコンポーネントの使用 --}}
    <x-slot:navigation>
        <x-navigation 
            :totalListingsCount="$totalListingsCount" 
            :showSearch="true" 
            :showFilter="true" 
            :keyword="$keyword" 
        />
    </x-slot:navigation>

    {{-- 3. メインコンテンツ --}}
    <div class="bg-gray-50 min-h-[calc(100vh-64px)] py-8">
        <div class="max-w-7xl mx-auto px-4">
            
            <!-- 結果情報ヘッダー -->
            <div class="mb-8 flex flex-col sm:flex-row sm:items-end justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-black text-black">
                        @if($prefecture) 
                            <span class="text-blue-600">{{ $prefecture }}</span>
                        @endif

                        @if($keyword) 
                            @if($prefecture) <span class="text-gray-400 mx-1">の</span> @endif
                            「{{ $keyword }}」
                        @endif

                        @if($prefecture || $keyword)
                            <span class="text-gray-900">の検索結果</span>
                        @else
                            車両一覧
                        @endif
                    </h2>
                    <p class="text-xs font-bold text-gray-400 mt-1 tracking-wider">
                        全 {{ number_format($pagination['total']) }} 件中 
                        {{ $pagination['from'] }}〜{{ $pagination['to'] }} 件を表示
                    </p>
                </div>
                
                <div class="flex gap-2">
                    {{-- 並び替えフォーム --}}
                    <form action="{{ route('bikes.search') }}" method="GET" id="sort-form">
                        {{-- 既存の検索条件を保持 --}}
                        <input type="hidden" name="keyword" value="{{ $keyword }}">
                        <input type="hidden" name="prefecture" value="{{ $prefecture }}">
                        
                        <select 
                            name="sort" 
                            onchange="document.getElementById('sort-form').submit()"
                            class="bg-white border border-gray-200 rounded-lg px-3 py-2 text-xs font-bold focus:outline-none shadow-sm cursor-pointer text-gray-600 hover:border-black transition-colors"
                        >
                            <option value="latest" {{ $sort === 'latest' ? 'selected' : '' }}>新着順</option>
                            <option value="price_asc" {{ $sort === 'price_asc' ? 'selected' : '' }}>価格の安い順</option>
                            <option value="price_desc" {{ $sort === 'price_desc' ? 'selected' : '' }}>価格の高い順</option>
                        </select>
                    </form>
                </div>
            </div>

            <!-- 検索結果グリッド -->
            <div id="results-grid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                @forelse ($listings as $listing)
                <div class="bg-white rounded-2xl overflow-hidden shadow-sm hover:shadow-xl transition-all duration-300 flex flex-col group border border-gray-100">
                    <!-- 画像エリア -->
                    <div class="aspect-[4/3] relative overflow-hidden bg-gray-100">
                        @if(!empty($listing['images']) && isset($listing['images'][0]))
                            <img src="{{ $listing['images'][0] }}" 
                                 class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" 
                                 alt="{{ $listing['name'] }}">
                        @else
                            <div class="w-full h-full flex items-center justify-center text-gray-200">
                                <i data-lucide="image" class="w-12 h-12"></i>
                            </div>
                        @endif
                    </div>
                    
                    <!-- 情報エリア -->
                    <div class="p-5 flex-grow flex flex-col">
                        <div class="flex justify-between items-center mb-1">
                            <div class="flex items-center gap-2">
                                <span class="text-[10px] font-black text-gray-300 uppercase tracking-tighter">
                                    {{ $listing['maker'] ?? 'OTHER' }}
                                </span>
                                <span class="text-[9px] font-bold px-1.5 py-0.5 rounded bg-gray-100 text-gray-500 uppercase">
                                    {{ $listing['condition'] }}
                                </span>
                            </div>
                            
                            {{-- 掲載元サイトのファビコンと名前 --}}
                            <div class="flex items-center gap-1.5 opacity-60 group-hover:opacity-100 transition-opacity">
                                <img src="https://www.google.com/s2/favicons?domain={{ $listing['source_domain'] }}&sz=32" 
                                     class="w-3 h-3 rounded-sm filter grayscale group-hover:grayscale-0 transition-all" 
                                     alt="">
                                <span class="text-[9px] font-bold text-gray-400 uppercase tracking-tight">
                                    {{ $listing['source'] === 'GooBike' ? 'グーバイク' : $listing['source'] }}
                                </span>
                            </div>
                        </div>

                        <h3 class="text-sm font-bold text-black mb-4 line-clamp-2 h-10 group-hover:text-blue-600 transition-colors">
                            {{ $listing['name'] }}
                        </h3>

                        <!-- クイックビュー -->
                        <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-[11px] text-gray-500 mb-6">
                            <div class="flex items-center gap-1" title="モデル年式">
                                <i data-lucide="calendar" class="w-3.5 h-3.5 text-gray-300"></i>
                                {{ $listing['model_year'] }}
                            </div>
                            <div class="flex items-center gap-1" title="初年度登録">
                                <i data-lucide="history" class="w-3.5 h-3.5 text-gray-300"></i>
                                {{ $listing['first_registration'] }}
                            </div>
                            <div class="flex items-center gap-1" title="走行距離">
                                <i data-lucide="gauge" class="w-3.5 h-3.5 text-gray-300"></i>
                                {{ $listing['mileage'] }}
                            </div>
                            <div class="basis-full h-0"></div>
                            <div class="flex items-center gap-1" title="排気量">
                                <i data-lucide="zap" class="w-3.5 h-3.5 text-gray-300"></i>
                                {{ $listing['displacement'] }}
                            </div>
                            <div class="flex items-center gap-1" title="修理歴">
                                <i data-lucide="wrench" class="w-3.5 h-3.5 text-gray-300"></i>
                                <span class="{{ $listing['repair_history'] === 'あり' ? 'text-red-500 font-bold' : '' }}">
                                    修復{{ $listing['repair_history'] }}
                                </span>
                            </div>
                        </div>

                        <!-- 価格バッジ -->
                        <div class="bg-gray-50 p-4 rounded-xl mt-auto border border-gray-100 group-hover:bg-blue-50/50 group-hover:border-blue-100 transition-colors">
                            <div class="flex justify-between items-center mb-1 pb-1 border-b border-gray-200/50">
                                <span class="text-[10px] font-bold text-gray-600 uppercase tracking-tighter shrink-0 mr-2 leading-tight">
                                    車両<br class="sm:hidden">価格
                                </span>
                                <div class="text-black text-right">
                                    <span class="text-xl font-black italic">{{ $listing['base_price'] }}</span>
                                    <span class="text-[10px] font-bold ml-0.5">万円</span>
                                </div>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-[10px] font-black text-red-500 uppercase italic tracking-tighter shrink-0 mr-2 leading-tight">
                                    総額<br class="sm:hidden">価格
                                </span>
                                <div class="text-red-500 text-right">
                                    <span class="text-2xl font-black italic">{{ $listing['total_price'] }}</span>
                                    <span class="text-[10px] font-bold ml-0.5">万円</span>
                                </div>
                            </div>
                        </div>

                        <!-- 店舗・リンク -->
                        <div class="mt-4 pt-4 border-t border-gray-50 flex items-center justify-between">
                            <div class="flex items-center overflow-hidden">
                                <span class="text-[10px] font-bold text-gray-600 truncate max-w-[150px]">
                                    {{ $listing['store_name'] }}
                                </span>
                            </div>
                            <a href="{{ $listing['url'] }}" target="_blank" class="text-[11px] font-black text-blue-600 hover:text-blue-700 flex items-center gap-1 transition-colors">
                                VIEW INFO <i data-lucide="arrow-up-right" class="w-3 h-3"></i>
                            </a>
                        </div>
                    </div>
                </div>
                @empty
                <!-- 検索結果なし -->
                <div class="col-span-full py-24 text-center">
                    <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-6">
                        <i data-lucide="search-x" class="w-10 h-10 text-gray-300"></i>
                    </div>
                    <h3 class="text-lg font-bold text-black mb-2">一致する車両が見つかりませんでした</h3>
                    <p class="text-gray-400 text-sm mb-8">条件を変えて再度お試しください。</p>
                    <a href="{{ route('bikes.index') }}" class="inline-flex items-center gap-2 bg-black text-white px-6 py-3 rounded-xl font-bold text-sm hover:bg-gray-800 transition-all">
                        <i data-lucide="arrow-left" class="w-4 h-4"></i> トップに戻って探す
                    </a>
                </div>
                @endforelse
            </div>

            <!-- 動的ページネーション -->
            @if($pagination['last_page'] > 1)
            <div class="mt-16 flex flex-col items-center gap-4">
                <div class="flex items-center gap-1 sm:gap-2">
                    @php
                        $current = $pagination['current_page'];
                        $last = $pagination['last_page'];
                        $pages = [];
                        for ($i = 1; $i <= $last; $i++) {
                            if ($i == 1 || $i == $last || ($i >= $current - 2 && $i <= $current + 2)) {
                                $pages[] = $i;
                            }
                        }
                    @endphp

                    @if($current > 1)
                    <a href="{{ request()->fullUrlWithQuery(['page' => $current - 1]) }}" 
                       class="w-9 h-9 sm:w-10 sm:h-10 flex items-center justify-center rounded-xl bg-white border border-gray-200 text-gray-600 hover:bg-gray-50 hover:border-black hover:text-black transition-all">
                        <i data-lucide="chevron-left" class="w-4 h-4 sm:w-5 sm:h-5"></i>
                    </a>
                    @endif

                    @php $cursor = 0; @endphp
                    @foreach($pages as $page)
                        @if($cursor > 0 && $page - $cursor > 1)
                            <span class="px-0.5 sm:px-1 text-gray-300 {{ ($page - $cursor == 2) && ($page == $current - 1 || $cursor == $current + 1) ? 'hidden sm:inline' : '' }}">...</span>
                        @endif

                        @if($page == $current)
                            <span class="w-9 h-9 sm:w-10 sm:h-10 flex items-center justify-center rounded-xl bg-black text-white font-bold text-sm shadow-lg shadow-black/10 transition-all">
                                {{ $page }}
                            </span>
                        @else
                            <a href="{{ request()->fullUrlWithQuery(['page' => $page]) }}" 
                               class="{{ ($page == $current - 2 || $page == $current + 2) && $page != 1 && $page != $last ? 'hidden sm:flex' : 'flex' }} w-9 h-9 sm:w-10 sm:h-10 items-center justify-center rounded-xl bg-white border border-gray-200 text-gray-600 font-bold text-sm hover:bg-gray-50 hover:border-black hover:text-black transition-all">
                                {{ $page }}
                            </a>
                        @endif
                        @php $cursor = $page; @endphp
                    @endforeach

                    @if($current < $last)
                    <a href="{{ request()->fullUrlWithQuery(['page' => $current + 1]) }}" 
                       class="w-9 h-9 sm:w-10 sm:h-10 flex items-center justify-center rounded-xl bg-white border border-gray-200 text-gray-600 hover:bg-gray-50 hover:border-black hover:text-black transition-all">
                        <i data-lucide="chevron-right" class="w-4 h-4 sm:w-5 sm:h-5"></i>
                    </a>
                    @endif
                </div>
                
                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">
                    Page {{ $current }} of {{ $last }}
                </p>
            </div>
            @endif
        </div>
    </div>
</x-layout>