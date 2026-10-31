// WCAG 2.1.4 Character Key Shortcuts (theme audit P2-3, 2026.10): the
// single-key shortcuts (j, k, ? on notes; / site-wide) can be turned off, the
// choice persists in localStorage, and every handler honors it. They stay on
// by default. The way back when they are off is the palette (Cmd/Ctrl-K or
// the visible Search button), which is reachable without a single-key
// shortcut on every page.
const { test } = require('node:test');
const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

const KEY = 'sn-single-key-shortcuts';

function fixture(file, store, opts = {}) {
  const nodes = [];
  let document;
  function node(tagName) {
    const el = { tagName: tagName.toUpperCase(), children: [], attributes: {}, handlers: {},
      classList: { add() {}, remove() {} },
      appendChild(child) { this.children.push(child); child.parentNode = this; },
      setAttribute(k, v) { this.attributes[k] = String(v); },
      getAttribute(k) { return k in this.attributes ? this.attributes[k] : null; },
      removeAttribute(k) { delete this.attributes[k]; },
      addEventListener(type, fn) { (this.handlers[type] ||= []).push(fn); },
      focus() { document.activeElement = this; }, scrollIntoView() {},
      open: false, showModal() { this.open = true; }, close() { this.open = false; }, // <dialog> (2026-10-31)
    };
    nodes.push(el);
    return el;
  }
  const trigger = node('button');
  const handlers = [];
  const navigations = [];
  document = { readyState: 'complete', body: node('body'), createElement: node,
    querySelector(sel) { return sel === '.sn-cmdk-trigger' ? trigger : { href: '/next', getAttribute() { return '/next'; } }; },
    addEventListener(type, fn) { if (type === 'keydown') handlers.push(fn); },
    removeEventListener(type, fn) { const i = handlers.indexOf(fn); if (i >= 0) handlers.splice(i, 1); },
  };
  document.activeElement = document.body;
  const localStorage = opts.throwing
    ? { getItem() { throw new Error('denied'); }, setItem() { throw new Error('denied'); }, removeItem() { throw new Error('denied'); } }
    : { getItem(k) { return k in store ? store[k] : null; }, setItem(k, v) { store[k] = String(v); }, removeItem(k) { delete store[k]; } };
  // sn-keys.js (the shared helper, 2026-10-31) loads first, as its dependents
  // declare; the page's scripts share its one window.snKeys.
  const ctx = { document, window: { localStorage, setTimeout(fn) { fn(); }, location: { assign(url) { navigations.push(url); } } } };
  vm.runInNewContext(readFileSync(path.join(__dirname, '../../assets/js/sn-keys.js'), 'utf8'), ctx);
  if (opts.preOff) ctx.window.snKeys.setSingleKeys(false);
  vm.runInNewContext(readFileSync(path.join(__dirname, '../../assets/js', file), 'utf8'), ctx);
  function key(k, extra = {}) {
    const event = { key: k, keyCode: 0, prevented: false, preventDefault() { this.prevented = true; }, ...extra };
    for (const fn of [...handlers]) fn(event);
    return event;
  }
  return { nodes, trigger, key, navigations };
}

const dialog = (f, id) => f.nodes.find(n => (id ? n.id === id : n.className === 'sn-kbdn'));

test('on by default: j navigates, ? opens the sheet, / opens the palette', () => {
  const nav = fixture('keyboard-nav.js', {});
  nav.key('j');
  assert.deepEqual(nav.navigations, ['/next']);
  nav.key('?');
  assert.equal(dialog(nav).open, true);
  const pal = fixture('command-palette.js', {});
  assert.equal(pal.key('/').prevented, true);
  assert.equal(dialog(pal, 'sn-cmdk').open, true);
});

test('off: j, k and ? do nothing on a note, and / does nothing anywhere', () => {
  const nav = fixture('keyboard-nav.js', { [KEY]: 'off' });
  const before = nav.nodes.length;
  for (const k of ['j', 'k', '?']) assert.equal(nav.key(k).prevented, false, k);
  assert.deepEqual(nav.navigations, []);
  assert.equal(nav.nodes.length, before, 'the sheet is not even built');
  const pal = fixture('command-palette.js', { [KEY]: 'off' });
  assert.equal(pal.key('/').prevented, false);
  assert.equal(dialog(pal, 'sn-cmdk'), undefined, 'the palette is not built by /');
});

test('off: Cmd/Ctrl-K still opens the palette (a modifier chord is not a single-key shortcut)', () => {
  const pal = fixture('command-palette.js', { [KEY]: 'off' });
  assert.equal(pal.key('k', { metaKey: true }).prevented, true);
  assert.equal(dialog(pal, 'sn-cmdk').open, true);
});

test('the ? sheet carries the switch, checked by default; unchecking it turns them off', () => {
  const store = {};
  const nav = fixture('keyboard-nav.js', store);
  nav.key('?');
  const box = nav.nodes.find(n => n.tagName === 'INPUT' && n.type === 'checkbox');
  assert.ok(box, 'a checkbox is in the sheet');
  assert.equal(box.checked, true);
  box.checked = false;
  box.handlers.change[0]();
  assert.equal(store[KEY], 'off');
  nav.key('Escape');
  nav.key('j');
  assert.deepEqual(nav.navigations, [], 'j is dead after switching off');
});

test('the palette offers the switch in both states, and it flips the stored choice', () => {
  const store = {};
  const pal = fixture('command-palette.js', store);
  pal.trigger.handlers.click[0]();
  const list = pal.nodes.find(n => n.id === 'sn-cmdk-list');
  const row = () => list.children.find(li => /single-key shortcuts/i.test(li.children[0].textContent));
  assert.match(row().children[0].textContent, /^Turn off single-key shortcuts/);
  list.handlers.click[0]({ target: row() });
  assert.equal(store[KEY], 'off');
  assert.deepEqual(pal.navigations, [], 'toggling never navigates');
  list.children.length = 0; // the stub's textContent setter does not clear
  pal.nodes.find(n => n.tagName === 'INPUT').handlers.input[0]();
  assert.match(row().children[0].textContent, /^Turn on single-key shortcuts/);
  list.handlers.click[0]({ target: row() });
  assert.equal(store[KEY], undefined, 'on is the default, so turning on clears the key');
});

test('blocked storage fails safe: shortcuts stay on and nothing throws', () => {
  const nav = fixture('keyboard-nav.js', {}, { throwing: true });
  nav.key('j');
  assert.deepEqual(nav.navigations, ['/next']);
  const pal = fixture('command-palette.js', {}, { throwing: true });
  assert.equal(pal.key('/').prevented, true);
});

test('one switch for both scripts: off through the shared helper stops j even when storage throws', () => {
  const nav = fixture('keyboard-nav.js', {}, { throwing: true, preOff: true });
  nav.key('j');
  assert.deepEqual(nav.navigations, [], 'the notes script reads the same in-page memo the palette writes');
});
