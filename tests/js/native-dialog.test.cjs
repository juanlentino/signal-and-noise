// 2026-10-31: the search palette and the shortcut sheet are native modal
// dialogs. showModal() makes the rest of the page inert, so VoiceOver's
// cursor cannot wander behind the overlay (aria-modal on a div does not stop
// it in Safari). A close the browser makes itself (Escape's cancel, Android
// back) must leave each script closed and return focus, like its own close.
// Drives the REAL files under a fake DOM.
const { test } = require('node:test');
const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

function fixture(file) {
  const nodes = [];
  let document;
  function node(tagName) {
    const el = { tagName: tagName.toUpperCase(), children: [], attributes: {}, handlers: {}, open: false, modalCalls: 0,
      classList: { add() {}, remove() {} },
      appendChild(child) { this.children.push(child); child.parentNode = this; },
      setAttribute(k, v) { this.attributes[k] = v; },
      getAttribute(k) { return this.attributes[k] || null; },
      removeAttribute(k) { delete this.attributes[k]; },
      addEventListener(type, fn) { (this.handlers[type] ||= []).push(fn); },
      focus() { document.activeElement = this; }, scrollIntoView() {},
    };
    if (el.tagName === 'DIALOG') {
      el.showModal = function () { this.open = true; this.modalCalls++; };
      el.close = function () { if (!this.open) return; this.open = false; for (const fn of this.handlers.close || []) fn({ type: 'close' }); };
    }
    nodes.push(el);
    return el;
  }
  const trigger = node('button');
  const handlers = [];
  document = { readyState: 'complete', body: node('body'), createElement: node,
    querySelector(sel) { return sel === '.sn-cmdk-trigger' ? trigger : null; },
    addEventListener(type, fn) { if (type === 'keydown') handlers.push(fn); },
    removeEventListener(type, fn) { const i = handlers.indexOf(fn); if (i >= 0) handlers.splice(i, 1); },
  };
  document.activeElement = trigger;
  const ctx = { document, window: { setTimeout(fn) { fn(); }, location: { assign() {} } } };
  vm.runInNewContext(readFileSync(path.join(__dirname, '../../assets/js/sn-keys.js'), 'utf8'), ctx);
  vm.runInNewContext(readFileSync(path.join(__dirname, '../../assets/js', file), 'utf8'), ctx);
  const key = (k) => { const e = { key: k, keyCode: 0, preventDefault() { this.prevented = true; } }; for (const fn of [...handlers]) fn(e); return e; };
  const nativeClose = (d) => { d.open = false; for (const fn of d.handlers.close || []) fn({ type: 'close' }); };
  return { nodes, trigger, document, key, nativeClose };
}

for (const [file, cls, openKey] of [['command-palette.js', 'sn-cmdk', '/'], ['keyboard-nav.js', 'sn-kbdn', '?']]) {
  test(`${file}: the overlay is a <dialog> opened with showModal()`, () => {
    const f = fixture(file);
    f.key(openKey);
    const d = f.nodes.find(n => n.className === cls);
    assert.ok(d, 'built on first open');
    assert.equal(d.tagName, 'DIALOG');
    assert.equal(d.open, true);
    assert.equal(d.modalCalls, 1, 'modal, so the page behind is inert');
    assert.equal(d.getAttribute('aria-modal'), null, 'no ARIA stand-in for what the element does');
    assert.equal(d.getAttribute('role'), null);
  });

  test(`${file}: its own close (Escape) closes the dialog and returns focus`, () => {
    const f = fixture(file);
    f.key(openKey);
    const d = f.nodes.find(n => n.className === cls);
    assert.equal(f.key('Escape').prevented, true);
    assert.equal(d.open, false);
    assert.equal(f.document.activeElement, f.trigger);
  });

  test(`${file}: a close the browser makes leaves it closed, focus returned, and it opens again`, () => {
    const f = fixture(file);
    f.key(openKey);
    const d = f.nodes.find(n => n.className === cls);
    f.nativeClose(d);
    assert.equal(f.document.activeElement, f.trigger, 'focus back on the opener');
    assert.equal(f.key('Tab').prevented, undefined, 'the trap let go');
    f.key(openKey);
    assert.equal(d.open, true, 'opens again: the script knew it was closed');
    assert.equal(d.modalCalls, 2);
  });
}
