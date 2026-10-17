# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

- **The runout reads the anchoring status the way the page does, and essays drop the arrival rule.** Live on /provenance/over-detection the runout said "anchoring" beside the page's own "Verified v1": it tested for a status of `anchored`, which the plugin never writes (its anchored state is `confirmed`, and a genesis commit counts once its root is confirmed). It now asks the plugin's `sn_prov_present_status` and `sn_prov_genesis_root_state`, the same reading as the chip, and shows the block when one is on record, else "verified". The test fixture had used the wrong word too, which is how it passed; it now uses the plugin's vocabulary and covers both cases (red before: 3 failed). Separately, the rule base.css draws beside a jumped-to heading is gone on the essays (owner, from screenshots): only a loose section's heading carries an id, so it marked Section 6 alone, and the lane and tracklist already say where you are.

## [2026.10.17] - 2026-10-17 — the tracklist sits under the title


- **The tracklist sits between an essay's title and Section 1, and the arrival rule no longer strikes through a margin label.** Owner, from the installed site: the tracklist rendered above the title. It is emitted outside the signed element, so it cannot move inside it; instead the essay's `<main>` is now the grid and the post-content element is unboxed (`display: contents`), so the tracklist and the content share one grid and `order` shows it after the hero at every width. `reading-flow: grid-order` keeps the tab order on what is seen where supported. The served HTML is unchanged, so signatures still verify. Jumping to a section (for example from the tracklist) drew base.css's arrival rule 0.6em left of the heading, which at display size landed on the "Section N" label; in the essay grid it now hugs the heading inside the gutter. `tests/house-detail.php` pins both (red before: 5 failed).


