<x-layout>
    <x-slot:title>{{ $parking->name }} - バイク駐車場 @if($parking->prefecture)| {{ $parking->prefecture }}@endif | MotoHub</x-slot:title>
    <x-slot:metaDescription>{{ $parking->prefecture ? $parking->prefecture . 'の' : '' }}バイク駐車場「{{ $parking->name }}」の詳細情報。{{ $parking->getPriceDisplay() }}。ユーザーレビューも掲載。</x-slot:metaDescription>

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
                    btn.textContent = r <= rating ? '★' : '☆';
                    btn.classList.toggle('bg-yellow-100', r <= rating);
                    btn.classList.toggle('text-yellow-500', r <= rating);
                    btn.classList.toggle('bg-gray-100', r > rating);
                });
            }

            function markUsed(parkingId) {
                const btn = document.getElementById('btn-used');
                btn.disabled = true;
                btn.innerHTML = '<svg class="w-4 h-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" class="opacity-25"/><path d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" fill="currentColor" class="opacity-75"/></svg>';

                fetch(`/parking/${parkingId}/used`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    }
                })
                .then(r => r.json())
                .then(data => {
                    document.getElementById('used-count').textContent = data.used_count;
                    btn.innerHTML = '✓ 回答済み';
                    btn.classList.remove('bg-gray-100', 'hover:bg-green-100', 'text-gray-600');
                    btn.classList.add('bg-green-100', 'text-green-700');
                })
                .catch(() => {
                    btn.disabled = false;
                    btn.innerHTML = '<i data-lucide="check-circle" class="w-4 h-4"></i> 使った！';
                });
            }
        </script>
    </x-slot:scripts>

    <x-slot:navigation>
        <x-navigation :showSearch="true" />
    </x-slot:navigation>

    <div class="bg-gray-50 min-h-screen py-8">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">

            {{-- パンくず --}}
            <nav class="overflow-x-auto text-xs font-bold text-gray-400 mb-6 scrollbar-hide" aria-label="Breadcrumb">
                <ol class="flex items-center space-x-2 whitespace-nowrap">
                    <li><a href="/" class="hover:text-gray-600 transition-colors">HOME</a></li>
                    <li><span class="text-gray-300">＞</span></li>
                    <li><a href="{{ route('parking.index') }}" class="hover:text-gray-600 transition-colors">駐車場マップ</a></li>
                    <li><span class="text-gray-300">＞</span></li>
                    <li><span class="text-gray-800">{{ $parking->name }}</span></li>
                </ol>
            </nav>

            {{-- 基本情報カード --}}
            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8 mb-6">
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <h1 class="text-xl sm:text-2xl font-black text-gray-900 mb-2">{{ $parking->name }}</h1>
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="inline-flex items-center gap-1 bg-green-100 text-green-700 text-[11px] font-bold px-3 py-1 rounded-full">
                                <i data-lucide="square-parking" class="w-3 h-3"></i>
                                {{ $parking->getParkingTypeLabel() }}
                            </span>
                            @if($parking->is_verified)
                            <span class="inline-flex items-center gap-1 bg-blue-100 text-blue-700 text-[10px] font-bold px-2.5 py-1 rounded-full">
                                <i data-lucide="badge-check" class="w-3 h-3"></i>
                                確認済み
                            </span>
                            @endif
                            @if($parking->is_free)
                            <span class="inline-flex items-center gap-1 bg-yellow-100 text-yellow-700 text-[10px] font-bold px-2.5 py-1 rounded-full">
                                無料
                            </span>
                            @endif
                        </div>
                    </div>
                    @if($parking->avg_rating > 0)
                    <div class="text-center">
                        <div class="text-2xl font-black text-yellow-500">{{ number_format($parking->avg_rating, 1) }}</div>
                        <div class="text-[10px] text-gray-400 font-bold">{{ $parking->reviews_count }}件</div>
                    </div>
                    @endif
                </div>

                {{-- 基本情報テーブル --}}
                <div class="space-y-3 mb-6">
                    <div class="flex items-start gap-3">
                        <i data-lucide="map-pin" class="w-4 h-4 text-gray-400 mt-0.5 shrink-0"></i>
                        <span class="text-sm text-gray-700">{{ $parking->address }}</span>
                    </div>
                    @if($parking->tel)
                    <div class="flex items-start gap-3">
                        <i data-lucide="phone" class="w-4 h-4 text-gray-400 mt-0.5 shrink-0"></i>
                        <a href="tel:{{ $parking->tel }}" class="text-sm text-blue-600 hover:underline">{{ $parking->tel }}</a>
                    </div>
                    @endif
                    <div class="flex items-start gap-3">
                        <i data-lucide="coins" class="w-4 h-4 text-gray-400 mt-0.5 shrink-0"></i>
                        <span class="text-sm text-gray-700">{{ $parking->price_detail ?: $parking->getPriceDisplay() }}</span>
                    </div>
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

                {{-- 設備アイコン --}}
                <div class="flex flex-wrap gap-3 mb-6">
                    @if($parking->is_covered)
                    <span class="flex items-center gap-1.5 text-xs text-gray-600 bg-gray-100 px-3 py-1.5 rounded-lg">
                        <i data-lucide="umbrella" class="w-3.5 h-3.5"></i> 屋根あり
                    </span>
                    @endif
                    @if($parking->is_locked)
                    <span class="flex items-center gap-1.5 text-xs text-gray-600 bg-gray-100 px-3 py-1.5 rounded-lg">
                        <i data-lucide="lock" class="w-3.5 h-3.5"></i> 施錠可能
                    </span>
                    @endif
                    @if($parking->has_security_camera)
                    <span class="flex items-center gap-1.5 text-xs text-gray-600 bg-gray-100 px-3 py-1.5 rounded-lg">
                        <i data-lucide="cctv" class="w-3.5 h-3.5"></i> 防犯カメラ
                    </span>
                    @endif
                    @if($parking->available_24h)
                    <span class="flex items-center gap-1.5 text-xs text-gray-600 bg-gray-100 px-3 py-1.5 rounded-lg">
                        <i data-lucide="clock" class="w-3.5 h-3.5"></i> 24時間利用可
                    </span>
                    @endif
                </div>

                {{-- 備考 --}}
                @if($parking->notes || $parking->description)
                <div class="bg-gray-50 rounded-xl p-4 mb-6">
                    <p class="text-xs font-bold text-gray-500 mb-1">備考</p>
                    <p class="text-sm text-gray-700 whitespace-pre-line">{{ $parking->notes ?: $parking->description }}</p>
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

            {{-- 地図 --}}
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
            </div>

            {{-- ストリートビュー --}}
            @if($parking->latitude && $parking->longitude && config('services.google_maps.api_key'))
            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 mb-6">
                <h2 class="text-sm font-black text-gray-900 mb-3 flex items-center gap-2">
                    <i data-lucide="camera" class="w-4 h-4 text-green-600"></i> ストリートビューで入口を確認
                </h2>
                <img
                    src="https://maps.googleapis.com/maps/api/streetview?size=800x400&location={{ $parking->latitude }},{{ $parking->longitude }}&key={{ config('services.google_maps.api_key') }}"
                    alt="{{ $parking->name }} ストリートビュー"
                    class="w-full rounded-xl"
                    loading="lazy"
                    onerror="this.closest('.bg-white').style.display='none'">
            </div>
            @endif

            {{-- 料金シミュレーター --}}
            @if($parking->price_per_hour || $parking->price_per_day)
            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 mb-6">
                <h2 class="text-sm font-black text-gray-900 mb-4 flex items-center gap-2">
                    <i data-lucide="calculator" class="w-4 h-4 text-blue-600"></i> 料金シミュレーター
                </h2>

                {{-- 結果エリア（常に表示） --}}
                <div id="parking-fee-result" class="bg-gradient-to-br from-blue-50 to-slate-50 rounded-2xl p-5 mb-4 border border-blue-100">
                    <p id="pf-duration" class="text-xs font-bold text-blue-500 text-center mb-1">3時間の駐車</p>
                    <div id="pf-cards" class="flex justify-center gap-3"></div>
                    <div id="pf-monthly" class="text-center mt-3 pt-3 border-t border-blue-100 hidden">
                        <span class="text-[11px] text-gray-400">月極契約なら</span>
                        <span class="text-sm font-black text-gray-700 ml-1">月{{ number_format($parking->price_per_month ?? 0) }}円</span>
                    </div>
                    <p id="pf-error" class="text-sm text-red-500 font-bold text-center hidden"></p>
                </div>

                {{-- 日時入力 --}}
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

                    // 駐車時間ラベル
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
                        // 2カード比較表示
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
                        // 1カード表示
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

                // デフォルト値セット & 自動計算
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

                // 入力変更時も自動再計算
                ['ps-date','ps-hour','ps-min','pe-date','pe-hour','pe-min'].forEach(function(id) {
                    document.getElementById(id).addEventListener('change', calcFee);
                });
            })();
            </script>
            @endif

            {{-- レビュー一覧 --}}
            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8 mb-6">
                <h2 class="text-sm font-black text-gray-900 mb-4 flex items-center gap-2">
                    <i data-lucide="message-square" class="w-4 h-4 text-green-600"></i>
                    レビュー ({{ $reviews->count() }}件)
                </h2>

                @if($reviews->isEmpty())
                <p class="text-sm text-gray-400 text-center py-8">まだレビューはありません。最初のレビューを投稿しましょう！</p>
                @else
                <div class="space-y-4">
                    @foreach($reviews as $review)
                    <div class="border-b border-gray-50 pb-4 last:border-b-0 last:pb-0">
                        <div class="flex items-center justify-between mb-2">
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-bold text-gray-800">{{ $review->nickname }}</span>
                                <div class="flex items-center gap-0.5">
                                    @for($i = 1; $i <= 5; $i++)
                                    <i data-lucide="star" class="w-3 h-3 {{ $i <= $review->rating ? 'text-yellow-400 fill-yellow-400' : 'text-gray-200' }}"></i>
                                    @endfor
                                </div>
                            </div>
                            <span class="text-[10px] text-gray-400">{{ $review->created_at->format('Y/m/d') }}</span>
                        </div>
                        <p class="text-sm text-gray-700 whitespace-pre-line">{{ $review->body }}</p>
                        @if($review->visited_at)
                        <p class="text-[10px] text-gray-400 mt-1">訪問日: {{ $review->visited_at->format('Y/m/d') }}</p>
                        @endif
                    </div>
                    @endforeach
                </div>
                @endif
            </div>

            {{-- 「使ったことある」ボタン --}}
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 mb-6 flex items-center justify-between">
                <div>
                    <p class="text-sm font-bold text-gray-700">この駐車場を使ったことがある？</p>
                    <p class="text-xs text-gray-400"><span id="used-count">{{ $parking->used_count ?? 0 }}</span>人が「使った」と回答</p>
                </div>
                <button id="btn-used" onclick="markUsed({{ $parking->id }})"
                    class="bg-gray-100 hover:bg-green-100 text-gray-600 hover:text-green-700 font-bold text-sm px-5 py-2.5 rounded-xl transition-colors flex items-center gap-2">
                    <i data-lucide="check-circle" class="w-4 h-4"></i>
                    使った！
                </button>
            </div>

            {{-- レビュー投稿フォーム（ログイン不要・ワンタップ評価） --}}
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                <h2 class="text-lg font-black text-gray-900 mb-4">この駐車場を使ったことがありますか？</h2>

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
                        <div class="mb-3">
                            <label class="text-xs font-bold text-gray-500 mb-1 block">コメント（任意）</label>
                            <textarea name="body" rows="2" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent transition"
                                placeholder="停めやすさ、見つけやすさ、周辺の雰囲気など...">{{ old('body') }}</textarea>
                        </div>
                        <div class="flex gap-2">
                            <input type="text" name="nickname" value="{{ old('nickname', auth()->user()?->name ?? '') }}" class="flex-1 border border-gray-200 rounded-lg px-3 py-2 text-sm" placeholder="ニックネーム（任意）">
                            <button type="submit" class="bg-blue-600 text-white font-bold px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors">投稿</button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- 近くの駐車場・ショップ・回遊リンク --}}
            <div class="mt-6 space-y-6">
                <x-nearby-parkings :nearbyParkings="$nearbyParkings" :latitude="$parking->latitude" :longitude="$parking->longitude" />
                <x-nearby-shops :nearbyShops="$nearbyShops" :latitude="$parking->latitude" :longitude="$parking->longitude" />
                <x-cross-links :crossLinks="$crossLinks" />
            </div>
        </div>
    </div>
</x-layout>
