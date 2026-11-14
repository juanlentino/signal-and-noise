/**
 * sn-beacon.js "still here" ping (2026-11-14): one `tm` after 10 s of visible
 * time, never while hidden; the deltas add up to the visible time; a bfcache
 * restore re-arms it. Runs the real beacon under a fake DOM with a fake clock.
 */
const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const SRC = fs.readFileSync(path.join(__dirname, '../../assets/js/sn-beacon.js'), 'utf8');

function page() {
  const sent = [];
  const on = {};
  let now = 0;
  let timers = [];
  const add = (t, fn) => { (on[t] = on[t] || []).push(fn); };
  const doc = { addEventListener: add, prerendering: false, visibilityState: 'visible', referrer: '', cookie: '', documentElement: { scrollHeight: 3000 }, getElementById: (id) => (id === 'sn-beacon-data' ? { textContent: '{"endpoint":"/_sn/px","k":"k"}' } : null) };
  const win = { addEventListener: add, removeEventListener() {}, location: { pathname: '/notes/x/', search: '', hostname: 'juanlentino.com' }, innerHeight: 1000, scrollY: 0, outerWidth: 1200, chrome: {} };
  const ctx = {
    window: win, document: doc, location: win.location, URLSearchParams, URL, Blob, JSON, Object, Math, Date, isFinite,
    navigator: { userAgent: 'Mozilla/5.0 Chrome/140.0.0.0 Safari/537.36', userAgentData: { brands: [], mobile: false, platform: 'macOS' }, webdriver: false },
    fetch: (url, o) => { sent.push(JSON.parse(o.body)); return Promise.resolve(); },
    requestAnimationFrame: (fn) => fn(), performance: { now: () => now },
    setTimeout: (fn, ms) => { const t = { fn, at: now + ms }; timers.push(t); return t; },
    clearTimeout: (t) => { timers = timers.filter((x) => x !== t); },
  };
  vm.runInNewContext(SRC, ctx);
  const fire = (t, ev = {}) => (on[t] || []).forEach((fn) => fn(Object.assign({ type: t }, ev)));
  // Advance the clock, firing due timers in order.
  const advance = (ms) => {
    const end = now + ms;
    for (;;) {
      const due = timers.filter((t) => t.at <= end).sort((a, b) => a.at - b.at)[0];
      if (!due) break;
      timers = timers.filter((x) => x !== due);
      now = due.at;
      due.fn();
    }
    now = end;
  };
  const hide = () => { doc.visibilityState = 'hidden'; fire('visibilitychange'); };
  const show = () => { doc.visibilityState = 'visible'; fire('visibilitychange'); };
  const tm = () => sent.filter((e) => e.e === 'tm');
  return { sent, fire, advance, hide, show, tm };
}

test('one ping at 10 s of visible time, then nothing until the page is left', () => {
  const p = page();
  p.advance(9999);
  assert.equal(p.tm().length, 0, 'nothing before 10 s');
  p.advance(1);
  assert.equal(p.tm().length, 1, 'the ping at 10 s');
  assert.equal(p.tm()[0].ms, 10000);
  p.advance(60000);
  assert.equal(p.tm().length, 1, 'one ping per page view');
});

test('never while hidden: hidden time does not count toward the 10 s', () => {
  const p = page();
  p.advance(6000);
  p.hide();                       // the leave flush: 6 s
  assert.equal(p.tm().length, 1);
  p.advance(60000);               // hidden a minute
  assert.equal(p.tm().length, 1, 'no ping while hidden');
  p.show();
  p.advance(3999);
  assert.equal(p.tm().length, 1, '9.999 s visible in all: no ping yet');
  p.advance(1);
  assert.equal(p.tm().length, 2, 'the ping when visible time reaches 10 s');
  assert.equal(p.tm()[1].ms, 4000, 'it carries only the time since the last flush');
});

test('the deltas add up to the visible time', () => {
  const p = page();
  p.advance(25000);
  p.hide();
  p.advance(5000);
  p.show();
  p.advance(7000);
  p.fire('pagehide');
  const sum = p.tm().reduce((n, e) => n + e.ms, 0);
  assert.equal(sum, 32000, 'ping 10 s + leave 15 s + return 7 s');
  assert.deepEqual(p.tm().map((e) => e.ms), [10000, 15000, 7000]);
});

test('a bfcache restore re-arms the ping', () => {
  const p = page();
  p.advance(12000);
  p.fire('pagehide');
  const before = p.tm().length;
  p.fire('pageshow', { persisted: true });
  p.advance(10000);
  const after = p.tm().filter((e, i) => i >= before);
  assert.equal(after.length, 1, 'one ping on the restored view');
  assert.equal(after[0].ms, 10000);
});

test('a page left before 10 s sends its one leave flush and no ping', () => {
  const p = page();
  p.advance(4000);
  p.fire('pagehide');
  p.advance(20000);
  assert.deepEqual(p.tm().map((e) => e.ms), [4000]);
});

test('the ping never sets the no-input signal: a reader may read before touching anything', () => {
  const p = page();
  p.advance(10000);
  const ping = p.tm()[0];
  assert.equal(ping.ms, 10000);
  assert.equal(ping.sg.ni, false, 'ni stays clear on the ping');
  p.fire('wheel');
  p.fire('pagehide');
  assert.ok(p.sent.every((e) => e.sg.ni === false), 'and on every later row once the reader touches the page');
});
