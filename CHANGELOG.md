# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

## [14.1.0] - 2026-09-22 — one centred frame

### Changed
- **Every page is centred again, on one frame, and the frame is wider on big screens.** 13.9.0 moved `/uses`, `/now`, `/index` and `/accessibility` to the left under the mark, and 13.9.1 and 14.0.0 did the same to `/notes`; on a wide screen that left an empty band on one side, and every way of holding both edges meant stretching text, leaving holes inside the layout or cropping the photo (owner, 2026-09-22: "Just make the whole thing as it was before. Even /notes. But push the width enough"). The page containers are back to `margin: 0 auto` and their pre-13.9.0 side padding, `/index` and `/accessibility` back to 60rem. What changes is the track: a new `theme.json` token, `custom.pageTrack` = `max(1320px, min(wideSize, 100vw - 400px))`, which is exactly the old 1320px on every screen up to 1720px wide and grows to 1600px (the new `wideSize`) at 2000. The pages built in the editor (About, Services, Music, Resume, Contact) carry their own 1320px in the page content, so `critical.css` lifts them to the same track, scoped to `body.page`; a Note's body is untouched. Measured live with the built rules: at 1440 and 375 About and Resume are pixel-identical to today; at 2000 every page runs 200 to 1800px (960px for the two reading pages); no horizontal scroll. The header keeps its own gutter.
- **The type does not move.** Core's fluid type scales to `wideSize` by default, so raising it would have re-scaled every fluid font size (body copy, h3, h4 and the display sizes). `settings.typography.fluid` now pins the same 320px to 1400px pair it always used; `tests/fluid-type-presets.php` shows every size byte-identical and fails if the pair is left to follow the track.

### Tests
- **`tests/layout-width-system.php` pins the centred frame (33).** The five containers on the track or their 60rem, all `margin: 0 auto`, `padPage` off the header gutter, the `body.page` lift and no left-pinning, and `pageTrack` evaluated at 1024 / 1440 / 1720 / 1800 / 2000 / 2560. `tests/notes-css-extraction.php` follows. Each falsified.

