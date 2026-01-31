/**
 * MotoHub Price Stats Logic
 * 車両詳細ページの「市場価格分析」チャートを描画します。
 */

document.addEventListener('DOMContentLoaded', async () => {
    // データ属性を持った親要素を取得
    const container = document.getElementById('price-stats-container');
    if (!container) return;

    // データ属性から値を取得 (Bladeから渡された値)
    const bikeModelIdStr = container.dataset.modelId;
    const bikeModelId = parseInt(bikeModelIdStr);
    
    const totalPriceStr = container.dataset.totalPrice;
    // カンマなどを除去して数値に変換
    const currentPrice = parseFloat(String(totalPriceStr).replace(/,/g, '')) || 0;

    const loading = document.getElementById('price-stats-loading');
    const content = document.getElementById('price-stats-content');

    // モデルIDがない場合は非表示にして終了
    if (isNaN(bikeModelId) || !bikeModelId) {
        if (loading) loading.style.display = 'none';
        return;
    }
    
    try {
        // APIからデータ取得
        const response = await fetch(`/api/stats/price/${bikeModelId}`);
        if (!response.ok) throw new Error('API Error');
        const data = await response.json();

        if (!data.distribution || data.distribution.length === 0) {
            if (loading) loading.innerHTML = '<p class="text-xs text-gray-400">データが不足しています</p>';
            return;
        }

        // 統計値の表示更新
        const updateText = (id, value) => {
            const el = document.getElementById(id);
            if (el) el.textContent = value;
        };
        
        updateText('stat-avg', data.avg);
        updateText('stat-min', data.min);
        updateText('stat-max', data.max);

        // チャート描画準備
        const chartCanvas = document.getElementById('priceChart');
        if (chartCanvas) {
            const ctx = chartCanvas.getContext('2d');
            const labels = data.distribution.map(d => d.label);
            const counts = data.distribution.map(d => d.count);
            
            // 現在の車両がどの価格帯にいるか特定して色を変える
            const bgColors = data.distribution.map(d => {
                return (currentPrice >= d.range_min && currentPrice < d.range_max) 
                    ? 'rgba(239, 68, 68, 0.8)' // 赤色（この車両）
                    : 'rgba(59, 130, 246, 0.2)'; // 青色（その他）
            });

            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: '台数',
                        data: counts,
                        backgroundColor: bgColors,
                        borderColor: 'rgba(59, 130, 246, 0)',
                        borderWidth: 1,
                        borderRadius: 4,
                        barPercentage: 0.8,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                title: (ctx) => ctx[0].label + 'の車両',
                                label: (ctx) => ctx.raw + '台'
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: { color: '#f3f4f6' },
                            ticks: { font: { size: 10 } }
                        },
                        x: {
                            grid: { display: false },
                            ticks: { font: { size: 9 } }
                        }
                    }
                }
            });
        }

        // 表示切り替え
        if (loading) loading.classList.add('hidden');
        if (content) {
            content.classList.remove('hidden');
            content.classList.add('animate-in', 'fade-in');
        }

    } catch (e) {
        console.error('Price stats error:', e);
        if (loading) loading.innerHTML = '<p class="text-xs text-gray-400">データの取得に失敗しました</p>';
    }
});