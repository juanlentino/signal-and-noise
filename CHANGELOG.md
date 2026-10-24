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
- **A Note's `.json` copy and its JSON Feed item read the same modified date as its JSON-LD and the sitemap (2026-10-24).** `date_modified` in both now comes from the plugin's `sn_schema_date_modified()` (the provenance commit), falling back to `post_modified` when the plugin is absent or has no time. Both read `post_modified` alone, which a scheduled publish does not touch, so `/notes/a-remix-inherits-nothing/` said it was modified a week before it was published. The value is the plugin's ISO 8601 string as is, in UTC, byte for byte what the JSON-LD `dateModified` carries; the feed still omits `date_modified` when neither clock has a time.
- **Clearing template overrides deletes only the active theme's, and never menus (2026-10-24).** `sn_clear_template_overrides()` (run on theme activation and by a full reset) deleted `wp_template` and `wp_template_part` rows for every theme and force-deleted `wp_navigation` posts, which hold menu content. It now deletes only the rows whose `wp_theme` term is `get_stylesheet()`, the theme scope core's `get_block_templates()` uses, and leaves `wp_navigation` alone. It also reaches auto-draft and trashed overrides, which its `post_status => 'any'` skipped while core still renders them (the regular editor creates auto-draft template parts, and `get_block_template()` reads trashed rows). It still returns the count it deleted.

### Docs
- **docs/CSP.md says what is live (2026-10-24).** The strict `script-src` is enforced by two Cloudflare rules (loose first, "CSP enforce (strict)" after, skipped on `X-SN-CSP: lax` and on `/provhub`), the Report-Only copy is gone, and `/provhub/*` is listed as exempt: those pages are static files outside WordPress, and the enforced policy had blocked the verifier.

## [2026.10.27] - 2026-10-22 — nav and footer links point straight at their pages


- **Header and footer links go straight to the page, not through a redirect.** The header nav (About, Resume, Music, Services, Notes, Contact), the footer meta-nav (Now, Accessibility, Stats, Privacy), the single note's "All Notes" link and the posts index's provenance link pointed at paths without the trailing slash, so each click and crawl took a 301 to the canonical `/about/`-style URL first. They now link the canonical URL directly, Spanish pages included. A new guard, `tests/chrome-trailing-slash.php`, fails on any slashless internal link in the parts, templates, block renders and Spanish chrome.


