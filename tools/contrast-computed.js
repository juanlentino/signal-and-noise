/**
 * Contrast from COMPUTED styles — the remaining half of the token-level
 * audit, and the half a file cannot answer.
 *
 * Board row (Accessibility, planned): "the CI sweeps read stylesheets, so
 * an inline override, or text nested inside a surface nobody declared, can
 * still hide from them."
 *
 * tests/front-end-css-contrast.php owns the SOURCE half: every declared
 * ink/surface pair, all three palettes, read from the files this repo owns.
 * Its header lists what it cannot see — nesting (an HTML fact), inline
 * overrides, opacity, background images. THIS instrument is that list,
 * measured: the rendered cascade, real nesting, real opacity, on the live
 * site.
 *
 * AXES. Light and dark are toggled per page via data-theme on the iframe's
 * root — both measured in one run. The high-contrast VARIATION is
 * owner-activated (one variation serves live), so that axis stays with the
 * source test; this instrument measures the variation actually served.
 *
 * PATTERN: tools/typography-baseline.js — same-origin iframes for real
 * layout, run from the site's own origin (console or browser pane).
 * Compare runs the same way: capture, fix, capture again.
 *
 * WHAT THIS ONE CANNOT SEE: text over background-image is recorded and
 * skipped (ratio against an image is not a number); generated content
 * (::before/::after) and canvas/SVG text are not walked; single-glyph
 * text nodes (an em-dash placeholder) are below the walker's floor;
 * pages behind auth are out of scope. Text inside aria-hidden subtrees
 * is SKIPPED on purpose: decorative-by-declaration is outside WCAG
 * contrast scope (the /verify ghost numerals are the canonical case).
 *
 * FIRST-RUN LESSON, kept for the next reader: measure the SETTLED page.
 * Entrance animations sit paused at from{opacity:.01} in offscreen
 * iframes, the opacity chain multiplies to ~0, and every composite
 * collapses fg into bg — run one reported 451 false violations that were
 * all exactly fg===bg. The injected animation/transition kill below is
 * not an optimization; it is the difference between measuring the page
 * and measuring its opening frame.
 *
 * Usage: paste the IIFE; await window.__snContrastDone; read
 * window.__snContrast. Violations sorted worst-first.
 *
 * @since theme v12.8.0
 */
