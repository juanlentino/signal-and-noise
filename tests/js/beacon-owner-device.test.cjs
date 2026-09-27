/**
 * sn-beacon.js owner-device gate: a browser carrying the exact cookie pair
 * sn_owner=1 (set by the plugin at login) sends nothing; any other cookie,
 * including a look-alike name, still sends. A throwing cookie read fails open.
 */
const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const SRC = fs.readFileSync(path.join(__dirname, '../../assets/js/sn-beacon.js'), 'utf8');

function run(cookie) {
  const sent = [];
  const doc = { addEventListener() {}, prerendering: false, visibilityState: 'visible', referrer: '', documentElement: { scrollHeight: 0 }, body: { scrollHeight: 0 } };
  if (typeof cookie === 'function') Object.defineProperty(doc, 'cookie', { get: cookie });
  else doc.cookie = cookie;
  const win = { SN_BEACON: { endpoint: '/_sn/px', k: 'k' }, addEventListener() {}, removeEventListener() {}, location: { pathname: '/', search: '', hostname: 'x' }, innerHeight: 0, scrollY: 0 };
  const ctx = {
    window: win, document: doc, location: win.location, URLSearchParams, URL, Blob, JSON, Object, Math, Date, setTimeout, clearTimeout,
    navigator: { sendBeacon(url, body) { sent.push(url); return true; } },
    addEventListener() {}, PerformanceObserver: undefined, performance: { now: () => 0 },
  };
  vm.runInNewContext(SRC, ctx);
  return sent.length;
}

test('no cookie: the pageview is sent', () => { assert.ok(run('') > 0); });
test('sn_owner=1 present: nothing is sent', () => { assert.equal(run('a=b; sn_owner=1; c=d'), 0); });
test('look-alike xsn_owner=1: still sent', () => { assert.ok(run('xsn_owner=1') > 0); });
test('sn_owner=0: still sent', () => { assert.ok(run('sn_owner=0') > 0); });
test('cookie read throws: fails open and sends', () => { assert.ok(run(() => { throw new Error('blocked'); }) > 0); });
