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
- **Every page is centred again, on one frame, and the frame is wider on big screens.** 13.9.0 moved `/uses`, `/now`, `/index` and `/accessibility` to the left under the mark, and 13.9.1 and 14.0.0 did the same to `/notes`; on a wide screen that left an empty band on one side, and every way of holding both edges meant stretching text, leaving holes inside the layout or cropping the photo (owner, 2026-09-22: "Just make the whole thing as it was before. Even /notes. But push the width enough"). The page containers are back to `margin: 0 auto` and their pre-13.9.0 side padding, `/index` and `/accessibility` back to 60rem. What changes is the track: a new `theme.json` token, `custom.pageTrack` = `max(1320px, min(wideSize, 100vw - 400px))`, which is exactly the old 1320px on every screen up to 1720px wide and grows to 1600px (the new `wideSize`) at 2000. The pages built in the editor (About, Services, Music, Resume, Contact) carry their own 1320px in the page content, so `critical.css` lifts them to the same track, scoped to `body.page`; a Note's body is untouched. Measured live with the built rules: at 1440 and 375 About and Resume are pixel-identical to today; at 2000 every page runs 200 to 1800px (960px for the two reading pages); no horizontal scroll. The header keeps its own gutter.
- **The type does not move.** Core's fluid type scales to `wideSize` by default, so raising it would have re-scaled every fluid font size (body copy, h3, h4 and the display sizes). `settings.typography.fluid` now pins the same 320px to 1400px pair it always used; `tests/fluid-type-presets.php` shows every size byte-identical and fails if the pair is left to follow the track.

### Tests
- **`tests/layout-width-system.php` pins the centred frame (33).** The five containers on the track or their 60rem, all `margin: 0 auto`, `padPage` off the header gutter, the `body.page` lift and no left-pinning, and `pageTrack` evaluated at 1024 / 1440 / 1720 / 1800 / 2000 / 2560. `tests/notes-css-extraction.php` follows. Each falsified.

## [14.0.0] - 2026-09-22 — the wordmark

### Fixed
- **Version references in this release say 14.0.0, not 13.10.0.** The theme uses the WordPress version shape (`docs/VERSIONING.md`): after 13.9 the next release is 14.0, and 14 is not a breaking flag. Seven comments in `critical.css`, `docs/BRAND.md` and four test files had been written ahead of the cut as 13.10.0.

### Changed
- **`/notes` runs gutter to gutter, the same edges as the header.** 13.9.1 set its 1400px grid to `margin: 0` so the title would start under the header brand; on a screen wider than 1400px that pinned the grid left and left an empty band on the right (owner, 2026-09-22: "I don't like this. At all."). The owner's condition for keeping it left: extend it to the other edge. `max-width: none` at `margin: 0`, so the rows, rules and search bar end where the nav ends. Previewed live at 2000px: rows from 36 to 1964px, the nav's right edge exactly. Up to 1400px nothing changes. `tests/layout-width-system.php` and `tests/notes-css-extraction.php` pin `none` and `0`.
- **The header carries the wordmark, not the monogram.** The home link is now "Juan Lentino" as live text in Bebas Neue, uppercase, in the `bone` ink (owner, 2026-09-22: the name is the brand on the page). The JL monogram stays the favicon, touch icon and OG image; the brand still appears once per page. Size is one `clamp(2rem, 10vw, 2.875rem)`, 32px at 320 to 46px from 460 up (max within 2.5x min, so it still honours zoom). Tracking is 0.04em, not the lockup's `wide` 0.15em: at 0.15em the name cannot hold one header row on a phone at any readable size. It sits in a box with the old mark's heights (64 / 48 / 36px), so the header is exactly as tall as before and `--sn-chrome-top` did not move. Measured on the live site with the built CSS at 1440, 1024, 781, 700, 480, 375 and 320: same header height as today at every width, text at the gutter (36 / 20 / 16px), no horizontal scroll. Previewed against a name beside the monogram and a lockup scaled to the mark; the owner chose the wordmark. `docs/BRAND.md` records the header wordmark and drops the removed `ultra` token from the descriptor line.
- **The hamburger runs to 781px, the theme's own mobile breakpoint, not core's 600.** Between them the full nav shared a row with the header brand: the header already wrapped to two rows at 600px with the monogram (106px tall instead of 70), and beside the wordmark it wrapped from 601 to 780. Now one 70px row at every width from 599 to 781; the menu opens as the theme's existing full-screen overlay (checked at 700px, all seven items). `critical.css` only, since the header is above the fold.

### Tests
- **`tests/brand-mark.php` pins the wordmark (32).** Live text in the home link with a matching `aria-label`, no SVG or monogram in the header, the five `assets/brand` files that keep the monogram as icon and OG image, the heading face, the 32px floor and zoom bound, the `bone` ink with no second dark rule, the box heights the header offsets are keyed to, once in the header and never in the footer, and the 600-781px hamburger. Each rule falsified. `tests/dark-mode.php` and the parity set (`.jl-mark` becomes `.sn-wordmark`) follow the rename.
- **`tests/styles-inline-size-limit.php` pins the shape of core's old nav copy, not its selector string.** It forbade the toggle selector anywhere in `critical.css`, which also caught the new 600-781px rule that SHOWS the toggle. It now fails on what the copy did: hide the toggle, or open an unbounded `min-width: 600px` query. Re-adding core's copy still turns both checks red.

