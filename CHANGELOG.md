# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

### Changed
- **One language switch, in the header, on every page.** The EN | ES control is now a bordered, two-part switch drawn like the theme toggle. The current language is filled; the other is the link, with the toggle's hover and focus. On a page with a translation it goes there; on an English-only page (notes, résumé, colophon), ES goes to /inicio/ and its accessible name says "página de inicio". It renders nothing until the Spanish home is published.

### Removed
- **The footer's language link.** The header switch is the one place (owner, 2026-10-15).

## [2026.10.5] - 2026-10-15 — The header and footer in Spanish, and a site-wide Español link

### Added
- **The header and footer speak Spanish on the Spanish pages** (`inc/lang-chrome.php`, `inc/lang-chrome-footer.php`). The header nav reads Inicio, Sobre mí, Currículum, Música, Servicios, Notas and Contacto. Each link points at the published Spanish page where one exists and at the English page otherwise, and `aria-current` marks the Spanish page being viewed. The logo links to /inicio/. The menu, search and theme-toggle labels switch to Spanish, and so do the footer meta-nav labels: the pages they reach stay English, and each label says "(en inglés)" and carries `hreflang="en"`. English pages render as before, byte for byte; `tests/footer-meta-nav.php` still pins the meta-nav. Owner, 2026-10-15.
- **A site-wide language link in the footer** (`signal-noise/lang-toggle` with `placement: footer`). Once the Spanish home is published, every English page shows "Español" (to its own translation, else /inicio/) and every Spanish page shows "English". It is a plain link with no storage. Pinned in `tests/lang-chrome.php`.

