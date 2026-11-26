import './bootstrap';

import Alpine from 'alpinejs';
import Chart from 'chart.js/auto';

// ============================================
// 1. ALPINE JS
// ============================================

window.Alpine = Alpine;

// Komponen dialog untuk modal tambah/edit/hapus.
Alpine.data('ngDialog', () => ({
    open: false,

    show() {
        this.open = true;

        this.$nextTick(() => {
            this.$refs.dialog?.focus();
        });
    },

    close() {
        this.open = false;
    },

    onEscape(event) {
        if (event.key === 'Escape') {
            this.close();
        }
    },
}));

Alpine.start();


// ============================================
// 2. CHART.JS DARK THEME
// ============================================

Chart.defaults.color = '#8b949e';

Chart.defaults.borderColor =
    'rgba(139, 148, 158, 0.12)';

Chart.defaults.font.family =
    'Figtree, Inter, sans-serif';

Chart.defaults.font.size = 11;

// Legend
Chart.defaults.plugins.legend.labels.color =
    '#c9d1d9';

Chart.defaults.plugins.legend.labels.usePointStyle = true;

Chart.defaults.plugins.legend.labels.boxWidth = 8;

Chart.defaults.plugins.legend.labels.padding = 18;

// Tooltip
Chart.defaults.plugins.tooltip.backgroundColor =
    '#1c2127';

Chart.defaults.plugins.tooltip.titleColor =
    '#e6edf3';

Chart.defaults.plugins.tooltip.bodyColor =
    '#c9d1d9';

Chart.defaults.plugins.tooltip.borderColor =
    '#30363d';

Chart.defaults.plugins.tooltip.borderWidth = 1;

Chart.defaults.plugins.tooltip.padding = 11;

Chart.defaults.plugins.tooltip.cornerRadius = 8;

// Grid
Chart.defaults.scale.grid.color =
    'rgba(139, 148, 158, 0.11)';

Chart.defaults.scale.ticks.color = '#8b949e';


// ============================================
// 3. TRAFFIC CHART
// ============================================

function initializeTrafficChart() {

    const canvas = document.getElementById('trafficChart');

    // Pastikan canvas tersedia.
    if (!canvas) return;

    // Mencegah grafik dibuat dua kali.
    if (canvas.dataset.initialized === 'true') return;

    let chartData;

    try {
        chartData = JSON.parse(
            canvas.dataset.chart || '{}'
        );
    } catch (error) {
        console.error(
            'NetGuard: Data grafik tidak valid.',
            error
        );

        return;
    }

    // Validasi struktur data dari Laravel.
    if (
        !Array.isArray(chartData.labels) ||
        !Array.isArray(chartData.allow) ||
        !Array.isArray(chartData.deny)
    ) {
        console.error(
            'NetGuard: Struktur data grafik tidak sesuai.'
        );

        return;
    }

    const context = canvas.getContext('2d');

    new Chart(context, {

        type: 'line',

        data: {

            labels: chartData.labels,

            datasets: [

                // ALLOW TRAFFIC
                {
                    label: 'Allow',

                    data: chartData.allow,

                    borderColor: '#34d399',

                    backgroundColor:
                        'rgba(52, 211, 153, 0.07)',

                    borderWidth: 2,

                    pointRadius: 2,

                    pointHoverRadius: 5,

                    pointBackgroundColor: '#34d399',

                    fill: true,

                    tension: 0.32,
                },

                // DENY TRAFFIC
                {
                    label: 'Deny',

                    data: chartData.deny,

                    borderColor: '#fb7185',

                    backgroundColor:
                        'rgba(251, 113, 133, 0.06)',

                    borderWidth: 2,

                    pointRadius: 2,

                    pointHoverRadius: 5,

                    pointBackgroundColor: '#fb7185',

                    fill: true,

                    tension: 0.32,
                }

            ]
        },

        options: {

            responsive: true,

            maintainAspectRatio: false,

            animation: {
                duration: 480
            },

            interaction: {
                mode: 'index',
                intersect: false
            },

            plugins: {

                legend: {
                    position: 'top',
                    align: 'end'
                },

                tooltip: {
                    enabled: true,
                    mode: 'index',
                    intersect: false
                }

            },

            scales: {

                // SUMBU Y
                y: {

                    beginAtZero: true,

                    ticks: {
                        precision: 0,
                        color: '#8b949e'
                    },

                    grid: {
                        color:
                            'rgba(139, 148, 158, 0.11)'
                    },

                    border: {
                        display: false
                    },

                    title: {
                        display: true,
                        text: 'Jumlah Trafik',
                        color: '#8b949e'
                    }

                },

                // SUMBU X
                x: {

                    ticks: {
                        color: '#8b949e'
                    },

                    grid: {
                        display: false
                    },

                    border: {
                        color:
                            'rgba(139, 148, 158, 0.14)'
                    },

                    title: {
                        display: true,
                        text: 'Tanggal',
                        color: '#8b949e'
                    }

                }

            }

        }

    });

    canvas.dataset.initialized = 'true';
}


// ============================================
// 4. SUBMIT LOADING FEEDBACK
// ============================================

function enableSubmitFeedback() {

    document.addEventListener('submit', (event) => {

        const form = event.target;

        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        // Jangan mengganggu form pencarian GET.
        if (form.method.toUpperCase() === 'GET') {
            return;
        }

        // Form tertentu dapat dikecualikan.
        if (form.dataset.skipLoading === 'true') {
            return;
        }

        // Mencegah pengiriman berulang.
        if (form.dataset.ngSubmitting === 'true') {
            event.preventDefault();
            return;
        }

        form.dataset.ngSubmitting = 'true';

        const submitter = event.submitter;

        const buttons = submitter
            ? [submitter]
            : Array.from(
                form.querySelectorAll(
                    'button[type="submit"], button:not([type])'
                )
            );

        buttons.forEach((button) => {

            if (!(button instanceof HTMLButtonElement)) {
                return;
            }

            if (button.disabled) {
                return;
            }

            button.classList.add('is-loading');

            button.setAttribute('aria-busy', 'true');

            button.setAttribute('aria-disabled', 'true');

            button.style.pointerEvents = 'none';

        });

    });

}


// ============================================
// 5. INITIALIZE APPLICATION
// ============================================

function initializeNetGuard() {

    initializeTrafficChart();

    enableSubmitFeedback();

    console.log(
        'NetGuard UI initialized successfully.'
    );

}


// Tunggu DOM selesai dimuat.
if (document.readyState === 'loading') {

    document.addEventListener(
        'DOMContentLoaded',
        initializeNetGuard,
        { once: true }
    );

} else {

    initializeNetGuard();

}