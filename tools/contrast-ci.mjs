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
 * runner, or no page loaded), reported as a warning by the workflow, never as
 * a pass.
 *
 * Usage: node tools/contrast-ci.mjs [origin]   (needs `playwright` installed)
 */
import { readFile } from 'node:fs/promises';
import { chromium } from 'playwright';

const ORIGIN = process.argv[2] || 'https://juanlentino.com';
const TOKEN = process.env.SN_SMOKE_TOKEN || '';
const headers = { 'User-Agent': 'SignalNoise-ContrastCI/1.0 (GitHub Actions)', ...(TOKEN ? { 'X-SN-Smoke': TOKEN } : {}) };

async function locs(url) {
	const res = await fetch(url, { headers });
	if (!res.ok) throw new Error(`${url} answered ${res.status}`);
	return [...(await res.text()).matchAll(/<loc>([^<]+)<\/loc>/g)].map(m => m[1]);
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
		// The allow-list token goes to the site's own origin only, and only to
		// the hop it was sent on: a same-origin request is fetched with
		// redirects off, so a redirect returns to the browser and its next hop
		// is routed (and judged by origin) afresh. route.continue({ headers })
		// would carry the token through every redirect, cross-origin included
		// (Codex on #508, P1, both rounds).
		if (TOKEN) {
			const own = new URL(ORIGIN).origin;
			await context.route('**/*', async route => {
				const req = route.request();
				if (new URL(req.url()).origin !== own) return route.continue();
				try {
					const response = await route.fetch({ headers: { ...req.headers(), 'x-sn-smoke': TOKEN }, maxRedirects: 0 });
					return await route.fulfill({ response });
				} catch {
					// A request still in flight when the run closes the context.
				}
			});
		}
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
		await page.addScriptTag({ content: tool });
		const out = await page.evaluate(() => window.__snContrastDone, null, { timeout: 0 });

		console.log(`${out.checked} text pairs and ${out.linksChecked} links checked on ${out.pages} pages, light and dark; ${out.imaged} over images skipped.`);
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
