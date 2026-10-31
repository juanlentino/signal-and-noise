// assets/js/note-share.js wires the [sn_note_share] row (inc/post-share.php:
// a visible COPY LINK button and a SHARE button that ships `hidden`, each
// carrying data-sn-share-url and data-sn-share-title). Pinned: COPY writes the
// copy permalink to the clipboard (or the textarea + execCommand fallback),
// swaps its label to COPIED or COPY FAILED and back after 1.6 s, and says so
// in a polite live region, since a label swap on the focused button is not
// reliably announced. SHARE is revealed and calls navigator.share only where
// the Web Share API exists; elsewhere it stays hidden. Drives the REAL file.
const { test, mock } = require('node:test');
const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

const SRC = readFileSync(path.join(__dirname, '..', '..', 'assets', 'js', 'note-share.js'), 'utf8');
const COPY_URL = 'https://juanlentino.com/notes/a-note/?utm_source=share&utm_medium=copy';
const NATIVE_URL = 'https://juanlentino.com/notes/a-note/?utm_source=share&utm_medium=native';

function el(tag, attrs = {}) {
  const classes = new Set();
  return {
    tagName: tag, attrs: { ...attrs }, children: [], style: {}, listeners: {}, hidden: false, textContent: '',
    classList: { add: (c) => classes.add(c), remove: (c) => classes.delete(c), contains: (c) => classes.has(c) },
    getAttribute(k) { return k in this.attrs ? this.attrs[k] : null; },
    setAttribute(k, v) { this.attrs[k] = String(v); },
    appendChild(c) { this.children.push(c); return c; },
    removeChild(c) { this.children = this.children.filter((x) => x !== c); },
    addEventListener(type, cb) { this.listeners[type] = cb; },
    select() {},
  };
}

// The row exactly as sn_note_share_shortcode() prints it.
function page({ clipboard = 'ok', execCommand = true, share = null, url = true } = {}) {
  const copy = el('BUTTON', url ? { 'data-sn-share-url': COPY_URL, 'data-sn-share-title': 'A note' } : {});
  copy.textContent = 'COPY LINK';
  const native = el('BUTTON', { 'data-sn-share-url': NATIVE_URL, 'data-sn-share-title': 'A note' });
  native.textContent = 'SHARE';
  native.hidden = true;
  const row = el('DIV');
  row.querySelector = (sel) => ({ '.sn-note-share__copy': copy, '.sn-note-share__native': native })[sel] || null;
  const body = el('BODY');
  const log = { writes: [], shares: [], exec: [] };
  const document = {
    readyState: 'complete', body,
    querySelectorAll: (sel) => (sel === '.sn-note-share' ? [row] : []),
    createElement: (tag) => el(tag.toUpperCase()),
    execCommand: (cmd) => { log.exec.push([cmd, body.children[0] && body.children[0].value]); return execCommand; },
  };
  const navigator = {};
  if (clipboard) {
    navigator.clipboard = { writeText: (t) => { log.writes.push(t); return clipboard === 'ok' ? Promise.resolve() : Promise.reject(new Error('denied')); } };
  }
  if (share) navigator.share = (data) => { log.shares.push({ ...data }); return share === 'ok' ? Promise.resolve() : Promise.reject(new Error('AbortError')); };
  vm.runInNewContext(SRC, {
    window: { document, navigator }, document, navigator,
    setTimeout: (fn, ms) => setTimeout(fn, ms), clearTimeout: (id) => clearTimeout(id),
  });
  const live = row.children.find((c) => c.attrs.role === 'status') || null;
  return { copy, native, row, body, live, log };
}

const flush = () => new Promise((r) => setImmediate(r));
async function withTimers(fn) {
  mock.timers.enable({ apis: ['setTimeout'] });
  try { await fn(); } finally { mock.timers.reset(); }
}

test('COPY writes the permalink, shows COPIED, announces it politely, and reverts after 1.6 s', () => withTimers(async () => {
  const p = page();
  assert.equal(p.live.attrs['aria-live'], 'polite');
  assert.equal(p.live.textContent, '', 'silent until something happens');
  p.copy.listeners.click();
  await flush();
  assert.deepEqual(p.log.writes, [COPY_URL]);
  assert.equal(p.copy.textContent, 'COPIED');
  assert.ok(p.copy.classList.contains('is-copied'));
  assert.equal(p.live.textContent, 'Link copied to clipboard');
  mock.timers.tick(1599);
  assert.equal(p.copy.textContent, 'COPIED', 'not before the 1.6 s');
  mock.timers.tick(1);
  assert.equal(p.copy.textContent, 'COPY LINK');
  assert.ok(!p.copy.classList.contains('is-copied'));
}));

test('a refused clipboard shows COPY FAILED, announces it, and reverts', () => withTimers(async () => {
  const p = page({ clipboard: 'fail' });
  p.copy.listeners.click();
  await flush();
  assert.equal(p.copy.textContent, 'COPY FAILED');
  assert.ok(!p.copy.classList.contains('is-copied'));
  assert.equal(p.live.textContent, 'Copy failed');
  mock.timers.tick(1600);
  assert.equal(p.copy.textContent, 'COPY LINK');
}));

test('without the Clipboard API it copies through a transient textarea', () => withTimers(async () => {
  const ok = page({ clipboard: null });
  ok.copy.listeners.click();
  await flush();
  assert.deepEqual(ok.log.exec, [['copy', COPY_URL]]);
  assert.equal(ok.body.children.length, 0, 'the textarea is removed again');
  assert.equal(ok.copy.textContent, 'COPIED');

  const refused = page({ clipboard: null, execCommand: false });
  refused.copy.listeners.click();
  await flush();
  assert.equal(refused.copy.textContent, 'COPY FAILED');
}));

test('a second copy inside the window restarts the 1.6 s', () => withTimers(async () => {
  const p = page();
  p.copy.listeners.click();
  await flush();
  mock.timers.tick(1000);
  p.copy.listeners.click();
  await flush();
  mock.timers.tick(1000);
  assert.equal(p.copy.textContent, 'COPIED', 'the first timer no longer reverts it');
  mock.timers.tick(600);
  assert.equal(p.copy.textContent, 'COPY LINK');
}));

test('SHARE is revealed where Web Share exists and shares the native permalink', async () => {
  const p = page({ share: 'ok' });
  assert.equal(p.native.hidden, false);
  p.native.listeners.click();
  assert.deepEqual(p.log.shares, [{ title: 'A note', url: NATIVE_URL }]);
  const dismissed = page({ share: 'dismissed' });
  dismissed.native.listeners.click();
  await flush(); // a dismissed sheet is caught, not an unhandled rejection
  assert.equal(dismissed.log.shares.length, 1);
});

test('negative control: no Web Share keeps SHARE hidden and unwired; no URL makes COPY a no-op', async () => {
  const p = page({ url: false });
  assert.equal(p.native.hidden, true);
  assert.equal(p.native.listeners.click, undefined);
  p.copy.listeners.click();
  await flush();
  assert.deepEqual(p.log.writes, []);
  assert.equal(p.copy.textContent, 'COPY LINK');
});
