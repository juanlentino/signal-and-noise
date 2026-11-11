/**
 * The contrast runner's request route (tools/contrast-route.mjs), driven with
 * a fake Playwright context: the runner must block the site's analytics
 * beacon for every frame, never report the blocked beacon as a route issue,
 * and keep its asset checks and same-origin-only tokens as they were.
 */
const { test } = require('node:test');
const assert = require('node:assert/strict');
const path = require('node:path');
const { pathToFileURL } = require('node:url');

const MODULE = pathToFileURL(path.join(__dirname, '../../tools/contrast-route.mjs')).href;
const ORIGIN = 'https://juanlentino.com';

function fakeRoute(url, { type = 'document', method = 'GET', status = 200 } = {}) {
  const calls = { abort: [], continue: 0, fetch: [], fulfill: 0 };
  const route = {
    request: () => ({ url: () => url, method: () => method, resourceType: () => type, headers: () => ({ accept: '*/*' }), postDataBuffer: () => null }),
    abort: async (code) => { calls.abort.push(code); },
    continue: async () => { calls.continue++; },
    fetch: async (opts) => { calls.fetch.push(opts); return { status: () => status, headers: () => ({}) }; },
    fulfill: async () => { calls.fulfill++; },
  };
  return { route, calls };
}

async function setup(headers = {}) {
  const { installRoute } = await import(MODULE);
  const registered = [];
  const context = { route: async (pattern, handler) => { registered.push({ pattern, handler }); } };
  const routeIssues = [];
  let measured = false;
  const stats = await installRoute(context, { origin: ORIGIN, headers, routeIssues, measured: () => measured });
  return { registered, routeIssues, stats, handler: registered[0] && registered[0].handler, measure: () => { measured = true; } };
}

test('the route is registered on the whole context, for every request', async () => {
  const { registered } = await setup();
  assert.equal(registered.length, 1);
  assert.equal(registered[0].pattern, '**/*');
});

test('a beacon is aborted before it is fetched, in any form, and is never a route issue', async () => {
  const { handler, routeIssues, stats, measure } = await setup({ 'x-sn-smoke': 'token' });
  for (const type of ['ping', 'fetch']) {
    const { route, calls } = fakeRoute(`${ORIGIN}/_sn/px`, { type, method: 'POST' });
    await handler(route);
    assert.deepEqual(calls.abort, ['blockedbyclient'], `${type}: aborted`);
    assert.equal(calls.fetch.length, 0, `${type}: nothing sent`);
    assert.equal(calls.continue, 0, `${type}: not passed to the network`);
  }
  measure();
  const { route, calls } = fakeRoute(`${ORIGIN}/_sn/px`, { type: 'ping', method: 'POST' });
  await handler(route);
  assert.deepEqual(calls.abort, ['blockedbyclient'], 'still blocked after measurement ends');
  assert.deepEqual(routeIssues, []);
  assert.equal(stats.beacons, 3, 'every blocked beacon is counted for the run summary');
});

test('only the site path is the beacon: lookalikes are fetched as usual', async () => {
  const { handler, stats } = await setup();
  for (const url of [`${ORIGIN}/_sn/px-notes/`, `${ORIGIN}/notes/_sn/px`]) {
    const { route, calls } = fakeRoute(url);
    await handler(route);
    assert.equal(calls.fetch.length, 1, url);
    assert.deepEqual(calls.abort, [], url);
  }
  assert.equal(stats.beacons, 0);
});

test('a failed stylesheet is still a route issue, so the checks are not silenced', async () => {
  const { handler, routeIssues } = await setup();
  const { route } = fakeRoute(`${ORIGIN}/wp-content/themes/signal-and-noise/style.css`, { type: 'stylesheet', status: 404 });
  await handler(route);
  assert.equal(routeIssues.length, 1);
  assert.match(routeIssues[0], /^stylesheet answered 404/);
});

test('the tokens ride same-origin hops only', async () => {
  const { handler } = await setup({ 'x-sn-smoke': 's', 'x-sn-first-party': 'f' });
  const own = fakeRoute(`${ORIGIN}/about/`);
  await handler(own.route);
  assert.equal(own.calls.fetch[0].headers['x-sn-smoke'], 's');
  assert.equal(own.calls.fetch[0].headers['x-sn-first-party'], 'f');
  const other = fakeRoute('https://fonts.example.com/a.woff2', { type: 'font' });
  await handler(other.route);
  assert.equal(other.calls.continue, 1);
  assert.equal(other.calls.fetch.length, 0);
});
