/**
 * MotoHub Model Detail Page Scripts
 * 統計データの数値反映とチャート描画を行います。
 */
document.addEventListener('DOMContentLoaded', () => {
    // グローバル変数からデータを取得（Blade側で window.bikeModelStats = ... と定義されている前提）
    const stats = window.bikeModelStats || {};
    const history = window.bikeModelHistory || {};

    // ==========================================
    // 0. 統計数値・ローディング表示のDOM更新
    // ==========================================
    const loading = document.getElementById('price-stats-loading');
    const content = document.getElementById('price-stats-content');

    // データがある場合のみ数値を反映して表示
    if (stats && stats.count > 0) {
        const setVal = (id, val) => {
            const el = document.getElementById(id);
            if (el) el.textContent = val;
        };

        // HTML側のID (stat-avgなど) に数値をセット
        setVal('stat-avg', stats.avg);
        setVal('stat-min', stats.min);
        setVal('stat-max', stats.max);

        // ローディングを消してコンテンツを表示
        if (loading) loading.style.display = 'none';
        if (content) {
            content.classList.remove('hidden');
            content.classList.add('animate-in', 'fade-in');
        }
    } else {
        // データがない場合
        if (loading) loading.innerHTML = '<p class="text-xs text-gray-400 font-bold">データが不足しています</p>';
    }

    // ==========================================
    // 1. 価格分布チャート (Stats)
    // ==========================================
    if (stats.distribution && stats.distribution.length > 0) {
        const chartCanvas = document.getElementById('priceChart');
        if (chartCanvas) {
            try {
                const ctx = chartCanvas.getContext('2d');
                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: stats.distribution.map(d => d.label),
                        datasets: [{
                            label: '台数',
                            data: stats.distribution.map(d => d.count),
                            backgroundColor: 'rgba(59, 130, 246, 0.5)',
                            borderColor: 'rgba(59, 130, 246, 1)',
                            borderWidth: 1,
                            borderRadius: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: {
                            y: { 
                                beginAtZero: true, 
                                grid: { color: '#f3f4f6' },
                                ticks: { stepSize: 1 }
                            },
                            x: { 
                                grid: { display: false }, 
                                ticks: { font: { size: 10 } } 
                            }
                        }
                    }
                });
            } catch (e) {
                console.error('Price chart error:', e);
            }
        }
    }

    // ==========================================
    // 2. 価格推移チャート (History)
    // ==========================================
    if (history.prices && history.prices.length > 0) {
        const historyCanvas = document.getElementById('historyChart');
        if (historyCanvas) {
            try {
                const ctxHistory = historyCanvas.getContext('2d');
                new Chart(ctxHistory, {
                    type: 'line',
                    data: {
                        labels: history.labels,
                        datasets: [{
                            label: '平均価格推移 (万円)',
                            data: history.prices,
                            borderColor: 'rgb(234, 88, 12)', // オレンジ
                            backgroundColor: 'rgba(234, 88, 12, 0.1)',
                            fill: true,
                            tension: 0.4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: {
                            y: { beginAtZero: false, grid: { color: '#f3f4f6' } },
                            x: { grid: { display: false } }
                        }
                    }
                });
            } catch (e) {
                console.error('History chart error:', e);
            }
        }
    }
});