/**
 * sn-beacon.js résumé lane (2026-11-02, owner-only measurement): on /resume
 * every beacon of a view carries `lane`, the lane that showed when the view
 * began, and only 'music' or 'operations'. Runs the real beacon under a fake
 * DOM. The pageview payload is pinned in
 * tests/js/fixtures/beacon-payload-resume.json, which the analytics worker's
 * join test parses with the real worker (same file, copied).
 * SN_WRITE_FIXTURE=1 rewrites it.
 */
const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const SRC = fs.readFileSync(path.join(__dirname, '../../assets/js/sn-beacon.js'), 'utf8');
const FIXTURE = path.join(__dirname, 'fixtures/beacon-payload-resume.json');
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

test('the operations lane rides on every beacon of the view', () => {
  const p = page(both('operations'));
  p.scrollTo(2000);
  p.win.SN_BEACON.event('resume_hunk_operations');
  assert.ok(p.sent.length >= 3);
  assert.deepEqual([...new Set(p.sent.map((e) => e.e))].sort(), ['ce', 'pv', 'sc']);
  assert.ok(p.sent.every((e) => e.lane === 'operations'));
});

test('the music lane reads music', () => {
  assert.equal(page(both('music')).sent[0].lane, 'music');
});

test('a page without lanes sends no lane key at all', () => {
  const p = page([], '/notes/x/');
  p.scrollTo(2000);
  assert.ok(p.sent.length >= 2 && p.sent.every((e) => !('lane' in e)));
});

test('only the two known ids leave the browser', () => {
  for (const id of ['resume-other', 'resume-Music', 'xresume-music', 'resume-music-x', '', '<b>']) {
    assert.ok(!('lane' in page([{ id, hidden: false }]).sent[0]), `id ${JSON.stringify(id)} sends no lane`);
  }
});

test('two lanes showing, or none, send no lane', () => {
  assert.ok(!('lane' in page([{ id: 'resume-music', hidden: false }, { id: 'resume-operations', hidden: false }]).sent[0]));
  assert.ok(!('lane' in page([{ id: 'resume-music', hidden: true }, { id: 'resume-operations', hidden: true }]).sent[0]));
});

test('a lane switched in place keeps the view on its first lane; a new view reads again', () => {
  const lanes = both('music');
  const p = page(lanes);
  lanes[0].hidden = true; lanes[1].hidden = false; // resume-switch.js, mid-view
  p.scrollTo(2000);
  assert.ok(p.sent.every((e) => e.lane === 'music'), 'the view keeps the lane its pageview had');
  p.fire('pageshow', { persisted: true }); // a bfcache restore is a new view
  const pvs = p.sent.filter((e) => e.e === 'pv');
  assert.equal(pvs.length, 2);
  assert.equal(pvs[1].lane, 'operations');
});

test('the résumé pageview payload is pinned in the shared fixture', () => {
  const sample = page(both('operations')).sent.find((e) => e.e === 'pv');
  sample.pid = 1; // random per page view; the fixture pins the field, not the draw
  sample.sg.tz = 240; // the host clock's zone is not the fixture's
  if (process.env.SN_WRITE_FIXTURE === '1') fs.writeFileSync(FIXTURE, JSON.stringify(sample, null, 2) + '\n');
  assert.deepEqual(JSON.parse(fs.readFileSync(FIXTURE, 'utf8')), sample);
});
