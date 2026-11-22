/**
 * sn-beacon.js CLS attribution: the `vc` event carries `s`, a short selector
 * (tag, #id, up to three classes) for the element that moved most in the
 * largest layout shift. Nothing about the reader, and a failure to build it
 * never stops the `vc` beacon. Runs the real beacon under node:vm with a stub
 * window.webVitals whose callbacks the tests call by hand.
 */
const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const SRC = fs.readFileSync(path.join(__dirname, '../../assets/js/sn-beacon.js'), 'utf8');

function page() {
  const sent = [];
  const cb = {};
  const noop = () => {};
  const doc = { addEventListener: noop, prerendering: false, visibilityState: 'visible', referrer: '', cookie: '', documentElement: { scrollHeight: 3000 }, getElementById: (id) => (id === 'sn-beacon-data' ? { textContent: '{"endpoint":"/_sn/px","k":"k"}' } : null) };
  const win = {
    addEventListener: noop, removeEventListener: noop, location: { pathname: '/notes/x/', search: '', hostname: 'juanlentino.com' }, innerHeight: 1000, scrollY: 0, outerWidth: 1200, chrome: {},
    webVitals: { onLCP: (fn) => { cb.vl = fn; }, onINP: (fn) => { cb.vi = fn; }, onCLS: (fn) => { cb.vc = fn; } },
  };
  vm.runInNewContext(SRC, {
    window: win, document: doc, location: win.location, URLSearchParams, URL, Blob, JSON, Object, Math, Date, isFinite,
    navigator: { userAgent: 'Mozilla/5.0 Chrome/140.0.0.0 Safari/537.36', userAgentData: { brands: [], mobile: false, platform: 'macOS' }, webdriver: false },
    fetch: (url, o) => { sent.push(JSON.parse(o.body)); return Promise.resolve(); },
    requestAnimationFrame: (fn) => fn(), performance: { now: () => 0 }, setTimeout: () => 0, clearTimeout: noop,
  });
  // Report one metric and return the payload it sent.
  const report = (ev, metric) => { cb[ev](metric); return sent.filter((e) => e.e === ev).pop(); };
  return { report };
}

// An element as the selector code reads it: nodeType, tagName, getAttribute.
const node = (tag, attrs = {}) => ({ nodeType: 1, tagName: tag.toUpperCase(), getAttribute: (k) => (k in attrs ? attrs[k] : null) });
const rect = (width, height) => ({ width, height });
// Browsers hand back a frozen sources array, so the fixtures freeze theirs.
const entry = (value, sources) => ({ value, sources: Object.freeze(sources) });
const src = (n, prev, cur) => ({ node: n, previousRect: prev, currentRect: cur });
const cls = (entries) => page().report('vc', { value: 0.25, entries });

test('the largest shift\'s largest source names the element', () => {
  const p = cls([
    entry(0.02, [src(node('p', { class: 'small' }), rect(900, 900), rect(900, 900))]),
    entry(0.2, [
      src(node('span', { id: 'tiny' }), rect(10, 10), rect(10, 10)),
      src(node('div', { id: 'hero', class: 'wp-block-cover alignfull has-bg extra' }), rect(100, 50), rect(800, 400)),
      src(node('img', { class: 'mid' }), rect(300, 300), rect(300, 300)),
    ]),
  ]);
  assert.equal(p.s, 'div#hero.wp-block-cover.alignfull.has-bg', 'tag, id, the first three classes');
  assert.equal(p.v, 250, 'the reading itself is unchanged');
});

test('the larger of the previous and current rect is the moved area', () => {
  const p = cls([entry(0.1, [
    src(node('aside'), rect(400, 400), rect(400, 400)),
    src(node('figure'), rect(900, 900), rect(0, 0)),
  ])]);
  assert.equal(p.s, 'figure');
});

