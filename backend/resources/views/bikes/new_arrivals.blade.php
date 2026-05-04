<x-layout>
    <x-slot:title>{{ $targetDate->format('Y年n月j日') }}の新着入荷バイク | MotoHub</x-slot:title>
    <x-slot:metaDescription>{{ $targetDate->format('Y年n月j日') }}の新着入荷バイク{{ number_format($data['totalNew']) }}台。メーカー別・価格帯別の内訳と人気車種TOP20をチェック。</x-slot:metaDescription>
    <x-slot:canonical>{{ route('bikes.new_arrivals', $targetDate->toDateString()) }}</x-slot:canonical>
    <x-slot:navigation><x-navigation :showSearch="true" /></x-slot:navigation>

    <div class="max-w-4xl mx-auto px-4 py-6 sm:py-10">
        {{-- パンくず --}}
        <nav class="flex text-xs font-bold text-gray-400 mb-6">
            <ol class="flex items-center space-x-2">
                <li><a href="{{ route('bikes.index') }}" class="hover:text-black transition">HOME</a></li>
                <li><span class="text-gray-300">/</span></li>
                <li class="text-gray-600">新着入荷</li>
            </ol>
        </nav>

        <h1 class="text-2xl sm:text-3xl font-black text-gray-900 mb-2 flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-7 h-7 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
            新着入荷バイク
        </h1>
        <p class="text-sm text-gray-500 mb-6">日別の新着入荷データ</p>

        {{-- 直近7日間ナビ --}}
        @php
            $prevWeekDate = $weekStart->copy()->subDays(7)->toDateString();
            $nextWeekDate = $weekEnd->copy()->addDay()->toDateString();
            $canGoNext = $weekEnd->copy()->addDay()->lte(\Carbon\Carbon::today());
        @endphp
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 sm:p-5 mb-6">
            <div class="flex items-center justify-between mb-4">
                <a href="{{ route('bikes.new_arrivals', $prevWeekDate) }}" class="p-2 rounded-lg hover:bg-gray-100 transition" title="前の週">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                </a>
                <h2 class="text-sm font-black text-gray-900">
                    {{ $weekStart->format('Y年n月j日') }}〜{{ $weekEnd->format('n月j日') }}
                </h2>
                @if($canGoNext)
                <a href="{{ route('bikes.new_arrivals', $nextWeekDate) }}" class="p-2 rounded-lg hover:bg-gray-100 transition" title="次の週">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </a>
                @else
                <div class="p-2 w-9"></div>
                @endif
            </div>

            <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:4px">
                @foreach($weekDays as $day)
                @php
                    $isSelected = $targetDate->toDateString() === $day['date'];
                    $isSun = $day['dow'] === '日';
                    $isSat = $day['dow'] === '土';
                @endphp
                @if($day['isFuture'])
                <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;padding:8px 2px;border-radius:12px;color:#d1d5db">
                    <span style="font-size:10px;font-weight:700">{{ $day['dow'] }}</span>
                    <span style="font-size:13px;font-weight:900">{{ $day['label'] }}</span>
                    <span style="font-size:10px;font-weight:700;margin-top:2px">-</span>
                </div>
                @else
                <a href="{{ route('bikes.new_arrivals', $day['date']) }}"
                   style="display:flex;flex-direction:column;align-items:center;justify-content:center;padding:8px 2px;border-radius:12px;text-decoration:none;transition:all .15s;{{ $isSelected ? 'background:#16a34a;color:#fff;box-shadow:0 4px 6px -1px rgba(22,163,74,.3)' : '' }}"
                   onmouseover="{{ $isSelected ? '' : "this.style.background='#f9fafb'" }}"
                   onmouseout="{{ $isSelected ? '' : "this.style.background=''" }}">
                    <span style="font-size:10px;font-weight:700;color:{{ $isSelected ? '#bbf7d0' : ($isSun ? '#f87171' : ($isSat ? '#60a5fa' : '#9ca3af')) }}">{{ $day['dow'] }}</span>
                    <span style="font-size:13px;font-weight:900;color:{{ $isSelected ? '#fff' : '#111827' }}">{{ $day['label'] }}</span>
                    <span style="font-size:10px;font-weight:700;margin-top:2px;color:{{ $isSelected ? '#dcfce7' : '#9ca3af' }}">
                        {{ $day['count'] > 0 ? number_format($day['count']) . '台' : '-' }}
                    </span>
                </a>
                @endif
                @endforeach
            </div>
        </div>

        {{-- 日付ヘッダー --}}
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 mb-6">
            <div class="flex items-center gap-4">
                <div class="w-14 h-14 bg-green-50 rounded-2xl flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-7 h-7 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                </div>
                <div>
                    <p class="text-xs font-bold text-gray-400">{{ $targetDate->format('Y年n月j日') }}（{{ ['日','月','火','水','木','金','土'][$targetDate->dayOfWeek] }}）</p>
                    <p class="text-3xl font-black text-gray-900">{{ number_format($data['totalNew']) }}<span class="text-base text-gray-400 ml-1">台入荷</span></p>
                </div>
            </div>
        </div>

        {{-- Xシェアボタン --}}
        @php
            $newArrivalsShareText = '本日の新着バイク入荷' . number_format($data['totalNew']) . '台！メーカー別・価格帯別の入荷データをチェック #MotoHub #中古バイク #バイク好きと繋がりたい #バイク乗りと繋がりたい #バイクのある生活 #ツーリング #新着入荷';
        @endphp
        <a href="https://twitter.com/intent/tweet?text={{ urlencode($newArrivalsShareText) }}&url={{ urlencode(route('bikes.new_arrivals', $targetDate->toDateString())) }}"
           target="_blank" rel="noopener noreferrer"
           class="inline-flex items-center gap-1.5 mb-6 px-4 py-2 bg-gray-900 text-white text-xs font-bold rounded-full hover:bg-gray-700 transition">
            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
            シェア
        </a>

        @if($data['totalNew'] > 0)
        {{-- メーカー別 横棒グラフ --}}
        @if($data['makerCounts']->isNotEmpty())
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 mb-6">
            <h2 class="text-base font-black text-gray-900 mb-4 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                メーカー別入荷
            </h2>
            @php $maxMaker = $data['makerCounts']->max('cnt') ?: 1; @endphp
            <div class="space-y-2">
                @foreach($data['makerCounts']->take(10) as $maker)
                <div class="flex items-center gap-3">
                    <span class="text-xs font-bold text-gray-600 w-28 text-right flex-shrink-0">{{ $maker['name'] }}</span>
                    <div class="flex-1 bg-gray-100 rounded-full h-6 overflow-hidden">
                        <div class="bg-blue-400 h-full rounded-full flex items-center justify-end pr-2 transition-all"
                             style="width: {{ max(($maker['cnt'] / $maxMaker) * 100, 5) }}%">
                            <span class="text-[10px] font-black text-white">{{ number_format($maker['cnt']) }}</span>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- 価格帯別 --}}
        @if($data['priceBands']->isNotEmpty())
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 mb-6">
            <h2 class="text-base font-black text-gray-900 mb-4 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                価格帯別入荷
            </h2>
            @php $maxPrice = $data['priceBands']->max('cnt') ?: 1; @endphp
            <div class="space-y-2">
                @foreach($data['priceBands'] as $range)
                <div class="flex items-center gap-3">
                    <span class="text-xs font-bold text-gray-600 w-24 text-right flex-shrink-0">{{ $range->price_range }}</span>
                    <div class="flex-1 bg-gray-100 rounded-full h-6 overflow-hidden">
                        <div class="bg-green-400 h-full rounded-full flex items-center justify-end pr-2 transition-all"
                             style="width: {{ max(($range->cnt / $maxPrice) * 100, 5) }}%">
                            <span class="text-[10px] font-black text-white">{{ number_format($range->cnt) }}</span>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- チャート（メーカー別ドーナツ + 価格帯別横棒） --}}
        @php
            $makerColors = [
                'ホンダ' => '#E60012', 'ヤマハ' => '#0068B7', 'スズキ' => '#FFD700',
                'カワサキ' => '#00A550', 'ハーレーダビッドソン' => '#F47920',
            ];
            $defaultColor = '#9CA3AF';
            $top5 = $data['makerCounts']->take(5);
            $othersCount = $data['makerCounts']->skip(5)->sum('cnt');
            $donutLabels = $top5->pluck('name')->toArray();
            $donutData = $top5->pluck('cnt')->toArray();
            $donutColors = $top5->map(fn($m) => $makerColors[$m['name']] ?? $defaultColor)->toArray();
            if ($othersCount > 0) {
                $donutLabels[] = 'その他';
                $donutData[] = $othersCount;
                $donutColors[] = $defaultColor;
            }
        @endphp
        @if($data['makerCounts']->isNotEmpty() || $data['priceBands']->isNotEmpty())
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
            @if($data['makerCounts']->isNotEmpty())
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
                <h3 class="text-sm font-black text-gray-900 mb-3">メーカー別シェア</h3>
                <div style="height:280px;position:relative">
                    <canvas id="makerDonut"></canvas>
                </div>
            </div>
            @endif
            @if($data['priceBands']->isNotEmpty())
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
                <h3 class="text-sm font-black text-gray-900 mb-3">価格帯別分布</h3>
                <div style="height:280px;position:relative">
                    <canvas id="priceBar"></canvas>
                </div>
            </div>
            @endif
        </div>
        @endif

        {{-- 車種別TOP20 --}}
        @if($data['modelTop20']->isNotEmpty())
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 mb-6">
            <h2 class="text-base font-black text-gray-900 mb-4 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-yellow-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg>
                車種別 TOP20
            </h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100">
                            <th class="text-left py-2 px-2 text-xs font-bold text-gray-400 w-10">#</th>
                            <th class="text-left py-2 px-2 text-xs font-bold text-gray-400">車種</th>
                            <th class="text-left py-2 px-2 text-xs font-bold text-gray-400">メーカー</th>
                            <th class="text-right py-2 px-2 text-xs font-bold text-gray-400">台数</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($data['modelTop20'] as $i => $model)
                        <tr class="border-b border-gray-50 hover:bg-gray-50 transition">
                            <td class="py-2.5 px-2 font-black text-gray-400">
                                @if($i < 3)
                                    <span class="text-base">{{ ['🥇','🥈','🥉'][$i] }}</span>
                                @else
                                    {{ $i + 1 }}
                                @endif
                            </td>
                            <td class="py-2.5 px-2">
                                @if($model['seo_url'])
                                <a href="{{ $model['seo_url'] }}" class="font-bold text-gray-900 hover:text-blue-600 transition">{{ $model['name'] }}</a>
                                @else
                                <span class="font-bold text-gray-900">{{ $model['name'] }}</span>
                                @endif
                            </td>
                            <td class="py-2.5 px-2 text-gray-500">{{ $model['manufacturer'] }}</td>
                            <td class="py-2.5 px-2 text-right font-black text-green-600">{{ number_format($model['cnt']) }}台</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        {{-- 7日間推移 --}}
        @if(!empty($data['trend7days']))
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 mb-6">
            <h2 class="text-base font-black text-gray-900 mb-4 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                7日間推移
            </h2>
            <div style="height:250px;position:relative">
                <canvas id="trendLine"></canvas>
            </div>
        </div>
        @endif

        @else
        <div class="text-center py-16 text-gray-400">
            <p class="text-lg font-bold">この日の新着入荷データはありません</p>
            <p class="text-sm mt-2">別の日付を選んでください</p>
        </div>
        @endif

        <p class="text-[10px] text-gray-400 mt-6 text-center">※MotoHub掲載車両の新規登録データに基づく集計です</p>
    </div>

    {{-- Chart.js --}}
    @if($data['totalNew'] > 0)
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // メーカー別ドーナツ
        var donutEl = document.getElementById('makerDonut');
        if (donutEl) {
            new Chart(donutEl, {
                type: 'doughnut',
                data: {
                    labels: @json($donutLabels),
                    datasets: [{
                        data: @json($donutData),
                        backgroundColor: @json($donutColors),
                        borderWidth: 0,
                        hoverOffset: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '60%',
                    plugins: {
                        legend: { position: 'bottom', labels: { font: { size: 11, weight: 'bold' }, padding: 12 } },
                        tooltip: {
                            callbacks: {
                                label: function(ctx) {
                                    var total = ctx.dataset.data.reduce(function(a,b){return a+b},0);
                                    var pct = ((ctx.raw / total) * 100).toFixed(1);
                                    return ctx.label + ': ' + ctx.raw.toLocaleString() + '台 (' + pct + '%)';
                                }
                            }
                        }
                    }
                },
                plugins: [{
                    id: 'centerText',
                    afterDraw: function(chart) {
                        var total = chart.data.datasets[0].data.reduce(function(a,b){return a+b},0);
                        var ctx = chart.ctx;
                        var cx = (chart.chartArea.left + chart.chartArea.right) / 2;
                        var cy = (chart.chartArea.top + chart.chartArea.bottom) / 2;
                        ctx.save();
                        ctx.textAlign = 'center';
                        ctx.fillStyle = '#9CA3AF';
                        ctx.font = 'bold 11px sans-serif';
                        ctx.fillText('合計', cx, cy - 10);
                        ctx.fillStyle = '#111827';
                        ctx.font = 'bold 20px sans-serif';
                        ctx.fillText(total.toLocaleString() + '台', cx, cy + 14);
                        ctx.restore();
                    }
                }]
            });
        }

        // 価格帯横棒
        var priceEl = document.getElementById('priceBar');
        if (priceEl) {
            new Chart(priceEl, {
                type: 'bar',
                data: {
                    labels: @json($data['priceBands']->pluck('price_range')->toArray()),
                    datasets: [{
                        data: @json($data['priceBands']->pluck('cnt')->toArray()),
                        backgroundColor: '#34D399',
                        borderRadius: 6,
                        barThickness: 28
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y',
                    plugins: {
                        legend: { display: false },
                        tooltip: { callbacks: { label: function(ctx) { return ctx.raw.toLocaleString() + '台'; } } }
                    },
                    scales: {
                        x: { grid: { display: false }, ticks: { font: { size: 11 } } },
                        y: { grid: { display: false }, ticks: { font: { size: 11, weight: 'bold' } } }
                    }
                }
            });
        }

        // 7日間推移折れ線
        var trendEl = document.getElementById('trendLine');
        if (trendEl) {
            new Chart(trendEl, {
                type: 'line',
                data: {
                    labels: @json(collect($data['trend7days'])->pluck('label')->toArray()),
                    datasets: [{
                        label: '新着入荷',
                        data: @json(collect($data['trend7days'])->pluck('count')->toArray()),
                        borderColor: '#16a34a',
                        backgroundColor: 'rgba(22,163,74,0.1)',
                        fill: true,
                        tension: 0.3,
                        pointRadius: 5,
                        pointBackgroundColor: '#16a34a',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: { callbacks: { label: function(ctx) { return ctx.raw.toLocaleString() + '台'; } } }
                    },
                    scales: {
                        x: { grid: { display: false }, ticks: { font: { size: 11, weight: 'bold' } } },
                        y: { beginAtZero: true, grid: { color: '#f3f4f6' }, ticks: { font: { size: 11 } } }
                    }
                }
            });
        }
    });
    </script>
    @endif
</x-layout>
