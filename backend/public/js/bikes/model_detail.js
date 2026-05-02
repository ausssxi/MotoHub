/**
 * MotoHub Model Detail Page Scripts
 * チャート描画のみを担当。統計数値はサーバーサイド(Blade)でレンダリング済み。
 * Chart.jsは遅延読み込みされるため、__loadChartJs経由で初期化する。
 */
function initModelDetail() {
    var stats = window.bikeModelStats || {};
    var history = window.bikeModelHistory || {};

    function drawCharts() {
        // ==========================================
        // 1. 価格分布ヒストグラム (Chart.js)
        // ==========================================
        if (stats.distribution && stats.distribution.length > 0) {
            var chartCanvas = document.getElementById('priceChart');
            if (chartCanvas && typeof Chart !== 'undefined') {
                try {
                    new Chart(chartCanvas.getContext('2d'), {
                        type: 'bar',
                        data: {
                            labels: stats.distribution.map(function(d) { return d.label; }),
                            datasets: [{
                                label: '台数',
                                data: stats.distribution.map(function(d) { return d.count; }),
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
            var historyCanvas = document.getElementById('historyChart');
            if (historyCanvas && typeof Chart !== 'undefined') {
                try {
                    new Chart(historyCanvas.getContext('2d'), {
                        type: 'line',
                        data: {
                            labels: history.labels,
                            datasets: [{
                                label: '平均価格推移 (万円)',
                                data: history.prices,
                                borderColor: 'rgb(234, 88, 12)',
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
    }

    // Chart.jsが遅延読み込みされるので、ロード完了後に描画
    if (typeof window.__loadChartJs === 'function') {
        window.__loadChartJs(drawCharts);
    } else if (typeof Chart !== 'undefined') {
        drawCharts();
    }
}
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initModelDetail);
} else {
    initModelDetail();
}
