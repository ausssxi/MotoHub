@props(['showSearch' => false, 'keyword' => ''])

<nav class="bg-white border-b border-gray-100 sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        {{-- items-center で垂直方向の中央揃え --}}
        <div class="flex justify-between items-center h-16">
            <div class="flex items-center">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('bikes.index') }}" class="flex items-center gap-2 flex-shrink-0 group">
                        <img src="{{ asset('favicon.svg') }}" alt="MotoHub" class="w-8 h-8 group-hover:scale-110 transition-transform">
                        <span class="text-xl font-black tracking-tighter">MotoHub</span>
                    </a>
                </div>
            </div>

            <!-- 中央: PC用検索窓 -->
            @if($showSearch)
            <div class="hidden md:flex flex-grow max-w-md relative mx-8" id="nav-search-container">
                <form action="{{ route('bikes.search') }}" method="GET" class="w-full" autocomplete="off" id="nav-search-form">
                    <div class="relative w-full">
                        <div class="absolute inset-y-0 left-3 flex items-center pointer-events-none text-gray-400">
                            <i data-lucide="search" class="w-4 h-4"></i>
                        </div>
                        <input type="text" name="keyword" id="nav-search-input" value="{{ $keyword }}" placeholder="車種を検索..." 
                            class="w-full bg-gray-100 border-none rounded-full pl-10 pr-4 py-2.5 text-sm focus:ring-2 focus:ring-black transition-all">
                    </div>
                </form>
                <div id="nav-suggest-results" class="absolute left-0 right-0 top-full mt-2 bg-white rounded-2xl shadow-2xl border border-gray-100 overflow-hidden hidden z-[110] text-left">
                    <div id="nav-suggest-list" class="py-2 max-h-[400px] overflow-y-auto"></div>
                </div>
            </div>
            @endif

            <!-- 右側のアクションエリア -->
            <div class="flex items-center gap-3 sm:gap-5 flex-shrink-0">
                
                <!-- スマホ用検索ボタン -->
                @if($showSearch)
                <button id="mobile-nav-search-toggle" class="md:hidden p-2 text-gray-400 hover:text-black rounded-full transition-all">
                    <i data-lucide="search" class="w-6 h-6"></i>
                </button>
                @endif

                {{-- 相場ランキング (PCのみ) --}}
                <a href="{{ route('bikes.trends') }}" class="hidden lg:flex items-center gap-1.5 px-3 py-2 text-[10px] font-black text-gray-500 hover:text-blue-600 hover:bg-blue-50 rounded-xl transition-all uppercase tracking-widest" title="相場・価格変動ランキング">
                    <i data-lucide="trending-up" class="w-4 h-4"></i>
                    <span class="hidden xl:inline">相場ランキング</span>
                </a>

                {{-- お気に入り --}}
                <a href="{{ route('wishlist') }}" class="relative flex flex-col items-center justify-center min-w-[40px] px-2 py-1 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-xl transition-all group" title="お気に入り一覧">
                    <i data-lucide="heart" class="w-5 h-5 group-hover:scale-110 transition-transform"></i>
                    {{-- バッジ --}}
                    <span id="wishlist-count" class="absolute top-1 right-1 w-2.5 h-2.5 bg-red-500 rounded-full border-2 border-white" style="display: none;"></span>
                </a>

                {{-- ログイン・会員登録ボタン --}}
                <div class="relative ml-2" x-data="{ open: false }">
                    @auth
                        {{-- ログイン中: ユーザー名とドロップダウン --}}
                        <button @click="open = !open" @click.outside="open = false" class="flex items-center gap-2 px-2 py-1.5 rounded-full hover:bg-gray-50 transition-colors border border-transparent hover:border-gray-200">
                            <div class="w-8 h-8 rounded-full bg-gray-900 text-white flex items-center justify-center overflow-hidden">
                                <i data-lucide="user" class="w-4 h-4"></i>
                            </div>
                            <span class="text-xs font-bold text-gray-700 hidden sm:block max-w-[100px] truncate">{{ auth()->user()->name }}</span>
                            <i data-lucide="chevron-down" class="w-3 h-3 text-gray-400"></i>
                        </button>

                        {{-- ドロップダウンメニュー --}}
                        <div x-show="open" 
                             x-transition:enter="transition ease-out duration-100"
                             x-transition:enter-start="transform opacity-0 scale-95"
                             x-transition:enter-end="transform opacity-100 scale-100"
                             x-transition:leave="transition ease-in duration-75"
                             x-transition:leave-start="transform opacity-100 scale-100"
                             x-transition:leave-end="transform opacity-0 scale-95"
                             class="absolute right-0 mt-2 w-48 bg-white rounded-xl shadow-xl border border-gray-100 py-1 z-50" 
                             style="display: none;">
                            
                            <div class="px-4 py-3 border-b border-gray-50">
                                <p class="text-xs text-gray-500">ログイン中</p>
                                <p class="text-sm font-bold text-gray-900 truncate">{{ auth()->user()->email }}</p>
                            </div>

                            <a href="{{ route('dashboard') }}" class="block px-4 py-2.5 text-xs font-bold text-gray-700 hover:bg-blue-50 hover:text-blue-600 transition-colors flex items-center gap-2">
                                <i data-lucide="layout-dashboard" class="w-3.5 h-3.5"></i> マイページ
                            </a>
                            <a href="{{ route('profile.edit') }}" class="block px-4 py-2.5 text-xs font-bold text-gray-700 hover:bg-blue-50 hover:text-blue-600 transition-colors flex items-center gap-2">
                                <i data-lucide="settings" class="w-3.5 h-3.5"></i> アカウント設定
                            </a>
                            
                            <form method="POST" action="{{ route('logout') }}" class="border-t border-gray-50 mt-1">
                                @csrf
                                <button type="submit" class="w-full text-left px-4 py-2.5 text-xs font-bold text-red-600 hover:bg-red-50 transition-colors flex items-center gap-2">
                                    <i data-lucide="log-out" class="w-3.5 h-3.5"></i> ログアウト
                                </button>
                            </form>
                        </div>
                    @else
                        {{-- 未ログイン: ログイン/登録ボタン --}}
                        <div class="flex items-center gap-2">
                            <a href="{{ route('login') }}" class="text-xs font-bold text-gray-600 hover:text-black px-3 py-2 rounded-lg hover:bg-gray-100 transition-colors">
                                ログイン
                            </a>
                            <a href="{{ route('register') }}" class="hidden sm:flex text-xs font-bold text-white bg-black hover:bg-gray-800 px-4 py-2 rounded-full transition-all shadow-sm items-center gap-1">
                                <i data-lucide="user-plus" class="w-3.5 h-3.5"></i>
                                会員登録
                            </a>
                        </div>
                    @endauth
                </div>

                {{-- ★復活: 掲載台数表示（PCのみ、変数がある場合のみ表示） --}}
                @if(isset($totalListingsCount))
                <div class="hidden xl:flex items-center gap-2 border-l border-gray-100 pl-5 ml-2">
                    <div class="text-right">
                        <span class="text-[9px] font-bold text-gray-400 uppercase tracking-widest block leading-none mb-1">掲載台数</span>
                        <div class="flex items-baseline gap-0.5">
                            <span class="text-lg font-black text-black leading-none">{{ number_format($totalListingsCount) }}</span>
                            <span class="text-[10px] font-bold text-gray-500">台</span>
                        </div>
                    </div>
                </div>
                @endif

            </div>
        </div>

        {{-- スマホ用ドロップダウン検索バー (既存機能) --}}
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
            <div id="mobile-nav-suggest-results" class="absolute left-0 right-0 top-full mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 overflow-hidden hidden z-[120] text-left">
                <div id="mobile-nav-suggest-list" class="py-2 max-h-[300px] overflow-y-auto"></div>
            </div>
        </div>
        @endif
    </div>
</nav>

{{-- サジェスト用スクリプトの読み込み (既存機能) --}}
@if($showSearch)
    <script src="{{ asset('js/search/suggest.js') }}"></script>
@endif