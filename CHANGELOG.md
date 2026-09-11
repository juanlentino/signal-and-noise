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
- **Versioning is the WordPress shape.** `X.Y.0` is a release, `X.Y.Z` a fix, `X` rolls when `Y` would reach 10 — core / WooCommerce / Jetpack numbering, same day as the plugin. `X` is not a breaking flag; a breaking release says BREAKING in its headline. `tools/cut-release.sh release|fix` (`minor`/`patch` aliased; `major` refuses). Cuts stack: one per arc, never per merge. Owner decision 2026-09-11; rule in `docs/VERSIONING.md`. The next theme cut is 13.0.0 — the roll, not a break.
- CI: the lint job now refuses any workflow job without `timeout-minutes` (the plugin's YAML guard, copied verbatim); the rule was enforced in one repo and kept by habit here. `smoke-test.yml` lints on PHP 8.4 — production — instead of 8.2. `CLAUDE.md` §Versioning says what is true since 2026-09-11: a cut is a PR (direct pushes to `main` are refused), and releases are public. From the plugin's [enforcement audit](https://github.com/juanlentino/signal-and-noise-tools/blob/main/docs/audits/enforcement-audit-2026-09-11.md), Phase 4.

## [12.20.6] - 2026-09-10 — the pull quote stops running off the screen

### Fixed
- The pull quote no longer runs off the right edge on mobile. `width: calc(100% + 2rem)` sizes the **content** box, so under `content-box` the `1.5rem` horizontal padding was added on top of the intended 1rem bleed instead of inside it. Measured on `/provenance` at 375px: the quote rendered 423px against a 343px column and ran 48px past the viewport, clipped rather than scrollable, slicing the last character off six lines. The three `.sn-pattern-*` siblings never showed it because they are core `wp:group` blocks, which core already gives `border-box`; only `.sn-pull-quote` is a custom `<aside>` from `blocks/pull-quote/render.php`, so only it inherited nothing. `box-sizing: border-box` is now declared on all four so the idiom stops depending on who renders the element.
- `tests/prose-slab-idiom.php` read its own comments. The scan stripped comment blocks from a rule's selector but not its body, and a comment sitting between `;` and a declaration blocks any predicate anchored on `;`. `.sn-correction` documents its padding inline, so its `padding: 1.5rem` was invisible to the new sizing check and the element this test file was written for would have skipped it in silence.

### Added
- The prose-slab suite now pins the box MODEL, not only the shape. It derived the slab family from source and asserted each one breaks the measure, but nothing asserted the bleed **fits**: a padded full-bleed slab under `content-box` renders wider than the viewport, which is correctly shaped and wrongly sized. Removing `box-sizing` from the four rules reddens three assertions; the controls separate a correctly-shaped-but-mis-sized slab from a mis-shaped one, and confirm a vertically-padded bleed is not flagged.

