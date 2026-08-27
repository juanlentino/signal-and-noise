/**
 * Signal & Noise — contact alias assembly.
 *
 * Each .sn-email span ships a base64 user (data-eu) + domain (data-ed) and a
 * readable "user [at] domain [dot] tld" fallback as its text. On load we decode
 * the parts and replace that fallback with a CLICKABLE mailto: link, built
 * entirely at runtime via the DOM.
 *
 * Scraper protection is intact: the contiguous user@domain string AND the
 * "mailto:" only ever exist in the live DOM after this script runs — never in
 * the served HTML source. A non-JS bulk harvester (and Cloudflare's edge email
 * scan) sees only the split base64 attributes + the "[at]/[dot]" span, so an
 * email regex over the source gets nothing. Without JS the fallback stays
 * readable (just not clickable). The residual JS-executing scraper is handled
 * downstream by the Proton aliases + Cloudflare obfuscation (kept enabled).
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
			// The decoded parts are server-authored, but validate anyway: the
			// strict shapes stop a DOM-injected .sn-email span from smuggling
			// extra mailto params (cc, body) through a crafted "address".
			if (!/^[A-Za-z0-9._%+-]+$/.test(user) || !/^[A-Za-z0-9.-]+$/.test(domain)) {
				continue; // hostile or mangled parts → keep the inert fallback
			}
			var address = user + '@' + domain;
			// Optional subject (data-esj, base64) — [sn_note_reply] prefills
			// "Re: <note title>"; the five /contact aliases ship none.
			var subject = decode(el.getAttribute('data-esj') || '');
			var link = document.createElement('a');
			link.href = 'mailto:' + encodeURIComponent(user) + '@' + encodeURIComponent(domain)
				+ (subject ? '?subject=' + encodeURIComponent(subject) : '');
			link.textContent = address;
			el.textContent = ''; // clear the [at]/[dot] fallback
			el.appendChild(link); // clickable address — assembled only at runtime
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', assemble);
	} else {
		assemble();
	}
})();
