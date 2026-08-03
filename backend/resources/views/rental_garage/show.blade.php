<x-layout>
    @php
        $feeText = null;
        if ($garage->monthly_fee_min && $garage->monthly_fee_max) {
            $feeText = number_format($garage->monthly_fee_min).'〜'.number_format($garage->monthly_fee_max).'円';
        } elseif ($garage->monthly_fee_min) {
            $feeText = number_format($garage->monthly_fee_min).'円〜';
        } elseif ($garage->monthly_fee_max) {
            $feeText = '〜'.number_format($garage->monthly_fee_max).'円';
        }
        $areaName = $garage->city ?: $garage->prefecture;
        // 設備 3値表示: null=情報なし / true=あり / false=なし
        $eq = fn ($v) => is_null($v) ? '情報なし' : ($v ? 'あり' : 'なし');
        $eqClass = fn ($v) => is_null($v) ? 'text-gray-400' : ($v ? 'text-emerald-600' : 'text-gray-500');
    @endphp

    <x-slot:title>{{ $garage->name }}｜{{ $feeText ? $feeText.' ' : '' }}レンタルガレージ・バイク保管{{ $areaName ? ' '.$areaName : '' }} - MotoHub</x-slot:title>
    <x-slot:metaDescription>{{ ($garage->prefecture ?? '').($garage->city ?? '') }}のレンタルガレージ・バイク保管場所「{{ $garage->name }}」の詳細。{{ $garage->garage_type_label }}{{ $feeText ? '／月額'.$feeText : '' }}。住所・地図・設備情報を掲載。</x-slot:metaDescription>

    @if($noindex)
        <x-slot:robotsMeta>noindex, follow</x-slot:robotsMeta>
    @endif

    <x-slot:styles>
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
        <style>
            #detail-map { height: 250px; z-index: 10; border-radius: 12px; }
        </style>
    </x-slot:styles>

    <x-slot:scripts>
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
        @if($garage->latitude && $garage->longitude)
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const lat = {{ $garage->latitude }};
                const lng = {{ $garage->longitude }};
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
                    <li><a href="{{ route('riders.map') }}" class="hover:text-gray-600 transition-colors">ライダーズマップ</a></li>
                    <li><span class="text-gray-300">＞</span></li>
                    <li><span class="text-gray-800">{{ $garage->name }}</span></li>
                </ol>
            </nav>

            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8">
                {{-- 見出し（市区町村名を含む） --}}
                <h1 class="text-xl font-black text-gray-900 mb-2">
                    {{ $areaName ? $areaName.'の' : '' }}レンタルガレージ「{{ $garage->name }}」
                </h1>
                {{-- 未確認ユーザー投稿の可視ラベル（noindex は検索向けで訪問者には伝わらないため明示） --}}
                @if($noindex)
                <div class="mb-3">
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-amber-100 text-amber-800 text-[11px] font-black rounded-md">
                        <i data-lucide="alert-triangle" class="w-3.5 h-3.5"></i> ユーザー投稿・運営未確認
                    </span>
                </div>
                @endif
                <div class="flex flex-wrap items-center gap-2 mb-4">
                    <span class="inline-block px-2.5 py-1 bg-violet-50 text-violet-700 text-[11px] font-bold rounded-md">{{ $garage->garage_type_label }}</span>
                    @if($garage->operator)
                    <span class="inline-block px-2.5 py-1 bg-gray-100 text-gray-600 text-[11px] font-bold rounded-md">{{ $garage->operator }}</span>
                    @endif
                </div>

                @if($garage->address)
                <p class="text-xs text-gray-500 mb-1"><i data-lucide="map-pin" class="inline w-3.5 h-3.5"></i> {{ $garage->postal_code ? '〒'.$garage->postal_code.' ' : '' }}{{ $garage->address }}</p>
                @endif
                @if($garage->phone)
                <p class="text-xs text-gray-500 mb-3"><i data-lucide="phone" class="inline w-3.5 h-3.5"></i> {{ $garage->phone }}</p>
                @endif

                {{-- 地図 --}}
                @if($garage->latitude && $garage->longitude)
                <div id="detail-map" class="w-full mb-2"></div>
                @if($garage->geocode_status === 'approximate')
                <p class="text-[11px] text-amber-700 bg-amber-50 rounded-lg px-3 py-2 mb-4">
                    この施設の地図上の位置は市区町村レベルの概算です。正確な所在地は住所欄と公式サイトをご確認ください。
                </p>
                @else
                <div class="mb-4"></div>
                @endif
                @endif

                {{-- 料金・サイズ --}}
                <div class="bg-gray-50 rounded-lg p-4 mb-4 space-y-2">
                    <div class="flex items-start gap-2">
                        <span class="text-[11px] font-bold text-gray-400 w-24 shrink-0 pt-0.5">月額</span>
                        <span class="text-sm text-gray-800 font-bold">{{ $feeText ?? '情報なし' }}<span class="text-[10px] font-normal text-gray-400 ml-1">（区画により変動）</span></span>
                    </div>
                    <div class="flex items-start gap-2">
                        <span class="text-[11px] font-bold text-gray-400 w-24 shrink-0 pt-0.5">区画サイズ</span>
                        <span class="text-sm text-gray-700">{{ $garage->size_text ?: '情報なし' }}</span>
                    </div>
                    @if($garage->capacity)
                    <div class="flex items-start gap-2">
                        <span class="text-[11px] font-bold text-gray-400 w-24 shrink-0 pt-0.5">台数</span>
                        <span class="text-sm text-gray-700">{{ $garage->capacity }}台</span>
                    </div>
                    @endif
                </div>

                {{-- 設備（あり／なし／情報なし。null を「なし」と表示しない） --}}
                <div class="grid grid-cols-2 gap-2 mb-4">
                    @foreach(['is_24h' => '24時間出入り', 'has_power' => '電源', 'has_security' => '防犯設備', 'has_shutter' => 'シャッター'] as $field => $label)
                    <div class="flex items-center justify-between px-3 py-2 border border-gray-100 rounded-lg text-sm">
                        <span class="text-gray-600">{{ $label }}</span>
                        <span class="font-bold {{ $eqClass($garage->$field) }}">{{ $eq($garage->$field) }}</span>
                    </div>
                    @endforeach
                </div>

                {{-- 相場比較の一文 --}}
                @if($prefMedian && $garage->monthly_fee_min)
                <p class="text-xs text-gray-600 mb-4 bg-violet-50 rounded-lg px-3 py-2">
                    {{ $garage->prefecture }}のレンタルガレージ月額中央値は約{{ number_format($prefMedian) }}円です。この施設（下限{{ number_format($garage->monthly_fee_min) }}円）は
                    @if($garage->monthly_fee_min < $prefMedian)<strong class="text-emerald-600">相場より安め</strong>
                    @elseif($garage->monthly_fee_min > $prefMedian)<strong class="text-gray-700">相場よりやや高め</strong>
                    @else<strong>相場並み</strong>@endif
                    です。
                </p>
                @endif

                {{-- 公式サイト・備考 --}}
                @if($garage->website_url)
                <a href="{{ $garage->website_url }}" target="_blank" rel="noopener" class="flex items-center justify-center gap-1.5 w-full px-4 py-2.5 bg-violet-600 text-white text-xs font-bold rounded-lg hover:bg-violet-700 transition mb-2">
                    <i data-lucide="external-link" class="w-3.5 h-3.5"></i> 公式サイトで見る
                </a>
                @endif
                @if($garage->latitude && $garage->longitude)
                <a href="https://www.google.com/maps/dir/?api=1&destination={{ $garage->latitude }},{{ $garage->longitude }}" target="_blank" rel="noopener" class="flex items-center justify-center gap-1.5 w-full px-4 py-2.5 bg-gray-100 text-gray-700 text-xs font-bold rounded-lg hover:bg-gray-200 transition mb-2">ルート案内</a>
                @endif
                @if($garage->description)
                <div class="mt-3 text-sm text-gray-700 whitespace-pre-line bg-gray-50 rounded-lg p-3">{{ $garage->description }}</div>
                @endif
            </div>

            {{-- 周辺の関連（薄いページ対策・内部リンク） --}}
            @if($nearbyGarages->isNotEmpty() || $nearbyCarWashes->isNotEmpty() || $nearbyParkings->isNotEmpty())
            <div class="mt-6 grid gap-4 sm:grid-cols-3">
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
                @if($nearbyCarWashes->isNotEmpty())
                <div class="bg-white rounded-2xl border border-gray-100 p-4">
                    <h2 class="text-xs font-black text-gray-900 mb-2">近くの洗車場</h2>
                    <ul class="space-y-1.5">
                        @foreach($nearbyCarWashes as $c)
                        <li><a href="{{ route('riders.map', ['lat' => $c->latitude, 'lng' => $c->longitude, 'zoom' => 16, 'layer' => 'car_wash']) }}" class="text-xs text-sky-600 hover:underline font-bold">{{ $c->name }}</a></li>
                        @endforeach
                    </ul>
                </div>
                @endif
                @if($nearbyParkings->isNotEmpty())
                <div class="bg-white rounded-2xl border border-gray-100 p-4">
                    <h2 class="text-xs font-black text-gray-900 mb-2">近くのバイク駐車場</h2>
                    <ul class="space-y-1.5">
                        @foreach($nearbyParkings as $p)
                        <li><a href="{{ route('parking.show', $p->id) }}" class="text-xs text-green-600 hover:underline font-bold">{{ $p->name }}</a></li>
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

    {{-- JSON-LD: SelfStorage は「確認済み」情報のときのみ（未確認ユーザー投稿は構造化データにしない）。BreadcrumbList は常に出す。 --}}
    @unless($noindex)
    <script type="application/ld+json">
    {
        "@@context": "https://schema.org",
        "@@type": "SelfStorage",
        "name": "{{ e($garage->name) }}",
        "url": "{{ route('rental-garage.show', $garage->id) }}",
        @if($garage->phone)"telephone": "{{ e($garage->phone) }}",@endif
        @if($feeText)"priceRange": "{{ e($feeText) }}",@endif
        "address": {
            "@@type": "PostalAddress",
            @if($garage->postal_code)"postalCode": "{{ e($garage->postal_code) }}",@endif
            @if($garage->prefecture)"addressRegion": "{{ e($garage->prefecture) }}",@endif
            @if($garage->city)"addressLocality": "{{ e($garage->city) }}",@endif
            "streetAddress": "{{ e($garage->address) }}",
            "addressCountry": "JP"
        }@if($garage->latitude && $garage->longitude),
        "geo": {
            "@@type": "GeoCoordinates",
            "latitude": "{{ $garage->latitude }}",
            "longitude": "{{ $garage->longitude }}"
        }@endif
    }
    </script>
    @endunless
    <script type="application/ld+json">
    {
        "@@context": "https://schema.org",
        "@@type": "BreadcrumbList",
        "itemListElement": [
            {"@@type": "ListItem", "position": 1, "name": "HOME", "item": "{{ url('/') }}"},
            {"@@type": "ListItem", "position": 2, "name": "ライダーズマップ", "item": "{{ route('riders.map') }}"},
            {"@@type": "ListItem", "position": 3, "name": "{{ e($garage->name) }}", "item": "{{ route('rental-garage.show', $garage->id) }}"}
        ]
    }
    </script>
</x-layout>
