// assets/js/footnotes-popover.js shows a footnote's text beside its reference
// on hover and on focus. It had no way to put the card away without moving the
// pointer or the focus (WCAG 1.4.13, dismissible), where the note-link preview
// card (assets/js/note-link-previews.js) already took Escape. Escape now
// dismisses it. Focus stays where it is, which for a keyboard reader IS the
// reference; if focus sits inside the card being removed, it goes back to the
// reference that opened it, and that does not open the card again. The keydown
// listener exists only while a card is open. Drives the REAL file.
const { test } = require('node:test');
const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

const SRC = readFileSync(path.join(__dirname, '..', '..', 'assets', 'js', 'footnotes-popover.js'), 'utf8');

function load() {
  const listeners = {};
  const fire = (type, e) => { for (const cb of [...(listeners[type] || [])]) cb(e); };
  const built = [];
  const body = {
    children: [],
    appendChild(c) { c.parentNode = body; body.children.push(c); },
    removeChild(c) { body.children = body.children.filter((x) => x !== c); c.parentNode = null; },
  };
  const document = {
    readyState: 'complete', body, activeElement: body, documentElement: { clientWidth: 1000 },
    addEventListener: (type, cb) => { (listeners[type] = listeners[type] || []).push(cb); },
    removeEventListener: (type, cb) => { listeners[type] = (listeners[type] || []).filter((x) => x !== cb); },
    getElementById: (id) => (id === 'fn1' ? { childNodes: [] } : null),
    createElement: () => {
      const inner = { name: 'a link inside the card' };
      const pop = { style: {}, parentNode: null, inner, appendChild() {}, addEventListener() {},
        contains: (n) => n === pop || n === inner,
        getBoundingClientRect: () => ({ top: 0, left: 0, bottom: 0, width: 200, height: 80 }) };
      built.push(pop);
      return pop;
    },
  };
  const sup = { closest: (sel) => (sel === 'sup' ? sup : null), hasAttribute: () => false,
    getBoundingClientRect: () => ({ top: 10, left: 10, bottom: 20, width: 8, height: 10 }) };
  const anchor = { closest: sup.closest, getAttribute: () => '#fn1', focusCalls: 0,
    // Like a browser: focus() on an element that does not have it fires focusin.
    focus() { this.focusCalls++; if (document.activeElement !== anchor) { document.activeElement = anchor; fire('focusin', { target: anchor }); } } };
  sup.querySelector = () => anchor;
  const window = { document, innerWidth: 1000, innerHeight: 800, scrollX: 0, scrollY: 0 };
  vm.runInNewContext(SRC, { window, document });
  const key = (k, extra = {}) => fire('keydown', { key: k, keyCode: 0, ...extra });
  const keydowns = () => (listeners.keydown || []).length;
  return { document, body, built, sup, anchor, fire, key, keydowns };
}

test('Escape dismisses a card opened by focus, and focus stays on the reference', () => {
  const p = load();
  assert.equal(p.keydowns(), 0, 'no keydown listener before a card is open');
  p.anchor.focus();
  assert.equal(p.body.children.length, 1, 'the card is open');
  assert.equal(p.keydowns(), 1, 'one listener while it is');
  p.key('a');
  assert.equal(p.body.children.length, 1, 'another key leaves it');
  p.key('Escape');
  assert.equal(p.body.children.length, 0, 'Escape puts it away');
  assert.equal(p.document.activeElement, p.anchor, 'focus is on the footnote reference');
  assert.equal(p.built.length, 1, 'and the card was not built again');
  assert.equal(p.keydowns(), 0, 'the listener goes with the card');
  assert.doesNotThrow(() => p.key('Escape'));
});

test('a card opened by hover: Escape dismisses it and does not move focus', () => {
  const p = load();
  p.fire('pointerenter', { target: p.sup });
  assert.equal(p.body.children.length, 1);
  p.key('Escape');
  assert.equal(p.body.children.length, 0);
  assert.equal(p.document.activeElement, p.body, 'focus was not on the reference, and is not taken there');
  assert.equal(p.anchor.focusCalls, 0);
});

test('focus inside the card: Escape returns it to the reference that opened it, without opening the card again', () => {
  const p = load();
  p.fire('pointerenter', { target: p.sup });
  p.document.activeElement = p.built[0].inner;
  p.key('Escape');
  assert.equal(p.document.activeElement, p.anchor);
  assert.equal(p.body.children.length, 0, 'the returning focus does not reopen it');
  assert.equal(p.built.length, 1);
  assert.equal(p.keydowns(), 0);
});

test('Escape during IME composition belongs to the IME', () => {
  for (const flags of [{ isComposing: true }, { keyCode: 229 }]) {
    const p = load();
    p.anchor.focus();
    p.key('Escape', flags);
    assert.equal(p.body.children.length, 1, JSON.stringify(flags));
  }
});

test('the listener also goes when the card closes by blur or by the pointer leaving', () => {
  const p = load();
  p.anchor.focus();
  p.fire('focusout', { target: p.anchor });
  assert.equal(p.body.children.length, 0);
  assert.equal(p.keydowns(), 0, 'blur');
  p.fire('pointerenter', { target: p.sup });
  assert.equal(p.keydowns(), 1);
  p.fire('pointerleave', { target: p.sup, relatedTarget: null });
  assert.equal(p.keydowns(), 0, 'pointerleave');
  p.fire('pointerenter', { target: p.sup });
  p.fire('pointerenter', { target: p.sup });
  assert.equal(p.keydowns(), 1, 'reopening over an open card never stacks listeners');
});
