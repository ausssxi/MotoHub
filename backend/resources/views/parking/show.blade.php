<x-layout>
    <x-slot:title>{{ $parking->name }} - バイク駐車場 @if($parking->prefecture)| {{ $parking->prefecture }}@endif | MotoHub</x-slot:title>
    <x-slot:metaDescription>{{ $parking->prefecture ? $parking->prefecture . 'の' : '' }}バイク駐車場「{{ $parking->name }}」の詳細情報。{{ $parking->getPriceDisplay() }}。ユーザーレビューも掲載。</x-slot:metaDescription>

    <x-slot:styles>
        <x-jsonld.parking :parking="$parking" />
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
        <style>
            #detail-map { height: 250px; z-index: 10; border-radius: 12px; }
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
        </script>
    </x-slot:scripts>

    <x-slot:navigation>
        <x-navigation :showSearch="true" />
    </x-slot:navigation>

    <div class="bg-gray-50 min-h-screen py-8">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">

            {{-- パンくず --}}
            <nav class="flex text-xs font-bold text-gray-400 mb-6" aria-label="Breadcrumb">
                <ol class="flex items-center space-x-2">
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
                            <span class="inline-flex items-center gap-1 bg-green-100 text-green-700 text-[10px] font-bold px-2.5 py-1 rounded-full">
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

                {{-- 住所・料金 --}}
                <div class="space-y-3 mb-6">
                    <div class="flex items-start gap-3">
                        <i data-lucide="map-pin" class="w-4 h-4 text-gray-400 mt-0.5 shrink-0"></i>
                        <span class="text-sm text-gray-700">{{ $parking->address }}</span>
                    </div>
                    <div class="flex items-start gap-3">
                        <i data-lucide="coins" class="w-4 h-4 text-gray-400 mt-0.5 shrink-0"></i>
                        <span class="text-sm text-gray-700">{{ $parking->getPriceDisplay() }}</span>
                    </div>
                    @if($parking->capacity)
                    <div class="flex items-start gap-3">
                        <i data-lucide="car" class="w-4 h-4 text-gray-400 mt-0.5 shrink-0"></i>
                        <span class="text-sm text-gray-700">収容台数: {{ $parking->capacity }}台</span>
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

                {{-- 説明文 --}}
                @if($parking->description)
                <div class="bg-gray-50 rounded-xl p-4 mb-6">
                    <p class="text-sm text-gray-700 whitespace-pre-line">{{ $parking->description }}</p>
                </div>
                @endif

                {{-- データソース --}}
                @if($parking->source_url)
                <div class="text-xs text-gray-400">
                    出典: <a href="{{ $parking->source_url }}" target="_blank" rel="noopener noreferrer" class="text-blue-500 hover:underline">{{ parse_url($parking->source_url, PHP_URL_HOST) }}</a>
                </div>
                @endif
            </div>

            {{-- 地図 --}}
            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 mb-6">
                <h2 class="text-sm font-black text-gray-900 mb-3 flex items-center gap-2">
                    <i data-lucide="map" class="w-4 h-4 text-green-600"></i> 位置情報
                </h2>
                <div id="detail-map" class="w-full"></div>
                <a href="{{ route('parking.index', ['lat' => $parking->latitude, 'lng' => $parking->longitude]) }}" class="block mt-3 text-center text-xs font-bold text-green-600 hover:text-green-700 transition">
                    周辺の駐車場を探す →
                </a>
            </div>

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

            {{-- レビュー投稿フォーム --}}
            @auth
            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8">
                <h2 class="text-sm font-black text-gray-900 mb-4 flex items-center gap-2">
                    <i data-lucide="pen-line" class="w-4 h-4 text-green-600"></i>
                    レビューを投稿
                </h2>

                @if(session('success'))
                <div class="bg-green-50 border border-green-200 text-green-700 text-sm rounded-xl px-4 py-3 mb-4">
                    {{ session('success') }}
                </div>
                @endif

                <form action="{{ route('parking.review', $parking) }}" method="POST" class="space-y-4">
                    @csrf
                    <div>
                        <label for="nickname" class="block text-xs font-bold text-gray-700 mb-1">ニックネーム</label>
                        <input type="text" name="nickname" id="nickname" value="{{ old('nickname', auth()->user()->name) }}" required maxlength="50"
                            class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent transition">
                        @error('nickname') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">評価</label>
                        <div class="flex items-center gap-1" x-data="{ rating: {{ old('rating', 0) }} }">
                            @for($i = 1; $i <= 5; $i++)
                            <button type="button" @click="rating = {{ $i }}" class="focus:outline-none">
                                <i data-lucide="star" class="w-6 h-6 transition-colors" :class="rating >= {{ $i }} ? 'text-yellow-400 fill-yellow-400' : 'text-gray-300'"></i>
                            </button>
                            @endfor
                            <input type="hidden" name="rating" x-bind:value="rating">
                        </div>
                        @error('rating') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="body" class="block text-xs font-bold text-gray-700 mb-1">レビュー内容</label>
                        <textarea name="body" id="body" rows="4" required maxlength="1000" placeholder="駐車場の使い心地やアクセスの良さなど..."
                            class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent transition resize-none">{{ old('body') }}</textarea>
                        @error('body') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="visited_at" class="block text-xs font-bold text-gray-700 mb-1">訪問日（任意）</label>
                        <input type="date" name="visited_at" id="visited_at" value="{{ old('visited_at') }}" max="{{ date('Y-m-d') }}"
                            class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent transition">
                    </div>

                    <button type="submit" class="w-full bg-green-600 text-white font-bold py-3 rounded-xl hover:bg-green-700 transition text-sm">
                        レビューを投稿する
                    </button>
                </form>
            </div>
            @else
            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 text-center">
                <p class="text-sm text-gray-500 mb-3">レビューを投稿するにはログインが必要です</p>
                <a href="{{ route('login') }}" class="inline-flex items-center gap-2 bg-black text-white text-xs font-bold px-6 py-2.5 rounded-full hover:bg-gray-800 transition">
                    <i data-lucide="log-in" class="w-3.5 h-3.5"></i> ログインする
                </a>
            </div>
            @endauth
        </div>
    </div>
</x-layout>
