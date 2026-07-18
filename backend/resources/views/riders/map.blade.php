<x-layout>
    <x-slot:title>ライダーズマップ | バイクショップ・駐車場・ガソリンスタンド | MotoHub</x-slot:title>
    <x-slot:metaDescription>バイクショップ・駐車場・ガソリンスタンド・コンビニ・道の駅をまとめて検索できる統合マップ。ツーリングの計画に便利。</x-slot:metaDescription>

    <x-slot:styles>
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
        <link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.css" />
        {{-- マーカークラスタリング（近接ピンを数字バッジに集約・ズームで展開） --}}
        <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css" />
        <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css" />
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

            /* ===== モバイル全画面（地図主役）＋ 結果ボトムシート（ロケスマ風・A） ===== */
            /* デスクトップ(≥768px)は従来レイアウト維持（このブロックは max-width:767px のみ） */
            @media (max-width: 767px) {
                /* B: /riders-map のモバイルのみ bottom-nav を隠して地図領域を最大化。
                   このCSSは /riders-map の <style> にだけ載る＝他ページのボトムナビは無改変。
                   ヘッダー（上部ナビ）は残す＝遷移動線を確保。 */
                #bottom-nav { display: none !important; }
                body { padding-bottom: 0 !important; }

                /* 地図をビューポート占有（上部ナビ3.5rem のみ除く。bottom-nav が無いので viewport 下端まで） */
                #map { height: calc(100dvh - 3.5rem); }

                /* 結果（件数バー＋カード列）を下からのボトムシート化。折りたたみ時はピークバーのみ。
                   bottom-nav 撤去に伴い bottom:0（viewport 下端）へ。 */
                #results-sheet {
                    position: fixed; left: 0; right: 0; bottom: 0;
                    z-index: 44;
                    background: #fff;
                    border-radius: 16px 16px 0 0;
                    box-shadow: 0 -4px 20px rgba(0,0,0,.14);
                    transition: max-height .3s cubic-bezier(.4,0,.2,1);
                    max-height: 68px;            /* peek: ハンドル＋件数バー（"地図内にN件"）が見える高さ */
                    overflow: hidden;
                }
                #results-sheet.sheet-open { max-height: 56dvh; overflow: visible; }
                #results-sheet .sheet-handle { display: flex; } /* モバイルのみハンドル表示 */

                /* 浮遊コントロールは「シートpeek(68px) の上」へ（bottom-nav 撤去ぶん 60px 下げて再計算）。 */
                #map-actions {
                    position: absolute; left: 12px; right: 60px;
                    bottom: calc(68px + 8px);  /* peek + 余白 */
                    z-index: 43;
                    border-radius: 12px;
                }
                #btn-current-location { bottom: calc(68px + 8px) !important; }
                #map-legend { bottom: calc(68px + 8px + 48px) !important; }
            }
            /* デスクトップ: ハンドルは出さない（シートはただの通常フロー） */
            .sheet-handle { display: none; }

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

            /* 横スクロールのツールバー内はボタンを縮めない（1行に並べてスクロール） */
            #map-actions > a, #map-actions > button, #layer-chips > button { flex-shrink: 0; }
        </style>
    </x-slot:styles>

    <x-slot:scripts>
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
        {{-- markercluster は leaflet.js の後・map.js の前に読む（map.js が L.markerClusterGroup を使う） --}}
        <script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
        <script src="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.min.js"></script>
        <script src="{{ asset('js/common/map-search.js') }}?v={{ asset_buster(public_path('js/common/map-search.js')) }}"></script>
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

    {{-- (a) 初回オンボーディング【モバイル】。地図の外＝上の独立した帯（オーバーレイにしない＝
         検索窓・レイヤートグル・地図操作と原理的に重ならない）。×で dismiss すると帯ごと消え
         地図が上に詰まる。デスクトップ(sm以上)は地図内オーバーレイ（下部中央）で表示。 --}}
    <div x-data="{ show: false }"
         x-init="show = ! localStorage.getItem('riders_map_onboarding_dismissed_at')"
         x-show="show" x-cloak
         class="sm:hidden relative bg-white border-b border-gray-100 shadow-sm px-4 py-4">
        @include('riders._onboarding')
    </div>

    {{-- 外側=アクションバーの配置基準。内側(#map-stage)=地図＋地図追従オーバーレイ（chips/現在地）。
         モバイルはアクションバーを #map-stage の外＝地図直下に流し、現在地は #map-stage 内で地図に追従させる。 --}}
    <div class="relative w-full">
      <div class="relative w-full" id="map-stage">
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

        {{-- (a) 初回オンボーディング【デスクトップ】。地図下部中央のオーバーレイ（従来・破綻なし・sm以上のみ）。
             z-30=現在地ボタン(z-40)・ボトムナビ(z-50)より下＝操作を妨げない。モバイルは地図の外（上）の帯に分離。 --}}
        <div x-data="{ show: false }"
             x-init="show = ! localStorage.getItem('riders_map_onboarding_dismissed_at')"
             x-show="show" x-cloak
             class="hidden sm:block absolute bottom-4 left-1/2 -translate-x-1/2 z-30 w-[min(440px,calc(100%-6rem))] bg-white rounded-2xl shadow-xl border border-gray-100 p-4">
            @include('riders._onboarding')
        </div>

        {{-- レイヤートグル（1行横スクロール＝地図の占有面積を最小化） --}}
        <div id="layer-chips" class="absolute top-14 left-3 right-3 z-40 flex flex-nowrap overflow-x-auto scrollbar-hide gap-1.5 pb-1"
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

        {{-- 現在地ボタン（#map-stage 内＝地図に追従。アクションバーで下に伸びても位置がずれない） --}}
        <button id="btn-current-location" class="absolute bottom-4 right-3 bg-white p-2.5 rounded-lg shadow-md z-40 text-gray-600 hover:text-blue-600 transition-colors border border-gray-200"
                title="現在地に移動">
            <i data-lucide="crosshair" class="w-5 h-5"></i>
        </button>

        {{-- 凡例（SHOPピンの3区分・折りたたみ・過度にしない）。現在地ボタンの上。 --}}
        <div id="map-legend" class="absolute bottom-16 right-3 z-40" x-data="{ open: false }">
            <button type="button" @click="open = !open"
                    class="bg-white px-2.5 py-1.5 rounded-lg shadow-md border border-gray-200 text-[11px] font-bold text-gray-600 hover:text-blue-600 transition-colors flex items-center gap-1">
                <i data-lucide="info" class="w-3.5 h-3.5"></i> 凡例
            </button>
            <div x-show="open" x-cloak x-transition
                 class="absolute bottom-full right-0 mb-1.5 w-44 bg-white rounded-xl shadow-lg border border-gray-100 p-3 text-[11px] font-bold text-gray-600 space-y-1.5">
                <p class="text-[10px] text-gray-400 mb-1">SHOPピンの種類</p>
                <div class="flex items-center gap-2"><span class="inline-flex w-5 h-5 rounded-full bg-[#dc2626] text-white items-center justify-center text-[8px] font-black">RB</span> チェーン店（色＋略称）</div>
                <div class="flex items-center gap-2"><span class="inline-flex w-5 h-5 rounded-md bg-[#d97706] text-white items-center justify-center text-[8px] font-black">正規</span> メーカー正規店</div>
                <div class="flex items-center gap-2"><span class="inline-flex w-5 h-5 rounded-full bg-white border-[3px] border-[#2563eb] items-center justify-center text-[9px]">&#x1F3CD;&#xFE0F;</span> その他のSHOP</div>
            </div>
        </div>
      </div>{{-- /#map-stage --}}

        {{-- アクション: モバイル=地図直下の横スクロールツールバー / PC=地図オーバーレイ（従来位置・無改変） --}}
        <div id="map-actions" class="flex gap-1.5 overflow-x-auto scrollbar-hide bg-white/95 border-b border-gray-200 px-3 py-2 md:overflow-visible md:bg-transparent md:border-0 md:px-0 md:py-0 md:absolute md:bottom-20 md:left-3 md:z-40">
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
            <a id="btn-ai-route" href="#" onclick="event.preventDefault();var c=window.ridersMap?window.ridersMap.getCenter():{lat:35.68,lng:139.77};location.href='/touring/planner?lat='+c.lat.toFixed(5)+'&lng='+c.lng.toFixed(5);"
               class="bg-white px-3 py-2 rounded-lg shadow-md text-gray-600 hover:text-violet-600 transition-colors border border-gray-200 flex items-center gap-1.5 text-[11px] font-bold"
               title="AIにツーリングルートを提案してもらう">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                <span>AIルート提案</span>
            </a>
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
        </div>{{-- /#map-actions --}}
    </div>{{-- /outer --}}

    {{-- 結果ボトムシート（モバイル=下からのシート・ピーク/展開 / デスクトップ=通常フロー） --}}
    <div id="results-sheet" x-data="{ open: false }" :class="{ 'sheet-open': open }">
    {{-- ハンドル（モバイルのみ・タップで展開/折りたたみ） --}}
    <button type="button" class="sheet-handle w-full flex-col items-center pt-2 pb-1 bg-white" @click="open = !open" :aria-expanded="open" aria-label="結果パネルを開閉">
        <span class="block w-10 h-1 rounded-full bg-gray-300"></span>
    </button>

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

    {{-- 件数バー + 距離フィルタ（モバイルはタップでシート開閉。select はバブリング止め） --}}
    <div class="bg-white border-t border-b border-gray-200 px-4 py-2 flex items-center gap-3 md:cursor-default cursor-pointer" @click="open = !open">
        <span id="result-count" class="text-sm font-black text-gray-800 shrink-0">地図内に0件</span>
        <div id="distance-filter" class="flex items-center gap-1 ml-auto shrink-0" style="display:none;" @click.stop>
            <select id="distance-select" class="text-xs border border-gray-200 rounded-lg px-2 py-1 text-gray-700 bg-white focus:outline-none focus:ring-1 focus:ring-blue-300">
                <option value="0">距離制限なし</option>
                <option value="5">5km圏内</option>
                <option value="10">10km圏内</option>
                <option value="20">20km圏内</option>
            </select>
        </div>
        {{-- 開閉インジケータ（モバイルのみ） --}}
        <i data-lucide="chevron-up" class="md:hidden w-4 h-4 text-gray-400 ml-auto transition-transform" :class="open ? '' : 'rotate-180'"></i>
        <span class="text-xs text-gray-400 shrink-0 hidden sm:inline">← スクロール →</span>
    </div>

    {{-- カードスライダー --}}
    <div id="result-cards"
         class="flex gap-3 overflow-x-auto pb-4 px-4 py-3 bg-gray-50 snap-x snap-mandatory scrollbar-hide"
         style="min-height: 120px;">
        <div class="flex items-center justify-center w-full text-sm text-gray-400">地図を移動するとスポットが表示されます</div>
    </div>
    </div>{{-- /#results-sheet --}}

    {{-- 駐車場ページ・ツーリングガイドへのリンク --}}
    <div class="bg-white px-4 py-2 border-b border-gray-100 flex justify-end items-center gap-4">
        <a href="{{ route('touring.index') }}" class="text-xs font-bold text-blue-600 hover:text-blue-700 transition-colors inline-flex items-center gap-0.5">
            ツーリングガイドを見る <i data-lucide="arrow-right" class="w-3 h-3"></i>
        </a>
        <a href="{{ route('parking.index') }}" class="text-xs font-bold text-blue-600 hover:text-blue-700 transition-colors inline-flex items-center gap-0.5">
            駐車場一覧を見る <i data-lucide="arrow-right" class="w-3 h-3"></i>
        </a>
    </div>

    {{-- (b) 常設の使い方（SEO兼用）。折りたたみは max-height 制御で、中身は常時 DOM・display:none にしない。 --}}
    <section class="bg-white border-t border-gray-100 px-4 py-6" x-data="{ open: false }">
        <div class="max-w-3xl mx-auto">
            <button type="button" @click="open = ! open"
                    class="w-full flex items-center justify-between text-left gap-2"
                    :aria-expanded="open.toString()">
                <h2 class="text-base sm:text-lg font-black text-gray-800 flex items-center gap-2">
                    <i data-lucide="help-circle" class="w-5 h-5 text-blue-600"></i>
                    ライダーズマップの使い方
                </h2>
                <i data-lucide="chevron-down" class="w-5 h-5 text-gray-400 transition-transform shrink-0" :class="open && 'rotate-180'"></i>
            </button>

            {{-- 折りたたみ時も max-height:0 で視覚的に畳むだけ（display:none にしない＝SEOで本文を拾わせる） --}}
            <div class="overflow-hidden transition-[max-height] duration-300" style="max-height:0" :style="open ? 'max-height: 4000px' : 'max-height: 0'">
                <div class="pt-4 text-sm text-gray-700 leading-relaxed space-y-5">
                    <p>MotoHub のライダーズマップは、ツーリングの計画から当日のナビまでを1枚の地図で完結できる機能です。どこを走るか決める、停める場所を確認する、給油や休憩のポイントを押さえる ── バイクならではの「困りごと」を先回りして解決します。</p>

                    <div>
                        <h3 class="text-sm font-black text-gray-800 mb-2">表示する情報を切り替える</h3>
                        <p class="mb-2">地図の左上のボタンで、表示する情報を切り替えられます。必要なものだけ重ねれば、地図をすっきり保てます。</p>
                        <ul class="space-y-1.5 list-none">
                            <li><strong>駐車場</strong> ── バイクを停められる駐車場。都市部では事前確認が特に役立ちます</li>
                            <li><strong>GS</strong> ── ガソリンスタンド。山間部や半島の先端など、給油できる場所が限られるエリアで重宝します</li>
                            <li><strong>道の駅</strong> ── 休憩・食事・地元名物のスポット。ツーリングの立ち寄り先の定番です</li>
                            <li><strong>ショップ</strong> ── バイクの販売店・整備店</li>
                            <li><strong>コンビニ</strong> ── トイレ・軽食・現金の補給に</li>
                            <li><strong>記事</strong> ── MotoHub のツーリングガイド。走って気持ちいいルートを、季節や難易度つきで紹介します</li>
                            <li><strong>お気に入り</strong> ── お気に入り登録した車両を地図に表示します（ログイン時）</li>
                        </ul>
                    </div>

                    <div>
                        <h3 class="text-sm font-black text-gray-800 mb-2">行き先を探す</h3>
                        <p>上部の検索窓に地名や駅名を入れると、その周辺に地図が移動します（例：渋谷、橋本駅）。行きたいエリアが決まっているなら、まずここから。</p>
                    </div>

                    <div>
                        <h3 class="text-sm font-black text-gray-800 mb-2">ルートを引く</h3>
                        <ul class="space-y-1.5 list-none">
                            <li><strong>ルート作成</strong> ── 立ち寄りたい地点を順につないで、自分だけのルートを作れます</li>
                            <li><strong>AIルート提案</strong> ── 「日帰りで峠を走りたい」「海沿いをのんびり」といった希望を伝えると、AI がルートを提案します。できたルートは Google マップのナビにも渡せるので、当日はそのまま走り出せます</li>
                        </ul>
                    </div>

                    <div>
                        <h3 class="text-sm font-black text-gray-800 mb-2">ツーリング記事から計画する</h3>
                        <p>どこを走るか迷ったら、「記事」レイヤーをオンにしてみてください。地図上のピンや下部のカードから、エリアごとのツーリングガイドを開けます。各ガイドには見どころ・所要時間・おすすめの季節・注意点がまとまっており、記事内の地図からはそのままライダーズマップに戻って続きを計画できます。</p>
                    </div>

                    <p class="text-xs text-gray-400 border-t border-gray-100 pt-4">
                        ※ このほか、走ったルートや行きたい場所を残す「ピン留め」、あなたのおすすめルートを投稿する「ガイドを書く」機能もあります。
                    </p>
                </div>
            </div>
        </div>
    </section>

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
