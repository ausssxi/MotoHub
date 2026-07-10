<x-layout>
    <x-slot:title>{{ $parking->clean_name }}｜{{ $parking->price_per_month ? '月極' . number_format($parking->price_per_month) . '円' : ($parking->price_per_hour ? number_format($parking->price_per_hour) . '円/時間' : '') }}{{ ($parking->price_per_month || $parking->price_per_hour) ? ' ' : '' }}バイク駐車場{{ $parking->city ? ' ' . $parking->city : '' }} - MotoHub</x-slot:title>
    <x-slot:metaDescription>{{ $parking->prefecture || $parking->city ? ($parking->prefecture ?? '') . ($parking->city ?? '') . 'のバイク駐車場' : 'バイク駐車場' }}「{{ $parking->clean_name }}」の詳細。{{ $parking->getPriceDisplay() ? $parking->getPriceDisplay() . '。' : '' }}ユーザーレビューも掲載。</x-slot:metaDescription>

    @if(in_array($parking->management_company, ['akippa株式会社', '株式会社アース・カー'], true))
        <x-slot:robotsMeta>noindex, follow</x-slot:robotsMeta>
    @endif
    <x-slot:publishedTime>{{ $parking->created_at->toIso8601String() }}</x-slot:publishedTime>
    <x-slot:modifiedTime>{{ $parking->updated_at->toIso8601String() }}</x-slot:modifiedTime>

    <x-slot:styles>
        <x-jsonld.parking :parking="$parking" />
        <x-jsonld.breadcrumb-parking :parking="$parking" />
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
        <style>
            #detail-map { height: 250px; z-index: 10; border-radius: 12px; }
            .scrollbar-hide::-webkit-scrollbar { display: none; }
            .scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
        </style>
    </x-slot:styles>

    <x-slot:scripts>
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const lat = {{ $parking->latitude }};
                const lng = {{ $parking->longitude }};
                const map = L.map('detail-map').setView([lat, lng], 16);
                L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>'
                }).addTo(map);
                L.marker([lat, lng]).addTo(map);
                if (typeof lucide !== 'undefined') lucide.createIcons();
            });

            function setRating(rating) {
                document.getElementById('rating-input').value = rating;
                document.getElementById('review-detail-form').classList.remove('hidden');

                document.querySelectorAll('.rating-star').forEach(btn => {
                    const r = parseInt(btn.dataset.rating);
                    btn.textContent = r <= rating ? '\u2605' : '\u2606';
                    btn.classList.toggle('bg-yellow-100', r <= rating);
                    btn.classList.toggle('text-yellow-500', r <= rating);
                    btn.classList.toggle('bg-gray-100', r > rating);
                });
            }

        </script>
    </x-slot:scripts>

    <x-slot:navigation>
        <x-navigation :showSearch="true" />
    </x-slot:navigation>

    <div class="bg-gray-50 min-h-screen py-8">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">

            {{-- ===== 13. パンくずリスト改善（都道府県→市区町村→駅→駐車場名） ===== --}}
            <nav class="overflow-x-auto text-xs font-bold text-gray-400 mb-6 scrollbar-hide" aria-label="Breadcrumb">
                <ol class="flex items-center space-x-2 whitespace-nowrap">
                    <li><a href="/" class="hover:text-gray-600 transition-colors">HOME</a></li>
                    <li><span class="text-gray-300">></span></li>
                    <li><a href="{{ route('parking.index') }}" class="hover:text-gray-600 transition-colors">駐車場マップ</a></li>
                    @if($parking->prefecture)
                    <li><span class="text-gray-300">></span></li>
                    <li><a href="{{ route('parking.area.prefecture', $parking->prefecture) }}" class="hover:text-gray-600 transition-colors">{{ $parking->prefecture }}</a></li>
                    @endif
                    {{-- prefecture が null だと route 生成が "Missing parameter: prefecture" で例外→ページ全体が500になるため両方必須 --}}
                    @if($parking->prefecture && $parking->city)
                    <li><span class="text-gray-300">></span></li>
                    <li><a href="{{ route('parking.area.city', [$parking->prefecture, $parking->city]) }}" class="hover:text-gray-600 transition-colors">{{ $parking->city }}</a></li>
                    @endif
                    @if($parking->station)
                    <li><span class="text-gray-300">></span></li>
                    <li><a href="{{ route('parking.station.show', $parking->station->slug) }}" class="hover:text-gray-600 transition-colors">{{ $parking->station->name }}駅</a></li>
                    @endif
                    <li><span class="text-gray-300">></span></li>
                    <li><span class="text-gray-800">{{ $parking->name }}</span></li>
                </ol>
            </nav>

            {{-- ===== 1. 料金ヒーロー表示 ===== --}}
            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8 mb-6">
                <div class="flex items-start justify-between mb-4">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-3 mb-2 flex-wrap">
                            <h1 class="text-xl sm:text-2xl font-black text-gray-900">{{ $parking->name }}</h1>
                            @auth
                            @if(auth()->user()->is_admin || $parking->user_id === auth()->id())
                            <a href="{{ route('parking.edit', $parking) }}"
                               class="inline-flex items-center gap-1 text-xs font-bold text-gray-400 hover:text-blue-600 bg-gray-100 hover:bg-blue-50 px-2.5 py-1 rounded-lg transition-colors shrink-0">
                                <i data-lucide="pencil" class="w-3 h-3"></i> 編集する
                            </a>
                            @endif
                            @endauth
                        </div>
                        @if($parking->address)
                        <p class="text-sm text-gray-500 flex items-center gap-1.5 mb-3">
                            <i data-lucide="map-pin" class="w-3.5 h-3.5 text-gray-400 shrink-0"></i>
                            {{ $parking->address }}
                        </p>
                        @endif
                    </div>
                    @if($parking->avg_rating > 0)
                    <div class="text-center shrink-0 ml-4">
                        <div class="text-2xl font-black text-yellow-500">{{ number_format($parking->avg_rating, 1) }}</div>
                        <div class="text-[10px] text-gray-400 font-bold">{{ $parking->reviews_count }}件</div>
                    </div>
                    @endif
                </div>

                {{-- 料金ヒーローカード --}}
                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5 mb-5">
                    @if($parking->is_free)
                    <div>
                        <div class="text-sm text-gray-500 mb-1">料金</div>
                        <span class="text-3xl font-bold text-green-600">無料</span>
                    </div>
                    @else
                    @php
                        $hasHour = (bool) $parking->price_per_hour;
                        $subPrices = array_filter([
                            $parking->price_per_day ? ['label' => '日額', 'price' => $parking->price_per_day, 'unit' => '/日'] : null,
                            $parking->price_per_month ? ['label' => '月極', 'price' => $parking->price_per_month, 'unit' => '/月'] : null,
                        ]);
                    @endphp
                    <div class="flex items-stretch">
                        {{-- メイン料金（時間貸し or 最初に見つかった料金） --}}
                        @if($hasHour)
                        <div class="flex-1 min-w-0">
                            <div class="text-sm text-gray-500 mb-1">時間貸し料金</div>
                            <div class="flex items-baseline gap-1">
                                <span class="text-3xl font-bold text-gray-900">¥{{ number_format($parking->price_per_hour) }}</span>
                                <span class="text-sm text-gray-500">/1時間</span>
                                @if($parking->available_hours)
                                <span class="text-xs text-gray-400 ml-1">{{ $parking->available_hours }}</span>
                                @endif
                            </div>
                        </div>
                        @elseif(!empty($subPrices))
                        @php $main = array_shift($subPrices); @endphp
                        <div class="flex-1 min-w-0">
                            <div class="text-sm text-gray-500 mb-1">{{ $main['label'] }}料金</div>
                            <div class="flex items-baseline gap-1">
                                <span class="text-3xl font-bold text-gray-900">¥{{ number_format($main['price']) }}</span>
                                <span class="text-sm text-gray-500">{{ $main['unit'] }}</span>
                            </div>
                        </div>
                        @endif

                        {{-- サブ料金（border-l区切りで横並び） --}}
                        @if(!empty($subPrices))
                        @foreach($subPrices as $sub)
                        <div class="border-l border-gray-200 pl-5 ml-5 flex-shrink-0">
                            <div class="text-xs text-gray-400 mb-1">{{ $sub['label'] }}</div>
                            <div class="flex items-baseline gap-1">
                                <span class="text-xl font-bold text-gray-700">¥{{ number_format($sub['price']) }}</span>
                                <span class="text-xs text-gray-400">{{ $sub['unit'] }}</span>
                            </div>
                        </div>
                        @endforeach
                        @endif
                    </div>
                    @endif

                    {{-- バッジ --}}
                    <div class="flex flex-wrap gap-1.5 mt-3">
                        <span class="inline-flex items-center gap-1 bg-green-100 text-green-700 text-[11px] font-bold px-2.5 py-1 rounded-full">
                            <i data-lucide="square-parking" class="w-3 h-3"></i>
                            {{ $parking->getParkingTypeLabel() }}
                        </span>
                        @if($parking->available_24h)
                        <span class="inline-flex items-center gap-1 bg-indigo-100 text-indigo-700 text-[11px] font-bold px-2.5 py-1 rounded-full">
                            <i data-lucide="clock" class="w-3 h-3"></i> 24h利用可
                        </span>
                        @endif
                        @if($parking->is_covered)
                        <span class="inline-flex items-center gap-1 bg-cyan-100 text-cyan-700 text-[11px] font-bold px-2.5 py-1 rounded-full">
                            <i data-lucide="umbrella" class="w-3 h-3"></i> 屋根あり
                        </span>
                        @endif
                        @if($parking->is_locked)
                        <span class="inline-flex items-center gap-1 bg-purple-100 text-purple-700 text-[11px] font-bold px-2.5 py-1 rounded-full">
                            <i data-lucide="lock" class="w-3 h-3"></i> 施錠可能
                        </span>
                        @endif
                        @if($parking->has_security_camera)
                        <span class="inline-flex items-center gap-1 bg-slate-100 text-slate-700 text-[11px] font-bold px-2.5 py-1 rounded-full">
                            <i data-lucide="cctv" class="w-3 h-3"></i> 防犯カメラ
                        </span>
                        @endif
                        @if($parking->is_free)
                        <span class="inline-flex items-center gap-1 bg-yellow-100 text-yellow-700 text-[11px] font-bold px-2.5 py-1 rounded-full">
                            無料
                        </span>
                        @endif
                        @if($parking->is_verified)
                        <span class="inline-flex items-center gap-1 bg-blue-100 text-blue-700 text-[11px] font-bold px-2.5 py-1 rounded-full">
                            <i data-lucide="badge-check" class="w-3 h-3"></i> 確認済み
                        </span>
                        @endif
                        @if(!empty($displacementLimit['label']))
                        <span class="inline-flex items-center gap-1 bg-red-100 text-red-700 text-[11px] font-bold px-2.5 py-1 rounded-full">
                            <i data-lucide="alert-triangle" class="w-3 h-3"></i> {{ $displacementLimit['label'] }}
                        </span>
                        @endif
                    </div>

                    @if($parking->notes || $parking->price_detail)
                    <p class="text-sm text-gray-500 mt-2">※{{ $parking->notes ?: $parking->price_detail }}</p>
                    @endif
                </div>

                {{-- 基本情報テーブル --}}
                <div class="space-y-3 mb-5">
                    @if($parking->tel)
                    <div class="flex items-start gap-3">
                        <i data-lucide="phone" class="w-4 h-4 text-gray-400 mt-0.5 shrink-0"></i>
                        <a href="tel:{{ $parking->tel }}" class="text-sm text-blue-600 hover:underline">{{ $parking->tel }}</a>
                    </div>
                    @endif
                    @if($parking->capacity)
                    <div class="flex items-start gap-3">
                        <i data-lucide="car" class="w-4 h-4 text-gray-400 mt-0.5 shrink-0"></i>
                        <span class="text-sm text-gray-700">収容台数: {{ $parking->capacity }}台</span>
                    </div>
                    @endif
                    @if($parking->available_hours)
                    <div class="flex items-start gap-3">
                        <i data-lucide="clock" class="w-4 h-4 text-gray-400 mt-0.5 shrink-0"></i>
                        <span class="text-sm text-gray-700">{{ $parking->available_hours }}</span>
                    </div>
                    @endif
                    @if($parking->closed_days)
                    <div class="flex items-start gap-3">
                        <i data-lucide="calendar-off" class="w-4 h-4 text-gray-400 mt-0.5 shrink-0"></i>
                        <span class="text-sm text-gray-700">{{ $parking->closed_days }}</span>
                    </div>
                    @endif
                    @if($parking->parking_form)
                    <div class="flex items-start gap-3">
                        <i data-lucide="tag" class="w-4 h-4 text-gray-400 mt-0.5 shrink-0"></i>
                        <span class="text-sm text-gray-700">{{ $parking->parking_form }}</span>
                    </div>
                    @endif
                    @if($parking->vehicle_restriction)
                    <div class="flex items-start gap-3">
                        <i data-lucide="alert-triangle" class="w-4 h-4 text-yellow-500 mt-0.5 shrink-0"></i>
                        <span class="text-sm text-gray-700">{{ $parking->vehicle_restriction }}</span>
                    </div>
                    @endif
                </div>

                {{-- description（notesと別の補足情報がある場合のみ表示） --}}
                @if($parking->description && $parking->description !== $parking->notes)
                <div class="bg-gray-50 rounded-xl p-4 mb-5">
                    <p class="text-sm text-gray-700 whitespace-pre-line">{{ $parking->description }}</p>
                </div>
                @endif

                {{-- 管理会社・データソース --}}
                <div class="space-y-1 text-xs text-gray-400">
                    @if($parking->management_company)
                    <p>管理会社: {{ $parking->management_company }}</p>
                    @endif
                    @if($parking->jmpsa_updated_at)
                    <p>情報更新日: {{ $parking->jmpsa_updated_at->format('Y年n月j日') }}</p>
                    @endif
                    @if($parking->source_url)
                    <p>出典: <a href="{{ $parking->source_url }}" target="_blank" rel="noopener noreferrer" class="text-blue-500 hover:underline">{{ parse_url($parking->source_url, PHP_URL_HOST) }}</a></p>
                    @endif
                </div>
            </div>

            {{-- 口コミサマリー（検索流入者のファーストビュー用・料金の下に埋もれさせず本体へアンカー） --}}
            <a href="#reviews" class="block bg-white rounded-2xl shadow-sm border border-gray-100 p-4 mb-6 hover:border-green-200 transition-colors">
                <div class="flex items-center justify-between gap-3">
                    @if($reviews->count() > 0)
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="flex items-center gap-1 shrink-0">
                            <span class="text-2xl font-black text-yellow-500">{{ number_format($parking->avg_rating, 1) }}</span>
                            <i data-lucide="star" class="w-5 h-5 text-yellow-400 fill-yellow-400"></i>
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-black text-gray-800">利用者の口コミ {{ $reviews->count() }}件</p>
                            <p class="text-xs text-gray-400 truncate">停めやすさ・見つけやすさの実際の声</p>
                        </div>
                    </div>
                    <span class="shrink-0 inline-flex items-center gap-1 text-xs font-black text-green-700 bg-green-50 px-3 py-1.5 rounded-full">口コミを見る<i data-lucide="chevron-down" class="w-3.5 h-3.5"></i></span>
                    @else
                    <div class="min-w-0">
                        <p class="text-sm font-black text-gray-800">この駐車場の口コミはまだありません</p>
                        <p class="text-xs text-gray-400">最初の一人になって、停めやすさを教えてください。</p>
                    </div>
                    <span class="shrink-0 inline-flex items-center gap-1 text-xs font-black text-white bg-green-600 px-3 py-1.5 rounded-full">口コミを書く<i data-lucide="chevron-down" class="w-3.5 h-3.5"></i></span>
                    @endif
                </div>
            </a>

            {{-- ===== 3. 写真ギャラリー（カルーセル表示） ===== --}}
            @if($parking->images->isNotEmpty())
            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 mb-6">
                <h2 class="text-sm font-black text-gray-900 mb-3 flex items-center gap-2">
                    <i data-lucide="images" class="w-4 h-4 text-green-600"></i> 写真 ({{ $parking->images->count() }}枚)
                </h2>
                {{-- メイン画像 --}}
                <div x-data="{ current: 0, total: {{ $parking->images->count() }} }" class="relative">
                    <div class="aspect-[16/9] rounded-xl overflow-hidden bg-gray-100 mb-3">
                        @foreach($parking->images as $idx => $image)
                        <img x-show="current === {{ $idx }}"
                             x-transition:enter="transition ease-out duration-300"
                             x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                             src="{{ asset('storage/' . $image->image_path) }}"
                             alt="{{ $parking->name }} 写真{{ $idx + 1 }}"
                             class="w-full h-full object-cover cursor-pointer"
                             loading="{{ $idx === 0 ? 'eager' : 'lazy' }}"
                             @click="$dispatch('open-lightbox', { index: {{ $idx }} })">
                        @endforeach
                    </div>
                    @if($parking->images->count() > 1)
                    <button @click="current = (current - 1 + total) % total" class="absolute left-2 top-1/2 -translate-y-1/2 bg-black/40 hover:bg-black/60 text-white rounded-full w-8 h-8 flex items-center justify-center transition">
                        <i data-lucide="chevron-left" class="w-5 h-5"></i>
                    </button>
                    <button @click="current = (current + 1) % total" class="absolute right-2 top-1/2 -translate-y-1/2 bg-black/40 hover:bg-black/60 text-white rounded-full w-8 h-8 flex items-center justify-center transition">
                        <i data-lucide="chevron-right" class="w-5 h-5"></i>
                    </button>
                    <div class="absolute bottom-4 left-1/2 -translate-x-1/2 bg-black/50 text-white text-xs font-bold px-3 py-1 rounded-full">
                        <span x-text="current + 1"></span> / {{ $parking->images->count() }}
                    </div>
                    @endif
                </div>
                {{-- サムネイル --}}
                @if($parking->images->count() > 1)
                <div class="flex gap-2 overflow-x-auto pb-1 scrollbar-hide" x-ref="thumbs">
                    @foreach($parking->images as $idx => $image)
                    <button @click="current = {{ $idx }}" class="shrink-0 w-16 h-12 rounded-lg overflow-hidden border-2 transition-colors"
                            :class="current === {{ $idx }} ? 'border-green-500' : 'border-transparent hover:border-gray-300'">
                        <img src="{{ asset('storage/' . $image->image_path) }}" alt="" class="w-full h-full object-cover" loading="lazy">
                    </button>
                    @endforeach
                </div>
                @endif
            </div>

            {{-- ライトボックスモーダル --}}
            <div id="lightbox" class="fixed inset-0 z-[9999] bg-black/90 hidden items-center justify-center" onclick="closeLightbox(event)">
                <button type="button" onclick="closeLightbox()" class="absolute top-4 right-4 text-white/70 hover:text-white transition z-10 p-2">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
                <button type="button" id="lb-prev" onclick="event.stopPropagation(); lbNav(-1)" class="absolute left-2 sm:left-4 text-white/70 hover:text-white transition p-2 z-10">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </button>
                <button type="button" id="lb-next" onclick="event.stopPropagation(); lbNav(1)" class="absolute right-2 sm:right-4 text-white/70 hover:text-white transition p-2 z-10">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </button>
                <img id="lb-img" src="" alt="" class="max-w-[90vw] max-h-[85vh] object-contain rounded-lg" onclick="event.stopPropagation()">
                <span id="lb-counter" class="absolute bottom-4 left-1/2 -translate-x-1/2 text-white/60 text-xs font-bold"></span>
            </div>
            <script>
            (function() {
                const images = @json($parking->images->pluck('image_path')->map(fn($p) => asset('storage/' . $p)));
                let current = 0;
                window.openLightbox = function(idx) {
                    current = idx;
                    updateLb();
                    document.getElementById('lightbox').classList.remove('hidden');
                    document.getElementById('lightbox').classList.add('flex');
                    document.body.style.overflow = 'hidden';
                };
                window.closeLightbox = function(e) {
                    if (e && e.target !== document.getElementById('lightbox')) return;
                    document.getElementById('lightbox').classList.add('hidden');
                    document.getElementById('lightbox').classList.remove('flex');
                    document.body.style.overflow = '';
                };
                window.lbNav = function(dir) {
                    current = (current + dir + images.length) % images.length;
                    updateLb();
                };
                function updateLb() {
                    document.getElementById('lb-img').src = images[current];
                    document.getElementById('lb-counter').textContent = (current + 1) + ' / ' + images.length;
                    document.getElementById('lb-prev').style.display = images.length > 1 ? '' : 'none';
                    document.getElementById('lb-next').style.display = images.length > 1 ? '' : 'none';
                }
                document.addEventListener('keydown', function(e) {
                    if (document.getElementById('lightbox').classList.contains('hidden')) return;
                    if (e.key === 'Escape') closeLightbox();
                    if (e.key === 'ArrowLeft') lbNav(-1);
                    if (e.key === 'ArrowRight') lbNav(1);
                });
                window.addEventListener('open-lightbox', function(e) { openLightbox(e.detail.index); });
            })();
            </script>
            @endif

            {{-- ===== 4. 入庫可能バイク例 + 6. バイクサイズチェッカー ===== --}}
            @if(!empty($bikeCompatibility))
            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 mb-6" x-data="bikeChecker()">
                <h2 class="text-sm font-black text-gray-900 mb-4 flex items-center gap-2">
                    <i data-lucide="bike" class="w-4 h-4 text-green-600"></i> 入庫可能バイク判定
                </h2>

                {{-- サンプルバイク一覧 --}}
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mb-5">
                    @foreach($bikeCompatibility as $bike)
                    @php
                        $ok = true;
                        if (!empty($displacementLimit['min']) && $bike['displacement'] < $displacementLimit['min']) $ok = false;
                        if (!empty($displacementLimit['max']) && $bike['displacement'] > $displacementLimit['max']) $ok = false;
                    @endphp
                    <div class="flex items-center gap-2 bg-gray-50 rounded-lg px-3 py-2">
                        <span class="text-base">{{ $ok ? '✅' : '❌' }}</span>
                        <div class="min-w-0">
                            <div class="text-xs font-bold text-gray-800 truncate">{{ $bike['name'] }}</div>
                            <div class="text-[10px] text-gray-400">{{ $bike['displacement'] }}cc</div>
                        </div>
                    </div>
                    @endforeach
                </div>

                {{-- インタラクティブチェッカー --}}
                <div class="bg-blue-50 rounded-xl p-4">
                    <p class="text-xs font-bold text-gray-600 mb-2">あなたのバイクは停められる？</p>
                    <div class="flex gap-2">
                        <input type="number" x-model="inputCc" placeholder="排気量を入力（例: 250）"
                               class="flex-1 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-400 focus:border-transparent transition bg-white"
                               min="0" max="2000" @keyup.enter="checkBike()">
                        <span class="text-sm text-gray-400 self-center">cc</span>
                        <button @click="checkBike()" class="bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs px-4 py-2 rounded-lg transition-colors">
                            判定
                        </button>
                    </div>
                    <div x-show="result !== null" x-transition class="mt-3 text-sm font-bold text-center" :class="result ? 'text-green-600' : 'text-red-600'">
                        <span x-text="result ? '✅ ' + inputCc + 'ccのバイクは駐車可能です' : '❌ ' + inputCc + 'ccのバイクは制限により駐車できない可能性があります'"></span>
                    </div>
                </div>
            </div>
            <script>
            function bikeChecker() {
                return {
                    inputCc: '',
                    result: null,
                    minCc: {{ $displacementLimit['min'] ?? 'null' }},
                    maxCc: {{ $displacementLimit['max'] ?? 'null' }},
                    checkBike() {
                        const cc = parseInt(this.inputCc);
                        if (!cc || cc <= 0) { this.result = null; return; }
                        let ok = true;
                        if (this.minCc !== null && cc < this.minCc) ok = false;
                        if (this.maxCc !== null && cc > this.maxCc) ok = false;
                        this.result = ok;
                    }
                };
            }
            </script>
            @endif

            {{-- 地図 + 12. Google Mapsルート案内 --}}
            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 mb-6">
                <h2 class="text-sm font-black text-gray-900 mb-3 flex items-center gap-2">
                    <i data-lucide="map" class="w-4 h-4 text-green-600"></i> 位置情報
                </h2>
                <div id="detail-map" class="w-full"></div>
                <div class="mt-3 flex flex-col sm:flex-row items-center justify-center gap-2">
                    <a href="https://www.google.com/maps/dir/?api=1&destination={{ $parking->latitude }},{{ $parking->longitude }}" target="_blank" rel="noopener noreferrer"
                       class="inline-flex items-center justify-center gap-2 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs py-2.5 px-5 rounded-xl transition-colors shadow-sm">
                        <i data-lucide="navigation" class="w-4 h-4"></i>
                        Google Maps でルートを表示
                    </a>
                    <a href="{{ route('parking.index', ['lat' => $parking->latitude, 'lng' => $parking->longitude]) }}" class="text-xs font-bold text-green-600 hover:text-green-700 transition">
                        周辺の駐車場を探す →
                    </a>
                </div>

                {{-- ===== 8. 近隣施設情報 ===== --}}
                @if($parking->station)
                @php
                    $stationDist = null;
                    if ($parking->station->latitude && $parking->station->longitude) {
                        $r = 6371000;
                        $dLat = deg2rad($parking->station->latitude - $parking->latitude);
                        $dLng = deg2rad($parking->station->longitude - $parking->longitude);
                        $a2 = sin($dLat/2)**2 + cos(deg2rad($parking->latitude)) * cos(deg2rad($parking->station->latitude)) * sin($dLng/2)**2;
                        $stationDist = (int) round($r * 2 * atan2(sqrt($a2), sqrt(1 - $a2)));
                    }
                @endphp
                <div class="mt-4 pt-4 border-t border-gray-100">
                    <p class="text-xs font-bold text-gray-500 mb-2">最寄り駅</p>
                    <div class="flex items-center gap-2">
                        <i data-lucide="train-front" class="w-4 h-4 text-blue-500"></i>
                        <a href="{{ route('parking.station.show', $parking->station->slug) }}" class="text-sm font-bold text-blue-600 hover:underline">{{ $parking->station->name }}駅</a>
                        @if($stationDist)
                        <span class="text-xs text-gray-400 font-bold">（約{{ $stationDist < 1000 ? $stationDist . 'm' : number_format($stationDist / 1000, 1) . 'km' }}）</span>
                        @endif
                    </div>
                </div>
                @endif
            </div>

            {{-- ストリートビュー（IntersectionObserverで遅延読み込み） --}}
            @if($parking->latitude && $parking->longitude && config('services.google_maps.api_key'))
            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 mb-6" id="street-view-section">
                <h2 class="text-sm font-black text-gray-900 mb-3 flex items-center gap-2">
                    <i data-lucide="camera" class="w-4 h-4 text-green-600"></i> ストリートビューで入口を確認
                </h2>
                <div id="street-view" class="w-full rounded-xl bg-gray-100" style="height:400px"></div>
            </div>
            <script>
            (function() {
                var loaded = false;
                var observer = new IntersectionObserver(function(entries) {
                    if (entries[0].isIntersecting && !loaded) {
                        loaded = true;
                        observer.disconnect();
                        var s = document.createElement('script');
                        s.src = 'https://maps.googleapis.com/maps/api/js?key={{ config('services.google_maps.api_key') }}&callback=_initParkingSV';
                        s.async = true;
                        s.defer = true;
                        document.head.appendChild(s);
                    }
                }, {rootMargin: '200px'});
                observer.observe(document.getElementById('street-view-section'));

                window._initParkingSV = function() {
                    var el = document.getElementById('street-view');
                    var sv = new google.maps.StreetViewService();
                    var pos = {lat: {{ $parking->latitude }}, lng: {{ $parking->longitude }}};
                    sv.getPanorama({location: pos, radius: 50}, function(data, status) {
                        if (status === 'OK') {
                            new google.maps.StreetViewPanorama(el, {
                                position: data.location.latLng,
                                pov: {heading: 0, pitch: 0},
                                zoom: 1
                            });
                        } else {
                            document.getElementById('street-view-section').style.display = 'none';
                        }
                    });
                };
            })();
            </script>
            @endif

            {{-- ===== 最寄り駅の駐車場ページへの内部リンク ===== --}}
            @if($parking->station)
            <a href="{{ route('parking.station.show', $parking->station->slug) }}"
               class="block bg-gradient-to-r from-blue-50 to-indigo-50 rounded-3xl border border-blue-100 p-5 mb-6 hover:shadow-md hover:border-blue-200 transition-all group">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="bg-blue-100 rounded-xl p-2.5 shrink-0 group-hover:bg-blue-200 transition-colors">
                            <i data-lucide="train-front" class="w-5 h-5 text-blue-600"></i>
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-black text-gray-900 truncate">{{ $parking->station->name }}駅周辺のバイク駐車場をもっと見る</p>
                            <p class="text-xs text-gray-500 mt-0.5">{{ $parking->station->name }}駅の駐車場一覧・料金比較</p>
                        </div>
                    </div>
                    <i data-lucide="chevron-right" class="w-5 h-5 text-blue-400 shrink-0 group-hover:translate-x-0.5 transition-transform"></i>
                </div>
            </a>
            @endif

            {{-- ===== 9. 料金比較テーブル ===== --}}
            @if(!empty($priceComparison))
            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 mb-6">
                <h2 class="text-sm font-black text-gray-900 mb-4 flex items-center gap-2">
                    <i data-lucide="scale" class="w-4 h-4 text-amber-500"></i> 周辺駐車場との料金比較（1km以内）
                </h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b-2 border-gray-200">
                                <th class="text-left py-2 px-3 font-bold text-gray-500">駐車場名</th>
                                <th class="text-center py-2 px-3 font-bold text-gray-500">距離</th>
                                <th class="text-center py-2 px-3 font-bold text-gray-500">時間</th>
                                <th class="text-center py-2 px-3 font-bold text-gray-500">月極</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            {{-- 自分自身 --}}
                            <tr class="bg-blue-50">
                                <td class="py-2 px-3 font-black text-blue-700 text-xs truncate max-w-[150px]">{{ $parking->name }}</td>
                                <td class="py-2 px-3 text-center text-xs text-gray-400">-</td>
                                <td class="py-2 px-3 text-center font-bold text-blue-600">{{ $parking->price_per_hour ? number_format($parking->price_per_hour) . '円' : '-' }}</td>
                                <td class="py-2 px-3 text-center font-bold text-blue-600">{{ $parking->price_per_month ? number_format($parking->price_per_month) . '円' : '-' }}</td>
                            </tr>
                            @foreach($priceComparison as $comp)
                            <tr>
                                <td class="py-2 px-3 text-xs truncate max-w-[150px]">
                                    <a href="{{ route('parking.show', $comp['id']) }}" class="text-gray-700 hover:text-blue-600 font-bold">{{ $comp['name'] }}</a>
                                </td>
                                <td class="py-2 px-3 text-center text-xs text-gray-400">{{ $comp['distance_m'] }}m</td>
                                <td class="py-2 px-3 text-center text-xs font-bold {{ ($comp['price_per_hour'] ?? 0) > 0 && $comp['price_per_hour'] < ($parking->price_per_hour ?? PHP_INT_MAX) ? 'text-green-600' : 'text-gray-600' }}">
                                    {{ $comp['price_per_hour'] ? number_format($comp['price_per_hour']) . '円' : ($comp['is_free'] ? '無料' : '-') }}
                                </td>
                                <td class="py-2 px-3 text-center text-xs font-bold {{ ($comp['price_per_month'] ?? 0) > 0 && $comp['price_per_month'] < ($parking->price_per_month ?? PHP_INT_MAX) ? 'text-green-600' : 'text-gray-600' }}">
                                    {{ $comp['price_per_month'] ? number_format($comp['price_per_month']) . '円' : '-' }}
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="text-[10px] text-gray-400 mt-2">※ 安い料金は緑色で表示されます</p>
            </div>
            @endif

            {{-- 料金シミュレーター --}}
            @if($parking->price_per_hour || $parking->price_per_day)
            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 mb-6">
                <h2 class="text-sm font-black text-gray-900 mb-4 flex items-center gap-2">
                    <i data-lucide="calculator" class="w-4 h-4 text-blue-600"></i> 料金シミュレーター
                </h2>

                <div id="parking-fee-result" class="bg-gradient-to-br from-blue-50 to-slate-50 rounded-2xl p-5 mb-4 border border-blue-100">
                    <p id="pf-duration" class="text-xs font-bold text-blue-500 text-center mb-1">3時間の駐車</p>
                    <div id="pf-cards" class="flex justify-center gap-3"></div>
                    <div id="pf-monthly" class="text-center mt-3 pt-3 border-t border-blue-100 hidden">
                        <span class="text-[11px] text-gray-400">月極契約なら</span>
                        <span class="text-sm font-black text-gray-700 ml-1">月{{ number_format($parking->price_per_month ?? 0) }}円</span>
                    </div>
                    <p id="pf-error" class="text-sm text-red-500 font-bold text-center hidden"></p>
                    <p class="text-[10px] text-gray-400 text-center mt-2">※実際の料金体系と異なる場合があります。詳しくは現地の看板をご確認ください。</p>
                </div>

                <div class="space-y-3 mb-3">
                    <div>
                        <label class="text-[11px] font-bold text-gray-400 mb-1 block uppercase tracking-wider">入庫</label>
                        <div class="flex items-center gap-1.5">
                            <input type="date" id="ps-date" class="flex-1 min-w-0 border border-gray-200 rounded-lg px-2.5 py-2 text-sm focus:ring-2 focus:ring-blue-400 focus:border-transparent transition bg-gray-50">
                            <select id="ps-hour" class="border border-gray-200 rounded-lg px-1.5 py-2 text-sm focus:ring-2 focus:ring-blue-400 focus:border-transparent transition appearance-none bg-gray-50 text-center w-[60px]">
                                @for($h = 0; $h <= 23; $h++)
                                <option value="{{ $h }}">{{ $h }}時</option>
                                @endfor
                            </select>
                            <select id="ps-min" class="border border-gray-200 rounded-lg px-1.5 py-2 text-sm focus:ring-2 focus:ring-blue-400 focus:border-transparent transition appearance-none bg-gray-50 text-center w-[60px]">
                                <option value="0">00分</option>
                                <option value="15">15分</option>
                                <option value="30">30分</option>
                                <option value="45">45分</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="text-[11px] font-bold text-gray-400 mb-1 block uppercase tracking-wider">出庫</label>
                        <div class="flex items-center gap-1.5">
                            <input type="date" id="pe-date" class="flex-1 min-w-0 border border-gray-200 rounded-lg px-2.5 py-2 text-sm focus:ring-2 focus:ring-blue-400 focus:border-transparent transition bg-gray-50">
                            <select id="pe-hour" class="border border-gray-200 rounded-lg px-1.5 py-2 text-sm focus:ring-2 focus:ring-blue-400 focus:border-transparent transition appearance-none bg-gray-50 text-center w-[60px]">
                                @for($h = 0; $h <= 23; $h++)
                                <option value="{{ $h }}">{{ $h }}時</option>
                                @endfor
                            </select>
                            <select id="pe-min" class="border border-gray-200 rounded-lg px-1.5 py-2 text-sm focus:ring-2 focus:ring-blue-400 focus:border-transparent transition appearance-none bg-gray-50 text-center w-[60px]">
                                <option value="0">00分</option>
                                <option value="15">15分</option>
                                <option value="30">30分</option>
                                <option value="45">45分</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="text-center">
                    <button type="button" id="calc-parking-fee"
                        class="inline-flex items-center gap-1.5 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs px-6 py-2 rounded-lg transition-colors shadow-sm">
                        <i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i> 再計算
                    </button>
                </div>
            </div>
            <script>
            (function() {
                var pricePerHour = {{ $parking->price_per_hour ?? 'null' }};
                var pricePerDay = {{ $parking->price_per_day ?? 'null' }};
                var hasMonthly = {{ $parking->price_per_month ? 'true' : 'false' }};

                function calcFee() {
                    var sd = document.getElementById('ps-date').value;
                    var ed = document.getElementById('pe-date').value;
                    var durationEl = document.getElementById('pf-duration');
                    var cardsEl = document.getElementById('pf-cards');
                    var monthlyEl = document.getElementById('pf-monthly');
                    var errorEl = document.getElementById('pf-error');

                    errorEl.classList.add('hidden');
                    cardsEl.innerHTML = '';
                    durationEl.textContent = '';
                    monthlyEl.classList.add('hidden');

                    if (!sd || !ed) {
                        errorEl.textContent = '日付を入力してください';
                        errorEl.classList.remove('hidden');
                        return;
                    }

                    var start = new Date(sd + 'T' + String(document.getElementById('ps-hour').value).padStart(2,'0') + ':' + String(document.getElementById('ps-min').value).padStart(2,'0'));
                    var end = new Date(ed + 'T' + String(document.getElementById('pe-hour').value).padStart(2,'0') + ':' + String(document.getElementById('pe-min').value).padStart(2,'0'));
                    var diffMs = end - start;

                    if (diffMs <= 0) {
                        errorEl.textContent = '出庫日時は入庫日時より後にしてください';
                        errorEl.classList.remove('hidden');
                        return;
                    }

                    var totalMin = Math.ceil(diffMs / (1000 * 60));
                    var hours = Math.ceil(totalMin / 60);
                    var days = Math.ceil(totalMin / (60 * 24));

                    var dur;
                    if (totalMin < 60) {
                        dur = totalMin + '分';
                    } else if (hours < 24) {
                        var rm = totalMin % 60;
                        dur = Math.floor(totalMin / 60) + '時間' + (rm > 0 ? rm + '分' : '');
                    } else {
                        var rh = hours % 24;
                        dur = Math.floor(hours / 24) + '日' + (rh > 0 ? rh + '時間' : '');
                    }
                    durationEl.textContent = dur + 'の駐車';

                    var options = [];
                    if (pricePerHour) options.push({ fee: hours * pricePerHour, detail: hours + 'h × ' + pricePerHour.toLocaleString() + '円', type: '時間料金' });
                    if (pricePerDay) options.push({ fee: days * pricePerDay, detail: days + '日 × ' + pricePerDay.toLocaleString() + '円', type: '日額料金' });

                    if (options.length === 0) return;

                    options.sort(function(a, b) { return a.fee - b.fee; });

                    if (options.length === 2) {
                        var best = options[0];
                        var other = options[1];
                        cardsEl.innerHTML =
                            '<div class="flex-1 bg-white rounded-xl p-3 border-2 border-blue-400 shadow-sm text-center relative">' +
                                '<span class="absolute -top-2.5 left-1/2 -translate-x-1/2 bg-blue-500 text-white text-[9px] font-black px-2 py-0.5 rounded-full">お得</span>' +
                                '<p class="text-[10px] font-bold text-blue-500 mb-0.5">' + best.type + '</p>' +
                                '<p class="text-2xl font-black text-gray-900">' + best.fee.toLocaleString() + '<span class="text-xs font-bold text-gray-400">円</span></p>' +
                                '<p class="text-[10px] text-gray-400 mt-0.5">' + best.detail + '</p>' +
                            '</div>' +
                            '<div class="flex-1 bg-white rounded-xl p-3 border border-gray-200 text-center opacity-70">' +
                                '<p class="text-[10px] font-bold text-gray-400 mb-0.5">' + other.type + '</p>' +
                                '<p class="text-2xl font-black text-gray-400">' + other.fee.toLocaleString() + '<span class="text-xs font-bold text-gray-300">円</span></p>' +
                                '<p class="text-[10px] text-gray-300 mt-0.5">' + other.detail + '</p>' +
                            '</div>';
                    } else {
                        var o = options[0];
                        cardsEl.innerHTML =
                            '<div class="bg-white rounded-xl p-4 border border-blue-200 shadow-sm text-center min-w-[180px]">' +
                                '<p class="text-[10px] font-bold text-blue-500 mb-0.5">' + o.type + '</p>' +
                                '<p class="text-3xl font-black text-gray-900">' + o.fee.toLocaleString() + '<span class="text-sm font-bold text-gray-400">円</span></p>' +
                                '<p class="text-[10px] text-gray-400 mt-0.5">' + o.detail + '</p>' +
                            '</div>';
                    }

                    if (hasMonthly) monthlyEl.classList.remove('hidden');
                }

                var now = new Date();
                var mins = [0, 15, 30, 45];
                var nearMin = mins.reduce(function(a, b) { return Math.abs(b - now.getMinutes()) < Math.abs(a - now.getMinutes()) ? b : a; });
                var later = new Date(now.getTime() + 3 * 60 * 60 * 1000);
                var nearMinEnd = mins.reduce(function(a, b) { return Math.abs(b - later.getMinutes()) < Math.abs(a - later.getMinutes()) ? b : a; });
                var fmt = function(d) { return d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0'); };

                document.getElementById('ps-date').value = fmt(now);
                document.getElementById('ps-hour').value = now.getHours();
                document.getElementById('ps-min').value = nearMin;
                document.getElementById('pe-date').value = fmt(later);
                document.getElementById('pe-hour').value = later.getHours();
                document.getElementById('pe-min').value = nearMinEnd;

                calcFee();

                document.getElementById('calc-parking-fee').addEventListener('click', calcFee);
                ['ps-date','ps-hour','ps-min','pe-date','pe-hour','pe-min'].forEach(function(id) {
                    document.getElementById(id).addEventListener('change', calcFee);
                });
            })();
            </script>
            @endif

            {{-- 月額 vs 時間貸し比較 --}}
            @if($parking->price_per_hour && $parking->price_per_month)
            @php
                $hourlyMonthly = $parking->price_per_hour * 8 * 20;
                $monthly = $parking->price_per_month;
                $diff = abs($hourlyMonthly - $monthly);
            @endphp
            <div class="bg-amber-50 border border-amber-200 rounded-2xl px-5 py-4 mb-6">
                <p class="text-sm text-gray-700 leading-relaxed">
                    月20日・1日8時間利用する場合、時間料金だと月{{ number_format($hourlyMonthly) }}円。
                    @if($monthly < $hourlyMonthly)
                        月極契約なら月{{ number_format($monthly) }}円で、<span class="font-bold text-amber-700">月極の方が{{ number_format($diff) }}円お得</span>です。
                    @elseif($hourlyMonthly < $monthly)
                        月極契約は月{{ number_format($monthly) }}円なので、<span class="font-bold text-amber-700">時間料金の方が{{ number_format($diff) }}円お得</span>です。
                    @else
                        月極契約も月{{ number_format($monthly) }}円で、どちらも同額です。
                    @endif
                </p>
            </div>
            @endif

            {{-- レビュー一覧（口コミサマリーのアンカー先） --}}
            <div id="reviews" class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8 mb-6 scroll-mt-20">
                <h2 class="text-sm font-black text-gray-900 mb-4 flex items-center gap-2">
                    <i data-lucide="message-square" class="w-4 h-4 text-green-600"></i>
                    口コミ ({{ $reviews->count() }}件)
                </h2>

                @if(session('report_success'))
                <div class="mb-3 text-[11px] font-bold text-gray-600 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2">
                    報告を受け付けました。確認します。ご協力ありがとうございます。
                </div>
                @endif

                @if($reviews->isEmpty())
                {{-- 0件のみ: 一番乗り歓迎トーン（1件でもあれば通常表示） --}}
                <div class="text-center py-8">
                    <p class="text-sm text-gray-600 font-bold mb-1">まだレビューはありません。</p>
                    <p class="text-xs text-gray-500 leading-relaxed">最初の一人になって、停めやすさを教えてください。</p>
                </div>
                @else
                <div class="space-y-4" id="parking-review-list">
                    @foreach($reviews as $review)
                    <div class="border-b border-gray-50 pb-4 last:border-b-0 last:pb-0" x-data="{ report: false }">
                        <div class="flex items-center justify-between mb-2">
                            <div class="flex items-center gap-2">
                                {{-- 公開表示名（本名は出さない。ログイン投稿はハンドル・ゲストはニックネーム） --}}
                                <span class="text-sm font-bold text-gray-800">{{ $review->display_name }}</span>
                                <div class="flex items-center gap-0.5">
                                    @for($i = 1; $i <= 5; $i++)
                                    <i data-lucide="star" class="w-3 h-3 {{ $i <= $review->rating ? 'text-yellow-400 fill-yellow-400' : 'text-gray-200' }}"></i>
                                    @endfor
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-[10px] text-gray-400">{{ $review->created_at->format('Y/m/d') }}</span>
                                <button type="button" @click="report = !report"
                                    class="inline-flex items-center gap-0.5 text-[9px] font-bold text-gray-300 hover:text-red-500 transition-colors"
                                    aria-label="このレビューを報告する">
                                    <i data-lucide="flag" class="w-2.5 h-2.5"></i>報告
                                </button>
                            </div>
                        </div>
                        <p class="text-sm text-gray-700 whitespace-pre-line">{{ $review->body }}</p>
                        @if($review->visited_at)
                        <p class="text-[10px] text-gray-400 mt-1">訪問日: {{ $review->visited_at->format('Y/m/d') }}</p>
                        @endif

                        {{-- 報告フォーム（フェーズ1の reports.store 連携・polymorphic parking_review） --}}
                        <form x-show="report" x-cloak method="POST" action="{{ route('reports.store') }}" class="mt-2 pt-2 border-t border-gray-100 space-y-1.5">
                            @csrf
                            <input type="hidden" name="type" value="parking_review">
                            <input type="hidden" name="id" value="{{ $review->id }}">
                            <p class="text-[10px] font-bold text-gray-500">報告の理由（任意）</p>
                            <div class="flex flex-wrap gap-1.5">
                                @foreach(\App\Models\Report::REASONS as $key => $label)
                                <label class="inline-flex items-center gap-1 cursor-pointer text-[10px] text-gray-600 bg-gray-50 border border-gray-200 rounded-full px-2 py-0.5">
                                    <input type="radio" name="reason" value="{{ $key }}" class="accent-red-500 w-2.5 h-2.5">{{ $label }}
                                </label>
                                @endforeach
                            </div>
                            {{-- ハニーポット（人間には非表示・ボット除け） --}}
                            <input type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true" class="hidden" style="display:none">
                            <button type="submit" class="text-[10px] font-black text-white bg-red-500 hover:bg-red-600 rounded-lg px-3 py-1 transition">報告する</button>
                        </form>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>

            {{-- レビュー投稿フォーム（口コミ一覧の直後＝読んで→書く。星タップ→コメント欄が開く唯一の投稿導線） --}}
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-6">
                <h2 class="text-lg font-black text-gray-900 mb-1">この駐車場のレビューを書く</h2>
                <p class="text-xs text-gray-400 mb-4">星をタップすると一言コメント欄が開きます（コメントは任意・即反映）。</p>

                @if(session('review_success'))
                @php $reviewData = json_decode(session('review_success'), true); @endphp
                <div class="bg-green-50 border border-green-200 text-green-700 text-sm rounded-xl px-4 py-3 mb-4">
                    レビューを投稿しました！
                    <div class="mt-2 flex gap-2">
                        <a href="https://twitter.com/intent/tweet?text={{ urlencode('バイク駐車場「' . ($reviewData['name'] ?? '') . '」をレビューしました！') }}&url={{ urlencode($reviewData['url'] ?? '') }}&hashtags=MotoHub,バイク駐車場"
                           target="_blank" rel="noopener noreferrer"
                           class="inline-flex items-center gap-1 bg-black text-white text-xs font-bold px-3 py-1.5 rounded-lg hover:bg-gray-800 transition">
                            <svg class="w-3 h-3" viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                            Xでシェア
                        </a>
                    </div>
                </div>
                {{-- 投稿直後: 自分の投稿（新着最上部）へスクロール＋一時ハイライト（即反映の実感） --}}
                <script>
                    document.addEventListener('DOMContentLoaded', function () {
                        var list = document.getElementById('parking-review-list');
                        var first = list && list.firstElementChild;
                        if (!first) return;
                        first.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        first.style.transition = 'background-color 0.6s ease';
                        first.style.backgroundColor = '#fefce8'; // yellow-50
                        first.style.borderRadius = '0.75rem';
                        setTimeout(function () { first.style.backgroundColor = ''; }, 2800);
                    });
                </script>
                @endif

                @error('rating')
                <div class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-xl px-4 py-3 mb-4">{{ $message }}</div>
                @enderror

                <div class="text-center mb-4">
                    <p class="text-sm text-gray-500 mb-2">タップで評価してください</p>
                    <div class="flex justify-center gap-2" id="quick-rating">
                        @for($i = 1; $i <= 5; $i++)
                        <button type="button" onclick="setRating({{ $i }})"
                                class="w-12 h-12 rounded-full bg-gray-100 hover:bg-yellow-100 transition-colors flex items-center justify-center text-2xl cursor-pointer rating-star"
                                data-rating="{{ $i }}">☆</button>
                        @endfor
                    </div>
                </div>
                <div id="review-detail-form" class="hidden mt-4">
                    <form action="{{ route('parking.review', $parking->id) }}" method="POST">
                        @csrf
                        <input type="hidden" name="rating" id="rating-input" value="">
                        {{-- ハニーポット（人間には非表示・ボット除け） --}}
                        <input type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true" class="hidden" style="display:none">
                        <div class="mb-3">
                            <label class="text-xs font-bold text-gray-500 mb-1 block">コメント（任意）</label>
                            <textarea name="body" rows="2" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent transition"
                                placeholder="停めやすさ、見つけやすさ、周辺の雰囲気など...">{{ old('body') }}</textarea>
                        </div>
                        <div class="flex gap-2">
                            {{-- ★本名(user->name)で prefill しない。公開ハンドルのみ・未設定は空 --}}
                            <input type="text" name="nickname" value="{{ old('nickname', auth()->user()?->review_display_name ?? '') }}" class="flex-1 border border-gray-200 rounded-lg px-3 py-2 text-sm" placeholder="ニックネーム（任意）">
                            <button type="submit" class="bg-blue-600 text-white font-bold px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors">投稿</button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- ===== 7. 近隣の中古バイク在庫 ===== --}}
            @if($nearbyListings->isNotEmpty())
            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8 mb-6">
                <h2 class="text-sm font-black text-gray-900 mb-4 flex items-center gap-2">
                    <i data-lucide="bike" class="w-4 h-4 text-green-600"></i> {{ $parking->city ?: $parking->prefecture }}で売っているバイク
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
                    <a href="{{ route('bikes.search', ['prefecture' => mb_substr($parking->prefecture, 0, -1)]) }}"
                       class="inline-flex items-center gap-1 text-xs font-bold text-blue-600 hover:text-blue-700 transition">
                        {{ $parking->prefecture }}の中古バイク一覧を見る
                        <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                    </a>
                </div>
            </div>
            @endif

            {{-- FAQ --}}
            @php
                $faqs = [];
                if ($parking->price_per_hour) {
                    $a = "{$parking->price_per_hour}円/時間です。";
                    if ($parking->price_per_day) $a .= "日額{$parking->price_per_day}円でもご利用いただけます。";
                    $faqs[] = ['q' => "{$parking->name}の駐車料金はいくらですか？", 'a' => $a];
                }
                if ($parking->capacity) {
                    $faqs[] = ['q' => "{$parking->name}は何台停められますか？", 'a' => "{$parking->capacity}台駐車可能です。"];
                }
                if (!is_null($parking->available_24h)) {
                    $faqs[] = [
                        'q' => "{$parking->name}は24時間利用できますか？",
                        'a' => $parking->available_24h
                            ? 'はい、24時間いつでも利用できます。'
                            : '営業時間内のみ利用可能です。詳細は現地看板をご確認ください。',
                    ];
                }
                if ($parking->price_per_month) {
                    $faqs[] = ['q' => "{$parking->name}の月極料金はいくらですか？", 'a' => "月額" . number_format($parking->price_per_month) . "円で契約可能です。"];
                }
                if ($parking->parking_type === 'bike_only') {
                    $faqs[] = ['q' => "{$parking->name}はバイク専用ですか？", 'a' => 'はい、バイク専用の駐車場です。'];
                }
            @endphp

            @if(count($faqs) >= 1)
            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8 mb-6" x-data="{ open: null }">
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

            {{-- 近くの駐車場・ショップ・回遊リンク --}}
            <div class="mt-6 space-y-6">
                <x-nearby-parkings :nearbyParkings="$nearbyParkings" :latitude="$parking->latitude" :longitude="$parking->longitude" :prefecture="$parking->prefecture" />
                <x-nearby-shops :nearbyShops="$nearbyShops" :latitude="$parking->latitude" :longitude="$parking->longitude" />

                {{-- ===== 10. 周辺ツーリングスポット ===== --}}
                @if($touringSpots->isNotEmpty())
                @php
                    $prefSlug = \App\Models\TouringSpot::slugFromPrefectureName($parking->prefecture);
                @endphp
                <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6">
                    <h2 class="text-sm font-black text-gray-900 mb-3 flex items-center gap-2">
                        <i data-lucide="mountain" class="w-4 h-4 text-emerald-500"></i> {{ $parking->prefecture }}のおすすめツーリングスポット
                    </h2>
                    <div class="flex flex-wrap gap-2">
                        @foreach($touringSpots as $spot)
                        <a href="{{ url('/touring/' . $prefSlug . '/' . $spot->slug) }}"
                           class="inline-flex items-center gap-1.5 bg-emerald-50 text-emerald-700 text-xs font-bold px-3 py-1.5 rounded-lg hover:bg-emerald-100 transition">
                            <i data-lucide="map-pin" class="w-3 h-3"></i> {{ $spot->name }}
                        </a>
                        @endforeach
                    </div>
                </div>
                @endif

                {{-- 都道府県エリアリンク --}}
                @if($parking->prefecture)
                <div class="bg-green-50 rounded-2xl p-5 border border-green-100 flex items-center justify-between hover:shadow-md transition-shadow">
                    <div class="flex items-center gap-3">
                        <span class="text-2xl">🅿️</span>
                        <span class="text-sm font-black text-gray-800">{{ $parking->prefecture }}の他のバイク駐車場を見る</span>
                    </div>
                    <a href="{{ route('parking.area.prefecture', $parking->prefecture) }}" class="text-xs font-bold text-green-600 hover:text-green-800 flex items-center gap-1">
                        エリア一覧 <i data-lucide="arrow-right" class="w-4 h-4"></i>
                    </a>
                </div>
                @endif

                <x-cross-links :crossLinks="$crossLinks" />
            </div>
        </div>
    </div>
</x-layout>
