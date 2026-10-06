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
- **The theme screenshot is current.** `screenshot.png` (the README image and the wp-admin theme card) was the v3.6.0 site from March; it is now the live home page at 1200×900 in light.
- **The cron-liveness guard watches `contrast.yml`** (#511): a live contrast check that stops running now fails CI instead of going quiet.
- **Docs:** `docs/ACCESSIBILITY.md` lists the live contrast check, the dim-text link guard and the reduced-motion reset, and says what the live check can see; `docs/MONITORING.md` documents `contrast.yml`; `readme.txt` names both.
- **README:** Now and Uses described as spec sheets; a Front-end entry for the accessibility rules and the live contrast check.

## [15.7.0] - 2026-10-06 — reduced motion stops all motion, labels and links at AA, contrast measured live

### Added
- **Contrast is measured on the live site in CI.** `.github/workflows/contrast.yml` runs `tools/contrast-computed.js` headless (`tools/contrast-ci.mjs`, Playwright 1.63.0) on every push to main, daily, and on demand: every sitemap page plus two notes and a tag archive, light and dark, failing on any text under AA. The instrument also gained a link check (a link marked by color alone must differ from its text by 3:1, WCAG 1.4.1) and lost two blind spots: it keyed pairs without opacity, so a passing first instance hid faded ones of the same class (the roadmap legend), and it skipped any background-image, which skipped every note title the gradient underline sits on. First local run caught the provenance panel and roadmap legend failures fixed in plugin 22.6.8.

### Fixed
- **The provenance brow link was marked by color alone.** On the pages that carry it (/about, /provenance and its papers), the "Verified vN↗" link is rust inside a blood eyebrow, 2.5:1 light and 1.2:1 dark, with its underline transparent until hover. The 1px rule now shows at rest in the link's own rust; hover still turns it blood. Found by the live contrast check's first full run.
- **A reduced-motion setting now stops every transition and animation.** The plugin's motion scan counted 44 declared transitions and animations with no reduced-motion counterpart across the theme and plugin stylesheets: mostly hover fades, a few real movement (the roadmap fold, the pillar CTA arrow, the /verify check states). `assets/css/base.css` now sets `animation` and `transition` to none on everything under `prefers-reduced-motion: reduce`. Nothing changes for anyone else.
- **Diagram labels in eleven notes fell under AA.** The inline diagrams mute secondary labels with opacity (.5 to .7), measured as low as 3.95:1 at 10-12px. The diagrams are part of the signed notes, so `assets/css/article.css` renders those labels in rust at full strength instead: still visibly secondary, AA in both palettes, the notes' content untouched.

