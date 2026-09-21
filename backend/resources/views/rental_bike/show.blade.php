<x-layout>
    @php
        $areaName = $shop->city ?: $shop->prefecture;
        $titleArea = ($shop->prefecture ?? '').($shop->city ?? '');

        // 最寄り駅（近い順ソート済み→ first が最寄り）。駅名が「駅」で終わらなければ「駅」を補う。
        $nearestStation = $nearbyStations->first();
        $stationLabel = null;
        if ($nearestStation) {
            $n = (string) $nearestStation->name;
            $stationLabel = \Illuminate\Support\Str::endsWith($n, '駅') ? $n : $n.'駅';
        }

        // 自動生成の説明文。事実だけを順に連結する。★評価語（安心・便利・おすすめ等）は入れない。
        $autoSentences = [];
        $pc = ($shop->prefecture ?? '').($shop->city ?? '');
        // 1) 概要（name / company は常に存在）
        $autoSentences[] = $shop->name.'は、'.$pc.'にある'.$shop->company.'の店舗です。';
        // 2) 最寄り駅（座標から算出。あるときだけ）
        if ($stationLabel) {
            $autoSentences[] = '最寄りの'.$stationLabel.'から約'.round($nearestStation->dist_m / 1000, 1).'km。';
        }
        // 3) 周辺のレンタルガレージ件数（内部リンクの裏づけ。0件のときは出さない）
        if ($nearbyGarages->isNotEmpty()) {
            $autoSentences[] = '周辺にレンタルガレージが'.$nearbyGarages->count().'件あります。';
        }
        $autoBody = implode('', $autoSentences);

        // パンくずの JSON-LD（画面パンくずと同じ階層）。URL は全て route() 生成。
        $crumbs = [
            ['name' => 'HOME', 'item' => url('/')],
            ['name' => 'レンタルバイク', 'item' => route('rental-bike.index')],
        ];
        if (filled($shop->prefecture)) {
            $crumbs[] = ['name' => $shop->prefecture, 'item' => route('rental-bike.prefecture', $shop->prefecture)];
        }
        $crumbs[] = ['name' => $shop->name, 'item' => route('rental-bike.show', $shop->id)];
        $breadcrumbLd = [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => [],
        ];
        foreach ($crumbs as $i => $c) {
            $breadcrumbLd['itemListElement'][] = [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $c['name'],
                'item' => $c['item'],
            ];
        }
    @endphp

    <x-slot:title>{{ $shop->name }}｜{{ $titleArea !== '' ? $titleArea.'の' : '' }}レンタルバイク - MotoHub</x-slot:title>
    <x-slot:metaDescription>{{ $titleArea }}のレンタルバイク店舗「{{ $shop->name }}」（{{ $shop->company }}）の詳細。住所・地図・公式サイトを掲載。</x-slot:metaDescription>

    <x-slot:styles>
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
        <style>
            #detail-map { height: 250px; z-index: 10; border-radius: 12px; }
        </style>
    </x-slot:styles>

    <x-slot:scripts>
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
        @if($shop->latitude && $shop->longitude)
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const lat = {{ $shop->latitude }};
                const lng = {{ $shop->longitude }};
                const map = L.map('detail-map').setView([lat, lng], 16);
                L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>'
                }).addTo(map);
                L.marker([lat, lng]).addTo(map);
                if (typeof lucide !== 'undefined') lucide.createIcons();
            });
        </script>
        @endif
    </x-slot:scripts>

    <x-slot:navigation>
        <x-navigation :showSearch="true" />
    </x-slot:navigation>

    <div class="bg-gray-50 min-h-screen py-8">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">

            {{-- パンくず --}}
            <nav class="flex text-xs font-bold text-gray-400 mb-6" aria-label="Breadcrumb">
                <ol class="flex items-center space-x-2">
                    <li><a href="/" class="hover:text-gray-600 transition-colors">HOME</a></li>
                    <li><span class="text-gray-300">＞</span></li>
                    <li><a href="{{ route('rental-bike.index') }}" class="hover:text-gray-600 transition-colors">レンタルバイク</a></li>
                    @if(filled($shop->prefecture))
                    <li><span class="text-gray-300">＞</span></li>
                    <li><a href="{{ route('rental-bike.prefecture', $shop->prefecture) }}" class="hover:text-gray-600 transition-colors">{{ $shop->prefecture }}</a></li>
                    @endif
                    <li><span class="text-gray-300">＞</span></li>
                    <li><span class="text-gray-800">{{ $shop->name }}</span></li>
                </ol>
            </nav>

            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8">
                {{-- 見出し --}}
                <h1 class="text-xl font-black text-gray-900 mb-2">
                    {{ $areaName ? $areaName.'の' : '' }}レンタルバイク「{{ $shop->name }}」
                </h1>
                <div class="flex flex-wrap items-center gap-2 mb-4">
                    <span class="inline-block px-2.5 py-1 bg-violet-50 text-violet-700 text-[11px] font-bold rounded-md">レンタルバイク</span>
                    <span class="inline-block px-2.5 py-1 bg-gray-100 text-gray-600 text-[11px] font-bold rounded-md">{{ $shop->company }}</span>
                </div>

                @if($shop->address)
                <p class="text-xs text-gray-500 mb-1"><i data-lucide="map-pin" class="inline w-3.5 h-3.5"></i> {{ $shop->postal_code ? '〒'.$shop->postal_code.' ' : '' }}{{ $shop->address }}</p>
                @endif
                {{-- ★電話番号は「ある場合のみ」表示。無い場合は項目ごと非表示（「情報なし」とは出さない）。 --}}
                @if($shop->tel)
                <p class="text-xs text-gray-500 mb-1"><i data-lucide="phone" class="inline w-3.5 h-3.5"></i> {{ $shop->tel }}</p>
                @endif
                {{-- ★営業時間も「ある場合のみ」。 --}}
                @if($shop->opening_hours)
                <p class="text-xs text-gray-500 mb-3"><i data-lucide="clock" class="inline w-3.5 h-3.5"></i> {{ $shop->opening_hours }}</p>
                @endif

                {{-- 地図 --}}
                @if($shop->latitude && $shop->longitude)
                <div id="detail-map" class="w-full mb-4"></div>
                @endif

                {{-- 自動生成の説明文（施設データから組み立て・DBは書き換えていない。評価語なし） --}}
                @if($autoBody !== '')
                <p class="text-sm text-gray-700 leading-relaxed mb-4">{{ $autoBody }}</p>
                @endif

                {{-- ★公式サイトへの送客。rel="nofollow" は付けない。電話が無い店舗ほど目立たせる。 --}}
                @if($shop->official_url)
                <a href="{{ $shop->official_url }}" target="_blank" rel="noopener" class="flex items-center justify-center gap-1.5 w-full px-4 py-3 bg-violet-600 text-white text-sm font-bold rounded-lg hover:bg-violet-700 transition mb-2">
                    <i data-lucide="external-link" class="w-4 h-4"></i> 公式サイトで見る
                </a>
                @unless($shop->tel)
                <p class="text-xs text-gray-500 text-center mb-2">予約・お問い合わせは公式サイトから</p>
                @endunless
                @endif
                @if($shop->latitude && $shop->longitude)
                <a href="https://www.google.com/maps/dir/?api=1&destination={{ $shop->latitude }},{{ $shop->longitude }}" target="_blank" rel="noopener" class="flex items-center justify-center gap-1.5 w-full px-4 py-2.5 bg-gray-100 text-gray-700 text-xs font-bold rounded-lg hover:bg-gray-200 transition mb-2">ルート案内</a>
                @endif
            </div>

            {{-- 周辺の関連（薄いページ対策・内部リンク）。★1件も無いブロックは出さない。 --}}
            @if($nearbyStations->isNotEmpty() || $nearbyGarages->isNotEmpty())
            <div class="mt-6 grid gap-4 sm:grid-cols-2">
                @if($nearbyStations->isNotEmpty())
                <div class="bg-white rounded-2xl border border-gray-100 p-4">
                    <h2 class="text-xs font-black text-gray-900 mb-2">近くの駅</h2>
                    <ul class="space-y-1.5">
                        @foreach($nearbyStations as $st)
                        <li class="text-xs text-gray-700 font-bold">{{ $st->name }}<span class="text-gray-400 font-normal ml-1">約{{ round($st->dist_m / 1000, 1) }}km</span></li>
                        @endforeach
                    </ul>
                </div>
                @endif
                @if($nearbyGarages->isNotEmpty())
                <div class="bg-white rounded-2xl border border-gray-100 p-4">
                    <h2 class="text-xs font-black text-gray-900 mb-2">近くのレンタルガレージ</h2>
                    <ul class="space-y-1.5">
                        @foreach($nearbyGarages as $g)
                        <li><a href="{{ route('rental-garage.show', $g->id) }}" class="text-xs text-violet-600 hover:underline font-bold">{{ $g->name }}</a></li>
                        @endforeach
                    </ul>
                </div>
                @endif
            </div>
            @endif

            {{-- 回遊リンク --}}
            <div class="mt-6">
                <x-cross-links :crossLinks="$crossLinks" />
            </div>
        </div>
    </div>

    {{-- JSON-LD: 店舗（AutomotiveBusiness）＋ BreadcrumbList。★画像は出さない。電話はあるときだけ。 --}}
    <script type="application/ld+json">
    {
        "@@context": "https://schema.org",
        "@@type": "AutomotiveBusiness",
        "name": "{{ e($shop->name) }}",
        "url": "{{ route('rental-bike.show', $shop->id) }}",
        @if($shop->tel)"telephone": "{{ e($shop->tel) }}",@endif
        @if($shop->official_url)"sameAs": "{{ e($shop->official_url) }}",@endif
        "address": {
            "@@type": "PostalAddress",
            @if($shop->postal_code)"postalCode": "{{ e($shop->postal_code) }}",@endif
            @if($shop->prefecture)"addressRegion": "{{ e($shop->prefecture) }}",@endif
            @if($shop->city)"addressLocality": "{{ e($shop->city) }}",@endif
            "streetAddress": "{{ e($shop->address) }}",
            "addressCountry": "JP"
        }@if($shop->latitude && $shop->longitude),
        "geo": {
            "@@type": "GeoCoordinates",
            "latitude": "{{ $shop->latitude }}",
            "longitude": "{{ $shop->longitude }}"
        }@endif
    }
    </script>
    <script type="application/ld+json">
    {!! json_encode($breadcrumbLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}
    </script>
</x-layout>
