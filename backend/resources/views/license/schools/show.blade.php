<x-layout>
    <x-slot:title>{{ $prefecture }}で二輪免許が取れる指定自動車教習所一覧｜MotoHub</x-slot:title>
    <x-slot:metaDescription>{{ $prefecture }}で普通二輪・大型二輪の教習を行っている指定自動車教習所の一覧です。市区町村・対応免許区分・公式サイトへのリンクをまとめています。</x-slot:metaDescription>
    <x-slot:canonical>https://motohub.jp/license/schools/{{ $pref }}</x-slot:canonical>
    <x-slot:navigation>
        <x-navigation :showSearch="true" />
    </x-slot:navigation>

    <x-jsonld.breadcrumb-list :items="[
        ['name' => 'HOME', 'url' => route('bikes.index')],
        ['name' => '二輪免許が取れる指定自動車教習所', 'url' => route('license.schools.index')],
        ['name' => $prefecture],
    ]" />

    <div class="bg-gray-50 min-h-screen">

        {{-- ヒーロー --}}
        <div class="bg-gradient-to-br from-slate-900 to-blue-900 text-white pt-8 pb-10 px-4">
            <div class="max-w-3xl mx-auto">
                <nav class="text-xs text-blue-300 font-bold mb-4">
                    <a href="{{ route('license.schools.index') }}" class="hover:underline">二輪免許が取れる指定自動車教習所</a>
                    <span class="mx-1.5 text-blue-500">/</span>
                    <span class="text-blue-100">{{ $prefecture }}</span>
                </nav>
                <div class="text-4xl mb-2">🏍️</div>
                <h1 class="text-2xl sm:text-3xl font-black tracking-tight">{{ $prefecture }}で二輪免許が取れる指定自動車教習所</h1>
            </div>
        </div>

        <div class="max-w-3xl mx-auto px-4 py-10 space-y-10">

            {{-- 受付状況の注意書き（ページに一度だけ） --}}
            <div class="bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 text-[13px] text-amber-900 leading-relaxed">
                受付状況は変わることがあります。定員や休講により、掲載時点と異なる場合があります。お申し込み前に各校の公式サイトでご確認ください。
            </div>

            @php
                // 地図に出すのは緯度経度が両方入っている校のみ。一覧表（下）は従来どおり全件。
                $mapSchools = $schools->filter(fn ($s) => $s->latitude !== null && $s->longitude !== null)->values();
                $missingCoordCount = $schools->count() - $mapSchools->count();
                // 埋め込む項目は name/latitude/longitude/city/official_url/futsuu_nirin/oogata_nirin のみ。
                $mapMarkers = $mapSchools->map(fn ($s) => [
                    'name' => $s->name,
                    'latitude' => (float) $s->latitude,
                    'longitude' => (float) $s->longitude,
                    'city' => $s->city,
                    'official_url' => $s->official_url,
                    'futsuu_nirin' => (bool) $s->futsuu_nirin,
                    'oogata_nirin' => (bool) $s->oogata_nirin,
                ])->values();
            @endphp

            {{-- 地図（座標のある校のみ）。座標が0件なら、このブロック・Leaflet CSS/JS を一切出力しない。 --}}
            @if($mapSchools->isNotEmpty())
            <section>
                <h2 class="text-lg font-black text-slate-900 mb-3">地図で見る</h2>
                <div id="schools-map" class="w-full h-[320px] sm:h-[460px] rounded-2xl border border-slate-200"
                     data-schools='@json($mapMarkers)'></div>
                @if($missingCoordCount > 0)
                    <p class="text-[11px] text-slate-400 mt-2">※ 位置情報が未登録のため地図に表示していない教習所が {{ $missingCoordCount }} 校あります（一覧には掲載しています）。</p>
                @endif
            </section>
            @endif

            {{-- 一覧 --}}
            <section>
                <div class="bg-white rounded-2xl border border-slate-200 overflow-x-auto">
                    <table class="w-full text-sm min-w-[560px]">
                        <thead class="bg-slate-50 text-slate-600">
                            <tr class="text-left">
                                <th class="px-4 py-3 font-black">市区町村</th>
                                <th class="px-4 py-3 font-black">教習所名</th>
                                <th class="px-4 py-3 font-black text-center">普通二輪</th>
                                <th class="px-4 py-3 font-black text-center">大型二輪</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($schools as $s)
                                <tr class="hover:bg-slate-50">
                                    <td class="px-4 py-3 text-slate-600 font-bold whitespace-nowrap">{{ $s->city }}</td>
                                    <td class="px-4 py-3">
                                        @if($s->official_url)
                                            <a href="{{ $s->official_url }}" target="_blank" rel="nofollow noopener"
                                               class="font-bold text-blue-700 hover:underline">{{ $s->name }}</a>
                                        @else
                                            <span class="font-bold text-slate-900">{{ $s->name }}</span>
                                        @endif
                                        @if($s->address)
                                            <div class="text-xs text-slate-600 mt-1">{{ $s->address }}</div>
                                        @endif
                                        <div class="text-[11px] text-slate-400 mt-1">{{ $s->verified_at->format('Y') }}年{{ $s->verified_at->format('n') }}月時点で公式サイトに二輪教習の案内を確認</div>
                                        @if($s->isStale())
                                            <div class="text-[11px] text-amber-600 mt-0.5">この情報は確認から時間が経っています</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-center {{ $s->futsuu_nirin ? 'font-black text-emerald-600' : 'text-slate-300' }}">
                                        {{ $s->futsuu_nirin ? '○' : '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-center {{ $s->oogata_nirin ? 'font-black text-emerald-600' : 'text-slate-300' }}">
                                        {{ $s->oogata_nirin ? '○' : '—' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            {{-- 免許区分への導線 --}}
            <section>
                <div class="grid sm:grid-cols-2 gap-3">
                    <a href="/license/futsuu"
                       class="block bg-white rounded-xl border border-slate-200 p-4 hover:border-blue-400 transition">
                        <div class="text-sm font-black text-slate-900">🏍️ 普通二輪でどんなバイクに乗れるか見る</div>
                    </a>
                    <a href="/license/oogata"
                       class="block bg-white rounded-xl border border-slate-200 p-4 hover:border-blue-400 transition">
                        <div class="text-sm font-black text-slate-900">🏁 大型二輪でどんなバイクに乗れるか見る</div>
                    </a>
                </div>
            </section>

            {{-- 同じ県の関連ページ（在庫/販売店/駐車場） --}}
            @if($areaLinks['shops'] || $areaLinks['parking'] || $areaLinks['bikes'])
                <section>
                    <h2 class="text-lg font-black text-slate-900 mb-3">{{ $prefecture }}で免許を取ったあとに</h2>
                    <div class="grid sm:grid-cols-3 gap-3">
                        @if($areaLinks['bikes'])
                            <a href="{{ route('bikes.area_index', $areaLinks['bikes_short']) }}"
                               class="block bg-white rounded-xl border border-slate-200 p-4 hover:border-blue-400 transition">
                                <div class="text-sm font-black text-slate-900">🛵 {{ $prefecture }}の中古バイクを探す</div>
                            </a>
                        @endif
                        @if($areaLinks['shops'])
                            <a href="{{ route('shops.area.prefecture', $prefecture) }}"
                               class="block bg-white rounded-xl border border-slate-200 p-4 hover:border-blue-400 transition">
                                <div class="text-sm font-black text-slate-900">🏪 {{ $prefecture }}のバイク販売店を見る</div>
                            </a>
                        @endif
                        @if($areaLinks['parking'])
                            <a href="{{ route('parking.area.prefecture', $prefecture) }}"
                               class="block bg-white rounded-xl border border-slate-200 p-4 hover:border-blue-400 transition">
                                <div class="text-sm font-black text-slate-900">🅿️ {{ $prefecture }}のバイク駐車場を探す</div>
                            </a>
                        @endif
                    </div>
                </section>
            @endif

            {{-- 他の都道府県の教習所 --}}
            @php
                $others = collect($otherPrefectures)->reject(fn ($p) => $p['slug'] === $pref);
            @endphp
            @if($others->isNotEmpty())
                <section>
                    <h2 class="text-lg font-black text-slate-900 mb-3">他の都道府県から探す</h2>
                    <div class="flex flex-wrap gap-2">
                        @foreach($others as $p)
                            <a href="{{ route('license.schools.show', $p['slug']) }}"
                               class="inline-flex items-baseline gap-1 bg-white rounded-lg border border-slate-200 px-3 py-1.5 hover:border-blue-400 transition">
                                <span class="text-sm font-bold text-slate-900">{{ $p['name'] }}</span>
                                <span class="text-[11px] text-slate-400">{{ $p['count'] }}校</span>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif

            {{-- 免責文・出典 --}}
            <section class="border-t border-slate-200 pt-6">
                <p class="text-[11px] text-slate-400 leading-relaxed">
                    この一覧は各都道府県の指定自動車教習所協会が公表している会員校リストをもとに作成しています。協会に加盟していない教習所や、掲載後に取扱いが変わった教習所は反映されていない場合があります。教習料金・入校条件・二輪教習の実施状況は各教習所が個別に定めているため、お申し込み前に必ず各校の公式サイトでご確認ください。
                </p>
                <p class="text-[11px] text-slate-400 mt-3 leading-relaxed">
                    @foreach($sourceUrls as $url)
                        出典：<a href="{{ $url }}" target="_blank" rel="nofollow noopener" class="text-blue-600 hover:underline">{{ $url }}</a>@if(!$loop->last)<br>@endif
                    @endforeach
                </p>
            </section>

        </div>
    </div>

    {{-- 座標のある校が1件以上のときだけ Leaflet を読み込む。0件なら slot 自体を登録しない
         （$styles / $scripts は未定義のまま＝出力は従来と1バイトも変わらない）。 --}}
    @if($mapSchools->isNotEmpty())
        <x-slot:styles>
            <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
        </x-slot:styles>
        <x-slot:scripts>
            <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
            <script>
            (function () {
                var el = document.getElementById('schools-map');
                if (!el || typeof L === 'undefined') return;
                var schools = [];
                try { schools = JSON.parse(el.dataset.schools || '[]'); } catch (e) { return; }
                if (!schools.length) return;

                function esc(v) {
                    return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) {
                        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
                    });
                }

                var map = L.map('schools-map', { scrollWheelZoom: false, zoomSnap: 0.25 });
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
                    maxZoom: 18,
                }).addTo(map);

                var markers = [];
                schools.forEach(function (s) {
                    var lat = parseFloat(s.latitude), lng = parseFloat(s.longitude);
                    if (isNaN(lat) || isNaN(lng)) return;
                    var marker = L.marker([lat, lng]).addTo(map);
                    var title = s.official_url
                        ? '<a href="' + esc(s.official_url) + '" target="_blank" rel="nofollow noopener" style="color:#1d4ed8;font-weight:700;">' + esc(s.name) + '</a>'
                        : '<span style="font-weight:700;">' + esc(s.name) + '</span>';
                    var html = '<div style="font-size:12px;line-height:1.6;">' + title
                        + '<br>' + esc(s.city)
                        + '<br>普通二輪：' + (s.futsuu_nirin ? '○' : '—')
                        + '　大型二輪：' + (s.oogata_nirin ? '○' : '—')
                        + '</div>';
                    marker.bindPopup(html);
                    markers.push(marker);
                });

                if (markers.length === 1) {
                    map.setView(markers[0].getLatLng(), 15);
                } else if (markers.length > 1) {
                    map.fitBounds(L.featureGroup(markers).getBounds(), { padding: [16, 16] });
                }
            })();
            </script>
        </x-slot:scripts>
    @endif
</x-layout>
