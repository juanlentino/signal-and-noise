// /resume's lane switch (assets/js/resume-switch.js), driven for real under
// node:vm: the PHP guard pins its source, this pins what it does. The fixture
// (lib/resume-switch-fixture.cjs) is two lanes, a nav with two a[data-lane]
// links, the head alternates, and a fake history.
const { test } = require('node:test');
const assert = require('node:assert/strict');
const { fixture, SITE } = require('./lib/resume-switch-fixture.cjs');

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

test('a polite status region sits in the nav, empty at load, even when the load corrects the lane', () => {
  for (const at of ['/resume/', '/resume/?lane=operations']) {
    const live = fixture({ at }).live();
    assert.ok(live, at);
    assert.equal(live.getAttribute('class'), 'screen-reader-text');
    assert.equal(live.parentNode.getAttribute('aria-label'), 'Résumé version');
    assert.equal(live.textContent, '', at);
  }
});

test('a real switch is announced with the link\'s own label; a no-op or a modified click is not', () => {
  const f = fixture();
  f.click('music');
  f.click('operations', { ctrlKey: true });
  assert.equal(f.live().textContent, '');
  f.click('operations');
  assert.equal(f.live().textContent, 'Showing the Operations & AI Strategy résumé');
  f.live().textContent = 'x';
  f.click('operations');
  assert.equal(f.live().textContent, 'x', 'the current lane says nothing again');
  f.pop('/resume/');
  assert.equal(f.live().textContent, 'Showing the Music résumé');
  f.live().textContent = 'x';
  f.pop('/resume/#experience');
  assert.equal(f.live().textContent, 'x', 'a popstate that keeps the lane says nothing');
});

test('Back into the other lane moves focus out of the lane it hid, to the current switch link', () => {
  const f = fixture();
  f.click('operations');
  f.click('music');
  f.downloads.music.focus();
  f.pop('/resume/?lane=operations');
  assert.deepEqual(f.shown(), ['operations']);
  assert.equal(f.doc.activeElement, f.a.operations);
});

test('focus outside a hidden lane stays where it is', () => {
  const f = fixture();
  f.a.music.focus();
  f.pop('/resume/?lane=operations');
  assert.equal(f.doc.activeElement, f.a.music, 'on the switch');
  const g = fixture();
  g.downloads.music.focus();
  g.pop('/resume/#experience');
  assert.equal(g.doc.activeElement, g.downloads.music, 'in the lane that stays');
  const h = fixture();
  h.pop('/resume/?lane=operations');
  assert.equal(h.doc.activeElement.tagName, 'BODY', 'nothing focused, nothing moved');
});
