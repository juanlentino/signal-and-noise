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
- **The beacon says "still here" once, at 10 seconds of visible time (owner brief 2026-11-14).** Visible time was sent only when the page was hidden or closed, so a reader who stayed without scrolling said nothing until they left, and a lost leave signal (it happens on some desktops) left the view with no time at all. After 10 seconds of accumulated visible time on a page view, the beacon now sends the accrued delta once, as the existing `tm` event, and resets the accumulator. Counting goes on as before, and the leave flush sends the rest, so the deltas still add up to the view's visible time.
  - One ping per page view. A back-forward cache restore re-arms it.
  - It never fires while the page is hidden: the timer is cleared on hide and set again on return for the time still owed.
  - No new event type, no storage.
  - The ping never sets the no-input bot signal (`ni`). It measures visible time, not input timing, so a reader who reads before touching anything is not flagged.
  - Effect: a reader who stays 10 seconds shows as reading while still on the page, and the view is a read even if its leave signal is lost.
  - Test: `tests/js/beacon-still-here.test.cjs` (new). It checks one ping at 10 s, nothing while hidden, deltas that sum to the visible time, the restore re-arm, and a page left early. It goes red when the ping keeps its delta or fires early.

## [2026.11.12] - 2026-11-12 — The beacon reads no résumé lane; snCopy.liveRegion shim removed

### Removed
- **The beacon no longer reads a résumé lane.** /resume is one résumé since 2026-11-12, so the lane read (`readLane()`, the `lane` key on /resume beacons, 2026-11-02 to 2026-11-12) found nothing and sent nothing. Its code, the comment naming the deleted `resume-switch.js`, and the pinned `tests/js/fixtures/beacon-payload-resume.json` are gone (`assets/js/sn-beacon.js`). Test: `tests/js/beacon-resume-lane.test.cjs` now asserts no beacon carries a lane, even with the old lane markup (fails against the old beacon).
- **`snCopy.liveRegion()`, the one-release shim for pages cached before the switch to `wp.a11y.speak()` (2026-11-10), is gone** (`assets/js/sn-copy.js`). Test: `tests/js/sn-copy-stale-callers.test.cjs` asserts it is absent and the copy helper remains.

