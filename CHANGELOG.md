# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

- The analytics beacon now sends five yes-or-no bot signals with every event, for observation only: whether the browser says it is remote-controlled (`navigator.webdriver`), whether a scroll milestone or a time report fired before any wheel, touch, key or pointer input on the page, whether a Chrome-family browser's `userAgentData` is missing or disagrees with its user agent, headless tells, and the browser's raw time zone offset. No identifier, position or timing is sent. Scroll milestones sent at load on a page too short to scroll never count as "no input". iOS Chrome, Edge and Firefox are WebKit and are not judged as Chrome. The analytics worker 1.22.0 must be live first. `tests/js/beacon-bot-signals.test.cjs` runs the real beacon under a fake page and pins the payload in `tests/js/fixtures/beacon-payload.json`, which the worker's join test reads.
- docs/CACHING.md no longer says /wp-json/ is kept out of Cloudflare caching. Measured 2026-09-27, public /wp-json/ GET responses are cached at the edge and the edge honours origin cache headers; the feed-open pixel is named as the example of an endpoint that opts out with its own no-store header.

## [14.5.0] - 2026-09-27 — your own devices stop counting


- The analytics beacon now sends nothing on a browser that carries the `sn_owner=1` cookie, which the plugin sets when the site owner logs in. The owner's own devices stop counting as readers even when logged out and the page comes from cache. The cookie name must match exactly, and a failed cookie read still counts the visit.


