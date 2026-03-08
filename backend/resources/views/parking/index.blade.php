<x-layout>
    <x-slot:title>バイク駐車場・駐輪場マップ | MotoHub</x-slot:title>
    <x-slot:metaDescription>全国のバイク駐車場・駐輪場をマップで検索。料金や設備情報、ユーザーレビューも確認できます。</x-slot:metaDescription>

    <x-slot:styles>
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
        <style>
            #map { height: calc(100vh - 64px); z-index: 10; }
            .custom-popup .leaflet-popup-content-wrapper { border-radius: 12px; padding: 0; overflow: hidden; }
            .custom-popup .leaflet-popup-content { margin: 0; width: 280px !important; }
        </style>
    </x-slot:styles>

    <x-slot:scripts>
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
        <script src="{{ asset('js/parking/map.js') }}"></script>
    </x-slot:scripts>

    <x-slot:navigation>
        <x-navigation :showSearch="true" />
    </x-slot:navigation>

    <div class="relative w-full h-[calc(100vh-64px)]">
        <div id="map" class="w-full h-full bg-gray-100"></div>

        {{-- 検索中ローディング --}}
        <div id="map-loading" class="absolute top-4 left-1/2 -translate-x-1/2 bg-white/90 backdrop-blur px-4 py-2 rounded-full shadow-lg z-[1000] flex items-center gap-2 hidden">
            <i data-lucide="loader-2" class="w-4 h-4 animate-spin text-green-600"></i>
            <span class="text-xs font-bold text-gray-600">駐車場を検索中...</span>
        </div>

        {{-- フィルタパネル --}}
        <div x-data="{ open: false }" class="absolute top-4 left-4 z-[1000]">
            <button @click="open = !open" class="bg-white px-4 py-2.5 rounded-xl shadow-lg border border-gray-200 flex items-center gap-2 text-sm font-bold text-gray-700 hover:bg-gray-50 transition">
                <i data-lucide="sliders-horizontal" class="w-4 h-4"></i>
                フィルタ
            </button>
            <div x-show="open" x-transition @click.outside="open = false" class="mt-2 bg-white rounded-xl shadow-xl border border-gray-100 p-4 w-64" style="display: none;">
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

        {{-- 駐車場登録ボタン --}}
        <a href="{{ route('parking.create') }}" class="absolute top-4 right-16 bg-green-600 text-white px-4 py-2.5 rounded-xl shadow-lg flex items-center gap-2 text-xs font-bold hover:bg-green-700 transition z-[1000]">
            <i data-lucide="plus" class="w-4 h-4"></i>
            駐車場を登録
        </a>

        {{-- 現在地ボタン --}}
        <button id="btn-current-location" class="absolute bottom-8 right-4 bg-white p-3 rounded-full shadow-lg z-[1000] text-gray-600 hover:text-green-600 transition-colors border border-gray-200">
            <i data-lucide="crosshair" class="w-6 h-6"></i>
        </button>
    </div>
</x-layout>
