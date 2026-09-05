# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

## [12.18.9] - 2026-09-05 — the presets the site actually serves

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

