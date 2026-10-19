# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

- **A wrapped Contents entry hangs under its text, not under its numeral.** On a phone, a long entry in a note's numbered table of contents ("02 The record is not / the announcement") wrapped back to the left edge, under the 02. Each entry now pads by the numeral's slot and pulls its first line back by the same, so the second line starts where the words do. Wide screens, where entries fit on one line, are unchanged, and References still lines up with the numbered entries.
- **Asking for more contrast drops the page textures.** With `prefers-contrast: more`, the film grain and the scanline are hidden, which also stops the grain's noise-floor scroll animation. Nothing else changes for those readers. The rule sits at the end of the inline critical sheet so it wins the cascade.
- **The empty comments feed is no longer advertised.** Every page's `<head>` linked a "Comments Feed" at /comments/feed/, a channel with nothing in it, since the site has no comments by design. The link is gone; the posts feed link stays, and the feed address itself is untouched.
- **The 404 notes search is a native `<search>` landmark.** The form drops `role="search"` and sits inside a `<search>` element, which says the same thing in markup. It renders in exactly the same place at 375px and 1440px.

## [2026.10.25] - 2026-10-19 — the ORCID iD joins the identity links


- **The ORCID iD joins the identity links, and humans.txt prints the theme's name as text.** Every page's `<head>` now carries `<link rel="me" href="https://orcid.org/0009-0006-8151-5920">` beside the social profiles, and humans.txt lists it under PROFILES, matching the plugin's Person.sameAs and llms.txt. It is added before the `sn_schema_same_as` filter, so the filter still governs the final list, and it is never doubled if the settings list it too. humans.txt printed `Signal &amp; Noise` because the theme header comes back HTML-escaped; it is decoded, since the file is plain text.


