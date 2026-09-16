# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

## [13.2.4] - 2026-09-16 — the document as a pointer target


- **Fixed:** `footnotes-popover.js` threw `event.target.closest is not a function` on every note whenever the pointer left the window: `pointerenter` and `pointerleave` are captured on the document, so the pointer leaving the document targets the document itself, which has no `closest()`; a text node has none either. One `supOf( event )` helper guards the four handlers. Node suite `tests/js/footnotes-popover-target.test.cjs` drives the real file with the document, a text node and null as targets (red on the old code) and a real `<sup>` as the negative control.


