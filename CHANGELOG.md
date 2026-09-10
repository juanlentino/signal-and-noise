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
- The pull quote no longer runs off the right edge on mobile. `width: calc(100% + 2rem)` sizes the **content** box, so under `content-box` the `1.5rem` horizontal padding was added on top of the intended 1rem bleed instead of inside it. Measured on `/provenance` at 375px: the quote rendered 423px against a 343px column and ran 48px past the viewport, clipped rather than scrollable, slicing the last character off six lines. The three `.sn-pattern-*` siblings never showed it because they are core `wp:group` blocks, which core already gives `border-box`; only `.sn-pull-quote` is a custom `<aside>` from `blocks/pull-quote/render.php`, so only it inherited nothing. `box-sizing: border-box` is now declared on all four so the idiom stops depending on who renders the element.
- `tests/prose-slab-idiom.php` read its own comments. The scan stripped comment blocks from a rule's selector but not its body, and a comment sitting between `;` and a declaration blocks any predicate anchored on `;`. `.sn-correction` documents its padding inline, so its `padding: 1.5rem` was invisible to the new sizing check and the element this test file was written for would have skipped it in silence.

### Added
- The prose-slab suite now pins the box MODEL, not only the shape. It derived the slab family from source and asserted each one breaks the measure, but nothing asserted the bleed **fits**: a padded full-bleed slab under `content-box` renders wider than the viewport, which is correctly shaped and wrongly sized. Removing `box-sizing` from the four rules reddens three assertions; the controls separate a correctly-shaped-but-mis-sized slab from a mis-shaped one, and confirm a vertically-padded bleed is not flagged.

## [12.20.5] - 2026-09-10 — the steps list learns to be unordered

### Added
- The steps list takes an unordered variant. The 01/02/03 numerals come from a CSS counter on the list class, not from the `<ol>` tag, so switching a steps list to `<ul>` fixed its semantics and left the numbers rendering — markup saying unordered, pixels saying ordered. `sn-steps__list--plain` stops the counter and marks items with an en-dash instead, for sets whose items are alternatives rather than steps. The bleed panel and label are unchanged.

