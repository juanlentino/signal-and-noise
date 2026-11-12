/**
 * sn-beacon.js sends no résumé lane (2026-11-12): /resume is one résumé, so the
 * lane read (2026-11-02 to 2026-11-12) is gone. Even a cached page that still
 * carries the old lane markup sends no `lane` key. Runs the real beacon under a
 * fake DOM.
 */
const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const SRC = fs.readFileSync(path.join(__dirname, '../../assets/js/sn-beacon.js'), 'utf8');
const SHOWN = '.sn-resume-variant:not([hidden])';

// lanes: [{ id, hidden }]. querySelectorAll answers the two selectors a real
// DOM would: every lane, or only the ones without [hidden].
function page(lanes, pathname = '/resume/') {
  const sent = [];
  const on = {};
  const add = (t, fn) => { (on[t] = on[t] || []).push(fn); };
  const doc = {
    addEventListener: add, prerendering: false, visibilityState: 'visible', referrer: '', documentElement: { scrollHeight: 3000 },
    getElementById: (id) => (id === 'sn-beacon-data' ? { textContent: '{"endpoint":"/_sn/px","k":"k"}' } : null),
    querySelectorAll: (sel) => (sel === SHOWN ? lanes.filter((l) => !l.hidden) : sel === '.sn-resume-variant' ? lanes : []),
  };
  const win = { addEventListener: add, removeEventListener() {}, location: { pathname, search: '', hostname: 'juanlentino.com' }, innerHeight: 1000, scrollY: 0, outerWidth: 1200, chrome: {} };
  const ctx = {
    window: win, document: doc, location: win.location, URLSearchParams, URL, Blob, JSON, Object, Math, Date, isFinite, String,
    navigator: { userAgent: 'Mozilla/5.0 (X11; Linux x86_64; rv:140.0) Gecko/20100101 Firefox/140.0' },
    fetch: (url, o) => { sent.push(JSON.parse(o.body)); return Promise.resolve(); },
    requestAnimationFrame: (fn) => fn(), performance: { now: () => 0 },
  };
  vm.runInNewContext(SRC, ctx);
  const fire = (t, ev = {}) => (on[t] || []).forEach((fn) => fn(Object.assign({ type: t }, ev)));
  const scrollTo = (y) => { win.scrollY = y; fire('scroll'); };
  return { sent, fire, scrollTo, win, doc };
}
const both = (shown) => [{ id: 'resume-music', hidden: shown !== 'music' }, { id: 'resume-operations', hidden: shown !== 'operations' }];

test('no beacon of a /resume view carries a lane, even with the old lane markup', () => {
  const p = page(both('operations'));
  p.scrollTo(2000);
  p.win.SN_BEACON.event('resume_formats_md');
  assert.ok(p.sent.length >= 3);
  assert.ok(p.sent.every((e) => !('lane' in e)));
});

test('a page without lanes sends no lane key at all', () => {
  const p = page([], '/notes/x/');
  p.scrollTo(2000);
  assert.ok(p.sent.length >= 2 && p.sent.every((e) => !('lane' in e)));
});

test('the beacon source no longer reads the lane markup', () => {
  assert.ok(!SRC.includes('sn-resume-variant') && !SRC.includes('readLane'));
});
