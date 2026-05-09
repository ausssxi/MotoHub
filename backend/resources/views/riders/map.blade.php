<x-layout>
    <x-slot:title>ライダーズマップ | バイクショップ・駐車場・ガソリンスタンド | MotoHub</x-slot:title>
    <x-slot:metaDescription>バイクショップ・駐車場・ガソリンスタンド・コンビニ・道の駅をまとめて検索できる統合マップ。ツーリングの計画に便利。</x-slot:metaDescription>

    <x-slot:styles>
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
        <link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.css" />
        <style>
            #map { height: 60vh; z-index: 10; }
            #map.route-mode-active,
            #map.route-mode-active * { cursor: crosshair !important; }
            #map.blog-pin-mode,
            #map.blog-pin-mode * { cursor: crosshair !important; }
            #btn-blog-pin.active {
                background: #0891b2 !important;
                color: #fff !important;
                border-color: #0891b2 !important;
            }
            #map.spot-pin-mode,
            #map.spot-pin-mode * { cursor: crosshair !important; }
            #btn-spot-pin.active {
                background: #f59e0b !important;
                color: #fff !important;
                border-color: #f59e0b !important;
            }
            #map.has-spot-popup { z-index: 45 !important; }
            @media (max-width: 640px) { #map { height: 50vh; } }
            .scrollbar-hide::-webkit-scrollbar { display: none; }
            .scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
            .leaflet-routing-container { display: none !important; }

            /* Route toggle button */
            #btn-route-toggle.active {
                background: #ec4899 !important;
                color: #fff !important;
                border-color: #ec4899 !important;
            }

            /* Detail Panel */
            #detail-panel {
                position: fixed;
                z-index: 1100;
                background: #fff;
                box-shadow: 0 -4px 24px rgba(0,0,0,.12);
                transition: transform .3s cubic-bezier(.4,0,.2,1);
                overflow-y: auto;
                -webkit-overflow-scrolling: touch;
            }
            @media (max-width: 767px) {
                #detail-panel {
                    bottom: 0; left: 0; right: 0;
                    height: 50vh;
                    border-radius: 16px 16px 0 0;
                    transform: translateY(100%);
                }
                #detail-panel.open { transform: translateY(0); }
            }
            @media (min-width: 768px) {
                #detail-panel {
                    top: 0; right: 0; bottom: 0;
                    width: 400px;
                    transform: translateX(100%);
                }
                #detail-panel.open { transform: translateX(0); }
            }
            #detail-panel-overlay {
                position: fixed; inset: 0; z-index: 1099;
                background: rgba(0,0,0,.25);
                opacity: 0; pointer-events: none;
                transition: opacity .3s ease;
            }
            #detail-panel-overlay.open { opacity: 1; pointer-events: auto; }

            /* Layer toggle buttons */
            .layer-btn { transition: all .15s ease; }
        </style>
    </x-slot:styles>

    <x-slot:scripts>
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
        <script src="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.min.js"></script>
        <script src="{{ asset('js/common/map-search.js') }}?v={{ filemtime(public_path('js/common/map-search.js')) }}"></script>
        <script src="{{ asset('js/riders/map.js') }}?v={{ time() }}"></script>
        <script src="{{ asset('js/riders/route.js') }}?v={{ time() }}"></script>
        @auth
            <script src="{{ asset('js/riders/blog-pin.js') }}?v={{ time() }}"></script>
            <script src="{{ asset('js/riders/spot-pin.js') }}?v={{ time() }}"></script>
        @endauth
    </x-slot:scripts>

    <x-slot:navigation>
        <x-navigation :showSearch="true" />
    </x-slot:navigation>

    <div class="relative w-full">
        <div id="map" class="w-full bg-gray-100"></div>

        {{-- ローディング --}}
        <div id="map-loading" class="absolute top-4 left-1/2 -translate-x-1/2 bg-white/90 backdrop-blur px-4 py-2 rounded-full shadow-lg z-40 flex items-center gap-2 hidden">
            <i data-lucide="loader-2" class="w-4 h-4 animate-spin text-blue-600"></i>
            <span class="text-xs font-bold text-gray-600">検索中...</span>
        </div>

        {{-- 地名検索 --}}
        <div class="absolute top-3 left-1/2 -translate-x-1/2 z-40 w-[min(calc(100%-140px),400px)]">
            <div class="flex bg-white rounded-lg shadow-md border border-gray-200 overflow-hidden">
                <input id="map-search-input" type="search" inputmode="search" enterkeyhint="search"
                       placeholder="地名・住所で検索（例：渋谷、橋本駅）"
                       class="flex-1 min-w-0 px-3 py-2 text-xs text-gray-800 placeholder-gray-400 outline-none bg-transparent">
                <button id="map-search-btn" class="px-3 text-gray-500 hover:text-blue-600 transition-colors shrink-0">
                    <svg class="w-4 h-4 search-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path stroke-linecap="round" stroke-width="2" d="m21 21-4.35-4.35"/></svg>
                    <svg class="w-4 h-4 search-spinner hidden animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                </button>
            </div>
        </div>

        {{-- レイヤートグル --}}
        <div class="absolute top-14 left-3 right-3 z-40 flex flex-wrap gap-1.5"
             x-data="{
                 shop: true, parking: true, gas: false, cvs: false, michi: false, blog: false, saved_spots: {{ auth()->check() ? 'true' : 'false' }},
                 notify() {
                     let l = {shop: this.shop, parking: this.parking, gas_station: this.gas, convenience_store: this.cvs, michi_no_eki: this.michi, blog: this.blog, saved_spots: this.saved_spots};
                     window.ridersMapLayers = l;
                     window.dispatchEvent(new CustomEvent('layers-changed', {detail: l}));
                 }
             }"
             x-init="window.ridersMapLayers = {shop: true, parking: true, gas_station: false, convenience_store: false, michi_no_eki: false, blog: false, saved_spots: {{ auth()->check() ? 'true' : 'false' }}}">
            <button type="button" @click="shop = !shop; notify()"
                    class="layer-btn px-2.5 py-1.5 rounded-lg text-[11px] font-bold flex items-center gap-1"
                    :style="shop ? 'background:#2563eb;color:#fff;border:2px solid #2563eb' : 'background:#fff;color:#4b5563;border:2px solid #e5e7eb'">
                <span class="text-sm leading-none">&#x1F3CD;&#xFE0F;</span>
                ショップ
            </button>
            <button type="button" @click="parking = !parking; notify()"
                    class="layer-btn px-2.5 py-1.5 rounded-lg text-[11px] font-bold flex items-center gap-1"
                    :style="parking ? 'background:#16a34a;color:#fff;border:2px solid #16a34a' : 'background:#fff;color:#4b5563;border:2px solid #e5e7eb'">
                <span class="text-sm leading-none">&#x1F17F;&#xFE0F;</span>
                駐車場
            </button>
            <button type="button" @click="gas = !gas; notify()"
                    class="layer-btn px-2.5 py-1.5 rounded-lg text-[11px] font-bold flex items-center gap-1"
                    :style="gas ? 'background:#dc2626;color:#fff;border:2px solid #dc2626' : 'background:#fff;color:#4b5563;border:2px solid #e5e7eb'">
                <span class="text-sm leading-none">&#x26FD;</span>
                GS
            </button>
            <button type="button" @click="cvs = !cvs; notify()"
                    class="layer-btn px-2.5 py-1.5 rounded-lg text-[11px] font-bold flex items-center gap-1"
                    :style="cvs ? 'background:#ea580c;color:#fff;border:2px solid #ea580c' : 'background:#fff;color:#4b5563;border:2px solid #e5e7eb'">
                <span class="text-sm leading-none">&#x1F3EA;</span>
                コンビニ
            </button>
            <button type="button" @click="michi = !michi; notify()"
                    class="layer-btn px-2.5 py-1.5 rounded-lg text-[11px] font-bold flex items-center gap-1"
                    :style="michi ? 'background:#9333ea;color:#fff;border:2px solid #9333ea' : 'background:#fff;color:#4b5563;border:2px solid #e5e7eb'">
                <span class="text-sm leading-none">&#x1F6E3;&#xFE0F;</span>
                道の駅
            </button>
            <button type="button" @click="blog = !blog; notify()"
                    class="layer-btn px-2.5 py-1.5 rounded-lg text-[11px] font-bold flex items-center gap-1"
                    :style="blog ? 'background:#0891b2;color:#fff;border:2px solid #0891b2' : 'background:#fff;color:#4b5563;border:2px solid #e5e7eb'">
                <span class="text-sm leading-none">&#x270D;&#xFE0F;</span>
                記事
            </button>
            @auth
            <button type="button" @click="saved_spots = !saved_spots; notify()"
                    class="layer-btn px-2.5 py-1.5 rounded-lg text-[11px] font-bold flex items-center gap-1"
                    :style="saved_spots ? 'background:#f59e0b;color:#fff;border:2px solid #f59e0b' : 'background:#fff;color:#4b5563;border:2px solid #e5e7eb'">
                <span class="text-sm leading-none">&#x2B50;</span>
                お気に入り
            </button>
            @endauth
        </div>

        {{-- ルートコントロール --}}
        <div class="absolute bottom-20 left-3 z-40 flex gap-1.5">
            <button id="btn-route-toggle" class="bg-white px-3 py-2 rounded-lg shadow-md text-gray-600 hover:text-pink-600 transition-colors border border-gray-200 flex items-center gap-1.5 text-[11px] font-bold"
                    title="ルート作成モード">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l5.447 2.724A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                <span class="route-btn-label">ルート作成</span>
            </button>
            <button id="btn-route-clear" class="hidden bg-white px-3 py-2 rounded-lg shadow-md text-gray-600 hover:text-red-600 transition-colors border border-gray-200 text-[11px] font-bold"
                    title="ルートをクリア">
                クリア
            </button>
            <button id="btn-route-pois" class="hidden bg-white px-3 py-2 rounded-lg shadow-md text-gray-600 hover:text-purple-600 transition-colors border border-gray-200 text-[11px] font-bold"
                    title="沿線スポットを表示" onclick="window.ridersRouteSearchPois()">
                沿線スポット表示
            </button>
            @auth
                <button id="btn-blog-pin" class="bg-white px-3 py-2 rounded-lg shadow-md text-gray-600 hover:text-cyan-600 transition-colors border border-gray-200 flex items-center gap-1.5 text-[11px] font-bold"
                        title="地図上の場所を選んで記事を書く">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    <span class="blog-pin-label">ガイドを書く</span>
                </button>
                <button id="btn-spot-pin" class="bg-white px-3 py-2 rounded-lg shadow-md text-gray-600 hover:text-amber-600 transition-colors border border-gray-200 flex items-center gap-1.5 text-[11px] font-bold"
                        title="地図上の場所をお気に入り保存">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
                    <span class="spot-pin-label">ピン留め</span>
                </button>
            @endauth
        </div>

        {{-- 現在地ボタン --}}
        <button id="btn-current-location" class="absolute bottom-4 right-3 bg-white p-2.5 rounded-lg shadow-md z-40 text-gray-600 hover:text-blue-600 transition-colors border border-gray-200"
                title="現在地に移動">
            <i data-lucide="crosshair" class="w-5 h-5"></i>
        </button>
    </div>

    {{-- ルート情報バー --}}
    <div id="route-info-bar" class="hidden bg-pink-50 border-t border-b border-pink-200 px-4 py-2 flex items-center gap-4">
        <div class="flex items-center gap-1.5">
            <svg class="w-4 h-4 text-pink-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l5.447 2.724A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
            <span class="text-xs font-bold text-pink-700">ルート:</span>
        </div>
        <span class="text-xs font-black text-pink-800"><span id="route-distance">-</span></span>
        <span class="text-xs text-pink-600">|</span>
        <span class="text-xs font-black text-pink-800"><span id="route-time">-</span></span>
        <span id="route-poi-count" class="text-xs font-bold text-purple-600"></span>
    </div>

    {{-- 件数バー + 距離フィルタ --}}
    <div class="bg-white border-t border-b border-gray-200 px-4 py-2 flex items-center gap-3">
        <span id="result-count" class="text-sm font-black text-gray-800 shrink-0">地図内に0件</span>
        <div id="distance-filter" class="flex items-center gap-1 ml-auto shrink-0" style="display:none;">
            <select id="distance-select" class="text-xs border border-gray-200 rounded-lg px-2 py-1 text-gray-700 bg-white focus:outline-none focus:ring-1 focus:ring-blue-300">
                <option value="0">距離制限なし</option>
                <option value="5">5km圏内</option>
                <option value="10">10km圏内</option>
                <option value="20">20km圏内</option>
            </select>
        </div>
        <span class="text-xs text-gray-400 shrink-0 hidden sm:inline">← スクロール →</span>
    </div>

    {{-- カードスライダー --}}
    <div id="result-cards"
         class="flex gap-3 overflow-x-auto pb-4 px-4 py-3 bg-gray-50 snap-x snap-mandatory scrollbar-hide"
         style="min-height: 120px;">
        <div class="flex items-center justify-center w-full text-sm text-gray-400">地図を移動するとスポットが表示されます</div>
    </div>

    {{-- 駐車場ページ・ツーリングガイドへのリンク --}}
    <div class="bg-white px-4 py-2 border-b border-gray-100 flex justify-end items-center gap-4">
        <a href="{{ route('touring.index') }}" class="text-xs font-bold text-blue-600 hover:text-blue-700 transition-colors inline-flex items-center gap-0.5">
            ツーリングガイドを見る <i data-lucide="arrow-right" class="w-3 h-3"></i>
        </a>
        <a href="{{ route('parking.index') }}" class="text-xs font-bold text-blue-600 hover:text-blue-700 transition-colors inline-flex items-center gap-0.5">
            駐車場一覧を見る <i data-lucide="arrow-right" class="w-3 h-3"></i>
        </a>
    </div>

    {{-- 詳細パネル --}}
    <div id="detail-panel-overlay"></div>
    <div id="detail-panel">
        <div style="position:sticky;top:0;z-index:1;background:#fff;border-bottom:1px solid #f3f4f6;padding:12px 16px;display:flex;align-items:center;justify-content:space-between;">
            <span id="detail-panel-title" style="font-size:12px;font-weight:700;color:#9ca3af;">詳細</span>
            <button id="detail-panel-close" style="color:#9ca3af;background:none;border:none;cursor:pointer;padding:4px;">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div id="detail-panel-body" style="padding:20px;"></div>
    </div>
</x-layout>
