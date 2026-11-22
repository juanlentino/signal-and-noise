// assets/js/command-palette.js builds its dialog in the page's language. The
// trigger was already Spanish on a Spanish page (inc/lang-chrome.php: "Buscar")
// while everything it opened was English. The page says its language on
// <html lang> (es-AR on the Spanish pages, from the companion plugin), which
// every cached copy already carries, so the script needs no new markup: English
// is the default and stays byte for byte, Spanish is Argentine (voseo).
// Drives the REAL file under a fake DOM.
const { test } = require('node:test');
const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

const read = (f) => readFileSync(path.join(__dirname, '..', '..', 'assets', 'js', f), 'utf8');
const ISLAND = JSON.stringify({
  notesUrl: '/notes/',
  recent: [{ t: 'A recent note', u: '/notes/recent/' }],
  pillars: [{ t: 'Provenance', u: '/provenance/' }],
  notes: [{ t: 'A recent note', u: '/notes/recent/' }, { t: 'Provenance at scale', u: '/notes/scale/' }],
});

// lang: the <html lang> value, or undefined for a document with no root element.
function palette(lang) {
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
      // The script empties the list with textContent = ''.
      set textContent(v) { this._text = v; if (v === '') this.children = []; },
      get textContent() { return this._text || ''; },
    };
    nodes.push(el);
    return el;
  }
  const trigger = node('button');
  document = { readyState: 'complete', body: node('body'), createElement: node,
    getElementById: (id) => (id === 'sn-cmdk-data' ? { textContent: ISLAND } : null),
    querySelector: (sel) => (sel === '.sn-cmdk-trigger' ? trigger : null),
    addEventListener() {}, removeEventListener() {},
  };
  if (lang !== undefined) document.documentElement = { lang };
  document.activeElement = document.body;
  const ctx = { document, window: { setTimeout(fn) { fn(); }, location: { assign() {}, origin: 'https://x.test' } } };
  vm.runInNewContext(read('sn-keys.js'), ctx);
  vm.runInNewContext(read('command-palette.js'), ctx);
  trigger.handlers.click[0]();
  const dialog = nodes.find((n) => n.id === 'sn-cmdk');
  const input = nodes.find((n) => n.tagName === 'INPUT');
  const list = nodes.find((n) => n.id === 'sn-cmdk-list');
  const rows = () => list.children.map((li) => li.children.map((s) => s.textContent).join(' | '));
  const type = (q) => { input.value = q; input.handlers.input[0](); return rows(); };
  return {
    chrome: [dialog.attributes['aria-label'], input.attributes['aria-label'], input.attributes.placeholder, list.attributes['aria-label']],
    empty: rows(), type,
    toggle: () => { list.handlers.click[0]({ target: list.children[list.children.length - 1] }); return rows().pop(); },
  };
}

const EN = {
  chrome: ['Search and navigate', 'Search notes', 'Search notes, jump to a page…', 'Results'],
  empty: ['Search all notes | Enter', 'Provenance | Pillar', 'A recent note | Note', 'Turn off single-key shortcuts (/, J, K, ?) | Setting'],
  typed: ['Provenance at scale | Note', 'Provenance | Pillar', 'Search notes for “prov” | Enter'],
  off: 'Turn on single-key shortcuts (/, J, K, ?) | Setting',
};
const ES = {
  chrome: ['Buscar y navegar', 'Buscar notas', 'Buscá notas, saltá a una página…', 'Resultados'],
  empty: ['Buscar en todas las notas | Enter', 'Provenance | Pilar', 'A recent note | Nota', 'Desactivar los atajos de una tecla (/, J, K, ?) | Ajuste'],
  typed: ['Provenance at scale | Nota', 'Provenance | Pilar', 'Buscar en las notas “prov” | Enter'],
  off: 'Activar los atajos de una tecla (/, J, K, ?) | Ajuste',
};

function read4(p) { return { chrome: p.chrome, empty: p.empty, typed: p.type('prov'), off: (p.type(''), p.toggle()) }; }

for (const lang of ['es-AR', 'es', 'ES-ar']) {
  test(`<html lang="${lang}">: the dialog, its field, its list and every row it writes are Spanish`, () => {
    assert.deepEqual(read4(palette(lang)), ES);
  });
}

for (const lang of ['en-US', 'en', '', 'et', 'eu', undefined]) {
  test(`<html lang=${JSON.stringify(lang)}>: the palette is the English one, string for string`, () => {
    assert.deepEqual(read4(palette(lang)), EN);
  });
}

test('the Spanish strings carry no em dash, and titles from the data island are never translated', () => {
  const p = read4(palette('es-AR'));
  assert.ok(!JSON.stringify(p).includes(String.fromCharCode(0x2014)));
  assert.ok(p.empty.some((r) => r.startsWith('A recent note')) && p.typed[0].startsWith('Provenance at scale'));
});
