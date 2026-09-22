# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

## [13.7.1] - 2026-09-22 — air in the header

### Fixed
- **The desktop hero's height fix from 13.7.0 never reached a modern browser.** 13.7.0 corrected `.sn-hero { min-height: calc(100vh - 108px - 90px) }` to the 100px body offset, but only the `100vh` fallback: the `100dvh` line beneath it, which every current browser applies, still subtracted 108. The parity pin missed it because it matched `100vh` alone. Both lines now agree in every breakpoint, and `tests/critical-deferred-parity.php` reads every viewport unit the hero uses and fails when a `vh`/`dvh` pair disagrees (red against the stale 108).
- **On phones the header covered the top of every page.** At 480px and below the header is 68px (12px padding top and bottom, 4px clear space either side of a 36px mark) and `body` padded 65, measured live at 375px on 2026-09-22. The phone offset is now 78px, the same roughly 10px gap tablets have (80 over a 70px header), with the hero and `scroll-padding-top` following. Part of the overlap predates 13.6.0; the rest was 13.6.0 sizing the phone mark without counting the header's own padding. `tests/brand-mark.php` Group 7 now computes the header at every breakpoint (the breakpoint's vertical padding, else the part's, plus clear space plus that breakpoint's mark) and fails when `body` pads less; it was red on this exact case.

### Changed
- **The header breathes and has no rule.** The 1px `concrete` line under the header (13.7.0) goes: a solid bar over a ground of the same colour has an edge already (owner, 2026-09-22). The separation comes from air instead: the header part pads 16px top and bottom (`spacing-40`, was 7px) and 36px at the sides (`spacing-60`, was 16px), and the footer part takes the same 36px sides so the mark and the social icons share one left edge and the nav and the copyright one right edge. Desktop only: tablets keep their 0.44rem vertical padding through the 781px breakpoint, and both parts already carry their own side padding at 781px and below. The desktop header grows from 86 to 104px, so `body` padding-top goes 100 to 118, the hero reserves 118, and `scroll-padding-top` goes 116 to 134. `tests/brand-mark.php` Group 5 reads the header's vertical padding from the part through theme.json's spacing scale instead of a literal, and `tests/dark-mode.php` pins the absence of the rule.

