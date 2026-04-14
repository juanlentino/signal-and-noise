/**
 * Signal & Noise — Admin visitor trend chart init.
 *
 * Auto-initialises any `.sn-chart-widget[data-sn-chart]` element with Chart.js.
 * Data shape:
 *   {
 *     "labels":    ["2026-04-01", ...],
 *     "visitors":  [123, 456, ...],
 *     "pageviews": [200, 700, ...]
 *   }
 *
 * Point radius auto-collapses to 0 for dense (>60 points) series to keep the
 * line readable. Waits for Chart.js on 100ms interval.
 */
(function () {
	'use strict';

	function initOne(el) {
		if (el.dataset.snChartReady) return;
		el.dataset.snChartReady = '1';

		var canvas = el.querySelector('canvas');
		if (!canvas) return;

		var payload;
		try { payload = JSON.parse(el.dataset.snChart || '{}'); } catch (e) { return; }

		var labels    = payload.labels || [];
		var visitors  = payload.visitors || [];
		var pageviews = payload.pageviews || [];
		var dense = labels.length > 60;

		new Chart(canvas, {
			type: 'line',
			data: {
				labels: labels,
				datasets: [
					{
						label: 'Visitors',
						data: visitors,
						borderColor: '#e00404',
						backgroundColor: 'rgba(224,4,4,0.05)',
						fill: true,
						tension: 0.3,
						pointRadius: dense ? 0 : 3,
						borderWidth: 2
					},
					{
						label: 'Pageviews',
						data: pageviews,
						borderColor: '#1d2327',
						backgroundColor: 'transparent',
						fill: false,
						tension: 0.3,
						pointRadius: dense ? 0 : 2,
						borderWidth: 1.5,
						borderDash: [4, 4]
					}
				]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				interaction: { mode: 'index', intersect: false },
				plugins: {
					legend: { position: 'bottom', labels: { usePointStyle: true, padding: 16, font: { size: 11 } } }
				},
				scales: {
					x: { display: true, ticks: { maxTicksLimit: 8, font: { size: 10 }, color: '#787c82' }, grid: { display: false } },
					y: { display: true, beginAtZero: true, ticks: { font: { size: 10 }, color: '#787c82' }, grid: { color: 'rgba(0,0,0,0.04)' } }
				}
			}
		});
	}

	function init() {
		if (typeof Chart === 'undefined') {
			return setTimeout(init, 100);
		}
		document.querySelectorAll('.sn-chart-widget[data-sn-chart]').forEach(initOne);
	}

	if (document.readyState === 'complete') init();
	else window.addEventListener('load', init);
})();
