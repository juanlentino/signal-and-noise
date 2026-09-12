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
- **The furniture blocks are visible on the site-editor canvas.** The eight post-dependent blocks (chip, record, related, cited-by, share, reply, updated date, pillar) render empty in the editor's preview — it has no post to read — so they existed in List View only. An empty render under a REST request now shows a labelled placeholder (`.sn-block-placeholder`, editor-only; the front end never renders through REST and is untouched).

### Changed
- `docs/changelog/v12.md` → `docs/changelog/archive.md`: it has held every past release since 13.0.0, not v12's alone. `tools/cut-release.sh` follows.

## [13.1.0] - 2026-09-12 — the template furniture is blocks

### Added
- **The template furniture is blocks.** Nine renderers that were `[shortcodes]` inside block templates — provenance chip and record, related notes, cited-by, share, reply, updated date, pillar link, the high-contrast toggle — are WordPress 7.0 PHP-only blocks (`supports.autoRegister`, no JavaScript build; `inc/blocks-php-only.php`): listed by name in the site editor's List View and movable. Byte-identical on the page: each block returns `wpautop( shortcode )`, exactly what the `core/shortcode` block produced, and `tests/blocks-php-only.php` pins it per block. The templates place each block exactly where its shortcode was. The shortcodes stay registered through 13.1.x for post content; 13.2.0 retires them.
- **Reading time is a bound paragraph** in three slots (note front matter, the home card, the notes card) through the `signal-noise/post-field` source the theme has carried since v9.11.0 — the migration v9.11.1 reverted, retried now that PHPStan is a required check and `tests/block-bindings-templates.php` pins every binding's key and empty inner. The pillar-card eyebrows keep the plugin's `[sn_reading_time slug]` (prefix text + figure in one paragraph; a binding would replace the prefix).
- **The closing part is hooked into `single` by rule** (`hooked_block_types`, `inc/block-hooks.php`): a single template that lacks it gets it after the content. The shipped template, which places it, declares `ignoredHookedBlocks` on its `core/post-content` anchor — core applies hooks to file-based templates too, and without that line the part would render twice.
- `post-frontmatter` and `post-closing` are declared template parts in a new **Article** area (`theme.json`, `inc/template-part-areas.php`) — they were "General" in the site editor.
- **Speculative loading** raised to prerender/moderate (`inc/speculation.php`); search and pagination need no exclusion — core already excludes every query-string URL under pretty permalinks. The first-party beacon waits for `prerenderingchange` before it counts a pageview.

### Changed
- Survey, spec and plan for this arc: `docs/research/2026-09-11-*.md`. The 7.1 Icon API was verified on core and not adopted — the Icon block wraps every icon in a div and cannot carry the footer's `<a aria-label>` links.

