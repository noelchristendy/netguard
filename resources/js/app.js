import './bootstrap';

import Alpine from 'alpinejs';
import Chart from 'chart.js/auto';

window.Alpine = Alpine;

Alpine.start();

function initializeTrafficChart() {
    const canvas = document.getElementById('trafficChart');

    if (!canvas) {
        return;
    }

    if (canvas.dataset.initialized === 'true') {
        return;
    }

    let chartData;

    try {
        chartData = JSON.parse(canvas.dataset.chart || '{}');
    } catch (error) {
        console.error('Data grafik tidak valid:', error);
        return;
    }

    if (
        !Array.isArray(chartData.labels) ||
        !Array.isArray(chartData.allow) ||
        !Array.isArray(chartData.deny)
    ) {
        console.error('Struktur data grafik tidak sesuai.');
        return;
    }

    new Chart(canvas, {
        type: 'line',

        data: {
            labels: chartData.labels,

            datasets: [
                {
                    label: 'Allow',
                    data: chartData.allow,
                    borderColor: '#16a34a',
                    backgroundColor: 'rgba(22, 163, 74, 0.12)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.3,
                },
                {
                    label: 'Deny',
                    data: chartData.deny,
                    borderColor: '#dc2626',
                    backgroundColor: 'rgba(220, 38, 38, 0.08)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.3,
                },
            ],
        },

        options: {
            responsive: true,
            maintainAspectRatio: false,

            interaction: {
                mode: 'index',
                intersect: false,
            },

            plugins: {
                legend: {
                    position: 'top',
                },

                tooltip: {
                    enabled: true,
                },
            },

            scales: {
                y: {
                    beginAtZero: true,

                    ticks: {
                        precision: 0,
                    },

                    title: {
                        display: true,
                        text: 'Jumlah Trafik',
                    },
                },

                x: {
                    title: {
                        display: true,
                        text: 'Tanggal',
                    },
                },
            },
        },
    });

    canvas.dataset.initialized = 'true';
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeTrafficChart);
} else {
    initializeTrafficChart();
}
