/**
 * sn-beacon.js bot signals: every event carries `sg` {wd, ni, um, hl, tz}.
 * Runs the real beacon under a fake DOM. A scripted scroll with no input sets
 * ni; a wheel first leaves it clear; navigator.webdriver sets wd. The captured
 * payload is pinned in tests/js/fixtures/beacon-payload.json, which the
 * analytics worker's join test parses with the real worker (same file, copied).
 * SN_WRITE_FIXTURE=1 rewrites it.
 */
const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const SRC = fs.readFileSync(path.join(__dirname, '../../assets/js/sn-beacon.js'), 'utf8');
const FIXTURE = path.join(__dirname, 'fixtures/beacon-payload.json');
const CHROME_MAC = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36';
const UAD = { brands: [{ brand: 'Chromium', version: '140' }, { brand: 'Google Chrome', version: '140' }], mobile: false, platform: 'macOS' };

function page(nav = {}, winExtra = {}) {
  const sent = [];
  const on = {};
  const add = (t, fn) => { (on[t] = on[t] || []).push(fn); };
  const doc = { addEventListener: add, prerendering: false, visibilityState: 'visible', referrer: '', cookie: '', documentElement: { scrollHeight: 3000 } };
  const win = Object.assign({ SN_BEACON: { endpoint: '/_sn/px', k: 'k' }, addEventListener: add, removeEventListener() {}, location: { pathname: '/notes/x/', search: '', hostname: 'juanlentino.com' }, innerHeight: 1000, scrollY: 0, outerWidth: 1200, chrome: {} }, winExtra);
  const ctx = {
    window: win, document: doc, location: win.location, URLSearchParams, URL, Blob, JSON, Object, Math, Date, isFinite,
    navigator: Object.assign({ userAgent: CHROME_MAC, userAgentData: UAD, webdriver: false }, nav),
    fetch: (url, o) => { sent.push(JSON.parse(o.body)); return Promise.resolve(); },
    requestAnimationFrame: (fn) => fn(), performance: { now: () => 0 },
  };
  vm.runInNewContext(SRC, ctx);
  const fire = (t) => (on[t] || []).forEach((fn) => fn({ type: t }));
  const scrollTo = (y) => { win.scrollY = y; fire('scroll'); };
  return { sent, fire, scrollTo };
}

test('every event carries sg; a clean human reads all-clear', () => {
  const p = page();
  p.fire('wheel');
  p.scrollTo(2000);
  assert.ok(p.sent.length >= 2);
  for (const e of p.sent) assert.deepEqual(Object.keys(e.sg).sort(), ['hl', 'ni', 'tz', 'um', 'wd']);
  const sc = p.sent.filter((e) => e.e === 'sc');
  assert.ok(sc.length > 0);
  assert.ok(sc.every((e) => e.sg.ni === false && e.sg.wd === false && e.sg.um === false && e.sg.hl === false));
});

test('a scripted scroll with no input sets ni', () => {
  const p = page();
  assert.equal(p.sent[0].e, 'pv');
  assert.equal(p.sent[0].sg.ni, false, 'the pageview fires before any chance of input');
  p.scrollTo(2000);
  const sc = p.sent.filter((e) => e.e === 'sc');
  assert.ok(sc.length > 0 && sc.every((e) => e.sg.ni === true));
});

test('a short page sends milestones at load without setting ni', () => {
  const p = page({}, { innerHeight: 5000 });
  assert.ok(p.sent.some((e) => e.e === 'sc'));
  assert.ok(p.sent.every((e) => e.sg.ni === false));
});

test('navigator.webdriver sets wd', () => {
  assert.equal(page({ webdriver: true }).sent[0].sg.wd, true);
});

test('a Chromium UA without userAgentData sets um; headless tells set hl', () => {
  assert.equal(page({ userAgentData: undefined }).sent[0].sg.um, true);
  assert.equal(page({ userAgentData: Object.assign({}, UAD, { platform: 'Windows' }) }).sent[0].sg.um, true);
  assert.equal(page({ userAgent: CHROME_MAC.replace('Chrome/', 'HeadlessChrome/') }).sent[0].sg.hl, true);
  assert.equal(page({}, { outerWidth: 0 }).sent[0].sg.hl, true);
  assert.equal(page({}, { chrome: undefined }).sent[0].sg.hl, true);
});

test('iOS Chrome (WebKit) is not judged as Chromium', () => {
  const ios = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/140.0 Mobile/15E148 Safari/604.1';
  const sg = page({ userAgent: ios, userAgentData: undefined }, { chrome: undefined }).sent[0].sg;
  assert.equal(sg.um, false);
  assert.equal(sg.hl, false);
});

test('the payload shape is pinned in the shared fixture', () => {
  const p = page({ webdriver: true });
  p.scrollTo(2000);
  const sample = p.sent.find((e) => e.e === 'sc');
  sample.sg.tz = 240; // the host clock's zone is not the fixture's
  assert.ok(Number.isSafeInteger(sample.pid) && sample.pid > 0, 'pid is a positive safe integer');
  assert.equal(sample.pid, p.sent.find((e) => e.e === 'pv').pid, 'a scroll event carries its pageview\'s pid');
  sample.pid = 1; // random per page view; the fixture pins the field, not the draw
  if (process.env.SN_WRITE_FIXTURE === '1') fs.writeFileSync(FIXTURE, JSON.stringify(sample, null, 2) + '\n');
  assert.deepEqual(JSON.parse(fs.readFileSync(FIXTURE, 'utf8')), sample);
});
