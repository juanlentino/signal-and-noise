# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

### Added
- **The template furniture is blocks.** Nine renderers that were `[shortcodes]` inside block templates — provenance chip and record, related notes, cited-by, share, reply, updated date, pillar link, the high-contrast toggle — are WordPress 7.0 PHP-only blocks (`supports.autoRegister`, no JavaScript build; `inc/blocks-php-only.php`): listed by name in the site editor's List View and movable. Byte-identical on the page: each block returns `wpautop( shortcode )`, exactly what the `core/shortcode` block produced, and `tests/blocks-php-only.php` pins it per block. The templates place each block exactly where its shortcode was. The shortcodes stay registered through 13.1.x for post content; 13.2.0 retires them.
- **Reading time is a bound paragraph** in three slots (note front matter, the home card, the notes card) through the `signal-noise/post-field` source the theme has carried since v9.11.0 — the migration v9.11.1 reverted, retried now that PHPStan is a required check and `tests/block-bindings-templates.php` pins every binding's key and empty inner. The pillar-card eyebrows keep the plugin's `[sn_reading_time slug]` (prefix text + figure in one paragraph; a binding would replace the prefix).
- **The closing part is hooked into `single` by rule** (`hooked_block_types`, `inc/block-hooks.php`): a single template that lacks it gets it after the content. The shipped template, which places it, declares `ignoredHookedBlocks` on its `core/post-content` anchor — core applies hooks to file-based templates too, and without that line the part would render twice.
- `post-frontmatter` and `post-closing` are declared template parts in a new **Article** area (`theme.json`, `inc/template-part-areas.php`) — they were "General" in the site editor.
- **Speculative loading** raised to prerender/moderate (`inc/speculation.php`); search and pagination need no exclusion — core already excludes every query-string URL under pretty permalinks. The first-party beacon waits for `prerenderingchange` before it counts a pageview.

### Changed
- Survey, spec and plan for this arc: `docs/research/2026-09-11-*.md`. The 7.1 Icon API was verified on core and not adopted — the Icon block wraps every icon in a div and cannot carry the footer's `<a aria-label>` links.

## [13.0.0] - 2026-09-11 — search served by the kernel

### Added
- **Search results ranked by the plugin's kernel, each row showing its evidence.** `sn_notes_query_posts()` flags the search query with `sn_notes_search => true`; the companion plugin (≥ 14.0.0) ranks notes with BM25 through that flag and hands each ranked row the sentence that matched via `sn_notes_search_snippet`, query words in `<mark>` (kses'd to that one tag; a browse row and a plugin-off row still render the escaped excerpt). One `mark` rule in `notes.css`, text colour only. `tests/notes-search-query.php` (7 pins). Pages keep their date order.

### Changed
- **Versioning is the WordPress shape.** `X.Y.0` is a release, `X.Y.Z` a fix, `X` rolls when `Y` would reach 10 — core / WooCommerce / Jetpack numbering, same day as the plugin. `X` is not a breaking flag; a breaking release says BREAKING in its headline. `tools/cut-release.sh release|fix` (`minor`/`patch` aliased; `major` refuses). Cuts stack: one per arc, never per merge. Owner decision 2026-09-11; rule in `docs/VERSIONING.md`. The next theme cut is 13.0.0 — the roll, not a break.
- CI: the lint job now refuses any workflow job without `timeout-minutes` (the plugin's YAML guard, copied verbatim); the rule was enforced in one repo and kept by habit here. `smoke-test.yml` lints on PHP 8.4 — production — instead of 8.2. `CLAUDE.md` §Versioning says what is true since 2026-09-11: a cut is a PR (direct pushes to `main` are refused), and releases are public. From the plugin's [enforcement audit](https://github.com/juanlentino/signal-and-noise-tools/blob/main/docs/audits/enforcement-audit-2026-09-11.md), Phase 4.

