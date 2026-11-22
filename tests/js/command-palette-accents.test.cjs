// assets/js/command-palette.js ranks note titles by plain token matching. The
// word split was ASCII-only, so "Música" split into "m" and "sica": an accented
// word lost its prefix rank, and a query typed without the accent ("musica")
// matched nothing. Query and titles are now compared with accents folded
// (NFD, combining marks removed, lower case). Matching only: every row still
// shows the title as written. Drives the REAL file under a fake DOM.
const { test } = require('node:test');
const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

const read = (f) => readFileSync(path.join(__dirname, '..', '..', 'assets', 'js', f), 'utf8');
const ISLAND = JSON.stringify({
  notesUrl: '/notes/',
  recent: [],
  pillars: [{ t: 'Música', u: '/musica/' }, { t: 'Services', u: '/services/' }],
  notes: [
    { t: 'Notes on a Summúsico', u: '/notes/sub/' },
    { t: 'La música que queda', u: '/notes/musica/' },
    { t: 'Señal y ruido', u: '/notes/senal/' },
  ],
});

function palette() {
  const nodes = [];
  let document;
  function node(tagName) {
    const el = { tagName: tagName.toUpperCase(), children: [], attributes: {}, handlers: {}, value: '',
      classList: { add() {}, remove() {} },
      appendChild(child) { this.children.push(child); child.parentNode = this; },
      setAttribute(k, v) { this.attributes[k] = v; },
      getAttribute(k) { return k in this.attributes ? this.attributes[k] : null; },
      removeAttribute(k) { delete this.attributes[k]; },
      addEventListener(type, fn) { (this.handlers[type] ||= []).push(fn); },
      focus() { document.activeElement = this; }, scrollIntoView() {},
      open: false, showModal() { this.open = true; }, close() { this.open = false; },
      set textContent(v) { this._text = v; if (v === '') this.children = []; },
      get textContent() { return this._text || ''; },
    };
    nodes.push(el);
    return el;
  }
  const trigger = node('button');
  document = { readyState: 'complete', body: node('body'), createElement: node, documentElement: { lang: 'en-US' },
    getElementById: (id) => (id === 'sn-cmdk-data' ? { textContent: ISLAND } : null),
    querySelector: (sel) => (sel === '.sn-cmdk-trigger' ? trigger : null),
    addEventListener() {}, removeEventListener() {},
  };
  document.activeElement = document.body;
  const ctx = { document, window: { setTimeout(fn) { fn(); }, location: { assign() {}, origin: 'https://x.test' } } };
  vm.runInNewContext(read('sn-keys.js'), ctx);
  vm.runInNewContext(read('command-palette.js'), ctx);
  trigger.handlers.click[0]();
  const input = nodes.find((n) => n.tagName === 'INPUT');
  const list = nodes.find((n) => n.id === 'sn-cmdk-list');
  return (q) => { input.value = q; input.handlers.input[0](); return list.children.map((li) => li.children.map((s) => s.textContent).join(' | ')); };
}

test('"musica" finds "música", in notes and in pillars, and shows the titles as written', () => {
  assert.deepEqual(palette()('musica'), [
    'La música que queda | Note',
    'Música | Pillar',
    'Search notes for “musica” | Enter',
  ]);
});

test('an accented word keeps its prefix rank: "mús" puts the word that starts with it first', () => {
  // "Summúsico" only contains it; "música" starts with it. Corpus order would
  // put the substring match first, so this order is the prefix score at work.
  assert.deepEqual(palette()('mús').slice(0, 2), ['La música que queda | Note', 'Notes on a Summúsico | Note']);
});

test('an accented query finds the same notes as the plain one, and ñ folds too', () => {
  const type = palette();
  assert.deepEqual(type('MÚSICA'), ['La música que queda | Note', 'Música | Pillar', 'Search notes for “MÚSICA” | Enter']);
  assert.equal(type('senal')[0], 'Señal y ruido | Note');
});

test('the search row keeps the query exactly as typed', () => {
  assert.equal(palette()('música').pop(), 'Search notes for “música” | Enter');
});
