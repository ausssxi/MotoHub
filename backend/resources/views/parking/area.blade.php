<x-layout>
    <x-slot:title>{{ $prefecture }}のバイク駐車場・駐輪場一覧（{{ $totalCount }}件） | MotoHub</x-slot:title>
    <x-slot:metaDescription>{{ $prefecture }}のバイク駐車場・駐輪場を{{ $totalCount }}件掲載。@if($priceStats['avg_per_hour'])時間料金の相場は平均{{ $priceStats['avg_per_hour'] }}円/時。@endif料金・設備・レビューで比較できます。</x-slot:metaDescription>

    <x-slot:styles>
        <x-jsonld.breadcrumb-parking :prefecture="$prefecture" />
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
        <style>
            #area-map { height: 300px; z-index: 10; border-radius: 12px; }
        </style>
    </x-slot:styles>

    <x-slot:scripts>
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
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
            <nav class="flex text-xs font-bold text-gray-400 mb-6" aria-label="Breadcrumb">
                <ol class="flex items-center space-x-2">
                    <li><a href="/" class="hover:text-gray-600 transition-colors">HOME</a></li>
                    <li><span class="text-gray-300">＞</span></li>
                    <li><a href="{{ route('parking.index') }}" class="hover:text-gray-600 transition-colors">駐車場マップ</a></li>
                    <li><span class="text-gray-300">＞</span></li>
                    <li><a href="{{ route('parking.area.index') }}" class="hover:text-gray-600 transition-colors">エリアから探す</a></li>
                    <li><span class="text-gray-300">＞</span></li>
                    <li><span class="text-gray-800">{{ $prefecture }}</span></li>
                </ol>
            </nav>

            {{-- ヘッダー --}}
            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8 mb-8">
                <h1 class="text-2xl sm:text-3xl font-black text-gray-900 mb-4">
                    {{ $prefecture }}のバイク駐車場・駐輪場一覧
                </h1>
                <div class="flex flex-wrap gap-4">
                    <div class="flex items-center gap-2 bg-green-50 text-green-700 text-sm font-bold px-4 py-2 rounded-xl border border-green-100">
                        <i data-lucide="square-parking" class="w-4 h-4"></i>
                        {{ $totalCount }} 件
                    </div>
                    @if($freeCount > 0)
                    <div class="flex items-center gap-2 bg-yellow-50 text-yellow-700 text-sm font-bold px-4 py-2 rounded-xl border border-yellow-100">
                        <i data-lucide="circle-check" class="w-4 h-4"></i>
                        無料 {{ $freeCount }} 件
                    </div>
                    @endif
                </div>
                <div class="mt-4">
                    <a href="{{ route('parking.index', ['lat' => $avgLat, 'lng' => $avgLng]) }}"
                       class="inline-flex items-center gap-2 text-xs font-bold text-green-600 hover:text-green-700 transition-colors">
                        <i data-lucide="map" class="w-4 h-4"></i>
                        {{ $prefecture }}の駐車場をマップで見る
                    </a>
                </div>
            </div>

            {{-- 料金相場 --}}
            @if($priceStats['avg_per_hour'] || $priceStats['avg_per_month'])
            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8 mb-8">
                <h2 class="text-base font-black text-gray-900 mb-4 flex items-center gap-2">
                    <i data-lucide="coins" class="w-5 h-5 text-green-500"></i>
                    {{ $prefecture }}の駐車料金相場
                </h2>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    @if($priceStats['avg_per_hour'])
                    <div class="bg-gray-50 rounded-xl p-4 text-center">
                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">平均時間料金</p>
                        <p class="text-xl font-black text-gray-900">{{ number_format($priceStats['avg_per_hour']) }}<span class="text-xs font-bold text-gray-400">円/時</span></p>
                    </div>
                    @endif
                    @if($priceStats['min_per_hour'])
                    <div class="bg-gray-50 rounded-xl p-4 text-center">
                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">最安時間料金</p>
                        <p class="text-xl font-black text-green-600">{{ number_format($priceStats['min_per_hour']) }}<span class="text-xs font-bold text-gray-400">円/時</span></p>
                    </div>
                    @endif
                    @if($priceStats['max_per_hour'])
                    <div class="bg-gray-50 rounded-xl p-4 text-center">
                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">最高時間料金</p>
                        <p class="text-xl font-black text-gray-500">{{ number_format($priceStats['max_per_hour']) }}<span class="text-xs font-bold text-gray-400">円/時</span></p>
                    </div>
                    @endif
                    @if($priceStats['avg_per_month'])
                    <div class="bg-gray-50 rounded-xl p-4 text-center">
                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">平均月極料金</p>
                        <p class="text-xl font-black text-gray-900">{{ number_format($priceStats['avg_per_month']) }}<span class="text-xs font-bold text-gray-400">円/月</span></p>
                    </div>
                    @endif
                </div>
            </div>
            @endif

            {{-- マップ --}}
            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 mb-8">
                <h2 class="text-sm font-black text-gray-900 mb-3 flex items-center gap-2">
                    <i data-lucide="map" class="w-4 h-4 text-green-600"></i> {{ $prefecture }}の駐車場マップ
                </h2>
                <div id="area-map" class="w-full"></div>
            </div>

            {{-- 市区町村カード --}}
            <section class="mb-8">
                <h2 class="text-base font-black text-gray-900 mb-4 flex items-center gap-2">
                    <i data-lucide="map-pin" class="w-5 h-5 text-green-500"></i>
                    {{ $prefecture }}の市区町村から探す
                </h2>
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                    @foreach($cities as $cityData)
                    {{-- city 名が空の集計（未設定）はリンク化しない（空 city セグメントのcrawl汚染を防ぐ。prefecture はページ必須なので常に有り） --}}
                    @php $cityUrl = !empty($cityData['name']) ? route('parking.area.city', [$prefecture, $cityData['name']]) : null; @endphp
                    <a @if($cityUrl) href="{{ $cityUrl }}" @endif
                       class="bg-white rounded-xl border border-gray-100 p-4 hover:border-green-200 hover:shadow-md transition-all duration-200 hover:-translate-y-0.5 block">
                        <div class="text-sm font-black text-gray-800 mb-1">{{ $cityData['name'] ?: '（未設定）' }}</div>
                        <div class="flex items-center gap-2 text-xs">
                            <span class="font-bold text-green-600">{{ $cityData['count'] }}件</span>
                            @if($cityData['free_count'] > 0)
                            <span class="text-yellow-600">無料{{ $cityData['free_count'] }}件</span>
                            @endif
                        </div>
                        @if($cityData['avg_price_per_hour'])
                        <div class="text-[10px] text-gray-400 mt-1">平均{{ number_format($cityData['avg_price_per_hour']) }}円/時</div>
                        @endif
                    </a>
                    @endforeach
                </div>
            </section>

            {{-- このエリアで売っているバイク --}}
            @if($nearbyListings->isNotEmpty())
            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8 mb-8">
                <h2 class="text-sm font-black text-gray-900 mb-4 flex items-center gap-2">
                    <i data-lucide="bike" class="w-4 h-4 text-green-600"></i> {{ $prefecture }}で売っているバイク
                </h2>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                    @foreach($nearbyListings as $nl)
                    @php
                        $img = null;
                        if ($nl->local_image_paths && is_array($nl->local_image_paths) && count($nl->local_image_paths) > 0) {
                            $img = listing_image_url($nl->local_image_paths[0]);
                        } elseif ($nl->image_urls) {
                            $img = is_array($nl->image_urls) ? ($nl->image_urls[0] ?? null) : $nl->image_urls;
                        }
                        $price = $nl->total_price ? number_format((int)$nl->total_price / 10000, 1) : null;
                    @endphp
                    <a href="{{ route('bikes.show', $nl->id) }}" class="block bg-gray-50 rounded-xl overflow-hidden border border-gray-100 hover:shadow-md hover:-translate-y-0.5 transition-all group">
                        <div class="aspect-[4/3] bg-gray-200 overflow-hidden">
                            @if($img)
                            <img src="{{ $img }}" alt="{{ $nl->title }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" loading="lazy"
                                 onerror="this.parentElement.innerHTML='<div class=\'w-full h-full flex items-center justify-center text-gray-300\'><svg class=\'w-8 h-8\' fill=\'none\' stroke=\'currentColor\' viewBox=\'0 0 24 24\'><path stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'1.5\' d=\'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14\'/></svg></div>'">
                            @else
                            <div class="w-full h-full flex items-center justify-center text-gray-300">
                                <i data-lucide="image" class="w-8 h-8"></i>
                            </div>
                            @endif
                        </div>
                        <div class="p-2.5">
                            <p class="text-xs font-bold text-gray-800 truncate">{{ $nl->bikeModel->name ?? $nl->title }}</p>
                            @if($price)
                            <p class="text-sm font-black text-red-500 mt-0.5">{{ $price }}<span class="text-[10px] font-bold text-gray-400">万円</span></p>
                            @endif
                            <p class="text-[10px] text-gray-400 truncate mt-0.5">{{ $nl->shop->name ?? '' }}</p>
                        </div>
                    </a>
                    @endforeach
                </div>
                <div class="text-center mt-4">
                    <a href="{{ route('bikes.search', ['prefecture' => mb_substr($prefecture, 0, -1)]) }}"
                       class="inline-flex items-center gap-1 text-xs font-bold text-blue-600 hover:text-blue-700 transition">
                        {{ $prefecture }}の中古バイク一覧を見る
                        <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                    </a>
                </div>
            </div>
            @endif

            {{-- バイク検索クロスリンク --}}
            <div class="mt-8 bg-blue-50 rounded-2xl p-5 border border-blue-100 flex items-center justify-between hover:shadow-md transition-shadow">
                <div class="flex items-center gap-3">
                    <span class="text-2xl">🏍️</span>
                    <span class="text-sm font-black text-gray-800">{{ $prefecture }}の中古バイクを探す</span>
                </div>
                <a href="{{ route('bikes.search', ['prefecture' => $prefecture]) }}" class="text-xs font-bold text-blue-600 hover:text-blue-800 flex items-center gap-1">
                    検索する <i data-lucide="arrow-right" class="w-4 h-4"></i>
                </a>
            </div>

            {{-- 回遊リンク --}}
            <div class="mt-8">
                <x-cross-links :crossLinks="$crossLinks" />
            </div>

            {{-- 教習所ページへの内部リンク（公開県のみ表示） --}}
            <div class="mt-4">
                <x-license.schools-link :prefecture="$prefecture" />
            </div>
        </div>
    </div>
</x-layout>
