# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

## [13.8.0] - 2026-09-22 — BREAKING: tokens and air, the ultra letter-spacing token removed

### New
- **The fluid values the theme repeats are tokens now: seven spacing steps and four page type sizes.** `theme.json` `settings.custom.air` (`xxs` to `xxl`, emitted as `--wp--custom--air--*`) and four role sizes in `settings.custom.fontSize` (`pageHeadline`, `pageDek`, `pageSectionLabel`, `pageItem`). Each is an EXACT value that already recurred, so nothing on the site moves: 22 spacing sites and 16 type sites read them. The owner chose this over two rate-based scales. The brief's eight-step table would have moved 48 of the 84 `clamp()` sites (up to 36px at 1440, 16px of page padding on phones, headings by 11px) and folded 26 type sizes into "air"; its step values mostly did not exist in the repo. The 28 one-off values stay literal, each with a `/* not a step: */` comment naming the nearest token and how far it would move; one of them would fold within 0.8px and stays literal because the choice was "nothing moves". Type sizes stay out of `air`, next to the existing `display*` sizes, so spacing and type are separate scales.
- **`tests/air-scale.php` (24) guards the scale.** Every scale `var()` names a token that exists (a typo'd var resolves to nothing and the property silently drops); no stylesheet re-types a token's value as a literal `clamp()`; every other `clamp(rem, vw, rem)` carries a reason; every font-size `clamp()` keeps max within 2.5x min so `vw` type still honours browser zoom (WCAG 1.4.4, and the bound modern-web-guidance's fluid-scaling guide states; all 26 in CSS and the four `display*` tokens pass). Comments are stripped across the whole file, because several sheets quote old values in multi-line comments; the reason marker survives with its text blanked, because a reason usually quotes the clamp it explains. Each rule falsified on its own.

### Removed
- **The `ultra` letter-spacing token (`--wp--custom--letter-spacing--ultra`, 0.3em) is gone.** Nothing in the theme has read it since 13.6.1 moved the footer descriptor to `wide`, and the block that documented it was deleted in 13.7.0. Anything outside the theme reading that property gets nothing.
- **The hero veil.** `.sn-hero::before` drew a gradient from 20% to 95% of the page ground over a hero whose ground is that same colour (`backgroundColor: void`, no image, the only hero on the site), so in light it was white on white and in dark `#0a0a0a` on `#0a0a0a`, at every stop. 13.7.0 kept it as "one decorative gradient"; it had never been visible. Removed with its six token declarations, the `.sn-hero > *` rule that existed only to lift content above it, both copies (critical and layout) and their two entries in the declared parity set. `.sn-hero` keeps `position: relative; overflow: hidden`, which the entrance animation still needs. The June 2026 note that settled the hero's negative space described the veil as fog over the grain; the grain sits above everything at `z-index: 9999` and the veil sat inside the hero at 1, so it never touched the grain either. Nothing on screen changes.

### Changed
- **The header's height is written once.** `--sn-chrome-top` and `--sn-chrome-bottom` (the header and utility-bar footprint) are defined in `critical.css` and overridden at 781px and 480px; body padding, the hero's `vh` and `dvh` min-heights and `scroll-padding-top` all read them. They replace 21 hand-typed numbers across four files, which is the shape that let 13.7.0's hero fix reach the `vh` line and not the `dvh` one. Every value is unchanged. `tests/critical-deferred-parity.php` Group 2 now pins the construction (defined where each breakpoint needs it, read by every consumer, typed as a number nowhere, red on a hand-typed `dvh` line three ways) instead of comparing two numbers after the fact; `tests/brand-mark.php` reads the variable.
- **Named tokens for what `theme.json` hardcoded.** `elements.heading` line-height, `elements.h1`/`h2`/`button` and `core/navigation` letter-spacing read `custom` tokens; `snug` (`-0.01em`, what `h2` carried) joins `letterSpacing`. In CSS, `-0.02em` x3 and `line-height: 1.1` x4 read the same tokens, including the mobile-menu rule, which is duplicated in `critical.css` and `layout.css` and changes in both. `custom.padPage` names the page padding shorthand, byte-identical in four page sheets (`index`, `accessibility`, `uses`, `now`).
- **Font sizes small / medium / large / x-large are rem (`0.8125` / `1.25` / `2.25` / `2.625`), not px.** Identical at a 16px root; they now follow a reader's font setting. Core emits rem strings for them now, so `tests/fluid-type-presets.php` keeps the pre-13.8 px strings as a fixture and checks the new ones render within 0.01px at every tested width (the largest drift is 0.007px, from core rounding the rem form).
- **Spacing step 10 is 0.3rem, not 0.5rem.** Steps 30 to 80 are a x1.5 ramp and 0.44rem (step 20) sits exactly x1.5 below 0.67rem; 0.5rem broke it. Its nine consumers (card meta gaps) tighten from 8px to 4.8px. Step 20's consumers do not move. The one visible change in this release.

