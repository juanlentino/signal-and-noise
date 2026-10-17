# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

## [2026.10.14] - 2026-10-17 — catalog numbers, a quiet fold and a tidier finish


- **Five fixes from a live sweep of today's design work.** Section numerals were reaching standalone pages: /provenance has four h2s and no table of contents, so it showed 01 to 04 with nothing to match. They are now notes only (`body.single-post`), as approved. In a numbered note's TOC, References sat 25px left of the numbered entries; every entry now gets the same 2.6em numeral slot, References an empty one, so they all start on one line. The crop-mark focus ticks sat 12px out and touched a neighbouring button 11px away on home at 768px; they now sit 9px out, still clear of the 2px outline and its 3px offset. /resume printed the running head and folio (RESUME / juanlentino.com / 01 / 03); its named page now turns all three margin boxes off. The contact message box grew without limit (40 lines made it 1414px tall); it now stops at 70% of the screen and scrolls inside, so the send button stays in reach. `tests/house-detail.php` pins each (red before: 7 failed).

- **Catalog numbers on /notes, and a quiet fold for archive months.** Each row carries the note's catalog number above its date, in the spec column's rust: its place among published notes, oldest first, so the newest carries the count of everything published. `sn_notes_catalog_number()` reads the published order once per request (one ids-only query), so a note has the same number on the index, a tag archive or a search. The pinned Start-here row keeps its flag instead, and a scheduled or draft note has no number. The number is hidden from screen readers, which already have the list order. The orphaned `sn-note-counter` reset in `notes.css` is gone. Opening a collapsed month now fades its rows in over 120ms, opacity only, via `::details-content`; the global reduced-motion reset makes it instant. Approved by the owner from renders. `tests/notes-index-row.php` and `tests/house-detail.php` pin both (red before the change).


