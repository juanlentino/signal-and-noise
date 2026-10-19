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
- **`/llms.txt` lists the résumé's formats.** A line under Machine surfaces links the résumé as Markdown, JSON Resume, PDF and vCard, and says the Markdown and JSON Resume carry the link to the page's authorship proof. The URLs come from the plugin, so the line appears only while the plugin publishes them and the résumé page is published.

## [2026.10.22] - 2026-10-18 — the translator hint sits on Notas, and every hint shows once


- **The translator line moves to Notas, and every hint shows once.** On the live v2026.10.21, the sentence about the browser's translator sat on ES, and hovering ES drew two tooltips: the theme's box and the browser's own `title` tooltip. Now, in the Spanish header, Notas (which goes to the English /notes/) carries the hint "En inglés. Podés leerlas con el traductor del navegador." on hover and keyboard focus, and its accessible name starts with what it shows: "Notas, en inglés: podés leerlas con el traductor del navegador." (WCAG 2.5.3). ES on an English-only page keeps its unavailable state (muted, `aria-disabled`, focusable) with a shorter hint, "Solo en inglés.", and the name "ES, no disponible: solo en inglés." No hint reads `title` anymore: the text lives in `data-hint` and the CSS draws it from there, so the browser adds no second tooltip. The footer's icon labels (Now, Accessibility, Colophon, Stats, Privacy, and their Spanish versions) had the same double tooltip and get the same fix, with no change to how they look. The Notas hint draws on `::before`, since a nav link's `::after` is its blood underline. Checked with a keyboard pass on a local render.


