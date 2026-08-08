@props(['showSearch' => true, 'keyword' => ''])

@php
    $navIs = function (...$patterns) {
        foreach ($patterns as $p) {
            if (request()->is($p)) return true;
        }
        return false;
    };
    $isShindan  = $navIs('shindan*');
    $isRanking  = $navIs('ranking*');
    $isNews     = $navIs('news*');
    $isMap      = $navIs('riders-map*', 'parking*');
    $isShop     = $navIs('shops*');
    $isBlog     = $navIs('blog*');
    $isSouba    = $navIs('trends*', 'sell*');
    $isOther    = $navIs('bikes/overseas*', 'parts*', 'identify*', 'garage/public*', 'ar*', 'quiz*', 'warashibe*', 'puzzle*', 'games/*', 'touring*');
@endphp

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

            <!-- 右側のアクションエリア -->
            <div class="flex items-center gap-1 sm:gap-2 md:gap-3 flex-shrink-0">

                <!-- スマホ用検索ボタン -->
                @if($showSearch)
                <button id="mobile-nav-search-toggle" class="md:hidden p-1.5 sm:p-2 text-gray-400 hover:text-black rounded-full transition">
                    <i data-lucide="search" class="w-5 h-5 sm:w-6 sm:h-6"></i>
                </button>
                @endif

                {{-- PC用検索アイコン（$showSearch=false のTOPページでは非表示） --}}
                @if($showSearch)
                <button class="hidden md:flex items-center gap-1.5 px-3 py-2 text-[10px] font-black text-gray-500 hover:text-blue-600 hover:bg-blue-50 rounded-xl transition uppercase tracking-widest"
                        title="検索"
                        onclick="document.getElementById('nav-search-panel').classList.toggle('hidden'); this.querySelector('[data-lucide]') && lucide.createIcons(); setTimeout(() => document.getElementById('nav-search-input')?.focus(), 100);">
                    <i data-lucide="search" class="w-4 h-4"></i>
                    <span class="hidden xl:inline">検索</span>
                </button>
                @endif

                {{-- AIで探す --}}
                <a href="{{ route('ai-search') }}" class="hidden md:flex items-center gap-1.5 px-3 py-2 text-[10px] font-black {{ $navIs('ai-search*') ? 'text-blue-600 bg-blue-50' : 'text-gray-700 hover:text-blue-600 hover:bg-blue-50' }} rounded-xl transition uppercase tracking-widest group relative" title="AIで探す">
                    <i data-lucide="bot" class="w-4 h-4 text-purple-500 group-hover:animate-pulse"></i>
                    <span class="hidden xl:inline">AIで探す</span>
                    <span class="absolute -top-0.5 -right-0.5 flex h-2 w-2">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-purple-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-purple-500"></span>
                    </span>
                </a>

                {{-- バイク相性診断 --}}
                <a href="{{ route('shindan.index') }}" class="hidden md:flex items-center gap-1.5 px-3 py-2 text-[10px] font-black {{ $isShindan ? 'text-blue-600 bg-blue-50' : 'text-gray-700 hover:text-blue-600 hover:bg-blue-50' }} rounded-xl transition uppercase tracking-widest group relative" title="あなたにぴったりの1台を診断">
                    <i data-lucide="sparkles" class="w-4 h-4 text-blue-500 group-hover:animate-pulse"></i>
                    <span class="hidden xl:inline">バイク相性診断</span>
                    <span class="absolute -top-0.5 -right-0.5 flex h-2 w-2">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-blue-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-blue-500"></span>
                    </span>
                </a>

                {{-- ランキング --}}
                <a href="{{ route('ranking.index') }}" class="hidden md:flex items-center gap-1.5 px-3 py-2 text-[10px] font-black {{ $isRanking ? 'text-blue-600 bg-blue-50' : 'text-gray-500 hover:text-blue-600 hover:bg-blue-50' }} rounded-xl transition uppercase tracking-widest" title="売れ筋ランキング">
                    <i data-lucide="trophy" class="w-4 h-4"></i>
                    <span class="hidden xl:inline">ランキング</span>
                </a>

                {{-- ニュース --}}
                <a href="{{ route('news.index') }}" class="hidden md:flex items-center gap-1.5 px-3 py-2 text-[10px] font-black {{ $isNews ? 'text-blue-600 bg-blue-50' : 'text-gray-500 hover:text-blue-600 hover:bg-blue-50' }} rounded-xl transition uppercase tracking-widest" title="バイク業界の最新ニュース">
                    <i data-lucide="newspaper" class="w-4 h-4"></i>
                    <span class="hidden xl:inline">ニュース</span>
                </a>

                {{-- マ���プ --}}
                <a href="{{ route('riders.map') }}" class="hidden md:flex items-center gap-1.5 px-3 py-2 text-[10px] font-black {{ $isMap ? 'text-blue-600 bg-blue-50' : 'text-gray-500 hover:text-blue-600 hover:bg-blue-50' }} rounded-xl transition uppercase tracking-widest" title="ライダーズマップ">
                    <i data-lucide="map" class="w-4 h-4"></i>
                    <span class="hidden xl:inline">マップ</span>
                </a>

                {{-- ショップを探す（一覧・店名検索・投稿の玄関。マップ=地図で探す との棲み分け） --}}
                <a href="{{ route('shops.area.index') }}" class="hidden md:flex items-center gap-1.5 px-3 py-2 text-[10px] font-black {{ $isShop ? 'text-blue-600 bg-blue-50' : 'text-gray-500 hover:text-blue-600 hover:bg-blue-50' }} rounded-xl transition uppercase tracking-widest" title="バイクショップを探す（地域・店名検索・掲載リクエスト）">
                    <i data-lucide="store" class="w-4 h-4"></i>
                    <span class="hidden xl:inline">ショップを探す</span>
                </a>

                {{-- ブログ --}}
                <a href="{{ route('blog.index') }}" class="hidden md:flex items-center gap-1.5 px-3 py-2 text-[10px] font-black {{ $isBlog ? 'text-blue-600 bg-blue-50' : 'text-gray-500 hover:text-blue-600 hover:bg-blue-50' }} rounded-xl transition uppercase tracking-widest" title="ブログ記事">
                    <i data-lucide="pen-line" class="w-4 h-4"></i>
                    <span class="hidden xl:inline">ブログ</span>
                </a>

                {{-- 相場 ドロップダウン --}}
                <div class="hidden md:flex relative" x-data="{ open: false }">
                    <button @click="open = !open" @click.outside="open = false"
                        class="flex items-center gap-1.5 px-3 py-2 text-[10px] font-black {{ $isSouba ? 'text-blue-600 bg-blue-50' : 'text-gray-500 hover:text-blue-600 hover:bg-blue-50' }} rounded-xl transition uppercase tracking-widest" title="相場">
                        <i data-lucide="trending-up" class="w-4 h-4"></i>
                        <span class="hidden xl:inline">相場</span>
                        <i data-lucide="chevron-down" class="w-3 h-3 transition-transform duration-200" x-bind:class="{ 'rotate-180': open }"></i>
                    </button>
                    <div x-show="open" x-transition
                         class="absolute left-0 top-full mt-1 w-48 max-h-[calc(100vh-5rem)] overflow-y-auto bg-white rounded-xl shadow-xl border border-gray-100 py-1 z-50"
                         style="display: none;">
                        <a href="{{ route('market') }}" class="flex items-center gap-2 px-4 py-2.5 text-xs font-bold text-gray-700 hover:bg-blue-50 hover:text-blue-600 transition-colors whitespace-nowrap">
                            <i data-lucide="line-chart" class="w-3.5 h-3.5"></i>
                            相場トップ
                        </a>
                        <a href="{{ route('bikes.trends') }}" class="flex items-center gap-2 px-4 py-2.5 text-xs font-bold text-gray-700 hover:bg-blue-50 hover:text-blue-600 transition-colors whitespace-nowrap">
                            <i data-lucide="trophy" class="w-3.5 h-3.5"></i>
                            相場ランキング
                        </a>
                        <a href="{{ route('bikes.region_price_index') }}" class="flex items-center gap-2 px-4 py-2.5 text-xs font-bold text-gray-700 hover:bg-green-50 hover:text-green-600 transition-colors whitespace-nowrap">
                            <i data-lucide="map-pinned" class="w-3.5 h-3.5"></i>
                            エリア別相場
                        </a>
                        <a href="{{ route('sell.index') }}" class="flex items-center gap-2 px-4 py-2.5 text-xs font-bold text-gray-700 hover:bg-yellow-50 hover:text-yellow-600 transition-colors whitespace-nowrap">
                            <i data-lucide="coins" class="w-3.5 h-3.5"></i>
                            買取相場
                        </a>
                    </div>
                </div>

                {{-- その他 ドロップダウン --}}
                <div class="hidden md:flex relative" x-data="{ open: false }">
                    <button @click="open = !open" @click.outside="open = false"
                        class="flex items-center gap-1.5 px-3 py-2 text-[10px] font-black {{ $isOther ? 'text-blue-600 bg-blue-50' : 'text-gray-500 hover:text-gray-800 hover:bg-gray-100' }} rounded-xl transition uppercase tracking-widest" title="その他">
                        <i data-lucide="more-horizontal" class="w-4 h-4"></i>
                        <span class="hidden xl:inline">その他</span>
                        <i data-lucide="chevron-down" class="w-3 h-3 transition-transform duration-200" x-bind:class="{ 'rotate-180': open }"></i>
                    </button>
                    <div x-show="open" x-transition
                         class="absolute left-0 top-full mt-1 w-48 max-h-[calc(100vh-5rem)] overflow-y-auto bg-white rounded-xl shadow-xl border border-gray-100 py-1 z-50"
                         style="display: none;">
                        {{-- コンテンツ --}}
                        <a href="{{ route('bikes.reviews_index') }}" class="flex items-center gap-2 px-4 py-2.5 text-xs font-bold text-gray-700 hover:bg-blue-50 hover:text-blue-600 transition-colors whitespace-nowrap">
                            <i data-lucide="star" class="w-3.5 h-3.5"></i>
                            レビュー
                        </a>
                        <a href="{{ route('hoken') }}" class="flex items-center gap-2 px-4 py-2.5 text-xs font-bold text-gray-700 hover:bg-blue-50 hover:text-blue-600 transition-colors whitespace-nowrap">
                            <i data-lucide="shield" class="w-3.5 h-3.5"></i>
                            バイク保険・維持費
                        </a>
                        <a href="{{ route('theft') }}" class="flex items-center gap-2 px-4 py-2.5 text-xs font-bold text-gray-700 hover:bg-blue-50 hover:text-blue-600 transition-colors whitespace-nowrap">
                            <i data-lucide="shield-alert" class="w-3.5 h-3.5"></i>
                            バイクの盗難データ
                        </a>
                        <div class="my-1 border-t border-gray-100"></div>
                        {{-- ゲームセクション --}}
                        <p class="px-4 pt-2 pb-1 text-[9px] font-black text-gray-400 uppercase tracking-widest">ゲーム</p>
                        <a href="{{ route('games.subaracity') }}" class="flex items-center gap-2 px-4 py-2.5 text-xs font-bold text-gray-700 hover:bg-orange-50 hover:text-orange-600 transition-colors whitespace-nowrap">
                            <i data-lucide="gamepad-2" class="w-3.5 h-3.5"></i>
                            バイクガレージパズル
                        </a>
                        <a href="{{ route('warashibe') }}" class="flex items-center gap-2 px-4 py-2.5 text-xs font-bold text-gray-700 hover:bg-orange-50 hover:text-orange-600 transition-colors whitespace-nowrap">
                            <i data-lucide="gamepad-2" class="w-3.5 h-3.5"></i>
                            わらしべ長者
                        </a>
                        <a href="{{ route('quiz') }}" class="flex items-center gap-2 px-4 py-2.5 text-xs font-bold text-gray-700 hover:bg-orange-50 hover:text-orange-600 transition-colors whitespace-nowrap">
                            <i data-lucide="gamepad-2" class="w-3.5 h-3.5"></i>
                            バイククイズ
                        </a>
                        <a href="{{ route('puzzle') }}" class="flex items-center gap-2 px-4 py-2.5 text-xs font-bold text-gray-700 hover:bg-orange-50 hover:text-orange-600 transition-colors whitespace-nowrap">
                            <i data-lucide="puzzle" class="w-3.5 h-3.5"></i>
                            バイク2048
                        </a>
                        <div class="my-1 border-t border-gray-100"></div>
                        {{-- ツ��ルセクション --}}
                        <p class="px-4 pt-2 pb-1 text-[9px] font-black text-gray-400 uppercase tracking-widest">ツール</p>
                        <a href="{{ route('ai-search') }}" class="flex items-center gap-2 px-4 py-2.5 text-xs font-bold text-gray-700 hover:bg-purple-50 hover:text-purple-600 transition-colors whitespace-nowrap">
                            <i data-lucide="bot" class="w-3.5 h-3.5"></i>
                            AIで探す
                        </a>
                        <a href="{{ route('pages.widget') }}" class="flex items-center gap-2 px-4 py-2.5 text-xs font-bold text-gray-700 hover:bg-blue-50 hover:text-blue-600 transition-colors whitespace-nowrap" title="ブログに貼れる相場ウィジェット">
                            <i data-lucide="code-2" class="w-3.5 h-3.5"></i>
                            相場ウィジェット
                        </a>
                        <a href="{{ route('parts.index') }}" class="flex items-center gap-2 px-4 py-2.5 text-xs font-bold text-gray-700 hover:bg-blue-50 hover:text-blue-600 transition-colors whitespace-nowrap">
                            <i data-lucide="wrench" class="w-3.5 h-3.5"></i>
                            パーツ
                        </a>
                        <a href="{{ route('trouble.index') }}" class="flex items-center gap-2 px-4 py-2.5 text-xs font-bold text-gray-700 hover:bg-blue-50 hover:text-blue-600 transition-colors whitespace-nowrap" title="症状から原因を切り分け（バイク選びの「バイク相性診断」とは別）">
                            <i data-lucide="stethoscope" class="w-3.5 h-3.5"></i>
                            症状診断
                        </a>
                        <a href="{{ route('bikes.identify') }}" class="flex items-center gap-2 px-4 py-2.5 text-xs font-bold text-gray-700 hover:bg-purple-50 hover:text-purple-600 transition-colors whitespace-nowrap">
                            <i data-lucide="scan-eye" class="w-3.5 h-3.5"></i>
                            車種判定AI
                        </a>
                        <a href="{{ route('garage.public.index') }}" class="flex items-center gap-2 px-4 py-2.5 text-xs font-bold text-gray-700 hover:bg-pink-50 hover:text-pink-600 transition-colors whitespace-nowrap">
                            <i data-lucide="bike" class="w-3.5 h-3.5"></i>
                            愛車ガレージ
                        </a>
                        <a href="{{ route('ar.index') }}" class="flex items-center gap-2 px-4 py-2.5 text-xs font-bold text-gray-700 hover:bg-green-50 hover:text-green-600 transition-colors whitespace-nowrap">
                            <i data-lucide="camera" class="w-3.5 h-3.5"></i>
                            AR駐車場ファインダー
                        </a>
                        <div class="my-1 border-t border-gray-100"></div>
                        {{-- ガイドセクション --}}
                        <p class="px-4 pt-2 pb-1 text-[9px] font-black text-gray-400 uppercase tracking-widest">ガイド</p>
                        <a href="{{ route('touring.index') }}" class="flex items-center gap-2 px-4 py-2.5 text-xs font-bold text-gray-700 hover:bg-cyan-50 hover:text-cyan-600 transition-colors whitespace-nowrap">
                            <i data-lucide="map-pin" class="w-3.5 h-3.5"></i>
                            ツーリングガイド・スポット
                        </a>
                        <a href="{{ route('license.index') }}" class="flex items-center gap-2 px-4 py-2.5 text-xs font-bold text-gray-700 hover:bg-blue-50 hover:text-blue-600 transition-colors whitespace-nowrap">
                            <i data-lucide="book-open" class="w-3.5 h-3.5"></i>
                            バイク免許
                        </a>
                        <a href="{{ route('data') }}" class="flex items-center gap-2 px-4 py-2.5 text-xs font-bold text-gray-700 hover:bg-blue-50 hover:text-blue-600 transition-colors whitespace-nowrap">
                            <i data-lucide="database" class="w-3.5 h-3.5"></i>
                            データ提供
                        </a>
                    </div>
                </div>

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
                            <p class="text-[10px] text-gray-400 font-bold mt-0.5">📬 毎朝 8:30 に値下げ・新着をお届け中</p>
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
                                @if(auth()->user()->avatar_url)
                                    <img src="{{ auth()->user()->avatar_url }}" alt="" class="w-full h-full object-cover">
                                @else
                                    <i data-lucide="user" class="w-4 h-4"></i>
                                @endif
                            </div>
                            <span class="text-xs font-bold text-gray-700 hidden sm:block max-w-[100px] truncate">{{ auth()->user()->displayName() }}</span>
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
                            {{-- 自分の公開プロフィール（自分の public_token のみ・公開ガレージがある時だけ表示） --}}
                            @if(auth()->user()->public_token)
                            <a href="{{ route('riders.profile', auth()->user()->public_token) }}" target="_blank" rel="noopener" class="block px-4 py-2.5 text-xs font-bold text-gray-700 hover:bg-blue-50 hover:text-blue-600 transition-colors flex items-center gap-2">
                                <i data-lucide="user-circle" class="w-3.5 h-3.5"></i> 公開プロフィールを見る
                            </a>
                            @endif
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
            
            {{-- ツールセクション --}}
            <p class="text-[9px] font-black text-gray-400 uppercase tracking-widest mb-1 px-1">ツール</p>
            <a href="{{ route('ai-search') }}" class="flex items-center justify-between p-4 bg-gradient-to-r from-purple-600 to-indigo-600 rounded-2xl text-white shadow-lg active:scale-[0.98] transition-all">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-white/20 rounded-full flex items-center justify-center">
                        <i data-lucide="bot" class="w-6 h-6 fill-current"></i>
                    </div>
                    <div>
                        <p class="text-sm font-black">AIで探す</p>
                        <p class="text-[10px] text-white/70 font-bold">条件を話しかけるだけでOK</p>
                    </div>
                </div>
                <i data-lucide="chevron-right" class="w-5 h-5 opacity-50"></i>
            </a>
            <a href="{{ route('shindan.index') }}" class="flex items-center justify-between p-4 bg-gradient-to-r from-blue-600 to-indigo-600 rounded-2xl text-white shadow-lg active:scale-[0.98] transition-all mt-2">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-white/20 rounded-full flex items-center justify-center">
                        <i data-lucide="sparkles" class="w-6 h-6 fill-current"></i>
                    </div>
                    <div>
                        <p class="text-sm font-black">AIバイク相性診断</p>
                        <p class="text-[10px] text-white/70 font-bold">あなたにぴったりの1台を提案</p>
                    </div>
                </div>
                <i data-lucide="chevron-right" class="w-5 h-5 opacity-50"></i>
            </a>
            <a href="{{ route('bikes.identify') }}" class="flex items-center justify-between p-3 bg-white rounded-2xl border border-gray-200 mt-2 active:scale-[0.98] transition-all">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 bg-purple-100 rounded-full flex items-center justify-center">
                        <i data-lucide="scan-eye" class="w-4 h-4 text-purple-600"></i>
                    </div>
                    <p class="text-xs font-black text-gray-800">車種判定AI</p>
                </div>
                <i data-lucide="chevron-right" class="w-4 h-4 text-gray-300"></i>
            </a>
            <a href="{{ route('parts.index') }}" class="flex items-center justify-between p-3 bg-white rounded-2xl border border-gray-200 mt-2 active:scale-[0.98] transition-all">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                        <i data-lucide="wrench" class="w-4 h-4 text-blue-600"></i>
                    </div>
                    <p class="text-xs font-black text-gray-800">パーツ検索</p>
                </div>
                <i data-lucide="chevron-right" class="w-4 h-4 text-gray-300"></i>
            </a>
            <a href="{{ route('ar.index') }}" class="flex items-center justify-between p-3 bg-white rounded-2xl border border-gray-200 mt-2 active:scale-[0.98] transition-all">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                        <i data-lucide="camera" class="w-4 h-4 text-green-600"></i>
                    </div>
                    <p class="text-xs font-black text-gray-800">AR駐車場ファインダー</p>
                </div>
                <i data-lucide="chevron-right" class="w-4 h-4 text-gray-300"></i>
            </a>
            <a href="{{ route('pages.widget') }}" class="flex items-center justify-between p-3 bg-white rounded-2xl border border-gray-200 mt-2 active:scale-[0.98] transition-all">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                        <i data-lucide="code-2" class="w-4 h-4 text-blue-600"></i>
                    </div>
                    <p class="text-xs font-black text-gray-800">相場ウィジェット</p>
                </div>
                <i data-lucide="chevron-right" class="w-4 h-4 text-gray-300"></i>
            </a>

            {{-- ゲームセクション --}}
            <p class="text-[9px] font-black text-gray-400 uppercase tracking-widest mt-4 mb-1 px-1">ゲーム</p>
            <div class="grid grid-cols-2 gap-2">
                <a href="{{ route('games.subaracity') }}" class="flex items-center gap-2 p-3 bg-white rounded-2xl border border-gray-200 active:scale-[0.98] transition-all">
                    <div class="w-7 h-7 bg-orange-100 rounded-full flex items-center justify-center shrink-0">
                        <i data-lucide="gamepad-2" class="w-3.5 h-3.5 text-orange-600"></i>
                    </div>
                    <p class="text-[11px] font-black text-gray-800 leading-tight">ガレージパズル</p>
                </a>
                <a href="{{ route('quiz') }}" class="flex items-center gap-2 p-3 bg-white rounded-2xl border border-gray-200 active:scale-[0.98] transition-all">
                    <div class="w-7 h-7 bg-orange-100 rounded-full flex items-center justify-center shrink-0">
                        <i data-lucide="gamepad-2" class="w-3.5 h-3.5 text-orange-600"></i>
                    </div>
                    <p class="text-[11px] font-black text-gray-800 leading-tight">バイク4択クイズ</p>
                </a>
                <a href="{{ route('warashibe') }}" class="flex items-center gap-2 p-3 bg-white rounded-2xl border border-gray-200 active:scale-[0.98] transition-all">
                    <div class="w-7 h-7 bg-orange-100 rounded-full flex items-center justify-center shrink-0">
                        <i data-lucide="gamepad-2" class="w-3.5 h-3.5 text-orange-600"></i>
                    </div>
                    <p class="text-[11px] font-black text-gray-800 leading-tight">わらしべ</p>
                </a>
                <a href="{{ route('puzzle') }}" class="flex items-center gap-2 p-3 bg-white rounded-2xl border border-gray-200 active:scale-[0.98] transition-all">
                    <div class="w-7 h-7 bg-orange-100 rounded-full flex items-center justify-center shrink-0">
                        <i data-lucide="puzzle" class="w-3.5 h-3.5 text-orange-600"></i>
                    </div>
                    <p class="text-[11px] font-black text-gray-800 leading-tight">バイク2048</p>
                </a>
            </div>

            {{-- 情報セクション --}}
            <p class="text-[9px] font-black text-gray-400 uppercase tracking-widest mt-4 mb-1 px-1">情報</p>
            <a href="{{ route('blog.index') }}" class="flex items-center justify-between p-3 bg-white rounded-2xl border border-gray-200 active:scale-[0.98] transition-all">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 bg-amber-100 rounded-full flex items-center justify-center">
                        <i data-lucide="pen-line" class="w-4 h-4 text-amber-600"></i>
                    </div>
                    <p class="text-xs font-black text-gray-800">ブログ</p>
                </div>
                <i data-lucide="chevron-right" class="w-4 h-4 text-gray-300"></i>
            </a>
            <a href="{{ route('sell.index') }}" class="flex items-center justify-between p-3 bg-white rounded-2xl border border-gray-200 mt-2 active:scale-[0.98] transition-all">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 bg-yellow-100 rounded-full flex items-center justify-center">
                        <i data-lucide="coins" class="w-4 h-4 text-yellow-600"></i>
                    </div>
                    <p class="text-xs font-black text-gray-800">買取相場</p>
                </div>
                <i data-lucide="chevron-right" class="w-4 h-4 text-gray-300"></i>
            </a>
            <a href="{{ route('market') }}" class="flex items-center justify-between p-3 bg-white rounded-2xl border border-gray-200 mt-2 active:scale-[0.98] transition-all">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                        <i data-lucide="line-chart" class="w-4 h-4 text-blue-600"></i>
                    </div>
                    <p class="text-xs font-black text-gray-800">相場トップ</p>
                </div>
                <i data-lucide="chevron-right" class="w-4 h-4 text-gray-300"></i>
            </a>
            <a href="{{ route('bikes.trends') }}" class="flex items-center justify-between p-3 bg-white rounded-2xl border border-gray-200 mt-2 active:scale-[0.98] transition-all">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 bg-rose-100 rounded-full flex items-center justify-center">
                        <i data-lucide="trophy" class="w-4 h-4 text-rose-600"></i>
                    </div>
                    <p class="text-xs font-black text-gray-800">相場ランキング</p>
                </div>
                <i data-lucide="chevron-right" class="w-4 h-4 text-gray-300"></i>
            </a>
            <a href="{{ route('bikes.region_price_index') }}" class="flex items-center justify-between p-3 bg-white rounded-2xl border border-gray-200 mt-2 active:scale-[0.98] transition-all">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                        <i data-lucide="map-pinned" class="w-4 h-4 text-green-600"></i>
                    </div>
                    <p class="text-xs font-black text-gray-800">エリア別相場</p>
                </div>
                <i data-lucide="chevron-right" class="w-4 h-4 text-gray-300"></i>
            </a>

            {{-- ガイドセクション --}}
            <p class="text-[9px] font-black text-gray-400 uppercase tracking-widest mt-4 mb-1 px-1">ガイド</p>
            <a href="{{ route('touring.index') }}" class="flex items-center justify-between p-3 bg-white rounded-2xl border border-gray-200 active:scale-[0.98] transition-all">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 bg-cyan-100 rounded-full flex items-center justify-center">
                        <i data-lucide="map-pin" class="w-4 h-4 text-cyan-600"></i>
                    </div>
                    <div>
                        <p class="text-xs font-black text-gray-800">ツーリングガイド・スポット</p>
                        <p class="text-[10px] text-gray-400">全国{{ \App\Models\TouringSpot::count() }}スポット掲載</p>
                    </div>
                </div>
                <i data-lucide="chevron-right" class="w-4 h-4 text-gray-300"></i>
            </a>
            <a href="{{ route('license.index') }}" class="flex items-center justify-between p-3 bg-white rounded-2xl border border-gray-200 mt-2 active:scale-[0.98] transition-all">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                        <i data-lucide="book-open" class="w-4 h-4 text-blue-600"></i>
                    </div>
                    <p class="text-xs font-black text-gray-800">バイク免許</p>
                </div>
                <i data-lucide="chevron-right" class="w-4 h-4 text-gray-300"></i>
            </a>
            <a href="{{ route('riders.map') }}" class="flex items-center justify-between p-3 bg-white rounded-2xl border border-gray-200 mt-2 active:scale-[0.98] transition-all">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 bg-teal-100 rounded-full flex items-center justify-center">
                        <i data-lucide="map" class="w-4 h-4 text-teal-600"></i>
                    </div>
                    <p class="text-xs font-black text-gray-800">ライダーズマップ</p>
                </div>
                <i data-lucide="chevron-right" class="w-4 h-4 text-gray-300"></i>
            </a>
            <a href="{{ route('data') }}" class="flex items-center justify-between p-3 bg-white rounded-2xl border border-gray-200 mt-2 active:scale-[0.98] transition-all">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                        <i data-lucide="database" class="w-4 h-4 text-blue-600"></i>
                    </div>
                    <p class="text-xs font-black text-gray-800">データ提供</p>
                </div>
                <i data-lucide="chevron-right" class="w-4 h-4 text-gray-300"></i>
            </a>

            {{-- マイページセクション --}}
            <p class="text-[9px] font-black text-gray-400 uppercase tracking-widest mt-4 mb-1 px-1">マイページ</p>
            <a href="{{ route('mybikes.index') }}" class="flex items-center justify-between p-3 bg-white rounded-2xl border border-gray-200 active:scale-[0.98] transition-all">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 bg-pink-100 rounded-full flex items-center justify-center">
                        <i data-lucide="bike" class="w-4 h-4 text-pink-600"></i>
                    </div>
                    <p class="text-xs font-black text-gray-800">愛車ガレージ</p>
                </div>
                <i data-lucide="chevron-right" class="w-4 h-4 text-gray-300"></i>
            </a>
            <a href="{{ route('wishlist') }}" class="flex items-center justify-between p-3 bg-white rounded-2xl border border-gray-200 mt-2 active:scale-[0.98] transition-all">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 bg-red-100 rounded-full flex items-center justify-center">
                        <i data-lucide="heart" class="w-4 h-4 text-red-600"></i>
                    </div>
                    <p class="text-xs font-black text-gray-800">お気に入り</p>
                </div>
                <i data-lucide="chevron-right" class="w-4 h-4 text-gray-300"></i>
            </a>

            <div id="mobile-nav-suggest-results" class="absolute left-0 right-0 top-[60px] mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 overflow-hidden hidden z-[120] text-left">
                <div id="mobile-nav-suggest-list" class="py-2 max-h-[300px] overflow-y-auto"></div>
            </div>
        </div>
        @endif
    </div>

    {{-- PC用フルワイド検索パネル --}}
    @if($showSearch)
    <div id="nav-search-panel" class="hidden border-t border-gray-100 shadow-lg bg-white">
        <div id="nav-search-container" class="max-w-xl mx-auto px-4 py-4 relative">
            <form action="{{ route('bikes.search') }}" method="GET" class="w-full" autocomplete="off" id="nav-search-form">
                <div class="relative w-full">
                    <div class="absolute inset-y-0 left-3 flex items-center pointer-events-none text-gray-400">
                        <i data-lucide="search" class="w-4 h-4"></i>
                    </div>
                    <input type="text" name="keyword" id="nav-search-input" value="{{ $keyword }}" placeholder="車種を検索..."
                        class="w-full bg-gray-100 border-none rounded-xl pl-10 pr-10 py-3 text-sm focus:ring-2 focus:ring-black transition">
                    <button type="button" class="absolute inset-y-0 right-2 flex items-center text-gray-400 hover:text-gray-600"
                            onclick="document.getElementById('nav-search-panel').classList.add('hidden')">
                        <i data-lucide="x" class="w-4 h-4"></i>
                    </button>
                </div>
            </form>
            <div id="nav-suggest-results" class="mt-2 bg-white rounded-xl overflow-hidden hidden text-left">
                <div id="nav-suggest-list" class="py-2 max-h-[400px] overflow-y-auto"></div>
            </div>
        </div>
    </div>
    @endif
</nav>

{{-- ★復旧: サジェスト用スクリプトの読み込み --}}
@if($showSearch)
    <script src="{{ asset('js/search/suggest.js') }}?v={{ asset_buster(public_path('js/search/suggest.js')) }}"></script>
@endif