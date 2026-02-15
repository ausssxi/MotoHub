<x-layout>
    <x-slot:title>バイクショップ マップ検索 | MotoHub</x-slot:title>
    <x-slot:metaDescription>現在地や指定したエリアから、近くのバイクショップを探せます。在庫数や店舗情報も地図上で一目で確認できます。</x-slot:metaDescription>

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
        {{-- マップ制御JS --}}
        <script src="{{ asset('js/shops/map.js') }}"></script>
    </x-slot:scripts>

    <x-slot:navigation>
        <x-navigation :showSearch="true" />
    </x-slot:navigation>

    <div class="relative w-full h-[calc(100vh-64px)]">
        <div id="map" class="w-full h-full bg-gray-100"></div>

        {{-- 検索中ローディング --}}
        <div id="map-loading" class="absolute top-4 left-1/2 -translate-x-1/2 bg-white/90 backdrop-blur px-4 py-2 rounded-full shadow-lg z-[1000] flex items-center gap-2 hidden">
            <i data-lucide="loader-2" class="w-4 h-4 animate-spin text-blue-600"></i>
            <span class="text-xs font-bold text-gray-600">エリアを検索中...</span>
        </div>

        {{-- 現在地ボタン --}}
        <button id="btn-current-location" class="absolute bottom-8 right-4 bg-white p-3 rounded-full shadow-lg z-[1000] text-gray-600 hover:text-blue-600 transition-colors border border-gray-200">
            <i data-lucide="crosshair" class="w-6 h-6"></i>
        </button>
    </div>
</x-layout>