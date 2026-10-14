# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

### Changed
- **The purge chain no longer fires `breeze_clear_varnish`.** Varnish is off for this app (Cloudways API: `varnish_enabled: false`), so the action only ran the companion plugin's Cloudways app purge, which with Varnish off just emptied Redis a second time, with up to 10 seconds of blocking API calls. The action is removed, not gated: the theme has nothing to gate it on. Breeze's own buttons still fire it. The purge report drops its Varnish leg with it, because the companion's last Cloudways record would otherwise be reported as this purge's result when no such purge ran. Pinned by tests/template-maintenance.php and tests/purge-verify.php.
- **A superseded combined stylesheet now survives 8 days, not 7.** The edge may serve a page up to its stale-if-error (7 days) plus its edge TTL (2 hours) old during an origin outage, so the stylesheet that page names has to outlive both. Pinned by tests/asset-combine.php.

### Added
- **The purge report says whether Breeze's page cache is on** (`legs.breeze_page_cache`: true, false, or null when Breeze is not loaded or has no setting to read; a deactivated Breeze leaves its settings behind). A Breeze hit leaves without the CDN header and `Cache-Tag`, so a copy the edge stores from one is out of reach of every tag purge. Read from Breeze's own `breeze_basic_settings` / `breeze-active`, the value Breeze checks before it writes `advanced-cache.php`. Pinned by tests/purge-verify.php.

### Fixed
- **The Claude app's built-in browser no longer counts as a visitor, so its page loads stop reading as slow desktop LCP.** This is a measurement fix, not a speed fix. The field beacon now skips any browser whose user agent carries the `Claude/<version>` token, the same way it already skips the owner's browsers (`sn_owner=1`). That pane is where agent sessions open the site at desktop width. It has its own cookie jar without the owner flag, and while hidden it reports `visible` but paints nothing until it is shown. Measured 2026-10-13: a note loaded in the hidden pane had its first contentful paint at 508 s, and web-vitals would have sent that as a desktop LCP sample. Lab desktop LCP on the field's worst paths (`/`, `/about/`, `/music/`, `/contact/`, `/now/` and the worst note) is 0.4 to 0.7 s, with nothing render-blocking. The poor tail (9% over 4 s on desktop against 3% on mobile) does not come from the pages, and one note with five views accounted for four of the ten poor samples site-wide. Pinned red-first in tests/js/beacon-owner-device.test.cjs.

## [2026.10.2] - 2026-10-13 — every note carries its own cache label

### Added
- **Each note, and every page that lists notes, carries a cache tag of its own.** Every cached response still carries `sn-render`. A single note now also carries `sn-note-<ID>`, and the pages, feeds and machine files listed here carry `sn-lists`: home, /notes/ and its pages, /notes/tags/, /index/, /provenance/, the tag and category archives, every feed (JSON included), llms.txt and llms-full.txt. With these, the companion plugin can refresh a note whose save changed nothing other pages list by purging those two tags in one call. That clears every copy of the note's page whatever its query string (share and feed links with `?utm_source=` are separate copies at the edge), in every tier, without emptying the whole site. `sn_edge_cache_note_tag()` and `SN_EDGE_CACHE_LIST_TAG` are the names the plugin reads, and without them it keeps the whole-site purge. No page output changes; only the `Cache-Tag` header, which Cloudflare strips before the visitor. Pinned by tests/cache-headers.php.

