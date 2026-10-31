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
    select() { this.selected = true; },
  };
}

// The row exactly as sn_note_share_shortcode() prints it.
function page({ clipboard = 'ok', execCommand = true, share = null, url = true, helper = true } = {}) {
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
    // Like a browser, execCommand copies the SELECTION: no select(), nothing copied.
    execCommand: (cmd) => { const ta = body.children[0]; log.exec.push([cmd, ta && ta.value]); return execCommand && !!(ta && ta.selected); },
  };
  const navigator = {};
  const ctl = { clipboard };
  if (clipboard) {
    navigator.clipboard = { writeText: (t) => { log.writes.push(t); return ctl.clipboard === 'ok' ? Promise.resolve() : Promise.reject(new Error('denied')); } };
  }
  if (share) navigator.share = (data) => { log.shares.push({ ...data }); return share === 'ok' ? Promise.resolve() : Promise.reject(new Error('AbortError')); };
  const ctx = {
    window: { document, navigator }, document, navigator,
    setTimeout: (fn, ms) => setTimeout(fn, ms), clearTimeout: (id) => clearTimeout(id),
  };
  // sn-copy.js (the shared clipboard helper, 2026-10-31) first, as the script declares.
  if (helper) vm.runInNewContext(readFileSync(path.join(__dirname, '..', '..', 'assets', 'js', 'sn-copy.js'), 'utf8'), ctx);
  vm.runInNewContext(SRC, ctx);
  const live = row.children.find((c) => c.attrs.role === 'status') || null;
  return { copy, native, row, body, live, log, ctl };
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

test('a failure right after a success drops the COPIED style and restarts the 1.6 s', () => withTimers(async () => {
  const p = page();
  p.copy.listeners.click();
  await flush();
  mock.timers.tick(1000);
  p.ctl.clipboard = 'fail';
  p.copy.listeners.click();
  await flush();
  assert.equal(p.copy.textContent, 'COPY FAILED');
  assert.ok(!p.copy.classList.contains('is-copied'), 'a failure is not drawn as a success');
  mock.timers.tick(1000);
  assert.equal(p.copy.textContent, 'COPY FAILED', 'the failure gets its own 1.6 s');
  mock.timers.tick(600);
  assert.equal(p.copy.textContent, 'COPY LINK');
  assert.ok(!p.copy.classList.contains('is-copied'));
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

// The cite line (inc/note-cite.php) rides the same wiring: each
// .sn-note-cite__copy button copies its data-sn-copy-text, swaps its label the
// same way and announces its own data-sn-copied message in one polite region.
const APA = 'Lentino, J. (2026, October 17). A note (Version 2). Signal & Noise. https://doi.org/10.5281/zenodo.1';
const BIB = '@online{lentino2026note,\n  author = {Lentino, Juan},\n  doi = {10.5281/zenodo.1}\n}';
function citePage({ clipboard = 'ok', helper = true } = {}) {
  const apa = el('BUTTON', { 'data-sn-copy-text': APA, 'data-sn-copied': 'APA citation copied to clipboard' });
  apa.textContent = 'COPY APA';
  const bib = el('BUTTON', { 'data-sn-copy-text': BIB, 'data-sn-copied': 'BibTeX copied to clipboard' });
  bib.textContent = 'COPY BIBTEX';
  const row = el('DIV');
  row.querySelectorAll = (sel) => (sel === '.sn-note-cite__copy' ? [apa, bib] : []);
  const log = { writes: [] };
  const document = {
    readyState: 'complete', body: el('BODY'),
    querySelectorAll: (sel) => (sel === '.sn-note-cite' ? [row] : []),
    createElement: (tag) => el(tag.toUpperCase()),
  };
  const navigator = { clipboard: { writeText: (t) => { log.writes.push(t); return clipboard === 'ok' ? Promise.resolve() : Promise.reject(new Error('denied')); } } };
  const ctx = {
    window: { document, navigator }, document, navigator,
    setTimeout: (fn, ms) => setTimeout(fn, ms), clearTimeout: (id) => clearTimeout(id),
  };
  // sn-copy.js first, as the script declares (#621): the cite line copies through it too.
  if (helper) vm.runInNewContext(readFileSync(path.join(__dirname, '..', '..', 'assets', 'js', 'sn-copy.js'), 'utf8'), ctx);
  vm.runInNewContext(SRC, ctx);
  const lives = row.children.filter((c) => c.attrs.role === 'status');
  return { apa, bib, row, lives, log };
}

test('cite: COPY BIBTEX writes the whole entry, shows COPIED, announces its own message, and reverts', () => withTimers(async () => {
  const p = citePage();
  assert.equal(p.lives.length, 1, 'one polite region for the line, shared by both buttons');
  assert.equal(p.lives[0].attrs['aria-live'], 'polite');
  p.bib.listeners.click();
  await flush();
  assert.deepEqual(p.log.writes, [BIB], 'the multi-line entry, byte for byte');
  assert.equal(p.bib.textContent, 'COPIED');
  assert.ok(p.bib.classList.contains('is-copied'));
  assert.equal(p.lives[0].textContent, 'BibTeX copied to clipboard');
  assert.equal(p.apa.textContent, 'COPY APA', 'the other button is untouched');
  mock.timers.tick(1600);
  assert.equal(p.bib.textContent, 'COPY BIBTEX');
  p.apa.listeners.click();
  await flush();
  assert.deepEqual(p.log.writes, [BIB, APA]);
  assert.equal(p.lives[0].textContent, 'APA citation copied to clipboard');
}));

test('cite: a refused clipboard shows COPY FAILED, drops the copied style, and announces it', () => withTimers(async () => {
  const p = citePage({ clipboard: 'fail' });
  p.apa.listeners.click();
  await flush();
  assert.equal(p.apa.textContent, 'COPY FAILED');
  assert.ok(!p.apa.classList.contains('is-copied'));
  assert.equal(p.lives[0].textContent, 'Copy failed');
  mock.timers.tick(1600);
  assert.equal(p.apa.textContent, 'COPY APA');
}));

test('without the copy helper COPY stays unwired and SHARE still works (no throw)', () => {
  const p = page({ helper: false, share: 'ok' });
  assert.equal(p.copy.listeners.click, undefined);
  assert.equal(p.native.hidden, false);
  p.native.listeners.click();
  assert.equal(p.log.shares.length, 1);
});

test('cite: without the copy helper the buttons stay unwired and nothing throws', () => {
  const p = citePage({ helper: false });
  assert.equal(p.apa.listeners.click, undefined);
  assert.equal(p.lives.length, 0);
});
