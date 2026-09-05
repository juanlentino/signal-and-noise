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
- theme.json now declares the spacing scale and the four core-named font sizes
  the site actually serves, and owns them. Since WordPress 6.6 core drops a
  theme preset from the theme origin when its slug collides with a core default
  unless the family's `default*` flag is false; `defaultPalette` was set in
  v3.6.0, `defaultSpacingSizes` and `defaultFontSizes` never were. So slugs
  20–80 (declared 1rem…12rem) served core's 0.44…5.06rem for the theme's whole
  life, and small/medium/large/x-large served core's 13/20/36/42px — including
  the body text (`styles.typography.fontSize`) and the single-post H2 rule. The
  site was tuned against what served, so the fix declares the served values and
  sets both flags: `wp_get_global_stylesheet()` on WordPress 7.1 is byte-identical
  before and after (19,379 bytes). No visual change; the editor's size pickers
  now show the sizes that apply; a future change to core's default scale can no
  longer move this site. Restoring the originally declared values would change
  spacing everywhere by 2–3× and is a design decision left open. (#284)
- Notes index rows are H2, not H3. The only heading above a row on /notes/,
  every tag archive and every search view is the H1 headline — the section
  label beneath it is a `<p>` wired through `aria-labelledby` — so every row
  skipped a level (WCAG 1.3.1). The 404 page's copy of the same row has been an
  H2 since it was built. The row title's CSS is class-only and sets its own
  font, size and margin, so nothing moves. (#285)

### Internal
- `tests/theme-json-default-presets.php`: derives core's default preset slugs
  per family from a vendored copy of core's `wp-includes/theme.json` (7.1) —
  the spacing slugs the way core derives them from `spacingScale` — and fails
  whenever theme.json declares a colliding slug without the flag. Negative
  control: the pre-fix theme.json turns it red on exactly the two families. (#284)
- Dead CSS removed, each traceable to a commit: the "ON-SITE SEARCH" block in
  components.css (three `.sn-search-*` rules for a `templates/search.html`
  deleted in 69ce71a) and the `.sn-post-frontmatter__pillar-slot` rules in
  article.css (from a683390, reverted in 2cf1e2f — the revert restored the
  markup, not the CSS). (#285)
- The article.css "BODY HEADING SCALE" comment no longer reasons from a
  `.sn-note-title` override that never existed (the single-note H1 is sized by
  an inline block style) or from an x-large value that never served. (#285)

## [12.18.8] - 2026-09-05 — two declarations that could never win

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

