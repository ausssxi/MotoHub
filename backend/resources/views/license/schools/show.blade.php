<x-layout>
    <x-slot:title>{{ $prefecture }}で二輪免許が取れる指定自動車教習所一覧【{{ number_format($schoolTotal) }}校】｜MotoHub</x-slot:title>
    <x-slot:metaDescription>{{ $prefecture }}で普通二輪・大型二輪の教習を行っている指定自動車教習所{{ number_format($schoolTotal) }}校（うち大型二輪対応{{ number_format($schoolOogata) }}校）の一覧。市区町村・対応免許区分・公式サイトへのリンクに加え、県内の中古バイク相場やバイク環境もまとめています。</x-slot:metaDescription>
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

            {{-- 合宿免許アフィリCTA（★affiliate.url 設定時のみ・PR表記付き。未設定なら枠自体を出さない） --}}
            @php
                $schoolAffiliate = config('driving_schools.affiliate', []);
                $schoolCtaUrl = $schoolAffiliate['url'] ?? '';
            @endphp
            @if(!empty($schoolCtaUrl) && !empty($schoolAffiliate['label']))
                <section>
                    <div class="bg-white rounded-2xl border border-slate-200 p-6 text-center">
                        <p class="text-[10px] font-black tracking-widest uppercase text-gray-500 mb-2">PR・広告</p>
                        <h2 class="text-lg font-black text-slate-900 mb-1">短期間でまとめて取りたいなら合宿という方法もあります</h2>
                        <p class="text-[13px] text-slate-600 leading-relaxed mb-4">通いの教習所のほかに、合宿で免許を取る方法もあります。二輪の合宿を扱っているか、料金や日程は申込先のサイトでご確認ください。</p>
                        @if(!empty($schoolAffiliate['label']))
                            <a href="{{ $schoolCtaUrl }}" target="_blank" rel="nofollow sponsored noopener"
                               class="inline-flex items-center gap-2 bg-slate-900 text-white font-black text-sm px-6 py-3 rounded-full hover:bg-slate-800 transition-colors">
                                {{ $schoolAffiliate['label'] }}
                            </a>
                        @endif
                        @if(!empty($schoolAffiliate['provider']))
                            <p class="text-[10px] font-bold text-slate-400 mt-3">提供: {{ $schoolAffiliate['provider'] }}・PR</p>
                        @endif
                        @if(!empty($schoolAffiliate['imp_url']))
                            <img src="{{ $schoolAffiliate['imp_url'] }}" width="1" height="1" alt="" style="border:0;position:absolute;left:-9999px;" aria-hidden="true">
                        @endif
                    </div>
                </section>
            @endif

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

            {{-- 説明文（実データから組み立て・県ごとに数値が変わる） --}}
            <section>
                <p class="text-[13px] text-slate-600 leading-relaxed">
                    {{ $prefecture }}には二輪教習を行う指定自動車教習所が{{ number_format($schoolTotal) }}校あります。うち大型二輪に対応しているのは{{ number_format($schoolOogata) }}校です。免許を取ったあとに乗る中古バイクの相場や、県内のバイク環境もまとめました。
                </p>
            </section>

            {{-- 免許取得後に買える中古バイク（在庫0台ならセクションごと非表示）。価格は total_price。 --}}
            @if((int) ($prefStats['bike_total'] ?? 0) > 0)
            <section>
                <h2 class="text-lg font-black text-slate-900 mb-3">{{ $prefecture }}で免許を取ったあとに買える中古バイク</h2>
                <div class="bg-white rounded-2xl border border-slate-200 p-5">
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
                        <div class="bg-slate-50 rounded-xl p-3"><p class="text-[10px] font-bold text-slate-400">掲載台数</p><p class="text-sm font-black text-slate-900">{{ number_format((int) $prefStats['bike_total']) }}台</p></div>
                        @if((int) ($prefStats['bike_priced'] ?? 0) > 0)
                        <div class="bg-slate-50 rounded-xl p-3"><p class="text-[10px] font-bold text-slate-400">最低価格</p><p class="text-sm font-black text-slate-900">{{ number_format((int) $prefStats['bike_min']) }}円</p></div>
                        <div class="bg-slate-50 rounded-xl p-3"><p class="text-[10px] font-bold text-slate-400">最高価格</p><p class="text-sm font-black text-slate-900">{{ number_format((int) $prefStats['bike_max']) }}円</p></div>
                        <div class="bg-slate-50 rounded-xl p-3"><p class="text-[10px] font-bold text-slate-400">平均価格</p><p class="text-sm font-black text-slate-900">{{ number_format((int) $prefStats['bike_avg']) }}円</p></div>
                        @endif
                    </div>
                    <p class="text-[11px] text-slate-400 mb-1">※価格は支払総額（車両本体価格＋諸費用込み）の目安です。</p>
                    <p class="text-[11px] font-bold text-slate-400 mb-2 mt-3">免許区分（排気量帯）で探す</p>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                        <a href="{{ route('bikes.search', ['prefecture' => $prefecture, 'max_displacement' => 50]) }}" class="block rounded-xl border border-slate-200 p-3 hover:border-blue-400 transition">
                            <p class="text-sm font-black text-slate-900">〜50cc</p>
                            <p class="text-[11px] text-slate-500">原付免許 / {{ number_format((int) $prefStats['disp']['d50']) }}台</p>
                        </a>
                        <a href="{{ route('bikes.search', ['prefecture' => $prefecture, 'min_displacement' => 51, 'max_displacement' => 125]) }}" class="block rounded-xl border border-slate-200 p-3 hover:border-blue-400 transition">
                            <p class="text-sm font-black text-slate-900">51〜125cc</p>
                            <p class="text-[11px] text-slate-500">小型限定普通二輪 / {{ number_format((int) $prefStats['disp']['d125']) }}台</p>
                        </a>
                        <a href="{{ route('bikes.search', ['prefecture' => $prefecture, 'min_displacement' => 126, 'max_displacement' => 400]) }}" class="block rounded-xl border border-slate-200 p-3 hover:border-blue-400 transition">
                            <p class="text-sm font-black text-slate-900">126〜400cc</p>
                            <p class="text-[11px] text-slate-500">普通二輪 / {{ number_format((int) $prefStats['disp']['d400']) }}台</p>
                        </a>
                        <a href="{{ route('bikes.search', ['prefecture' => $prefecture, 'min_displacement' => 401]) }}" class="block rounded-xl border border-slate-200 p-3 hover:border-blue-400 transition">
                            <p class="text-sm font-black text-slate-900">401cc〜</p>
                            <p class="text-[11px] text-slate-500">大型二輪 / {{ number_format((int) $prefStats['disp']['d401']) }}台</p>
                        </a>
                    </div>
                </div>
            </section>
            @endif

            {{-- 県のバイク環境（件数0の行は出さない・全て0ならセクションごと非表示） --}}
            @if((int) ($prefStats['shops'] ?? 0) > 0 || (int) ($prefStats['garages'] ?? 0) > 0 || (int) ($prefStats['parkings'] ?? 0) > 0)
            <section>
                <h2 class="text-lg font-black text-slate-900 mb-3">{{ $prefecture }}のバイク環境</h2>
                <div class="grid sm:grid-cols-3 gap-3">
                    @if((int) ($prefStats['shops'] ?? 0) > 0)
                    <a href="{{ route('shops.area.prefecture', $prefecture) }}" class="block bg-white rounded-xl border border-slate-200 p-4 hover:border-blue-400 transition">
                        <p class="text-sm font-black text-slate-900">🏪 バイク販売店 {{ number_format((int) $prefStats['shops']) }}店</p>
                    </a>
                    @endif
                    @if((int) ($prefStats['garages'] ?? 0) > 0)
                    <a href="{{ route('rental-garage.area.prefecture', $prefecture) }}" class="block bg-white rounded-xl border border-slate-200 p-4 hover:border-blue-400 transition">
                        <p class="text-sm font-black text-slate-900">🔧 レンタルガレージ {{ number_format((int) $prefStats['garages']) }}件</p>
                    </a>
                    @endif
                    @if((int) ($prefStats['parkings'] ?? 0) > 0)
                    <a href="{{ route('parking.area.prefecture', $prefecture) }}" class="block bg-white rounded-xl border border-slate-200 p-4 hover:border-blue-400 transition">
                        <p class="text-sm font-black text-slate-900">🅿️ バイク駐車場 {{ number_format((int) $prefStats['parkings']) }}件</p>
                    </a>
                    @endif
                </div>
            </section>
            @endif

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

                var groups = {};
                var order = [];
                schools.forEach(function (s) {
                    var lat = parseFloat(s.latitude), lng = parseFloat(s.longitude);
                    if (isNaN(lat) || isNaN(lng)) return;
                    var key = lat + ',' + lng;
                    if (!groups[key]) {
                        groups[key] = { lat: lat, lng: lng, items: [] };
                        order.push(key);
                    }
                    groups[key].items.push(s);
                });
                function popupBlock(s) {
                    var title = s.official_url
                        ? '<a href="' + esc(s.official_url) + '" target="_blank" rel="nofollow noopener" style="color:#1d4ed8;font-weight:700;">' + esc(s.name) + '</a>'
                        : '<span style="font-weight:700;">' + esc(s.name) + '</span>';
                    return title
                        + '<br>' + esc(s.city)
                        + '<br>普通二輪：' + (s.futsuu_nirin ? '○' : '—')
                        + '　大型二輪：' + (s.oogata_nirin ? '○' : '—');
                }
                var markers = [];
                order.forEach(function (key) {
                    var g = groups[key];
                    var body = g.items.map(popupBlock).join('<hr style="margin:8px 0;border:0;border-top:1px solid #e2e8f0;">');
                    var head = g.items.length > 1
                        ? '<div style="font-weight:700;margin-bottom:6px;">この場所に' + g.items.length + '校</div>'
                        : '';
                    var marker = L.marker([g.lat, g.lng]).addTo(map);
                    marker.bindPopup('<div style="font-size:12px;line-height:1.6;">' + head + body + '</div>');
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