test('a removed node, or one that is not an element, falls back to the next source', () => {
  const p = cls([entry(0.1, [
    src(null, rect(900, 900), rect(900, 900)),
    src({ nodeType: 3, tagName: undefined }, rect(800, 800), rect(800, 800)),
    src(node('nav', { class: 'site-nav' }), rect(100, 100), rect(100, 100)),
  ])]);
  assert.equal(p.s, 'nav.site-nav');
});

test('an id or class with unusual characters is dropped, not mangled', () => {
  const p = cls([entry(0.1, [src(node('div', { id: 'a b', class: 'md:flex w-1/2 p-2.5 ok_1 café fine-2 x#y third fourth' }), rect(9, 9), rect(9, 9))])]);
  assert.equal(p.s, 'div.ok_1.fine-2.third');
  assert.match(p.s, /^[A-Za-z0-9_#.-]+$/);
});

test('the 96-character cap drops trailing classes and never cuts a token', () => {
  const a = 'a'.repeat(40), b = 'b'.repeat(40), c = 'c'.repeat(40);
  const p = cls([entry(0.1, [src(node('section', { class: `${a} ${b} ${c}` }), rect(9, 9), rect(9, 9))])]);
  assert.equal(p.s, `section.${a}.${b}`, '7 + 41 + 41 = 89 fits; a third class would make 130');
  const long = cls([entry(0.1, [src(node('div', { id: 'i'.repeat(120), class: 'kept' }), rect(9, 9), rect(9, 9))])]);
  assert.equal(long.s, 'div.kept', 'an id that cannot fit is dropped whole');
});

test('html and body are named by tag alone: their classes hold reader state', () => {
  const body = cls([entry(0.1, [src(node('body', { id: 'top', class: 'error404 logged-in admin-bar' }), rect(9, 9), rect(9, 9))])]);
  assert.equal(body.s, 'body');
  const html = cls([entry(0.1, [src(node('html', { class: 'js dark' }), rect(9, 9), rect(9, 9))])]);
  assert.equal(html.s, 'html');
});

test('nothing to name: the vc beacon goes out exactly as before, with no s key', () => {
  const bare = (metric) => {
    const p = page().report('vc', metric);
    assert.ok(p, 'the vc beacon was sent');
    assert.equal(p.v, 250);
    assert.equal('s' in p, false, 'no s key at all');
  };
  bare({ value: 0.25, entries: [] });
  bare({ value: 0.25 });
  bare({ value: 0.25, entries: [entry(0.1, [])] });
  bare({ value: 0.25, entries: [{ value: 0.1 }] });
  bare({ value: 0.25, entries: [entry(0.1, [src(null, rect(9, 9), rect(9, 9))])] });
  bare({ value: 0.25, entries: [entry(0.1, [src(node('a:b'), rect(9, 9), rect(9, 9))])] });
});

test('a throw inside the selector code never stops the vc beacon', () => {
  const boom = () => { throw new Error('boom'); };
  const sent = (metric) => {
    const p = page().report('vc', metric);
    assert.ok(p, 'the vc beacon was sent');
    assert.equal(p.v, 250);
    assert.equal('s' in p, false);
  };
  sent({ value: 0.25, get entries() { return boom(); } });
  sent({ value: 0.25, entries: [entry(0.1, [{ get node() { return boom(); }, previousRect: rect(9, 9), currentRect: rect(9, 9) }])] });
  sent({ value: 0.25, entries: [entry(0.1, [src({ nodeType: 1, tagName: 'DIV', getAttribute: boom }, rect(9, 9), rect(9, 9))])] });
});

test('LCP and INP never carry s', () => {
  const entries = [entry(0.1, [src(node('div', { id: 'hero' }), rect(9, 9), rect(9, 9))])];
  for (const ev of ['vl', 'vi']) {
    const p = page().report(ev, { value: 1234, entries });
    assert.equal(p.v, 1234);
    assert.equal('s' in p, false, ev + ' has no s');
  }
});
