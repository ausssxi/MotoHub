@props(['showSearch' => false, 'keyword' => ''])

<nav class="bg-white border-b border-gray-100 sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-16 gap-3">
            <div class="flex items-center flex-shrink-0 mr-1 sm:mr-0">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('bikes.index') }}" class="flex items-center gap-1.5 sm:gap-2 flex-shrink-0 group">
                        <img src="{{ asset('favicon.svg') }}" alt="MotoHub" class="w-7 h-7 sm:w-8 sm:h-8 group-hover:scale-110 transition-transform">
                        <span class="text-lg sm:text-xl font-black tracking-tighter">MotoHub</span>
                    </a>
                </div>
            </div>

            <!-- 中央: PC用検索窓 -->
            @if($showSearch)
            <div class="hidden md:flex flex-grow max-w-2xl relative mx-4 lg:mx-8" id="nav-search-container">
                <form action="{{ route('bikes.search') }}" method="GET" class="w-full" autocomplete="off" id="nav-search-form">
                    <div class="relative w-full">
                        <div class="absolute inset-y-0 left-3 flex items-center pointer-events-none text-gray-400">
                            <i data-lucide="search" class="w-4 h-4"></i>
                        </div>
                        <input type="text" name="keyword" id="nav-search-input" value="{{ $keyword }}" placeholder="車種を検索..." 
                            class="w-full bg-gray-100 border-none rounded-full pl-10 pr-4 py-2.5 text-sm focus:ring-2 focus:ring-black transition">
                    </div>
                </form>
                <div id="nav-suggest-results" class="absolute left-0 right-0 top-full mt-2 bg-white rounded-2xl shadow-2xl border border-gray-100 overflow-hidden hidden z-[110] text-left">
                    <div id="nav-suggest-list" class="py-2 max-h-[400px] overflow-y-auto"></div>
                </div>
            </div>
            @endif

            <!-- 右側のアクションエリア -->
            <div class="flex items-center gap-1 sm:gap-2 md:gap-3 flex-shrink-0">

                <!-- スマホ用検索ボタン -->
                @if($showSearch)
                <button id="mobile-nav-search-toggle" class="md:hidden p-1.5 sm:p-2 text-gray-400 hover:text-black rounded-full transition">
                    <i data-lucide="search" class="w-5 h-5 sm:w-6 sm:h-6"></i>
                </button>
                @endif

                {{-- バイク診断 (PC/タブレット以上) --}}
                <a href="/shindan" class="hidden md:flex items-center gap-1.5 px-3 py-2 text-[10px] font-black text-gray-700 hover:text-blue-600 hover:bg-blue-50 rounded-xl transition uppercase tracking-widest group relative" title="あなたにぴったりの1台を診断">
                    <i data-lucide="sparkles" class="w-4 h-4 text-blue-500 group-hover:animate-pulse"></i>
                    <span class="hidden xl:inline">バイク診断</span>
                    <span class="absolute -top-0.5 -right-0.5 flex h-2 w-2">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-blue-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-blue-500"></span>
                    </span>
                </a>

                {{-- 車種判定AI (PC/タブレット以上) --}}
                <a href="{{ route('bikes.identify') }}" class="hidden md:flex items-center gap-1.5 px-3 py-2 text-[10px] font-black text-gray-500 hover:text-purple-600 hover:bg-purple-50 rounded-xl transition uppercase tracking-widest" title="写真からバイクの車種を判定">
                    <i data-lucide="scan-eye" class="w-4 h-4"></i>
                    <span class="hidden xl:inline">車種判定</span>
                </a>

                {{-- ショップマップへのリンク --}}
                <a href="{{ route('shops.map') }}" class="hidden lg:flex items-center gap-1.5 px-3 py-2 text-[10px] font-black text-gray-500 hover:text-blue-600 hover:bg-blue-50 rounded-xl transition uppercase tracking-widest" title="地図から探す">
                    <i data-lucide="map" class="w-4 h-4"></i>
                    <span class="hidden xl:inline">地図検索</span>
                </a>

                {{-- 愛車ガレージへのリンク --}}
                <a href="{{ route('garage.public.index') }}" class="hidden lg:flex items-center gap-1.5 px-3 py-2 text-[10px] font-black text-gray-500 hover:text-pink-600 hover:bg-pink-50 rounded-xl transition uppercase tracking-widest" title="みんなの愛車ガレージ">
                    <i data-lucide="heart" class="w-4 h-4"></i>
                    <span class="hidden xl:inline">ガレージ</span>
                </a>

                {{-- 駐車場マップへのリンク --}}
                <a href="{{ route('parking.index') }}" class="hidden lg:flex items-center gap-1.5 px-3 py-2 text-[10px] font-black text-gray-500 hover:text-green-600 hover:bg-green-50 rounded-xl transition uppercase tracking-widest" title="バイク駐車場を探す">
                    <i data-lucide="square-parking" class="w-4 h-4"></i>
                    <span class="hidden xl:inline">駐車場</span>
                </a>

                {{-- AR駐車場ファインダー (モバイルのみ) --}}
                <a href="{{ route('ar.index') }}" class="hidden sm:flex lg:hidden items-center gap-1.5 px-3 py-2 text-[10px] font-black text-gray-500 hover:text-green-600 hover:bg-green-50 rounded-xl transition uppercase tracking-widest relative" title="ARで駐車場を探す">
                    <i data-lucide="camera" class="w-4 h-4"></i>
                    <span class="hidden sm:inline">AR</span>
                    <span class="absolute -top-0.5 -right-0.5 flex h-2 w-2">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-green-500"></span>
                    </span>
                </a>

                {{-- 相場ランキング (PCのみ) --}}
                <a href="{{ route('bikes.trends') }}" class="hidden xl:flex items-center gap-1.5 px-3 py-2 text-[10px] font-black text-gray-500 hover:text-blue-600 hover:bg-blue-50 rounded-xl transition uppercase tracking-widest" title="相場・価格変動ランキング">
                    <i data-lucide="trending-up" class="w-4 h-4"></i>
                    <span class="hidden xl:inline">相場ランキング</span>
                </a>

                {{-- 買取査定LPへのリンク --}}
                <a href="{{ route('sell.index') }}" class="hidden xl:flex items-center gap-1.5 px-3 py-2 text-[10px] font-black text-gray-500 hover:text-yellow-600 hover:bg-yellow-50 rounded-xl transition uppercase tracking-widest" title="あなたのバイクいくらで売れる？">
                    <i data-lucide="coins" class="w-4 h-4 text-yellow-500"></i>
                    買取相場
                </a>

                {{-- 通知ベル（対応環境のみ表示） --}}
                <div class="relative" x-data="pushBellDropdown()" x-on:click.outside="open = false" x-show="supported" x-cloak>
                    <button x-on:click="toggle()" class="relative flex flex-col items-center justify-center min-w-[36px] sm:min-w-[40px] px-1.5 sm:px-2 py-1 rounded-xl transition group"
                            x-bind:class="count > 0 ? 'text-blue-500 hover:bg-blue-50' : 'text-gray-400 hover:text-blue-500 hover:bg-blue-50'" title="通知設定">
                        <i data-lucide="bell" class="w-5 h-5 group-hover:scale-110 transition-transform"></i>
                        <span x-show="count > 0" x-text="count"
                              class="absolute -top-1 -right-2 min-w-[18px] h-[18px] px-1 bg-blue-500 text-white text-[10px] font-bold rounded-full border-2 border-white flex items-center justify-center shadow-sm">
                        </span>
                    </button>
                    <div x-show="open" x-transition
                         class="absolute right-0 mt-2 w-72 bg-white rounded-xl shadow-xl border border-gray-100 py-2 z-50"
                         style="display: none;">
                        <div class="px-4 py-2 border-b border-gray-50">
                            <p class="text-xs font-black text-gray-900">通知中の車種</p>
                        </div>
                        <div class="max-h-60 overflow-y-auto">
                            <template x-if="count === 0">
                                <p class="px-4 py-6 text-xs text-gray-400 text-center">通知登録はまだありません。<br>車種詳細ページから登録できます。</p>
                            </template>
                            <template x-for="item in items" x-bind:key="item.id">
                                <a x-bind:href="item.url" class="flex items-center justify-between px-4 py-2.5 hover:bg-blue-50 transition-colors">
                                    <span class="text-xs font-bold text-gray-700 truncate" x-text="item.name"></span>
                                    <i data-lucide="chevron-right" class="w-3 h-3 text-gray-300 shrink-0"></i>
                                </a>
                            </template>
                        </div>
                    </div>
                </div>
                <script>
                    document.addEventListener('alpine:init', function() {
                        Alpine.data('pushBellDropdown', function() {
                            return {
                                open: false,
                                count: 0,
                                items: [],
                                supported: false,
                                init: function() {
                                    // defer読み込み順の問題を回避: init時に判定
                                    try {
                                        this.supported = typeof MotoHubPush !== 'undefined'
                                            && typeof MotoHubPush.getNotificationSupport === 'function'
                                            && MotoHubPush.getNotificationSupport() === 'supported';
                                    } catch(e) {
                                        this.supported = false;
                                    }
                                    // それでもfalseなら少し待って再判定
                                    if (!this.supported) {
                                        var self = this;
                                        setTimeout(function() {
                                            try {
                                                self.supported = typeof MotoHubPush !== 'undefined'
                                                    && typeof MotoHubPush.getNotificationSupport === 'function'
                                                    && MotoHubPush.getNotificationSupport() === 'supported';
                                            } catch(e) {}
                                        }, 500);
                                    }
                                    this.loadSubs();
                                    var self = this;
                                    window.addEventListener('push-subs-changed', function() {
                                        self.items = [];
                                        self.loadSubs();
                                    });
                                },
                                toggle: function() {
                                    this.loadSubs();
                                    this.open = !this.open;
                                    var self = this;
                                    if (this.open) {
                                        this.$nextTick(function() { if (typeof lucide !== 'undefined') lucide.createIcons(); });
                                    }
                                },
                                loadSubs: function() {
                                    try {
                                        var subs = JSON.parse(localStorage.getItem('push_subs') || '{}');
                                        var ids = Object.keys(subs);
                                        this.count = ids.length;
                                        if (ids.length === 0) {
                                            this.items = [];
                                            return;
                                        }
                                        var self = this;
                                        fetch('/api/push/subscribed-models?ids=' + ids.join(','))
                                            .then(function(r) { return r.json(); })
                                            .then(function(data) {
                                                self.items = data;
                                                self.$nextTick(function() { if (typeof lucide !== 'undefined') lucide.createIcons(); });
                                            })
                                            .catch(function() {
                                                self.items = ids.map(function(id) {
                                                    return { id: id, name: '車種ID: ' + id, url: '/bikes/search?bike_model_id=' + id };
                                                });
                                            });
                                    } catch(e) { this.count = 0; this.items = []; }
                                }
                            };
                        });
                    });
                </script>

                {{-- お気に入り --}}
                <a href="{{ route('wishlist') }}" class="relative flex flex-col items-center justify-center min-w-[36px] sm:min-w-[40px] px-1.5 sm:px-2 py-1 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-xl transition group" title="お気に入り一覧">
                    <i data-lucide="heart" class="w-5 h-5 group-hover:scale-110 transition-transform"></i>
                    <span id="wishlist-count" 
                          class="absolute -top-1 -right-2 min-w-[18px] h-[18px] px-1 bg-red-500 text-white text-[10px] font-bold rounded-full border-2 border-white flex items-center justify-center shadow-sm transition-transform {{ isset($wishlistCount) && $wishlistCount > 0 ? '' : 'hidden' }}">
                        {{ $wishlistCount ?? '' }}
                    </span>
                </a>

                {{-- 比較 --}}
                <a href="{{ route('bikes.compare') }}" class="relative flex flex-col items-center justify-center min-w-[36px] sm:min-w-[40px] px-1.5 sm:px-2 py-1 text-gray-400 hover:text-blue-500 hover:bg-blue-50 rounded-xl transition group" title="車両比較">
                    <i data-lucide="scale" class="w-5 h-5 group-hover:scale-110 transition-transform"></i>
                    <span id="compare-count-badge"
                          class="absolute -top-1 -right-2 min-w-[18px] h-[18px] px-1 bg-blue-500 text-white text-[10px] font-bold rounded-full border-2 border-white flex items-center justify-center shadow-sm transition-transform hidden">
                    </span>
                </a>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        var ids = JSON.parse(localStorage.getItem('motohub_compare_list') || '[]');
                        var badge = document.getElementById('compare-count-badge');
                        if (badge && ids.length > 0) {
                            badge.textContent = ids.length;
                            badge.classList.remove('hidden');
                        }
                    });
                </script>

                {{-- ログイン・会員登録ボタン --}}
                <div class="relative ml-0.5 sm:ml-2" x-data="{ open: false }">
                    @auth
                        <button @click="open = !open" @click.outside="open = false" class="flex items-center gap-2 px-2 py-1.5 rounded-full hover:bg-gray-50 transition-colors border border-transparent hover:border-gray-200">
                            <div class="w-8 h-8 rounded-full bg-gray-900 text-white flex items-center justify-center overflow-hidden">
                                @if(auth()->user()->avatar)
                                    <img src="{{ auth()->user()->avatar }}" alt="" class="w-full h-full object-cover">
                                @else
                                    <i data-lucide="user" class="w-4 h-4"></i>
                                @endif
                            </div>
                            <span class="text-xs font-bold text-gray-700 hidden sm:block max-w-[100px] truncate">{{ auth()->user()->name }}</span>
                            <i data-lucide="chevron-down" class="w-3 h-3 text-gray-400"></i>
                        </button>

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
                        <div class="flex items-center gap-2">
                            <a href="{{ route('login') }}" class="text-xs font-bold text-gray-600 hover:text-black px-3 py-2 rounded-lg hover:bg-gray-100 transition-colors">
                                ログイン
                            </a>
                            <a href="{{ route('register') }}" class="hidden sm:flex text-xs font-bold text-white bg-black hover:bg-gray-800 px-4 py-2 rounded-full transition shadow-sm items-center gap-1">
                                <i data-lucide="user-plus" class="w-3.5 h-3.5"></i>
                                会員登録
                            </a>
                        </div>
                    @endauth
                </div>
            </div>
        </div>

        {{-- スマホ用ドロップダウン検索バー & 診断バナー --}}
        @if($showSearch)
        <div id="mobile-nav-search-bar" class="md:hidden hidden pt-4 pb-4 relative animate-in slide-in-from-top-2 duration-200">
            <form action="{{ route('bikes.search') }}" method="GET" class="w-full mb-3" id="mobile-nav-search-form" autocomplete="off">
                <div class="relative w-full">
                    <div class="absolute inset-y-0 left-3 flex items-center pointer-events-none text-gray-400">
                        <i data-lucide="search" class="w-4 h-4"></i>
                    </div>
                    <input type="search" name="keyword" id="mobile-nav-search-input" value="{{ $keyword }}" placeholder="車種名を入力..." 
                        class="w-full bg-gray-100 border-none rounded-xl pl-10 pr-4 py-3 text-base focus:ring-2 focus:ring-black transition">
                </div>
            </form>
            
            {{-- スマホでのみ表示される診断バナー --}}
            <a href="/shindan" class="flex items-center justify-between p-4 bg-gradient-to-r from-blue-600 to-indigo-600 rounded-2xl text-white shadow-lg active:scale-[0.98] transition-all">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-white/20 rounded-full flex items-center justify-center">
                        <i data-lucide="sparkles" class="w-6 h-6 fill-current"></i>
                    </div>
                    <div>
                        <p class="text-sm font-black">AIバイク診断をはじめる</p>
                        <p class="text-[10px] text-white/70 font-bold">あなたにぴったりの1台を提案</p>
                    </div>
                </div>
                <i data-lucide="chevron-right" class="w-5 h-5 opacity-50"></i>
            </a>

            {{-- 車種判定AIバナー --}}
            <a href="{{ route('bikes.identify') }}" class="flex items-center justify-between p-3 bg-white rounded-2xl border border-gray-200 mt-2 active:scale-[0.98] transition-all">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 bg-purple-100 rounded-full flex items-center justify-center">
                        <i data-lucide="scan-eye" class="w-4 h-4 text-purple-600"></i>
                    </div>
                    <div>
                        <p class="text-xs font-black text-gray-800">このバイクなに？ 車種判定AI</p>
                    </div>
                </div>
                <i data-lucide="chevron-right" class="w-4 h-4 text-gray-300"></i>
            </a>

            <div id="mobile-nav-suggest-results" class="absolute left-0 right-0 top-[60px] mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 overflow-hidden hidden z-[120] text-left">
                <div id="mobile-nav-suggest-list" class="py-2 max-h-[300px] overflow-y-auto"></div>
            </div>
        </div>
        @endif
    </div>
</nav>

{{-- ★復旧: サジェスト用スクリプトの読み込み --}}
@if($showSearch)
    <script src="{{ asset('js/search/suggest.js') }}"></script>
@endif