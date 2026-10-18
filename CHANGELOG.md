# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

- **Four accessibility fixes from the live sweep of 2026-10-18.** (1) The language switch's accessible name now starts with what it shows: "ES, Español" and "EN, English", not "Español (ES)" (WCAG 2.5.3, axe `label-content-name-mismatch`). (2) Smooth scrolling now stops under `prefers-reduced-motion: reduce`. The inline critical CSS prints after the combined sheet, so base.css's override always lost; `critical.css` now carries its own, right after the smooth rule, and base.css's comment no longer claims otherwise. New `tests/critical-reduced-motion.php` pins the order. (3) The featured Spotify player on /music shows the house focus ring (2px blood) on its box. CSS alone cannot do it: measured in Chromium, Tab moves focus inside the cross-origin player and the `<iframe>` matches no focus selector, and its mask would clip an outline of its own. The ring sits on the wrapper on `:focus-within`, and a small new script, `assets/js/frame-focus.js` (loaded only with the player), adds it when focus enters the player by Tab, never on a click (WCAG 2.4.7). (4) The comparison table's figure keeps `tabindex="0"` and its name but drops `role="region"`, which a figure with a caption may not carry (axe `aria-allowed-role`); keyboard scrolling works as before. Checked in Chromium with axe and a keyboard pass on a local render of the theme code: all four reproduce on `main` and clear on this branch.

- **Notes no longer send Spanish readers to the Spanish home.** On an English page with no Spanish version (every note), ES used to link to /inicio/. Now the reader stays: ES is a muted segment that goes nowhere, announced as unavailable ("ES, no disponible: solo en inglés. Podés leerla con el traductor del navegador."), with the same words as a small hint on hover and on keyboard focus. Spanish pages are unchanged. In the Spanish header, Notas still goes to the English /notes/ (there is no /notas/) and now says so with `hreflang="en"`, like the footer's English links; so does any header link that stays English, and the latest-note line on the Spanish home.

## [2026.10.20] - 2026-10-17 — unlisted feeds stay out of the edge cache; caching and CSP docs refreshed


- **Feeds and searches the theme does not cache now say so, so the edge stops keeping them.** The live audit of 2026-10-17 found Cloudflare holding `/comments/feed/` (HIT, age 43), `/tag/x/feed/` and `/feed/?z=<random>`, all with no Cache-Control and no Cache-Tag, so a new comment or note never cleared them; `/tag/c2pa/?s=x` left the same way. `sn_edge_cache_kind_for()` returned nothing for them on the old assumption that Breeze sends `no-cache`, and Breeze has been inactive since 2026-10-13. A new `nocache` kind covers an anonymous 200 with no Cache-Control set that is an unlisted feed (comment, tag, category, author or search feeds, any query form of a feed) or a page refusal (search, password form, preview, robots and the like): it sends `Cache-Control: no-cache` (browsers and feed readers keep revalidating with ETag and Last-Modified) and `Cloudflare-CDN-Cache-Control: no-store`. A header already set (core's `nocache_headers()`, a 404) stays; logged-in and admin responses, the listed feeds, pages and machine files keep theirs. `/notes/?z=<random>` was not header-less on recheck: it is a cached, tagged HTML page and is unchanged. 7 new assertions in `tests/cache-headers.php` (red before).

- **Cleanup: the old data globals go, the cache doc catches up, a CSP plan, one core-fetch script.** The beacon and the command palette read only their JSON data islands now; the one-release fallback to `window.SN_BEACON` / `window.SN_CMDK` is gone (no island means no beacon and an empty palette, never a throw), and `window.SN_BEACON.event()` stays the public API. `docs/CACHING.md` now says Breeze is inactive since 2026-10-13, that unlisted feeds and refusals get `no-cache` plus an edge `no-store` from the theme (#584), that the plugin deletes the edge-cached feeds on an update (plugin #2166), and that core sends its own no-cache for logged-in and password-protected responses. New `docs/CSP.md`: the plan for a strict `script-src` at Cloudflare, Report-Only first, with the three inline hashes measured on the live site; not enforceable yet (`/contact/` has a third-party inline script). New `tools/fetch-wp-test-core.sh` fetches the core HTML and Interactivity API files the sweep needs; both CI lanes call it in their existing step.


