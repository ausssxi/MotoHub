<x-layout>
    <x-slot:title>{{ $prefecture }}のレンタルバイク店舗｜地図・一覧 - MotoHub</x-slot:title>
    <x-slot:metaDescription>{{ $prefecture }}のレンタルバイク店舗を市区町村別に一覧。店舗名・事業者・所在地・電話番号と地図を掲載しています。</x-slot:metaDescription>

    <x-slot:styles>
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
        <style>
            #pref-map { height: 320px; z-index: 10; border-radius: 12px; }
        </style>
    </x-slot:styles>

    <x-slot:scripts>
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
        @if(!empty($markers))
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const markers = @json($markers);
                if (!markers.length) return;
                const map = L.map('pref-map');
                L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>'
                }).addTo(map);
                const bounds = [];
                markers.forEach((m) => {
                    const link = document.createElement('a');
                    link.href = m.url;
                    link.textContent = m.name;
                    L.marker([m.lat, m.lng]).addTo(map).bindPopup(link);
                    bounds.push([m.lat, m.lng]);
                });
                if (bounds.length === 1) {
                    map.setView(bounds[0], 14);
                } else {
                    map.fitBounds(bounds, { padding: [30, 30] });
                }
                if (typeof lucide !== 'undefined') lucide.createIcons();
            });
        </script>
        @endif
    </x-slot:scripts>

    <x-slot:navigation>
        <x-navigation :showSearch="true" />
    </x-slot:navigation>

    <div class="bg-gray-50 min-h-screen py-8">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">

            {{-- パンくず --}}
            <nav class="flex text-xs font-bold text-gray-400 mb-6" aria-label="Breadcrumb">
                <ol class="flex items-center space-x-2">
                    <li><a href="/" class="hover:text-gray-600 transition-colors">HOME</a></li>
                    <li><span class="text-gray-300">＞</span></li>
                    <li><a href="{{ route('rental-bike.index') }}" class="hover:text-gray-600 transition-colors">レンタルバイク</a></li>
                    <li><span class="text-gray-300">＞</span></li>
                    <li><span class="text-gray-800">{{ $prefecture }}</span></li>
                </ol>
            </nav>

            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8">
                <h1 class="text-xl font-black text-gray-900 mb-4">{{ $prefecture }}のレンタルバイク店舗</h1>

                {{-- 地図（都道府県ページには出してよい） --}}
                @if(!empty($markers))
                <div id="pref-map" class="w-full mb-6"></div>
                @endif

                {{-- 市区町村見出しでグルーピング --}}
                <div class="space-y-6">
                    @foreach($byCity as $city => $shops)
                    <section>
                        @if($city !== '')
                        <h2 class="text-sm font-black text-gray-900 mb-2"><i data-lucide="map-pin" class="inline w-4 h-4 text-gray-400"></i> {{ $city }}</h2>
                        @endif
                        <ul class="divide-y divide-gray-100 border border-gray-100 rounded-xl overflow-hidden">
                            @foreach($shops as $shop)
                            <li class="px-4 py-3 hover:bg-gray-50 transition">
                                <a href="{{ route('rental-bike.show', $shop['id']) }}" class="text-sm font-bold text-violet-700 hover:underline">{{ $shop['name'] }}</a>
                                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 mt-1 text-[11px] text-gray-500">
                                    <span class="inline-block px-2 py-0.5 bg-gray-100 text-gray-600 rounded font-bold">{{ $shop['company'] }}</span>
                                    @if($shop['tel'])
                                    <span><i data-lucide="phone" class="inline w-3 h-3"></i> {{ $shop['tel'] }}</span>
                                    @endif
                                </div>
                            </li>
                            @endforeach
                        </ul>
                    </section>
                    @endforeach
                </div>
            </div>

            {{-- 他の都道府県から探す（店舗のある都道府県のみ） --}}
            @if(!empty($allPrefectures))
            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8 mt-6">
                <h2 class="text-sm font-black text-gray-900 mb-3">他の都道府県から探す</h2>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                    @foreach($allPrefectures as $p)
                    <a href="{{ route('rental-bike.prefecture', $p['prefecture']) }}" class="flex items-baseline justify-between gap-1 px-2.5 py-1.5 border border-gray-100 rounded-lg text-xs hover:bg-violet-50 transition {{ $p['prefecture'] === $prefecture ? 'bg-violet-50 border-violet-200' : '' }}">
                        <span class="text-violet-700 font-bold">{{ $p['prefecture'] }}</span>
                    </a>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- 回遊リンク --}}
            <div class="mt-6">
                <x-cross-links :crossLinks="$crossLinks" />
            </div>
        </div>
    </div>

    {{-- JSON-LD: BreadcrumbList --}}
    @php
        $ldFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
        $ldBreadcrumb = [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'HOME', 'item' => url('/')],
                ['@type' => 'ListItem', 'position' => 2, 'name' => 'レンタルバイク', 'item' => route('rental-bike.index')],
                ['@type' => 'ListItem', 'position' => 3, 'name' => $prefecture, 'item' => route('rental-bike.prefecture', $prefecture)],
            ],
        ];
    @endphp
    <script type="application/ld+json">{!! json_encode($ldBreadcrumb, $ldFlags) !!}</script>
</x-layout>
