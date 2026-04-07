{{-- メーカー別シェア ドーナツ + 価格帯別 横棒グラフ --}}
@php
    $makerColors = [
        'ホンダ' => '#E60012',
        'ヤマハ' => '#0068B7',
        'スズキ' => '#FFD700',
        'カワサキ' => '#00A550',
        'ハーレーダビッドソン' => '#F47920',
    ];
    $defaultColor = '#9CA3AF';

    // ドーナツ用: 上位5メーカー + その他
    $top5 = $ranking['makerRanking']->take(5);
    $othersCount = $ranking['makerRanking']->skip(5)->sum('sold_count');
    $donutLabels = $top5->pluck('name')->toArray();
    $donutData = $top5->pluck('sold_count')->toArray();
    $donutColors = $top5->map(fn($m) => $makerColors[$m['name']] ?? $defaultColor)->toArray();
    if ($othersCount > 0) {
        $donutLabels[] = 'その他';
        $donutData[] = $othersCount;
        $donutColors[] = $defaultColor;
    }
@endphp

@if($ranking['makerRanking']->isNotEmpty() || (isset($ranking['priceRanges']) && $ranking['priceRanges']->isNotEmpty()))
<div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
    {{-- ドーナツチャート --}}
    @if($ranking['makerRanking']->isNotEmpty())
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
        <h3 class="text-sm font-black text-gray-900 mb-3">メーカー別シェア</h3>
        <div style="height:280px;position:relative">
            <canvas id="makerDonut"></canvas>
        </div>
    </div>
    @endif

    {{-- 価格帯別 横棒 --}}
    @if(isset($ranking['priceRanges']) && $ranking['priceRanges']->isNotEmpty())
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
        <h3 class="text-sm font-black text-gray-900 mb-3">価格帯別販売分布</h3>
        <div style="height:280px;position:relative">
            <canvas id="priceBar"></canvas>
        </div>
    </div>
    @endif
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // ドーナツ
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
        var priceLabels = @json(isset($ranking['priceRanges']) ? $ranking['priceRanges']->pluck('price_range')->toArray() : []);
        var priceData = @json(isset($ranking['priceRanges']) ? $ranking['priceRanges']->pluck('cnt')->toArray() : []);
        new Chart(priceEl, {
            type: 'bar',
            data: {
                labels: priceLabels,
                datasets: [{
                    data: priceData,
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
});
</script>
@endif
