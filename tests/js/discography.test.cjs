// assets/js/discography.js enhances the [sn_discography] grid on /music
// (inc/discography-render.php). Pinned, against the markup the server prints:
// role chips filter the cards, collapse empty year sections, update the live
// count, the empty state and aria-pressed, and sync ?role= (an unknown role
// falls back to All); the page ships ZERO Spotify iframes and a cover mounts
// its embed only when played, by click, Enter or Space; liner-notes previews
// share one <audio>, toggle, retire a dead preview, and a late rejection of
// A's play() never stops B (#325). Drives the REAL file under a fake DOM.
const { test } = require('node:test');
const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const { el, match, all } = require('./lib/fake-dom.cjs');

const SRC = readFileSync(path.join(__dirname, '..', '..', 'assets', 'js', 'discography.js'), 'utf8');

function card(roles, id, type, playable = true) {
  const c = el('article', { class: 'sn-disco-card', 'data-roles': roles, 'data-spotify': id, 'data-type': type });
  const wrap = c.append(el('div', playable
    ? { class: 'sn-disco-cover-wrap', role: 'button', tabindex: '0', 'aria-label': 'Play ' + id }
    : { class: 'sn-disco-cover-wrap sn-disco-cover-wrap--static' }));
  wrap.append(el('img', { class: 'sn-disco-art' }));
  if (playable) wrap.append(el('span', { class: 'sn-disco-play-badge' }));
  return c;
}

function page({ search = '', play } = {}) {
  const root = el('div');
  const chip = (role) => root.append(el('button', { class: role === '*' ? 'sn-disco-chip is-active' : 'sn-disco-chip', 'data-role': role, 'aria-pressed': role === '*' ? 'true' : 'false' }));
  const count = root.append(el('strong', { 'data-disco-count': '' }));
  count.textContent = '3';
  const chips = { all: chip('*'), mixing: chip('Mixing'), mastering: chip('Mastering'), producer: chip('Producer') };
  const y24 = root.append(el('section', { class: 'sn-disco-year' }));
  const y23 = root.append(el('section', { class: 'sn-disco-year' }));
  const cards = { a: y24.append(card('Mixing|Mastering', 'ALB1', 'album')), b: y24.append(card('Mastering', 'TRK1', 'track')), c: y23.append(card('Mixing', '', 'album', false)) };
  const empty = root.append(el('p', { class: 'sn-disco-empty', hidden: '' }));
  const liner = root.append(el('details', { class: 'sn-disco-liner' }));
  const track = (url) => liner.append(el('button', Object.assign({ class: 'sn-disco-track__play', 'aria-pressed': 'false' }, url ? { 'data-preview': url } : {})));
  const tracks = { one: track('https://p.scdn.co/1.mp3'), two: track('https://p.scdn.co/2.mp3'), none: track('') };

  const pushed = [];
  const winListeners = {};
  const audios = [];
  const location = { pathname: '/music/', search, hash: '' };
  const window = {
    location, URLSearchParams,
    history: { pushState: (s, t, url) => { pushed.push(url); const q = url.indexOf('?'); location.search = q < 0 ? '' : url.slice(q); } },
    addEventListener: (type, cb) => { winListeners[type] = cb; },
  };
  const document = {
    readyState: 'complete',
    querySelectorAll: (sel) => all(root, sel),
    querySelector: (sel) => all(root, sel)[0] || null,
    createElement: (tag) => el(tag),
  };
  class Audio {
    constructor() { this.listeners = {}; this.paused = true; this.calls = []; audios.push(this); }
    addEventListener(type, cb) { this.listeners[type] = cb; }
    play() { this.paused = false; this.calls.push('play:' + this.src); return play ? play(this) : Promise.resolve(); }
    pause() { this.paused = true; this.calls.push('pause'); }
  }
  vm.runInNewContext(SRC, { window, document, Audio, URLSearchParams });
  const visible = () => Object.keys(cards).filter((k) => !cards[k].classList.contains('is-hidden'));
  return { root, chips, cards, count, empty, years: [y24, y23], tracks, pushed, winListeners, audios, location, visible };
}

const pressed = (p) => Object.keys(p.chips).filter((k) => p.chips[k].getAttribute('aria-pressed') === 'true');
const iframes = (n) => all(n, 'iframe');

test('a role chip filters the cards, collapses an empty year, and updates count, aria-pressed and the URL', () => {
  const p = page();
  p.chips.mastering.click();
  assert.deepEqual(p.visible(), ['a', 'b']);
  assert.equal(p.years[0].style.display, '');
  assert.equal(p.years[1].style.display, 'none', '2023 has nothing left');
  assert.equal(p.count.textContent, '2');
  assert.deepEqual(pressed(p), ['mastering']);
  assert.ok(p.chips.mastering.classList.contains('is-active') && !p.chips.all.classList.contains('is-active'));
  assert.equal(p.empty.getAttribute('hidden'), '', 'empty state stays hidden');
  assert.deepEqual(p.pushed, ['/music/?role=Mastering']);

  p.chips.all.click();
  assert.deepEqual(p.visible(), ['a', 'b', 'c']);
  assert.equal(p.years[1].style.display, '');
  assert.equal(p.count.textContent, '3');
  assert.deepEqual(pressed(p), ['all']);
  assert.equal(p.pushed[1], '/music/', 'All drops ?role=');
});

