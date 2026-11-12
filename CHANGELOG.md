# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

## [2026.11.12] - 2026-11-12 — The beacon reads no résumé lane; snCopy.liveRegion shim removed

### Removed
- **The beacon no longer reads a résumé lane.** /resume is one résumé since 2026-11-12, so the lane read (`readLane()`, the `lane` key on /resume beacons, 2026-11-02 to 2026-11-12) found nothing and sent nothing. Its code, the comment naming the deleted `resume-switch.js`, and the pinned `tests/js/fixtures/beacon-payload-resume.json` are gone (`assets/js/sn-beacon.js`). Test: `tests/js/beacon-resume-lane.test.cjs` now asserts no beacon carries a lane, even with the old lane markup (fails against the old beacon).
- **`snCopy.liveRegion()`, the one-release shim for pages cached before the switch to `wp.a11y.speak()` (2026-11-10), is gone** (`assets/js/sn-copy.js`). Test: `tests/js/sn-copy-stale-callers.test.cjs` asserts it is absent and the copy helper remains.

