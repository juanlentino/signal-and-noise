// /resume's lane switch (assets/js/resume-switch.js), driven for real under
// node:vm: the PHP guard pins its source, this pins what it does. Two lanes, a
// nav with two a[data-lane] links, the head alternates, and a fake history.
const { test } = require('node:test');
const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const { el } = require('./lib/fake-dom.cjs');

const SRC = readFileSync(path.join(__dirname, '../../assets/js/resume-switch.js'), 'utf8');
const SITE = 'https://juanlentino.com';

function fixture({ at = '/resume/', server = 'music', history = true } = {}) {
  const doc = el('html');
  const head = doc.appendChild(el('head'));
  const body = doc.appendChild(el('body'));
  const opsQ = server === 'operations' ? '?lane=operations' : '';
  const alt = (type, href) => head.appendChild(el('link', { rel: 'alternate', type, href }));
  const links = { md: alt('text/markdown', SITE + '/resume.md' + opsQ), json: alt('application/json', SITE + '/resume/json-resume.json' + opsQ),
    page: alt('application/json', SITE + '/resume.json'), output: alt('text/markdown', SITE + '/resume/?output_format=markdown') };
  const focusable = (n) => { n.focus = () => { doc.activeElement = n; }; return n; };
  const nav = body.appendChild(el('nav', { class: 'wp-block-group sn-resume-switch', 'aria-label': 'Résumé version' }));
  const ul = nav.appendChild(el('ul', { class: 'wp-block-list sn-resume-switch-list' }));
  const a = {};
  const lanes = {};
  const downloads = {};
  for (const [lane, text, href] of [['music', 'Music', '/resume/'], ['operations', 'Operations & AI Strategy', '/resume/?lane=operations']]) {
    a[lane] = focusable(ul.appendChild(el('li')).appendChild(el('a', { href, 'data-lane': lane })));
    a[lane].textContent = text;
    if (lane === server) a[lane].setAttribute('aria-current', 'page');
    lanes[lane] = body.appendChild(el('div', { id: 'resume-' + lane, class: 'wp-block-group sn-resume-variant sn-resume-variant--' + lane }));
    lanes[lane].hidden = lane !== server;
    downloads[lane] = focusable(lanes[lane].appendChild(el('a', { href: '/r.pdf' })));
  }
  doc.activeElement = body;
  doc.createElement = (t) => el(t);
  let url = new URL(at, SITE);
  const pushed = [];
  const popHandlers = [];
  const win = {
    location: { get href() { return url.href; }, get search() { return url.search; }, get hash() { return url.hash; } },
    history: history ? { pushState(state, title, u) { pushed.push(u); url = new URL(u, url.href); } } : {},
    addEventListener(type, fn) { if (type === 'popstate') popHandlers.push(fn); },
  };
  vm.runInNewContext(SRC, { window: win, document: doc, URL, URLSearchParams });
  const click = (lane, extra = {}) => a[lane].dispatch('click', { button: 0, defaultPrevented: false, preventDefault() { this.defaultPrevented = true; }, ...extra });
  const pop = (u) => { url = new URL(u, url.href); for (const fn of popHandlers) fn({}); };
  const shown = () => Object.keys(lanes).filter((l) => !lanes[l].hidden);
  const current = () => Object.keys(a).filter((l) => a[l].getAttribute('aria-current') === 'page');
  const live = () => nav.querySelector('p[role="status"]');
  return { doc, a, lanes, downloads, links, pushed, click, pop, shown, current, live, url: () => url.href };
}

test('Operations: the music lane hides, ops shows, aria-current moves, the address gains ?lane=operations', () => {
  const f = fixture();
  const ev = f.click('operations');
  assert.equal(ev.defaultPrevented, true);
  assert.deepEqual(f.shown(), ['operations']);
  assert.deepEqual(f.current(), ['operations']);
  assert.equal(f.pushed.length, 1);
  assert.match(f.pushed[0], /\/resume\/\?lane=operations$/);
});

test('Music deletes the lane param, keeping the rest of the query', () => {
  const f = fixture({ at: '/resume/?preview_id=9&lane=operations', server: 'operations' });
  f.click('music');
  assert.deepEqual(f.shown(), ['music']);
  assert.equal(f.pushed[0], SITE + '/resume/?preview_id=9');
});

test('the current lane pushes nothing', () => {
  const f = fixture();
  f.click('music');
  assert.deepEqual(f.pushed, []);
  assert.deepEqual(f.shown(), ['music']);
});

test('a modified or middle click is the link\'s own job', () => {
  for (const extra of [{ ctrlKey: true }, { metaKey: true }, { shiftKey: true }, { altKey: true }, { button: 1 }]) {
    const f = fixture();
    assert.equal(f.click('operations', extra).defaultPrevented, false, JSON.stringify(extra));
    assert.deepEqual(f.shown(), ['music']);
    assert.deepEqual(f.pushed, []);
  }
});

test('popstate reads the address as the server does: ?lane=OPERATIONS is ops, anything else is music', () => {
  const f = fixture();
  f.pop('/resume/?lane=OPERATIONS');
  assert.deepEqual(f.shown(), ['operations']);
  assert.deepEqual(f.current(), ['operations']);
  f.pop('/resume/?lane=sales');
  assert.deepEqual(f.shown(), ['music']);
  assert.deepEqual(f.current(), ['music']);
});

test('on load the address decides, whatever copy answered', () => {
  const f = fixture({ at: '/resume/?lane=operations', server: 'music' });
  assert.deepEqual(f.shown(), ['operations']);
  assert.deepEqual(f.current(), ['operations']);
});

test('without history.pushState the script does nothing: the links work as links', () => {
  const f = fixture({ history: false });
  assert.equal(f.click('operations').defaultPrevented, false);
  assert.deepEqual(f.shown(), ['music']);
  assert.equal(f.live(), null);
});

test('the last lane wins, as PHP\'s $_GET reads a repeated key', () => {
  assert.deepEqual(fixture({ at: '/resume/?lane=music&lane=operations', server: 'operations' }).shown(), ['operations']);
  assert.deepEqual(fixture({ at: '/resume/?lane=operations&lane=music', server: 'music' }).shown(), ['music']);
});

test('with no ?lane, a #resume-<lane> address names its lane; ?lane outranks it', () => {
  assert.deepEqual(fixture({ at: '/resume/#resume-operations' }).shown(), ['operations']);
  assert.deepEqual(fixture({ at: '/resume/?lane=music#resume-operations' }).shown(), ['music']);
  assert.deepEqual(fixture({ at: '/resume/#operations' }).shown(), ['music']);
});

test('switching away from a #resume-<lane> address drops that hash, so Back and reload agree', () => {
  const f = fixture({ at: '/resume/#resume-operations' });
  f.click('music');
  assert.deepEqual(f.pushed, [SITE + '/resume/']);
  assert.deepEqual(f.shown(), ['music']);
  f.click('operations');
  assert.equal(f.pushed[1], SITE + '/resume/?lane=operations');
  const g = fixture({ at: '/resume/#experience' });
  g.click('operations');
  assert.equal(g.pushed[0], SITE + '/resume/?lane=operations#experience', 'any other anchor stays');
});
