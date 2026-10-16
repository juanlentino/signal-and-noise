/**
 * The beacon and the command palette read their data from JSON data islands
 * (<script type="application/json" id="...">, 2026-10-16 CSP prep), which a
 * script-src without 'unsafe-inline' does not block. For one release each
 * still falls back to the old window global, for pages cached before then.
 * The beacon also keeps window.SN_BEACON as its public event() API.
 */
const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const JS = (f) => fs.readFileSync(path.join(__dirname, '../../assets/js', f), 'utf8');

function runBeacon(islands, globalCfg) {
  const sent = [];
  const doc = {
    addEventListener() {}, prerendering: false, visibilityState: 'visible', referrer: '', cookie: '',
    documentElement: { scrollHeight: 0 }, body: { scrollHeight: 0 },
    getElementById(id) { return id in islands ? { textContent: islands[id] } : null; },
  };
  const win = { addEventListener() {}, removeEventListener() {}, location: { pathname: '/', search: '', hostname: 'x' }, innerHeight: 0, scrollY: 0 };
  if (globalCfg) win.SN_BEACON = globalCfg;
  vm.runInNewContext(JS('sn-beacon.js'), {
    window: win, document: doc, location: win.location, URLSearchParams, URL, Blob, JSON, Object, Math, Date, setTimeout, clearTimeout,
    navigator: { userAgent: '', sendBeacon(url) { sent.push(url); return true; } },
    addEventListener() {}, PerformanceObserver: undefined, performance: { now: () => 0 },
  });
  return { sent, win };
}

test('beacon: reads the #sn-beacon-data island and sends to its endpoint', () => {
  const r = runBeacon({ 'sn-beacon-data': '{"endpoint":"/_sn/island","k":"k","id":7}' });
  assert.ok(r.sent.length > 0);
  assert.equal(r.sent[0], '/_sn/island');
});

test('beacon: exposes the parsed config as window.SN_BEACON with event()', () => {
  const r = runBeacon({ 'sn-beacon-data': '{"endpoint":"/_sn/px","k":"k","id":7}' });
  assert.equal(r.win.SN_BEACON.id, 7);
  assert.equal(typeof r.win.SN_BEACON.event, 'function');
});

test('beacon: the island wins over a stale global', () => {
  const r = runBeacon({ 'sn-beacon-data': '{"endpoint":"/_sn/island","k":"k"}' }, { endpoint: '/_sn/global', k: 'k' });
  assert.equal(r.sent[0], '/_sn/island');
});

test('beacon: falls back to window.SN_BEACON on a cached page with no island', () => {
  const r = runBeacon({}, { endpoint: '/_sn/global', k: 'k' });
  assert.equal(r.sent[0], '/_sn/global');
});

test('beacon: a malformed island falls back to the global, and with neither sends nothing', () => {
  assert.equal(runBeacon({ 'sn-beacon-data': '{oops' }, { endpoint: '/_sn/global', k: 'k' }).sent[0], '/_sn/global');
  assert.equal(runBeacon({ 'sn-beacon-data': '{oops' }).sent.length, 0);
});

function openPalette(islands, globalData) {
  const nodes = [];
  let document;
  function node(tagName) {
    const el = { tagName: tagName.toUpperCase(), children: [], attributes: {}, handlers: {}, textContent: '',
      classList: { add() {}, remove() {} },
      appendChild(child) { this.children.push(child); child.parentNode = this; },
      setAttribute(k, v) { this.attributes[k] = v; }, getAttribute(k) { return this.attributes[k] || null; },
      removeAttribute(k) { delete this.attributes[k]; },
      addEventListener(type, fn) { (this.handlers[type] ||= []).push(fn); },
      focus() { document.activeElement = this; }, scrollIntoView() {},
    };
    nodes.push(el);
    return el;
  }
  const trigger = node('button');
  document = { readyState: 'complete', body: node('body'), createElement: node,
    querySelector(sel) { return sel === '.sn-cmdk-trigger' ? trigger : null; },
    getElementById(id) { return id in islands ? { textContent: islands[id] } : null; },
    addEventListener() {}, removeEventListener() {},
  };
  document.activeElement = document.body;
  const win = { setTimeout(fn) { fn(); }, location: { assign() {} } };
  if (globalData) win.SN_CMDK = globalData;
  vm.runInNewContext(JS('command-palette.js'), { document, window: win });
  trigger.handlers.click[0]();
  return nodes.map((n) => n.textContent).join('|');
}

const recent = (t) => ({ notesUrl: '/notes/', recent: [{ t, u: '/notes/x/' }], pillars: [], notes: [] });

test('palette: lists recent notes from the #sn-cmdk-data island', () => {
  assert.match(openPalette({ 'sn-cmdk-data': JSON.stringify(recent('From the island')) }), /From the island/);
});

test('palette: the island wins over a stale global', () => {
  const text = openPalette({ 'sn-cmdk-data': JSON.stringify(recent('From the island')) }, recent('From the global'));
  assert.match(text, /From the island/);
  assert.doesNotMatch(text, /From the global/);
});

test('palette: falls back to window.SN_CMDK on a cached page with no island', () => {
  assert.match(openPalette({}, recent('From the global')), /From the global/);
});

test('palette: a javascript: URL from the island is never navigated to', () => {
  const navigations = [];
  const nodes = [];
  let document;
  function node(tagName) {
    const el = { tagName: tagName.toUpperCase(), children: [], attributes: {}, handlers: {}, textContent: '',
      classList: { add() {}, remove() {} },
      appendChild(child) { this.children.push(child); child.parentNode = this; },
      setAttribute(k, v) { this.attributes[k] = v; }, getAttribute(k) { return this.attributes[k] || null; },
      removeAttribute(k) { delete this.attributes[k]; },
      addEventListener(type, fn) { (this.handlers[type] ||= []).push(fn); },
      focus() { document.activeElement = this; }, scrollIntoView() {},
    };
    nodes.push(el);
    return el;
  }
  const trigger = node('button');
  const keys = [];
  const bad = { notesUrl: 'javascript:alert(1)//', recent: [{ t: 'Evil', u: 'javascript:alert(2)' }, { t: 'Slash', u: '/\\evil.example' }, { t: 'Proto', u: '//evil.example/x' }, { t: 'Mixed', u: '/a\\b' }], pillars: [], notes: [] };
  document = { readyState: 'complete', body: node('body'), createElement: node,
    querySelector(sel) { return sel === '.sn-cmdk-trigger' ? trigger : null; },
    getElementById(id) { return id === 'sn-cmdk-data' ? { textContent: JSON.stringify(bad) } : null; },
    addEventListener(type, fn) { if (type === 'keydown') keys.push(fn); }, removeEventListener() {},
  };
  document.activeElement = document.body;
  vm.runInNewContext(JS('command-palette.js'), { document, window: { setTimeout(fn) { fn(); }, location: { assign(u) { navigations.push(u); } } } });
  trigger.handlers.click[0]();
  const input = nodes.find((n) => n.tagName === 'INPUT');
  const press = (key) => { const e = { key, keyCode: 0, preventDefault() {} }; for (const fn of input.handlers.keydown || []) fn(e); };
  for (let i = 0; i < 6; i++) { press('Enter'); press('ArrowDown'); }
  assert.ok(navigations.every((u) => !/^\s*javascript:/i.test(u)), JSON.stringify(navigations));
  assert.ok(navigations.every((u) => /^\/(?![\/\\])/.test(u) && !u.includes('\\')), JSON.stringify(navigations));
});
