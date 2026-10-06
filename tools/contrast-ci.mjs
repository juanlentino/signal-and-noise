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
	const path = u => new URL(u).pathname;
	const of = kind => maps.find(m => m.includes(`-${kind}-`));
	const pagesList = of('posts-page') ? (await locs(of('posts-page'))).map(path) : [];
	const notes = of('posts-post') ? (await locs(of('posts-post'))).map(path).slice(0, 2) : [];
	const tags = of('taxonomies-post_tag') ? (await locs(of('taxonomies-post_tag'))).map(path).slice(0, 1) : [];
	return [...new Set([...pagesList, ...notes, ...tags, '/notes/', '/verify/'])];
}

const list = await pages().catch(e => { console.log(`::warning::sitemap unreadable (${e.message}); inconclusive`); process.exit(2); });
const tool = await readFile(new URL('./contrast-computed.js', import.meta.url), 'utf8');

const browser = await chromium.launch();
const context = await browser.newContext({ extraHTTPHeaders: headers, viewport: { width: 1440, height: 900 } });
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
console.log(`::notice title=contrast-summary::${JSON.stringify({ pages: out.pages, checked: out.checked, links: out.linksChecked })}`);
const bad = out.violations.length + out.links.length;
console.log(bad ? `${bad} failure(s).` : 'Clean: every text pair at AA, every color-only link at 3:1.');
process.exit(bad ? 1 : 0);
