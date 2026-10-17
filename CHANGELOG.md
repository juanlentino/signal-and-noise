# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

- **The essays' margin cue no longer splits /provenance/over-detection.** That essay's Section 6 was never wrapped in a section group: its label and heading sit straight in the page content. So the cue moved Sections 1 to 5 into the gutter and left 6 at the column edge, which the owner flagged. The cue's rules now stand down on any essay with a loose section label (a blood paragraph followed directly by a heading at the top of the content), so that page reads as one piece, as it did before. The other two essays keep the cue. Checked live: the guard is false on over-detection and true on as-substrate and without-institutions. Wrapping Section 6 in the editor would bring the cue back there, but that is a content edit and mints a new signed version, so it is the owner's call. `tests/house-detail.php` pins the guard (red before: 2 failed).

## [2026.10.15] - 2026-10-17 — a signal path for the essays, search that shows its work, and lanes for the résumé


- **Six details for pages that were still bare.** Approved by the owner from renders. None changes signed content.
  - **Signal path on the essays:** under each pillar's brow, the series in reading order (1.00, 2.00, 3.00), the current essay lit in blood, the others linked. `inc/essay-signal-path.php` uses the same order as Next essay and is emitted by the eyebrow filter, before the post-content element.
  - **Margin cue on the essays:** at 1200px and wider, each "Section N" label holds in the left gutter while its section scrolls. Body clips instead of hiding there, so sticky works; DOM order is unchanged.
  - **Search hits:** the matched term was not highlighted at all; it now carries a 2px blood underline. Each result also shows a hairline with a tick wherever the term occurs across the note, with the count as text (`inc/search-marker-lane.php`, at most 40 ticks).
  - **Arrangement lanes on /resume and /cv:** each role is drawn on one shared axis from the earliest role to today, open roles ending in blood. `inc/resume-lanes.php` reads the date line already on the page at render time, in English or Spanish, so the stored page never changes; a line it cannot read is skipped.
  - **Footer icon labels:** the five meta icons name themselves on keyboard focus (and on hover where the pointer hovers). A tooltip never reached a keyboard.
  - **Acquiring signal on /call:** until the booking calendar paints, or if a blocker stops it, the frame shows a static strip of the palette's test bars naming calendar.google.com.
  - Tests: `tests/essay-signal-path.php` (10) and `tests/surface-lanes.php` (16), both red before; `tests/house-detail.php` gains 9 CSS assertions. The dark-mode, contrast and width guards held the CSS to their rules during the build.


