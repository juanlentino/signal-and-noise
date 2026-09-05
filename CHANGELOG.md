# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

### Fixed
- The site header now uses its own padding. `.sn-header` is (0,1,0) and lost
  every left/right padding declaration to `.wp-block-group.has-background` at
  (0,2,0) — the header carries both classes from `parts/header.html`, and
  between two `!important` declarations specificity still decides, so the
  `!important` bought nothing. It wanted 1.25rem at ≤781px and 1rem at ≤480px
  and got 1.5rem and 1.25rem instead, in both `critical.css` and
  `responsive.css`. A second selector at (0,3,0) settles it without depending
  on source order; the bare `.sn-header` stays, so the rule still applies if
  the header ever loses `has-background`. `.sn-hero` carries the same classes
  and was unaffected only because it happens to ask for the value the generic
  rule already gives. (#281)

### Internal
- `tests/dead-css-declarations.php` resolves the cascade for every `sn-`
  component element against the markup that ships in this repo, and fails on
  any declaration that can never win. Both shipped instances of this class —
  the compare title (#279) and the header padding (#281) — were invisible in
  review because the CSS reads correctly; only the cascade disagrees.
  Three corrections it needed, each from a false positive it produced first:
  `@media` context is respected (flattening compared breakpoints that never
  coexist), `print.css` is excluded (enqueued `media='print'`), and pattern
  chains are seeded with `.wp-block-post-content` — without which the sweep
  reported ZERO for the very case it was built from. It skips selectors with
  ids, attributes, pseudo-classes or combinators rather than guessing, so it
  under-reports by design and asserts a vacuity floor on how many declarations
  it actually compared. Verified red against the pre-fix tree: 7 findings. (#281)

### Fixed
- The compare-columns pattern's column titles are H3, not H4. Notes use H2 for
  body subheads, so inserting the pattern produced an H2 → H4 jump — the same
  WCAG 1.3.1 heading skip `assets/css/article.css` already records for the
  corpus. `className` and the rendered `class` are unchanged, and no other
  pattern is touched. (#279)
- And the component's own CSS now actually applies. `.sn-compare__title` is
  (0,1,0) and loses every shared property to the generic body-subhead rules at
  (0,3,1), so the `1.1rem` written in v9.2.0 has been dead on single posts ever
  since — the titles rendered at whatever size their heading level resolved to.
  The H4 → H3 move alone would have swapped one wrong size for a bigger one, so
  a scoped override reclaims exactly the two properties the component declares
  and loses. It names NO element — (0,4,0) beats (0,3,1) on the class column —
  so the next heading-level change cannot silently re-break it. `line-height`
  stays with the generic rule deliberately: the component never asked for one.
  (#279)

## [12.18.7] - 2026-09-04 — the field that zoomed and never came back

### Fixed
- The notes search field no longer zooms the page on an iPhone and leaves it
  magnified. `font-size: max(0.9rem, 12px)` computes to **14.4px** (0.9rem is
  14.4px at the default root), and WebKit zooms in when a text-entry control
  under 16px takes focus without zooming back out on blur. It is front-end, so
  nothing in wp-admin's CSS guarded it. Raised to 16px at 782px only; the
  desktop size does not move. (#276)

### Internal
- `tests/ios-form-zoom-guard.php`, ported from the companion plugin where the
  same audit ran. Two things the plugin's version had to learn first, both kept
  here: the value parser resolves `max()`, `min()` and `clamp()` **by function**
  rather than by taking the smallest length — a digit-anchored regex cannot see
  `max(0.9rem, 12px)` at all, and "take the smallest" would flag
  `max(1.1rem, 12px)`, which computes to a perfectly safe 17.6px — and
  pseudo-element rules are excluded, since a `::placeholder` size zooms nothing.
  The first cross-repo sweep of this theme reported exactly one problem and it
  was the wrong one: `input[type="search"]::placeholder` at 12.5px, a false
  positive, sitting directly below the real 14.4px control it had missed.
  The guard's two populations are walked, not listed: stylesheets include the
  root `style.css` (an `assets/`-only walk skips the one sheet every page
  loads), and control tags are read from `inc/`, `patterns/`, `parts/` and
  `templates/`. The derived control-class list is legitimately EMPTY here —
  the theme's single control is styled by element plus ancestor — so the pin is
  on the tag count, which is what distinguishes "no class-styled controls" from
  "the walk opened nothing". (#276)

