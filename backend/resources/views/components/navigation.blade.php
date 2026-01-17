@props([
    'totalListingsCount' => 0,
    'showSearch' => false,
    'keyword' => ''
])

<nav class="border-b border-gray-100 py-4 sticky top-0 bg-white/80 backdrop-blur-md z-50">
    <div class="max-w-7xl mx-auto px-4 flex justify-between items-center gap-4">
        <!-- 左側: ロゴ -->
        <a href="{{ route('bikes.index') }}" class="flex items-center gap-2 flex-shrink-0">
            <img src="{{ asset('favicon.svg') }}" alt="MotoHub" class="w-8 h-8">
            <span class="text-xl font-black tracking-tighter">MotoHub</span>
        </a>

        <!-- 中央: 再検索窓 (showSearchがtrueの時のみ表示) -->
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

            <!-- サジェスト結果ドロップダウン -->
            <div id="nav-suggest-results" class="absolute left-0 right-0 top-full mt-2 bg-white rounded-2xl shadow-2xl border border-gray-100 overflow-hidden hidden z-[100] text-left">
                <div id="nav-suggest-list" class="py-2 max-h-[400px] overflow-y-auto">
                    <!-- JSでここに候補を挿入 -->
                </div>
            </div>
        </div>
        @endif

        <!-- 右側: 掲載台数 -->
        <div class="flex items-center gap-3 flex-shrink-0">
            <div class="hidden sm:flex items-center gap-2 mr-2">
                <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">掲載台数</span>
                <div class="flex items-baseline gap-0.5">
                    <span class="text-lg font-black text-black leading-none">{{ number_format($totalListingsCount) }}</span>
                    <span class="text-[10px] font-bold text-gray-500">台</span>
                </div>
            </div>
        </div>
    </div>
</nav>

{{-- サジェスト機能を共通JSとして読み込み --}}
@if($showSearch)
    <script src="{{ asset('js/search-suggest.js') }}"></script>
@endif