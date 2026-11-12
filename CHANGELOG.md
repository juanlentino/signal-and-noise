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
- **/resume's lane switch, edition marks and hunk rows are gone from the theme: the page is one résumé.** The plugin's one-résumé change (owner, 2026-11-12) stops emitting the music and operations lanes, the switch, the bone "only in this version" bar, its legend, the hunk rows and the head split, and stops serving `?lane=operations` alternates. Their rules had nothing left to style, so they are deleted. `assets/css/resume-switch.css` and `assets/css/resume-marks.css` and their enqueue (`inc/cms-page-styles.php`) are removed. The rules that still match (the hero's headline and subhead, receipts, "What I've Built", the formats line) move to `assets/css/resume.css` with the same declarations. Two of them lose the lane wrapper that scoped them and keep their specificity another way: the headline's `text-box` reset is scoped to `.sn-resume-hero-split` (0,3,0, over critical.css's cap-height trim) and the receipt-in-a-paragraph reset to `.wp-block-group` (0,4,2, over article.css's prose link). Also removed: the lane prerender rule (`inc/speculation.php`), the `?lane=operations` JSON alternate (`inc/content-json.php`), the `*_operations` format labels in llms.txt (`inc/llms-txt.php`), and the lane rules in `assets/css/print.css` and `assets/css/resume.css`. The beacon is unchanged. Pairs with the plugin's one-résumé change and should ship after it. Test: `tests/resume-one.php` (new, replacing `tests/resume-switch.php` and `tests/resume-marks.php`) pins the surviving rules and their specificity and fails when the headline or receipt scope is dropped; `tests/cms-page-styles.php`, `tests/speculation.php`, `tests/content-json.php`, `tests/llms-txt.php` and `tests/dark-mode.php` now check the one-résumé state.

## [2026.11.10] - 2026-11-11 — The /resume row link carries a line; the live contrast check is not traffic

### Fixed
- **The /resume row that points to the other lane underlines its link (WCAG 1.4.1).** A hunk row ("In Operations & AI Strategy: WHAT I'VE BUILT") kept its link's underline off, so beside the row's label the link differed by color alone: 1.66:1 in light and 2.68:1 in dark against the label, under the 3:1 a color-only link needs. The live contrast check has failed on it since 2026-11-10. `assets/css/resume-marks.css` now draws the link's own 1px line in its own color at rest; the blood fill on hover and focus is unchanged, and the receipts, the formats line and the lane switch are untouched. Checked on the live page with the rule applied in a browser: the line shows in both palettes in the link's color. Test: `tests/resume-marks.php` now requires the line and fails against the previous rule.
- **The live contrast check no longer counts as site traffic.** `tools/contrast-ci.mjs` loads every sitemap page in headless Chromium, and each page ran the analytics beacon, so every push to `main` and every nightly run recorded 20 to 29 views from GitHub's runners, filed as `suspect`. The route moved into `tools/contrast-route.mjs`, which now aborts the beacon (`/_sn/px`) for the whole browser context before it leaves the runner, `sendBeacon` and `fetch` alike, in every frame. A blocked beacon is not a route issue and cannot make a run inconclusive; asset failures are recorded as before. The log prints how many beacons were blocked and warns when a run blocked none (the endpoint moved). What the check measures is unchanged. With the optional `SN_FIRST_PARTY_TOKEN` repository secret set, the run also sends `x-sn-first-party` on same-origin requests, the header the analytics Workers already accept as proof of this site's own tooling. `smoke-test.yml` uses curl and runs no beacon. Test: `tests/js/contrast-route.test.cjs` (new), red against the route without the block; a real Chromium run against a local page confirmed both beacon forms reach the route and none reach the server.

