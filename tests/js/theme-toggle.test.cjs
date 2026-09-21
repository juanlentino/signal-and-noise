/**
 * The theme toggle store (assets/js/dark-mode-toggle.js), run for real under
 * node against a stub `@wordpress/interactivity` (tests/js/lib/). Each
 * scenario imports the module afresh (cache-busted URL) with its own
 * localStorage / matchMedia / document, calls the store's init and toggle
 * the way the runtime would from `data-wp-init` and `data-wp-on--click`, and
 * reads the state the directives bind. The DOM side of those directives is
 * core's, pinned in tests/theme-toggle-directives.php.
 */
const { test } = require('node:test');
const assert = require('node:assert/strict');
const nodeModule = require('node:module');
const { pathToFileURL } = require('node:url');
const path = require('node:path');

// Resolve `@wordpress/interactivity` to the stub. registerHooks (sync) is the
// current API; register() is the older one CI's runner Node may still be on.
if (typeof nodeModule.registerHooks === 'function') {
  const stubUrl = pathToFileURL(path.join(__dirname, 'lib/interactivity-stub.mjs')).href;
  nodeModule.registerHooks({ resolve(specifier, context, next) {
    return specifier === '@wordpress/interactivity' ? { url: stubUrl, shortCircuit: true } : next(specifier, context);
  } });
} else {
  nodeModule.register('./lib/interactivity-loader.mjs', pathToFileURL(__filename));
}
const MODULE = pathToFileURL(path.join(__dirname, '../../assets/js/dark-mode-toggle.js')).href;
let n = 0;

async function fixture({ stored = null, dark = false, readFails = false, writeFails = false } = {}) {
  const attributes = {};
  const metas = [{ content: '#ffffff' }, { content: '#0a0a0a' }].map((m) => ({ attributes: m, setAttribute(k, v) { this.attributes[k] = v; } }));
  const mq = { matches: dark, addEventListener(type, fn) { this[type] = fn; } };
  global.document = {
    documentElement: { setAttribute(k, v) { attributes[k] = v; } },
    querySelectorAll(sel) { return sel === 'meta[name="theme-color"]' ? metas : []; },
  };
  global.window = { matchMedia(query) { return query.includes('color-scheme') ? mq : { matches: true }; } };
  global.localStorage = {
    getItem() { if (readFails) throw new Error('blocked'); return stored; },
    setItem(k, v) { if (writeFails) throw new Error('full'); stored = v; },
    removeItem() { stored = null; },
  };
  const stub = await import('./lib/interactivity-stub.mjs');
  stub.__reset();
  // What inc/dark-mode.php serialises for the runtime.
  stub.__setServerState('signal-noise/theme-toggle', {
    isDark: false, ready: false, label: 'Light', ariaLabel: 'Switch to dark theme',
    labels: { light: 'Light', dark: 'Dark' }, names: { toDark: 'Switch to dark theme', toLight: 'Switch to light theme' },
  });
  await import(`${MODULE}?scenario=${n++}`);
  const s = stub.store('signal-noise/theme-toggle');
  // Two instances, one store: the runtime calls init once per element.
  s.callbacks.init(); s.callbacks.init();
  return { s, mq, attributes, metas, get stored() { return stored; } };
}

for (const scenario of [
  { readFails: true, writeFails: true },
  { stored: 'light', writeFails: true },
  { stored: 'dark', dark: true, writeFails: true },
  {},
]) {
  test(`the toggle remains usable with ${JSON.stringify(scenario)}`, async () => {
    const f = await fixture(scenario);
    assert.equal(f.s.state.ready, true, 'init reveals the button');
    const initial = f.s.state.label;
    f.s.actions.toggle();
    const next = initial === 'Light' ? 'Dark' : 'Light';
    assert.equal(f.s.state.label, next);
    assert.equal(f.s.state.isDark, next === 'Dark');
    assert.equal(f.s.state.ariaLabel, next === 'Dark' ? 'Switch to light theme' : 'Switch to dark theme');
    assert.equal(f.attributes['data-theme'], next.toLowerCase());
    assert.equal(f.metas[0].attributes.content, next === 'Dark' ? '#0a0a0a' : '#ffffff', 'both theme-color metas follow the choice');
    assert.equal(f.metas[1].attributes.content, next === 'Dark' ? '#0a0a0a' : '#ffffff');
    f.mq.matches = !f.mq.matches;
    f.mq.change();
    assert.equal(f.s.state.label, next, 'OS changes cannot undo the page choice');
    f.s.actions.toggle();
    assert.equal(f.s.state.label, initial);
    assert.equal(f.attributes['data-theme'], initial.toLowerCase());
  });
}

test('without a choice the label follows OS changes', async () => {
  const f = await fixture();
  assert.equal(f.s.state.label, 'Light');
  f.mq.matches = true;
  f.mq.change();
  assert.equal(f.s.state.label, 'Dark');
  assert.equal(f.s.state.isDark, true);
  assert.equal(f.metas[0].attributes.content, '#0a0a0a', 'chrome follows the OS too while nothing is chosen');
  assert.equal(f.attributes['data-theme'], undefined, 'following the OS writes no data-theme');
});

test('a choice is persisted and read back', async () => {
  const f = await fixture();
  f.s.actions.toggle();
  assert.equal(f.stored, 'dark');
  const g = await fixture({ stored: 'dark' });
  assert.equal(g.s.state.isDark, true, 'a stored dark choice is dark on a light OS');
});
