/**
 * Signal & Noise — Admin visitor map init.
 *
 * Auto-initialises any `.sn-map-widget[data-sn-map]` element with jsvectormap.
 * Data attribute is a JSON object: { "US": 123, "GB": 45, ... } (ISO alpha-2 → visitor count).
 * Waits for jsvectormap to load before running; retries on a 100ms interval.
 */
(function () {
	'use strict';

	function initOne(el) {
		if (el.dataset.snMapReady) return;
		el.dataset.snMapReady = '1';

		var data;
		try { data = JSON.parse(el.dataset.snMap || '{}'); } catch (e) { return; }

		var values = Object.values(data);
		var max = values.length ? Math.max.apply(null, values) : 1;
		if (max < 1) max = 1;

		var colors = {};
		Object.keys(data).forEach(function (code) {
			var pct = data[code] / max;
			colors[code] = 'rgba(224,4,4,' + Math.max(0.15, pct) + ')';
		});

		new jsVectorMap({
			selector: el,
			map: 'world',
			backgroundColor: 'transparent',
			regionStyle: {
				initial: { fill: '#e8e8e8', stroke: '#ffffff', strokeWidth: 0.5 },
				hover:   { fill: '#e00404' }
			},
			series: { regions: [{ values: colors, attribute: 'fill' }] },
			showTooltip: true,
			zoomOnScroll: false,
			zoomButtons: false,
			panOnDrag: false,
			onRegionTooltipShow: function (event, tooltip, code) {
				var v = data[code] || 0;
				tooltip.html(tooltip.html() + (v ? ' — ' + v + ' visitors' : ''));
			}
		});
	}

	function init() {
		if (typeof jsVectorMap === 'undefined') {
			return setTimeout(init, 100);
		}
		document.querySelectorAll('.sn-map-widget[data-sn-map]').forEach(initOne);
	}

	if (document.readyState === 'complete') init();
	else window.addEventListener('load', init);
})();
