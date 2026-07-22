<x-layout>
    @php $parkingCount = \Illuminate\Support\Facades\Cache::remember('parking_total_count', 3600, fn() => \App\Models\BikeParking::active()->count()); @endphp
    <x-slot:title>バイク駐車場マップ【全国{{ number_format($parkingCount) }}件】料金・空き状況 | MotoHub</x-slot:title>
    <x-slot:metaDescription>全国{{ number_format($parkingCount) }}件のバイク駐車場・駐輪場をマップで検索。料金シミュレーター・ストリートビュー・ユーザーレビューで最適な駐車場が見つかる。</x-slot:metaDescription>

    <x-slot:styles>
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
        {{-- マーカークラスタリング（ズームアウトで数字クラスタ／ズームインで個別ピン・ライダーズマップと同方式） --}}
        <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css" />
        <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css" />
        <style>
            #map { height: 60vh; z-index: 10; }
            @media (max-width: 640px) { #map { height: 50vh; } }
            .scrollbar-hide::-webkit-scrollbar { display: none; }
            .scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
            .parking-card-active { border-color: #16a34a !important; box-shadow: 0 0 0 2px rgba(22,163,74,.3); }

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
            /* Mobile: bottom sheet */
            @media (max-width: 767px) {
                #detail-panel {
                    bottom: 0; left: 0; right: 0;
                    height: 50vh;
                    border-radius: 16px 16px 0 0;
                    transform: translateY(100%);
                }
                #detail-panel.open { transform: translateY(0); }
            }
            /* PC: right side panel */
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
        </style>
    </x-slot:styles>

    <x-slot:scripts>
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
        {{-- markercluster は leaflet.js の後・parking/map.js の前に読む（map.js が L.markerClusterGroup を使う） --}}
        <script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
        <script src="{{ asset('js/common/map-search.js') }}?v={{ asset_buster(public_path('js/common/map-search.js')) }}"></script>
        <script src="{{ asset('js/parking/map.js') }}?v={{ asset_buster(public_path('js/parking/map.js')) }}"></script>
    </x-slot:scripts>

    <x-slot:navigation>
        <x-navigation :showSearch="true" />
    </x-slot:navigation>

    <div class="relative w-full">
        <h1 class="sr-only">バイク駐車場マップ｜全国{{ number_format($parkingCount) }}件の駐車場・駐輪場を検索</h1>
        <div id="map" class="w-full bg-gray-100"></div>

        {{-- 検索中ローディング --}}
        <div id="map-loading" class="absolute top-4 left-1/2 -translate-x-1/2 bg-white/90 backdrop-blur px-4 py-2 rounded-full shadow-lg z-40 flex items-center gap-2 hidden">
            <i data-lucide="loader-2" class="w-4 h-4 animate-spin text-green-600"></i>
            <span class="text-xs font-bold text-gray-600">駐車場を検索中...</span>
        </div>

        {{-- 上部中央: 地名検索 --}}
        <div class="absolute top-3 left-1/2 -translate-x-1/2 z-40 w-[min(calc(100%-140px),400px)]">
            <div class="flex bg-white rounded-lg shadow-md border border-gray-200 overflow-hidden">
                <input id="map-search-input" type="search" inputmode="search" enterkeyhint="search"
                       placeholder="地名・住所で検索（例：渋谷、橋本駅）"
                       class="flex-1 min-w-0 px-3 py-2 text-xs text-gray-800 placeholder-gray-400 outline-none bg-transparent">
                <button id="map-search-btn" class="px-3 text-gray-500 hover:text-green-600 transition-colors shrink-0">
                    <svg class="w-4 h-4 search-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path stroke-linecap="round" stroke-width="2" d="m21 21-4.35-4.35"/></svg>
                    <svg class="w-4 h-4 search-spinner hidden animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                </button>
            </div>
        </div>

        {{-- 左上: フィルタ --}}
        <div x-data="{ open: false }" class="absolute top-14 left-3 z-40">
            <button @click="open = !open" class="bg-white px-3 py-2 rounded-lg shadow-md border border-gray-200 flex items-center gap-1.5 text-xs font-bold text-gray-700 hover:bg-gray-50 transition">
                <i data-lucide="sliders-horizontal" class="w-3.5 h-3.5"></i>
                フィルタ
            </button>
            <div x-show="open" x-transition @click.outside="open = false" class="mt-2 bg-white rounded-xl shadow-xl border border-gray-100 p-4 w-56" style="display: none;">
                <p class="text-xs font-black text-gray-900 mb-3">駐車場タイプ</p>
                <div class="space-y-2" id="filter-type">
                    <label class="flex items-center gap-2 text-xs text-gray-700 cursor-pointer">
                        <input type="radio" name="parking_type" value="" checked class="text-green-600 focus:ring-green-500"> すべて
                    </label>
                    <label class="flex items-center gap-2 text-xs text-gray-700 cursor-pointer">
                        <input type="radio" name="parking_type" value="bike_only" class="text-green-600 focus:ring-green-500"> バイク専用
                    </label>
                    <label class="flex items-center gap-2 text-xs text-gray-700 cursor-pointer">
                        <input type="radio" name="parking_type" value="car_shared" class="text-green-600 focus:ring-green-500"> 四輪と共用
                    </label>
                    <label class="flex items-center gap-2 text-xs text-gray-700 cursor-pointer">
                        <input type="radio" name="parking_type" value="bicycle_shared" class="text-green-600 focus:ring-green-500"> 自転車と共用
                    </label>
                </div>
            </div>
        </div>

        {{-- 左上2段目: ARボタン (モバイルのみ) --}}
        <a href="{{ route('ar.index') }}" class="absolute top-[104px] left-3 bg-black/70 text-white px-3 py-2 rounded-lg shadow-md flex items-center gap-1.5 text-xs font-bold hover:bg-black/90 transition z-40 backdrop-filter backdrop-blur-sm sm:hidden">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
                <circle cx="12" cy="13" r="4"/>
            </svg>
            ARで探す
        </a>

        {{-- 右上: 駐車場登録ボタン --}}
        <a href="{{ route('parking.create') }}" class="absolute top-14 right-3 bg-gray-900 text-white px-3 py-2 rounded-lg shadow-md flex items-center gap-1.5 text-xs font-bold hover:bg-gray-700 transition z-40">
            <i data-lucide="plus" class="w-3.5 h-3.5"></i>
            駐車場を登録
        </a>

        {{-- 右下: 現在地ボタン（Leafletズームは左下） --}}
        <button id="btn-current-location" class="absolute bottom-4 right-3 bg-white p-2.5 rounded-lg shadow-md z-40 text-gray-600 hover:text-green-600 transition-colors border border-gray-200">
            <i data-lucide="crosshair" class="w-5 h-5"></i>
        </button>
    </div>

    {{-- 件数バー + レビュー促進 --}}
    <div class="bg-white border-t border-b border-gray-200 px-4 py-2 flex items-center justify-between gap-3">
        <span id="parking-count" class="text-sm font-black text-gray-800 shrink-0">地図内に0件</span>
        <a href="/riders-map" class="ml-auto text-green-600 hover:underline text-sm shrink-0">ライダーズマップで探す →</a>
        <div id="review-banner" class="flex items-center gap-2 text-[10px] text-gray-400">
            <span>🅿️ 使った駐車場に★評価をつけてみんなの参考に</span>
            <button onclick="document.getElementById('review-banner').remove()" class="text-gray-300 hover:text-gray-500">✕</button>
        </div>
        <span class="text-xs text-gray-400 shrink-0 hidden sm:inline">← スクロール →</span>
    </div>

    {{-- カードスライダー --}}
    <div id="parking-cards"
         class="flex gap-3 overflow-x-auto pb-4 px-4 py-3 bg-gray-50 snap-x snap-mandatory scrollbar-hide"
         style="min-height: 120px;">
        <div class="flex items-center justify-center w-full text-sm text-gray-400">地図を移動すると駐車場カードが表示されます</div>
    </div>

    {{-- レビューが多い駐車場ランキング --}}
    @if($topReviewed->isNotEmpty())
    <section class="bg-gray-50 py-8">
        <div class="max-w-5xl mx-auto px-4">
            <h2 class="text-lg font-black text-gray-900 text-center mb-6">レビューが多い駐車場</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($topReviewed as $rank => $p)
                <a href="{{ route('parking.show', $p) }}" class="bg-white rounded-xl border border-gray-100 p-4 hover:shadow-md transition-shadow block">
                    <div class="flex items-start gap-3">
                        <span class="text-2xl font-black text-gray-200">{{ $rank + 1 }}</span>
                        <div class="flex-1 min-w-0">
                            <h3 class="text-sm font-bold text-gray-800 truncate">{{ $p->name }}</h3>
                            <p class="text-[10px] text-gray-400 truncate">{{ $p->prefecture }} {{ $p->city }}</p>
                            <div class="flex items-center gap-2 mt-1">
                                <span class="text-yellow-500 text-xs">
                                    @for($i = 1; $i <= 5; $i++){{ $i <= round($p->avg_rating) ? '★' : '☆' }}@endfor
                                </span>
                                <span class="text-[10px] text-gray-400">{{ number_format($p->avg_rating, 1) }} ({{ $p->reviews_count }}件)</span>
                            </div>
                        </div>
                    </div>
                </a>
                @endforeach
            </div>
        </div>
    </section>
    @endif

    {{-- 詳細パネル --}}
    <div id="detail-panel-overlay"></div>
    <div id="detail-panel">
        <div style="position:sticky;top:0;z-index:1;background:#fff;border-bottom:1px solid #f3f4f6;padding:12px 16px;display:flex;align-items:center;justify-content:space-between;">
            <span style="font-size:12px;font-weight:700;color:#9ca3af;">駐車場詳細</span>
            <button id="detail-panel-close" style="color:#9ca3af;background:none;border:none;cursor:pointer;padding:4px;">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div id="detail-panel-body" style="padding:20px;"></div>
    </div>

    {{-- 都道府県から探す（SEO用内部リンク） --}}
    <section class="bg-white py-12">
        <div class="max-w-5xl mx-auto px-4">
            <h2 class="text-lg font-black text-gray-900 text-center mb-8">都道府県からバイク駐車場を探す</h2>
            @foreach(config('parking.regions', []) as $regionName => $prefs)
            <div class="mb-6">
                <h3 class="text-xs font-black text-gray-400 uppercase tracking-widest mb-3">{{ $regionName }}</h3>
                <div class="flex flex-wrap gap-2">
                    @foreach($prefs as $pref => $coords)
                    <a href="{{ route('parking.area.prefecture', $pref) }}"
                       class="px-3 py-1.5 rounded-lg bg-gray-50 border border-gray-100 text-xs font-bold text-gray-600 hover:bg-green-50 hover:border-green-200 hover:text-green-600 transition-colors">
                        {{ $pref }}
                    </a>
                    @endforeach
                </div>
            </div>
            @endforeach
            <div class="text-center mt-4">
                <a href="{{ route('parking.area.index') }}" class="inline-flex items-center gap-1 text-xs font-bold text-green-600 hover:text-green-700 transition">
                    すべてのエリアを見る <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                </a>
            </div>
        </div>
    </section>
</x-layout>
``