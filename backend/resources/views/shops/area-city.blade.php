<x-layout>
    <x-slot:title>{{ $city }}（{{ $prefecture }}）のバイクショップ{{ $totalShops }}店 | MotoHub</x-slot:title>
    <x-slot:metaDescription>{{ $prefecture }}{{ $city }}のバイクショップ・販売店を{{ $totalShops }}店掲載。@if($totalListings > 0)在庫{{ number_format($totalListings) }}台。@endif店舗情報・在庫・評価で比較できます。</x-slot:metaDescription>

    @if($totalShops < 3)
    <x-slot:robotsMeta>noindex, follow</x-slot:robotsMeta>
    @endif

    <x-slot:styles>
        <x-jsonld.breadcrumb-shop-area :prefecture="$prefecture" :city="$city" />
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
        <style>
            #city-map { height: 300px; z-index: 10; border-radius: 12px; }
        </style>
    </x-slot:styles>

    <x-slot:scripts>
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
        @php
            $shopMarkers = $shops->filter(fn($s) => $s->latitude && $s->longitude)->map(fn($s) => [
                'lat' => $s->latitude,
                'lng' => $s->longitude,
                'name' => $s->name,
                'id' => $s->id,
            ])->values();
        @endphp
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const shops = {!! json_encode($shopMarkers, JSON_UNESCAPED_UNICODE) !!};
                const avgLat = {{ $avgLat }};
                const avgLng = {{ $avgLng }};
                const map = L.map('city-map').setView([avgLat, avgLng], 13);
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
                if (bounds.length > 1) {
                    map.fitBounds(bounds, { padding: [30, 30] });
                }
                if (typeof lucide !== 'undefined') lucide.createIcons();
            });
        </script>

        @if($makerDistribution->isNotEmpty() || $priceRanges->isNotEmpty())
        @php
            $makerColors = [
                'ホンダ' => '#E60012',
                'ヤマハ' => '#0068B7',
                'スズキ' => '#FFD700',
                'カワサキ' => '#00A550',
                'ハーレーダビッドソン' => '#F47920',
            ];
            $defaultColor = '#9CA3AF';
            $top5 = $makerDistribution->take(5);
            $othersCount = $makerDistribution->skip(5)->sum('count');
            $donutLabels = $top5->pluck('name')->toArray();
            $donutData = $top5->pluck('count')->toArray();
            $donutColors = $top5->map(fn($m) => $makerColors[$m->name] ?? $defaultColor)->toArray();
            if ($othersCount > 0) {
                $donutLabels[] = 'その他';
                $donutData[] = $othersCount;
                $donutColors[] = $defaultColor;
            }
        @endphp
        <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.js"></script>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var donutEl = document.getElementById('makerDonut');
            if (donutEl) {
                new Chart(donutEl, {
                    type: 'doughnut',
                    data: {
                        labels: @json($donutLabels),
                        datasets: [{
                            data: @json($donutData),
                            backgroundColor: @json($donutColors),
                            borderWidth: 0,
                            hoverOffset: 8
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '60%',
                        plugins: {
                            legend: { position: 'bottom', labels: { font: { size: 11, weight: 'bold' }, padding: 12 } },
                            tooltip: {
                                callbacks: {
                                    label: function(ctx) {
                                        var total = ctx.dataset.data.reduce(function(a,b){return a+b},0);
                                        var pct = ((ctx.raw / total) * 100).toFixed(1);
                                        return ctx.label + ': ' + ctx.raw.toLocaleString() + '台 (' + pct + '%)';
                                    }
                                }
                            }
                        }
                    },
                    plugins: [{
                        id: 'centerText',
                        afterDraw: function(chart) {
                            var total = chart.data.datasets[0].data.reduce(function(a,b){return a+b},0);
                            var ctx = chart.ctx;
                            var cx = (chart.chartArea.left + chart.chartArea.right) / 2;
                            var cy = (chart.chartArea.top + chart.chartArea.bottom) / 2;
                            ctx.save();
                            ctx.textAlign = 'center';
                            ctx.fillStyle = '#9CA3AF';
                            ctx.font = 'bold 11px sans-serif';
                            ctx.fillText('合計', cx, cy - 10);
                            ctx.fillStyle = '#111827';
                            ctx.font = 'bold 20px sans-serif';
                            ctx.fillText(total.toLocaleString() + '台', cx, cy + 14);
                            ctx.restore();
                        }
                    }]
                });
            }

            var priceEl = document.getElementById('priceBar');
            if (priceEl) {
                new Chart(priceEl, {
                    type: 'bar',
                    data: {
                        labels: @json($priceRanges->pluck('price_range')->toArray()),
                        datasets: [{
                            data: @json($priceRanges->pluck('cnt')->toArray()),
                            backgroundColor: '#60A5FA',
                            borderRadius: 6,
                            barThickness: 28
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        indexAxis: 'y',
                        plugins: {
                            legend: { display: false },
                            tooltip: { callbacks: { label: function(ctx) { return ctx.raw.toLocaleString() + '台'; } } }
                        },
                        scales: {
                            x: { grid: { display: false }, ticks: { font: { size: 11 } } },
                            y: { grid: { display: false }, ticks: { font: { size: 11, weight: 'bold' } } }
                        }
                    }
                });
            }
        });
        </script>
        @endif
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
                    <li><a href="{{ route('shops.map') }}" class="hover:text-gray-600 transition-colors">ショップマップ</a></li>
                    <li><span class="text-gray-300">＞</span></li>
                    <li><a href="{{ route('shops.area.index') }}" class="hover:text-gray-600 transition-colors">エリアから探す</a></li>
                    <li><span class="text-gray-300">＞</span></li>
                    <li><a href="{{ route('shops.area.prefecture', $prefecture) }}" class="hover:text-gray-600 transition-colors">{{ $prefecture }}</a></li>
                    <li><span class="text-gray-300">＞</span></li>
                    <li><span class="text-gray-800">{{ $city }}</span></li>
                </ol>
            </nav>

            {{-- ヘッダー --}}
            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8 mb-8">
                <h1 class="text-2xl sm:text-3xl font-black text-gray-900 mb-4">
                    {{ $city }}（{{ $prefecture }}）のバイクショップ
                </h1>
                <div class="flex flex-wrap gap-4">
                    <div class="flex items-center gap-2 bg-blue-50 text-blue-700 text-sm font-bold px-4 py-2 rounded-xl border border-blue-100">
                        <i data-lucide="store" class="w-4 h-4"></i>
                        {{ $totalShops }} 店
                    </div>
                    @if($totalListings > 0)
                    <div class="flex items-center gap-2 bg-green-50 text-green-700 text-sm font-bold px-4 py-2 rounded-xl border border-green-100">
                        <i data-lucide="bike" class="w-4 h-4"></i>
                        在庫 {{ number_format($totalListings) }} 台
                    </div>
                    @endif
                    @if($avgPrice)
                    <div class="flex items-center gap-2 bg-yellow-50 text-yellow-700 text-sm font-bold px-4 py-2 rounded-xl border border-yellow-100">
                        <i data-lucide="coins" class="w-4 h-4"></i>
                        平均 {{ number_format((int)($avgPrice / 10000)) }} 万円
                    </div>
                    @endif
                </div>
            </div>

            {{-- 店名で探す（県内プリフィル） --}}
            <x-shop-name-search :pref="$prefecture" />

            {{-- マップ --}}
            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 mb-8">
                <h2 class="text-sm font-black text-gray-900 mb-3 flex items-center gap-2">
                    <i data-lucide="map" class="w-4 h-4 text-blue-600"></i> {{ $city }}のバイクショップマップ
                </h2>
                <div id="city-map" class="w-full"></div>
            </div>

            {{-- チャート --}}
            @if($makerDistribution->isNotEmpty() || $priceRanges->isNotEmpty())
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-8">
                @if($makerDistribution->isNotEmpty())
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
                    <h3 class="text-sm font-black text-gray-900 mb-3">メーカー別在庫シェア</h3>
                    <div style="height:280px;position:relative">
                        <canvas id="makerDonut"></canvas>
                    </div>
                </div>
                @endif

                @if($priceRanges->isNotEmpty())
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
                    <h3 class="text-sm font-black text-gray-900 mb-3">価格帯別在庫分布</h3>
                    <div style="height:280px;position:relative">
                        <canvas id="priceBar"></canvas>
                    </div>
                </div>
                @endif
            </div>
            @endif

            {{-- ショップカード一覧 --}}
            <section class="mb-8">
                <h2 class="text-base font-black text-gray-900 mb-4 flex items-center gap-2">
                    <i data-lucide="store" class="w-5 h-5 text-blue-500"></i>
                    {{ $city }}のバイクショップ一覧
                </h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($shops as $shop)
                    <a href="{{ route('shops.show', $shop) }}"
                       class="group bg-white rounded-2xl shadow-sm border border-gray-100 hover:border-blue-200 hover:shadow-md transition-all duration-200 hover:-translate-y-0.5 block overflow-hidden">
                        @if($shop->display_image_url)
                        <div class="aspect-[16/9] bg-gray-200 overflow-hidden">
                            <img src="{{ $shop->display_image_url }}" alt="{{ $shop->name }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" loading="lazy"
                                 onerror="this.parentElement.innerHTML='<div class=\'w-full h-full flex items-center justify-center text-gray-300\'><svg class=\'w-8 h-8\' fill=\'none\' stroke=\'currentColor\' viewBox=\'0 0 24 24\'><path stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'1.5\' d=\'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14\'/></svg></div>'">
                        </div>
                        @endif
                        <div class="p-5">
                            <div class="flex items-start justify-between mb-2">
                                <h3 class="text-sm font-black text-gray-900 group-hover:text-blue-700 transition-colors line-clamp-2 flex-1">{{ $shop->name }}</h3>
                                @if($shop->rating > 0)
                                <span class="shrink-0 ml-2 text-sm font-black text-yellow-500 flex items-center gap-0.5">
                                    <i data-lucide="star" class="w-3.5 h-3.5 fill-current"></i>
                                    {{ number_format($shop->rating, 1) }}
                                </span>
                                @endif
                            </div>

                            <p class="text-xs text-gray-400 line-clamp-1 mb-3">{{ $shop->address }}</p>

                            <div class="flex flex-wrap gap-2 mb-3">
                                @if($shop->listings_count > 0)
                                <span class="inline-flex items-center gap-1 bg-green-50 text-green-700 text-[10px] font-bold px-2 py-0.5 rounded-full border border-green-100">
                                    在庫 {{ $shop->listings_count }}台
                                </span>
                                @endif
                                @if($shop->business_hours)
                                <span class="inline-flex items-center gap-1 bg-gray-50 text-gray-600 text-[10px] font-bold px-2 py-0.5 rounded-full border border-gray-100">
                                    {{ \Illuminate\Support\Str::limit($shop->business_hours, 15) }}
                                </span>
                                @endif
                            </div>

                            <div class="flex items-center justify-between text-xs">
                                @if($shop->phone)
                                <span class="font-bold text-gray-600">{{ $shop->phone }}</span>
                                @else
                                <span></span>
                                @endif
                                <span class="text-gray-300 group-hover:text-blue-500 transition-colors">
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
                $faqs = [];
                $faqs[] = [
                    'q' => "{$city}にバイクショップは何店ある？",
                    'a' => "{$city}には{$totalShops}店のバイクショップ・販売店があります。" .
                           ($totalListings > 0 ? "合計{$totalListings}台の在庫バイクを取り扱っています。" : ''),
                ];
                if ($avgPrice) {
                    $faqs[] = [
                        'q' => "{$city}のバイクの平均価格は？",
                        'a' => "{$city}のバイクショップで販売されているバイクの平均価格は約" . number_format((int)($avgPrice / 10000)) . "万円です。",
                    ];
                }
                if ($makerDistribution->isNotEmpty()) {
                    $topMaker = $makerDistribution->first();
                    $faqs[] = [
                        'q' => "{$city}で一番在庫が多いバイクメーカーは？",
                        'a' => "{$city}で最も在庫が多いメーカーは{$topMaker->name}で、{$topMaker->count}台の在庫があります。",
                    ];
                }
            @endphp

            @if(count($faqs) >= 2)
            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8 mb-8" x-data="{ open: null }">
                <h2 class="text-sm font-black text-gray-900 mb-4 flex items-center gap-2">
                    <i data-lucide="help-circle" class="w-4 h-4 text-blue-600"></i> よくある質問
                </h2>
                <div class="divide-y divide-gray-100">
                    @foreach($faqs as $i => $faq)
                    <div class="py-3 first:pt-0 last:pb-0">
                        <button type="button" @click="open = open === {{ $i }} ? null : {{ $i }}"
                            class="w-full flex items-start justify-between gap-3 text-left group">
                            <span class="text-sm font-bold text-gray-800 group-hover:text-blue-600 transition">{{ $faq['q'] }}</span>
                            <i data-lucide="chevron-down" class="w-4 h-4 text-gray-400 shrink-0 mt-0.5 transition-transform duration-200"
                               x-bind:class="{ 'rotate-180': open === {{ $i }} }"></i>
                        </button>
                        <div x-show="open === {{ $i }}" x-transition:enter="transition ease-out duration-200"
                             x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
                             style="display: none;">
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
            @endif

            {{-- 周辺エリア --}}
            @if($siblingCities->isNotEmpty())
            <section class="mb-8">
                <h2 class="text-base font-black text-gray-900 mb-4 flex items-center gap-2">
                    <i data-lucide="compass" class="w-5 h-5 text-blue-500"></i>
                    {{ $prefecture }}の他のエリア
                </h2>
                <div class="flex flex-wrap gap-2">
                    @foreach($siblingCities as $sibling)
                    <a href="{{ route('shops.area.city', [$prefecture, $sibling->city]) }}"
                       class="px-3 py-1.5 rounded-lg bg-white border border-gray-100 text-xs font-bold text-gray-600 hover:bg-blue-50 hover:border-blue-200 hover:text-blue-600 transition-colors">
                        {{ $sibling->city }} ({{ $sibling->count }}店)
                    </a>
                    @endforeach
                </div>
            </section>
            @endif

            {{-- このエリアで売っているバイク --}}
            @if($nearbyListings->isNotEmpty())
            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8 mb-8">
                <h2 class="text-sm font-black text-gray-900 mb-4 flex items-center gap-2">
                    <i data-lucide="bike" class="w-4 h-4 text-blue-600"></i> {{ $city }}で売っているバイク
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

            {{-- 未掲載店の投稿導線（マップ文脈） --}}
            <a href="{{ route('shops.submit.create', ['pref' => $prefecture, 'city' => $city]) }}"
               class="mt-8 flex items-center justify-between bg-emerald-50 border border-emerald-200 rounded-2xl p-5 hover:shadow-md transition-shadow">
                <div class="flex items-center gap-3">
                    <i data-lucide="plus-circle" class="w-6 h-6 text-emerald-600"></i>
                    <div>
                        <p class="text-sm font-black text-gray-800">この地図に載っていないお店を教える</p>
                        <p class="text-xs text-gray-500 font-bold">{{ $city }}の店舗情報を投稿する（承認後に掲載されます）</p>
                    </div>
                </div>
                <i data-lucide="chevron-right" class="w-5 h-5 text-emerald-400"></i>
            </a>
        </div>
    </div>
</x-layout>
