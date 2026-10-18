// assets/js/frame-focus.js: Tab into the /music featured Spotify player moves
// focus inside a cross-origin iframe, where no CSS pseudo-class reaches the
// <iframe> (measured in Chromium, 2026-10-18). The script marks the player's
// wrapper .is-frame-focus when focus left the page BY KEYBOARD into that
// player, and clears it when focus comes back. Drives the REAL file.
const { test } = require('node:test');
const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

function load() {
  const src = readFileSync(path.join(__dirname, '..', '..', 'assets', 'js', 'frame-focus.js'), 'utf8');
  const on = {};
  const classes = new Set();
  const wrap = { classList: { add: (c) => classes.add(c), remove: (c) => classes.delete(c) } };
  const iframe = { tagName: 'IFRAME', closest: (s) => (s === '.sn-music-featured' ? wrap : null) };
  const document = { activeElement: null, addEventListener: (t, cb) => { on['d:' + t] = cb; }, querySelectorAll: () => (classes.size ? [wrap] : []) };
  const window = { addEventListener: (t, cb) => { on['w:' + t] = cb; } };
  vm.runInNewContext(src, { window, document, setTimeout: (f) => f() });
  return { on, classes, document, iframe };
}

test('Tab into the player rings its wrapper; coming back clears it', () => {
  const { on, classes, document, iframe } = load();
  on['d:keydown']({ key: 'Tab' });
  document.activeElement = iframe;
  on['w:blur']();
  assert.ok(classes.has('is-frame-focus'));
  document.activeElement = null;
  on['w:focus']();
  assert.equal(classes.size, 0);
});

test('negative control: a mouse click into the player draws no ring', () => {
  const { on, classes, document, iframe } = load();
  on['d:keydown']({ key: 'Tab' });
  on['d:pointerdown']({});
  document.activeElement = iframe;
  on['w:blur']();
  assert.equal(classes.size, 0);
});

test('focus leaving to another window (not the player) draws no ring', () => {
  const { on, classes, document } = load();
  on['d:keydown']({ key: 'Tab' });
  document.activeElement = { tagName: 'BODY', closest: () => null };
  on['w:blur']();
  assert.equal(classes.size, 0);
});
