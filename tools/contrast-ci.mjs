/**
 * Runs tools/contrast-computed.js headless against the live site and fails on
 * any text under AA or any link marked by color alone under 3:1.
 *
 * Why live, and why after a deploy rather than on a pull request: the
 * instrument measures the RENDERED cascade (nesting, opacity, inline styles),
 * which only exists on the served site, plugin and content included. A PR
 * preview of the theme alone would measure a site nobody visits.
 *
 * Pages: every page in the sitemap, the two newest notes and one tag archive
 * (notes and tags share a template each; one sample per template is the
 * coverage, not the count).
 *
 * Exit codes: 0 clean; 1 violations; 2 inconclusive (the edge blocked this
 * runner, a routed asset failed or redirected across directories, or no
 * page loaded), reported as a warning by the workflow, never as
 * a pass.
 *
 * The run's own analytics beacon is blocked for every page and frame
 * (tools/contrast-route.mjs), so measuring the site never counts as visiting it.
 *
 * Usage: node tools/contrast-ci.mjs [origin]   (needs `playwright` installed)
 */
import { readFile } from 'node:fs/promises';
import { chromium } from 'playwright';
import { installRoute } from './contrast-route.mjs';

const ORIGIN = process.argv[2] || 'https://juanlentino.com';
const TOKEN = process.env.SN_SMOKE_TOKEN || '';
// x-sn-first-party: the header and secret both analytics-side Workers check
// to file a request as this site's own tooling. Optional; unset, it is not sent.
const FIRST_PARTY = process.env.SN_FIRST_PARTY_TOKEN || '';
const ownHeaders = { ...(TOKEN ? { 'x-sn-smoke': TOKEN } : {}), ...(FIRST_PARTY ? { 'x-sn-first-party': FIRST_PARTY } : {}) };
const headers = { 'User-Agent': 'SignalNoise-ContrastCI/1.0 (GitHub Actions)', ...ownHeaders };

// Sitemaps are read from the site's own origin only, one redirect hop at a
// time, and the token rides only a same-origin hop: a sitemap index naming
// another host, or a redirect off-site, is refused before anything is sent
// (Codex on #508, round 4, P1).
async function locs(url) {
	const own = new URL(ORIGIN).origin;
	for (let hop = 0; hop < 5; hop++) {
		if (new URL(url).origin !== own) throw new Error(`refusing off-origin sitemap ${url}`);
		const res = await fetch(url, { headers, redirect: 'manual' });
		if (res.status >= 300 && res.status < 400 && res.headers.get('location')) {
			url = new URL(res.headers.get('location'), url).href;
			continue;
		}
		if (!res.ok) throw new Error(`${url} answered ${res.status}`);
		return [...(await res.text()).matchAll(/<loc>([^<]+)<\/loc>/g)].map(m => m[1]);
	}
	throw new Error(`too many redirects reading ${url}`);
}

async function pages() {
	const maps = await locs(`${ORIGIN}/wp-sitemap.xml`);
	// An edge block can answer 200 with an HTML page: no <loc> at all. That is
	// no sitemap, not an empty site; never let it shrink the run to two pages
	// and call that clean (Codex on #508).
	if (!maps.length) throw new Error('sitemap index carried no locations');
	const path = u => new URL(u).pathname;
	// Every partition of a kind, in order: WordPress splits a long sitemap into
	// -1, -2, …, and reading only the first missed pages and picked old notes
	// as "newest" (Codex on #508, round 2).
	const all = async kind => (await Promise.all(maps.filter(m => m.includes(`-${kind}-`)).map(locs))).flat().map(path);
	const pagesList = await all('posts-page');
	// The posts sitemaps list oldest first: sample the two NEWEST notes.
	const notes = (await all('posts-post')).slice(-2);
	const tags = (await all('taxonomies-post_tag')).slice(0, 1);
	if (!pagesList.length) throw new Error('page sitemap carried no locations');
	// Each sampled kind the index lists must yield its sample: an empty or
	// HTML-bodied child sitemap is no sample, not a smaller run (Codex on #508, round 4).
	if (maps.some(m => m.includes('-posts-post-')) && !notes.length) throw new Error('notes sitemap carried no locations');
	if (maps.some(m => m.includes('-taxonomies-post_tag-')) && !tags.length) throw new Error('tags sitemap carried no locations');
	return [...new Set([...pagesList, ...notes, ...tags, '/notes/', '/verify/'])];
}

