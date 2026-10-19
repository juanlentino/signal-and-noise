# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

- **The ORCID iD joins the identity links, and humans.txt prints the theme's name as text.** Every page's `<head>` now carries `<link rel="me" href="https://orcid.org/0009-0006-8151-5920">` beside the social profiles, and humans.txt lists it under PROFILES, matching the plugin's Person.sameAs and llms.txt. It is added before the `sn_schema_same_as` filter, so the filter still governs the final list, and it is never doubled if the settings list it too. humans.txt printed `Signal &amp; Noise` because the theme header comes back HTML-escaped; it is decoded, since the file is plain text.

## [2026.10.24] - 2026-10-19 — the résumé no longer claims a proof it does not have

### Fixed
- **A page claims an authorship proof only when it has one.** The page's `.json` twin said "This page carries a Bitcoin-anchored authorship proof" for any page holding a provenance uid, and older plugin releases gave uids to pages that never opted in to signing. `/resume.json` made that claim while `/verify` found no credential and the public ledger had no record. The claim now needs an anchored version, the same test the plugin applies to the résumé's JSON Resume. The six signed pages keep theirs. The `/llms.txt` résumé line names the proof only on the same condition.

