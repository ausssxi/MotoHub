document.addEventListener('DOMContentLoaded', () => {
    // グローバル変数からデータを取得（Blade側で定義）
    const stats = window.bikeModelStats || {};

    if (!stats.distribution || stats.distribution.length === 0) return;

    const chartCanvas = document.getElementById('priceChart');
    if (!chartCanvas) return;

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
                plugins: {
                    legend: { display: false }
                },
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
        console.error('Chart drawing error:', e);
    }
});