async function main() {
	let list;
	try { list = await pages(); } catch (e) { console.log(`::warning::sitemap unreadable (${e.message}); inconclusive`); return 2; }
	const tool = await readFile(new URL('./contrast-computed.js', import.meta.url), 'utf8');

	const browser = await chromium.launch();
	let context;
	try {
		context = await browser.newContext({ userAgent: headers['User-Agent'], viewport: { width: 1440, height: 900 } });
		// The route (tools/contrast-route.mjs) blocks the analytics beacon,
		// sends the tokens on same-origin hops only, and records any asset
		// failure that changes what is measured.
		const routeIssues = [];
		let measured = false;
		const routed = await installRoute(context, { origin: ORIGIN, headers: ownHeaders, routeIssues, measured: () => measured });
		const page = await context.newPage();
		let first;
		try {
			first = await page.goto(`${ORIGIN}/`, { waitUntil: 'load', timeout: 60000 });
		} catch (e) {
			// DNS, TLS, a reset or a timeout: no page loaded, so no verdict (Codex on #508).
			console.log(`::warning::home did not load (${e.message.split('\n')[0]}); inconclusive`);
			return 2;
		}
		if (!first || first.status() !== 200) {
			console.log(`::warning::home answered ${first ? first.status() : 'nothing'} to this runner (edge block?); inconclusive`);
			return 2;
		}
		await page.evaluate(p => { window.__snContrastPages = p; }, list);
		// Evaluated, not injected: the site's script-src allows only listed
		// hashes, so an inline <script> tag was blocked and every run from
		// 2026-10-18 died before measuring. Evaluation is not subject to
		// script-src, and the measured pages keep the CSP readers get.
		await page.evaluate(tool);
		const out = await page.evaluate(() => window.__snContrastDone, null, { timeout: 0 });
		measured = true;

		console.log(`${out.checked} text pairs and ${out.linksChecked} links checked on ${out.pages} pages, light and dark; ${out.imaged} over images skipped.`);
		// Every measured page loads the beacon, so a run that blocked none has
		// lost the match (the endpoint moved) and is being counted as traffic.
		// That is not a measurement problem, so it warns and never changes the verdict.
		console.log(`${routed.beacons} analytics beacon request(s) blocked.`);
		if (!routed.beacons) console.log('::warning::no analytics beacon was blocked: either the beacon endpoint moved and this run is being recorded as site traffic (tools/contrast-route.mjs BEACON_PATH), or the browser sent Do Not Track or GPC, which stops the beacon on its own.');
		if (out.unloaded.length) console.log(`::warning::${out.unloaded.length} page(s) rendered no <main> (blocked or broken): ${out.unloaded.join(', ')}`);

		for (const v of out.violations) console.log(`::error::${v.page} (${v.palette}) ${v.sel} "${v.text}": ${v.ratio}:1, needs ${v.need}:1 (${v.size}px, opacity ${v.opacity})`);
		for (const l of out.links) console.log(`::error::${l.page} (${l.palette}) link "${l.text}" in ${l.parent}: ${l.ratio}:1 against its text, no underline (needs 3:1, WCAG 1.4.1)`);
		// One machine-readable line for the plugin's Health report (inc/health-contrast-rendered.php).
		// failures is the uncapped total: GitHub keeps only ten error annotations a step.
		const bad = out.violations.length + out.links.length;
		console.log(`::notice title=contrast-summary::${JSON.stringify({ pages: out.pages, checked: out.checked, links: out.linksChecked, failures: bad })}`);
		if (bad) { console.log(`${bad} failure(s).`); return 1; }
		// A page that did not render was not measured: never call the run clean
		// (Codex on #508). Failures found elsewhere still go red above.
		if (out.unloaded.length) { console.log('Inconclusive: not every page rendered.'); return 2; }
		if (routeIssues.length) {
			console.log(`::warning::${routeIssues.length} routed request(s) changed what was measured: ${routeIssues.slice(0, 5).join('; ')}`);
			console.log('Inconclusive: an asset failed or redirected.');
			return 2;
		}
		console.log('Clean: every text pair at AA, every color-only link at 3:1.');
		return 0;
	} finally {
		// Settle in-flight routed requests before closing, or one rejects after
		// the context is gone and crashes a run that already measured clean.
		if (context) await context.unrouteAll({ behavior: 'ignoreErrors' });
		await browser.close();
	}
}

// exitCode, never process.exit(): exit can drop buffered stdout, the summary
// notice included (Codex on #508, round 2).
process.exitCode = await main();
