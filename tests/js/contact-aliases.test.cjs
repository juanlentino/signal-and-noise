// assets/js/contact-aliases.js turns each server-printed .sn-email span
// (inc/contact-email.php sn_email_markup: base64 user in data-eu, base64
// domain in data-ed, optional base64(rawurlencode(subject)) in data-esj, a
// readable "user [at] domain [dot] tld" fallback as text) into a clickable
// mailto link, built only at runtime so the served HTML never holds the
// address. Pinned: the href, a UTF-8 subject that survives the round trip
// (#328, mojibake when the decodeURIComponent step is missing), and that any
// malformed or hostile span keeps its inert fallback without stopping the
// others. Drives the REAL file under a fake DOM.
const { test } = require('node:test');
const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

const SRC = readFileSync(path.join(__dirname, '..', '..', 'assets', 'js', 'contact-aliases.js'), 'utf8');

const b64 = (s) => Buffer.from(s, 'utf8').toString('base64');
// PHP rawurlencode(): RFC 3986, so it also escapes the !'()* that
// encodeURIComponent leaves alone.
const rawurlencode = (s) => encodeURIComponent(s).replace(/[!'()*]/g, (c) => '%' + c.charCodeAt(0).toString(16).toUpperCase());

function el(tag) {
  return {
    tagName: tag, attrs: {}, children: [], parentNode: null, href: '', _text: '',
    get textContent() { return this._text + this.children.map((c) => c.textContent).join(''); },
    set textContent(v) { this._text = String(v); this.children = []; },
    getAttribute(k) { return k in this.attrs ? this.attrs[k] : null; },
    appendChild(c) { c.parentNode = this; this.children.push(c); return c; },
  };
}

// The span exactly as sn_email_markup() prints it.
function span({ user = 'research', domain = 'juanlentino.com', subject = '', eu, ed, esj } = {}) {
  const s = el('SPAN');
  s.attrs = { class: 'sn-email', 'data-sn-goal': 'contact-' + user, 'data-eu': eu ?? b64(user), 'data-ed': ed ?? b64(domain) };
  if (esj !== undefined) s.attrs['data-esj'] = esj;
  else if (subject) s.attrs['data-esj'] = b64(rawurlencode(subject));
  s.textContent = 'research [at] juanlentino [dot] com';
  return s;
}

function run(spans, { readyState = 'complete' } = {}) {
  const listeners = {};
  const document = {
    readyState,
    querySelectorAll: (sel) => (sel === '.sn-email' ? spans : []),
    createElement: (tag) => el(tag.toUpperCase()),
    addEventListener: (type, cb) => { listeners[type] = cb; },
  };
  vm.runInNewContext(SRC, { window: { atob, document }, document });
  return listeners;
}

const link = (s) => s.children.find((c) => c.tagName === 'A') || null;
const FALLBACK = 'research [at] juanlentino [dot] com';

test('a plain alias becomes a mailto link inside its span, replacing the fallback', () => {
  const s = span();
  run([s]);
  const a = link(s);
  assert.ok(a, 'a link was built');
  assert.equal(a.href, 'mailto:research@juanlentino.com');
  assert.equal(a.textContent, 'research@juanlentino.com');
  assert.equal(s.textContent, 'research@juanlentino.com', 'the [at]/[dot] text is gone');
  assert.equal(s.children.length, 1);
  assert.equal(s.getAttribute('data-sn-goal'), 'contact-research', 'the goal hook stays on the span the link sits in');
});

test('a UTF-8 subject round-trips into ?subject= with no mojibake (#328)', () => {
  const subject = "Re: Qué 'onda' (1/2) & más, ñandú 🎧";
  const s = span({ subject });
  run([s]);
  const href = link(s).href;
  assert.equal(href, 'mailto:research@juanlentino.com?subject=' + encodeURIComponent(subject));
  assert.equal(decodeURIComponent(href.split('?subject=')[1]), subject);
  assert.ok(!/Ã|Â|ð/.test(decodeURIComponent(href)), 'no Latin-1 reading of UTF-8 bytes');
});

test('a subject that is not valid percent-encoding is dropped; the link still works', () => {
  const s = span({ esj: b64('Re%3A%20%E0%A4%A') });
  run([s]);
  assert.equal(link(s).href, 'mailto:research@juanlentino.com');
});

test('malformed or hostile spans keep the inert fallback; the next span is still assembled', () => {
  const bad = [
    span({ eu: '!!!not base64' }),                      // atob throws
    span({ ed: '' }),                                   // missing domain
    span({ user: 'x?cc=evil@bad.example' }),            // smuggled mailto params
    span({ domain: 'juanlentino.com?bcc=x@bad.example' }),
  ];
  const good = span();
  run([...bad, good]);
  for (const s of bad) {
    assert.equal(link(s), null, s.attrs['data-eu']);
    assert.equal(s.textContent, FALLBACK);
  }
  assert.equal(link(good).href, 'mailto:research@juanlentino.com', 'one bad span does not stop the loop');
});

test('while the document is loading nothing changes until DOMContentLoaded', () => {
  const s = span();
  const listeners = run([s], { readyState: 'loading' });
  assert.equal(s.textContent, FALLBACK, 'no-JS-equivalent state until the event');
  listeners.DOMContentLoaded();
  assert.equal(link(s).href, 'mailto:research@juanlentino.com');
});

test('negative control: the script itself carries no address or mailto target', () => {
  assert.ok(!/[A-Za-z0-9._%+-]+@[A-Za-z0-9-]+\.[A-Za-z]{2,}/.test(SRC), 'no contiguous address in the source');
  assert.ok(!/mailto:[A-Za-z]/.test(SRC), 'mailto: is only ever joined to decoded parts');
});
