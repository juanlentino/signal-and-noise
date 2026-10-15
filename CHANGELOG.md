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
- **The header and footer speak Spanish on the Spanish pages** (`inc/lang-chrome.php`, `inc/lang-chrome-footer.php`). The header nav reads Inicio, Sobre mí, Currículum, Música, Servicios, Notas and Contacto. Each link points at the published Spanish page where one exists and at the English page otherwise, and `aria-current` marks the Spanish page being viewed. The logo links to /inicio/. The menu, search and theme-toggle labels switch to Spanish, and so do the footer meta-nav labels: the pages they reach stay English, and each label says "(en inglés)" and carries `hreflang="en"`. English pages render as before, byte for byte; `tests/footer-meta-nav.php` still pins the meta-nav. Owner, 2026-10-15.
- **A site-wide language link in the footer** (`signal-noise/lang-toggle` with `placement: footer`). Once the Spanish home is published, every English page shows "Español" (to its own translation, else /inicio/) and every Spanish page shows "English". It is a plain link with no storage. Pinned in `tests/lang-chrome.php`.

## [2026.10.4] - 2026-10-15 — Spanish storefront: the EN / ES toggle and Spanish discography strings

### Added
- **The EN / ES toggle (`signal-noise/lang-toggle`, in the header).** On a storefront page that has a published Spanish version (`/inicio/`, `/sobre-mi/`, `/musica/`, `/servicios/`; the pairing lives in the companion plugin's `inc/bilingual.php`), the header shows `EN / ES`. The other language is a plain link carrying `hreflang` and `lang`, and its accessible name keeps the visible letters. The current language is text marked `aria-current`. It renders nothing on any other page and nothing without the plugin. It uses no cookie, no storage and no redirect by browser language. `inc/lang-toggle.php`, pinned in `tests/lang-toggle.php`.
- **/musica speaks Spanish in the discography's interface:** the release counts, the filter group and chips, Credits, Liner notes, the track counts, the play names, the bridge line ("Una selección. Solo desde 2022, pasaron por mis manos unos 110 temas.") and the featured player's label ("Destacado · Escuchá"). The Spanish is Argentine, with voseo, approved by the owner on 2026-10-15. The toggle's ES link carries `lang="es-AR"`.
- **A release note under a card's roles** (`sn_discography_render_note()`), from the plugin's new Release notes box: the Richter stage-manager line. On a Spanish page an empty Spanish note shows the English one, marked `lang="en"`.

### Changed
- **`/inicio/` is tagged `sn-lists`, like the home page it translates,** so a note save refreshes it too.
- **The Varnish comment in the maintenance purge says the companion's purge is retired.** It told a future reader to restore `breeze_clear_varnish` if Varnish came back; the plugin retired that purge on 2026-10-14 (signal-and-noise-tools#2085), so the action would reach nothing. Comment only.

