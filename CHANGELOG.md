# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

- **The translator line moves to Notas, and every hint shows once.** On the live v2026.10.21, the sentence about the browser's translator sat on ES, and hovering ES drew two tooltips: the theme's box and the browser's own `title` tooltip. Now, in the Spanish header, Notas (which goes to the English /notes/) carries the hint "En inglés. Podés leerlas con el traductor del navegador." on hover and keyboard focus, and its accessible name starts with what it shows: "Notas, en inglés: podés leerlas con el traductor del navegador." (WCAG 2.5.3). ES on an English-only page keeps its unavailable state (muted, `aria-disabled`, focusable) with a shorter hint, "Solo en inglés.", and the name "ES, no disponible: solo en inglés." No hint reads `title` anymore: the text lives in `data-hint` and the CSS draws it from there, so the browser adds no second tooltip. The footer's icon labels (Now, Accessibility, Colophon, Stats, Privacy, and their Spanish versions) had the same double tooltip and get the same fix, with no change to how they look. The Notas hint draws on `::before`, since a nav link's `::after` is its blood underline. Checked with a keyboard pass on a local render.

## [2026.10.21] - 2026-10-18 — accessible language switch and an honest ES on English-only pages


- **Four accessibility fixes from the live sweep of 2026-10-18.** (1) The language switch's accessible name now starts with what it shows: "ES, Español" and "EN, English", not "Español (ES)" (WCAG 2.5.3, axe `label-content-name-mismatch`). (2) Smooth scrolling now stops under `prefers-reduced-motion: reduce`. The inline critical CSS prints after the combined sheet, so base.css's override always lost; `critical.css` now carries its own, right after the smooth rule, and base.css's comment no longer claims otherwise. New `tests/critical-reduced-motion.php` pins the order. (3) The featured Spotify player on /music shows the house focus ring (2px blood) on its box. CSS alone cannot do it: measured in Chromium, Tab moves focus inside the cross-origin player and the `<iframe>` matches no focus selector, and its mask would clip an outline of its own. The ring sits on the wrapper on `:focus-within`, and a small new script, `assets/js/frame-focus.js` (loaded only with the player), adds it when focus enters the player by Tab, never on a click (WCAG 2.4.7). (4) The comparison table's figure keeps `tabindex="0"` and its name but drops `role="region"`, which a figure with a caption may not carry (axe `aria-allowed-role`); keyboard scrolling works as before. Checked in Chromium with axe and a keyboard pass on a local render of the theme code: all four reproduce on `main` and clear on this branch.

- **Notes no longer send Spanish readers to the Spanish home.** On an English page with no Spanish version (every note), ES used to link to /inicio/. Now the reader stays: ES is a muted segment that goes nowhere, announced as unavailable ("ES, no disponible: solo en inglés. Podés leerla con el traductor del navegador."), with the same words as a small hint on hover and on keyboard focus. Spanish pages are unchanged. In the Spanish header, Notas still goes to the English /notes/ (there is no /notas/) and now says so with `hreflang="en"`, like the footer's English links; so does any header link that stays English, and the latest-note line on the Spanish home.


