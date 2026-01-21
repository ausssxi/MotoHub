@props([
    'totalListingsCount' => 0,
    'showSearch' => false,
    'keyword' => ''
])

<nav class="border-b border-gray-100 py-4 sticky top-0 bg-white/80 backdrop-blur-md z-[100]">
    <div class="max-w-7xl mx-auto px-4">
        <div class="flex justify-between items-center gap-4">
            
            <!-- 左側: ロゴ -->
            <a href="{{ route('bikes.index') }}" class="flex items-center gap-2 flex-shrink-0 group">
                <img src="{{ asset('favicon.svg') }}" alt="MotoHub" class="w-8 h-8 group-hover:scale-110 transition-transform">
                <span class="text-xl font-black tracking-tighter">MotoHub</span>
            </a>

            <!-- 中央: PC用検索窓 -->
            @if($showSearch)
            <div class="hidden md:flex flex-grow max-w-md relative" id="nav-search-container">
                <form action="{{ route('bikes.search') }}" method="GET" class="w-full" autocomplete="off" id="nav-search-form">
                    <div class="relative w-full">
                        <div class="absolute inset-y-0 left-3 flex items-center pointer-events-none text-gray-400">
                            <i data-lucide="search" class="w-4 h-4"></i>
                        </div>
                        <input type="text" name="keyword" id="nav-search-input" value="{{ $keyword }}" placeholder="車種を検索..." 
                            class="w-full bg-gray-100 border-none rounded-full pl-10 pr-4 py-2 text-sm focus:ring-2 focus:ring-black transition-all">
                    </div>
                </form>
                <div id="nav-suggest-results" class="absolute left-0 right-0 top-full mt-2 bg-white rounded-2xl shadow-2xl border border-gray-100 overflow-hidden hidden z-[110] text-left">
                    <div id="nav-suggest-list" class="py-2 max-h-[400px] overflow-y-auto"></div>
                </div>
            </div>
            @endif

            <!-- 右側: アクションエリア -->
            <div class="flex items-center gap-3 sm:gap-5 flex-shrink-0">
                
                <!-- スマホ用検索ボタン (JSで見つけるためのIDを追加しました) -->
                @if($showSearch)
                <button id="mobile-nav-search-toggle" class="md:hidden p-2 text-gray-400 hover:text-black rounded-full transition-all">
                    <i data-lucide="search" class="w-6 h-6"></i>
                </button>
                @endif

                <!-- お気に入りボタン -->
                <a href="{{ route('wishlist') }}" class="relative flex flex-col items-center justify-center min-w-[56px] px-1 py-1 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-xl transition-all group" title="お気に入り一覧">
                    <span class="text-[9px] font-black leading-none mb-1 tracking-tighter uppercase">お気に入り</span>
                    <div class="relative">
                        <i data-lucide="heart" class="w-6 h-6 group-active:scale-125 transition-transform"></i>
                        <span id="wishlist-count" class="absolute -top-1 -right-1 bg-red-500 text-white text-[8px] font-black min-w-[15px] h-3.5 flex items-center justify-center rounded-full px-1 border border-white hidden shadow-sm">0</span>
                    </div>
                </a>

                <!-- 掲載台数表示 -->
                <div class="hidden sm:flex items-center gap-2 border-l border-gray-100 pl-5">
                    <div class="text-right">
                        <span class="text-[9px] font-bold text-gray-400 uppercase tracking-widest block leading-none mb-1">掲載台数</span>
                        <div class="flex items-baseline gap-0.5">
                            <span class="text-lg font-black text-black leading-none">{{ number_format($totalListingsCount) }}</span>
                            <span class="text-[10px] font-bold text-gray-500">台</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ✨ スマホ用ドロップダウン検索バー (初期状態はhidden) -->
        @if($showSearch)
        <div id="mobile-nav-search-bar" class="md:hidden hidden pt-4 pb-2 relative animate-in slide-in-from-top-2 duration-200">
            <form action="{{ route('bikes.search') }}" method="GET" class="w-full" id="mobile-nav-search-form" autocomplete="off">
                <div class="relative w-full">
                    <div class="absolute inset-y-0 left-3 flex items-center pointer-events-none text-gray-400">
                        <i data-lucide="search" class="w-4 h-4"></i>
                    </div>
                    <input type="search" name="keyword" id="mobile-nav-search-input" value="{{ $keyword }}" placeholder="車種名を入力..." 
                        class="w-full bg-gray-100 border-none rounded-xl pl-10 pr-4 py-3 text-base focus:ring-2 focus:ring-black transition-all">
                </div>
            </form>
            <!-- スマホ用サジェスト結果表示エリア -->
            <div id="mobile-nav-suggest-results" class="absolute left-0 right-0 top-full mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 overflow-hidden hidden z-[120] text-left">
                <div id="mobile-nav-suggest-list" class="py-2 max-h-[300px] overflow-y-auto"></div>
            </div>
        </div>
        @endif
    </div>
</nav>

@if($showSearch)
    {{-- サジェスト機能は単独で動作するように切り分けています --}}
    <script src="{{ asset('js/search-suggest.js') }}"></script>
@endif