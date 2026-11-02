# Changelog

All notable changes to the Signal & Noise theme are documented here.

This file holds two things only: **`## [Unreleased]`**, the working log that
accumulates across pull requests, and the **current release**. Everything older
lives in [docs/changelog/](docs/changelog/).

A pull request does not bump `Version` and does not tag — it closes an issue and
adds a bullet below. A release is a separate, deliberate act:
`tools/cut-release.sh`, which stamps `style.css` **and** `readme.txt` together.

## [Unreleased]

### Changed
- **llms.txt's Key pages name Services and the Spanish CV.** Owner yes 2026-11-01. "Curriculum vitae" (/cv/, "the résumé in Spanish") follows the Résumé, and "Services" (/services/) lists the page's own scope; titles are the pages' own. No other Spanish twin is listed there, so /servicios/ is not either. `inc/llms-txt.php`. Tests: `tests/llms-txt.php` (+2).

### Fixed
- **The lane switch reads a repeated `?lane=` the way the server does: the last one wins.** `?lane=operations&lane=music` rendered music (PHP's `$_GET` keeps the last value) and then the script, reading the first, flipped to operations after paint (PERF-1). With no `?lane`, a `#resume-operations` or `#resume-music` link now names its lane, where before it showed music with its target hidden; switching away from such an address drops that anchor, so Back and a reload land on the lane chosen. `assets/js/resume-switch.js`. Tests: `tests/js/resume-switch.test.cjs` (new, drives the script under node:vm: an inverted `show()` passed the source pins 19 of 19 and fails here), `tests/resume-switch.php` (+1).
- **A screen reader hears the lane switch, and Back or Forward no longer drops focus into a hidden lane.** The switch changed the page in silence (A11Y-1): the script now adds an empty polite status region to the switch's nav and fills it on a real change, by click or by Back and Forward, never on load, with the link's own label ("Showing the Operations & AI Strategy résumé"). When Back or Forward hides the lane that held focus (A11Y-3), focus moves to the current switch link instead of falling to the top of the page. `assets/js/resume-switch.js`. Tests: `tests/js/resume-switch.test.cjs` (+4).
- **After a switch in place, the head's Markdown and JSON Resume alternates name the lane on screen.** They kept the lane the page loaded with, so an agent reading the head after a click was pointed at the other résumé. A real change now rewrites only those two (`/resume.md` and `/resume/json-resume.json`, `?lane=operations` or bare for music); every other alternate, and the load-time correction, leaves the head as the server wrote it. `assets/js/resume-switch.js`. Tests: `tests/js/resume-switch.test.cjs` (+2).
- **/resume on paper: the lane bands drop the screen page track, and the operations contact rail prints each address once.** The bands' page-track rule in `assets/css/resume-switch.css` (`max-width` at specificity 0,4,2, `!important`) outranked print.css's `max-width: none` (0,3,2) and held them at 1320px on paper (A11Y-7); it is now `@media screen` only, and the hidden-lane rule stays outside it, so paper still prints only the lane that shows. The switch, its caption and the receipt marks were already off paper, and the bands inside a lane already had print.css's flattening. The rail printed "github.com/juanlentino (https://github.com/juanlentino)": print.css blanked the spelled-out URL only for LinkedIn, and now blanks it for every contact-rail link (every LinkedIn link in the page sits in a rail). Measured with headless Chromium print-to-PDF on the live markup: band `max-width` 1320px before, `none` after; the GitHub URL once. The music lane still prints three pages (Skills alone on the third); the page track was not the cause at Letter width. Tests: `tests/resume-switch.php` (+3).
- **A receipt is a 24px target.** With 0.05em of vertical padding a receipt ("verify", "paper", "code") measured 18.8px tall (A11Y-6); 0.3em brings it to 24.4px, measured in headless Chromium at 1280 and 390 wide. A heading that carries one grows by under a pixel; a publication's meta line by about five. `assets/css/resume-switch.css`. Tests: `tests/resume-switch.php` (+1).

## [2026.11.2] - 2026-11-02 — The résumé's lane switch: Music and Operations & AI Strategy

### Added
- **/resume's lane switch: Music and Operations & AI Strategy.** Owner brief 2026-11-01 #2. The plugin stores both résumés in the page and hides the one not asked for at render time (`?lane=operations`); the theme draws and drives the switch. `assets/css/resume-switch.css`, English /resume only: the switch is drawn like the EN | ES switch (one hairline box split in two, the current lane filled, blood on hover and focus, 44px targets, underlined in forced colors), each lane's headline and subhead, the receipt marks (`verify`, `paper`, `code`), and "What I've Built" as publication cards. `.sn-resume-variant[hidden]` stays hidden over any group display rule. `assets/js/resume-switch.js`, deferred, English /resume only: a click shows the other lane in place, keeps `?lane=operations` (or the bare /resume/) in the address, and Back and Forward follow; a modified or middle click opens the link as a link, and every link works without the script. Each lane carries its own Download, so the download follows the lane with nothing rewritten.
- **The script reads `?lane=` exactly as the server does** (lowercased, stripped to `[a-z0-9_-]`, then operations or music), so `?lane=Operations` keeps the lane the page was rendered with; a switch changes only `lane` in the address, so a draft preview keeps its preview query (independent review of #2346).
- **llms.txt names both résumés' files.** The résumé formats line adds "JSON Resume, operations" and "PDF, operations" when the plugin lists them.

### Changed
- **Paper prints the lane that shows,** with the bands inside a lane flattened as the top-level ones were, and without the switch or the receipt marks.

  Tests: `tests/resume-switch.php` (15), `tests/llms-txt.php` (+1), `tests/dark-mode.php` (the switch's current-lane fill is allowed, the same case as the EN | ES switch).

