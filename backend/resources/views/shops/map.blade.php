<x-layout>
    <x-slot:title>バイクショップ マップ検索 | MotoHub</x-slot:title>
    <x-slot:metaDescription>現在地や指定したエリアから、近くのバイクショップを探せます。在庫数や店舗情報も地図上で一目で確認できます。</x-slot:metaDescription>

    @if(request()->hasAny(['lat', 'lng']))
        <x-slot:robotsMeta>noindex, follow</x-slot:robotsMeta>
    @endif

    <x-slot:styles>
        {{-- Leaflet CSS --}}
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
        <style>
            #map { height: calc(100vh - 64px); z-index: 10; }
            .custom-popup .leaflet-popup-content-wrapper { border-radius: 12px; padding: 0; overflow: hidden; }
            .custom-popup .leaflet-popup-content { margin: 0; width: 260px !important; }
        </style>
    </x-slot:styles>

    <x-slot:scripts>
        {{-- Leaflet JS --}}
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
        <script src="{{ asset('js/common/map-search.js') }}?v={{ asset_buster(public_path('js/common/map-search.js')) }}"></script>
        {{-- マップ制御JS --}}
        <script src="{{ asset('js/shops/map.js') }}?v={{ asset_buster(public_path('js/shops/map.js')) }}"></script>
    </x-slot:scripts>

    <x-slot:navigation>
        <x-navigation :showSearch="true" />
    </x-slot:navigation>

    <div class="relative w-full h-[calc(100vh-64px)]">
        <div id="map" class="w-full h-full bg-gray-100"></div>

        {{-- 上部中央: 地名検索 --}}
        <div class="absolute top-3 left-1/2 -translate-x-1/2 z-[1000] w-[min(calc(100%-32px),400px)]">
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

        {{-- 検索中ローディング --}}
        <div id="map-loading" class="absolute top-14 left-1/2 -translate-x-1/2 bg-white/90 backdrop-blur px-4 py-2 rounded-full shadow-lg z-[1000] flex items-center gap-2 hidden">
            <i data-lucide="loader-2" class="w-4 h-4 animate-spin text-blue-600"></i>
            <span class="text-xs font-bold text-gray-600">エリアを検索中...</span>
        </div>

        {{-- 現在地ボタン --}}
        <button id="btn-current-location" class="absolute bottom-8 right-4 bg-white p-3 rounded-full shadow-lg z-[1000] text-gray-600 hover:text-blue-600 transition-colors border border-gray-200">
            <i data-lucide="crosshair" class="w-6 h-6"></i>
        </button>
    </div>

    {{-- チェーン店リンク --}}
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 sm:p-8">
            <div class="flex items-center gap-2 mb-6">
                <div class="p-2 bg-blue-50 rounded-lg text-blue-600">
                    <i data-lucide="building-2" class="w-5 h-5"></i>
                </div>
                <h2 class="text-lg font-black text-gray-900">チェーン店から探す</h2>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                @foreach(config('bike.chains') as $slug => $chain)
                <a href="{{ route('shops.chain', $slug) }}"
                   class="group flex items-center gap-3 bg-gray-50 hover:bg-blue-50 border border-gray-100 hover:border-blue-200 rounded-xl p-4 transition-all duration-200 hover:-translate-y-0.5 hover:shadow-sm">
                    <div class="shrink-0 w-8 h-8 rounded-lg bg-white border border-gray-100 group-hover:border-blue-200 flex items-center justify-center text-gray-400 group-hover:text-blue-600 transition-colors">
                        <i data-lucide="store" class="w-4 h-4"></i>
                    </div>
                    <p class="text-xs font-black text-gray-800 group-hover:text-blue-700 transition-colors">{{ $chain['name'] }}</p>
                </a>
                @endforeach
            </div>
        </div>
    </section>
</x-layout>