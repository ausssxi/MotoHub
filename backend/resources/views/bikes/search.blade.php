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
                    <h2 class="text-2xl font-black text-black tracking-tighter">
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
                    <p class="text-xs font-bold text-gray-400 mt-1 tracking-wider uppercase">
                        FOUND {{ number_format($pagination['total']) }} RESULTS 
                        <span class="mx-2 text-gray-200">|</span> 
                        {{ $pagination['from'] ?? 0 }} - {{ $pagination['to'] ?? 0 }}
                    </p>
                </div>
                
                <div class="flex gap-2">
                    <form action="{{ route('bikes.search') }}" method="GET" id="sort-form">
                        <input type="hidden" name="keyword" value="{{ $keyword }}">
                        <input type="hidden" name="prefecture" value="{{ $prefecture }}">
                        
                        <select 
                            name="sort" 
                            onchange="document.getElementById('sort-form').submit()"
                            class="bg-white border border-gray-200 rounded-lg px-4 py-2.5 text-xs font-black focus:outline-none shadow-sm cursor-pointer text-gray-700 hover:border-black transition-all appearance-none pr-10 relative"
                            style="background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%2224%22%20height%3D%2224%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22%23ccc%22%20stroke-width%3D%222%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpath%20d%3D%22m6%209%206%206%206-6%22%2F%3E%3C%2Fsvg%3E'); background-repeat: no-repeat; background-position: right 0.7rem center; background-size: 1rem;"
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
                <div class="bg-white rounded-2xl overflow-hidden shadow-sm hover:shadow-2xl transition-all duration-500 flex flex-col group border border-gray-100 relative">
                    <!-- ...車両カードの内容 (省略なし) ... -->
                    <div class="absolute top-4 right-4 z-10 bg-white/90 backdrop-blur-sm px-2 py-1.5 rounded-lg shadow-sm flex items-center gap-1.5 border border-white/20">
                        <img src="https://www.google.com/s2/favicons?domain={{ $listing['source_domain'] }}&sz=32" class="w-3.5 h-3.5 rounded-sm" alt="">
                        <span class="text-[9px] font-black text-gray-500 tracking-tighter">{{ $listing['source'] }}</span>
                    </div>

                    <div class="aspect-[4/3] relative overflow-hidden bg-gray-50">
                        @if(!empty($listing['images']) && isset($listing['images'][0]))
                            <img src="{{ $listing['images'][0] }}" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-700" alt="{{ $listing['name'] }}">
                        @else
                            <div class="w-full h-full flex items-center justify-center text-gray-200"><i data-lucide="image" class="w-12 h-12"></i></div>
                        @endif
                    </div>
                    
                    <div class="p-5 flex-grow flex flex-col">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="text-[10px] font-black text-blue-600 bg-blue-50 px-2 py-0.5 rounded uppercase tracking-tighter">{{ $listing['maker'] ?? 'OTHER' }}</span>
                            <span class="text-[10px] font-black px-2 py-0.5 rounded bg-gray-100 text-gray-500 uppercase tracking-tighter">{{ $listing['condition'] }}</span>
                        </div>
                        <h3 class="text-sm sm:text-base font-black text-black mb-4 line-clamp-2 h-10 sm:h-12 leading-tight group-hover:text-blue-600 transition-colors">{{ $listing['name'] }}</h3>

                        <div class="grid grid-cols-2 gap-x-4 gap-y-2 text-[11px] font-bold text-gray-400 mb-6">
                            <div class="flex items-center gap-2"><i data-lucide="calendar" class="w-3.5 h-3.5 text-gray-300"></i><span class="text-gray-600">{{ $listing['model_year'] }}</span></div>
                            <div class="flex items-center gap-2"><i data-lucide="gauge" class="w-3.5 h-3.5 text-gray-300"></i><span class="text-gray-600">{{ $listing['mileage'] }}</span></div>
                            <div class="flex items-center gap-2"><i data-lucide="zap" class="w-3.5 h-3.5 text-gray-300"></i><span class="text-gray-600">{{ $listing['displacement'] }}</span></div>
                            <div class="flex items-center gap-2"><i data-lucide="wrench" class="w-3.5 h-3.5 text-gray-300"></i><span class="{{ $listing['repair_history'] === 'あり' ? 'text-red-500' : 'text-gray-600' }}">修復{{ $listing['repair_history'] }}</span></div>
                        </div>

                        <div class="bg-gray-50 p-4 rounded-2xl mt-auto border border-gray-100 group-hover:bg-blue-50/50 group-hover:border-blue-100 transition-all duration-300">
                            <div class="flex justify-between items-end">
                                <div>
                                    <span class="text-[9px] font-black text-gray-400 uppercase tracking-widest block mb-0.5">支払総額</span>
                                    <div class="text-red-500 leading-none">
                                        <span class="text-2xl sm:text-3xl font-black italic tracking-tighter">{{ $listing['total_price'] }}</span><span class="text-xs font-black ml-0.5">万円</span>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <span class="text-[9px] font-black text-gray-400 uppercase tracking-widest block mb-0.5">車両本体</span>
                                    <div class="text-gray-800 leading-none">
                                        <span class="text-lg font-black italic tracking-tighter">{{ $listing['base_price'] }}</span><span class="text-[10px] font-black ml-0.5">万円</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-5 pt-4 border-t border-gray-100 flex items-center justify-between gap-4">
                            <div class="flex items-center min-w-0">
                                <i data-lucide="store" class="w-3.5 h-3.5 text-gray-300 mr-1.5 flex-shrink-0"></i>
                                <span class="text-[10px] font-bold text-gray-400 truncate">{{ $listing['store_name'] }}</span>
                            </div>
                            <a href="{{ $listing['url'] }}" target="_blank" rel="noopener noreferrer" class="bg-black text-white text-[11px] font-black px-4 py-2.5 rounded-xl hover:bg-blue-600 transition-all flex items-center gap-2 shadow-lg shadow-black/5 active:scale-95 whitespace-nowrap">
                                VIEW INFO <i data-lucide="external-link" class="w-3.5 h-3.5"></i>
                            </a>
                        </div>
                    </div>
                </div>
                @empty
                <div class="col-span-full py-24 text-center">
                    <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-6">
                        <i data-lucide="search-x" class="w-10 h-10 text-gray-300"></i>
                    </div>
                    <h3 class="text-lg font-bold text-black mb-2">一致する車両が見つかりませんでした</h3>
                    <a href="{{ route('bikes.index') }}" class="inline-flex items-center gap-2 bg-black text-white px-8 py-4 rounded-2xl font-black text-sm hover:bg-gray-800 transition-all shadow-xl shadow-black/10">
                        <i data-lucide="arrow-left" class="w-4 h-4"></i> 検索画面に戻る
                    </a>
                </div>
                @endforelse
            </div>

            <!-- ページネーション (カスタム実装) -->
            @if($pagination['last_page'] > 1)
            <div class="mt-20 flex flex-col items-center gap-6">
                <nav class="flex items-center gap-1">
                    {{-- 前のページ --}}
                    @if($pagination['current_page'] > 1)
                    <a href="{{ request()->fullUrlWithQuery(['page' => $pagination['current_page'] - 1]) }}" 
                       class="w-10 h-10 flex items-center justify-center rounded-xl bg-white border border-gray-200 text-gray-400 hover:border-black hover:text-black transition-all">
                        <i data-lucide="chevron-left" class="w-5 h-5"></i>
                    </a>
                    @endif

                    {{-- ページ番号 --}}
                    @php
                        $start = max(1, $pagination['current_page'] - 2);
                        $end = min($pagination['last_page'], $pagination['current_page'] + 2);
                    @endphp

                    @if($start > 1)
                        <span class="px-2 text-gray-300">...</span>
                    @endif

                    @for($i = $start; $i <= $end; $i++)
                        <a href="{{ request()->fullUrlWithQuery(['page' => $i]) }}" 
                           class="w-10 h-10 flex items-center justify-center rounded-xl font-black text-sm transition-all {{ $i === $pagination['current_page'] ? 'bg-black text-white shadow-lg shadow-black/20' : 'bg-white border border-gray-200 text-gray-400 hover:border-black hover:text-black' }}">
                            {{ $i }}
                        </a>
                    @endfor

                    @if($end < $pagination['last_page'])
                        <span class="px-2 text-gray-300">...</span>
                    @endif

                    {{-- 次のページ --}}
                    @if($pagination['current_page'] < $pagination['last_page'])
                    <a href="{{ request()->fullUrlWithQuery(['page' => $pagination['current_page'] + 1]) }}" 
                       class="w-10 h-10 flex items-center justify-center rounded-xl bg-white border border-gray-200 text-gray-400 hover:border-black hover:text-black transition-all">
                        <i data-lucide="chevron-right" class="w-5 h-5"></i>
                    </a>
                    @endif
                </nav>

                <p class="text-[10px] font-black text-gray-300 uppercase tracking-[0.2em]">
                    Page {{ $pagination['current_page'] }} of {{ $pagination['last_page'] }}
                </p>
            </div>
            @endif
        </div>
    </div>
</x-layout>