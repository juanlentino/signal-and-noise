/**
 * sn-beacon.js on a bfcache restore (pageshow, persisted) and with no token.
 * A restored view is a new page view, and three things used to go wrong:
 *   - it resent the ORIGINAL document.referrer (the search engine the reader
 *     first arrived from), so one arrival counted as two; UTM was already
 *     guarded. It now sends the site's own origin, the kind of value an
 *     in-site navigation sends, which the collector files as same-site;
 *   - it never evaluated scroll, so a page too short to scroll reported its
 *     milestones on first load and none on the restored view;
 *   - with an empty token, SN_BEACON.event was never defined, although the
 *     file promises callers a no-op.
 * Runs the real beacon under a fake DOM.
 */
const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const SRC = fs.readFileSync(path.join(__dirname, '../../assets/js/sn-beacon.js'), 'utf8');

function page({ island = '{"endpoint":"/_sn/px","k":"k"}', referrer = 'https://www.google.com/', scrollHeight = 3000, search = '' } = {}) {
  const sent = [];
  const on = {};
  const add = (t, fn) => { (on[t] = on[t] || []).push(fn); };
  const doc = { addEventListener: add, prerendering: false, visibilityState: 'visible', referrer, cookie: '', documentElement: { scrollHeight }, getElementById: (id) => (id === 'sn-beacon-data' ? { textContent: island } : null) };
  const win = { addEventListener: add, removeEventListener() {}, location: { pathname: '/notes/x/', search, hostname: 'juanlentino.com', origin: 'https://juanlentino.com' }, innerHeight: 1000, scrollY: 0, outerWidth: 1200, chrome: {} };
  const ctx = {
    window: win, document: doc, location: win.location, URLSearchParams, URL, Blob, JSON, Object, Math, Date, isFinite,
    navigator: { userAgent: 'Mozilla/5.0 Chrome/140.0.0.0 Safari/537.36', userAgentData: { brands: [], mobile: false, platform: 'macOS' }, webdriver: false },
    fetch: (url, o) => { sent.push(JSON.parse(o.body)); return Promise.resolve(); },
    requestAnimationFrame: (fn) => fn(), performance: { now: () => 0 },
    setTimeout: () => 0, clearTimeout() {},
  };
  vm.runInNewContext(SRC, ctx);
  const fire = (t, ev = {}) => (on[t] || []).forEach((fn) => fn(Object.assign({ type: t }, ev)));
  const of = (e) => sent.filter((x) => x.e === e);
  return { sent, win, fire, of };
}

test('the first page view carries the referrer the reader arrived with', () => {
  const p = page();
  assert.equal(p.of('pv').length, 1);
  assert.equal(p.of('pv')[0].r, 'https://www.google.com/');
});

test('a restored view is a new page view that does not resend the arrival referrer', () => {
  const p = page({ search: '?utm_source=news' });
  p.fire('pageshow', { persisted: true });
  const [first, second] = p.of('pv');
  assert.equal(p.of('pv').length, 2);
  assert.notEqual(second.pid, first.pid, 'a new view');
  assert.equal(second.r, 'https://juanlentino.com/', 'the site itself, as an in-site navigation sends');
  assert.equal(new URL(second.r).hostname, p.win.location.hostname, 'which the collector reads as same-site');
  assert.ok(first.utm && !second.utm, 'UTM stays first-view only');
});

test('a pageshow that is not a restore sends nothing', () => {
  const p = page();
  p.fire('pageshow', { persisted: false });
  assert.equal(p.of('pv').length, 1);
});

test('a custom event on the restored view carries that view\'s referrer, not the arrival one', () => {
  const p = page();
  p.win.SN_BEACON.event('before');
  p.fire('pageshow', { persisted: true });
  p.win.SN_BEACON.event('after');
  const [before, after] = p.of('ce');
  assert.equal(before.r, 'https://www.google.com/');
  assert.equal(after.r, 'https://juanlentino.com/');
});

test('a page too short to scroll reports its milestones again on the restored view', () => {
  const p = page({ scrollHeight: 800 });
  assert.deepEqual(p.of('sc').map((e) => e.d), [25, 50, 75, 100], 'first load');
  const firstPid = p.of('pv')[0].pid;
  p.fire('pageshow', { persisted: true });
  const restored = p.of('sc').slice(4);
  assert.deepEqual(restored.map((e) => e.d), [25, 50, 75, 100], 'restored view');
  const pid = p.of('pv')[1].pid;
  assert.ok(pid !== firstPid && restored.every((e) => e.pid === pid), 'under the restored view\'s pid');
  assert.ok(restored.every((e) => e.sg.ni === false), 'and it is not an input-timing signal');
});

test('a long page at the top reports no milestone on restore', () => {
  const p = page();
  p.fire('pageshow', { persisted: true });
  assert.equal(p.of('sc').length, 0);
});

for (const island of ['{"endpoint":"/_sn/px","k":""}', '{"endpoint":"","k":"k"}', '{}']) {
  test(`island ${island}: SN_BEACON.event is a callable no-op and nothing is sent`, () => {
    const p = page({ island });
    assert.equal(typeof p.win.SN_BEACON.event, 'function');
    assert.doesNotThrow(() => p.win.SN_BEACON.event('x', { a: 'b' }));
    p.fire('pageshow', { persisted: true });
    assert.equal(p.sent.length, 0);
  });
}
