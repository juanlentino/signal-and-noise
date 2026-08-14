=== Signal & Noise ===
Contributors: Juan Lentino
Requires at least: 7.0
Tested up to: 7.0
Requires PHP: 8.3
Stable tag: 11.8.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

== Description ==

Signal & Noise is a white-first, brutalist WordPress block theme for juanlentino.com — inspired by nin.com. Black text on white, generous whitespace, blood-red accents, Bebas Neue display + DM Mono editorial pairing.

README.md in the repository root is the canonical, fuller doc (templates, virtual routes, front-end features); this file is a WordPress-format summary.

Features:
* Full Site Editing (FSE) — block templates, template parts, patterns
* Long-form post layout with frontmatter spec card, drop caps, footnotes, and sidenotes (v9.3.0+)
* Justified text with hyphenation and hanging punctuation (v9.4.0)
* Self-hosted Bebas Neue + DM Mono typography (no Google Fonts)
* View Transitions API for soft cross-page navigation
* Sticky shrinking header with reduced-motion honor
* Inlined critical CSS; the modular stylesheets ship as one combined, minified file the theme builds itself (v10.21.6+), with per-file enqueues as the fail-open fallback
* Skip-link, focus-visible outlines on every interactive element (WCAG 2.4.7 AA)
* Companion plugin (Signal & Noise Tools) owns SEO, login hardening, admin tooling

== Installation ==

This theme is distributed via GitHub releases, not the WordPress.org directory. Install paths:

1. **Canonical (user-driven):** wp-admin → Dashboard → Updates → "Update theme" for Signal & Noise. The theme registers with WP's native update system via `inc/wp-update-integration.php`.
2. **Emergency manual:** `gh workflow run deploy.yml --repo juanlentino/signal-and-noise --ref vX.Y.Z` (v10.44.0+: builds the tag archive on the runner and rsyncs it over SSH, then asserts the landed style.css Version matches the tag).

== Color Palette ==

All design tokens live in theme.json — a white-first palette (`void`/`bone` with `blood` + `signal` red accents), editable in the Site Editor under Styles → Colors. Every text pairing clears WCAG AA (4.5:1). Full token reference: README.md and docs/ACCESSIBILITY.md.

== Recommended Plugins ==

* **Signal & Noise Tools** (companion plugin) — owns SEO emission, login hardening, admin tooling, analytics, and AI-health surfaces. Recommended for the full feature set; the theme is standalone-safe and degrades gracefully without it.

Note: Contact Form 7 was removed in v10.12.0 in favor of a plain-text routing directory on `/contact` — it is no longer used or recommended (see `tests/cf7-removal.php`, a regression guard against its reintroduction).

== Changelog ==

See [CHANGELOG.md](CHANGELOG.md) in the repository root for the full, current release history. The canonical installed version is the `Version:` header in style.css (what the self-updater reads); the `Stable tag:` above mirrors it.
