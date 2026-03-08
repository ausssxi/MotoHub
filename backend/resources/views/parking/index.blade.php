<x-layout>
    <x-slot:title>バイク駐車場・駐輪場マップ | MotoHub</x-slot:title>
    <x-slot:metaDescription>全国のバイク駐車場・駐輪場をマップで検索。料金や設備情報、ユーザーレビューも確認できます。</x-slot:metaDescription>

    <x-slot:styles>
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
        <style>
            #map { height: 60vh; z-index: 10; }
            @media (max-width: 640px) { #map { height: 50vh; } }
            .custom-popup .leaflet-popup-content-wrapper { border-radius: 12px; padding: 0; overflow: hidden; }
            .custom-popup .leaflet-popup-content { margin: 0; width: 280px !important; }
            .scrollbar-hide::-webkit-scrollbar { display: none; }
            .scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
            .parking-card-active { border-color: #16a34a !important; box-shadow: 0 0 0 2px rgba(22,163,74,.3); }
        </style>
    </x-slot:styles>

    <x-slot:scripts>
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
        <script src="{{ asset('js/parking/map.js') }}"></script>
    </x-slot:scripts>

    <x-slot:navigation>
        <x-navigation :showSearch="true" />
    </x-slot:navigation>

    <div class="relative w-full">
        <div id="map" class="w-full bg-gray-100"></div>

        {{-- 検索中ローディング --}}
        <div id="map-loading" class="absolute top-4 left-1/2 -translate-x-1/2 bg-white/90 backdrop-blur px-4 py-2 rounded-full shadow-lg z-[1000] flex items-center gap-2 hidden">
            <i data-lucide="loader-2" class="w-4 h-4 animate-spin text-green-600"></i>
            <span class="text-xs font-bold text-gray-600">駐車場を検索中...</span>
        </div>

        {{-- 左上: フィルタ --}}
        <div x-data="{ open: false }" class="absolute top-3 left-3 z-[1000]">
            <button @click="open = !open" class="bg-white px-3 py-2 rounded-lg shadow-md border border-gray-200 flex items-center gap-1.5 text-xs font-bold text-gray-700 hover:bg-gray-50 transition">
                <i data-lucide="sliders-horizontal" class="w-3.5 h-3.5"></i>
                フィルタ
            </button>
            <div x-show="open" x-transition @click.outside="open = false" class="mt-2 bg-white rounded-xl shadow-xl border border-gray-100 p-4 w-56" style="display: none;">
                <p class="text-xs font-black text-gray-900 mb-3">駐車場タイプ</p>
                <div class="space-y-2" id="filter-type">
                    <label class="flex items-center gap-2 text-xs text-gray-700 cursor-pointer">
                        <input type="radio" name="parking_type" value="" checked class="text-green-600 focus:ring-green-500"> すべて
                    </label>
                    <label class="flex items-center gap-2 text-xs text-gray-700 cursor-pointer">
                        <input type="radio" name="parking_type" value="bike_only" class="text-green-600 focus:ring-green-500"> バイク専用
                    </label>
                    <label class="flex items-center gap-2 text-xs text-gray-700 cursor-pointer">
                        <input type="radio" name="parking_type" value="car_shared" class="text-green-600 focus:ring-green-500"> 四輪と共用
                    </label>
                    <label class="flex items-center gap-2 text-xs text-gray-700 cursor-pointer">
                        <input type="radio" name="parking_type" value="bicycle_shared" class="text-green-600 focus:ring-green-500"> 自転車と共用
                    </label>
                </div>
            </div>
        </div>

        {{-- 右上: 駐車場登録ボタン --}}
        <a href="{{ route('parking.create') }}" class="absolute top-3 right-3 bg-gray-900 text-white px-3 py-2 rounded-lg shadow-md flex items-center gap-1.5 text-xs font-bold hover:bg-gray-700 transition z-[1000]">
            <i data-lucide="plus" class="w-3.5 h-3.5"></i>
            駐車場を登録
        </a>

        {{-- 右下: 現在地ボタン（Leafletズームは左下） --}}
        <button id="btn-current-location" class="absolute bottom-4 right-3 bg-white p-2.5 rounded-lg shadow-md z-[1000] text-gray-600 hover:text-green-600 transition-colors border border-gray-200">
            <i data-lucide="crosshair" class="w-5 h-5"></i>
        </button>
    </div>

    {{-- 件数バー + レビュー促進 --}}
    <div class="bg-white border-t border-b border-gray-200 px-4 py-2 flex items-center justify-between gap-3">
        <span id="parking-count" class="text-sm font-black text-gray-800 shrink-0">地図内に0件</span>
        <div id="review-banner" class="flex items-center gap-2 text-[10px] text-gray-400">
            <span>🅿️ 使った駐車場に★評価をつけてみんなの参考に</span>
            <button onclick="document.getElementById('review-banner').remove()" class="text-gray-300 hover:text-gray-500">✕</button>
        </div>
        <span class="text-xs text-gray-400 shrink-0 hidden sm:inline">← スクロール →</span>
    </div>

    {{-- カードスライダー --}}
    <div id="parking-cards"
         class="flex gap-3 overflow-x-auto pb-4 px-4 py-3 bg-gray-50 snap-x snap-mandatory scrollbar-hide"
         style="min-height: 120px;">
        <div class="flex items-center justify-center w-full text-sm text-gray-400">地図を移動すると駐車場カードが表示されます</div>
    </div>

    {{-- レビューが多い駐車場ランキング --}}
    @if($topReviewed->isNotEmpty())
    <section class="bg-gray-50 py-8">
        <div class="max-w-5xl mx-auto px-4">
            <h2 class="text-lg font-black text-gray-900 text-center mb-6">レビューが多い駐車場</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($topReviewed as $rank => $p)
                <a href="{{ route('parking.show', $p) }}" class="bg-white rounded-xl border border-gray-100 p-4 hover:shadow-md transition-shadow block">
                    <div class="flex items-start gap-3">
                        <span class="text-2xl font-black text-gray-200">{{ $rank + 1 }}</span>
                        <div class="flex-1 min-w-0">
                            <h3 class="text-sm font-bold text-gray-800 truncate">{{ $p->name }}</h3>
                            <p class="text-[10px] text-gray-400 truncate">{{ $p->prefecture }} {{ $p->city }}</p>
                            <div class="flex items-center gap-2 mt-1">
                                <span class="text-yellow-500 text-xs">
                                    @for($i = 1; $i <= 5; $i++){{ $i <= round($p->avg_rating) ? '★' : '☆' }}@endfor
                                </span>
                                <span class="text-[10px] text-gray-400">{{ number_format($p->avg_rating, 1) }} ({{ $p->reviews_count }}件)</span>
                            </div>
                        </div>
                    </div>
                </a>
                @endforeach
            </div>
        </div>
    </section>
    @endif

    {{-- 都道府県から探す（SEO用内部リンク） --}}
    <section class="bg-white py-12">
        <div class="max-w-5xl mx-auto px-4">
            <h2 class="text-lg font-black text-gray-900 text-center mb-8">都道府県からバイク駐車場を探す</h2>
            @php
                $regions = [
                    '北海道・東北' => ['北海道' => [43.06, 141.35], '青森県' => [40.82, 140.74], '岩手県' => [39.70, 141.15], '宮城県' => [38.27, 140.87], '秋田県' => [39.72, 140.10], '山形県' => [38.24, 140.36], '福島県' => [37.75, 140.47]],
                    '関東' => ['茨城県' => [36.34, 140.45], '栃木県' => [36.57, 139.88], '群馬県' => [36.39, 139.06], '埼玉県' => [35.86, 139.65], '千葉県' => [35.61, 140.12], '東京都' => [35.68, 139.77], '神奈川県' => [35.45, 139.64]],
                    '中部' => ['新潟県' => [37.90, 139.02], '富山県' => [36.70, 137.21], '石川県' => [36.59, 136.63], '福井県' => [36.07, 136.22], '山梨県' => [35.66, 138.57], '長野県' => [36.23, 138.18], '岐阜県' => [35.39, 136.72], '静岡県' => [34.98, 138.38], '愛知県' => [35.18, 136.91]],
                    '近畿' => ['三重県' => [34.73, 136.51], '滋賀県' => [35.00, 135.87], '京都府' => [35.02, 135.76], '大阪府' => [34.69, 135.52], '兵庫県' => [34.69, 135.18], '奈良県' => [34.69, 135.83], '和歌山県' => [34.23, 135.17]],
                    '中国・四国' => ['鳥取県' => [35.50, 134.24], '島根県' => [35.47, 133.05], '岡山県' => [34.66, 133.93], '広島県' => [34.40, 132.46], '山口県' => [34.19, 131.47], '徳島県' => [34.07, 134.56], '香川県' => [34.34, 134.04], '愛媛県' => [33.84, 132.77], '高知県' => [33.56, 133.53]],
                    '九州・沖縄' => ['福岡県' => [33.61, 130.42], '佐賀県' => [33.25, 130.30], '長崎県' => [32.74, 129.87], '熊本県' => [32.79, 130.74], '大分県' => [33.24, 131.61], '宮崎県' => [31.91, 131.42], '鹿児島県' => [31.56, 130.56], '沖縄県' => [26.34, 127.68]],
                ];
            @endphp
            @foreach($regions as $regionName => $prefs)
            <div class="mb-6">
                <h3 class="text-xs font-black text-gray-400 uppercase tracking-widest mb-3">{{ $regionName }}</h3>
                <div class="flex flex-wrap gap-2">
                    @foreach($prefs as $pref => $coords)
                    <a href="{{ route('parking.index', ['lat' => $coords[0], 'lng' => $coords[1]]) }}"
                       class="px-3 py-1.5 rounded-lg bg-gray-50 border border-gray-100 text-xs font-bold text-gray-600 hover:bg-green-50 hover:border-green-200 hover:text-green-600 transition-colors">
                        {{ $pref }}
                    </a>
                    @endforeach
                </div>
            </div>
            @endforeach
        </div>
    </section>
</x-layout>
``