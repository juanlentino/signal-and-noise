'use strict';
// snCopy.liveRegion() was kept for one release after the switch to
// wp.a11y.speak() (2026-11-10) so pages cached with the old callers kept
// working. Those caches have aged out; the shim is gone (2026-11-12).
const { test } = require('node:test');
const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

test('snCopy has no liveRegion shim, and its copy helper is still there', () => {
  const document = { createElement: () => ({ style: {}, setAttribute() {} }) };
  const ctx = { window: {}, document };
  ctx.window.document = document;
  vm.runInNewContext(readFileSync(path.join(__dirname, '..', '..', 'assets', 'js', 'sn-copy.js'), 'utf8'), ctx);
  assert.equal(ctx.window.snCopy.liveRegion, undefined);
  assert.equal(typeof ctx.window.snCopy.text, 'function');
});
