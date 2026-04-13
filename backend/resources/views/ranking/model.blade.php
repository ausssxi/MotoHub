<x-layout>
    <x-slot:title>{{ $bikeModel->name }}の販売データ・売れ筋分析【{{ now()->format('Y年n月') }}】| MotoHub</x-slot:title>
    <x-slot:metaDescription>{{ $bikeModel->manufacturer->name ?? '' }} {{ $bikeModel->name }}の販売データを徹底分析。先月{{ $stats['lastMonthSold'] }}台が販売、売れ筋価格帯・地域・走行距離をグラフで可視化。</x-slot:metaDescription>
    <x-slot:canonical>{{ route('ranking.model_stats', $bikeModel->id) }}</x-slot:canonical>
    <x-slot:navigation><x-navigation :showSearch="true" /></x-slot:navigation>

    <div class="max-w-4xl mx-auto px-4 py-6 sm:py-10">
        {{-- パンくず --}}
        <nav class="flex text-xs font-bold text-gray-400 mb-6">
            <ol class="flex items-center space-x-2">
                <li><a href="{{ route('bikes.index') }}" class="hover:text-black transition">HOME</a></li>
                <li><span class="text-gray-300">/</span></li>
                <li><a href="{{ route('ranking.index') }}" class="hover:text-black transition">ランキング</a></li>
                <li><span class="text-gray-300">/</span></li>
                <li class="text-gray-600">{{ $bikeModel->name }}</li>
            </ol>
        </nav>

        {{-- タイトル + 車種画像 --}}
        <div class="flex items-center gap-4 mb-2">
            @if($bikeModel->image_url)
            <div class="w-16 h-16 sm:w-20 sm:h-20 rounded-2xl overflow-hidden bg-gray-100 flex-shrink-0 border border-gray-200">
                <img src="{{ $bikeModel->image_url }}" alt="{{ $bikeModel->name }}" class="w-full h-full object-cover" loading="lazy" onerror="this.parentElement.style.display='none'">
            </div>
            @endif
            <div>
                <h1 class="text-xl sm:text-2xl font-black text-gray-900 flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-blue-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                    {{ $bikeModel->manufacturer->name ?? '' }} {{ $bikeModel->name }} の販売データ分析
                </h1>
                <p class="text-sm text-gray-500 mt-1">過去の販売データに基づく詳細分析</p>
            </div>
        </div>

        {{-- 宣言文 --}}
        <div class="bg-blue-50 border border-blue-100 rounded-xl px-4 py-3 mb-6">
            <p class="text-xs text-blue-700 font-bold">このランキングは、MotoHubに掲載された中古バイクの成約・掲載終了データに基づく、実際の販売結果です。</p>
        </div>

        {{-- 販売サマリー --}}
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 mb-6">
            <h2 class="text-sm font-black text-gray-500 uppercase tracking-widest mb-4">販売サマリー</h2>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <div class="bg-gray-50 rounded-xl p-4 text-center">
                    <p class="text-[10px] font-bold text-gray-400 mb-1">先月販売台数</p>
                    <p class="text-2xl font-black text-gray-900">{{ number_format($stats['lastMonthSold']) }}<span class="text-xs text-gray-400">台</span></p>
                </div>
                <div class="bg-gray-50 rounded-xl p-4 text-center">
                    <p class="text-[10px] font-bold text-gray-400 mb-1">全車種順位</p>
                    <p class="text-2xl font-black {{ $stats['rank'] && $stats['rank'] <= 10 ? 'text-yellow-600' : 'text-gray-900' }}">
                        {{ $stats['rank'] ? $stats['rank'] . '位' : '-' }}
                        <span class="text-xs text-gray-400">/{{ number_format($stats['totalModels']) }}種</span>
                    </p>
                </div>
                <div class="bg-gray-50 rounded-xl p-4 text-center">
                    <p class="text-[10px] font-bold text-gray-400 mb-1">1日平均</p>
                    <p class="text-2xl font-black text-gray-900">{{ $stats['dailyAvg'] }}<span class="text-xs text-gray-400">台</span></p>
                </div>
                <div class="bg-gray-50 rounded-xl p-4 text-center">
                    <p class="text-[10px] font-bold text-gray-400 mb-1">平均在庫日数</p>
                    <p class="text-2xl font-black text-gray-900">{{ $stats['avgDays'] }}<span class="text-xs text-gray-400">日</span></p>
                </div>
            </div>
        </div>

        {{-- 解説テキスト（自動生成） --}}
        @php
            $topPrice = $stats['priceRanges']->first();
            $topRegion = $stats['regionRanking']->first();
            $topMileage = $stats['mileageRanges']->first();
            $hasInsight = $topPrice || $topRegion || $topMileage;
        @endphp
        @if($hasInsight)
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 mb-6">
            <h2 class="text-sm font-black text-gray-500 uppercase tracking-widest mb-3 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                データから読み解く {{ $bikeModel->name }} の傾向
            </h2>
            <div class="space-y-2.5">
                @if($topPrice)
                <div class="flex items-start gap-2">
                    <span class="w-5 h-5 rounded-full bg-green-100 text-green-600 flex items-center justify-center flex-shrink-0 mt-0.5">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8V7m0 10v1"/></svg>
                    </span>
                    <p class="text-sm text-gray-700">
                        <span class="font-black text-gray-900">{{ $topPrice->price_range }}</span>が最多で、
                        @if(str_contains($topPrice->price_range, '〜20') || str_contains($topPrice->price_range, '〜30'))
                            手が出しやすい価格帯が人気です。
                        @elseif(str_contains($topPrice->price_range, '100万'))
                            高価格帯に需要が集中しています。
                        @else
                            中間価格帯がボリュームゾーンになっています。
                        @endif
                    </p>
                </div>
                @endif

                @if($topRegion)
                @php
                    $secondRegion = $stats['regionRanking']->skip(1)->first();
                @endphp
                <div class="flex items-start gap-2">
                    <span class="w-5 h-5 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center flex-shrink-0 mt-0.5">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    </span>
                    <p class="text-sm text-gray-700">
                        <span class="font-black text-gray-900">{{ $topRegion->prefecture }}</span>が最多{{ $secondRegion ? '、' . $secondRegion->prefecture . 'が続きます' : 'です' }}。
                        @php
                            $kanto = ['東京都','神奈川県','埼玉県','千葉県'];
                            $kansai = ['大阪府','兵庫県','京都府'];
                            $isKanto = in_array($topRegion->prefecture, $kanto);
                            $isKansai = in_array($topRegion->prefecture, $kansai);
                        @endphp
                        @if($isKanto)
                            首都圏を中心に需要が高い傾向です。
                        @elseif($isKansai)
                            関西圏を中心に需要が高い傾向です。
                        @else
                            地方でも安定した需要があります。
                        @endif
                    </p>
                </div>
                @endif

                @if($topMileage)
                <div class="flex items-start gap-2">
                    <span class="w-5 h-5 rounded-full bg-purple-100 text-purple-600 flex items-center justify-center flex-shrink-0 mt-0.5">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    </span>
                    <p class="text-sm text-gray-700">
                        <span class="font-black text-gray-900">{{ $topMileage->mileage_range }}</span>が最も売れており、
                        @if(str_contains($topMileage->mileage_range, '〜5,000'))
                            低走行車が特に人気で、状態重視の傾向があります。
                        @elseif(str_contains($topMileage->mileage_range, '5,000〜10,000'))
                            程よく使われた個体がバランス良く選ばれています。
                        @elseif(str_contains($topMileage->mileage_range, '30,000'))
                            走行距離よりも価格を重視する傾向があります。
                        @else
                            実用的な走行距離帯に需要が集まっています。
                        @endif
                    </p>
                </div>
                @endif
            </div>
        </div>
        @endif

        {{-- 販売推移（折れ線） --}}
        @if(!empty($stats['monthlySales']))
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 mb-6">
            <h2 class="text-sm font-black text-gray-500 uppercase tracking-widest mb-4">販売推移（過去6ヶ月）</h2>
            <div style="height:260px;position:relative">
                <canvas id="salesTrend"></canvas>
            </div>
        </div>
        @endif

        {{-- グラフ 2カラム --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            {{-- 価格帯別 --}}
            @if($stats['priceRanges']->isNotEmpty())
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
                <h3 class="text-sm font-black text-gray-900 mb-3">売れている価格帯</h3>
                <div style="height:240px;position:relative"><canvas id="priceChart"></canvas></div>
            </div>
            @endif

            {{-- 地域TOP10 --}}
            @if($stats['regionRanking']->isNotEmpty())
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
                <h3 class="text-sm font-black text-gray-900 mb-3">売れている地域 TOP10</h3>
                <div style="height:240px;position:relative"><canvas id="regionChart"></canvas></div>
            </div>
            @endif

            {{-- 走行距離帯 --}}
            @if($stats['mileageRanges']->isNotEmpty())
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
                <h3 class="text-sm font-black text-gray-900 mb-3">売れている走行距離帯</h3>
                <div style="height:240px;position:relative"><canvas id="mileageChart"></canvas></div>
            </div>
            @endif

            {{-- 年式 --}}
            @if($stats['yearRanking']->isNotEmpty())
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
                <h3 class="text-sm font-black text-gray-900 mb-3">売れている年式（新しい順）</h3>
                <div style="height:240px;position:relative"><canvas id="yearChart"></canvas></div>
            </div>
            @endif
        </div>

        {{-- 注記 --}}
        <p class="text-[10px] text-gray-400 mb-6 text-center">※MotoHub掲載車両の売り切れデータに基づく集計です（過去3ヶ月分）</p>

        {{-- この車種の在庫 --}}
        @if($relatedListings->isNotEmpty())
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 mb-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-base font-black text-gray-900 flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    {{ $bikeModel->name }} の在庫
                </h2>
                <a href="{{ route('bikes.index', ['model' => $bikeModel->name]) }}"
                   class="text-xs font-bold text-blue-600 hover:text-blue-800 transition-colors flex items-center gap-1">
                    すべて見る（{{ number_format($activeCount) }}台）
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                </a>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                @foreach($relatedListings as $listing)
                <a href="{{ route('bikes.show', $listing->id) }}" class="bg-gray-50 rounded-xl overflow-hidden hover:shadow-md transition-shadow flex flex-col">
                    <div class="aspect-[4/3] bg-gray-100 overflow-hidden">
                        @if(!empty($listing->image_urls[0]))
                        <img src="{{ $listing->image_urls[0] }}" alt="{{ $listing->title }}" class="w-full h-full object-cover" loading="lazy" onerror="this.style.display='none'">
                        @endif
                    </div>
                    <div class="p-2.5">
                        @if($listing->total_price)
                        <p class="text-sm font-black text-red-600 mb-1">&yen;{{ number_format($listing->total_price) }}</p>
                        @else
                        <p class="text-sm font-black text-gray-400 mb-1">価格ASK</p>
                        @endif
                        <div class="flex flex-wrap gap-x-2 gap-y-0.5 text-[10px] text-gray-500 font-bold">
                            @if($listing->model_year)<span>{{ $listing->model_year }}年</span>@endif
                            @if($listing->mileage)<span>{{ number_format($listing->mileage) }}km</span>@endif
                        </div>
                        @if($listing->shop)
                        <p class="text-[10px] text-gray-400 mt-1 truncate">{{ $listing->shop->prefecture ?? '' }}</p>
                        @endif
                    </div>
                </a>
                @endforeach
            </div>
        </div>
        @endif

        {{-- この車種のパーツ --}}
        @if(!empty($relatedParts))
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 mb-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-base font-black text-gray-900 flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-orange-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    {{ $bikeModel->name }} のパーツ
                </h2>
                <a href="{{ route('parts.index', ['bike' => $bikeModel->name]) }}"
                   class="text-xs font-bold text-blue-600 hover:text-blue-800 transition-colors flex items-center gap-1">
                    パーツをもっと見る
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                </a>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                @foreach(array_slice($relatedParts, 0, 4) as $part)
                @php
                    $hasCode = !empty($part['jan_code']) || !empty($part['part_number']);
                    $compareParams = [];
                    if (!empty($part['jan_code'])) $compareParams['jan'] = $part['jan_code'];
                    if (!empty($part['part_number'])) $compareParams['partnum'] = $part['part_number'];
                    $compareParams['keyword'] = \Illuminate\Support\Str::limit($part['name'], 100, '');
                    $compareUrl = route('parts.compare', $compareParams);
                @endphp
                <div class="bg-gray-50 rounded-xl overflow-hidden hover:shadow-md transition-shadow flex flex-col">
                    <div class="aspect-square bg-white flex items-center justify-center overflow-hidden">
                        @if($part['image'])
                        <img src="{{ str_replace('?_ex=128x128', '?_ex=300x300', $part['image']) }}"
                             alt="{{ $part['name'] }}" class="w-full h-full object-contain p-2" loading="lazy">
                        @else
                        <span class="text-gray-300 text-3xl">&#x1F527;</span>
                        @endif
                    </div>
                    <div class="p-2.5 flex flex-col flex-grow">
                        <h3 class="text-xs font-bold text-gray-800 line-clamp-2 mb-1">{{ $part['name'] }}</h3>
                        <p class="text-sm font-black text-red-600 mb-2">&yen;{{ number_format($part['price']) }}</p>
                        <div class="mt-auto">
                            @if($hasCode)
                            <a href="{{ $compareUrl }}" class="block text-center text-[11px] font-bold py-1.5 rounded-lg bg-gray-800 hover:bg-gray-900 text-white transition">
                                価格を比較する
                            </a>
                            @else
                            <a href="{{ $part['url'] }}" target="_blank" rel="noopener noreferrer" class="block text-center text-[11px] font-bold py-1.5 rounded-lg bg-red-500 hover:bg-red-600 text-white transition">
                                楽天市場で見る
                            </a>
                            @endif
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- 関連リンク --}}
        <div class="bg-gray-50 rounded-2xl p-6">
            <h3 class="text-sm font-black text-gray-500 mb-3">関連リンク</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                @if($bikeModel->seo_url)
                <a href="{{ $bikeModel->seo_url }}" class="flex items-center gap-2 p-3 bg-white rounded-xl hover:shadow-sm transition text-sm font-bold text-gray-700 hover:text-blue-600">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                    {{ $bikeModel->name }}の車種詳細ページ
                </a>
                @endif
                <a href="{{ route('bikes.index', ['model' => $bikeModel->name]) }}" class="flex items-center gap-2 p-3 bg-white rounded-xl hover:shadow-sm transition text-sm font-bold text-gray-700 hover:text-blue-600">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    {{ $bikeModel->name }}の在庫を見る（{{ number_format($activeCount) }}台掲載中）
                </a>
                <a href="{{ route('ranking.index') }}" class="flex items-center gap-2 p-3 bg-white rounded-xl hover:shadow-sm transition text-sm font-bold text-gray-700 hover:text-blue-600">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138c.12.464.336.896.627 1.266a3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg>
                    売れ筋ランキングTOPへ
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var barOpts = {
            responsive: true, maintainAspectRatio: false, indexAxis: 'y',
            plugins: { legend: { display: false }, tooltip: { callbacks: { label: function(c){return c.raw.toLocaleString()+'台'} } } },
            scales: { x: { grid:{display:false}, ticks:{font:{size:10}} }, y: { grid:{display:false}, ticks:{font:{size:10,weight:'bold'}} } }
        };

        @if(!empty($stats['monthlySales']))
        new Chart(document.getElementById('salesTrend'), {
            type: 'line',
            data: {
                labels: @json(collect($stats['monthlySales'])->pluck('label')),
                datasets: [{ data: @json(collect($stats['monthlySales'])->pluck('count')), borderColor: '#3B82F6', backgroundColor: 'rgba(59,130,246,0.1)', fill: true, tension: 0.3, pointRadius: 5, pointBackgroundColor: '#3B82F6', borderWidth: 2.5 }]
            },
            options: { responsive:true, maintainAspectRatio:false, plugins:{legend:{display:false},tooltip:{callbacks:{label:function(c){return c.raw.toLocaleString()+'台'}}}}, scales:{x:{grid:{display:false}},y:{beginAtZero:true,grid:{color:'#f3f4f6'}}} }
        });
        @endif

        @if($stats['priceRanges']->isNotEmpty())
        new Chart(document.getElementById('priceChart'), { type:'bar', data:{labels:@json($stats['priceRanges']->pluck('price_range')),datasets:[{data:@json($stats['priceRanges']->pluck('cnt')),backgroundColor:'#34D399',borderRadius:4,barThickness:22}]}, options:barOpts });
        @endif

        @if($stats['regionRanking']->isNotEmpty())
        new Chart(document.getElementById('regionChart'), { type:'bar', data:{labels:@json($stats['regionRanking']->pluck('prefecture')),datasets:[{data:@json($stats['regionRanking']->pluck('cnt')),backgroundColor:'#60A5FA',borderRadius:4,barThickness:22}]}, options:barOpts });
        @endif

        @if($stats['mileageRanges']->isNotEmpty())
        new Chart(document.getElementById('mileageChart'), { type:'bar', data:{labels:@json($stats['mileageRanges']->pluck('mileage_range')),datasets:[{data:@json($stats['mileageRanges']->pluck('cnt')),backgroundColor:'#A78BFA',borderRadius:4,barThickness:22}]}, options:barOpts });
        @endif

        @if($stats['yearRanking']->isNotEmpty())
        new Chart(document.getElementById('yearChart'), { type:'bar', data:{labels:@json($stats['yearRanking']->pluck('model_year')),datasets:[{data:@json($stats['yearRanking']->pluck('cnt')),backgroundColor:'#F59E0B',borderRadius:4,barThickness:22}]}, options:barOpts });
        @endif
    });
    </script>
</x-layout>
