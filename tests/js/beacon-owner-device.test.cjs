/**
 * sn-beacon.js owner-device gate: a browser carrying the exact cookie pair
 * sn_owner=1 (set by the plugin at login) sends nothing; any other cookie,
 * including a look-alike name, still sends. A throwing cookie read fails open.
 * The Claude desktop app's built-in browser pane (UA token "Claude/<n>") is the
 * owner's agent browser too: it sends nothing, so its throttled hidden-pane
 * loads (first paint minutes late) stop landing as desktop LCP samples.
 */
const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const SRC = fs.readFileSync(path.join(__dirname, '../../assets/js/sn-beacon.js'), 'utf8');

function run(cookie, ua) {
  const sent = [];
  const doc = { addEventListener() {}, prerendering: false, visibilityState: 'visible', referrer: '', documentElement: { scrollHeight: 0 }, body: { scrollHeight: 0 } };
  if (typeof cookie === 'function') Object.defineProperty(doc, 'cookie', { get: cookie });
  else doc.cookie = cookie;
  const win = { SN_BEACON: { endpoint: '/_sn/px', k: 'k' }, addEventListener() {}, removeEventListener() {}, location: { pathname: '/', search: '', hostname: 'x' }, innerHeight: 0, scrollY: 0 };
  const ctx = {
    window: win, document: doc, location: win.location, URLSearchParams, URL, Blob, JSON, Object, Math, Date, setTimeout, clearTimeout,
    navigator: { userAgent: ua || '', sendBeacon(url, body) { sent.push(url); return true; } },
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

const CHROME = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.7977.130 Safari/537.36';
const PANE = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Claude/2.37337.1 Chrome/152.0.7977.130 Safari/537.36';
test('desktop Chrome UA: the pageview is sent', () => { assert.ok(run('', CHROME) > 0); });
test('Claude app browser pane UA: nothing is sent', () => { assert.equal(run('', PANE), 0); });
test('a UA that only mentions Claude in prose: still sent', () => { assert.ok(run('', CHROME + ' ClaudeBot-free Claude') > 0); });
