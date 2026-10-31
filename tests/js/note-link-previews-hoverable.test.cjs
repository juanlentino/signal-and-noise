// 2026-10-31: the note-link preview card is hoverable (WCAG 1.4.13). Before,
// pointerleave on the link removed the card at once and the card was
// pointer-events: none, so a reader could not move onto it (to magnify or
// re-read it) without it disappearing. Now leaving the link starts a short
// grace timer, entering the card cancels it, leaving the card starts it again.
// Escape still dismisses at once. Drives the REAL file under a fake DOM.
const { test, mock } = require('node:test');
const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

const SRC = readFileSync(path.join(__dirname, '..', '..', 'assets', 'js', 'note-link-previews.js'), 'utf8');
const CSS = readFileSync(path.join(__dirname, '..', '..', 'assets', 'css', 'article.css'), 'utf8');

function matches(n, sel) {
  if (sel === 'a[data-sn-preview-title]') return n.tagName === 'A' && !!n.attrs['data-sn-preview-title'];
  if (sel.startsWith('.')) return String(n.className).split(' ').includes(sel.slice(1));
  return false;
}

function el(tag) {
  return {
    tagName: tag, className: '', style: {}, children: [], parentNode: null, attrs: {}, nodeType: 1,
    setAttribute(k, v) { this.attrs[k] = v; },
    getAttribute(k) { return this.attrs[k] ?? null; },
    appendChild(c) { c.parentNode = this; this.children.push(c); return c; },
    removeChild(c) { this.children = this.children.filter((x) => x !== c); c.parentNode = null; },
    getBoundingClientRect() { return { top: 100, bottom: 120, left: 50, width: 200, height: 80 }; },
    closest(sel) { for (let n = this; n; n = n.parentNode) { if (matches(n, sel)) return n; } return null; },
  };
}

function page() {
  const listeners = {};
  const body = el('BODY');
  const document = {
    readyState: 'complete', body,
    addEventListener: (type, cb) => { listeners[type] = cb; },
    createElement: (tag) => el(tag.toUpperCase()),
  };
  const window = {
    document, innerWidth: 1000, innerHeight: 800, scrollX: 0, scrollY: 0,
    matchMedia: () => ({ matches: false }),
    setTimeout: (fn, ms) => setTimeout(fn, ms),
    clearTimeout: (id) => clearTimeout(id),
  };
  vm.runInNewContext(SRC, { window, document, console });
  const link = el('A');
  link.setAttribute('data-sn-preview-title', 'A note');
  body.appendChild(link);
  const card = () => body.children.find((c) => matches(c, '.sn-link-preview')) || null;
  const fire = (type, target, extra = {}) => listeners[type](Object.assign({ target }, extra));
  return { link, card, fire };
}

test('hovering the link shows the card after the intent delay', () => {
  mock.timers.enable({ apis: ['setTimeout'] });
  try {
    const p = page();
    p.fire('pointerenter', p.link);
    assert.equal(p.card(), null, 'not before the intent delay');
    mock.timers.tick(150);
    assert.ok(p.card(), 'shown after it');
  } finally { mock.timers.reset(); }
});

test('the card survives the move from the link onto it, and stays while hovered (1.4.13 hoverable)', () => {
  mock.timers.enable({ apis: ['setTimeout'] });
  try {
    const p = page();
    p.fire('pointerenter', p.link);
    mock.timers.tick(150);
    const card = p.card();
    p.fire('pointerleave', p.link);
    mock.timers.tick(100);
    assert.ok(p.card(), 'still there while the pointer crosses the gap');
    p.fire('pointerenter', card);
    mock.timers.tick(2000);
    assert.equal(p.card(), card, 'stays while the pointer is on the card');
  } finally { mock.timers.reset(); }
});

test('leaving the card, or leaving the link for elsewhere, closes it after the grace', () => {
  mock.timers.enable({ apis: ['setTimeout'] });
  try {
    const p = page();
    p.fire('pointerenter', p.link);
    mock.timers.tick(150);
    const card = p.card();
    p.fire('pointerleave', p.link);
    p.fire('pointerenter', card);
    p.fire('pointerleave', card);
    mock.timers.tick(1000);
    assert.equal(p.card(), null, 'gone after leaving the card');

    p.fire('pointerenter', p.link);
    mock.timers.tick(150);
    p.fire('pointerleave', p.link);
    mock.timers.tick(1000);
    assert.equal(p.card(), null, 'gone after leaving the link for elsewhere');
  } finally { mock.timers.reset(); }
});

test('back onto the same link keeps the same card (no rebuild flicker)', () => {
  mock.timers.enable({ apis: ['setTimeout'] });
  try {
    const p = page();
    p.fire('pointerenter', p.link);
    mock.timers.tick(150);
    const card = p.card();
    p.fire('pointerleave', p.link);
    p.fire('pointerenter', p.link);
    mock.timers.tick(1000);
    assert.equal(p.card(), card);
  } finally { mock.timers.reset(); }
});

test('Escape dismisses at once; a sweep across the link never shows a card', () => {
  mock.timers.enable({ apis: ['setTimeout'] });
  try {
    const p = page();
    p.fire('pointerenter', p.link);
    mock.timers.tick(150);
    p.fire('keydown', p.link, { key: 'Escape' });
    assert.equal(p.card(), null);

    p.fire('pointerenter', p.link);
    mock.timers.tick(50);
    p.fire('pointerleave', p.link);
    mock.timers.tick(1000);
    assert.equal(p.card(), null);
  } finally { mock.timers.reset(); }
});

test('the card takes the pointer (no pointer-events: none on .sn-link-preview)', () => {
  const rule = CSS.slice(CSS.indexOf('.sn-link-preview {'), CSS.indexOf('}', CSS.indexOf('.sn-link-preview {')));
  assert.ok(rule.length > 0);
  assert.ok(!/pointer-events:\s*none/.test(rule), rule);
});
