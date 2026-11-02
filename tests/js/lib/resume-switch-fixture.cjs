// The /resume lane switch under node:vm (tests/js/resume-switch.test.cjs): the
// page as the plugin renders it, the real script run against it, and handles
// for clicks, popstate and what the reader would see.
const { readFileSync } = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const { el } = require('./fake-dom.cjs');

const SRC = readFileSync(path.join(__dirname, '../../../assets/js/resume-switch.js'), 'utf8');
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

module.exports = { fixture, SITE };
