# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

### Fixed
- **A page claims an authorship proof only when it has one.** The page's `.json` twin said "This page carries a Bitcoin-anchored authorship proof" for any page holding a provenance uid, and older plugin releases gave uids to pages that never opted in to signing. `/resume.json` made that claim while `/verify` found no credential and the public ledger had no record. The claim now needs an anchored version, the same test the plugin applies to the résumé's JSON Resume. The six signed pages keep theirs. The `/llms.txt` résumé line names the proof only on the same condition.

## [2026.10.23] - 2026-10-19 — llms.txt lists the résumé formats

### Added
- **`/llms.txt` lists the résumé's formats.** A line under Machine surfaces links the résumé as Markdown, JSON Resume, PDF and vCard, and says the Markdown and JSON Resume carry the link to the page's authorship proof. The URLs come from the plugin, so the line appears only while the plugin publishes them and the résumé page is published.

