'use strict';
// A page cached before the switch to wp.a11y.speak() (2026-11-10) can still run
// the old note-share.js and article-toc.js, which call snCopy.liveRegion() and
// write to what it returns. The helper keeps a harmless liveRegion for one
// release, so those buttons keep working instead of throwing.
const { test } = require('node:test');
const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

test('snCopy.liveRegion stays callable for stale callers and returns a writable element', () => {
  const made = [];
  const document = { createElement: (tag) => { const el = { tagName: tag, textContent: '', setAttribute() {}, style: {} }; made.push(el); return el; } };
  const ctx = { window: {}, document };
  ctx.window.document = document;
  vm.runInNewContext(readFileSync(path.join(__dirname, '..', '..', 'assets', 'js', 'sn-copy.js'), 'utf8'), ctx);
  const snCopy = ctx.window.snCopy;
  assert.equal(typeof snCopy.liveRegion, 'function');
  const live = snCopy.liveRegion({ appendChild() { throw new Error('must not attach'); } }, 'sn-share-live');
  live.textContent = 'Copied';
  assert.equal(live.textContent, 'Copied');
  assert.equal(made.length, 1);
});
