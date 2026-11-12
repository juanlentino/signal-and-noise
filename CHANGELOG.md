# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

### Removed
- **The beacon no longer reads a résumé lane.** /resume is one résumé since 2026-11-12, so the lane read (`readLane()`, the `lane` key on /resume beacons, 2026-11-02 to 2026-11-12) found nothing and sent nothing. Its code, the comment naming the deleted `resume-switch.js`, and the pinned `tests/js/fixtures/beacon-payload-resume.json` are gone (`assets/js/sn-beacon.js`). Test: `tests/js/beacon-resume-lane.test.cjs` now asserts no beacon carries a lane, even with the old lane markup (fails against the old beacon).
- **`snCopy.liveRegion()`, the one-release shim for pages cached before the switch to `wp.a11y.speak()` (2026-11-10), is gone** (`assets/js/sn-copy.js`). Test: `tests/js/sn-copy-stale-callers.test.cjs` asserts it is absent and the copy helper remains.

## [2026.11.11] - 2026-11-12 — One résumé: the switch styles are gone

### Removed
- **/resume's lane switch, edition marks and hunk rows are gone from the theme: the page is one résumé.** The plugin's one-résumé change (owner, 2026-11-12) stops emitting the music and operations lanes, the switch, the bone "only in this version" bar, its legend, the hunk rows and the head split, and stops serving `?lane=operations` alternates. Their rules had nothing left to style, so they are deleted. `assets/css/resume-switch.css` and `assets/css/resume-marks.css` and their enqueue (`inc/cms-page-styles.php`) are removed. The rules that still match (the hero's headline and subhead, receipts, "What I've Built", the formats line) move to `assets/css/resume.css` with the same declarations. Two of them lose the lane wrapper that scoped them and keep their specificity another way: the headline's `text-box` reset is scoped to `.sn-resume-hero-split` (0,3,0, over critical.css's cap-height trim) and the receipt-in-a-paragraph reset to `.wp-block-group` (0,4,2, over article.css's prose link). Also removed: the lane prerender rule (`inc/speculation.php`), the `?lane=operations` JSON alternate (`inc/content-json.php`), the `*_operations` format labels in llms.txt (`inc/llms-txt.php`), and the lane rules in `assets/css/print.css` and `assets/css/resume.css`. The beacon is unchanged. Pairs with the plugin's one-résumé change and should ship after it. Test: `tests/resume-one.php` (new, replacing `tests/resume-switch.php` and `tests/resume-marks.php`) pins the surviving rules and their specificity and fails when the headline or receipt scope is dropped; `tests/cms-page-styles.php`, `tests/speculation.php`, `tests/content-json.php`, `tests/llms-txt.php` and `tests/dark-mode.php` now check the one-résumé state.

