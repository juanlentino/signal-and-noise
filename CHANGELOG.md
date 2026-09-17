# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

### Fixed
- **A superseded combined stylesheet survives for a week instead of being deleted on the spot.** `inc/asset-combine.php` used to unlink every previous `sn-styles-<hash>.css` the moment a rebuild wrote a new one, on the theory that old URLs expire from edge caches. They do; a reader's browser is not an edge cache. On 2026-09-17 the owner's phone held the home page from before a rebuild, its stylesheet URL 404ed, and the page painted with the inline critical CSS alone: no rule under the tagline, footer icons in raw accent red, the theme toggle unstyled. The prune is now age-gated by `SN_CSS_COMBINE_GRACE_SECS` (7 days), so a cached page still finds its stylesheet until the reader reloads. A hash is about 12 KB. Test 5 pins both halves: a recent superseded hash survives a rebuild, an old one is pruned; verified red without the gate.

## [13.2.4] - 2026-09-16 — the document as a pointer target


- **Fixed:** `footnotes-popover.js` threw `event.target.closest is not a function` on every note whenever the pointer left the window: `pointerenter` and `pointerleave` are captured on the document, so the pointer leaving the document targets the document itself, which has no `closest()`; a text node has none either. One `supOf( event )` helper guards the four handlers. Node suite `tests/js/footnotes-popover-target.test.cjs` drives the real file with the document, a text node and null as targets (red on the old code) and a real `<sup>` as the negative control.


