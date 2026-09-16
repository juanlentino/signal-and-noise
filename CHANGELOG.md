# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

## [13.2.3] - 2026-09-16 — the theme matches the reference


- **Fixed:** the theme matches developer.wordpress.org where it names the way (a sweep of 2026-09-16; three drifts). The text domain is the slug, `signal-and-noise`, as the Theme Handbook says and Theme Check enforces: 64 i18n calls, the `Text Domain:` header, the three `block.json` files, the updater's identity constant and `phpcs.xml.dist` (identifiers that share the old string, the pattern category, the block category and the `signal-noise/` block namespace, are unchanged). The nine PHP-only furniture blocks (13.1.0) register from their own `blocks/<slug>/block.json` and `render.php`, the Block Editor Handbook's canonical form, instead of PHP argument arrays; `inc/blocks-php-only.php` is a loop over the directories. `theme.json` and the style variation point at the `wp/7.1` schema, the first that knows `settings.viewport` (the file used the 7.1 key under the 7.0 schema). `tests/reference-conformance.php` derives all three from the repo (every i18n call's domain, every block directory's manifest, the schema against `Tested up to`); the render-parity contract now reads an enumerated string as a choice, not prose.


