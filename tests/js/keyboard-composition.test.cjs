const { test } = require('node:test');
const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

function fixture(file) {
  const nodes = [];
  let document;
  function node(tagName) {
    const el = { tagName: tagName.toUpperCase(), children: [], attributes: {}, handlers: {},
      classList: { add() {}, remove() {} },
      appendChild(child) { this.children.push(child); child.parentNode = this; },
      setAttribute(k, v) { this.attributes[k] = v; },
      getAttribute(k) { return this.attributes[k] || null; },
      removeAttribute(k) { delete this.attributes[k]; },
      addEventListener(type, fn) { (this.handlers[type] ||= []).push(fn); },
      focus() { document.activeElement = this; }, scrollIntoView() {},
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
  vm.runInNewContext(readFileSync(path.join(__dirname, '../../assets/js', file), 'utf8'), {
    document, window: { setTimeout(fn) { fn(); }, location: { assign(url) { navigations.push(url); } } },
  });
  function key(key, extra = {}, target) {
    const event = { key, keyCode: 0, prevented: false, preventDefault() { this.prevented = true; }, ...extra };
    for (const fn of [...handlers]) fn(event);
    if (target) for (const fn of target.handlers.keydown || []) fn(event);
    return event;
  }
  return { nodes, trigger, key, navigations };
}

for (const flags of [{ isComposing: true }, { keyCode: 229 }]) {
  test(`composition cannot open shortcuts or navigate: ${JSON.stringify(flags)}`, () => {
    for (const file of ['command-palette.js', 'keyboard-nav.js']) {
      const f = fixture(file);
      const initial = f.nodes.length;
      for (const k of ['/', '?', 'j', 'k']) assert.equal(f.key(k, flags).prevented, false);
      assert.equal(f.nodes.length, initial);
      assert.deepEqual(f.navigations, []);
    }
  });
  test(`composition cannot select or close a search result: ${JSON.stringify(flags)}`, () => {
    const f = fixture('command-palette.js');
    f.trigger.handlers.click[0]();
    const input = f.nodes.find(n => n.tagName === 'INPUT');
    const dialog = f.nodes.find(n => n.id === 'sn-cmdk');
    for (const k of ['Enter', 'Escape', 'ArrowDown', 'ArrowUp']) {
      assert.equal(f.key(k, flags, input).prevented, false);
      assert.equal(dialog.hidden, false);
    }
    assert.deepEqual(f.navigations, []);
    f.key('Escape');
    assert.equal(dialog.hidden, true, 'ordinary Escape still closes');
  });
}
