document.addEventListener('DOMContentLoaded', () => {
    // 1. 価格分布チャート (Stats)
    const stats = window.bikeModelStats || {};
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

    // 2. 価格推移チャート (History)
    const history = window.bikeModelHistory || {};
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