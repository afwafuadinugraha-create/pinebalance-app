let myLineChart = null;
let myPieChart = null;

// 1. Line Chart Tren Water Balance
function initLineChart(labels, dataSoil, fc, mad, wp) {
    const canvas = document.getElementById('waterBalanceChart');
    if (!canvas) return;

    const ctx = canvas.getContext('2d');
    if (myLineChart) myLineChart.destroy();

    myLineChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Water Balance (mm)',
                    data: dataSoil,
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37, 99, 235, 0.08)',
                    fill: true,
                    tension: 0.25,
                    borderWidth: 2.5,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    pointBackgroundColor: '#2563eb'
                },
                {
                    label: 'FC (105)',
                    data: Array(labels.length).fill(fc),
                    borderColor: '#10b981',
                    borderDash: [4, 4],
                    borderWidth: 1.8,
                    pointRadius: 0
                },
                {
                    label: 'MAD 50% (80)',
                    data: Array(labels.length).fill(mad),
                    borderColor: '#f59e0b',
                    borderDash: [4, 4],
                    borderWidth: 1.8,
                    pointRadius: 0
                },
                {
                    label: 'WP (54)',
                    data: Array(labels.length).fill(wp),
                    borderColor: '#ef4444',
                    borderDash: [4, 4],
                    borderWidth: 1.8,
                    pointRadius: 0
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    min: 0,
                    max: 120,
                    grid: { color: '#f1f5f9' },
                    ticks: { color: '#64748b', font: { family: 'Plus Jakarta Sans', size: 11, weight: '500' } }
                },
                x: {
                    grid: { display: false },
                    ticks: { color: '#64748b', font: { family: 'Plus Jakarta Sans', size: 10, weight: '500' } }
                }
            },
            plugins: {
                legend: {
                    position: 'top',
                    labels: { color: '#1e293b', usePointStyle: true, boxWidth: 8, font: { family: 'Plus Jakarta Sans', size: 12, weight: '600' } }
                },
                tooltip: {
                    backgroundColor: '#ffffff',
                    titleColor: '#0f172a',
                    bodyColor: '#2563eb',
                    borderColor: '#e2e8f0',
                    borderWidth: 1,
                    padding: 10,
                    cornerRadius: 8,
                    titleFont: { weight: '800' },
                    bodyFont: { weight: '700' }
                }
            }
        }
    });
}

// 2. Pie Chart Persentase Status Zone
function initPieChart(counts) {
    const canvas = document.getElementById('statusPieChart');
    if (!canvas) return;

    const ctx = canvas.getContext('2d');
    if (myPieChart) myPieChart.destroy();

    const total = counts.atFc + counts.fcMad + counts.madWp + counts.atWp;
    if (total === 0) return;

    myPieChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['At FC', 'FC - MAD 50%', 'MAD 50% - WP', 'At WP'],
            datasets: [{
                data: [counts.atFc, counts.fcMad, counts.madWp, counts.atWp],
                backgroundColor: ['#10b981', '#3b82f6', '#f59e0b', '#ef4444'],
                borderWidth: 3,
                borderColor: '#ffffff',
                hoverOffset: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '68%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { color: '#1e293b', usePointStyle: true, boxWidth: 8, padding: 12, font: { family: 'Plus Jakarta Sans', size: 11, weight: '600' } }
                },
                tooltip: {
                    backgroundColor: '#0f172a',
                    titleColor: '#ffffff',
                    bodyColor: '#cbd5e1',
                    padding: 10,
                    cornerRadius: 8,
                    callbacks: {
                        label: function(context) {
                            const val = context.raw || 0;
                            const pct = ((val / total) * 100).toFixed(1);
                            return ` ${context.label}: ${val} Hari (${pct}%)`;
                        }
                    }
                }
            }
        }
    });
}