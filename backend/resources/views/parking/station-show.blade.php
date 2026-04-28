<x-layout>
    <x-slot:title>{{ $station->name }}駅周辺のバイク駐車場・駐輪場（{{ $totalCount }}件） | MotoHub</x-slot:title>
    <x-slot:metaDescription>{{ $station->name }}駅（{{ $station->prefecture }}）周辺のバイク駐車場・駐輪場を{{ $totalCount }}件掲載。@if($priceStats['avg_per_hour'])時間料金の相場は平均{{ $priceStats['avg_per_hour'] }}円/時。@endif{{ $station->line_names ? $station->line_names . '沿線。' : '' }}料金・設備・レビューで比較できます。</x-slot:metaDescription>

    @if(isset($canonicalUrl))
    <x-slot:canonical>{{ $canonicalUrl }}</x-slot:canonical>
    @endif

    <x-slot:styles>
        <x-jsonld.breadcrumb-station :station="$station" />
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
        <style>
            #station-map { height: 350px; z-index: 10; border-radius: 12px; }
        </style>
    </x-slot:styles>

    <x-slot:scripts>
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
        @php
            $parkingMarkers = $parkings->map(fn($p) => [
                'lat' => $p->latitude,
                'lng' => $p->longitude,
                'name' => $p->name,
                'id' => $p->id,
            ])->values();
        @endphp
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const parkings = {!! json_encode($parkingMarkers, JSON_UNESCAPED_UNICODE) !!};
                const stationLat = {{ $station->latitude }};
                const stationLng = {{ $station->longitude }};
                const map = L.map('station-map').setView([stationLat, stationLng], 15);
                L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>'
                }).addTo(map);

                // 駅マーカー（赤）
                const stationIcon = L.divIcon({
                    html: '<div style="background:#ef4444;width:14px;height:14px;border-radius:50%;border:3px solid white;box-shadow:0 2px 4px rgba(0,0,0,.3)"></div>',
                    iconSize: [14, 14],
                    iconAnchor: [7, 7],
                    className: ''
                });
                L.marker([stationLat, stationLng], { icon: stationIcon })
                    .addTo(map)
                    .bindPopup('<span class="font-bold text-sm">{{ $station->name }}駅</span>');

                // 半径500m円
                L.circle([stationLat, stationLng], {
                    radius: 500,
                    color: '#22c55e',
                    fillColor: '#22c55e',
                    fillOpacity: 0.06,
                    weight: 1.5,
                    dashArray: '6 4'
                }).addTo(map);

                // 駐車場マーカー
                const bounds = [[stationLat, stationLng]];
                parkings.forEach(p => {
                    if (p.lat && p.lng) {
                        L.marker([p.lat, p.lng]).addTo(map)
                            .bindPopup(`<a href="/parking/${p.id}" class="font-bold text-sm">${p.name}</a>`);
                        bounds.push([p.lat, p.lng]);
                    }
                });
                if (bounds.length > 1) {
                    map.fitBounds(bounds, { padding: [40, 40] });
                }
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
                    @if(isset($breadcrumb))
                        @foreach($breadcrumb as $crumb)
                            @if(!$loop->first)
                            <li><span class="text-gray-300">＞</span></li>
                            @endif
                            @if($crumb['url'])
                            <li><a href="{{ $crumb['url'] }}" class="hover:text-gray-600 transition-colors">{{ $crumb['label'] }}</a></li>
                            @else
                            <li><span class="text-gray-800">{{ $crumb['label'] }}</span></li>
                            @endif
                        @endforeach
                    @else
                        <li><a href="/" class="hover:text-gray-600 transition-colors">HOME</a></li>
                        <li><span class="text-gray-300">＞</span></li>
                        <li><a href="{{ route('parking.index') }}" class="hover:text-gray-600 transition-colors">駐車場マップ</a></li>
                        <li><span class="text-gray-300">＞</span></li>
                        <li><a href="{{ route('parking.station.index') }}" class="hover:text-gray-600 transition-colors">駅から探す</a></li>
                        <li><span class="text-gray-300">＞</span></li>
                        <li><span class="text-gray-800">{{ $station->name }}駅</span></li>
                    @endif
                </ol>
            </nav>

            {{-- ヘッダー --}}
            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8 mb-8">
                <h1 class="text-2xl sm:text-3xl font-black text-gray-900 mb-2">
                    {{ $station->name }}駅周辺のバイク駐車場・駐輪場
                </h1>
                <p class="text-sm text-gray-500 mb-4">
                    {{ $station->prefecture }}
                    @if($station->line_names)
                        ・{{ $station->line_names }}
                    @endif
                </p>
                <div class="flex flex-wrap gap-3">
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
                    <div class="flex items-center gap-2 bg-gray-50 text-gray-500 text-sm font-bold px-4 py-2 rounded-xl border border-gray-100">
                        <i data-lucide="navigation" class="w-4 h-4"></i>
                        徒歩約6分圏内（500m）
                    </div>
                </div>
                <div class="mt-4">
                    <a href="{{ route('parking.index', ['lat' => $station->latitude, 'lng' => $station->longitude]) }}"
                       class="inline-flex items-center gap-2 text-xs font-bold text-green-600 hover:text-green-700 transition-colors">
                        <i data-lucide="map" class="w-4 h-4"></i>
                        {{ $station->name }}駅周辺をマップで見る
                    </a>
                </div>
            </div>

            {{-- 料金相場 --}}
            @if($priceStats['avg_per_hour'] || $priceStats['avg_per_month'])
            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8 mb-8">
                <h2 class="text-base font-black text-gray-900 mb-4 flex items-center gap-2">
                    <i data-lucide="coins" class="w-5 h-5 text-green-500"></i>
                    {{ $station->name }}駅周辺の駐車料金相場
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

            {{-- AI生成テキスト --}}
            @if($station->ai_parking_content)
            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8 mb-8">
                <h2 class="text-base font-black text-gray-900 mb-3 flex items-center gap-2">
                    <i data-lucide="file-text" class="w-5 h-5 text-green-500"></i>
                    {{ $station->name }}駅のバイク駐車場事情
                </h2>
                <p class="text-sm text-gray-700 leading-relaxed">{{ $station->ai_parking_content }}</p>
            </div>
            @endif

            {{-- 駐車場比較表 --}}
            @if(count($comparisonTable) >= 2)
            @php $showMore = count($comparisonTable) > 5; $extraCount = count($comparisonTable) - 5; @endphp
            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8 mb-8" x-data="{ expanded: false }">
                <h2 class="text-base font-black text-gray-900 mb-4 flex items-center gap-2">
                    <i data-lucide="table" class="w-5 h-5 text-green-500"></i>
                    駐車場比較表
                </h2>

                {{-- デスクトップ: テーブル --}}
                <div class="hidden sm:block overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="border-b-2 border-gray-100">
                                <th class="text-left font-black text-gray-500 py-2 pr-3">駐車場名</th>
                                <th class="text-right font-black text-gray-500 py-2 px-2">時間料金</th>
                                <th class="text-right font-black text-gray-500 py-2 px-2">日額</th>
                                <th class="text-right font-black text-gray-500 py-2 px-2">月極</th>
                                <th class="text-center font-black text-gray-500 py-2 px-2">24h</th>
                                <th class="text-center font-black text-gray-500 py-2 px-2">屋根</th>
                                <th class="text-right font-black text-gray-500 py-2 px-2">距離</th>
                                <th class="text-right font-black text-gray-500 py-2 pl-2">台数</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @foreach($comparisonTable as $row)
                            <tr class="hover:bg-gray-50 transition-colors" {!! $loop->index >= 5 && $showMore ? 'x-show="expanded" x-transition' : '' !!}>
                                <td class="py-2.5 pr-3">
                                    <a href="{{ route('parking.show', $row['id']) }}" class="font-bold text-green-700 hover:underline">{{ \Illuminate\Support\Str::limit($row['name'], 20) }}</a>
                                </td>
                                <td class="text-right py-2.5 px-2 font-bold {{ $row['is_free'] ? 'text-green-600' : 'text-gray-800' }}">
                                    {{ $row['is_free'] ? '無料' : ($row['price_per_hour'] ? number_format($row['price_per_hour']) . '円' : '-') }}
                                </td>
                                <td class="text-right py-2.5 px-2 text-gray-600">{{ $row['price_per_day'] ? number_format($row['price_per_day']) . '円' : '-' }}</td>
                                <td class="text-right py-2.5 px-2 text-gray-600">{{ $row['price_per_month'] ? number_format($row['price_per_month']) . '円' : '-' }}</td>
                                <td class="text-center py-2.5 px-2">{{ $row['available_24h'] ? '✅' : '❌' }}</td>
                                <td class="text-center py-2.5 px-2">{{ $row['is_covered'] ? '✅' : '❌' }}</td>
                                <td class="text-right py-2.5 px-2 text-gray-600">{{ $row['distance_m'] !== null ? $row['distance_m'] . 'm' : '-' }}</td>
                                <td class="text-right py-2.5 pl-2 text-gray-600">{{ $row['capacity'] ? $row['capacity'] . '台' : '-' }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- モバイル: カード --}}
                <div class="sm:hidden space-y-3">
                    @foreach($comparisonTable as $row)
                    <a href="{{ route('parking.show', $row['id']) }}"
                       class="block bg-gray-50 rounded-xl p-4 hover:bg-green-50 transition-colors"
                       {!! $loop->index >= 5 && $showMore ? 'x-show="expanded" x-transition' : '' !!}>
                        <div class="font-bold text-sm text-green-700 mb-2">{{ $row['name'] }}</div>
                        <div class="grid grid-cols-2 gap-x-4 gap-y-1 text-xs">
                            <div class="text-gray-500">時間料金</div>
                            <div class="text-right font-bold {{ $row['is_free'] ? 'text-green-600' : 'text-gray-800' }}">
                                {{ $row['is_free'] ? '無料' : ($row['price_per_hour'] ? number_format($row['price_per_hour']) . '円/時' : '-') }}
                            </div>
                            @if($row['price_per_day'])
                            <div class="text-gray-500">日額</div>
                            <div class="text-right text-gray-700">{{ number_format($row['price_per_day']) }}円</div>
                            @endif
                            @if($row['price_per_month'])
                            <div class="text-gray-500">月極</div>
                            <div class="text-right text-gray-700">{{ number_format($row['price_per_month']) }}円</div>
                            @endif
                            <div class="text-gray-500">24h / 屋根</div>
                            <div class="text-right">{{ $row['available_24h'] ? '✅' : '❌' }} / {{ $row['is_covered'] ? '✅' : '❌' }}</div>
                            @if($row['distance_m'] !== null)
                            <div class="text-gray-500">駅からの距離</div>
                            <div class="text-right text-gray-700">{{ $row['distance_m'] }}m</div>
                            @endif
                            @if($row['capacity'])
                            <div class="text-gray-500">収容台数</div>
                            <div class="text-right text-gray-700">{{ $row['capacity'] }}台</div>
                            @endif
                        </div>
                    </a>
                    @endforeach
                </div>

                {{-- もっと見る / 閉じる ボタン --}}
                @if($showMore)
                <div class="mt-4 flex justify-center">
                    <button @click="expanded = !expanded" type="button"
                            class="inline-flex items-center justify-center text-center gap-1.5 text-xs font-bold text-green-600 hover:text-green-700 transition-colors px-4 py-2 rounded-lg hover:bg-green-50">
                        <span x-show="!expanded" class="inline-flex items-center gap-1.5">
                            <i data-lucide="chevron-down" class="w-3.5 h-3.5"></i>
                            もっと見る（残り{{ $extraCount }}件）
                        </span>
                        <span x-show="expanded" x-cloak class="inline-flex items-center gap-1.5">
                            <i data-lucide="chevron-up" class="w-3.5 h-3.5"></i>
                            閉じる
                        </span>
                    </button>
                </div>
                @endif
            </div>
            @endif

            {{-- マップ --}}
            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 mb-8">
                <h2 class="text-sm font-black text-gray-900 mb-3 flex items-center gap-2">
                    <i data-lucide="map" class="w-4 h-4 text-green-600"></i> {{ $station->name }}駅周辺の駐車場マップ
                </h2>
                <div id="station-map" class="w-full"></div>
                <p class="text-[10px] text-gray-400 mt-2">赤い点: {{ $station->name }}駅 / 緑の円: 徒歩圏内（約500m）</p>
            </div>

            {{-- 駐車場カード一覧 --}}
            @if($parkings->isNotEmpty())
            <section class="mb-8">
                <h2 class="text-base font-black text-gray-900 mb-4 flex items-center gap-2">
                    <i data-lucide="square-parking" class="w-5 h-5 text-green-500"></i>
                    {{ $station->name }}駅周辺の駐車場一覧
                </h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($parkings as $parking)
                    <a href="{{ route('parking.show', $parking) }}"
                       class="group bg-white rounded-2xl shadow-sm border border-gray-100 hover:border-green-200 hover:shadow-md p-5 transition-all duration-200 hover:-translate-y-0.5 block">
                        <div class="flex items-start justify-between mb-2">
                            <h3 class="text-sm font-black text-gray-900 group-hover:text-green-700 transition-colors line-clamp-2 flex-1">{{ $parking->name }}</h3>
                            @if($parking->avg_rating > 0)
                            <span class="shrink-0 ml-2 text-sm font-black text-yellow-500 flex items-center gap-0.5">
                                <i data-lucide="star" class="w-3.5 h-3.5 fill-current"></i>
                                {{ number_format($parking->avg_rating, 1) }}
                            </span>
                            @endif
                        </div>

                        <p class="text-xs text-gray-400 line-clamp-1 mb-2">{{ $parking->address }}</p>

                        @if(isset($parking->distance))
                        <p class="text-xs font-bold text-green-600 mb-2">
                            <i data-lucide="navigation" class="w-3 h-3 inline"></i>
                            駅から{{ number_format($parking->distance * 1000) }}m
                        </p>
                        @endif

                        <div class="flex flex-wrap gap-2 mb-3">
                            <span class="inline-flex items-center gap-1 bg-green-50 text-green-700 text-[10px] font-bold px-2 py-0.5 rounded-full border border-green-100">
                                {{ $parking->getParkingTypeLabel() }}
                            </span>
                            @if($parking->is_free)
                            <span class="inline-flex items-center gap-1 bg-yellow-50 text-yellow-700 text-[10px] font-bold px-2 py-0.5 rounded-full border border-yellow-100">
                                無料
                            </span>
                            @endif
                            @if($parking->is_covered)
                            <span class="inline-flex items-center gap-1 bg-gray-50 text-gray-600 text-[10px] font-bold px-2 py-0.5 rounded-full border border-gray-100">
                                屋根あり
                            </span>
                            @endif
                            @if($parking->available_24h)
                            <span class="inline-flex items-center gap-1 bg-gray-50 text-gray-600 text-[10px] font-bold px-2 py-0.5 rounded-full border border-gray-100">
                                24h
                            </span>
                            @endif
                        </div>

                        <div class="flex items-center justify-between text-xs">
                            <span class="font-bold text-gray-600">{{ $parking->getPriceDisplay() }}</span>
                            <span class="text-gray-300 group-hover:text-green-500 transition-colors">
                                <i data-lucide="chevron-right" class="w-4 h-4"></i>
                            </span>
                        </div>
                    </a>
                    @endforeach
                </div>
            </section>
            @else
            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-8 mb-8 text-center">
                <i data-lucide="search-x" class="w-10 h-10 text-gray-300 mx-auto mb-3"></i>
                <p class="text-sm font-bold text-gray-500">{{ $station->name }}駅周辺（500m以内）にバイク駐車場が見つかりませんでした</p>
                <a href="{{ route('parking.index', ['lat' => $station->latitude, 'lng' => $station->longitude]) }}"
                   class="inline-flex items-center gap-1 text-xs font-bold text-green-600 hover:text-green-700 mt-3 transition-colors">
                    マップで範囲を広げて探す <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                </a>
            </div>
            @endif

            {{-- FAQ --}}
            @php
                $faqs = [];
                if ($priceStats['avg_per_hour']) {
                    $faqs[] = [
                        'q' => "{$station->name}駅周辺のバイク駐車場の料金相場は？",
                        'a' => "{$station->name}駅周辺のバイク駐車場の時間料金は平均{$priceStats['avg_per_hour']}円/時です。" .
                               ($priceStats['min_per_hour'] ? "最安は{$priceStats['min_per_hour']}円/時からご利用いただけます。" : ''),
                    ];
                }
                $faqs[] = [
                    'q' => "{$station->name}駅周辺にバイク駐車場は何件ある？",
                    'a' => "{$station->name}駅（{$station->prefecture}）周辺500m以内に{$totalCount}件のバイク駐車場・駐輪場があります。" .
                           ($freeCount > 0 ? "うち{$freeCount}件は無料で利用できます。" : ''),
                ];
                if ($priceStats['monthly_count'] > 0) {
                    $monthlyAnswer = "{$station->name}駅周辺の月極バイク駐車場は{$priceStats['monthly_count']}件あり、月額" . number_format($priceStats['min_per_month']) . "円〜" . number_format($priceStats['max_per_month']) . "円です。";
                    $monthlyNames = collect($priceStats['monthly_parkings'] ?? []);
                    if ($monthlyNames->isNotEmpty()) {
                        $monthlyAnswer .= '（例：' . $monthlyNames->map(fn ($p) => $p['name'] . ' ' . number_format($p['price']) . '円/月')->implode('、') . '）';
                    }
                    $faqs[] = [
                        'q' => "{$station->name}駅周辺でバイクの月極駐車場はある？",
                        'a' => $monthlyAnswer,
                    ];
                }
                if ($station->line_names) {
                    $faqs[] = [
                        'q' => "{$station->name}駅は何線が通っている？",
                        'a' => "{$station->name}駅には{$station->line_names}が乗り入れています。",
                    ];
                }
                // 大型バイク対応
                if ($largeBikeCount > 0) {
                    $faqs[] = [
                        'q' => "{$station->name}駅周辺に大型バイク(400cc以上)が停められる駐輪場はありますか？",
                        'a' => "はい、{$station->name}駅周辺には大型バイク(400cc以上)に対応した駐車場が{$largeBikeCount}件あります。車両制限の詳細は各駐車場の情報をご確認ください。",
                    ];
                } else {
                    $faqs[] = [
                        'q' => "{$station->name}駅周辺に大型バイク(400cc以上)が停められる駐輪場はありますか？",
                        'a' => "{$station->name}駅周辺で大型バイク(400cc以上)に対応した駐車場の情報は現在確認が必要です。各駐車場に直接お問い合わせください。",
                    ];
                }
                // 125cc〜250cc対応
                if ($bikeOnlyCount + $bicycleSharedCount > 0) {
                    $midAnswer = "バイク専用の駐車場が{$bikeOnlyCount}件あり、概ね250ccまで対応しています。";
                    if ($bicycleSharedCount > 0) {
                        $midAnswer .= "自転車共用{$bicycleSharedCount}件は原付のみの場合があるため、事前にご確認ください。";
                    }
                    $faqs[] = [
                        'q' => "{$station->name}駅周辺に125cc〜250ccのバイクが停められる駐輪場はありますか？",
                        'a' => $midAnswer,
                    ];
                }
                // 24時間利用
                if ($available24hCount > 0) {
                    $faqs[] = [
                        'q' => "{$station->name}駅周辺に24時間利用できるバイク駐輪場はありますか？",
                        'a' => "はい、{$station->name}駅周辺には24時間利用可能なバイク駐車場が{$available24hCount}件あります。",
                    ];
                }
                // 屋根付き
                if ($coveredCount > 0) {
                    $faqs[] = [
                        'q' => "{$station->name}駅周辺に屋根付きのバイク駐輪場はありますか？",
                        'a' => "はい、{$station->name}駅周辺には屋根付き（雨天対応）のバイク駐車場が{$coveredCount}件あります。",
                    ];
                }
            @endphp

            @if(count($faqs) >= 2)
            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8 mb-8" x-data="{ open: null }">
                <h2 class="text-sm font-black text-gray-900 mb-4 flex items-center gap-2">
                    <i data-lucide="help-circle" class="w-4 h-4 text-green-600"></i> よくある質問
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

            {{-- 同じ都道府県の主要駅 --}}
            @if($siblingStations->isNotEmpty())
            <section class="mb-8">
                <h2 class="text-base font-black text-gray-900 mb-4 flex items-center gap-2">
                    <i data-lucide="train-front" class="w-5 h-5 text-green-500"></i>
                    {{ $station->prefecture }}の他の駅
                </h2>
                <div class="flex flex-wrap gap-2">
                    @foreach($siblingStations as $sibling)
                    <a href="{{ route('parking.station.show', $sibling) }}"
                       class="px-3 py-1.5 rounded-lg bg-white border border-gray-100 text-xs font-bold text-gray-600 hover:bg-green-50 hover:border-green-200 hover:text-green-600 transition-colors">
                        {{ $sibling->name }}駅 ({{ $sibling->bike_parkings_count }}件)
                    </a>
                    @endforeach
                </div>
            </section>
            @endif

            {{-- このエリアで売っているバイク --}}
            @if($nearbyListings->isNotEmpty())
            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8 mb-8">
                <h2 class="text-sm font-black text-gray-900 mb-4 flex items-center gap-2">
                    <i data-lucide="bike" class="w-4 h-4 text-green-600"></i> {{ $station->prefecture }}で売っているバイク
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
                    <a href="{{ route('bikes.search', ['prefecture' => mb_substr($station->prefecture, 0, -1)]) }}"
                       class="inline-flex items-center gap-1 text-xs font-bold text-blue-600 hover:text-blue-700 transition">
                        {{ $station->prefecture }}の中古バイク一覧を見る
                        <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                    </a>
                </div>
            </div>
            @endif

            {{-- バイク検索クロスリンク --}}
            <div class="mt-8 bg-blue-50 rounded-2xl p-5 border border-blue-100 flex items-center justify-between hover:shadow-md transition-shadow">
                <div class="flex items-center gap-3">
                    <span class="text-2xl">🏍️</span>
                    <span class="text-sm font-black text-gray-800">{{ $station->prefecture }}の中古バイクを探す</span>
                </div>
                <a href="{{ route('bikes.search', ['prefecture' => $station->prefecture]) }}" class="text-xs font-bold text-blue-600 hover:text-blue-800 flex items-center gap-1">
                    検索する <i data-lucide="arrow-right" class="w-4 h-4"></i>
                </a>
            </div>

            {{-- 回遊リンク --}}
            <div class="mt-8">
                <x-cross-links :crossLinks="$crossLinks" />
            </div>

            {{-- データ出典 --}}
            <p class="text-[10px] text-gray-300 mt-6 text-center">駅データ: 国土数値情報（鉄道データ）国土交通省</p>
        </div>
    </div>
</x-layout>
