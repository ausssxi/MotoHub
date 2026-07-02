<x-layout>
    <x-slot:title>{{ $city }}（{{ $prefecture }}）のバイク整備・修理ショップ{{ $totalShops }}店｜認証工場・車検 - MotoHub</x-slot:title>
    <x-slot:metaDescription>{{ $prefecture }}{{ $city }}でバイクの整備・修理・車検を依頼できる認証工場・整備専門店を{{ $totalShops }}店掲載。対応サービス・住所・電話で比較できます。</x-slot:metaDescription>

    @if($totalShops < 3)
    <x-slot:robotsMeta>noindex, follow</x-slot:robotsMeta>
    @endif

    <x-slot:styles>
        <x-jsonld.breadcrumb-shop-repair-area :prefecture="$prefecture" :city="$city" />
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
        <style>
            #city-map { height: 300px; z-index: 10; border-radius: 12px; }
        </style>
    </x-slot:styles>

    <x-slot:scripts>
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
        @php
            $shopMarkers = $shops->filter(fn ($s) => $s->latitude && $s->longitude)->map(fn ($s) => [
                'lat' => $s->latitude,
                'lng' => $s->longitude,
                'name' => $s->name,
                'id' => $s->id,
            ])->values();
        @endphp
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const shops = {!! json_encode($shopMarkers, JSON_UNESCAPED_UNICODE) !!};
                if (!document.getElementById('city-map')) return;
                const map = L.map('city-map').setView([{{ $avgLat }}, {{ $avgLng }}], 13);
                L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>'
                }).addTo(map);
                const bounds = [];
                shops.forEach(s => {
                    if (s.lat && s.lng) {
                        const marker = L.marker([s.lat, s.lng]).addTo(map);
                        marker.bindPopup(`<a href="/shops/${s.id}" class="font-bold text-sm">${s.name}</a>`);
                        bounds.push([s.lat, s.lng]);
                    }
                });
                if (bounds.length > 1) map.fitBounds(bounds, { padding: [30, 30] });
                if (typeof lucide !== 'undefined') lucide.createIcons();
            });
        </script>
    </x-slot:scripts>

    <x-slot:navigation>
        <x-navigation :showSearch="true" />
    </x-slot:navigation>

    <div class="bg-gray-50 min-h-screen py-8" x-data="{ filter: '' }">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">

            {{-- パンくず --}}
            <nav class="overflow-x-auto text-xs font-bold text-gray-400 mb-6 scrollbar-hide" aria-label="Breadcrumb">
                <ol class="flex items-center space-x-2 whitespace-nowrap">
                    <li><a href="/" class="hover:text-gray-600 transition-colors">HOME</a></li>
                    <li><span class="text-gray-300">＞</span></li>
                    <li><a href="{{ route('shops.repair.index') }}" class="hover:text-gray-600 transition-colors">バイク整備・修理店</a></li>
                    <li><span class="text-gray-300">＞</span></li>
                    <li><a href="{{ route('shops.repair.prefecture', $prefecture) }}" class="hover:text-gray-600 transition-colors">{{ $prefecture }}</a></li>
                    <li><span class="text-gray-300">＞</span></li>
                    <li><span class="text-gray-800">{{ $city }}</span></li>
                </ol>
            </nav>

            {{-- ヘッダー --}}
            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8 mb-8">
                <h1 class="text-2xl sm:text-3xl font-black text-gray-900 mb-3">
                    {{ $city }}（{{ $prefecture }}）のバイク整備・修理ショップ
                </h1>
                <p class="text-sm text-gray-500 font-bold mb-4">
                    バイクの点検・整備・修理・車検を依頼できる整備専門店・認証工場をまとめました。
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
                    <i data-lucide="map" class="w-4 h-4 text-green-600"></i> {{ $city }}の整備・修理店マップ
                </h2>
                <div id="city-map" class="w-full"></div>
            </div>

            {{-- 対応サービスで絞り込み --}}
            @if($serviceTags->isNotEmpty())
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 mb-6">
                <h2 class="text-sm font-black text-gray-900 mb-3 flex items-center gap-2">
                    <i data-lucide="filter" class="w-4 h-4 text-green-600"></i> 対応サービスで絞り込む
                </h2>
                <div class="flex flex-wrap gap-2">
                    <button type="button" @click="filter = ''"
                        class="px-3 py-1.5 rounded-lg text-xs font-bold border transition-colors"
                        :class="filter === '' ? 'bg-green-600 text-white border-green-600' : 'bg-gray-50 text-gray-600 border-gray-100 hover:bg-green-50'">
                        すべて（{{ $totalShops }}）
                    </button>
                    @foreach($serviceTags as $st)
                    <button type="button" @click="filter = @js($st['name'])"
                        class="px-3 py-1.5 rounded-lg text-xs font-bold border transition-colors"
                        :class="filter === @js($st['name']) ? 'bg-green-600 text-white border-green-600' : 'bg-gray-50 text-gray-600 border-gray-100 hover:bg-green-50'">
                        {{ $st['name'] }}（{{ $st['count'] }}）
                    </button>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- ショップカード一覧 --}}
            <section class="mb-8">
                <h2 class="text-base font-black text-gray-900 mb-4 flex items-center gap-2">
                    <i data-lucide="wrench" class="w-5 h-5 text-green-500"></i>
                    {{ $city }}の整備・修理店一覧
                </h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($shops as $shop)
                    <a href="{{ route('shops.show', $shop) }}"
                       x-show="filter === '' || {{ json_encode(array_values($shop->service_tags ?? []), JSON_UNESCAPED_UNICODE) }}.includes(filter)"
                       class="group bg-white rounded-2xl shadow-sm border border-gray-100 hover:border-green-200 hover:shadow-md transition-all duration-200 hover:-translate-y-0.5 block overflow-hidden">
                        @if($shop->display_image_url)
                        <div class="aspect-[16/9] bg-gray-200 overflow-hidden">
                            <img src="{{ $shop->display_image_url }}" alt="{{ $shop->name }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" loading="lazy">
                        </div>
                        @endif
                        <div class="p-5">
                            <div class="flex items-start justify-between mb-2">
                                <h3 class="text-sm font-black text-gray-900 group-hover:text-green-700 transition-colors line-clamp-2 flex-1">{{ $shop->name }}</h3>
                                @if($shop->rating > 0)
                                <span class="shrink-0 ml-2 text-sm font-black text-yellow-500 flex items-center gap-0.5">
                                    <i data-lucide="star" class="w-3.5 h-3.5 fill-current"></i>
                                    {{ number_format($shop->rating, 1) }}
                                </span>
                                @endif
                            </div>
                            <p class="text-xs text-gray-400 line-clamp-1 mb-3">{{ $shop->address }}</p>
                            <div class="mb-3">
                                <x-shop-service-tags :tags="$shop->service_tags" />
                            </div>
                            <div class="flex items-center justify-between text-xs">
                                @if($shop->phone)
                                <span class="font-bold text-gray-600">{{ $shop->phone }}</span>
                                @else
                                <span></span>
                                @endif
                                <span class="text-gray-300 group-hover:text-green-500 transition-colors">
                                    <i data-lucide="chevron-right" class="w-4 h-4"></i>
                                </span>
                            </div>
                        </div>
                    </a>
                    @endforeach
                </div>
            </section>

            {{-- FAQ --}}
            @php
                $faqs = [
                    [
                        'q' => "{$city}でバイクの整備・修理ができるお店は？",
                        'a' => "{$city}には{$totalShops}店のバイク整備・修理店・認証工場があります。点検・整備・車検・タイヤ交換などに対応しています。",
                    ],
                    [
                        'q' => '認証工場とは何ですか？',
                        'a' => '認証工場は、地方運輸局長の認証を受けた自動車（二輪含む）の分解整備ができる工場です。一定の設備・技術・管理体制の基準を満たしており、安心して整備を依頼できます。',
                    ],
                    [
                        'q' => 'バイクの車検はどこで受けられますか？',
                        'a' => "{$city}の「車検受付」対応の整備店で受けられます。指定工場では工場内で検査まで完結し、認証工場では運輸支局へ持ち込んで検査します。",
                    ],
                ];
            @endphp
            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8 mb-8" x-data="{ open: null }">
                <h2 class="text-sm font-black text-gray-900 mb-4 flex items-center gap-2">
                    <i data-lucide="help-circle" class="w-4 h-4 text-green-600"></i> よくある質問
                </h2>
                <div class="divide-y divide-gray-100">
                    @foreach($faqs as $i => $faq)
                    <div class="py-3 first:pt-0 last:pb-0">
                        <button type="button" @click="open = open === {{ $i }} ? null : {{ $i }}"
                            class="w-full flex items-start justify-between gap-3 text-left group">
                            <span class="text-sm font-bold text-gray-800 group-hover:text-green-600 transition">{{ $faq['q'] }}</span>
                            <i data-lucide="chevron-down" class="w-4 h-4 text-gray-400 shrink-0 mt-0.5 transition-transform duration-200"
                               x-bind:class="{ 'rotate-180': open === {{ $i }} }"></i>
                        </button>
                        <div x-show="open === {{ $i }}" x-transition style="display: none;">
                            <p class="text-sm text-gray-600 mt-2 pl-0.5">{{ $faq['a'] }}</p>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>

            {{-- FAQPage JSON-LD --}}
            <script type="application/ld+json">
            {
                "@@context": "https://schema.org",
                "@@type": "FAQPage",
                "mainEntity": [
                    @foreach($faqs as $i => $faq)
                    {
                        "@@type": "Question",
                        "name": "{{ e($faq['q']) }}",
                        "acceptedAnswer": {
                            "@@type": "Answer",
                            "text": "{{ e($faq['a']) }}"
                        }
                    }@if(!$loop->last),@endif
                    @endforeach
                ]
            }
            </script>

            {{-- 周辺エリア --}}
            @if($siblingCities->isNotEmpty())
            <section class="mb-8">
                <h2 class="text-base font-black text-gray-900 mb-4 flex items-center gap-2">
                    <i data-lucide="compass" class="w-5 h-5 text-green-500"></i>
                    {{ $prefecture }}の他のエリアの整備店
                </h2>
                <div class="flex flex-wrap gap-2">
                    @foreach($siblingCities as $sibling)
                    <a href="{{ route('shops.repair.city', [$prefecture, $sibling->city]) }}"
                       class="px-3 py-1.5 rounded-lg bg-white border border-gray-100 text-xs font-bold text-gray-600 hover:bg-green-50 hover:border-green-200 hover:text-green-600 transition-colors">
                        {{ $sibling->city }} ({{ $sibling->count }}店)
                    </a>
                    @endforeach
                </div>
            </section>
            @endif

            {{-- 未掲載店の投稿導線 --}}
            <a href="{{ route('shops.submit.create', ['pref' => $prefecture, 'city' => $city]) }}"
               class="mt-8 flex items-center justify-between bg-emerald-50 border border-emerald-200 rounded-2xl p-5 hover:shadow-md transition-shadow">
                <div class="flex items-center gap-3">
                    <i data-lucide="plus-circle" class="w-6 h-6 text-emerald-600"></i>
                    <div>
                        <p class="text-sm font-black text-gray-800">{{ $city }}で載っていない整備・修理店を知っていますか？</p>
                        <p class="text-xs text-gray-500 font-bold">掲載リクエストを送る（承認後に掲載されます）</p>
                    </div>
                </div>
                <i data-lucide="chevron-right" class="w-5 h-5 text-emerald-400"></i>
            </a>

            {{-- 回遊リンク --}}
            <div class="mt-8">
                <x-cross-links :crossLinks="$crossLinks" />
            </div>
        </div>
    </div>
</x-layout>
