# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

- **Home is exactly one screen, with a playhead and a latest line on the pulse.** Owner, naming home: "Build the latest line and the playhead as rendered ... and remove the existing extra scroll so home is exactly one screen." The hero's height reserved 90px for the footer, but `<main>` added layout.css's 140px fixed-footer reserve and two block gaps, so home ran 71px past a 1440x900 screen (and 110px past 375x812, where the footer is static and 123px tall). On home the page column is now a flex column one screen tall; main reserves only the fixed footer (nothing at 480px and under); the hero grows into the rest, never below its content; short screens get a height-scaled headline and tighter padding, and short phones set the two buttons side by side. Measured at 1440x900, 1280x720, 1366x768, 2000x1125, 768x1024, 375x812, 375x667 and 390x844: the page is exactly the screen at every one, with no horizontal scroll, and the latest line clears the buttons (13px at the tightest). **Playhead:** a blood hairline at the pulse's "now" end, lit by the pulse's own inline rule, so no waveform means no playhead; it fades in after the waveform draws, and forced colors drop it with the pulse. **Latest line** (`inc/home-latest-line.php`): the newest published note's catalog number, title and date, one link, appended after the post-content element (the home Page's own content is untouched) and positioned over the hero so it adds no height; "Lo último" on the Spanish home. New `tests/home-latest-line.php` (19) and a playhead assertion in `tests/hero-pulse.php` (red before).

## [2026.10.18] - 2026-10-17 — the runout reads verified, and essays drop the arrival rule


- **The runout reads the anchoring status the way the page does, and essays drop the arrival rule.** Live on /provenance/over-detection the runout said "anchoring" beside the page's own "Verified v1": it tested for a status of `anchored`, which the plugin never writes (its anchored state is `confirmed`, and a genesis commit counts once its root is confirmed). It now asks the plugin's `sn_prov_present_status` and `sn_prov_genesis_root_state`, the same reading as the chip, and shows the block when one is on record, else "verified". The test fixture had used the wrong word too, which is how it passed; it now uses the plugin's vocabulary and covers both cases (red before: 3 failed). Separately, the rule base.css draws beside a jumped-to heading is gone on the essays (owner, from screenshots): only a loose section's heading carries an id, so it marked Section 6 alone, and the lane and tracklist already say where you are.


