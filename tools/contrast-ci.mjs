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
	const of = kind => maps.find(m => m.includes(`-${kind}-`));
	const pagesList = of('posts-page') ? (await locs(of('posts-page'))).map(path) : [];
	// The posts sitemap lists oldest first: sample the two NEWEST notes (Codex on #508).
	const notes = of('posts-post') ? (await locs(of('posts-post'))).map(path).slice(-2) : [];
	const tags = of('taxonomies-post_tag') ? (await locs(of('taxonomies-post_tag'))).map(path).slice(0, 1) : [];
	if (!pagesList.length) throw new Error('page sitemap carried no locations');
	return [...new Set([...pagesList, ...notes, ...tags, '/notes/', '/verify/'])];
}

const list = await pages().catch(e => { console.log(`::warning::sitemap unreadable (${e.message}); inconclusive`); process.exit(2); });
const tool = await readFile(new URL('./contrast-computed.js', import.meta.url), 'utf8');

const browser = await chromium.launch();
// The allow-list token goes to the site's own origin only, never to a third
// party the page loads (fonts, embeds, analytics): headers are added per
// request by origin, not context-wide (Codex on #508, P1).
const context = await browser.newContext({ userAgent: headers['User-Agent'], viewport: { width: 1440, height: 900 } });
await context.route('**/*', route => {
	const req = route.request();
	if (TOKEN && new URL(req.url()).origin === new URL(ORIGIN).origin) {
		return route.continue({ headers: { ...req.headers(), 'x-sn-smoke': TOKEN } });
	}
	return route.continue();
});
const page = await context.newPage();
const first = await page.goto(`${ORIGIN}/`, { waitUntil: 'load', timeout: 60000 });
if (!first || first.status() !== 200) {
	console.log(`::warning::home answered ${first ? first.status() : 'nothing'} to this runner (edge block?); inconclusive`);
	await browser.close();
	process.exit(2);
}
await page.evaluate(p => { window.__snContrastPages = p; }, list);
await page.addScriptTag({ content: tool });
const out = await page.evaluate(() => window.__snContrastDone, null, { timeout: 0 });
await browser.close();

console.log(`${out.checked} text pairs and ${out.linksChecked} links checked on ${out.pages} pages, light and dark; ${out.imaged} over images skipped.`);
if (out.unloaded.length) console.log(`::warning::${out.unloaded.length} page(s) rendered no <main> (blocked or broken): ${out.unloaded.join(', ')}`);
if (out.unloaded.length === out.pages) process.exit(2);

for (const v of out.violations) console.log(`::error::${v.page} (${v.palette}) ${v.sel} "${v.text}": ${v.ratio}:1, needs ${v.need}:1 (${v.size}px, opacity ${v.opacity})`);
for (const l of out.links) console.log(`::error::${l.page} (${l.palette}) link "${l.text}" in ${l.parent}: ${l.ratio}:1 against its text, no underline (needs 3:1, WCAG 1.4.1)`);
// One machine-readable line for the plugin's Health report (inc/health-contrast-rendered.php).
// failures is the uncapped total: GitHub keeps only ten error annotations a step.
console.log(`::notice title=contrast-summary::${JSON.stringify({ pages: out.pages, checked: out.checked, links: out.linksChecked, failures: out.violations.length + out.links.length })}`);
const bad = out.violations.length + out.links.length;
console.log(bad ? `${bad} failure(s).` : 'Clean: every text pair at AA, every color-only link at 3:1.');
process.exit(bad ? 1 : 0);
