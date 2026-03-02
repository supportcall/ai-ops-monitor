/**
 * AI-NOC — Main JavaScript
 * File: /assets/js/app.js
 */

(function() {
    'use strict';

    // Live clock
    function updateClock() {
        var el = document.getElementById('clock');
        if (!el) return;
        var now = new Date();
        el.textContent = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' })
            + ' ' + Intl.DateTimeFormat().resolvedOptions().timeZone;
    }
    updateClock();
    setInterval(updateClock, 1000);

    // Last refresh
    var refreshEl = document.getElementById('last-refresh');
    if (refreshEl) {
        refreshEl.textContent = new Date().toLocaleTimeString();
    }

    // Tooltip on heatmap cells
    document.querySelectorAll('.heatmap-cell').forEach(function(cell) {
        cell.style.cursor = 'pointer';
        cell.addEventListener('mouseenter', function(e) {
            var tooltip = document.createElement('div');
            tooltip.className = 'heatmap-tooltip';
            tooltip.textContent = cell.title;
            tooltip.style.cssText = 'position:fixed;background:#131920;border:1px solid #1e2730;color:#e2e8f0;padding:4px 8px;border-radius:4px;font-size:11px;font-family:monospace;pointer-events:none;z-index:100;';
            tooltip.style.left = e.clientX + 10 + 'px';
            tooltip.style.top = e.clientY - 30 + 'px';
            document.body.appendChild(tooltip);
            cell._tooltip = tooltip;
        });
        cell.addEventListener('mouseleave', function() {
            if (cell._tooltip) {
                cell._tooltip.remove();
                cell._tooltip = null;
            }
        });
    });
})();
