<x-layout>
    <x-slot:title>{{ $targetDate->format('Y年n月j日') }}の値下げバイク | MotoHub</x-slot:title>
    <x-slot:metaDescription>{{ $targetDate->format('Y年n月j日') }}に値下げされたバイク{{ number_format($data['totalDrops']) }}台。値下げ額・値下げ率ランキングTOP20をチェック。</x-slot:metaDescription>
    <x-slot:canonical>{{ route('bikes.price_drops', $targetDate->toDateString()) }}</x-slot:canonical>
    <x-slot:ogImage>{{ route('bikes.price_drops_ogp') }}</x-slot:ogImage>
    <x-slot:navigation><x-navigation :showSearch="true" /></x-slot:navigation>

    <div class="max-w-4xl mx-auto px-4 py-6 sm:py-10">
        {{-- パンくず --}}
        <nav class="flex text-xs font-bold text-gray-400 mb-6">
            <ol class="flex items-center space-x-2">
                <li><a href="{{ route('bikes.index') }}" class="hover:text-black transition">HOME</a></li>
                <li><span class="text-gray-300">/</span></li>
                <li class="text-gray-600">値下げバイク</li>
            </ol>
        </nav>

        <h1 class="text-2xl sm:text-3xl font-black text-gray-900 mb-2 flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-7 h-7 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"/></svg>
            値下げバイク
        </h1>
        <p class="text-sm text-gray-500 mb-2">日別の値下げデータ</p>
        <a href="{{ route('market') }}" class="inline-flex items-center gap-1 mb-6 text-xs font-black text-blue-600 hover:underline">
            <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>中古バイク相場トップへ
        </a>

        {{-- 直近7日間ナビ --}}
        @php
            $prevWeekDate = $weekStart->copy()->subDays(7)->toDateString();
            $nextWeekDate = $weekEnd->copy()->addDay()->toDateString();
            $canGoNext = $weekEnd->copy()->addDay()->lte(\Carbon\Carbon::today());
        @endphp
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 sm:p-5 mb-6">
            <div class="flex items-center justify-between mb-4">
                <a href="{{ route('bikes.price_drops', $prevWeekDate) }}" class="p-2 rounded-lg hover:bg-gray-100 transition" title="前の週">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                </a>
                <h2 class="text-sm font-black text-gray-900">
                    {{ $weekStart->format('Y年n月j日') }}〜{{ $weekEnd->format('n月j日') }}
                </h2>
                @if($canGoNext)
                <a href="{{ route('bikes.price_drops', $nextWeekDate) }}" class="p-2 rounded-lg hover:bg-gray-100 transition" title="次の週">
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
                <a href="{{ route('bikes.price_drops', $day['date']) }}"
                   style="display:flex;flex-direction:column;align-items:center;justify-content:center;padding:8px 2px;border-radius:12px;text-decoration:none;transition:all .15s;{{ $isSelected ? 'background:#dc2626;color:#fff;box-shadow:0 4px 6px -1px rgba(220,38,38,.3)' : '' }}"
                   onmouseover="{{ $isSelected ? '' : "this.style.background='#f9fafb'" }}"
                   onmouseout="{{ $isSelected ? '' : "this.style.background=''" }}">
                    <span style="font-size:10px;font-weight:700;color:{{ $isSelected ? '#fecaca' : ($isSun ? '#f87171' : ($isSat ? '#60a5fa' : '#9ca3af')) }}">{{ $day['dow'] }}</span>
                    <span style="font-size:13px;font-weight:900;color:{{ $isSelected ? '#fff' : '#111827' }}">{{ $day['label'] }}</span>
                    <span style="font-size:10px;font-weight:700;margin-top:2px;color:{{ $isSelected ? '#fee2e2' : '#9ca3af' }}">
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
                <div class="w-14 h-14 bg-red-50 rounded-2xl flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-7 h-7 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"/></svg>
                </div>
                <div>
                    <p class="text-xs font-bold text-gray-400">{{ $targetDate->format('Y年n月j日') }}（{{ ['日','月','火','水','木','金','土'][$targetDate->dayOfWeek] }}）</p>
                    <p class="text-3xl font-black text-gray-900">{{ number_format($data['totalDrops']) }}<span class="text-base text-gray-400 ml-1">台値下げ</span></p>
                </div>
            </div>
        </div>

        {{-- Xシェアボタン --}}
        @php
            $priceDropsShareText = '本日の値下げバイク' . number_format($data['totalDrops']) . '台！お買い得車両をチェック #MotoHub #中古バイク #値下げ #バイク好きと繋がりたい #バイク乗りと繋がりたい #バイクのある生活 #ツーリング';
        @endphp
        <a href="https://twitter.com/intent/tweet?text={{ urlencode($priceDropsShareText) }}&url={{ urlencode(route('bikes.price_drops', $targetDate->toDateString())) }}"
           target="_blank" rel="noopener noreferrer"
           class="inline-flex items-center gap-1.5 mb-6 px-4 py-2 bg-gray-900 text-white text-xs font-bold rounded-full hover:bg-gray-700 transition">
            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
            シェア
        </a>

        @if($data['totalDrops'] > 0)

        {{-- 値下げ額ランキングTOP20 --}}
        @if($data['topByAmount']->isNotEmpty())
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 mb-6">
            <h2 class="text-base font-black text-gray-900 mb-4 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                値下げ額 TOP20
            </h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100">
                            <th class="text-left py-2 px-2 text-xs font-bold text-gray-400 w-10">#</th>
                            <th class="text-left py-2 px-2 text-xs font-bold text-gray-400">車種</th>
                            <th class="text-right py-2 px-2 text-xs font-bold text-gray-400">旧価格</th>
                            <th class="text-right py-2 px-2 text-xs font-bold text-gray-400">新価格</th>
                            <th class="text-right py-2 px-2 text-xs font-bold text-gray-400">値下げ</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($data['topByAmount'] as $i => $item)
                        <tr class="border-b border-gray-50 hover:bg-gray-50 transition">
                            <td class="py-2.5 px-2 font-black text-gray-400">
                                @if($i < 3)
                                    <span class="text-base">{{ ['🥇','🥈','🥉'][$i] }}</span>
                                @else
                                    {{ $i + 1 }}
                                @endif
                            </td>
                            <td class="py-2.5 px-2">
                                @if($item['seo_url'])
                                <a href="{{ $item['seo_url'] }}" class="font-bold text-gray-900 hover:text-blue-600 transition">{{ $item['bike_name'] }}</a>
                                @else
                                <span class="font-bold text-gray-900">{{ $item['bike_name'] }}</span>
                                @endif
                                <span class="text-xs text-gray-400 ml-1">{{ $item['manufacturer'] }}</span>
                            </td>
                            <td class="py-2.5 px-2 text-right text-gray-400 line-through text-xs">{{ number_format($item['old_price'] / 10000, 1) }}万</td>
                            <td class="py-2.5 px-2 text-right font-bold text-gray-900">{{ number_format($item['new_price'] / 10000, 1) }}万</td>
                            <td class="py-2.5 px-2 text-right font-black text-red-600">-{{ number_format($item['diff'] / 10000, 1) }}万</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        {{-- 値下げ率ランキングTOP20 --}}
        @if($data['topByRate']->isNotEmpty())
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 mb-6">
            <h2 class="text-base font-black text-gray-900 mb-4 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-orange-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21l-7-5-7 5V5a2 2 0 012-2h10a2 2 0 012 2v16z"/></svg>
                値下げ率 TOP20
            </h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100">
                            <th class="text-left py-2 px-2 text-xs font-bold text-gray-400 w-10">#</th>
                            <th class="text-left py-2 px-2 text-xs font-bold text-gray-400">車種</th>
                            <th class="text-right py-2 px-2 text-xs font-bold text-gray-400">旧価格</th>
                            <th class="text-right py-2 px-2 text-xs font-bold text-gray-400">新価格</th>
                            <th class="text-right py-2 px-2 text-xs font-bold text-gray-400">値下げ率</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($data['topByRate'] as $i => $item)
                        <tr class="border-b border-gray-50 hover:bg-gray-50 transition">
                            <td class="py-2.5 px-2 font-black text-gray-400">
                                @if($i < 3)
                                    <span class="text-base">{{ ['🥇','🥈','🥉'][$i] }}</span>
                                @else
                                    {{ $i + 1 }}
                                @endif
                            </td>
                            <td class="py-2.5 px-2">
                                @if($item['seo_url'])
                                <a href="{{ $item['seo_url'] }}" class="font-bold text-gray-900 hover:text-blue-600 transition">{{ $item['bike_name'] }}</a>
                                @else
                                <span class="font-bold text-gray-900">{{ $item['bike_name'] }}</span>
                                @endif
                                <span class="text-xs text-gray-400 ml-1">{{ $item['manufacturer'] }}</span>
                            </td>
                            <td class="py-2.5 px-2 text-right text-gray-400 line-through text-xs">{{ number_format($item['old_price'] / 10000, 1) }}万</td>
                            <td class="py-2.5 px-2 text-right font-bold text-gray-900">{{ number_format($item['new_price'] / 10000, 1) }}万</td>
                            <td class="py-2.5 px-2 text-right font-black text-orange-600">-{{ $item['rate'] }}%</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        {{-- メーカー別 横棒グラフ --}}
        @if($data['makerCounts']->isNotEmpty())
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 mb-6">
            <h2 class="text-base font-black text-gray-900 mb-4 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                メーカー別値下げ
            </h2>
            @php $maxMaker = $data['makerCounts']->max('cnt') ?: 1; @endphp
            <div class="space-y-2">
                @foreach($data['makerCounts']->take(10) as $maker)
                <div class="flex items-center gap-3">
                    <span class="text-xs font-bold text-gray-600 w-28 text-right flex-shrink-0">{{ $maker['name'] }}</span>
                    <div class="flex-1 bg-gray-100 rounded-full h-6 overflow-hidden">
                        <div class="bg-red-400 h-full rounded-full flex items-center justify-end pr-2 transition-all"
                             style="width: {{ max(($maker['cnt'] / $maxMaker) * 100, 5) }}%">
                            <span class="text-[10px] font-black text-white">{{ number_format($maker['cnt']) }}</span>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- チャート（メーカー別ドーナツ） --}}
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
        @if($data['makerCounts']->isNotEmpty())
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
                <h3 class="text-sm font-black text-gray-900 mb-3">メーカー別シェア</h3>
                <div style="height:280px;position:relative">
                    <canvas id="makerDonut"></canvas>
                </div>
            </div>
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
                <h3 class="text-sm font-black text-gray-900 mb-3">7日間推移</h3>
                <div style="height:280px;position:relative">
                    <canvas id="trendLine"></canvas>
                </div>
            </div>
        </div>
        @endif

        @else
        <div class="text-center py-16 text-gray-400">
            <p class="text-lg font-bold">この日の値下げデータはありません</p>
            <p class="text-sm mt-2">別の日付を選んでください</p>
        </div>
        @endif

        <p class="text-[10px] text-gray-400 mt-6 text-center">※MotoHub掲載車両の価格変動データに基づく集計です</p>
    </div>

    {{-- Chart.js --}}
    @if($data['totalDrops'] > 0)
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

        // 7日間推移折れ線
        var trendEl = document.getElementById('trendLine');
        if (trendEl) {
            new Chart(trendEl, {
                type: 'line',
                data: {
                    labels: @json(collect($data['trend7days'])->pluck('label')->toArray()),
                    datasets: [{
                        label: '値下げ',
                        data: @json(collect($data['trend7days'])->pluck('count')->toArray()),
                        borderColor: '#dc2626',
                        backgroundColor: 'rgba(220,38,38,0.1)',
                        fill: true,
                        tension: 0.3,
                        pointRadius: 5,
                        pointBackgroundColor: '#dc2626',
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
