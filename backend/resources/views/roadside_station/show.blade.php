<x-layout>
    @php
        $pref = $station->prefecture ?? '';
        $city = $station->city ?? '';
        $areaName = $pref.$city;

        // 施設フラグ（10項目）。$hasFacilityData が true のときだけ 2値（あり/なし）で表示。
        $facilities = [
            'has_atm' => 'ATM',
            'has_restaurant' => 'レストラン',
            'has_onsen' => '温泉',
            'has_ev_charging' => 'EV充電',
            'has_wifi' => 'Wi-Fi',
            'has_shower' => 'シャワー',
            'has_camp' => 'キャンプ場',
            'has_gas_station' => 'ガソリンスタンド',
            'has_observatory' => '展望台',
            'has_shop' => '売店・物産',
        ];

        // 周辺情報ブロックの定義（キーは controller の buildNearby と揃える）。
        $nearbyBlocks = [
            ['key' => 'gas_stations', 'title' => '近くのガソリンスタンド', 'color' => 'text-red-600'],
            ['key' => 'convenience_stores', 'title' => '近くのコンビニ', 'color' => 'text-orange-600'],
            ['key' => 'car_washes', 'title' => '近くの洗車場', 'color' => 'text-sky-600'],
            ['key' => 'shops', 'title' => '近くのバイクショップ', 'color' => 'text-indigo-600'],
            ['key' => 'parkings', 'title' => '近くのバイク駐車場', 'color' => 'text-green-600'],
            ['key' => 'rental_garages', 'title' => '近くのレンタルガレージ', 'color' => 'text-violet-600'],
            ['key' => 'roadside_stations', 'title' => '近くの道の駅', 'color' => 'text-purple-600'],
            ['key' => 'same_route_stations', 'title' => '同じ路線沿いの道の駅', 'color' => 'text-teal-600'],
        ];
        $hasAnyNearby = collect($nearby)->contains(fn ($items) => ! empty($items));

        // 自動生成の説明文（DBの summary は書き換えない・評価語は入れない）。条件に合う文だけ、この順で連結する。
        $autoSentences = [];
        // 1) route の有無（route_list は「・」連結済みの文字列を使う）
        $routeText = count($station->route_list) > 0 ? implode('・', $station->route_list) : '';
        $autoSentences[] = $routeText !== ''
            ? "{$station->name}は、{$pref}{$city}の{$routeText}沿いにある道の駅です。"
            : "{$station->name}は、{$pref}{$city}にある道の駅です。";
        // 2) designated_year
        if ($station->designated_year) {
            $autoSentences[] = "{$station->designated_year}年に登録されました。";
        }
        // 3) 施設フラグ（true のものだけ・画面表示と同じ語）
        $trueFacilities = [];
        foreach ($facilities as $facField => $facLabel) {
            if ($station->$facField) {
                $trueFacilities[] = $facLabel;
            }
        }
        if (count($trueFacilities) > 0) {
            $autoSentences[] = implode('、', $trueFacilities).'があります。';
        }
        // meta description は 1)+2)+3) までを 120 文字で打ち切る
        $autoMeta = mb_substr(implode('', $autoSentences), 0, 120);
        // 4) 最寄りガソリンスタンド（既存の $nearby を再利用・新クエリなし。先頭が最寄り）
        if (! empty($nearby['gas_stations'])) {
            $autoSentences[] = '最寄りのガソリンスタンドまで約'.number_format($nearby['gas_stations'][0]['distance_km'], 1).'km。';
        }
        // 5) 最寄りコンビニ
        if (! empty($nearby['convenience_stores'])) {
            $autoSentences[] = '最寄りのコンビニまで約'.number_format($nearby['convenience_stores'][0]['distance_km'], 1).'km。';
        }
        // 6) 近くの道の駅（最も近い1件）
        if (! empty($nearby['roadside_stations'])) {
            $autoSentences[] = '近くの道の駅は'.$nearby['roadside_stations'][0]['label'].'（約'.number_format($nearby['roadside_stations'][0]['distance_km'], 1).'km）です。';
        }
        // 7) バイクショップ件数
        if (! empty($nearby['shops'])) {
            $autoSentences[] = '周辺にはバイクショップが'.count($nearby['shops']).'軒あります。';
        }
        // 8) 半径50km以内の道の駅数（$nearby から・新クエリなし）
        if (($nearby['stations_within_50km'] ?? 0) >= 1) {
            $autoSentences[] = '半径50km以内に道の駅が'.$nearby['stations_within_50km'].'駅あります。';
        }
        // 9) 同じ路線沿い（路線名は先頭1件を全角「（」以降除去して使う）
        if (! empty($nearby['same_route_stations'])) {
            $firstRoute = count($station->route_list) > 0
                ? trim((string) preg_replace('/（.*$/su', '', $station->route_list[0]))
                : '';
            if ($firstRoute !== '') {
                $autoSentences[] = $firstRoute.'沿いには、ほかに'.count($nearby['same_route_stations']).'駅の道の駅があります。';
            }
        }
        $autoDescription = implode('', $autoSentences);
    @endphp

    {{-- name には既に「道の駅」が含まれるので二重に付けない --}}
    <x-slot:title>{{ count($station->route_list) > 0 ? $station->name.'｜'.$areaName.'・'.$station->route_list[0].'の道の駅 - MotoHub' : $station->name.'｜'.$areaName.'の道の駅 - MotoHub' }}</x-slot:title>
    <x-slot:metaDescription>{{ $autoMeta }}</x-slot:metaDescription>

    @if($station->image_url)
        <x-slot:ogImage>{{ $station->image_url }}</x-slot:ogImage>
    @endif

    <x-slot:styles>
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
        <style>
            #detail-map { height: 250px; z-index: 10; border-radius: 12px; }
        </style>
    </x-slot:styles>

    <x-slot:scripts>
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
        @if($hasCoords)
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const lat = {{ $station->latitude }};
                const lng = {{ $station->longitude }};
                const map = L.map('detail-map').setView([lat, lng], 15);
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

            {{-- パンくず（prefecture があれば4階層・無ければ道の駅＞駅名の3階層） --}}
            <nav class="flex text-xs font-bold text-gray-400 mb-6" aria-label="Breadcrumb">
                <ol class="flex items-center space-x-2">
                    <li><a href="/" class="hover:text-gray-600 transition-colors">HOME</a></li>
                    <li><span class="text-gray-300">＞</span></li>
                    <li><a href="{{ route('michinoeki.index') }}" class="hover:text-gray-600 transition-colors">道の駅</a></li>
                    @if($station->prefecture)
                    <li><span class="text-gray-300">＞</span></li>
                    <li><a href="{{ route('michinoeki.area', $station->prefecture) }}" class="hover:text-gray-600 transition-colors">{{ $station->prefecture }}</a></li>
                    @endif
                    <li><span class="text-gray-300">＞</span></li>
                    <li><span class="text-gray-800">{{ $station->name }}</span></li>
                </ol>
            </nav>

            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8">
                {{-- 見出し（市区町村名を含む） --}}
                <h1 class="text-xl font-black text-gray-900 mb-2">
                    {{ $areaName }}の道の駅「{{ $station->name }}」
                </h1>

                {{-- バッジ --}}
                @if(count($station->nickname_list) > 0 || $station->designated_year)
                <div class="flex flex-wrap items-center gap-2 mb-4">
                    @foreach($station->nickname_list as $nickname)
                    <span class="inline-block px-2.5 py-1 bg-purple-50 text-purple-700 text-[11px] font-bold rounded-md">{{ $nickname }}</span>
                    @endforeach
                    @if($station->designated_year)
                    <span class="inline-block px-2.5 py-1 bg-gray-100 text-gray-600 text-[11px] font-bold rounded-md">{{ $station->designated_year }}年登録</span>
                    @endif
                </div>
                @endif

                {{-- 住所・路線 --}}
                @if($station->address)
                <p class="text-xs text-gray-500 mb-1"><i data-lucide="map-pin" class="inline w-3.5 h-3.5"></i> {{ $station->address }}</p>
                @endif
                @if(count($station->route_list) > 0)
                {{-- route は「国道12号|国道233号」のようなパイプ区切り。route_list で分割し「・」で読みやすく連結。 --}}
                <p class="text-xs text-gray-500 mb-3"><i data-lucide="milestone" class="inline w-3.5 h-3.5"></i> {{ implode('・', $station->route_list) }}</p>
                @endif

                {{-- 地図（座標が無ければセクションごと省略） --}}
                @if($hasCoords)
                <div id="detail-map" class="w-full mb-4"></div>
                @endif

                {{-- 概要 --}}
                {{-- summary は40文字以上のときだけ生成文の前に表示（40文字未満は県名＋路線名だけの自動生成文が入っているため出さない）。DBは書き換えない --}}
                @if($station->summary && mb_strlen($station->summary) >= 40)
                <div class="text-sm text-gray-700 whitespace-pre-line bg-gray-50 rounded-lg p-4 mb-4">{{ $station->summary }}</div>
                @endif
                @if($autoDescription !== '')
                <div class="text-sm text-gray-700 leading-relaxed bg-gray-50 rounded-lg p-4 mb-4">{{ $autoDescription }}</div>
                @endif

                {{-- 写真＋クレジット（法的義務） --}}
                @if($station->image_url)
                <figure class="mb-4">
                    <img src="{{ $station->image_url }}" alt="{{ $station->name }}" loading="lazy" decoding="async"
                         onerror="this.closest('figure').style.display='none'"
                         class="w-full rounded-xl">
                    <figcaption class="mt-1.5 text-[11px] text-gray-400">
                        画像:
                        @if($station->image_author){{ $station->image_author }} / @endif
                        @if($station->image_license)@if($station->image_license_url)<a href="{{ $station->image_license_url }}" target="_blank" rel="noopener" class="underline hover:text-gray-600">{{ $station->image_license }}</a>@else{{ $station->image_license }}@endif / @endif
                        @if($station->image_source_url)<a href="{{ $station->image_source_url }}" target="_blank" rel="noopener" class="underline hover:text-gray-600">Wikimedia Commons</a>@else Wikimedia Commons @endif
                    </figcaption>
                </figure>
                @endif

                {{-- 施設情報グリッド（2値: あり/なし）。
                     summary が空の駅は施設フラグが全て false のまま＝未収集のため、
                     $hasFacilityData が false のときはセクションごと出さない（代替テキストも無し）。 --}}
                @if($hasFacilityData)
                <h2 class="text-sm font-black text-gray-900 mb-2">施設情報</h2>
                <div class="grid grid-cols-2 gap-2 mb-4">
                    @foreach($facilities as $field => $label)
                    <div class="flex items-center justify-between px-3 py-2 border border-gray-100 rounded-lg text-sm">
                        <span class="text-gray-600">{{ $label }}</span>
                        <span class="font-bold {{ $station->$field ? 'text-emerald-600' : 'text-gray-400' }}">{{ $station->$field ? 'あり' : 'なし' }}</span>
                    </div>
                    @endforeach
                </div>
                @endif

                {{-- 基本情報テーブル --}}
                <h2 class="text-sm font-black text-gray-900 mb-2">基本情報</h2>
                <div class="bg-gray-50 rounded-lg p-4 mb-4 space-y-2">
                    <div class="flex items-start gap-2">
                        <span class="text-[11px] font-bold text-gray-400 w-24 shrink-0 pt-0.5">都道府県</span>
                        <span class="text-sm text-gray-700">{{ $station->prefecture ?: '—' }}</span>
                    </div>
                    <div class="flex items-start gap-2">
                        <span class="text-[11px] font-bold text-gray-400 w-24 shrink-0 pt-0.5">市区町村</span>
                        <span class="text-sm text-gray-700">{{ $station->city ?: '—' }}</span>
                    </div>
                    <div class="flex items-start gap-2">
                        <span class="text-[11px] font-bold text-gray-400 w-24 shrink-0 pt-0.5">登録番号</span>
                        <span class="text-sm text-gray-700">{{ $station->station_code }}</span>
                    </div>
                    <div class="flex items-start gap-2">
                        <span class="text-[11px] font-bold text-gray-400 w-24 shrink-0 pt-0.5">指定年</span>
                        <span class="text-sm text-gray-700">{{ $station->designated_year ? $station->designated_year.'年' : '—' }}</span>
                    </div>
                    <div class="flex items-start gap-2">
                        <span class="text-[11px] font-bold text-gray-400 w-24 shrink-0 pt-0.5">緯度経度</span>
                        <span class="text-sm text-gray-700">{{ $hasCoords ? $station->latitude.', '.$station->longitude : '—' }}</span>
                    </div>
                </div>

                {{-- ルート案内・公式サイト --}}
                @if($hasCoords)
                <a href="https://www.google.com/maps/dir/?api=1&destination={{ $station->latitude }},{{ $station->longitude }}" target="_blank" rel="noopener" class="flex items-center justify-center gap-1.5 w-full px-4 py-2.5 bg-gray-100 text-gray-700 text-xs font-bold rounded-lg hover:bg-gray-200 transition mb-2">
                    <i data-lucide="navigation" class="w-3.5 h-3.5"></i> ルート案内
                </a>
                @endif
                @if($officialUrl)
                <a href="{{ $officialUrl }}" target="_blank" rel="noopener" class="flex items-center justify-center gap-1.5 w-full px-4 py-2.5 bg-purple-600 text-white text-xs font-bold rounded-lg hover:bg-purple-700 transition mb-2">
                    <i data-lucide="external-link" class="w-3.5 h-3.5"></i> {{ $officialLabel }}
                </a>
                @endif
                @if($station->wikipedia_url)
                <a href="{{ $station->wikipedia_url }}" target="_blank" rel="noopener" class="flex items-center justify-center gap-1.5 w-full px-4 py-2.5 bg-gray-100 text-gray-700 text-xs font-bold rounded-lg hover:bg-gray-200 transition mb-2">
                    <i data-lucide="external-link" class="w-3.5 h-3.5"></i> Wikipedia（外部サイト）
                </a>
                @endif
            </div>

            {{-- 周辺情報（空のブロックは出さない・各項目に距離を併記） --}}
            @if($hasAnyNearby)
            <div class="mt-6 grid gap-4 sm:grid-cols-2">
                @foreach($nearbyBlocks as $block)
                    @php $items = $nearby[$block['key']] ?? []; @endphp
                    @if(! empty($items))
                    <div class="bg-white rounded-2xl border border-gray-100 p-4">
                        <h2 class="text-xs font-black text-gray-900 mb-2">{{ $block['title'] }}</h2>
                        <ul class="space-y-1.5">
                            @foreach($items as $item)
                            <li class="flex items-baseline justify-between gap-2">
                                <a href="{{ $item['url'] }}" class="text-xs {{ $block['color'] }} hover:underline font-bold">{{ $item['label'] }}</a>
                                <span class="text-[10px] text-gray-400 shrink-0">{{ number_format($item['distance_km'], 1) }}km</span>
                            </li>
                            @endforeach
                        </ul>
                    </div>
                    @endif
                @endforeach
            </div>
            @endif

            {{-- 回遊リンク --}}
            <div class="mt-6">
                <x-cross-links :crossLinks="$crossLinks" />
            </div>
        </div>
    </div>

    {{-- JSON-LD: TouristAttraction ＋ BreadcrumbList --}}
    @php
        $ldFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;

        $ld = [
            '@context' => 'https://schema.org',
            '@type' => 'TouristAttraction',
            'name' => $station->name,
            'url' => route('michinoeki.show', $station->station_code),
        ];
        if ($autoDescription !== '') {
            // 構造化データの description は作業2で組み立てた自動生成文全体（DBの summary ではない）。
            $ld['description'] = $autoDescription;
        }
        if (filled($station->image_url)) {
            $ld['image'] = $station->image_url;
        }
        $ldAddress = ['@type' => 'PostalAddress'];
        if (filled($station->prefecture)) { $ldAddress['addressRegion'] = $station->prefecture; }
        if (filled($station->city)) { $ldAddress['addressLocality'] = $station->city; }
        if (filled($station->address)) { $ldAddress['streetAddress'] = $station->address; }
        $ldAddress['addressCountry'] = 'JP';
        $ld['address'] = $ldAddress;
        if ($hasCoords) {
            $ld['geo'] = [
                '@type' => 'GeoCoordinates',
                'latitude' => (float) $station->latitude,
                'longitude' => (float) $station->longitude,
            ];
        }

        // パンくずと同構成: HOME ＞ 道の駅 ＞ (都道府県) ＞ 駅名。prefecture 空なら都道府県段を省く。
        $ldCrumbs = [
            ['name' => 'HOME', 'item' => url('/')],
            ['name' => '道の駅', 'item' => route('michinoeki.index')],
        ];
        if (filled($station->prefecture)) {
            $ldCrumbs[] = ['name' => $station->prefecture, 'item' => route('michinoeki.area', $station->prefecture)];
        }
        $ldCrumbs[] = ['name' => $station->name, 'item' => route('michinoeki.show', $station->station_code)];

        $ldBreadcrumb = [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_map(fn ($c, $i) => [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $c['name'],
                'item' => $c['item'],
            ], $ldCrumbs, array_keys($ldCrumbs)),
        ];
    @endphp
    <script type="application/ld+json">{!! json_encode($ld, $ldFlags) !!}</script>
    <script type="application/ld+json">{!! json_encode($ldBreadcrumb, $ldFlags) !!}</script>
</x-layout>
