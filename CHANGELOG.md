# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

## [15.8.2] - 2026-10-10 — llms.txt lists Start Here and Objections, and its counts follow the site

### Fixed
- **llms.txt lists Start Here and Objections, and its summary counts can no longer go stale.** Read live on 2026-10-10, the summary said "Forty-plus notes, two SSRN papers" while the site held 51 notes and its own Pillar essays section listed three papers. The counts are now derived (`sn_llms_txt_summary_facts()`): notes from the published, non-password posts, the same corpus /notes/ lists (`sn_llms_txt_note_count()`, filters suppressed), papers from the pillar descriptors the Pillar essays section lists; a count that is unknown is left out, never guessed. Key pages gains `/provenance/objections/` directly after Provenance and `/notes/start-here/` directly after Notes, listed by hand on purpose: the list is not derived from the children of `/provenance/`, whose pillar essays have their own section. The `get-llms-txt` ability now passes the same inputs as the route, so its index output is the served `/llms.txt` byte for byte and its full output carries Topics, which it had been missing. No cache was added: the file rides the edge's machine lifetime (5 minutes) and the plugin's publish purge. The Rights section is unchanged, pinned byte for byte against `tests/fixtures/llms-rights.txt`, captured before the change. Pinned by `tests/llms-txt.php`.

