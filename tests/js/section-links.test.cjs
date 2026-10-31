// 2026-10-31: a copy-link button inside each H2 the contents list names.
// Drawn by article-toc.js (copying needs script, so feeds, the twins and
// no-JS readers never get a dead button) through sn-copy.js. Drives the REAL
// files under a fake DOM.
const { test, mock } = require('node:test');
const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const read = (f) => readFileSync(path.join(__dirname, '..', '..', 'assets', 'js', f), 'utf8');

function el(tag, attrs = {}) {
  return {
    tagName: tag, attrs: { ...attrs }, children: [], listeners: {}, style: {}, textContent: '', parentNode: null,
    setAttribute(k, v) { this.attrs[k] = String(v); }, getAttribute(k) { return k in this.attrs ? this.attrs[k] : null; },
    appendChild(c) { c.parentNode = this; this.children.push(c); return c; },
    removeChild(c) { this.children = this.children.filter((x) => x !== c); },
    addEventListener(t, cb) { this.listeners[t] = cb; }, select() { this.selected = true; },
    contains(n) { for (let x = n; x; x = x.parentNode) { if (x === this) return true; } return false; },
  };
}

function page({ copy = true, clipboard = 'ok', canonical = 'https://juanlentino.com/notes/a-note/' } = {}) {
  const nav = el('NAV', { class: 'sn-article-toc' });
  const link = (href, text) => { const a = el('A', { href }); a.textContent = text; return a; };
  const links = [link('#intro', 'Intro'), link('#the-gap', 'The gap'), link('#gone', 'Gone'), link('#references', 'References')];
  nav.querySelectorAll = (sel) => (sel === 'a[href^="#"]' ? links : []);
  const h = { intro: el('H2', { id: 'intro' }), 'the-gap': el('H2', { id: 'the-gap' }), references: el('DIV', { id: 'references' }) };
  const body = el('BODY');
  const ctl = { clipboard }; const writes = [];
  const navigator = { clipboard: { writeText: (t) => { writes.push(t); return ctl.clipboard === 'ok' ? Promise.resolve() : Promise.reject(new Error('denied')); } } };
  const document = {
    body,
    querySelector: (sel) => (sel === '.sn-article-toc' ? nav : sel === 'link[rel="canonical"]' && canonical ? { href: canonical } : null),
    getElementById: (id) => h[id] || null,
    createElement: (t) => el(t.toUpperCase()),
  };
  const window = { document, navigator, location: { origin: 'https://mirror.test', pathname: '/notes/a-note/' } }; // not the canonical host
  const ctx = { window, document, navigator, setTimeout: (f, ms) => setTimeout(f, ms), clearTimeout: (t) => clearTimeout(t) };
  if (copy) vm.runInNewContext(read('sn-copy.js'), ctx);
  vm.runInNewContext(read('article-toc.js'), ctx);
  const btn = (key) => h[key].children.find((c) => c.tagName === 'BUTTON') || null;
  const live = () => body.children.find((c) => c.attrs.role === 'status') || null;
  return { h, btn, live, writes, ctl };
}
const flush = () => new Promise((r) => setImmediate(r));

test('each H2 the contents list names gets a labeled copy-link button; the heading keeps its own name', () => {
  const p = page();
  for (const [key, label] of [['intro', 'Intro'], ['the-gap', 'The gap']]) {
    const b = p.btn(key);
    assert.ok(b, key + ' has a button');
    assert.equal(b.type, 'button');
    assert.equal(b.getAttribute('aria-label'), 'Copy link to section: ' + label);
    assert.equal(p.h[key].getAttribute('aria-label'), label, 'the heading list reads the heading, not the button');
  }
  assert.equal(p.btn('references'), null, 'only H2s');
});

test('a click copies the canonical URL with the section id, shows COPIED, announces it, and reverts', async () => {
  mock.timers.enable({ apis: ['setTimeout'] });
  try {
    const p = page();
    const b = p.btn('the-gap');
    const glyph = b.textContent;
    b.listeners.click();
    await flush();
    assert.deepEqual(p.writes, ['https://juanlentino.com/notes/a-note/#the-gap'], 'the canonical URL, not the address this copy was read at');
    assert.equal(b.textContent, 'COPIED');
    assert.equal(p.live().textContent, 'Link to section copied');
    assert.equal(p.live().attrs['aria-live'], 'polite');
    mock.timers.tick(1600);
    assert.equal(b.textContent, glyph);
  } finally { mock.timers.reset(); }
});

test('a refused clipboard says so; without a canonical link the page URL is used', async () => {
  mock.timers.enable({ apis: ['setTimeout'] });
  try {
    const p = page({ clipboard: 'fail', canonical: null });
    p.btn('intro').listeners.click();
    await flush();
    assert.deepEqual(p.writes, ['https://mirror.test/notes/a-note/#intro'], 'the page URL when no canonical is declared');
    assert.equal(p.btn('intro').textContent, 'COPY FAILED');
    assert.equal(p.live().textContent, 'Copy failed');
  } finally { mock.timers.reset(); }
});

test('negative control: without the copy helper no button is drawn (never a dead one)', () => {
  const p = page({ copy: false });
  assert.equal(p.btn('intro'), null);
  assert.equal(p.h.intro.getAttribute('aria-label'), null);
});
