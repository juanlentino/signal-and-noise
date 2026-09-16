// 13.2.4: the four pointer/focus handlers in assets/js/footnotes-popover.js
// take an event whose target may be the document (pointerenter/pointerleave
// are captured on the document, so the pointer leaving the window targets
// the document itself) or a text node; neither has closest(). Measured
// 2026-09-16 as "Uncaught TypeError: event.target.closest is not a function
// at HTMLDocument.onLeave" on every note. Drives the REAL file.
const { test } = require('node:test');
const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

function load() {
  const src = readFileSync(path.join(__dirname, '..', '..', 'assets', 'js', 'footnotes-popover.js'), 'utf8');
  const listeners = {};
  const document = {
    readyState: 'complete',
    addEventListener: (type, cb) => { listeners[type] = cb; },
    querySelector: () => null, getElementById: () => null,
    createElement: () => ({ style: {}, setAttribute() {}, appendChild() {}, addEventListener() {}, classList: { add() {} } }),
    body: { appendChild() {} }, documentElement: { clientWidth: 1000 },
  };
  const window = { document, innerWidth: 1000, innerHeight: 800, scrollX: 0, scrollY: 0, addEventListener() {} };
  vm.runInNewContext(src, { window, document, setTimeout, clearTimeout, console });
  return { listeners, document };
}

test('the handlers register on the document', () => {
  const { listeners } = load();
  for (const type of ['pointerenter', 'pointerleave', 'focusin', 'focusout']) assert.equal(typeof listeners[type], 'function', type);
});

test('a target with no closest() (the document, a text node, null) does not throw in any handler', () => {
  const { listeners, document } = load();
  for (const [type, target] of [['pointerleave', document], ['pointerenter', document], ['focusout', { nodeType: 3 }], ['focusin', null]]) {
    assert.doesNotThrow(() => listeners[type]({ target, relatedTarget: null }), `${type}`);
  }
});

test('negative control: a real <sup> target still reaches the handler body', () => {
  const { listeners } = load();
  const sup = { closest: (sel) => (sel === 'sup' ? sup : null), querySelector: () => null, getBoundingClientRect: () => ({ top: 0, left: 0, width: 0, height: 0 }) };
  assert.doesNotThrow(() => listeners.pointerleave({ target: sup, relatedTarget: null }));
});
