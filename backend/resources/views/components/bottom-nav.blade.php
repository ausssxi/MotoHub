{{-- モバイル下部タブナビゲーション（md以上で非表示） --}}
@php
    $isMenuActive = request()->is('shindan*')
        || request()->is('identify*')
        || request()->is('parts*')
        || request()->is('blog*')
        || request()->is('sell*')
        || request()->is('trends*')
        || request()->is('garage*')
        || request()->is('wishlist*')
        || request()->is('shops/map*')
        || request()->is('ar*')
        || request()->is('quiz*');
@endphp
<nav id="bottom-nav" class="fixed bottom-0 left-0 right-0 w-full bg-white border-t border-gray-100 z-50 md:hidden" style="height:60px;" aria-label="モバイルナビゲーション">
    <div class="flex w-full h-full items-center justify-around">

        {{-- 検索 --}}
        <a href="{{ route('bikes.search') }}"
           class="flex flex-col items-center justify-center gap-0.5 flex-1 py-1 {{ request()->is('bikes/search*') ? 'text-blue-600' : 'text-gray-400' }}">
            <i data-lucide="search" class="w-5 h-5"></i>
            <span class="text-[10px] font-bold leading-tight">検索</span>
        </a>

        {{-- ランキング --}}
        <a href="{{ route('ranking.index') }}"
           class="flex flex-col items-center justify-center gap-0.5 flex-1 py-1 {{ request()->is('ranking*') ? 'text-blue-600' : 'text-gray-400' }}">
            <i data-lucide="trophy" class="w-5 h-5"></i>
            <span class="text-[10px] font-bold leading-tight">ランキング</span>
        </a>

        {{-- ニュース --}}
        <a href="{{ route('news.index') }}"
           class="flex flex-col items-center justify-center gap-0.5 flex-1 py-1 {{ request()->is('news*') ? 'text-blue-600' : 'text-gray-400' }}">
            <i data-lucide="newspaper" class="w-5 h-5"></i>
            <span class="text-[10px] font-bold leading-tight">ニュース</span>
        </a>

        {{-- マップ --}}
        <a href="{{ route('parking.index') }}"
           class="flex flex-col items-center justify-center gap-0.5 flex-1 py-1 {{ request()->is('parking*') ? 'text-blue-600' : 'text-gray-400' }}">
            <i data-lucide="map-pin" class="w-5 h-5"></i>
            <span class="text-[10px] font-bold leading-tight">マップ</span>
        </a>

        {{-- メニュー（サブメニュー） --}}
        <button onclick="toggleSubMenu('submenu-menu')"
                class="flex flex-col items-center justify-center gap-0.5 flex-1 py-1 {{ $isMenuActive ? 'text-blue-600' : 'text-gray-400' }}">
            <i data-lucide="menu" class="w-5 h-5"></i>
            <span class="text-[10px] font-bold leading-tight">メニュー</span>
        </button>
    </div>

    {{-- サブメニュー: メニュー --}}
    <div id="submenu-menu" class="hidden fixed inset-0 z-[60]">
        <div class="absolute inset-0 bg-black/40" onclick="closeAllSubMenus()"></div>
        <div class="absolute bottom-[60px] inset-x-0 bg-white rounded-t-2xl shadow-xl border-t border-gray-100 p-4 transform transition-transform duration-200 animate-slide-up max-h-[80vh] overflow-y-auto">
            <div class="w-10 h-1 bg-gray-200 rounded-full mx-auto mb-4"></div>

            {{-- よく使う --}}
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider px-1 mb-1">よく使う</p>
            <a href="{{ route('shindan.index') }}" class="flex items-center gap-3 p-3 rounded-xl hover:bg-gray-50 transition-colors">
                <div class="w-10 h-10 bg-purple-50 rounded-xl flex items-center justify-center">
                    <i data-lucide="sparkles" class="w-5 h-5 text-purple-600"></i>
                </div>
                <div>
                    <p class="text-sm font-black text-gray-900">バイク診断</p>
                    <p class="text-[10px] text-gray-400">あなたにぴったりのバイクを診断</p>
                </div>
            </a>
            <a href="{{ route('bikes.identify') }}" class="flex items-center gap-3 p-3 rounded-xl hover:bg-gray-50 transition-colors">
                <div class="w-10 h-10 bg-blue-50 rounded-xl flex items-center justify-center">
                    <i data-lucide="scan-eye" class="w-5 h-5 text-blue-600"></i>
                </div>
                <div>
                    <p class="text-sm font-black text-gray-900">車種判定AI</p>
                    <p class="text-[10px] text-gray-400">写真から車種を瞬時に判定</p>
                </div>
            </a>
            <a href="{{ route('parts.index') }}" class="flex items-center gap-3 p-3 rounded-xl hover:bg-gray-50 transition-colors">
                <div class="w-10 h-10 bg-cyan-50 rounded-xl flex items-center justify-center">
                    <i data-lucide="wrench" class="w-5 h-5 text-cyan-600"></i>
                </div>
                <div>
                    <p class="text-sm font-black text-gray-900">パーツ検索</p>
                    <p class="text-[10px] text-gray-400">バイクパーツを探す</p>
                </div>
            </a>
            <a href="{{ route('quiz') }}" class="flex items-center gap-3 p-3 rounded-xl hover:bg-gray-50 transition-colors">
                <div class="w-10 h-10 bg-orange-50 rounded-xl flex items-center justify-center">
                    <i data-lucide="gamepad-2" class="w-5 h-5 text-orange-600"></i>
                </div>
                <div>
                    <p class="text-sm font-black text-gray-900">バイク4択クイズ</p>
                    <p class="text-[10px] text-gray-400">中古価格や売れ筋をクイズで学ぼう</p>
                </div>
            </a>

            {{-- 情報 --}}
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider px-1 mt-3 mb-1">情報</p>
            <a href="{{ route('blog.index') }}" class="flex items-center gap-3 p-3 rounded-xl hover:bg-gray-50 transition-colors">
                <div class="w-10 h-10 bg-amber-50 rounded-xl flex items-center justify-center">
                    <i data-lucide="pen-line" class="w-5 h-5 text-amber-600"></i>
                </div>
                <div>
                    <p class="text-sm font-black text-gray-900">ブログ</p>
                    <p class="text-[10px] text-gray-400">バイクに関する記事を読む</p>
                </div>
            </a>
            <a href="{{ route('sell.index') }}" class="flex items-center gap-3 p-3 rounded-xl hover:bg-gray-50 transition-colors">
                <div class="w-10 h-10 bg-yellow-50 rounded-xl flex items-center justify-center">
                    <i data-lucide="badge-japanese-yen" class="w-5 h-5 text-yellow-600"></i>
                </div>
                <div>
                    <p class="text-sm font-black text-gray-900">買取相場</p>
                    <p class="text-[10px] text-gray-400">愛車の買取価格を調べる</p>
                </div>
            </a>
            <a href="{{ route('bikes.trends') }}" class="flex items-center gap-3 p-3 rounded-xl hover:bg-gray-50 transition-colors">
                <div class="w-10 h-10 bg-red-50 rounded-xl flex items-center justify-center">
                    <i data-lucide="trending-up" class="w-5 h-5 text-red-600"></i>
                </div>
                <div>
                    <p class="text-sm font-black text-gray-900">相場ランキング</p>
                    <p class="text-[10px] text-gray-400">人気車種のトレンドを確認</p>
                </div>
            </a>

            {{-- マイページ --}}
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider px-1 mt-3 mb-1">マイページ</p>
            <a href="{{ route('mybikes.index') }}" class="flex items-center gap-3 p-3 rounded-xl hover:bg-gray-50 transition-colors">
                <div class="w-10 h-10 bg-pink-50 rounded-xl flex items-center justify-center">
                    <i data-lucide="bike" class="w-5 h-5 text-pink-600"></i>
                </div>
                <div>
                    <p class="text-sm font-black text-gray-900">ガレージ</p>
                    <p class="text-[10px] text-gray-400">愛車を登録・管理</p>
                </div>
            </a>
            <a href="{{ route('wishlist') }}" class="flex items-center gap-3 p-3 rounded-xl hover:bg-gray-50 transition-colors">
                <div class="w-10 h-10 bg-rose-50 rounded-xl flex items-center justify-center">
                    <i data-lucide="heart" class="w-5 h-5 text-rose-600"></i>
                </div>
                <div>
                    <p class="text-sm font-black text-gray-900">お気に入り</p>
                    <p class="text-[10px] text-gray-400">気になる車両を確認</p>
                </div>
            </a>

            {{-- その他 --}}
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider px-1 mt-3 mb-1">その他</p>
            <a href="{{ route('shops.map') }}" class="flex items-center gap-3 p-3 rounded-xl hover:bg-gray-50 transition-colors">
                <div class="w-10 h-10 bg-orange-50 rounded-xl flex items-center justify-center">
                    <i data-lucide="store" class="w-5 h-5 text-orange-600"></i>
                </div>
                <div>
                    <p class="text-sm font-black text-gray-900">ショップマップ</p>
                    <p class="text-[10px] text-gray-400">近くのバイクショップを探す</p>
                </div>
            </a>
            <a href="{{ route('ar.index') }}" class="flex items-center gap-3 p-3 rounded-xl hover:bg-gray-50 transition-colors">
                <div class="w-10 h-10 bg-green-50 rounded-xl flex items-center justify-center">
                    <i data-lucide="camera" class="w-5 h-5 text-green-600"></i>
                </div>
                <div>
                    <p class="text-sm font-black text-gray-900">AR駐車場ファインダー</p>
                    <p class="text-[10px] text-gray-400">ARで近くの駐車場を探す</p>
                </div>
            </a>
        </div>
    </div>
</nav>

<script>
    function toggleSubMenu(id) {
        var el = document.getElementById(id);
        var isOpen = !el.classList.contains('hidden');
        closeAllSubMenus();
        if (!isOpen) el.classList.remove('hidden');
    }
    function closeAllSubMenus() {
        document.querySelectorAll('#bottom-nav [id^="submenu-"]').forEach(function(m) {
            m.classList.add('hidden');
        });
    }
</script>

<style>
    .animate-slide-up { animation: slideUp 0.2s ease-out; }
</style>
