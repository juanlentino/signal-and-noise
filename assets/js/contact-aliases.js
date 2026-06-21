/**
 * Signal & Noise — contact alias assembly.
 *
 * Each .sn-email span ships a base64 user (data-eu) + domain (data-ed) and a
 * readable "user [at] domain [dot] tld" fallback as its text. On load we decode
 * the parts and write the clean address as PLAIN TEXT — never a clickable link
 * (the no-link design on /contact is intentional). The contiguous user@domain
 * string only ever exists at runtime, after this script runs; it is never in
 * the page source. Without JS the [at]/[dot] fallback stays readable.
 *
 * Enqueued only on /contact (inc/contact-email.php), footer + deferred.
 */
(function () {
	'use strict';

	function decode(value) {
		try {
			return window.atob(value);
		} catch (e) {
			return '';
		}
	}

	function assemble() {
		var nodes = document.querySelectorAll('.sn-email');
		for (var i = 0; i < nodes.length; i++) {
			var el = nodes[i];
			var user = decode(el.getAttribute('data-eu') || '');
			var domain = decode(el.getAttribute('data-ed') || '');
			if (!user || !domain) {
				continue; // malformed → keep the [at]/[dot] fallback in place
			}
			el.textContent = user + '@' + domain;
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', assemble);
	} else {
		assemble();
	}
})();
