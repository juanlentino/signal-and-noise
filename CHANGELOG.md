# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

## [2026.10.17] - 2026-10-17 — the tracklist sits under the title


- **The tracklist sits between an essay's title and Section 1, and the arrival rule no longer strikes through a margin label.** Owner, from the installed site: the tracklist rendered above the title. It is emitted outside the signed element, so it cannot move inside it; instead the essay's `<main>` is now the grid and the post-content element is unboxed (`display: contents`), so the tracklist and the content share one grid and `order` shows it after the hero at every width. `reading-flow: grid-order` keeps the tab order on what is seen where supported. The served HTML is unchanged, so signatures still verify. Jumping to a section (for example from the tracklist) drew base.css's arrival rule 0.6em left of the heading, which at display size landed on the "Section N" label; in the essay grid it now hugs the heading inside the gutter. `tests/house-detail.php` pins both (red before: 5 failed).


