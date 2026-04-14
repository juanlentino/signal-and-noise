/**
 * Signal & Noise — Accessible tablist for admin widgets.
 *
 * Markup contract (WAI-ARIA 1.2 tabs pattern):
 *   <div class="sn-tabs" role="tablist">
 *     <button type="button" role="tab" aria-selected="true"  aria-controls="p1" id="t1">…</button>
 *     <button type="button" role="tab" aria-selected="false" aria-controls="p2" id="t2" tabindex="-1">…</button>
 *   </div>
 *   <div id="p1" role="tabpanel" aria-labelledby="t1">…</div>
 *   <div id="p2" role="tabpanel" aria-labelledby="t2" hidden>…</div>
 *
 * Click + ArrowLeft/ArrowRight/Home/End keyboard nav.
 */
(function () {
	'use strict';

	function activate(tabs, target) {
		tabs.forEach(function (tab) {
			var selected = tab === target;
			tab.setAttribute('aria-selected', selected ? 'true' : 'false');
			tab.setAttribute('tabindex', selected ? '0' : '-1');
			// Visual feedback — scoped to our tab colors. Kept in JS because the
			// admin widgets don't load a dedicated stylesheet for two properties.
			tab.style.borderBottomColor = selected ? '#e00404' : 'transparent';
			tab.style.color = selected ? '#1d2327' : '#787c82';
			var panelId = tab.getAttribute('aria-controls');
			var panel = panelId ? document.getElementById(panelId) : null;
			if (panel) panel.hidden = !selected;
		});
	}

	function wire(list) {
		if (list.dataset.snTabsReady) return;
		list.dataset.snTabsReady = '1';

		var tabs = Array.prototype.slice.call(list.querySelectorAll('[role="tab"]'));
		tabs.forEach(function (tab, idx) {
			tab.addEventListener('click', function () {
				tab.focus();
				activate(tabs, tab);
			});
			tab.addEventListener('keydown', function (event) {
				var nextIdx = null;
				switch (event.key) {
					case 'ArrowRight': nextIdx = (idx + 1) % tabs.length; break;
					case 'ArrowLeft':  nextIdx = (idx - 1 + tabs.length) % tabs.length; break;
					case 'Home':       nextIdx = 0; break;
					case 'End':        nextIdx = tabs.length - 1; break;
					default: return;
				}
				event.preventDefault();
				tabs[nextIdx].focus();
				activate(tabs, tabs[nextIdx]);
			});
		});
	}

	function init() {
		document.querySelectorAll('.sn-tabs').forEach(wire);
	}

	if (document.readyState !== 'loading') init();
	else document.addEventListener('DOMContentLoaded', init);
})();
