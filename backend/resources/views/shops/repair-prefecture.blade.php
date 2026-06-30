<x-layout>
    <x-slot:title>{{ $prefecture }}のバイク整備・修理ショップ一覧（{{ $totalShops }}店）｜認証工場 - MotoHub</x-slot:title>
    <x-slot:metaDescription>{{ $prefecture }}でバイクの整備・修理・車検を依頼できる整備専門店・認証工場を{{ $totalShops }}店掲載。市区町村ごとに整備店を探せます。</x-slot:metaDescription>

    @if($totalShops < 3)
    <x-slot:robotsMeta>noindex, follow</x-slot:robotsMeta>
    @endif

    <x-slot:styles>
        <x-jsonld.breadcrumb-shop-repair-area :prefecture="$prefecture" />
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
        <style>
            #area-map { height: 300px; z-index: 10; border-radius: 12px; }
        </style>
    </x-slot:styles>

    <x-slot:scripts>
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                if (!document.getElementById('area-map')) return;
                const lat = {{ $avgLat }};
                const lng = {{ $avgLng }};
                const map = L.map('area-map').setView([lat, lng], 10);
                L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>'
                }).addTo(map);
                L.marker([lat, lng]).addTo(map).bindPopup('{{ $prefecture }}').openPopup();
                if (typeof lucide !== 'undefined') lucide.createIcons();
            });
        </script>
    </x-slot:scripts>

    <x-slot:navigation>
        <x-navigation :showSearch="true" />
    </x-slot:navigation>

    <div class="bg-gray-50 min-h-screen py-8">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">

            {{-- パンくず --}}
            <nav class="overflow-x-auto text-xs font-bold text-gray-400 mb-6 scrollbar-hide" aria-label="Breadcrumb">
                <ol class="flex items-center space-x-2 whitespace-nowrap">
                    <li><a href="/" class="hover:text-gray-600 transition-colors">HOME</a></li>
                    <li><span class="text-gray-300">＞</span></li>
                    <li><a href="{{ route('shops.repair.index') }}" class="hover:text-gray-600 transition-colors">バイク整備・修理店</a></li>
                    <li><span class="text-gray-300">＞</span></li>
                    <li><span class="text-gray-800">{{ $prefecture }}</span></li>
                </ol>
            </nav>

            {{-- ヘッダー --}}
            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8 mb-8">
                <h1 class="text-2xl sm:text-3xl font-black text-gray-900 mb-3">
                    {{ $prefecture }}のバイク整備・修理ショップ
                </h1>
                <p class="text-sm text-gray-500 font-bold mb-4">
                    {{ $prefecture }}でバイクの点検・整備・修理・車検に対応する整備専門店・認証工場をエリアごとにまとめました。
                </p>
                <div class="flex flex-wrap gap-3">
                    <div class="flex items-center gap-2 bg-green-50 text-green-700 text-sm font-bold px-4 py-2 rounded-xl border border-green-100">
                        <i data-lucide="wrench" class="w-4 h-4"></i>
                        整備・修理店 {{ $totalShops }} 店
                    </div>
                </div>
            </div>

            {{-- マップ --}}
            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 mb-8">
                <h2 class="text-sm font-black text-gray-900 mb-3 flex items-center gap-2">
                    <i data-lucide="map" class="w-4 h-4 text-green-600"></i> {{ $prefecture }}の整備・修理店マップ
                </h2>
                <div id="area-map" class="w-full"></div>
            </div>

            {{-- 市区町村一覧 --}}
            @if($cities->isNotEmpty())
            <section class="mb-8">
                <h2 class="text-base font-black text-gray-900 mb-4 flex items-center gap-2">
                    <i data-lucide="map-pin" class="w-5 h-5 text-green-500"></i>
                    市区町村から整備店を探す
                </h2>
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                    @foreach($cities as $c)
                    <a href="{{ route('shops.repair.city', [$prefecture, $c['name']]) }}"
                       class="flex items-center justify-between bg-white rounded-xl border border-gray-100 hover:border-green-200 hover:bg-green-50 px-4 py-3 transition-colors group">
                        <span class="text-sm font-bold text-gray-800 group-hover:text-green-700">{{ $c['name'] }}</span>
                        <span class="text-xs font-bold text-gray-400">{{ $c['count'] }}店</span>
                    </a>
                    @endforeach
                </div>
            </section>
            @endif

            {{-- 回遊リンク --}}
            <div class="mt-8">
                <x-cross-links :crossLinks="$crossLinks" />
            </div>
        </div>
    </div>
</x-layout>
