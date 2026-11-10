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
- **Each AI ability's `tokens_used` now says what it is.** The output schema of all five generative abilities describes the figure as an output-only estimate (characters / 4) that leaves out the prompt. The field keeps its name, so nothing that reads it breaks. Test: `tests/ai-tokens-used-schema.php`.
- **The /notes summary ability runs on the official AI plugin when it can.** `signal-and-noise/ai-generate-page-note-summary` keeps its name and its `{summary, post_id, tokens_used}` answer, but when the AI plugin registers `ai/summarization` it now runs that ability for the post, with the /notes voice and the word cap appended to the official instruction for that one call. An error from the official ability is returned as is. Without the AI plugin, the theme's own path runs as before. Test: `tests/ai-note-summary-official.php`.

## [2026.11.6] - 2026-11-07 — The record sleeve for /services

### Added
- **A record sleeve for `/services`.** `.sn-sides` and `.sn-side` draw Side A and Side B: a photo, a big name, a line, a numbered track list and one link stretched over the whole side. The home page's pulse and playhead close the sleeve. `.sn-service-crop` keeps the Consulting photos at one shape. Three crops of the desk photo ship in `assets/images/services/`, so Consulting has images like the old service cards. No image is generated.

### Removed
- **The `.sn-door-link` styles.** The hub no longer uses them. `.sn-door` stays for the rows on `/services/consulting`, now two columns.

