<x-layout>
    <x-slot:title>{{ $prefecture }}のバイクショップ一覧（{{ $totalShops }}店） | MotoHub</x-slot:title>
    <x-slot:metaDescription>{{ $prefecture }}のバイクショップ・販売店を{{ $totalShops }}店掲載。@if($totalListings > 0)在庫{{ number_format($totalListings) }}台。@endif店舗情報・在庫・評価で比較できます。</x-slot:metaDescription>

    <x-slot:styles>
        <x-jsonld.breadcrumb-shop-area :prefecture="$prefecture" />
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

        @if($makerDistribution->isNotEmpty())
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
            <nav class="flex text-xs font-bold text-gray-400 mb-6" aria-label="Breadcrumb">
                <ol class="flex items-center space-x-2">
                    <li><a href="/" class="hover:text-gray-600 transition-colors">HOME</a></li>
                    <li><span class="text-gray-300">＞</span></li>
                    <li><a href="{{ route('shops.map') }}" class="hover:text-gray-600 transition-colors">ショップマップ</a></li>
                    <li><span class="text-gray-300">＞</span></li>
                    <li><a href="{{ route('shops.area.index') }}" class="hover:text-gray-600 transition-colors">エリアから探す</a></li>
                    <li><span class="text-gray-300">＞</span></li>
                    <li><span class="text-gray-800">{{ $prefecture }}</span></li>
                </ol>
            </nav>

            {{-- ヘッダー --}}
            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8 mb-8">
                <h1 class="text-2xl sm:text-3xl font-black text-gray-900 mb-4">
                    {{ $prefecture }}のバイクショップ一覧
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
                <div class="mt-4">
                    <a href="{{ route('shops.map') }}"
                       class="inline-flex items-center gap-2 text-xs font-bold text-blue-600 hover:text-blue-700 transition-colors">
                        <i data-lucide="map" class="w-4 h-4"></i>
                        {{ $prefecture }}のショップをマップで見る
                    </a>
                </div>
            </div>

            {{-- 店名で探す（県内プリフィル） --}}
            <x-shop-name-search :pref="$prefecture" />

            {{-- マップ --}}
            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 mb-8">
                <h2 class="text-sm font-black text-gray-900 mb-3 flex items-center gap-2">
                    <i data-lucide="map" class="w-4 h-4 text-blue-600"></i> {{ $prefecture }}のバイクショップマップ
                </h2>
                <div id="area-map" class="w-full"></div>
            </div>

            {{-- メーカー分布チャート --}}
            @if($makerDistribution->isNotEmpty())
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 mb-8">
                <h2 class="text-sm font-black text-gray-900 mb-3">{{ $prefecture }}のメーカー別在庫シェア</h2>
                <div style="height:280px;position:relative">
                    <canvas id="makerDonut"></canvas>
                </div>
            </div>
            @endif

            {{-- 市区町村カード --}}
            <section class="mb-8">
                <h2 class="text-base font-black text-gray-900 mb-4 flex items-center gap-2">
                    <i data-lucide="map-pin" class="w-5 h-5 text-blue-500"></i>
                    {{ $prefecture }}の市区町村から探す
                </h2>
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                    @foreach($cities as $cityData)
                    <a href="{{ route('shops.area.city', [$prefecture, $cityData['name']]) }}"
                       class="bg-white rounded-xl border border-gray-100 p-4 hover:border-blue-200 hover:shadow-md transition-all duration-200 hover:-translate-y-0.5 block">
                        <div class="text-sm font-black text-gray-800 mb-1">{{ $cityData['name'] ?: '（未設定）' }}</div>
                        <div class="flex items-center gap-2 text-xs">
                            <span class="font-bold text-blue-600">{{ $cityData['count'] }}店</span>
                        </div>
                    </a>
                    @endforeach
                </div>
            </section>

            {{-- このエリアで売っているバイク --}}
            @if($nearbyListings->isNotEmpty())
            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8 mb-8">
                <h2 class="text-sm font-black text-gray-900 mb-4 flex items-center gap-2">
                    <i data-lucide="bike" class="w-4 h-4 text-blue-600"></i> {{ $prefecture }}で売っているバイク
                </h2>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                    @foreach($nearbyListings as $nl)
                    @php
                        $img = null;
                        if ($nl->local_image_paths && is_array($nl->local_image_paths) && count($nl->local_image_paths) > 0) {
                            $img = asset('storage/' . $nl->local_image_paths[0]);
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

            {{-- 未掲載店の投稿導線 --}}
            <a href="{{ route('shops.submit.create', ['pref' => $prefecture]) }}"
               class="mt-8 flex items-center justify-between bg-emerald-50 border border-emerald-200 rounded-2xl p-5 hover:shadow-md transition-shadow">
                <div class="flex items-center gap-3">
                    <i data-lucide="plus-circle" class="w-6 h-6 text-emerald-600"></i>
                    <div>
                        <p class="text-sm font-black text-gray-800">{{ $prefecture }}で掲載されていないバイクショップをご存知ですか？</p>
                        <p class="text-xs text-gray-500 font-bold">店舗情報を投稿する（承認後に掲載されます）</p>
                    </div>
                </div>
                <i data-lucide="chevron-right" class="w-5 h-5 text-emerald-400"></i>
            </a>
        </div>
    </div>
</x-layout>
