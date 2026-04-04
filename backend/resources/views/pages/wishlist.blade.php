<x-layout>
    {{-- 1. タイトルの設定 --}}
    <x-slot:title>
        お気に入り一覧 - MotoHub
    </x-slot:title>

    {{-- 比較機能とお気に入りページ専用のJSを読み込む --}}
    <x-slot:scripts>
        <script src="{{ asset('js/compare/manager.js') }}?v={{ filemtime(public_path('js/compare/manager.js')) }}"></script>
        <script src="{{ asset('js/compare/ui.js') }}?v={{ filemtime(public_path('js/compare/ui.js')) }}"></script>
        <script src="{{ asset('js/wishlist/page.js') }}?v={{ filemtime(public_path('js/wishlist/page.js')) }}"></script>
    </x-slot:scripts>

    {{-- 2. ナビゲーション --}}
    {{-- 検索窓を表示し、掲載台数(totalListingsCount)を渡します --}}
    <x-slot:navigation>
        <x-navigation :showSearch="true" />
    </x-slot:navigation>

    {{-- 3. メインコンテンツ --}}
    <div class="bg-gray-50 min-h-[calc(100vh-64px)] py-12 sm:py-20">
        <div class="max-w-7xl mx-auto px-4">
            
            {{-- ページヘッダー --}}
            <div class="mb-12 flex flex-col sm:flex-row sm:items-end justify-between border-b border-gray-200 pb-8 gap-4">
                <div>
                    <h1 class="text-3xl sm:text-4xl font-black text-black mb-2 tracking-tighter">
                        お気に入り一覧
                    </h1>
                    <p class="text-gray-400 text-xs font-bold tracking-widest uppercase flex items-center gap-2">
                        <i data-lucide="heart" class="w-3 h-3 text-red-500 fill-current"></i>
                        あなたがチェックしたバイク
                    </p>
                </div>
                <div class="text-right">
                    <span id="wishlist-total-label" class="text-3xl font-black text-black tabular-nums">0</span>
                    <span class="text-[10px] font-bold text-gray-400 ml-1 uppercase">台</span>
                </div>
            </div>

            {{-- お気に入り一覧表示エリア --}}
            <div id="wishlist-grid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 min-h-[400px]">
                {{-- JSでここにカードを流し込みます --}}
                <div class="col-span-full flex flex-col items-center justify-center py-20 text-gray-300" id="wishlist-loading">
                    <i data-lucide="loader-2" class="w-10 h-10 animate-spin mb-4"></i>
                    <p class="text-xs font-bold uppercase tracking-widest">Loading your bikes...</p>
                </div>
            </div>

            {{-- 空の状態の表示（初期は非表示） --}}
            <div id="wishlist-empty" class="hidden py-32 text-center">
                <div class="mb-8 inline-flex items-center justify-center w-24 h-24 bg-white rounded-full text-gray-200 shadow-sm border border-gray-100">
                    <i data-lucide="heart-off" class="w-10 h-10"></i>
                </div>
                <h2 class="text-xl font-black text-gray-900 mb-2 tracking-tight">お気に入りはまだありません</h2>
                <p class="text-sm text-gray-400 mb-10 max-w-sm mx-auto leading-relaxed">
                    気になるバイクを見つけたら、カードのハートマークをタップして保存しましょう。
                </p>
                <a href="{{ route('bikes.index') }}" class="inline-flex items-center gap-2 bg-black text-white px-10 py-4 rounded-2xl text-xs font-black uppercase tracking-widest hover:bg-gray-800 transition active:scale-95 shadow-xl shadow-black/10">
                    <i data-lucide="search" class="w-4 h-4"></i>
                    バイクを探しに行く
                </a>
            </div>
        </div>
    </div>
</x-layout>