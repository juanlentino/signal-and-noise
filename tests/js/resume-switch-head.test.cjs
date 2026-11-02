// /resume's lane switch and the page head: a real change in place points the
// head's lane-bound URLs (Markdown and JSON Resume alternates, the canonical,
// og:url) at the lane on screen; the load leaves them as the server wrote them.
const { test } = require('node:test');
const assert = require('node:assert/strict');
const { fixture, SITE } = require('./lib/resume-switch-fixture.cjs');

test('a real switch retargets the head\'s Markdown and JSON Resume alternates, and only those', () => {
  const f = fixture();
  const hrefs = () => Object.fromEntries(Object.entries(f.links).map(([k, l]) => [k, l.getAttribute('href')]));
  const before = hrefs();
  f.click('operations');
  assert.deepEqual(hrefs(), { ...before, md: SITE + '/resume.md?lane=operations', json: SITE + '/resume/json-resume.json?lane=operations' });
  f.pop('/resume/');
  assert.deepEqual(hrefs(), before);
  const g = fixture({ at: '/resume/?lane=operations', server: 'operations' });
  g.click('music');
  assert.equal(g.links.md.getAttribute('href'), SITE + '/resume.md');
  assert.equal(g.links.json.getAttribute('href'), SITE + '/resume/json-resume.json');
});

test('the load-time correction leaves the alternates as the server wrote them', () => {
  const f = fixture({ at: '/resume/?lane=operations', server: 'music' });
  assert.equal(f.links.md.getAttribute('href'), SITE + '/resume.md');
});

test('a real switch moves the canonical and og:url to the lane, so a share sheet copies the lane shown', () => {
  const f = fixture({ at: '/resume/?utm_source=x' });
  f.click('operations');
  assert.equal(f.canon.getAttribute('href'), SITE + '/resume/?lane=operations');
  assert.equal(f.og.getAttribute('content'), SITE + '/resume/?lane=operations');
  assert.equal(f.pushed[0], SITE + '/resume/?utm_source=x&lane=operations', 'the address keeps its query; the canonical does not take it');
  f.pop('/resume/');
  assert.equal(f.canon.getAttribute('href'), SITE + '/resume/');
  assert.equal(f.og.getAttribute('content'), SITE + '/resume/');
});

test('the load leaves the canonical and og:url as the server wrote them, for crawlers', () => {
  const f = fixture({ at: '/resume/?lane=operations', server: 'music' });
  assert.equal(f.canon.getAttribute('href'), SITE + '/resume/');
  assert.equal(f.og.getAttribute('content'), SITE + '/resume/');
});
