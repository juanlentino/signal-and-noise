# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

- **Feeds and searches the theme does not cache now say so, so the edge stops keeping them.** The live audit of 2026-10-17 found Cloudflare holding `/comments/feed/` (HIT, age 43), `/tag/x/feed/` and `/feed/?z=<random>`, all with no Cache-Control and no Cache-Tag, so a new comment or note never cleared them; `/tag/c2pa/?s=x` left the same way. `sn_edge_cache_kind_for()` returned nothing for them on the old assumption that Breeze sends `no-cache`, and Breeze has been inactive since 2026-10-13. A new `nocache` kind covers an anonymous 200 with no Cache-Control set that is an unlisted feed (comment, tag, category, author or search feeds, any query form of a feed) or a page refusal (search, password form, preview, robots and the like): it sends `Cache-Control: no-cache` (browsers and feed readers keep revalidating with ETag and Last-Modified) and `Cloudflare-CDN-Cache-Control: no-store`. A header already set (core's `nocache_headers()`, a 404) stays; logged-in and admin responses, the listed feeds, pages and machine files keep theirs. `/notes/?z=<random>` was not header-less on recheck: it is a cached, tagged HTML page and is unchanged. 7 new assertions in `tests/cache-headers.php` (red before).

- **Cleanup: the old data globals go, the cache doc catches up, a CSP plan, one core-fetch script.** The beacon and the command palette read only their JSON data islands now; the one-release fallback to `window.SN_BEACON` / `window.SN_CMDK` is gone (no island means no beacon and an empty palette, never a throw), and `window.SN_BEACON.event()` stays the public API. `docs/CACHING.md` now says Breeze is inactive since 2026-10-13, that unlisted feeds and refusals get `no-cache` plus an edge `no-store` from the theme (#584), that the plugin deletes the edge-cached feeds on an update (plugin #2166), and that core sends its own no-cache for logged-in and password-protected responses. New `docs/CSP.md`: the plan for a strict `script-src` at Cloudflare, Report-Only first, with the three inline hashes measured on the live site; not enforceable yet (`/contact/` has a third-party inline script). New `tools/fetch-wp-test-core.sh` fetches the core HTML and Interactivity API files the sweep needs; both CI lanes call it in their existing step.

## [2026.10.19] - 2026-10-17 — home in one screen, with a playhead and the latest note


- **Home is exactly one screen, with a playhead and a latest line on the pulse.** Owner, naming home: "Build the latest line and the playhead as rendered ... and remove the existing extra scroll so home is exactly one screen." The hero's height reserved 90px for the footer, but `<main>` added layout.css's 140px fixed-footer reserve and two block gaps, so home ran 71px past a 1440x900 screen (and 110px past 375x812, where the footer is static and 123px tall). On home the page column is now a flex column one screen tall; main reserves only the fixed footer (nothing at 480px and under); the hero grows into the rest, never below its content; short screens get a height-scaled headline and tighter padding, and short phones set the two buttons side by side. Measured at 1440x900, 1280x720, 1366x768, 2000x1125, 768x1024, 375x812, 375x667 and 390x844: the page is exactly the screen at every one, with no horizontal scroll, and the latest line clears the buttons (13px at the tightest). **Playhead:** a blood hairline at the pulse's "now" end, lit by the pulse's own inline rule, so no waveform means no playhead; it fades in after the waveform draws, and forced colors drop it with the pulse. **Latest line** (`inc/home-latest-line.php`): the newest published note's catalog number, title and date, one link, appended after the post-content element (the home Page's own content is untouched) and positioned over the hero so it adds no height; "Lo último" on the Spanish home. New `tests/home-latest-line.php` (19) and a playhead assertion in `tests/hero-pulse.php` (red before).


