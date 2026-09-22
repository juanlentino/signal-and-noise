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
- **`/resume` prints as a two-page Letter document.** Phase 1 of `docs/RESUME-PDF.md`: the hand-made PDF behind the Download link has drifted from the page, so the page itself must print well. A `/resume on paper` block in `assets/css/print.css`, scoped to `body:has(.sn-resume-hero-split)` (the hook the plugin emits only on /resume), makes it one column, black on white, the header wordmark as the name, nav, footer, stats band, Download button and duplicate credential chips hidden, URLs printed in full. Measured with headless Chrome print-to-PDF on the live page: 7 pages before, 2 after; a Note printed identical text with and without the block.

### Fixed
- **The early career was missing from every printed resume.** Chrome keeps a closed `<details>` in `::details-content`, which the old `details > *` rule never reached, so the fold printed as its label only. Opened for paper, and laid out without a box so it breaks across pages cleanly.
- **Four print traps, each found by measurement.** An unnamed `@page` cannot be scoped, so the Letter size and margins use a named page and Notes keep their 2cm. `columns: 1` is still a multicol container and Chrome fragmented it into a page holding one bullet; lists are `columns: auto`. `main`'s 140px fixed-footer reserve printed a blank trailing page. Ligatures and wide tracking corrupted the PDF text layer ("workow", "VOT ING"), which applicant tracking systems read.

### Tests
- **`tests/resume-print.php` (17)** pins the named page, the hidden chrome, the light ground, expanded links, the fold, the list, the reserve, the text layer, unbroken publications and skills rows, and that every /resume rule is scoped. Each guard falsified. `tests/print-styles.php` stays green (it caught a bare `footer` selector against #318, removed). `bash tests/run.sh`: 153 suites, 0 failed, with the HTML API fetched as CI does.

### Docs
- **`docs/RESUME-PDF.md`**: the data-source map, Phase 1's rules and why, how to check a change, the Phase 2 decisions (Dompdf, navy and gold in the PDF only, Lato embedded, the new Content fields) and known limits.

## [14.1.2] - 2026-09-22 — the home hero on the page frame, with more air

### Fixed
- **More air in the home hero.** Headline to subtitle, subtitle to the red line and line to the buttons were 11 / 19 / 11px at 1440 (11 / 14 / 11 on phones); they are now the air scale, `air--xs` then `air--sm` twice: 24 / 36 / 36 at 1440, 20 / 31 / 31 at 1024, 16 / 24 / 24 at 375 (owner, 2026-09-22). In `critical.css`, so the first paint is right; the deferred accent margin matches. Measured with the entrance animation off: mid-flight it moves the line 30px, which first read as the buttons overlapping it on phones.
- **The home hero sits on the shared page frame.** 14.1.0 put every page on one frame but left `.sn-hero-inner` at its own 1100px, so the home headline started 110px further in than every other page at 1440 (170px against 60px) and 250px at 2000 (450 against 200); going from Home to About, the left edge jumped. It now uses `custom.pageTrack`, both copies (critical and deferred). Type, the 640px dek and the buttons are unchanged; at 1024px and on phones nothing moves. Previewed on the live home page at 1440: headline at 60px, the same edge as About. `1100px` leaves the max-width sweep's allowed list, since nothing uses it now, and `tests/layout-width-system.php` pins the hero on the frame in both files.