(() => {
	// CI (tools/contrast-ci.mjs) passes its own list, the sitemap's pages plus
	// sample notes and a tag archive; the console default stays this one.
	const PAGES = window.__snContrastPages || ['/', '/notes/', '/about/', '/music/', '/services/', '/contact/',
		'/now/', '/resume/', '/stats/', '/colophon/', '/verify/', '/provenance/',
		'/accessibility/', '/maturity/', '/maturity/roadmap/'];
	const lum = ([r, g, b]) => {
		const f = c => { c /= 255; return c <= 0.04045 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4; };
		return 0.2126 * f(r) + 0.7152 * f(g) + 0.0722 * f(b);
	};
	const ratio = (a, b) => { const [x, y] = [lum(a), lum(b)].sort((p, q) => q - p); return (x + 0.05) / (y + 0.05); };
	// rgb()/rgba(), and color(srgb r g b / a): what a color-mix() computes to.
	// Unparsed, a visible color-mix rule read as no border at all (the notes'
	// 55% subscribe rule, 2026-10-06).
	const parse = s => {
		let m = s.match(/rgba?\(([\d.]+)[,\s]+([\d.]+)[,\s]+([\d.]+)(?:[,\s/]+([\d.]+))?/);
		if (m) return { c: [+m[1], +m[2], +m[3]], a: m[4] === undefined ? 1 : +m[4] };
		m = s.match(/color\(srgb\s+([\d.]+)\s+([\d.]+)\s+([\d.]+)(?:\s*\/\s*([\d.]+))?\)/);
		return m ? { c: [m[1] * 255, m[2] * 255, m[3] * 255].map(Math.round), a: m[4] === undefined ? 1 : +m[4] } : null;
	};
	const over = (top, alpha, under) => under.map((u, i) => Math.round(top.c[i] * alpha + u * (1 - alpha)));

	// LINKS (2026-10-06). A link marked by color alone must differ from the
	// text around it by 3:1 (WCAG 1.4.1). The theme drops underlines, so a
	// link in rust text, or one that takes its text's own color (an inverted
	// band), read as plain text until hovered. Checked: a visible link with
	// text, inside running text (a parent with words of its own), carrying no
	// underline, bottom border or own background.
	function measureLinks(doc, page, palette, out) {
		const win = doc.defaultView;
		for (const a of doc.querySelectorAll('a[href]')) {
			const t = a.textContent.trim(); if (!t) continue;
			const cs = win.getComputedStyle(a);
			if (cs.display === 'none' || cs.visibility === 'hidden') continue;
			const r = a.getBoundingClientRect(); if (r.width < 1 || r.height < 1) continue;
			if (a.closest('[aria-hidden="true"]')) continue;
			if (cs.textDecorationLine.includes('underline')) continue;
			// A bottom border marks the link only if it is visible at rest: a
			// transparent one reserved for hover is no cue (Codex on #508).
			const bb = parse(cs.borderBottomColor);
			if (parseFloat(cs.borderBottomWidth) > 0 && cs.borderBottomStyle !== 'none' && bb && bb.a > 0) continue;
			const own = parse(cs.backgroundColor); if (own && own.a > 0) continue;
			// The prose a link sits in may be an ancestor or two up: a CMS wraps
			// links in spans and ems. Walk inline ancestors to the first with
			// words of its own; stop at a block (Codex on #508, round 2).
			const words = el => [...el.childNodes].some(c => c.nodeType === 3 && c.textContent.trim().length > 2);
			let par = a.parentElement;
			while (par && !words(par) && win.getComputedStyle(par).display === 'inline') par = par.parentElement;
			if (!par || !words(par)) continue;
			// The color that paints the link's glyphs: a styled child (<a><span>)
			// can override the anchor's own (Codex on #508, round 4).
			const tw = doc.createTreeWalker(a, NodeFilter.SHOW_TEXT);
			let tn, paint = a;
			while ((tn = tw.nextNode())) { if (tn.textContent.trim()) { paint = tn.parentElement; break; } }
			const lc = parse(win.getComputedStyle(paint).color), pc = parse(win.getComputedStyle(par).color);
			if (!lc || !pc) continue;
			// Compare what is ON SCREEN: each color with its own alpha and its
			// opacity chain, composited over the ground under the text. Raw RGB
			// can pass 3:1 where faded colors have converged (Codex on #508).
			const chain = el => { let o = 1; for (let x = el; x && x !== doc.documentElement; x = x.parentElement) o *= parseFloat(win.getComputedStyle(x).opacity); return o; };
			const ground = (() => {
				for (let x = par; x; x = x.parentElement) {
					const b = parse(win.getComputedStyle(x).backgroundColor);
					if (b && b.a >= 0.999) return b.c;
				}
				return [255, 255, 255];
			})();
			const lFx = over(lc, lc.a * chain(paint), ground), pFx = over(pc, pc.a * chain(par), ground);
			const rr = ratio(lFx, pFx);
			out.linksChecked++;
			if (rr < 3) out.links.push({ page, palette, text: t.slice(0, 40), link: lFx.join(','), text_color: pFx.join(','), ratio: +rr.toFixed(2), parent: par.tagName.toLowerCase() + (typeof par.className === 'string' && par.className ? '.' + par.className.trim().split(/\s+/)[0] : '') });
		}
	}

	async function measure(doc, page, palette, out) {
		const win = doc.defaultView;
		const ground = (() => { // page ground: body over html over white
			let g = [255, 255, 255];
			for (const el of [doc.documentElement, doc.body]) {
				const p = parse(win.getComputedStyle(el).backgroundColor);
				if (p && p.a > 0) g = over(p, p.a, g);
			}
			return g;
		})();
		const walker = doc.createTreeWalker(doc.body, NodeFilter.SHOW_TEXT);
		const seen = new Set();
		let n;
		while ((n = walker.nextNode())) {
			const t = n.textContent.trim(); if (t.length < 2) continue;
			const el = n.parentElement; if (!el || ['SCRIPT', 'STYLE', 'NOSCRIPT'].includes(el.tagName)) continue;
			if (el.closest('[aria-hidden="true"]')) { out.decorative++; continue; }
			const cs = win.getComputedStyle(el);
			if (cs.display === 'none' || cs.visibility === 'hidden') continue;
			const r = el.getBoundingClientRect(); if (r.width < 2 || r.height < 2) continue;
			// effective fg: computed color, alpha-multiplied by the opacity chain
			const fgP = parse(cs.color); if (!fgP) continue;
			let op = 1, bg = null, imaged = false;
			for (let a = el; a && a !== doc.documentElement; a = a.parentElement) {
				const acs = win.getComputedStyle(a);
				op *= parseFloat(acs.opacity);
				if (bg === null) {
					// An image or a gradient that covers the box makes the ratio
					// unknowable: skip. The one exemption is the house underline, a
					// gradient one or two pixels tall (background-size `0 1px`,
					// grown to `100% 1px` on hover): it is a line, not a ground, and
					// skipping it hid every note title (Codex on #508, both ways).
					if (acs.backgroundImage !== 'none') {
						const tall = acs.backgroundSize.split(',').map(s => s.trim().split(/\s+/)[1] || 'auto');
						const line = /gradient/.test(acs.backgroundImage) && !/url\(/.test(acs.backgroundImage) && tall.every(h => /^[0-2](\.\d+)?px$/.test(h));
						if (!line) { imaged = true; break; }
					}
					const p = parse(acs.backgroundColor);
					if (p && p.a >= 0.999) bg = p.c;
					else if (p && p.a > 0) { bg = over(p, p.a, ground); } // translucent over ground (approx.)
				}
			}
			if (imaged) { out.imaged++; continue; }
			if (bg === null) bg = ground;
			const fg = over(fgP, fgP.a * op, bg);
			const rr = ratio(fg, bg);
			const sel = el.tagName.toLowerCase() + (el.className && typeof el.className === 'string' ? '.' + el.className.trim().split(/\s+/).slice(0, 2).join('.') : '');
			// Opacity is part of the identity: the same class at full strength and
			// faded (a roadmap legend's done vs later cells) are different pairs.
			// Keyed without it, the passing first cell hid every faded one.
			const size = parseFloat(cs.fontSize), w = parseInt(cs.fontWeight, 10) || 400;
			const need = size >= 24 || (size >= 18.66 && w >= 700) ? 3 : 4.5;
			// The threshold is part of the identity too: a large instance passing
			// at 3:1 must not hide a small one of the same style failing at 4.5
			// (Codex on #508, round 2).
			const key = [page, palette, sel, cs.color, bg.join(), op.toFixed(2), need].join('|');
			if (seen.has(key)) continue; seen.add(key);
			out.checked++;
			if (rr < need) out.violations.push({ page, palette, sel, text: t.slice(0, 40), fg: fg.join(','), bg: bg.join(','), ratio: +rr.toFixed(2), need, size: +size.toFixed(1), weight: w, opacity: +op.toFixed(2) });
		}
	}

	window.__snContrastDone = (async () => {
		const out = { violations: [], links: [], checked: 0, linksChecked: 0, imaged: 0, decorative: 0, pages: PAGES.length, unloaded: [] };
		for (const page of PAGES) {
			const fr = document.createElement('iframe');
			fr.style.cssText = 'position:fixed;left:-2000px;top:0;width:1280px;height:2200px';
			document.body.appendChild(fr);
			await new Promise((res, rej) => { fr.onload = res; fr.onerror = res; fr.src = page; setTimeout(res, 12000); });
			await new Promise(r => setTimeout(r, 800));
			const doc = fr.contentDocument;
			// Rendered means the route itself: the 404 template has a <main> too,
			// so a vanished route would be measured as its error page and pass
			// (Codex on #508, round 4). WordPress marks that page body.error404.
			if (!doc || !doc.body || !doc.querySelector('main') || doc.body.classList.contains('error404')) out.unloaded.push(page);
			if (doc && doc.body) {
				const kill = doc.createElement('style');
				kill.textContent = '*, *::before, *::after { animation: none !important; transition: none !important; }';
				doc.head.appendChild(kill);
				for (const palette of ['light', 'dark']) {
					doc.documentElement.setAttribute('data-theme', palette);
					await new Promise(r => setTimeout(r, 120));
					await measure(doc, page, palette, out);
					measureLinks(doc, page, palette, out);
				}
			}
			fr.remove();
		}
		out.violations.sort((a, b) => a.ratio - b.ratio);
		window.__snContrast = out;
		console.log(`contrast-computed: ${out.checked} unique pairs checked across ${out.pages} pages ×2 palettes; ${out.violations.length} below AA; ${out.links.length} links marked by color alone under 3:1; ${out.imaged} skipped over images`);
		return out;
	})();
})();
