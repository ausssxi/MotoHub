<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body {
    width: 1200px;
    height: 630px;
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
    overflow: hidden;
  }
  .chart-wrap {
    position: absolute;
    inset: 0;
    padding: 24px 32px 20px;
  }
  .chart-wrap canvas {
    width: 100% !important;
    height: 100% !important;
  }
  .branding {
    position: absolute;
    bottom: 14px;
    right: 28px;
    font-family: sans-serif;
    font-size: 14px;
    font-weight: 800;
    color: rgba(255,255,255,0.12);
    letter-spacing: 0.05em;
  }
</style>
</head>
<body>
  <div class="chart-wrap">
    <canvas id="chart"></canvas>
  </div>
  <div class="branding">MotoHub</div>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.js"></script>
  <script>
  document.addEventListener('DOMContentLoaded', function() {
    const labels = @json($chartLabels);
    const prices = @json($chartPrices);
    const listingPrice = {{ $listingPrice ?? 'null' }};
    const chartTitle = @json($chartTitle);

    // カスタムプラグイン: この車両の価格ライン + 赤丸 + ラベル
    const listingLinePlugin = listingPrice ? {
      id: 'listingPriceLine',
      afterDatasetsDraw(chart) {
        const { ctx, chartArea, scales } = chart;
        if (!chartArea || !scales.y) return;
        const y = scales.y.getPixelForValue(listingPrice);
        if (y < chartArea.top - 10 || y > chartArea.bottom + 10) return;

        ctx.save();

        // 破線
        ctx.setLineDash([10, 5]);
        ctx.strokeStyle = 'rgba(239, 68, 68, 0.7)';
        ctx.lineWidth = 2;
        ctx.beginPath();
        ctx.moveTo(chartArea.left, y);
        ctx.lineTo(chartArea.right, y);
        ctx.stroke();

        // 赤丸（右端）
        ctx.setLineDash([]);
        ctx.fillStyle = '#ef4444';
        ctx.beginPath();
        ctx.arc(chartArea.right, y, 8, 0, Math.PI * 2);
        ctx.fill();
        ctx.strokeStyle = 'rgba(255,255,255,0.8)';
        ctx.lineWidth = 2;
        ctx.stroke();

        // ラベル（赤丸の左側）
        const label = 'この車両 ' + listingPrice.toFixed(1) + '万円';
        ctx.font = 'bold 15px sans-serif';
        ctx.textAlign = 'right';

        // 背景ボックス
        const textW = ctx.measureText(label).width;
        const boxX = chartArea.right - 22 - textW - 12;
        const boxY = y - 22;
        ctx.fillStyle = 'rgba(239, 68, 68, 0.9)';
        ctx.beginPath();
        ctx.roundRect(boxX, boxY, textW + 12, 26, 4);
        ctx.fill();

        // テキスト
        ctx.fillStyle = '#ffffff';
        ctx.fillText(label, chartArea.right - 28, y - 4);

        ctx.restore();
      }
    } : { id: 'noop' };

    new Chart(document.getElementById('chart'), {
      type: 'line',
      data: {
        labels: labels,
        datasets: [{
          label: '平均価格',
          data: prices,
          borderColor: '#3B82F6',
          backgroundColor: function(ctx) {
            const chart = ctx.chart;
            const { ctx: c, chartArea } = chart;
            if (!chartArea) return 'rgba(59,130,246,0.1)';
            const g = c.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
            g.addColorStop(0, 'rgba(59,130,246,0.25)');
            g.addColorStop(1, 'rgba(59,130,246,0.02)');
            return g;
          },
          fill: true,
          tension: 0.35,
          pointRadius: 7,
          pointBackgroundColor: '#3B82F6',
          pointBorderColor: '#1e293b',
          pointBorderWidth: 3,
          borderWidth: 3.5,
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        animation: false,
        plugins: {
          legend: { display: false },
          tooltip: { enabled: false },
          title: {
            display: true,
            text: chartTitle,
            color: 'rgba(255,255,255,0.75)',
            font: { size: 24, weight: 'bold', family: 'sans-serif' },
            padding: { bottom: 20 },
          },
        },
        scales: {
          x: {
            grid: { color: 'rgba(255,255,255,0.06)' },
            ticks: {
              color: 'rgba(255,255,255,0.6)',
              font: { size: 16, weight: '600', family: 'sans-serif' },
            },
            border: { display: false },
          },
          y: {
            grid: { color: 'rgba(255,255,255,0.06)' },
            ticks: {
              color: 'rgba(255,255,255,0.45)',
              font: { size: 14, family: 'sans-serif' },
              callback: function(v) { return v.toFixed(1) + '万'; },
              maxTicksLimit: 6,
            },
            border: { display: false },
          },
        },
        layout: {
          padding: { top: 4, right: 12, bottom: 4, left: 4 },
        },
      },
      plugins: [listingLinePlugin],
    });
  });
  </script>
</body>
</html>
