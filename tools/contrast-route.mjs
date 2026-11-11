/**
 * The request route tools/contrast-ci.mjs installs on its browser context,
 * kept in its own module so tests/js/contrast-route.test.cjs can drive it
 * without a browser.
 *
 * It does three things to every request the measured pages make:
 *
 * 1. The site's analytics beacon (inc/beacon.php posts to BEACON_PATH) is
 *    aborted before anything leaves the runner. The run loads every sitemap
 *    page as a real browser, so without this each push recorded 20 to 29
 *    views from GitHub's runners as site traffic. The beacon writes nothing
 *    to the page, so blocking it changes nothing that is measured, and a
 *    blocked beacon is never a route issue.
 * 2. Same-origin requests are fetched here with the runner's own headers (the
 *    smoke token, the first-party token), one redirect hop at a time.
 * 3. A routed asset that failed, or one that redirected in a way that changes
 *    what is measured, is recorded in routeIssues.
 */

export const BEACON_PATH = '/_sn/px';

/**
 * Registers the route on a Playwright browser context, so it covers every
 * page and frame the run opens.
 *
 * @param {object}   context     A Playwright BrowserContext (anything with route()).
 * @param {object}   opts
 * @param {string}   opts.origin      The measured site's origin.
 * @param {object}   [opts.headers]   Header name => value, sent on same-origin hops only.
 * @param {string[]} opts.routeIssues Receives what changed the measurement.
 * @param {Function} opts.measured    Returns true once measurement has ended.
 * @returns {Promise<{beacons: number}>} Counts kept for the run summary.
 */
export async function installRoute(context, { origin, headers = {}, routeIssues, measured }) {
	const own = new URL(origin).origin;
	const stats = { beacons: 0 };
	// The tokens go to the site's own origin only, and only to the hop they
	// were sent on: a same-origin request is fetched with redirects off, so a
	// redirect returns to the browser and its next hop is routed (and judged
	// by origin) afresh. route.continue({ headers }) would carry them through
	// every redirect, cross-origin included (Codex on #508, P1, both rounds).
	// A routed asset that failed, or a subresource that redirected into
	// another directory (its relative url()s would resolve from the old
	// path), changes what is measured: such a run is never called clean
	// (Codex on #517, round 2). Only failures before measurement ends count.
	// The route is installed with or without the tokens: it is also what
	// records asset failures, and a tokenless run (local, console) must not
	// lose that (Codex on #517, #518). Only the headers depend on the tokens.
	// Fulfilling with a 3xx gives the handler no second look at the next hop,
	// so it would leave without the token and meet the WAF (Codex on #508).
	// Same-origin redirects are followed here, one hop at a time, up to five;
	// an off-origin one is handed back as the 3xx, and the browser's next
	// request for it is routed to continue() tokenless.
	// ponytail: the final body is fulfilled under the requested URL, so a
	// redirected document keeps its pre-redirect address; the sitemap lists
	// canonical URLs, so the pages this run loads do not redirect.
	await context.route('**/*', async route => {
		const req = route.request();
		const target = new URL(req.url());
		if (target.origin !== own) return route.continue();
		// Before anything is fetched, and never counted as an issue: the beacon
		// is the run's own traffic, not part of the page it measures.
		if (target.pathname === BEACON_PATH) {
			stats.beacons++;
			return route.abort('blockedbyclient').catch(() => {});
		}
		// What a measured page is built from: CSS, fonts, images, and scripts
		// that write DOM (contact-aliases.js builds the contact links). A
		// failure in any of them changes what is scanned (Codex on #517).
		const critical = ['document', 'stylesheet', 'font', 'image', 'script'].includes(req.resourceType());
		try {
			let url = req.url(), method = req.method(), postData = req.postDataBuffer() || undefined, response;
			let hdrs = { ...req.headers(), ...headers };
			let settled = false;
			for (let hop = 0; hop < 5; hop++) {
				response = await route.fetch({ url, method, headers: hdrs, postData, maxRedirects: 0 });
				const st = response.status(), loc = st >= 300 && st < 400 && response.headers()['location'];
				if (!loc) { settled = true; break; }
				const next = new URL(loc, url);
				if (next.origin !== own) { settled = true; break; }
				url = next.href;
				// A browser's redirect rules: 303 turns anything but HEAD into
				// GET, 301/302 turn POST into GET; the body and its headers go
				// (Codex on #517). 307/308 keep both.
				if ((st === 303 && method !== 'HEAD') || ((st === 301 || st === 302) && method === 'POST')) {
					method = 'GET'; postData = undefined;
					hdrs = Object.fromEntries(Object.entries(hdrs).filter(([k]) => !/^content-(type|length|encoding|language|location)$/i.test(k)));
				}
			}
			// Five same-origin hops and still redirecting: the chain did not
			// resolve, so what reached the page is not the asset (Codex on #517).
			if (!settled && !measured() && critical) routeIssues.push(`${req.resourceType()} redirected more than five times: ${req.url()}`);
			// An HTTP error is a completed request, not a thrown one: a 404
			// stylesheet or a 500 image never reached the catch (Codex on #517).
			// Documents are judged by the page's own render (unloaded) instead.
			if (!measured() && critical && req.resourceType() !== 'document' && response.status() >= 400) routeIssues.push(`${req.resourceType()} answered ${response.status()}: ${req.url()}`);
			const dir = u => new URL(u).pathname.replace(/[^/]*$/, '');
			// Only bodies that resolve relative URLs can be broken by a moved
			// base: stylesheets and scripts. A font or a raster image is the
			// same bytes wherever it came from (Codex on #517, #518).
			if (url !== req.url() && ['stylesheet', 'script'].includes(req.resourceType()) && dir(url) !== dir(req.url())) routeIssues.push(`${req.resourceType()} redirected across directories: ${req.url()}`);
			return await route.fulfill({ response });
		} catch {
			if (!measured() && critical) routeIssues.push(`${req.resourceType()} failed: ${req.url()}`);
			// A fetch that timed out or failed, or one still in flight when the
			// run closes the context: settle the route, or the request stalls
			// the page until the run times out (Codex on #508).
			await route.abort().catch(() => {});
		}
	});
	return stats;
}
