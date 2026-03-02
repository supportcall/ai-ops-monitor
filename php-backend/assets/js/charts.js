/**
 * AI-NOC — Chart Rendering (requires Chart.js)
 * File: /assets/js/charts.js
 */

(function() {
    'use strict';

    if (typeof Chart === 'undefined') {
        console.warn('Chart.js not loaded — charts disabled');
        return;
    }

    Chart.defaults.color = '#6b7280';
    Chart.defaults.borderColor = '#1e2730';
    Chart.defaults.font.family = "'SF Mono', 'Cascadia Code', monospace";
    Chart.defaults.font.size = 10;

    // Global latency chart (dashboard)
    var globalCanvas = document.getElementById('latencyChart');
    if (globalCanvas && window.latencyData) {
        renderLatencyChart(globalCanvas, window.latencyData, 'Global Avg Latency');
    }

    // Provider latency chart (detail page)
    var providerCanvas = document.getElementById('providerLatencyChart');
    if (providerCanvas && window.providerLatencyData) {
        renderLatencyChart(providerCanvas, window.providerLatencyData, 'Latency');
    }

    function renderLatencyChart(canvas, data, title) {
        if (!data || data.length === 0) {
            canvas.parentElement.innerHTML = '<p style="text-align:center;color:#6b7280;padding:40px;">No latency data yet.</p>';
            return;
        }

        new Chart(canvas.getContext('2d'), {
            type: 'line',
            data: {
                labels: data.map(function(d) { return d.label; }),
                datasets: [
                    {
                        label: 'P50',
                        data: data.map(function(d) { return Math.round(d.p50 || d.avg_ms || 0); }),
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        fill: true,
                        tension: 0.3,
                        pointRadius: 0,
                        borderWidth: 1.5,
                    },
                    {
                        label: 'P95',
                        data: data.map(function(d) { return Math.round(d.p95 || d.p95_ms || 0); }),
                        borderColor: '#f59e0b',
                        backgroundColor: 'rgba(245, 158, 11, 0.05)',
                        fill: true,
                        tension: 0.3,
                        pointRadius: 0,
                        borderWidth: 1.5,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: true, position: 'top', labels: { boxWidth: 12, padding: 15 } },
                    tooltip: {
                        backgroundColor: '#131920',
                        borderColor: '#1e2730',
                        borderWidth: 1,
                        titleColor: '#e2e8f0',
                        bodyColor: '#c5cbd3',
                        callbacks: {
                            label: function(ctx) { return ctx.dataset.label + ': ' + ctx.parsed.y + 'ms'; }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { maxTicksLimit: 12 }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: '#1e273050' },
                        ticks: { callback: function(v) { return v + 'ms'; } }
                    }
                }
            }
        });
    }
})();
