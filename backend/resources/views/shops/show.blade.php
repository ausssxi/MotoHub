<x-layout>
    {{-- ★ 1. タイトルの改修（店舗名＋地域名＋SEOキーワード） --}}
    <x-slot:title>{{ $shop->name }} - バイク在庫{{ $pagination['total'] > 0 ? '(' . $pagination['total'] . '台)' : '' }}・アクセス@if(!empty($shop->prefecture))｜{{ $shop->prefecture }}@endif | MotoHub</x-slot:title>

    {{-- ★ 2. メタディスクリプション（店舗名＋地域名＋在庫数＋取扱情報） --}}
    <x-slot:metaDescription>@if(!empty($shop->prefecture)){{ $shop->prefecture }}にある@endif「{{ $shop->name }}」のバイク在庫一覧{{ $pagination['total'] > 0 ? '【現在' . $pagination['total'] . '台販売中】' : '' }}。中古バイク・新車を価格・排気量で比較。住所・営業時間・地図などの店舗情報も掲載。</x-slot:metaDescription>

    <x-slot:styles>
        <x-jsonld.local-business :shop="$shop" :stockCount="$pagination['total'] ?? 0" />
        {{-- CSSの非同期読み込み（レンダリングブロック完全解除） --}}
        <link rel="preload" href="{{ asset('css/bike-search.css') }}" as="style" onload="this.onload=null;this.rel='stylesheet'">
        <noscript><link rel="stylesheet" href="{{ asset('css/bike-search.css') }}"></noscript>
    </x-slot:styles>

    <x-slot:scripts>
        <script src="{{ asset('js/search/sidebar.js') }}" defer></script>
        <script src="{{ asset('js/compare/manager.js') }}" defer></script>
        <script src="{{ asset('js/compare/ui.js') }}" defer></script>
        <script src="{{ asset('js/search/save_condition.js') }}" defer></script>
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
                                    <img src="{{ $shop->image_url }}" alt="{{ $shop->name }}" class="w-full h-full object-cover">
                                @else
                                    <i data-lucide="store" class="w-10 h-10 text-gray-300"></i>
                                @endif
                            </div>
                            <h1 class="text-xl font-black text-gray-900 leading-tight mb-2">{{ $shop->name }}</h1>
                            <span class="inline-block bg-blue-50 text-blue-600 text-[10px] font-bold px-2 py-1 rounded-full border border-blue-100">
                                {{ $shop->prefecture }}
                            </span>
                        </div>

                        <div class="space-y-4 text-sm">
                            <div class="flex items-start gap-3">
                                <i data-lucide="map-pin" class="w-4 h-4 text-gray-400 mt-0.5 shrink-0"></i>
                                <p class="font-bold text-gray-600">{{ $shop->address }}</p>
                            </div>
                            
                            {{-- ★追加: 地図で見るボタン --}}
                            @if($shop->latitude && $shop->longitude)
                            <a href="{{ route('shops.map', ['lat' => $shop->latitude, 'lng' => $shop->longitude, 'shop_id' => $shop->id]) }}" 
                               class="block w-full bg-white border-2 border-blue-600 text-blue-600 hover:bg-blue-50 font-black text-center py-3 rounded-xl transition flex items-center justify-center gap-2 group">
                                <i data-lucide="map" class="w-4 h-4 group-hover:scale-110 transition-transform"></i>
                                地図で場所を確認する
                            </a>
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
                            <div class="flex items-start gap-3">
                                <i data-lucide="calendar-off" class="w-4 h-4 text-gray-400 mt-0.5 shrink-0"></i>
                                <p class="font-bold text-gray-600">{{ $shop->closed_days ?? '-' }}</p>
                            </div>
                        </div>

                        @if($shop->url)
                        <div class="mt-6 pt-6 border-t border-gray-100">
                            <a href="{{ $shop->url }}" target="_blank" rel="nofollow" class="flex items-center justify-center gap-2 text-xs font-bold text-gray-400 hover:text-blue-600 transition-colors">
                                公式サイト・詳細ページ <i data-lucide="external-link" class="w-3 h-3"></i>
                            </a>
                        </div>
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
                            {{-- ★修正: 長い直書きコードを削除し、共通コンポーネントを呼び出す形にリファクタリング --}}
                            {{-- これにより、カードのデザイン変更が1箇所で済み、最初の4枚高速読み込みも自動適用されます --}}
                            @include('bikes.partials.bike_card', ['listing' => $listing, 'isFirstView' => $loop->index < 4])
                        @empty
                            <div class="col-span-full py-16 text-center text-gray-400 font-bold text-sm bg-white rounded-2xl border border-dashed border-gray-200">
                                現在、在庫はありません。
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
                    {{-- ★追加: SEO強化用の内部リンク（エリア検索への強力な導線） --}}
                    @if(!empty($shop->prefecture))
                    <div class="mt-16 bg-blue-50/50 rounded-3xl p-8 border border-blue-100 text-center shadow-sm">
                        <h3 class="text-base sm:text-lg font-black text-blue-900 mb-3 flex items-center justify-center gap-2">
                            <i data-lucide="map-pin" class="w-5 h-5 text-blue-600"></i> {{ $shop->prefecture }}のバイクをもっと探す
                        </h3>
                        <p class="text-xs text-gray-600 font-bold mb-6">
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
        </div>
    </div>
</x-layout>