test('a role that matches no card shows the empty state', () => {
  const p = page();
  p.chips.producer.click();
  assert.deepEqual(p.visible(), []);
  assert.equal(p.count.textContent, '0');
  assert.equal(p.empty.getAttribute('hidden'), null);
  assert.ok(p.years.every((y) => y.style.display === 'none'));
});

test('?role= deep-links on load without a push; an unknown role falls back to All; Back re-applies', () => {
  const p = page({ search: '?role=Mixing' });
  assert.deepEqual(p.visible(), ['a', 'c']);
  assert.deepEqual(pressed(p), ['mixing']);
  assert.deepEqual(p.pushed, []);
  p.location.search = '?role=%3Cimg%20src%3Dx%3E';
  p.winListeners.popstate();
  assert.deepEqual(p.visible(), ['a', 'b', 'c']);
  assert.deepEqual(pressed(p), ['all']);
});

test('no iframe ships; a click mounts only that release, at the album or track path, once', () => {
  const p = page();
  assert.equal(iframes(p.root).length, 0, 'zero embeds at load');
  const wrapA = all(p.cards.a, '.sn-disco-cover-wrap')[0];
  wrapA.click();
  const [f] = iframes(p.root);
  assert.equal(iframes(p.root).length, 1, 'only the chosen release');
  assert.equal(f.parentNode, wrapA);
  assert.equal(f.src, 'https://open.spotify.com/embed/album/ALB1');
  assert.equal(all(wrapA, 'img').length, 0, 'the cover is replaced in place');
  assert.ok(wrapA.classList.contains('is-playing'));
  for (const a of ['role', 'tabindex', 'aria-label']) assert.equal(wrapA.getAttribute(a), null, a + ' removed: no longer a button');
  wrapA.click();
  assert.equal(iframes(p.root).length, 1, 'a second click mounts nothing new');
});

test('Enter and Space play a cover (default prevented); other keys and static covers do nothing', () => {
  const p = page();
  const wrapB = all(p.cards.b, '.sn-disco-cover-wrap')[0];
  const tab = wrapB.key('Tab');
  assert.equal(tab.defaultPrevented, false);
  assert.equal(iframes(p.root).length, 0);
  assert.equal(wrapB.key(' ').defaultPrevented, true);
  assert.equal(iframes(p.cards.b)[0].src, 'https://open.spotify.com/embed/track/TRK1');
  const p2 = page();
  all(p2.cards.a, '.sn-disco-cover-wrap')[0].key('Enter');
  assert.equal(iframes(p2.cards.a).length, 1);
  all(p2.cards.c, '.sn-disco-cover-wrap')[0].click();
  assert.equal(iframes(p2.cards.c).length, 0, 'a Muso-only card is not playable');
});

test('liner-notes previews: one shared audio, toggle off, switch, end, and a dead preview retires', () => {
  const p = page();
  const { one, two, none } = p.tracks;
  none.click();
  assert.equal(p.audios.length, 0, 'no data-preview, no audio');
  one.click();
  assert.equal(p.audios.length, 1);
  const audio = p.audios[0];
  assert.equal(audio.src, 'https://p.scdn.co/1.mp3');
  assert.equal(one.getAttribute('aria-pressed'), 'true');
  two.click();
  assert.equal(p.audios.length, 1, 'the same element is reused');
  assert.equal(one.getAttribute('aria-pressed'), 'false');
  assert.equal(two.getAttribute('aria-pressed'), 'true');
  two.click();
  assert.equal(audio.paused, true);
  assert.equal(two.getAttribute('aria-pressed'), 'false', 'a second click stops it');
  one.click();
  audio.listeners.ended();
  assert.equal(one.getAttribute('aria-pressed'), 'false');
  two.click();
  audio.listeners.error();
  assert.ok(two.classList.contains('is-dead'));
  assert.equal(two.getAttribute('disabled'), '');
  assert.equal(two.getAttribute('aria-pressed'), 'false');
});

test("negative control (#325): A's late play() rejection does not stop B", async () => {
  const pending = [];
  const p = page({ play: () => new Promise((res, rej) => pending.push({ res, rej })) });
  p.tracks.one.click();
  p.tracks.two.click();
  pending[0].rej(new Error('AbortError'));
  await new Promise((r) => setImmediate(r));
  assert.equal(p.tracks.two.getAttribute('aria-pressed'), 'true', 'B still plays');
  assert.equal(p.audios[0].paused, false);
  pending[1].rej(new Error('NotAllowedError'));
  await new Promise((r) => setImmediate(r));
  assert.equal(p.tracks.two.getAttribute('aria-pressed'), 'false', "B's own rejection does stop B");
});

// Keep the selector helper honest: it is what decides whether these pass.
test('fake DOM matcher', () => {
  const n = el('div', { class: 'a b', role: 'button' });
  assert.ok(match(n, '.a') && match(n, '.b[role="button"]') && match(n, '[role]'));
  assert.ok(!match(n, '.c') && !match(n, '.a[role="link"]') && !match(n, '[hidden]'));
});
