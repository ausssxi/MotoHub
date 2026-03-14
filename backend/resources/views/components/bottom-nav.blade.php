{{-- モバイル下部タブナビゲーション（md以上で非表示） --}}
<nav id="bottom-nav" class="fixed bottom-0 left-0 right-0 w-full bg-white border-t border-gray-100 z-50 md:hidden" style="height:60px;" aria-label="モバイルナビゲーション">
    <div class="flex w-full h-full items-center justify-around">

        {{-- 検索 --}}
        <a href="{{ route('bikes.search') }}"
           class="flex flex-col items-center justify-center gap-0.5 flex-1 py-1 {{ request()->is('bikes/search*') ? 'text-blue-600' : 'text-gray-400' }}">
            <i data-lucide="search" class="w-5 h-5"></i>
            <span class="text-[10px] font-bold leading-tight">検索</span>
        </a>

        {{-- 診断（サブメニュー） --}}
        <button onclick="toggleSubMenu('submenu-shindan')"
                class="flex flex-col items-center justify-center gap-0.5 flex-1 py-1 {{ request()->is('shindan*') || request()->is('bikes/identify*') ? 'text-blue-600' : 'text-gray-400' }}">
            <i data-lucide="sparkles" class="w-5 h-5"></i>
            <span class="text-[10px] font-bold leading-tight">診断</span>
        </button>

        {{-- 地図（サブメニュー） --}}
        <button onclick="toggleSubMenu('submenu-map')"
                class="flex flex-col items-center justify-center gap-0.5 flex-1 py-1 {{ request()->is('shops/map*') || request()->is('parking*') ? 'text-blue-600' : 'text-gray-400' }}">
            <i data-lucide="map-pin" class="w-5 h-5"></i>
            <span class="text-[10px] font-bold leading-tight">地図</span>
        </button>

        {{-- ガレージ --}}
        <a href="{{ route('mybikes.index') }}"
           class="flex flex-col items-center justify-center gap-0.5 flex-1 py-1 {{ request()->is('garage*') ? 'text-blue-600' : 'text-gray-400' }}">
            <i data-lucide="bike" class="w-5 h-5"></i>
            <span class="text-[10px] font-bold leading-tight">ガレージ</span>
        </a>

        {{-- 相場（サブメニュー） --}}
        <button onclick="toggleSubMenu('submenu-market')"
                class="flex flex-col items-center justify-center gap-0.5 flex-1 py-1 {{ request()->is('bikes/trends*') || request()->is('sell*') ? 'text-blue-600' : 'text-gray-400' }}">
            <i data-lucide="trending-up" class="w-5 h-5"></i>
            <span class="text-[10px] font-bold leading-tight">相場</span>
        </button>
    </div>

    {{-- サブメニュー: 診断 --}}
    <div id="submenu-shindan" class="hidden fixed inset-0 z-[60]">
        <div class="absolute inset-0 bg-black/40" onclick="closeAllSubMenus()"></div>
        <div class="absolute bottom-[60px] inset-x-0 bg-white rounded-t-2xl shadow-xl border-t border-gray-100 p-4 transform transition-transform duration-200 animate-slide-up">
            <div class="w-10 h-1 bg-gray-200 rounded-full mx-auto mb-4"></div>
            <a href="{{ route('shindan.index') }}" class="flex items-center gap-3 p-3 rounded-xl hover:bg-gray-50 transition-colors">
                <div class="w-10 h-10 bg-purple-50 rounded-xl flex items-center justify-center">
                    <i data-lucide="heart" class="w-5 h-5 text-purple-600"></i>
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
        </div>
    </div>

    {{-- サブメニュー: 地図 --}}
    <div id="submenu-map" class="hidden fixed inset-0 z-[60]">
        <div class="absolute inset-0 bg-black/40" onclick="closeAllSubMenus()"></div>
        <div class="absolute bottom-[60px] inset-x-0 bg-white rounded-t-2xl shadow-xl border-t border-gray-100 p-4 transform transition-transform duration-200 animate-slide-up">
            <div class="w-10 h-1 bg-gray-200 rounded-full mx-auto mb-4"></div>
            <a href="{{ route('shops.map') }}" class="flex items-center gap-3 p-3 rounded-xl hover:bg-gray-50 transition-colors">
                <div class="w-10 h-10 bg-orange-50 rounded-xl flex items-center justify-center">
                    <i data-lucide="store" class="w-5 h-5 text-orange-600"></i>
                </div>
                <div>
                    <p class="text-sm font-black text-gray-900">ショップマップ</p>
                    <p class="text-[10px] text-gray-400">近くのバイクショップを探す</p>
                </div>
            </a>
            <a href="{{ route('parking.index') }}" class="flex items-center gap-3 p-3 rounded-xl hover:bg-gray-50 transition-colors">
                <div class="w-10 h-10 bg-green-50 rounded-xl flex items-center justify-center">
                    <i data-lucide="square-parking" class="w-5 h-5 text-green-600"></i>
                </div>
                <div>
                    <p class="text-sm font-black text-gray-900">駐車場マップ</p>
                    <p class="text-[10px] text-gray-400">バイク駐車場を探す</p>
                </div>
            </a>
        </div>
    </div>

    {{-- サブメニュー: 相場 --}}
    <div id="submenu-market" class="hidden fixed inset-0 z-[60]">
        <div class="absolute inset-0 bg-black/40" onclick="closeAllSubMenus()"></div>
        <div class="absolute bottom-[60px] inset-x-0 bg-white rounded-t-2xl shadow-xl border-t border-gray-100 p-4 transform transition-transform duration-200 animate-slide-up">
            <div class="w-10 h-1 bg-gray-200 rounded-full mx-auto mb-4"></div>
            <a href="{{ route('bikes.trends') }}" class="flex items-center gap-3 p-3 rounded-xl hover:bg-gray-50 transition-colors">
                <div class="w-10 h-10 bg-red-50 rounded-xl flex items-center justify-center">
                    <i data-lucide="trophy" class="w-5 h-5 text-red-600"></i>
                </div>
                <div>
                    <p class="text-sm font-black text-gray-900">相場ランキング</p>
                    <p class="text-[10px] text-gray-400">人気車種のトレンドを確認</p>
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
