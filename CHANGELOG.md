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
- **readme.txt meets the Theme Handbook's metadata rules.** `Contributors:` is now the owner's wordpress.org username (`juanml`), not a display name, and a `== Copyright ==` section declares the two bundled fonts: Bebas Neue (Copyright 2019 The Bebas Neue Project Authors) and DM Mono (Copyright 2020 The DM Mono Project Authors), both SIL OFL 1.1, with sources and the files they cover. The copyright lines are read from each font's own `name` table.

### Docs
- **`docs/RESUME-PDF.md` describes the resume PDF as shipped** (plugin 17.7.0 through 17.7.2): the Website field, the location fallback to the web contact line, the phone checkbox off by default with a private copy that is never stored, why that control must be a checkbox and not an `os-switch`, and times shown in the site timezone.

## [14.2.0] - 2026-09-22 — the resume prints

### Changed
- **`/resume` prints as a two-page Letter document.** Phase 1 of `docs/RESUME-PDF.md`: the hand-made PDF behind the Download link has drifted from the page, so the page itself must print well. A `/resume on paper` block in `assets/css/print.css`, scoped to `body:has(.sn-resume-hero-split)` (the hook the plugin emits only on /resume), makes it one column, black on white, the header wordmark as the name, nav, footer, stats band, Download button and duplicate credential chips hidden, URLs printed in full. Measured with headless Chrome print-to-PDF on the live page: 7 pages before, 2 after; a Note printed identical text with and without the block.

### Fixed
- **The early career was missing from every printed resume.** Chrome keeps a closed `<details>` in `::details-content`, which the old `details > *` rule never reached, so the fold printed as its label only. Opened for paper, and laid out without a box so it breaks across pages cleanly.
- **Four print traps, each found by measurement.** An unnamed `@page` cannot be scoped, so the Letter size and margins use a named page and Notes keep their 2cm. `columns: 1` is still a multicol container and Chrome fragmented it into a page holding one bullet; lists are `columns: auto`. `main`'s 140px fixed-footer reserve printed a blank trailing page. Ligatures and wide tracking corrupted the PDF text layer ("workow", "VOT ING"), which applicant tracking systems read.

### Tests
- **`tests/resume-print.php` (17)** pins the named page, the hidden chrome, the light ground, expanded links, the fold, the list, the reserve, the text layer, unbroken publications and skills rows, and that every /resume rule is scoped. Each guard falsified. `tests/print-styles.php` stays green (it caught a bare `footer` selector against #318, removed). `bash tests/run.sh`: 153 suites, 0 failed, with the HTML API fetched as CI does.

### Docs
- **`docs/RESUME-PDF.md`**: the data-source map, Phase 1's rules and why, how to check a change, the Phase 2 decisions (Dompdf, navy and gold in the PDF only, Lato embedded, the new Content fields) and known limits.

