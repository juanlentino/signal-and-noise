// A minimal element tree for driving a real front-end script under node:vm.
// Selectors: a tag, `.class`, `[attr]`, `[attr="value"]`, or `.class[attr="value"]`,
// which is every selector the scripts tested with it pass to querySelector(All).
// classList lives on the `class` attribute, so markup copied from the PHP
// renderer reads the same way the browser would read it.
'use strict';

const SEL = /^([a-z]+)?(?:\.([\w-]+))?(?:\[([\w-]+)(?:="([^"]*)")?\])?$/;

function match(n, sel) {
  const m = SEL.exec(sel);
  if (!m) throw new Error('fake-dom: unsupported selector ' + sel);
  const [, tag, cls, attr, val] = m;
  if (tag && n.tagName !== tag.toUpperCase()) return false;
  if (cls && !n.classList.contains(cls)) return false;
  if (attr && !(attr in n.attrs)) return false;
  if (attr && val !== undefined && n.attrs[attr] !== val) return false;
  return true;
}

function all(root, sel) {
  const out = [];
  (function walk(n) { for (const c of n.children) { if (match(c, sel)) out.push(c); walk(c); } })(root);
  return out;
}

function el(tag, attrs = {}) {
  const listeners = {};
  const classes = () => String(n.attrs.class || '').split(' ').filter(Boolean);
  const setClasses = (list) => { n.attrs.class = list.join(' '); };
  const n = {
    tagName: tag.toUpperCase(), attrs: { ...attrs }, children: [], parentNode: null, style: {}, textContent: '',
    classList: {
      contains: (c) => classes().includes(c),
      add: (c) => { if (!classes().includes(c)) setClasses([...classes(), c]); },
      remove: (c) => setClasses(classes().filter((x) => x !== c)),
      toggle: (c, force) => {
        const on = force === undefined ? !classes().includes(c) : !!force;
        if (on) n.classList.add(c); else n.classList.remove(c);
        return on;
      },
    },
    get firstChild() { return n.children[0] || null; },
    getAttribute: (k) => (k in n.attrs ? n.attrs[k] : null),
    setAttribute: (k, v) => { n.attrs[k] = String(v); },
    removeAttribute: (k) => { delete n.attrs[k]; },
    appendChild: (c) => { c.parentNode = n; n.children.push(c); return c; },
    append: (c) => n.appendChild(c),
    removeChild: (c) => { n.children = n.children.filter((x) => x !== c); c.parentNode = null; return c; },
    addEventListener: (type, cb) => { (listeners[type] = listeners[type] || []).push(cb); },
    dispatch: (type, e) => { for (const cb of listeners[type] || []) cb(e); return e; },
    click: () => n.dispatch('click', { target: n }),
    key: (key) => n.dispatch('keydown', { key, target: n, defaultPrevented: false, preventDefault() { this.defaultPrevented = true; } }),
    closest: (sel) => { for (let x = n; x; x = x.parentNode) { if (match(x, sel)) return x; } return null; },
    querySelectorAll: (sel) => all(n, sel),
    querySelector: (sel) => all(n, sel)[0] || null,
  };
  return n;
}

module.exports = { el, match, all };
