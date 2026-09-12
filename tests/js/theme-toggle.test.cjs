const { test } = require('node:test');
const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const source = readFileSync(path.join(__dirname, '../../assets/js/dark-mode-toggle.js'), 'utf8');

function fixture({ stored = null, dark = false, readFails = false, writeFails = false } = {}) {
  const attributes = {};
  const buttons = [0, 1].map(() => ({
    attributes: {}, label: { textContent: '', getAttribute() { return null; } },
    querySelector() { return this.label; },
    setAttribute(key, value) { this.attributes[key] = value; },
    addEventListener(type, fn) { this[type] = fn; },
  }));
  const mq = { matches: dark, addEventListener(type, fn) { this[type] = fn; } };
  vm.runInNewContext(source, {
    document: { documentElement: { setAttribute(k, v) { attributes[k] = v; } }, querySelectorAll(sel) { return sel === '.sn-theme-toggle' ? buttons : []; } },
    window: { matchMedia(query) { return query.includes('color-scheme') ? mq : { matches: true }; } },
    localStorage: {
      getItem() { if (readFails) throw new Error('blocked'); return stored; },
      setItem(k, v) { if (writeFails) throw new Error('full'); stored = v; },
      removeItem() { stored = null; },
    },
  });
  return { buttons, mq, attributes };
}

for (const scenario of [
  { readFails: true, writeFails: true },
  { stored: 'light', writeFails: true },
  { stored: 'dark', dark: true, writeFails: true },
  {},
]) {
  test(`both toggles remain usable with ${JSON.stringify(scenario)}`, () => {
    const f = fixture(scenario);
    const initial = f.buttons[0].label.textContent;
    f.buttons[0].click();
    const next = initial === 'Light' ? 'Dark' : 'Light';
    for (const b of f.buttons) {
      assert.equal(b.label.textContent, next);
      assert.equal(b.attributes['aria-pressed'], next === 'Dark' ? 'true' : 'false');
      assert.equal(b.attributes['aria-label'], next === 'Dark' ? 'Switch to light theme' : 'Switch to dark theme');
    }
    assert.equal(f.attributes['data-theme'], next.toLowerCase());
    f.mq.matches = !f.mq.matches;
    f.mq.change();
    assert.equal(f.buttons[0].label.textContent, next, 'OS changes cannot undo the page choice');
    f.buttons[1].click();
    assert.equal(f.buttons[0].label.textContent, initial);
    assert.equal(f.attributes['data-theme'], initial.toLowerCase());
  });
}
test('without a choice both labels follow OS changes', () => {
  const f = fixture();
  f.mq.matches = true;
  f.mq.change();
  assert.equal(f.buttons[0].label.textContent, 'Dark');
  assert.equal(f.buttons[1].label.textContent, 'Dark');
  assert.equal(f.attributes['data-theme'], undefined);
});
