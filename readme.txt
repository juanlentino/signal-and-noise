=== Signal & Noise ===
Contributors: juanml
Requires at least: 7.0
Tested up to: 7.1
Requires PHP: 8.3
Stable tag: 2026.10.27
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

== Description ==

Signal & Noise is a white-first, clinical-industrial WordPress block theme for juanlentino.com, inspired by nin.com. Brutalist typography, hard-edged surfaces. Black text on white, generous whitespace, blood-red accents, Bebas Neue display + DM Mono editorial pairing.

Since v12.0.0 it also ships a dark palette. It is an inversion, not a softening: a near-black ground, hairlines that stay hairlines, no elevation ramp, and the same red re-pointed so it clears AA against black. Dark follows the reader's OS by default and a toggle on the utility bar overrides it.

README.md in the repository root is the canonical, fuller doc (templates, virtual routes, front-end features); this file is a WordPress-format summary.

Features:
* Full Site Editing (FSE) — block templates, template parts, patterns
* Long-form post layout with frontmatter spec card, drop caps, footnotes, and sidenotes (v9.3.0+)
* Justified text with hyphenation and hanging punctuation (v9.4.0)
* Self-hosted Bebas Neue + DM Mono typography (no Google Fonts)
* Dark mode (v12.0.0) as a token layer: follows prefers-color-scheme, with a persistent reader toggle. Contrast-gated in CI across every palette the site can present
* Cross-page navigation is a "channel change": the View Transitions API wipes the new page in, top to bottom in eight steps (2026.10.11)
* Reading aids for notes: timeline, key term (a <dl> block), checklist and comparison table, each in the editorial-conventions registry (2026.10.11)
* Reading progress on long notes as an FM tuning dial, CSS scroll-driven with no script (2026.10.11)
* The home hero draws the last 24 hours of the site's own traffic as a hairline waveform, server-rendered with no script (2026.10.11)
* A printed note carries its own proof: the signed text's full hash, version and verify address, on paper only (2026.10.11)
* Fixed, solid, single-height header; under prefers-reduced-motion every animation and transition is off
* Contrast measured on sampled live pages daily (contrast.yml): text at AA; links in running text underlined or 3:1 against the text around them
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

== Copyright ==

Signal & Noise WordPress Theme, (C) Juan Lentino.
Signal & Noise is distributed under the terms of the GNU GPL v2 or later.

This theme bundles the following third-party resources:

Bebas Neue
Copyright 2019 The Bebas Neue Project Authors (https://github.com/dharmatype/Bebas-Neue)
License: SIL Open Font License, Version 1.1, https://openfontlicense.org
Source: https://github.com/dharmatype/Bebas-Neue
Files: assets/fonts/bebas-neue-*.woff2, assets/fonts/og/BebasNeue-Regular.ttf

DM Mono
Copyright 2020 The DM Mono Project Authors (https://github.com/googlefonts/dm-mono)
License: SIL Open Font License, Version 1.1, https://openfontlicense.org
Source: https://github.com/googlefonts/dm-mono
Files: assets/fonts/dm-mono-*.woff2, assets/fonts/og/DMMono-Light.ttf

== Changelog ==

See [CHANGELOG.md](CHANGELOG.md) in the repository root for the full, current release history. The canonical installed version is the `Version:` header in style.css (what the self-updater reads); the `Stable tag:` above mirrors it.
