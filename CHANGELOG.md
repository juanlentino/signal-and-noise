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
- **Each note links its Markdown copy.** A note page's `<head>` now carries `<link rel="alternate" type="text/markdown" href=".../notes/<slug>.md">`, beside the RSS, oEmbed and JSON alternates, and llms.txt and the discovery manifest name the convention (append `.md` to a note URL). Notes only: pages, the home page, drafts and password-protected notes print nothing. The Markdown is served by the sn-rights-signals Worker from the same converter that answers `Accept: text/markdown`, and that Worker converts a page only when this link names the exact URL, so this link is what decides which `.md` URLs exist. Nothing new to purge: the Worker reads the note's HTML URL, which the plugin's per-post purge already refreshes. Ship after the Worker deploys, or each link points at a 404.

### Changed
- **Versions are by date: `YYYY.M.N`.** The next cut is 2026.10.1, then 2026.10.2, and N starts again each month. One site installs this theme and nothing else ever will, so the number only has to go up and say how old an install is. `tools/cut-release.sh` computes the date number (`fix` is accepted and means the same as `release`; `major` is refused) and refuses a version that would not sort above the current one. The release workflow tags the cut and no longer drafts a GitHub release, which nothing read. docs/VERSIONING.md says why; the plugin changed the same day.

## [15.8.2] - 2026-10-10 — llms.txt lists Start Here and Objections, and its counts follow the site

### Fixed
- **llms.txt lists Start Here and Objections, and its summary counts can no longer go stale.** Read live on 2026-10-10, the summary said "Forty-plus notes, two SSRN papers" while the site held 51 notes and its own Pillar essays section listed three papers. The counts are now derived (`sn_llms_txt_summary_facts()`): notes from the published, non-password posts, the same corpus /notes/ lists (`sn_llms_txt_note_count()`, filters suppressed), papers from the pillar descriptors the Pillar essays section lists; a count that is unknown is left out, never guessed. Key pages gains `/provenance/objections/` directly after Provenance and `/notes/start-here/` directly after Notes, listed by hand on purpose: the list is not derived from the children of `/provenance/`, whose pillar essays have their own section. The `get-llms-txt` ability now passes the same inputs as the route, so its index output is the served `/llms.txt` byte for byte and its full output carries Topics, which it had been missing. No cache was added: the file rides the edge's machine lifetime (5 minutes) and the plugin's publish purge. The Rights section is unchanged, pinned byte for byte against `tests/fixtures/llms-rights.txt`, captured before the change. Pinned by `tests/llms-txt.php`.

