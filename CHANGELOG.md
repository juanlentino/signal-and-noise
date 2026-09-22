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
- **More air in the home hero.** Headline to subtitle, subtitle to the red line and line to the buttons were 11 / 19 / 11px at 1440 (11 / 14 / 11 on phones); they are now the air scale, `air--xs` then `air--sm` twice: 24 / 36 / 36 at 1440, 20 / 31 / 31 at 1024, 16 / 24 / 24 at 375 (owner, 2026-09-22). In `critical.css`, so the first paint is right; the deferred accent margin matches. Measured with the entrance animation off: mid-flight it moves the line 30px, which first read as the buttons overlapping it on phones.
- **The home hero sits on the shared page frame.** 14.1.0 put every page on one frame but left `.sn-hero-inner` at its own 1100px, so the home headline started 110px further in than every other page at 1440 (170px against 60px) and 250px at 2000 (450 against 200); going from Home to About, the left edge jumped. It now uses `custom.pageTrack`, both copies (critical and deferred). Type, the 640px dek and the buttons are unchanged; at 1024px and on phones nothing moves. Previewed on the live home page at 1440: headline at 60px, the same edge as About. `1100px` leaves the max-width sweep's allowed list, since nothing uses it now, and `tests/layout-width-system.php` pins the hero on the frame in both files.

## [14.1.1] - 2026-09-22 — /resume uses the frame

### Fixed
- **`/resume` uses the wider frame.** From 1440px each experience entry's bullets set in two columns, so the entry fills the frame instead of stopping at 80ch with about a third of the row empty on a wide screen (owner, 2026-09-22); lines stay at about 55 characters at 1440 and 65 at 2000, and an item never splits across the columns. Below 1440, one column as before.
- **The resume summary no longer runs into the credentials box.** The two columns had core's 10.7px gap; they now have the `air--xl` step (64px from 1280 up). Measured live at 1440 and 2000: 64px between the summary and the box, no horizontal scroll.

