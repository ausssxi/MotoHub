<x-layout>
    <x-slot:title>{{ $display }}｜{{ $prefecture }}{{ $city }}の{{ $label }} - MotoHub</x-slot:title>
    <x-slot:metaDescription>{{ $prefecture }}{{ $city }}の{{ $label }}「{{ $display }}」の場所・設備・営業時間。周辺のガソリンスタンドやコンビニもあわせて確認できます。</x-slot:metaDescription>

    <x-slot:styles>
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
        <style>
            #detail-map { height: 250px; z-index: 10; border-radius: 12px; }
        </style>
    </x-slot:styles>

    <x-slot:scripts>
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const lat = {{ $poi->latitude }};
                const lng = {{ $poi->longitude }};
                const map = L.map('detail-map').setView([lat, lng], 16);
                L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>'
                }).addTo(map);
                L.marker([lat, lng]).addTo(map);
                if (typeof lucide !== 'undefined') lucide.createIcons();
            });
        </script>
    </x-slot:scripts>

    <x-slot:navigation>
        <x-navigation :showSearch="true" />
    </x-slot:navigation>

    <div class="bg-gray-50 min-h-screen py-8">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">

            {{-- パンくず --}}
            <nav class="flex text-xs font-bold text-gray-400 mb-6" aria-label="Breadcrumb">
                <ol class="flex items-center space-x-2 flex-wrap">
                    <li><a href="/" class="hover:text-gray-600 transition-colors">HOME</a></li>
                    <li><span class="text-gray-300">＞</span></li>
                    <li><a href="{{ route($routePrefix.'.index') }}" class="hover:text-gray-600 transition-colors">{{ $label }}</a></li>
                    <li><span class="text-gray-300">＞</span></li>
                    <li><a href="{{ route($routePrefix.'.prefecture', $prefecture) }}" class="hover:text-gray-600 transition-colors">{{ $prefecture }}</a></li>
                    <li><span class="text-gray-300">＞</span></li>
                    <li><a href="{{ route($routePrefix.'.city', [$prefecture, $city]) }}" class="hover:text-gray-600 transition-colors">{{ $city }}</a></li>
                    <li><span class="text-gray-300">＞</span></li>
                    <li><span class="text-gray-800">{{ $display }}</span></li>
                </ol>
            </nav>

            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8">
                <h1 class="text-xl font-black text-gray-900 mb-2">{{ $display }}</h1>

                @php
                    $isSelf = in_array($poi->self_service, ['yes', 'only'], true);
                    $isAutomated = $poi->automated === 'yes';
                @endphp
                @if($isSelf || $isAutomated)
                <div class="flex flex-wrap gap-1 mb-4">
                    @if($isSelf)
                    <span class="inline-flex items-center gap-1 text-[10px] font-bold text-purple-700 bg-purple-50 border border-purple-100 rounded-full px-2 py-0.5"><i data-lucide="hand" class="w-3 h-3"></i>セルフ</span>
                    @endif
                    @if($isAutomated)
                    <span class="inline-flex items-center gap-1 text-[10px] font-bold text-purple-700 bg-purple-50 border border-purple-100 rounded-full px-2 py-0.5"><i data-lucide="droplets" class="w-3 h-3"></i>洗車機</span>
                    @endif
                </div>
                @endif

                {{-- 施設ごとに数字が変わる自動生成の紹介文（所在地・最寄り駅・半径5km以内の件数）。洗車場のみ。 --}}
                @if($routePrefix === 'senshajo' && filled($carWashSummary))
                <p class="text-sm leading-relaxed text-gray-600 mb-4">{{ $carWashSummary }}</p>
                @endif

                @if($routePrefix === 'senshajo')
                <p class="text-[13px] leading-relaxed text-gray-700 bg-purple-50 border border-purple-100 rounded-xl px-4 py-3 mb-4">
                    @if($isSelf && $isAutomated)
                    セルフの洗車スペースがあるため、バイクの手洗いに使える可能性が高い場所です。洗車機も併設されていますが、洗車機は四輪専用のことが多いため、二輪は手洗いスペースをご利用ください。
                    @elseif($isSelf)
                    セルフの洗車スペースがあるため、バイクの手洗いに使える可能性が高い場所です。
                    @elseif($isAutomated)
                    洗車機のみの施設です。洗車機は四輪専用のことが多く、二輪は利用できない場合があります。事前にご確認ください。
                    @else
                    設備の詳細が分かっていない施設です。二輪が利用できるかは、事前にご確認ください。
                    @endif
                </p>
                @endif

                <dl class="text-sm text-gray-700 space-y-1.5 mb-5">
                    @if(filled($poi->address))
                    <div class="flex gap-3"><dt class="text-gray-400 shrink-0 w-16 text-xs pt-0.5">住所</dt><dd>{{ $poi->address }}</dd></div>
                    @endif
                    @if(filled($poi->brand))
                    <div class="flex gap-3"><dt class="text-gray-400 shrink-0 w-16 text-xs pt-0.5">ブランド</dt><dd>{{ $poi->brand }}</dd></div>
                    @endif
                    @if(filled($poi->opening_hours))
                    <div class="flex gap-3"><dt class="text-gray-400 shrink-0 w-16 text-xs pt-0.5">営業時間</dt><dd>{{ $poi->opening_hours }}</dd></div>
                    @endif
                </dl>

                <div id="detail-map" class="mb-3"></div>

                <a href="https://www.google.com/maps/dir/?api=1&destination={{ $poi->latitude }},{{ $poi->longitude }}"
                   target="_blank" rel="noopener"
                   class="inline-flex items-center gap-1 text-xs font-bold text-purple-700 hover:underline">
                    <i data-lucide="navigation" class="w-3.5 h-3.5"></i>Googleマップで経路を見る
                </a>
            </div>

            {{-- 洗車場のみ: 周辺のバイク関連施設（半径10km・各3件）と最寄り駅。バイク乗りの回遊導線を優先し、GS・コンビニより上に出す。 --}}
            @if($routePrefix === 'senshajo' && (!empty($nearbyShops) || !empty($nearbyParkings) || !empty($nearbyGarages) || $nearestStation))
            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8 mt-6">
                <h2 class="text-sm font-black text-gray-900 mb-3">周辺のバイク関連施設（直線距離）</h2>

                @if($nearestStation)
                <p class="text-xs text-gray-500 mb-4">
                    <i data-lucide="train-front" class="inline w-3.5 h-3.5"></i>
                    最寄り駅: <span class="font-bold text-gray-700">{{ $nearestStation['name'] }}</span>
                    {{-- 0.1km未満は距離ではなく「同じ敷地内」（表示ルール） --}}
                    <span class="text-gray-400">{{ $nearestStation['km'] < 0.1 ? '同じ敷地内' : '約'.number_format($nearestStation['km'], 1).'km' }}</span>
                </p>
                @endif

                {{-- 3カテゴリを同じ体裁で描く。それぞれリンク先ルートが違うので配列で対応付けて重複を避ける。 --}}
                @foreach([
                    ['label' => 'バイクショップ', 'items' => $nearbyShops, 'route' => 'shops.show'],
                    ['label' => 'バイク駐車場', 'items' => $nearbyParkings, 'route' => 'parking.show'],
                    ['label' => 'レンタルガレージ', 'items' => $nearbyGarages, 'route' => 'rental-garage.show'],
                ] as $group)
                    @if(!empty($group['items']))
                    <div class="mb-4 last:mb-0">
                        <h3 class="text-xs font-bold text-gray-400 mb-1.5">{{ $group['label'] }}</h3>
                        <ul class="divide-y divide-gray-50">
                            @foreach($group['items'] as $f)
                            <li class="py-2">
                                <a href="{{ route($group['route'], $f['id']) }}" class="text-sm text-purple-700 font-bold hover:underline">{{ $f['name'] }}</a>
                                <span class="text-[11px] text-gray-400 ml-1">{{ $f['km'] < 0.1 ? '同じ敷地内' : '約'.number_format($f['km'], 1).'km' }}</span>
                            </li>
                            @endforeach
                        </ul>
                    </div>
                    @endif
                @endforeach
            </div>
            @endif

            @if(!empty($nearbyGas))
            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8 mt-6">
                <h2 class="text-sm font-black text-gray-900 mb-3">近くのガソリンスタンド（直線距離）</h2>
                <ul class="divide-y divide-gray-50">
                    @foreach($nearbyGas as $n)
                    <li class="py-2.5">
                        <a href="{{ route('gs.city', [$n['prefecture'], $n['city']]) }}" class="text-sm text-purple-700 font-bold hover:underline">{{ $n['display'] }}</a>
                        <span class="text-[11px] text-gray-400 ml-1">{{ $n['km'] < 0.1 ? '同じ敷地内' : '約'.number_format($n['km'], 1).'km' }}</span>
                        @if($n['address'])
                        <p class="text-[11px] text-gray-500 mt-0.5"><i data-lucide="map-pin" class="inline w-3 h-3"></i> {{ $n['address'] }}</p>
                        @endif
                    </li>
                    @endforeach
                </ul>
            </div>
            @endif

            @if(!empty($nearbyStore))
            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8 mt-6">
                <h2 class="text-sm font-black text-gray-900 mb-3">近くのコンビニ（直線距離）</h2>
                <ul class="divide-y divide-gray-50">
                    @foreach($nearbyStore as $n)
                    <li class="py-2.5">
                        <a href="{{ route('konbini.city', [$n['prefecture'], $n['city']]) }}" class="text-sm text-purple-700 font-bold hover:underline">{{ $n['display'] }}</a>
                        <span class="text-[11px] text-gray-400 ml-1">{{ $n['km'] < 0.1 ? '同じ敷地内' : '約'.number_format($n['km'], 1).'km' }}</span>
                        @if($n['address'])
                        <p class="text-[11px] text-gray-500 mt-0.5"><i data-lucide="map-pin" class="inline w-3 h-3"></i> {{ $n['address'] }}</p>
                        @endif
                    </li>
                    @endforeach
                </ul>
            </div>
            @endif

            @if(!empty($sameCity))
            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8 mt-6">
                <h2 class="text-sm font-black text-gray-900 mb-3">{{ $city }}の他の{{ $label }}</h2>
                <ul class="divide-y divide-gray-50">
                    @foreach($sameCity as $s)
                    <li class="py-2.5">
                        <a href="{{ route($routePrefix.'.show', [$prefecture, $city, $s['id']]) }}" class="text-sm text-purple-700 font-bold hover:underline">{{ $s['display'] }}</a>
                        @if($s['address'])
                        <p class="text-[11px] text-gray-500 mt-0.5"><i data-lucide="map-pin" class="inline w-3 h-3"></i> {{ $s['address'] }}</p>
                        @endif
                    </li>
                    @endforeach
                </ul>
                <p class="mt-4">
                    <a href="{{ route($routePrefix.'.city', [$prefecture, $city]) }}" class="text-xs font-bold text-purple-700 hover:underline">{{ $prefecture }}{{ $city }}の{{ $label }}をすべて見る</a>
                </p>
            </div>
            @endif

            <div class="mt-6">
                <x-cross-links :crossLinks="$crossLinks" />
            </div>

            <div class="mt-6 text-[11px] text-gray-400 leading-relaxed">
                <p>施設情報: © OpenStreetMap contributors</p>
                <p>行政区域: 国土数値情報（行政区域データ）国土交通省</p>
            </div>
        </div>
    </div>

    @php
        $ldFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
        $ldType = ['senshajo' => 'AutoWash', 'gs' => 'GasStation', 'konbini' => 'ConvenienceStore'][$routePrefix] ?? 'LocalBusiness';
        $ldPlace = [
            '@context' => 'https://schema.org',
            '@type' => $ldType,
            'name' => $display,
            'url' => url()->current(),
            'address' => [
                '@type' => 'PostalAddress',
                'addressCountry' => 'JP',
                'addressRegion' => $prefecture,
                'addressLocality' => $city,
                'streetAddress' => trim(str_replace([$prefecture, $city], '', (string) $poi->address)),
            ],
            'geo' => [
                '@type' => 'GeoCoordinates',
                'latitude' => (float) $poi->latitude,
                'longitude' => (float) $poi->longitude,
            ],
        ];
        $ldBreadcrumb = [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'HOME', 'item' => url('/')],
                ['@type' => 'ListItem', 'position' => 2, 'name' => $label, 'item' => route($routePrefix.'.index')],
                ['@type' => 'ListItem', 'position' => 3, 'name' => $prefecture, 'item' => route($routePrefix.'.prefecture', $prefecture)],
                ['@type' => 'ListItem', 'position' => 4, 'name' => $city, 'item' => route($routePrefix.'.city', [$prefecture, $city])],
                ['@type' => 'ListItem', 'position' => 5, 'name' => $display, 'item' => url()->current()],
            ],
        ];
    @endphp
    <script type="application/ld+json">{!! json_encode($ldPlace, $ldFlags) !!}</script>
    <script type="application/ld+json">{!! json_encode($ldBreadcrumb, $ldFlags) !!}</script>
</x-layout>
