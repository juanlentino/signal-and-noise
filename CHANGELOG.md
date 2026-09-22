# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

## [13.9.0] - 2026-09-22 — widths: text pages on a track, measures on three tiers

### New
- **`tests/layout-width-system.php` sweeps every `max-width` in `assets/css`, `blocks/` included.** The template sweep only ever saw block markup; the containers and measures live in the stylesheets, and that is where the 1320px / 60rem / 52ch / 62ch / 48ch scatter was. Allowed without comment: 1400px, 760px, 1100px, 80ch, 72ch, 60ch, 46ch, 100%, none. Anything else needs `/* not a track: <reason> */` on its line. `@media (max-width: ...)` conditions are skipped (a breakpoint is not a width), and a reason that quotes a width is not read as one. It also pins the five page containers on their tracks. Red on an off-track container and on an off-tier measure; silent on a media condition and on a quoted width.

### Changed
- **The five text-page containers sit on a track, and the reading measures sit on three tiers.** A page earns the 1400px wide track only with a real multi-track grid on its own content: `/notes` runs two (the pillars' 1.15fr / 1fr, the rows' 108px / 1fr / auto) and moves from 1320px to 1400px. `/uses` and `/now` move from 1320px to the 760px reading track; `/uses` is `1fr` becoming `1fr auto`, a label and a value, which is a list and not a grid. `/index` and `/accessibility` move from 60rem to 760px. They stay left-aligned under the mark rather than centring (owner, 2026-09-22): a 760px column centres on a wide screen by default, which moved their left edge from under the JL mark to about 292px. The four sit at `margin: 0`, and their side padding is `--sn-gutter`, a new variable in `critical.css` whose desktop value is the header part's own `spacing-60` and which the header's 781px and 480px rules now read instead of typing 1.25rem and 1rem; `custom.padPage` takes its sides from it. Measured on the live `/uses`: text and mark at 36px (1440, 1024, 800), 20px (700) and 16px (375). At phone width that also closes a 4px gap the page padding and the header gutter had before this release. `tests/layout-width-system.php` pins that the gutter is the header's preset rather than a copy of its value, that no header breakpoint rule types a number, and that the four pages sit at the left. Measures round to the narrow end of each cluster: deks 52ch x3 and 48ch go to 46ch, section text 62ch x2 goes to 60ch, prose stays at 72ch. Measured for accessibility at 320px (no horizontal scroll on `/uses` or `/now`, WCAG 1.4.10) and against the 80-character line limit (WCAG 1.4.8): 760px holds 79 DM Mono characters at 1rem, where `/uses` and `/now` body lines at 1320px ran well past 80.
- **Every width that is not a track or a tier says why.** The three `80ch` sites keep it, with the measured reason: the notes excerpt is 691px at 0.9rem (the 72ch tier would be 622px, narrower than its title, and an earlier 65ch triggered WordPress' constrained-layout auto-centring as a left indent), resume lines are 730px at 0.95rem, publication titles 768px at 1rem. The brief described all three as "the 760px track"; only the last is. The excerpt's rationale moves from `components.css` to the value it explains, correcting its two out-of-date claims (the track was 760px, not 720px, and the rule does set a max-width). The hero subtitle (640px, both copies), the footnote popover, the link preview, both overlay panels and the provenance SVG carry `/* not a track: */` reasons.

