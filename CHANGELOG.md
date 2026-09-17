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
- **`llms.txt` names the subject.** Its summary read "writer, music producer, and audio engineer. Long-form notes on craft, a discography, and professional background" and never said the word provenance; for a generative engine that line is the first, often the only, paragraph read about the site. It now says: music provenance research by Juan Lentino (with the ORCID), why cryptographic records of authorship made at creation, not AI detection after the fact, should anchor music rights; forty-plus notes, two SSRN papers, a verifier in the reader's browser; and the producer's discography and background. The Provenance hub is the first key page, ahead of Notes. Research register, no product. Pinned in `tests/llms-txt.php`.

## [13.2.5] - 2026-09-17 — a stylesheet outlives the page that names it

### Fixed
- **A superseded combined stylesheet survives for a week instead of being deleted on the spot.** `inc/asset-combine.php` used to unlink every previous `sn-styles-<hash>.css` the moment a rebuild wrote a new one, on the theory that old URLs expire from edge caches. They do; a reader's browser is not an edge cache. On 2026-09-17 the owner's phone held the home page from before a rebuild, its stylesheet URL 404ed, and the page painted with the inline critical CSS alone: no rule under the tagline, footer icons in raw accent red, the theme toggle unstyled. The prune is now age-gated by `SN_CSS_COMBINE_GRACE_SECS` (7 days), so a cached page still finds its stylesheet until the reader reloads. A hash is about 12 KB. Test 5 pins both halves: a recent superseded hash survives a rebuild, an old one is pruned; verified red without the gate.

