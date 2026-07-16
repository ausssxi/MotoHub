<x-layout>
    @php $isRepair = ($shop->shop_type ?? null) === 'repair_only'; @endphp
    <x-slot:title>{{ $isRepair
        ? $shop->name . '（バイク整備・修理）｜' . $shop->prefecture . ($shop->city ?? '') . ' - MotoHub'
        : $shop->name . 'の在庫・取扱車両一覧' . ($stockCount > 0 ? '【' . number_format($stockCount) . '台】' : '') . '｜中古バイク検索 - MotoHub' }}</x-slot:title>

    <x-slot:metaDescription>{{ $description }}</x-slot:metaDescription>

    @php
        // 位置情報なし → noindex。販売店は在庫0台でも noindex（整備店は在庫0が正常なので除外）。
        $noindex = (! $shop->latitude || ! $shop->longitude) || (! $isRepair && ($pagination['total'] ?? 0) === 0);
    @endphp
    @if($noindex)
        <x-slot:robotsMeta>noindex, follow</x-slot:robotsMeta>
    @endif

    <x-slot:styles>
        @if($isRepair)
            <x-jsonld.auto-repair :shop="$shop" :description="$description" />
        @else
            <x-jsonld.local-business :shop="$shop" :stockCount="$pagination['total'] ?? 0" :description="$description" />
        @endif
        <x-jsonld.breadcrumb-shop :shop="$shop" />
        {{-- CSSの非同期読み込み（レンダリングブロック完全解除） --}}
        <link rel="preload" href="{{ asset('css/bike-search.css') }}?v={{ asset_buster(public_path('css/bike-search.css')) }}" as="style" onload="this.onload=null;this.rel='stylesheet'">
        <noscript><link rel="stylesheet" href="{{ asset('css/bike-search.css') }}?v={{ asset_buster(public_path('css/bike-search.css')) }}"></noscript>
    </x-slot:styles>

    <x-slot:scripts>
        <script src="{{ asset('js/search/sidebar.js') }}?v={{ asset_buster(public_path('js/search/sidebar.js')) }}" defer></script>
        <script src="{{ asset('js/compare/manager.js') }}?v={{ asset_buster(public_path('js/compare/manager.js')) }}" defer></script>
        <script src="{{ asset('js/compare/ui.js') }}?v={{ asset_buster(public_path('js/compare/ui.js')) }}" defer></script>
        <script src="{{ asset('js/search/save_condition.js') }}?v={{ asset_buster(public_path('js/search/save_condition.js')) }}" defer></script>
    </x-slot:scripts>

    <x-slot:navigation>
        <x-navigation :showSearch="true" />
    </x-slot:navigation>

    <div class="bg-gray-50 min-h-screen py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            
            {{-- パンくず --}}
            <nav class="flex text-xs font-bold text-gray-400 mb-6" aria-label="Breadcrumb">
                <ol class="flex items-center space-x-2">
                    <li><a href="/" class="hover:text-gray-600 transition-colors">HOME</a></li>
                    <li><span class="text-gray-300">＞</span></li>
                    <li><a href="{{ route('bikes.prefectures') }}" class="hover:text-gray-600 transition-colors">ショップを探す</a></li>
                    <li><span class="text-gray-300">＞</span></li>
                    <li><span class="text-gray-800">{{ $shop->name }}</span></li>
                </ol>
            </nav>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
                {{-- 左カラム: 店舗情報 --}}
                <div class="lg:col-span-4 space-y-6 lg:sticky lg:top-24 lg:self-start">
                    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8">
                        <div class="text-center mb-6">
                            <div class="w-24 h-24 rounded-full bg-gray-100 mx-auto mb-4 overflow-hidden border-2 border-white shadow-sm flex items-center justify-center">
                                @if($shop->image_url)
                                    <img src="{{ $shop->image_url }}" alt="{{ $shop->name }}" class="w-full h-full object-cover" loading="lazy" decoding="async">
                                @else
                                    <i data-lucide="store" class="w-10 h-10 text-gray-300"></i>
                                @endif
                            </div>
                            <h1 class="text-xl font-black text-gray-900 leading-tight mb-2">{{ $shop->name }}</h1>
                            <span class="inline-block bg-blue-50 text-blue-600 text-[10px] font-bold px-2 py-1 rounded-full border border-blue-100">
                                {{ $shop->prefecture }}
                            </span>
                            @if(($shop->source ?? null) === \App\Models\Shop::SOURCE_USER)
                            <span class="inline-flex items-center gap-1 bg-emerald-50 text-emerald-700 text-[10px] font-bold px-2 py-1 rounded-full border border-emerald-100 ml-1">
                                <i data-lucide="users" class="w-3 h-3"></i> ユーザー投稿による掲載
                            </span>
                            @endif
                            <p class="text-sm text-gray-500 mt-3">{{ $description }}</p>
                        </div>

                        <div class="space-y-4 text-sm">
                            <div class="flex items-start gap-3">
                                <i data-lucide="map-pin" class="w-4 h-4 text-gray-400 mt-0.5 shrink-0"></i>
                                <p class="font-bold text-gray-600">{{ $shop->address }}</p>
                            </div>
                            
                            {{-- ★追加: 地図で見るボタン --}}
                            @if($shop->latitude && $shop->longitude)
                            <div class="flex gap-2">
                                <a href="https://www.google.com/maps/dir/?api=1&destination={{ $shop->latitude }},{{ $shop->longitude }}"
                                   target="_blank"
                                   class="flex-1 bg-blue-600 text-white hover:bg-blue-700 font-black text-center py-3 rounded-xl transition flex items-center justify-center gap-2 text-sm">
                                    <i data-lucide="navigation" class="w-4 h-4"></i>
                                    ルート案内
                                </a>
                                <a href="{{ route('shops.map', ['lat' => $shop->latitude, 'lng' => $shop->longitude, 'shop_id' => $shop->id]) }}"
                                   class="flex-1 bg-gray-100 text-gray-700 hover:bg-gray-200 font-black text-center py-3 rounded-xl transition flex items-center justify-center gap-2 text-sm">
                                    <i data-lucide="map" class="w-4 h-4"></i>
                                    地図で見る
                                </a>
                            </div>
                            @endif

                            <div class="border-t border-gray-100 my-4"></div>

                            <div class="flex items-start gap-3">
                                <i data-lucide="phone" class="w-4 h-4 text-gray-400 mt-0.5 shrink-0"></i>
                                <p class="font-bold text-gray-600">{{ $shop->phone ?? '-' }}</p>
                            </div>
                            <div class="flex items-start gap-3">
                                <i data-lucide="clock" class="w-4 h-4 text-gray-400 mt-0.5 shrink-0"></i>
                                <p class="font-bold text-gray-600">{{ $shop->business_hours ?? '-' }}</p>
                            </div>
                            @if($shop->closed_days && $shop->closed_days !== '-')
                            <div class="flex items-start gap-3">
                                <i data-lucide="calendar-off" class="w-4 h-4 text-gray-400 mt-0.5 shrink-0"></i>
                                <p class="font-bold text-gray-600">{{ $shop->closed_days }}</p>
                            </div>
                            @endif
                        </div>

                        {{-- 対応サービス（Webikeバッジ） --}}
                        @if(!empty($shop->service_tags))
                        <div class="mt-6 pt-6 border-t border-gray-100">
                            <p class="text-xs font-black text-gray-500 mb-3 flex items-center gap-1.5">
                                <i data-lucide="wrench" class="w-3.5 h-3.5 text-green-600"></i>
                                対応サービス
                            </p>
                            <x-shop-service-tags :tags="$shop->service_tags" />
                        </div>
                        @endif

                        {{-- 利用者からの情報（承認済みのユーザー投稿・スクレイプデータとは別系統） --}}
                        @php $accFlags = \App\Models\ShopAcceptanceReport::FLAGS; @endphp
                        {{-- 事実系フラグ（total）が0でも、即反映コメントがあればブロックを出す --}}
                        @if(($acceptanceSummary['total'] ?? 0) > 0 || !empty($acceptanceSummary['comments']))
                        <div class="mt-6 pt-6 border-t border-gray-100">
                            <p class="text-xs font-black text-gray-500 mb-3 flex items-center gap-1.5">
                                <i data-lucide="users" class="w-3.5 h-3.5 text-blue-600"></i>
                                利用者からの情報
                            </p>
                            <div class="flex flex-wrap gap-1.5 mb-3">
                                @foreach($accFlags as $col => $label)
                                    @if(($acceptanceSummary['counts'][$col] ?? 0) > 0)
                                    <span class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full border bg-blue-50 text-blue-700 border-blue-100">
                                        ☑ {{ $label }}
                                        <span class="text-blue-400">{{ $acceptanceSummary['counts'][$col] }}人</span>
                                    </span>
                                    @endif
                                @endforeach
                            </div>
                            @if(session('report_success'))
                            <div class="mb-2 text-[11px] font-bold text-gray-600 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2">
                                報告を受け付けました。確認します。ご協力ありがとうございます。
                            </div>
                            @endif
                            @if(!empty($acceptanceSummary['comments']))
                            <ul class="space-y-1.5">
                                @foreach($acceptanceSummary['comments'] as $cmt)
                                <li class="text-xs text-gray-600 bg-gray-50 rounded-lg px-3 py-2 leading-relaxed" x-data="{ report: false }">
                                    <span class="flex items-center gap-1 mb-0.5">
                                        <x-user-avatar :url="$cmt['avatar_url'] ?? null" :name="$cmt['name']" :size="6" />
                                        <span class="font-bold text-gray-500">{{ $cmt['name'] }}さん</span>
                                        @if($cmt['verified'])
                                        <span class="inline-flex items-center gap-0.5 text-[9px] font-black text-blue-600 bg-blue-50 px-1 py-0.5 rounded">
                                            <i data-lucide="badge-check" class="w-2.5 h-2.5"></i>ログインユーザー
                                        </span>
                                        @endif
                                        {{-- 通報ボタン（控えめ・通報したことは他ユーザーに見えない） --}}
                                        <button type="button" @click="report = !report"
                                            class="ml-auto inline-flex items-center gap-0.5 text-[9px] font-bold text-gray-300 hover:text-red-500 transition-colors"
                                            aria-label="この投稿を報告する">
                                            <i data-lucide="flag" class="w-2.5 h-2.5"></i>報告
                                        </button>
                                    </span>
                                    「{{ $cmt['comment'] }}」

                                    {{-- 報告フォーム（理由選択・即送信） --}}
                                    <form x-show="report" x-cloak method="POST" action="{{ route('reports.store') }}" class="mt-2 pt-2 border-t border-gray-200 space-y-1.5">
                                        @csrf
                                        <input type="hidden" name="type" value="shop_comment">
                                        <input type="hidden" name="id" value="{{ $cmt['id'] }}">
                                        <p class="text-[10px] font-bold text-gray-500">報告の理由（任意）</p>
                                        <div class="flex flex-wrap gap-1.5">
                                            @foreach(\App\Models\Report::REASONS as $key => $label)
                                            <label class="inline-flex items-center gap-1 cursor-pointer text-[10px] text-gray-600 bg-white border border-gray-200 rounded-full px-2 py-0.5">
                                                <input type="radio" name="reason" value="{{ $key }}" class="accent-red-500 w-2.5 h-2.5">{{ $label }}
                                            </label>
                                            @endforeach
                                        </div>
                                        {{-- ハニーポット（人間には非表示・ボット除け） --}}
                                        <input type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true" class="hidden" style="display:none">
                                        <button type="submit" class="text-[10px] font-black text-white bg-red-500 hover:bg-red-600 rounded-lg px-3 py-1 transition">報告する</button>
                                    </form>
                                </li>
                                @endforeach
                            </ul>
                            @endif
                            <p class="text-[10px] text-gray-400 mt-2">※ 利用者の報告に基づく情報です（運営確認済み）。不適切な投稿は各コメントの「報告」からお知らせください。</p>
                        </div>
                        @endif

                        {{-- 受け入れ情報を投稿するフォーム（承認制・ポジティブ項目のみ） --}}
                        <div class="mt-6 pt-6 border-t border-gray-100" x-data="{ open: false }">
                            @if(session('acceptance_success') === 'instant')
                            <div class="mb-3 text-xs font-bold text-green-700 bg-green-50 border border-green-200 rounded-xl px-3 py-2">
                                ありがとうございます。コメントを掲載しました（事実系の情報は確認後に反映されます）。
                            </div>
                            @elseif(session('acceptance_success'))
                            <div class="mb-3 text-xs font-bold text-green-700 bg-green-50 border border-green-200 rounded-xl px-3 py-2">
                                ありがとうございます。確認後に掲載されます。
                            </div>
                            @endif
                            <button type="button" @click="open = !open"
                                class="w-full flex items-center justify-center gap-1.5 text-xs font-black text-gray-500 hover:text-blue-600 transition-colors">
                                <i data-lucide="message-square-plus" class="w-3.5 h-3.5"></i>
                                このお店の情報を教える
                            </button>

                            <form x-show="open" x-cloak method="POST" action="{{ route('shops.acceptance-report', $shop) }}" class="mt-4 space-y-3">
                                @csrf
                                <p class="text-[11px] text-gray-500 leading-relaxed">このお店で「してもらえたこと・対応してもらえたこと」を教えてください。</p>

                                {{-- 投稿者名（Reviewパターン: ログイン時は公開ハンドル・匿名時は入力） --}}
                                @auth
                                    @if(empty(auth()->user()->review_display_name))
                                    <div>
                                        <input type="text" name="submitter_name" maxlength="30" placeholder="公開表示名（初回のみ・以降固定）"
                                            class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
                                        <p class="text-[10px] text-gray-400 mt-1">※ 一度設定すると、以降の投稿・レビューで共通の表示名になります。</p>
                                    </div>
                                    @else
                                    <p class="text-[11px] text-gray-500 font-bold">「{{ auth()->user()->review_display_name }}」として投稿します。</p>
                                    @endif
                                @else
                                    <input type="text" name="submitter_name" maxlength="30" placeholder="お名前（任意・未入力なら「名無しライダー」）"
                                        class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
                                @endauth

                                @error('submitter_name')
                                <p class="text-[11px] font-bold text-rose-600">{{ $message }}</p>
                                @enderror

                                <div class="space-y-2">
                                    @foreach($accFlags as $col => $label)
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" name="{{ $col }}" value="1" class="accent-blue-600">
                                        <span class="text-sm font-bold text-gray-700">{{ $label }}</span>
                                    </label>
                                    @endforeach
                                </div>

                                <textarea name="comment" maxlength="120" rows="2"
                                    placeholder="例: 〇〇を直してもらえました（任意・120文字まで）"
                                    class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none"></textarea>

                                {{-- ハニーポット（人間には非表示・ボット除け） --}}
                                <input type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true"
                                       class="hidden" style="display:none">

                                @error('accepts_other_store')
                                <p class="text-[11px] font-bold text-rose-600">{{ $message }}</p>
                                @enderror

                                <button type="submit"
                                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-black text-sm px-4 py-2.5 rounded-xl transition active:scale-[0.99]">
                                    情報を送信する
                                </button>
                                <p class="text-[10px] text-gray-400 leading-relaxed">
                                    ※ 評価・点数の投稿はできません。投稿は運営の確認後に掲載されます。
                                </p>
                            </form>
                        </div>

                        {{-- 公式サイト（ユーザー由来リンクのためSEOスパム対策で nofollow ugc） --}}
                        @if(!empty($shop->official_site_url))
                        <div class="mt-6 pt-6 border-t border-gray-100">
                            <a href="{{ $shop->official_site_url }}" target="_blank" rel="nofollow ugc noopener" class="flex items-center justify-center gap-2 text-xs font-bold text-gray-400 hover:text-blue-600 transition-colors">
                                公式サイト <i data-lucide="external-link" class="w-3 h-3"></i>
                            </a>
                        </div>
                        @else
                        {{-- 公式URL未登録: ユーザーからのURL提案（承認制・shop_submissions再利用） --}}
                        <div class="mt-6 pt-6 border-t border-gray-100" x-data="{ open: false }">
                            @if(session('suggest_url_success'))
                            <div class="mb-2 text-xs font-bold text-green-700 bg-green-50 border border-green-200 rounded-lg px-3 py-2">
                                ありがとうございます。承認後に反映されます。
                            </div>
                            @endif
                            <button type="button" @click="open = !open"
                                class="w-full flex items-center justify-center gap-1.5 text-xs font-black text-gray-400 hover:text-blue-600 transition-colors">
                                <i data-lucide="link" class="w-3.5 h-3.5"></i> 公式サイトを教える
                            </button>
                            <form x-show="open" x-cloak method="POST" action="{{ route('shops.suggest-url', $shop) }}" class="mt-3 space-y-2">
                                @csrf
                                <input type="url" name="website_url" required placeholder="https://..."
                                       class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
                                {{-- ハニーポット --}}
                                <input type="text" name="fax_number" tabindex="-1" autocomplete="off" aria-hidden="true" class="hidden" style="display:none">
                                @error('website_url')<p class="text-[11px] font-bold text-rose-600">{{ $message }}</p>@enderror
                                <button type="submit"
                                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-black text-xs px-4 py-2 rounded-xl transition active:scale-[0.99]">
                                    送信する
                                </button>
                                <p class="text-[10px] text-gray-400">※ 承認後に公式サイトリンクとして掲載されます。</p>
                            </form>
                        </div>
                        @endif

                        @if($shop->latitude && $shop->longitude)
                        <div class="mt-4">
                            <a href="{{ route('parking.index', ['lat' => $shop->latitude, 'lng' => $shop->longitude]) }}"
                               class="flex items-center justify-center gap-2 w-full bg-green-50 border border-green-200 text-green-700 hover:bg-green-100 font-bold text-xs py-3 rounded-xl transition">
                                <i data-lucide="square-parking" class="w-4 h-4"></i>
                                この店舗の近くの駐車場を探す
                            </a>
                        </div>
                        @endif

                        {{-- 地図 / ストリートビュー（タブ切り替え、Maps JavaScript API 共用） --}}
                        @if($shop->latitude && $shop->longitude && config('services.google_maps.api_key'))
                        <div class="mt-6 pt-6 border-t border-gray-100" id="map-section" x-data="{ tab: 'map' }">
                            <div class="flex items-center gap-1 mb-3">
                                <button @click="tab = 'map'" :class="tab === 'map' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'" class="flex items-center gap-1.5 text-xs font-bold px-3 py-1.5 rounded-lg transition" id="map-tab-btn">
                                    <i data-lucide="map" class="w-3.5 h-3.5"></i> 地図
                                </button>
                                <button @click="tab = 'sv'" :class="tab === 'sv' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'" class="flex items-center gap-1.5 text-xs font-bold px-3 py-1.5 rounded-lg transition" id="sv-tab-btn">
                                    <i data-lucide="camera" class="w-3.5 h-3.5"></i> ストリートビュー
                                </button>
                            </div>
                            <div x-show="tab === 'map'" id="map-container" class="w-full rounded-xl bg-gray-100" style="height:300px"></div>
                            <div x-show="tab === 'sv'" x-cloak id="street-view" class="w-full rounded-xl bg-gray-100" style="height:300px"></div>
                        </div>
                        <script>
                        (function() {
                            var apiLoaded = false, mapInit = false, svInit = false;
                            var pos = {lat: {{ $shop->latitude }}, lng: {{ $shop->longitude }}};

                            function loadApi(callback) {
                                if (apiLoaded) { callback(); return; }
                                apiLoaded = true;
                                window._onMapsReady = callback;
                                var s = document.createElement('script');
                                s.src = 'https://maps.googleapis.com/maps/api/js?key={{ config('services.google_maps.api_key') }}&callback=_onMapsReady';
                                s.async = true;
                                s.defer = true;
                                document.head.appendChild(s);
                            }

                            function initMap() {
                                if (mapInit) return;
                                mapInit = true;
                                var map = new google.maps.Map(document.getElementById('map-container'), {
                                    center: pos, zoom: 15, mapTypeControl: false, streetViewControl: false
                                });
                                new google.maps.Marker({ position: pos, map: map });
                            }

                            function initSV() {
                                if (svInit) return;
                                svInit = true;
                                var el = document.getElementById('street-view');
                                var sv = new google.maps.StreetViewService();
                                sv.getPanorama({location: pos, radius: 200}, function(data, status) {
                                    if (status === 'OK') {
                                        new google.maps.StreetViewPanorama(el, {
                                            position: data.location.latLng, pov: {heading: 0, pitch: 0}, zoom: 1
                                        });
                                    } else {
                                        el.innerHTML = '<div class="flex items-center justify-center h-full text-sm text-gray-400 font-bold">この地点のストリートビューはありません</div>';
                                    }
                                });
                            }

                            // 地図タブ: IntersectionObserverで遅延ロード
                            var observed = false;
                            var observer = new IntersectionObserver(function(entries) {
                                if (entries[0].isIntersecting && !observed) {
                                    observed = true;
                                    observer.disconnect();
                                    loadApi(initMap);
                                }
                            }, {rootMargin: '200px'});
                            observer.observe(document.getElementById('map-section'));

                            // SVタブ: クリック時に初期化
                            document.getElementById('sv-tab-btn').addEventListener('click', function() {
                                loadApi(initSV);
                            });
                            // 地図タブに戻った時のリサイズ対応
                            document.getElementById('map-tab-btn').addEventListener('click', function() {
                                if (mapInit && window.google) {
                                    setTimeout(function() { google.maps.event.trigger(document.getElementById('map-container'), 'resize'); }, 100);
                                }
                            });
                        })();
                        </script>
                        @endif
                    </div>

                    {{-- 取扱メーカー --}}
                    @if(isset($manufacturers) && $manufacturers->isNotEmpty())
                    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8">
                        <h2 class="text-base font-black text-gray-900 mb-4 flex items-center gap-2">
                            <i data-lucide="factory" class="w-4 h-4 text-blue-500"></i>
                            取扱メーカー
                        </h2>
                        <div class="flex flex-wrap gap-2">
                            @foreach($manufacturers as $mfr)
                            <a href="{{ route('bikes.search', ['manufacturer_id' => $mfr->id, 'shop_id' => $shop->id]) }}"
                               class="inline-flex items-center gap-1.5 bg-gray-50 hover:bg-blue-50 text-gray-700 hover:text-blue-700 text-xs font-bold px-3 py-2 rounded-lg border border-gray-100 hover:border-blue-200 transition-colors">
                                {{ $mfr->name }}
                                <span class="text-[10px] text-gray-400">({{ $mfr->stock_count }})</span>
                            </a>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    {{-- 諸経費傾向 --}}
                    @if(!empty($shopExpensesStats))
                    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8">
                        <h2 class="text-base font-black text-gray-900 mb-4 flex items-center gap-2">
                            <i data-lucide="receipt-japanese-yen" class="w-4 h-4 text-blue-500"></i>
                            諸経費の傾向
                            <span class="text-[10px] text-gray-400 font-normal ml-1">在庫{{ $shopExpensesStats['count'] }}台から算出</span>
                        </h2>

                        <div class="flex justify-between items-end mb-4">
                            <div>
                                <p class="text-[10px] text-gray-400 font-bold">このショップの平均</p>
                                <p class="text-xl font-black text-gray-900">{{ number_format($shopExpensesStats['avg']) }}<span class="text-sm font-bold text-gray-400">円</span></p>
                            </div>
                            <div class="text-right">
                                <p class="text-[10px] text-gray-400 font-bold">全国平均</p>
                                <p class="text-xl font-black text-gray-400">{{ number_format($shopExpensesStats['nationalAvg']) }}<span class="text-sm font-bold text-gray-300">円</span></p>
                            </div>
                        </div>

                        {{-- バーグラフ --}}
                        <div class="mb-4">
                            <div class="flex justify-between text-[10px] text-gray-400 font-bold mb-1">
                                <span>安い</span>
                                <span>高い</span>
                            </div>
                            <div class="flex gap-0.5">
                                @for($i = 1; $i <= 10; $i++)
                                    @php
                                        $pos = $shopExpensesStats['barPosition'];
                                        if ($i <= $pos) {
                                            $barColor = $pos <= 4 ? 'bg-green-400' : ($pos <= 6 ? 'bg-gray-300' : 'bg-orange-400');
                                        } else {
                                            $barColor = 'bg-gray-100';
                                        }
                                    @endphp
                                    <div class="h-3 flex-1 rounded-sm {{ $barColor }}"></div>
                                @endfor
                            </div>
                        </div>

                        <div class="flex items-center gap-2 text-sm font-bold {{ $shopExpensesStats['evaluation']['color'] === 'green' ? 'text-green-600' : ($shopExpensesStats['evaluation']['color'] === 'orange' ? 'text-orange-600' : 'text-gray-600') }}">
                            <i data-lucide="{{ $shopExpensesStats['evaluation']['icon'] }}" class="w-4 h-4"></i>
                            <span>{{ $shopExpensesStats['evaluation']['text'] }}</span>
                            @if($shopExpensesStats['diff'] != 0)
                                <span class="text-xs text-gray-400 font-normal">（全国平均より{{ $shopExpensesStats['diff'] > 0 ? '+' : '' }}{{ number_format($shopExpensesStats['diff']) }}円）</span>
                            @endif
                        </div>

                        <div class="mt-3 flex gap-4 text-[10px] text-gray-400 font-bold border-t border-gray-50 pt-3">
                            <span>最安: {{ number_format($shopExpensesStats['min']) }}円</span>
                            <span>最高: {{ number_format($shopExpensesStats['max']) }}円</span>
                        </div>
                    </div>
                    @endif

                    {{-- 販売実績 --}}
                    @if(!empty($salesStats))
                    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8">
                        <h2 class="text-base font-black text-gray-900 mb-4 flex items-center gap-2">
                            <i data-lucide="trending-up" class="w-4 h-4 text-emerald-500"></i>
                            販売実績（過去3ヶ月）
                        </h2>

                        <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:8px" class="mb-4">
                            <div class="bg-gray-50 rounded-xl p-3 text-center">
                                <p class="text-[10px] font-bold text-gray-400 mb-0.5">販売台数</p>
                                <p class="text-xl font-black text-gray-900">{{ number_format($salesStats['totalSold']) }}<span class="text-xs text-gray-400">台</span></p>
                            </div>
                            <div class="bg-gray-50 rounded-xl p-3 text-center">
                                <p class="text-[10px] font-bold text-gray-400 mb-0.5">平均在庫日数</p>
                                <p class="text-xl font-black text-gray-900">{{ $salesStats['avgDays'] }}<span class="text-xs text-gray-400">日</span></p>
                            </div>
                        </div>

                        {{-- 販売推移ミニバー --}}
                        @if(!empty($salesStats['monthlySales']))
                        <p class="text-[10px] font-bold text-gray-400 mb-2">月別販売推移</p>
                        <div class="flex items-end gap-1 h-16 mb-4">
                            @php $maxMonthlyCnt = max(array_column($salesStats['monthlySales'], 'count')) ?: 1; @endphp
                            @foreach($salesStats['monthlySales'] as $ms)
                            <div class="flex-1 flex flex-col items-center gap-0.5">
                                <span class="text-[9px] font-bold text-gray-400">{{ $ms['count'] }}</span>
                                <div class="w-full bg-emerald-400 rounded-t-sm" style="height: {{ max(($ms['count'] / $maxMonthlyCnt) * 40, 2) }}px"></div>
                                <span class="text-[9px] font-bold text-gray-400">{{ $ms['label'] }}</span>
                            </div>
                            @endforeach
                        </div>
                        @endif

                        {{-- 人気車種TOP5 --}}
                        @if($salesStats['topModels']->isNotEmpty())
                        <p class="text-[10px] font-bold text-gray-400 mb-2">よく売れている車種</p>
                        <div class="space-y-1.5">
                            @foreach($salesStats['topModels'] as $tm)
                            <div class="flex items-center justify-between gap-2">
                                <a href="{{ $tm['seo_url'] ?? '#' }}" class="text-xs font-bold text-gray-700 hover:text-blue-600 truncate transition-colors">{{ $tm['name'] }}</a>
                                <span class="text-xs font-black text-emerald-600 flex-shrink-0">{{ $tm['sold_count'] }}台</span>
                            </div>
                            @endforeach
                        </div>
                        @endif
                    </div>
                    @endif
                </div>

                {{-- 右カラム: 在庫リスト --}}
                <div class="lg:col-span-8">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-xl font-black text-gray-900 flex items-center gap-2">
                            <i data-lucide="bike" class="w-5 h-5 text-blue-500"></i>
                            在庫車両
                            <span class="text-sm font-bold text-gray-400 ml-1">({{ number_format($pagination['total']) }}台)</span>
                        </h2>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6">
                        @forelse ($items as $listing)
                            @include('bikes.partials.bike_card', ['listing' => $listing, 'isFirstView' => $loop->index < 4])
                        @empty
                            <div class="col-span-full py-6 sm:py-10 text-center bg-white rounded-2xl border border-dashed border-gray-200">
                                <i data-lucide="bike" class="w-8 h-8 text-gray-300 mx-auto mb-2"></i>
                                <p class="text-gray-400 font-bold text-sm">現在、在庫はありません</p>
                                @if(!empty($chainInfo))
                                <div class="mt-4 mx-auto max-w-sm bg-blue-50 border border-blue-200 rounded-xl p-4">
                                    <p class="text-sm font-bold text-blue-900">{{ $chainInfo['name'] }}の在庫は一括管理されています</p>
                                    <p class="text-xs text-blue-600 mt-1">現在 {{ number_format($chainInfo['stock']) }}台 の在庫があります</p>
                                    <a href="{{ route('shops.show', $chainInfo['main_shop_id']) }}" class="flex items-center justify-center gap-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-bold w-full py-3 rounded-lg mt-3 transition-colors">
                                        <i data-lucide="bike" class="w-4 h-4"></i>
                                        在庫を見る
                                    </a>
                                </div>
                                @endif
                            </div>
                        @endforelse
                    </div>

                    {{-- ページネーション --}}
                    @if($pagination['last_page'] > 1)
                    <div class="mt-12 flex justify-center">
                        <div class="flex gap-2">
                            @if($pagination['prev_url'])
                                <a href="{{ $pagination['prev_url'] }}" class="w-10 h-10 flex items-center justify-center rounded-lg bg-white border border-gray-200 hover:border-black transition"><i data-lucide="chevron-left" class="w-4 h-4"></i></a>
                            @endif
                            @foreach($pagination['pages'] as $page)
                                @if($page['is_dot']) <span class="px-1 text-gray-300">...</span>
                                @else <a href="{{ $page['url'] }}" class="w-10 h-10 flex items-center justify-center rounded-lg font-black text-sm transition {{ $page['is_active'] ? 'bg-black text-white shadow-lg' : 'bg-white border border-gray-200 text-gray-400 hover:border-black' }}">{{ $page['label'] }}</a>
                                @endif
                            @endforeach
                            @if($pagination['next_url'])
                                <a href="{{ $pagination['next_url'] }}" class="w-10 h-10 flex items-center justify-center rounded-lg bg-white border border-gray-200 hover:border-black transition"><i data-lucide="chevron-right" class="w-4 h-4"></i></a>
                            @endif
                        </div>
                    </div>
                    @endif

                    {{-- 取扱車種 → 公開済み適合表への内部リンク（在庫から派生・リンクのみ・空なら非表示） --}}
                    @include('shops.partials.fitment_links')

                    {{-- 在庫0台時は近くのショップ・駐車場を右カラム内に表示して空白を埋める --}}
                    @if($pagination['total'] === 0)
                    <div class="mt-6 space-y-6">
                        <x-nearby-shops :nearbyShops="$nearbyShops" :latitude="$shop->latitude" :longitude="$shop->longitude" />
                        <x-nearby-parkings :nearbyParkings="$nearbyParkings" :latitude="$shop->latitude" :longitude="$shop->longitude" />
                    </div>
                    @endif

                    {{-- エリア検索への導線 --}}
                    @if(!empty($shop->prefecture))
                    <div class="mt-8 sm:mt-16 bg-blue-50/50 rounded-3xl p-5 sm:p-8 border border-blue-100 text-center shadow-sm">
                        <h3 class="text-base sm:text-lg font-black text-blue-900 mb-2 sm:mb-3 flex items-center justify-center gap-2">
                            <i data-lucide="map-pin" class="w-5 h-5 text-blue-600"></i> {{ $shop->prefecture }}のバイクをもっと探す
                        </h3>
                        <p class="text-xs text-gray-600 font-bold mb-4 sm:mb-6">
                            「{{ $shop->name }}」がある{{ $shop->prefecture }}内の他店舗の在庫も、一括で比較・検索できます！
                        </p>
                        <a href="{{ route('bikes.search', ['prefecture' => $shop->prefecture]) }}"
                           class="inline-flex items-center justify-center gap-2 bg-white border-2 border-blue-600 text-blue-600 hover:bg-blue-600 hover:text-white font-black py-3.5 px-8 rounded-xl transition-all shadow-sm group w-full sm:w-auto">
                            {{ $shop->prefecture }}の中古・新車一覧を見る
                            <i data-lucide="arrow-right" class="w-4 h-4 group-hover:translate-x-1 transition-transform"></i>
                        </a>
                    </div>
                    @endif
                </div>
            </div>

            {{-- 訪問済みボタン --}}
            <div class="mt-8 bg-indigo-50 rounded-2xl p-5 border border-indigo-100 text-center max-w-md mx-auto">
                <p class="text-sm font-bold text-gray-800 mb-3">この店舗に行ったことはありますか？</p>
                <button onclick="markVisited({{ $shop->id }})" id="visited-btn"
                    class="bg-indigo-600 text-white font-bold text-sm px-6 py-2.5 rounded-xl hover:bg-indigo-700 transition-colors">
                    行ったことある！
                </button>
                <p class="text-xs text-gray-400 mt-2" id="visited-count" @if(empty($shop->visited_count)) style="display:none" @endif>
                    {{ $shop->visited_count ?? 0 }}人が訪問済み
                </p>
            </div>
            <script>
            function markVisited(shopId) {
                fetch(`/shops/${shopId}/visited`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Content-Type': 'application/json'
                    }
                })
                .then(r => r.json())
                .then(data => {
                    const btn = document.getElementById('visited-btn');
                    btn.textContent = '訪問済み！';
                    btn.disabled = true;
                    btn.classList.replace('bg-indigo-600', 'bg-gray-400');
                    btn.classList.remove('hover:bg-indigo-700');
                    var vc = document.getElementById('visited-count');
                    vc.textContent = data.count + '人が訪問済み';
                    vc.style.display = '';
                });
            }
            </script>

            {{-- 近くの駐車場・ショップ・回遊リンク（在庫0台時は右カラム内に表示済みなのでスキップ） --}}
            <div class="mt-8 sm:mt-12 space-y-6">
                @if($pagination['total'] > 0)
                <x-nearby-parkings :nearbyParkings="$nearbyParkings" :latitude="$shop->latitude" :longitude="$shop->longitude" />
                <x-nearby-shops :nearbyShops="$nearbyShops" :latitude="$shop->latitude" :longitude="$shop->longitude" />
                @endif
                <x-cross-links :crossLinks="$crossLinks" />

                {{-- 他のショップを店名で探す（同県プリセット） --}}
                <div>
                    <h2 class="text-base font-black text-gray-900 mb-3 flex items-center gap-2">
                        <i data-lucide="search" class="w-5 h-5 text-blue-500"></i>
                        他のバイクショップを探す
                    </h2>
                    <x-shop-name-search :pref="$shop->prefecture ?? ''" />
                </div>
            </div>
        </div>
    </div>
</x-layout>
