# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

- **The essays break out of the column: one gutter lane for every section, a lit pinned label, crop-marked figures, a tracklist and a runout.** It replaces the unreleased stand-down guard from #575, which kept the old cue off that essay. At 1200px and wider the essay's post content is now a named-line grid: a margin lane, the full 760px reading column (the first margin cue had narrowed the prose to 584px), and a right lane. Every "Section N" label sits in the margin lane and holds while its section scrolls, wrapped in a section group or loose in the content, so Section 6 of /provenance/over-detection now lines up with 1 to 5 with no content edit; a rule spans the lane and ends the last loose label. **Signal lock:** while a label is pinned (a scroll-state container query, Chrome 133 and later; elsewhere it is plain), a blood tally lights beside it and a short patch line bridges the gutter to the column. The tally is a border, so forced colors keep it. **Crop marks:** the detection and provenance diagram breaks out across the column and the right lane inside corner crop marks. **Tracklist** (`inc/essay-tracklist.php`): under the signal path, each essay's sections are set like the back of a sleeve (A1, A2, ...), each linked, each with its length at the byline's own reading rate (the plugin's `sn_reading_time_wpm`, 225 by default), and the byline's own total in the head. Where scroll target groups are supported (Chrome 140 and later) the section on screen lights, with no script. Where it renders it stands in for the essay's own "Jump to" line, which is hidden on screen only; the signed text is unchanged and the verifier reads the served HTML. **Runout** (`inc/essay-runout.php`): after the post-content element, the essay ends on one dead-wax line from the signed record: its short id, version, the date of the latest cut, the anchoring block once there is one, and a link to verify that record. Read from `sn_prov_view_data`, never `sn_prov_note_uid` (which mints an id); no record, or genesis only, means no line. Both are emitted outside the signed region. Rendered on the live essays in light and dark at 1200, 1440 and 2000 and at 375: prose 760px wide, all six labels at one lane edge, no horizontal scroll. New `tests/essay-tracklist.php` (16) and `tests/essay-runout.php` (12); `tests/house-detail.php` Group 10 rewritten for the grid (red before: 9 failed).

## [2026.10.15] - 2026-10-17 — a signal path for the essays, search that shows its work, and lanes for the résumé


- **Six details for pages that were still bare.** Approved by the owner from renders. None changes signed content.
  - **Signal path on the essays:** under each pillar's brow, the series in reading order (1.00, 2.00, 3.00), the current essay lit in blood, the others linked. `inc/essay-signal-path.php` uses the same order as Next essay and is emitted by the eyebrow filter, before the post-content element.
  - **Margin cue on the essays:** at 1200px and wider, each "Section N" label holds in the left gutter while its section scrolls. Body clips instead of hiding there, so sticky works; DOM order is unchanged.
  - **Search hits:** the matched term was not highlighted at all; it now carries a 2px blood underline. Each result also shows a hairline with a tick wherever the term occurs across the note, with the count as text (`inc/search-marker-lane.php`, at most 40 ticks).
  - **Arrangement lanes on /resume and /cv:** each role is drawn on one shared axis from the earliest role to today, open roles ending in blood. `inc/resume-lanes.php` reads the date line already on the page at render time, in English or Spanish, so the stored page never changes; a line it cannot read is skipped.
  - **Footer icon labels:** the five meta icons name themselves on keyboard focus (and on hover where the pointer hovers). A tooltip never reached a keyboard.
  - **Acquiring signal on /call:** until the booking calendar paints, or if a blocker stops it, the frame shows a static strip of the palette's test bars naming calendar.google.com.
  - Tests: `tests/essay-signal-path.php` (10) and `tests/surface-lanes.php` (16), both red before; `tests/house-detail.php` gains 9 CSS assertions. The dark-mode, contrast and width guards held the CSS to their rules during the build.


