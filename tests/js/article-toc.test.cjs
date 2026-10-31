// assets/js/article-toc.js enhances the server-rendered contents card
// (inc/article-toc.php: <nav class="sn-article-toc"> with <li><a href="#id">
// per H2). A click on one of its links scrolls to the section (smooth, or
// instant under prefers-reduced-motion), moves focus there without a second
// jump, and replaces the hash. A note with no TOC, a link to a heading that is
// not on the page, or a click off a link, leaves the browser's own behavior
// alone. The reading progress is CSS now (the tuning dial), so nothing here
// measures scroll. Drives the REAL file under a fake DOM.
const { test } = require('node:test');
const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

const SRC = readFileSync(path.join(__dirname, '..', '..', 'assets', 'js', 'article-toc.js'), 'utf8');

function el(tag, attrs = {}) {
  return {
    tagName: tag, attrs: { ...attrs }, parentNode: null, children: [], listeners: {}, calls: [],
    getAttribute(k) { return k in this.attrs ? this.attrs[k] : null; },
    setAttribute(k, v) { this.attrs[k] = String(v); },
    appendChild(c) { c.parentNode = this; this.children.push(c); return c; },
    addEventListener(type, cb) { this.listeners[type] = cb; },
    contains(n) { for (; n; n = n.parentNode) { if (n === this) return true; } return false; },
    closest(sel) {
      for (let n = this; n; n = n.parentNode) {
        if (sel === 'a[href^="#"]' && n.tagName === 'A' && String(n.attrs.href || '').startsWith('#')) return n;
      }
      return null;
    },
    // Spread: the options object comes from the script's realm.
    scrollIntoView(o) { this.calls.push(['scrollIntoView', { ...o }]); },
    focus(o) { this.calls.push(['focus', { ...o }]); },
  };
}

// The nav as article_toc prints it, plus the H2s it points at.
function page({ toc = true, reduce = false, matchMedia = true, history = true } = {}) {
  const nav = el('NAV', { class: 'sn-article-toc' });
  const ol = nav.appendChild(el('OL'));
  const mk = (href) => ol.appendChild(el('LI')).appendChild(el('A', { href }));
  const links = { intro: mk('#intro'), gone: mk('#not-on-page'), empty: mk('#') };
  const headings = { intro: el('H2', { id: 'intro' }) };
  const replaced = [];
  const document = {
    querySelector: (sel) => (sel === '.sn-article-toc' && toc ? nav : null),
    getElementById: (id) => headings[id] || null,
  };
  const window = { document };
  if (matchMedia) window.matchMedia = (q) => ({ matches: reduce && q === '(prefers-reduced-motion: reduce)' });
  if (history) window.history = { replaceState: (...a) => replaced.push([...a]) };
  vm.runInNewContext(SRC, { window, document });
  const click = (target, extra = {}) => {
    const e = { target, defaultPrevented: false, preventDefault() { this.defaultPrevented = true; }, ...extra };
    nav.listeners.click(e);
    return e;
  };
  return { nav, links, headings, replaced, click };
}

test('a TOC click scrolls smoothly to the section, focuses it without a second jump, and sets the hash', () => {
  const p = page();
  const e = p.click(p.links.intro);
  const h = p.headings.intro;
  assert.equal(e.defaultPrevented, true);
  assert.deepEqual(h.calls, [['scrollIntoView', { behavior: 'smooth', block: 'start' }], ['focus', { preventScroll: true }]]);
  assert.equal(h.getAttribute('tabindex'), '-1', 'the heading can take focus');
  assert.deepEqual(p.replaced, [[null, '', '#intro']]);
});

test('under prefers-reduced-motion the jump is instant', () => {
  const p = page({ reduce: true });
  p.click(p.links.intro);
  assert.deepEqual(p.headings.intro.calls[0], ['scrollIntoView', { behavior: 'auto', block: 'start' }]);
});

test('a click on text inside the link resolves to the link', () => {
  const p = page();
  const inner = p.links.intro.appendChild(el('SPAN'));
  assert.equal(p.click(inner).defaultPrevented, true);
  assert.equal(p.headings.intro.calls.length, 2);
});

test('negative control: a link to a missing heading, a bare "#", or a non-link target is left to the browser', () => {
  const p = page();
  for (const target of [p.links.gone, p.links.empty, p.nav, { nodeType: 3 }, null]) {
    assert.equal(p.click(target).defaultPrevented, false, String(target && target.attrs && target.attrs.href));
  }
  assert.deepEqual(p.replaced, [], 'the URL is untouched');
  assert.deepEqual(p.headings.intro.calls, []);
});

test('no matchMedia and no history.replaceState: the click still scrolls and focuses', () => {
  const p = page({ matchMedia: false, history: false });
  assert.equal(p.click(p.links.intro).defaultPrevented, true);
  assert.deepEqual(p.headings.intro.calls[0], ['scrollIntoView', { behavior: 'smooth', block: 'start' }]);
});

test('Cmd, Ctrl, Shift or Alt on a contents link is left to the browser (new tab, window, download)', () => {
  for (const mod of ['metaKey', 'ctrlKey', 'shiftKey', 'altKey']) {
    const p = page();
    assert.equal(p.click(p.links.intro, { [mod]: true }).defaultPrevented, false, mod);
    assert.deepEqual(p.headings.intro.calls || [], [], mod + ': no scroll or focus on this page');
  }
});

test('a note without a TOC: the script does nothing and does not throw', () => {
  assert.doesNotThrow(() => page({ toc: false, matchMedia: false }));
  const p = page({ toc: false });
  assert.equal(p.nav.listeners.click, undefined, 'no handler bound');
});
