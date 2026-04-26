<x-layout>
    <x-slot:title>{{ $shop->name }}の在庫一覧・口コミ{{ $pagination['total'] > 0 ? '【' . $pagination['total'] . '台掲載中】' : '' }}| MotoHub</x-slot:title>

    <x-slot:metaDescription>{{ $shop->name }}@if(!empty($shop->prefecture))（{{ $shop->prefecture }}）@endifの中古バイク在庫{{ $pagination['total'] > 0 ? $pagination['total'] . '台' : '' }}を価格・年式で比較。営業時間・アクセス・地図情報も掲載。MotoHubで最安値をチェック。</x-slot:metaDescription>

    <x-slot:styles>
        <x-jsonld.local-business :shop="$shop" :stockCount="$pagination['total'] ?? 0" />
        <x-jsonld.breadcrumb-shop :shop="$shop" />
        {{-- CSSの非同期読み込み（レンダリングブロック完全解除） --}}
        <link rel="preload" href="{{ asset('css/bike-search.css') }}?v={{ filemtime(public_path('css/bike-search.css')) }}" as="style" onload="this.onload=null;this.rel='stylesheet'">
        <noscript><link rel="stylesheet" href="{{ asset('css/bike-search.css') }}?v={{ filemtime(public_path('css/bike-search.css')) }}"></noscript>
    </x-slot:styles>

    <x-slot:scripts>
        <script src="{{ asset('js/search/sidebar.js') }}?v={{ filemtime(public_path('js/search/sidebar.js')) }}" defer></script>
        <script src="{{ asset('js/compare/manager.js') }}?v={{ filemtime(public_path('js/compare/manager.js')) }}" defer></script>
        <script src="{{ asset('js/compare/ui.js') }}?v={{ filemtime(public_path('js/compare/ui.js')) }}" defer></script>
        <script src="{{ asset('js/search/save_condition.js') }}?v={{ filemtime(public_path('js/search/save_condition.js')) }}" defer></script>
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

                        @if($shop->latitude && $shop->longitude)
                        <div class="mt-4">
                            <a href="{{ route('parking.index', ['lat' => $shop->latitude, 'lng' => $shop->longitude]) }}"
                               class="flex items-center justify-center gap-2 w-full bg-green-50 border border-green-200 text-green-700 hover:bg-green-100 font-bold text-xs py-3 rounded-xl transition">
                                <i data-lucide="square-parking" class="w-4 h-4"></i>
                                この店舗の近くの駐車場を探す
                            </a>
                        </div>
                        @endif

                        {{-- ストリートビュー --}}
                        @if($shop->latitude && $shop->longitude && config('services.google_maps.api_key'))
                        <div class="mt-6 pt-6 border-t border-gray-100">
                            <h3 class="text-sm font-black text-gray-900 mb-3 flex items-center gap-2">
                                <i data-lucide="camera" class="w-4 h-4 text-green-600"></i> ストリートビュー
                            </h3>
                            <img
                                src="https://maps.googleapis.com/maps/api/streetview?size=800x400&location={{ $shop->latitude }},{{ $shop->longitude }}&key={{ config('services.google_maps.api_key') }}"
                                alt="{{ $shop->name }} ストリートビュー"
                                class="w-full rounded-xl"
                                loading="lazy"
                                onerror="this.parentElement.style.display='none'">
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
                            {{-- ★修正: 長い直書きコードを削除し、共通コンポーネントを呼び出す形にリファクタリング --}}
                            {{-- これにより、カードのデザイン変更が1箇所で済み、最初の4枚高速読み込みも自動適用されます --}}
                            @include('bikes.partials.bike_card', ['listing' => $listing, 'isFirstView' => $loop->index < 4])
                        @empty
                            <div class="col-span-full py-16 text-center bg-white rounded-2xl border border-dashed border-gray-200">
                                <p class="text-gray-400 font-bold text-sm">現在、在庫はありません。</p>
                                @if(!empty($chainInfo))
                                <div class="mt-6 mx-auto max-w-md bg-blue-50 border border-blue-100 rounded-xl p-4">
                                    <p class="text-sm font-bold text-blue-900">{{ $chainInfo['name'] }}の在庫は一括管理されています</p>
                                    <p class="text-xs text-blue-600 mt-1">現在 {{ number_format($chainInfo['stock']) }}台 の在庫があります</p>
                                    <a href="{{ route('shops.show', $chainInfo['main_shop_id']) }}" class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold px-4 py-2 rounded-lg mt-3 transition-colors">
                                        <i data-lucide="bike" class="w-3 h-3"></i>
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

            {{-- 訪問済みボタン --}}
            <div class="mt-8 bg-indigo-50 rounded-2xl p-5 border border-indigo-100 text-center max-w-md mx-auto">
                <p class="text-sm font-bold text-gray-800 mb-3">この店舗に行ったことはありますか？</p>
                <button onclick="markVisited({{ $shop->id }})" id="visited-btn"
                    class="bg-indigo-600 text-white font-bold text-sm px-6 py-2.5 rounded-xl hover:bg-indigo-700 transition-colors">
                    行ったことある！
                </button>
                <p class="text-xs text-gray-400 mt-2" id="visited-count">
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
                    document.getElementById('visited-count').textContent = data.count + '人が訪問済み';
                });
            }
            </script>

            {{-- 近くの駐車場・ショップ・回遊リンク --}}
            <div class="mt-12 space-y-6">
                <x-nearby-parkings :nearbyParkings="$nearbyParkings" :latitude="$shop->latitude" :longitude="$shop->longitude" />
                <x-nearby-shops :nearbyShops="$nearbyShops" :latitude="$shop->latitude" :longitude="$shop->longitude" />
                <x-cross-links :crossLinks="$crossLinks" />
            </div>
        </div>
    </div>
</x-layout>
