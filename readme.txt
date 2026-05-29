=== Signal & Noise ===
Contributors: Juan Lentino
Requires at least: 6.4
Tested up to: 7.0
Requires PHP: 8.0
Stable tag: 9.5.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

== Description ==

Signal & Noise is a white-first, brutalist WordPress block theme for juanlentino.com — inspired by nin.com. Black text on white, generous whitespace, blood-red accents, Bebas Neue display + DM Mono editorial pairing.

Features:
* Full Site Editing (FSE) — block templates, template parts, patterns
* Long-form post layout with frontmatter spec card, drop caps, footnotes, and sidenotes (v9.3.0+)
* Justified text with hyphenation and hanging punctuation (v9.4.0)
* Self-hosted Bebas Neue + DM Mono typography (no Google Fonts)
* View Transitions API for soft cross-page navigation
* Sticky shrinking header with reduced-motion honour
* Inlined critical CSS; 5 deferred stylesheets (delegated to Breeze)
* Skip-link, focus-visible outlines on every interactive element (WCAG 2.4.7 AA)
* Companion plugin (Signal & Noise Tools) owns SEO, login hardening, admin tooling

== Installation ==

This theme is distributed via GitHub releases, not the WordPress.org directory. Install paths:

1. **Canonical (user-driven):** wp-admin → Dashboard → Updates → "Update theme" for Signal & Noise. The theme registers with WP's native update system via `inc/wp-update-integration.php`.
2. **Emergency manual:** `gh workflow run deploy.yml --repo juanlentino/signal-and-noise --ref vX.Y.Z` (deploys via Cloudways API).

== Color Palette ==

All design tokens are controlled through theme.json. Current palette (since v2.0.0 inversion):

- `void` (#ffffff) — primary background
- `asphalt` (#f5f5f5) — secondary background
- `concrete` (#d9d9d9) — borders, decorative dividers
- `rust` (#666666) — secondary text
- `bone` (#000000) — primary text
- `blood` (#e00404) — brand accent
- `signal` (#ff4c47) — hover/active accent

Modify in the Site Editor under Styles → Colors.

WCAG AA contrast: every text pairing clears 4.5:1 normal-text threshold. See `docs/superpowers/specs/2026-05-26-audit-d-perf-a11y-findings.md` §4 for measured ratios.

== Recommended Plugins ==

* **Signal & Noise Tools** (companion plugin) — owns SEO emission, login hardening, admin tooling, AI-health surfaces. Required.
* **Contact Form 7** (with Cloudflare Turnstile) — contact-page form. Optional but recommended.

== Changelog ==

See [CHANGELOG.md](CHANGELOG.md) in the repository root for the full release history. Latest stable: v9.5.0 (2026-05-27).

= 9.5.0 =
* New: cross-package listener tests for all 4 plugin-to-theme contracts (theme-side seal, 25 assertions)
* New: build-time WCAG 2.1 contrast verification asserting docs/ACCESSIBILITY.md baseline (20 assertions)
* New: theme v10.0.0 scope audit (docs/superpowers/specs/2026-05-27-v10.0.0-scope.md)
* Fix: tests/patterns-registry.php now covers the sidenote pattern (was untested)
* Fix: readme.txt Stable tag drift (was 9.4.3, now matches the shipped version)
* See CHANGELOG.md for full details.

Notable milestones:
* v2.0.0 — Full palette inversion to white-first brutalist (matches nin.com)
* v3.0.0 — PageSpeed Insights optimization pass (font preload, GSI removal, deferred scripts)
* v8.2.0 — Companion plugin (Signal & Noise Tools) introduced
* v9.0.0 — Theme abilities + Interactivity API integration
* v9.3.0 — Long-form post layout (drop caps, footnotes, sidenotes, frontmatter spec card)
* v9.4.0 — Typography polish (justified, hyphenation, hanging punctuation)
* v9.5.0 — Cross-package listener tests + WCAG contrast baseline + v10 scope audit
