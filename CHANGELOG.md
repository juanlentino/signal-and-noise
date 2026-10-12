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
- **/notes/tags/ opens with "What to do about it".** A new first group, seeded with the `practical` tag (live on 4 notes): "Procedures for the moment a statement, a credit, or an offer lands in your inbox." Pinned by `tests/notes-tags-index.php` and `tests/notes-tags-group-meta.php`.
- **Single-key shortcuts can be turned off (WCAG 2.1.4).** `j`, `k`, `?` (notes) and `/` (site-wide) had no off switch. A checkbox in the `?` cheat-sheet and a row in the search palette (Cmd/Ctrl-K or the Search button, on every page) store one localStorage key, read by every handler at keydown time. On by default; blocked storage fails safe to on; the Search button's `aria-keyshortcuts` drops `/` while off. Pinned by `tests/js/single-key-shortcuts.test.cjs`.
- **The header nav marks the current page with `aria-current="page"`.** Core sets it only for post-type links; the header's links are custom. `inc/nav-current.php`, exact path match (trailing slash and query ignored, off-site never). The home page gets no marker: Page 383 stays out of site-wide changes. Pinned by `tests/nav-current.php`.
- **Each note links its Markdown copy.** A note page's `<head>` now carries `<link rel="alternate" type="text/markdown" href=".../notes/<slug>.md">`, beside the RSS, oEmbed and JSON alternates, and llms.txt and the discovery manifest name the convention (append `.md` to a note URL). Notes only: pages, the home page, drafts and password-protected notes print nothing. The Markdown is served by the sn-rights-signals Worker from the same converter that answers `Accept: text/markdown`, and that Worker converts a page only when this link names the exact URL, so this link is what decides which `.md` URLs exist. Nothing new to purge: the Worker reads the note's HTML URL, which the plugin's per-post purge already refreshes. Ship after the Worker deploys, or each link points at a 404.

### Changed
- **Versions are by date: `YYYY.M.N`.** The next cut is 2026.10.1, then 2026.10.2, and N starts again each month. One site installs this theme and nothing else ever will, so the number only has to go up and say how old an install is. `tools/cut-release.sh` computes the date number (`fix` is accepted and means the same as `release`; `major` is refused) and refuses a version that would not sort above the current one. The release workflow tags the cut and no longer drafts a GitHub release, which nothing read. docs/VERSIONING.md says why; the plugin changed the same day.
- **phpcs grades against the declared floors.** `minimum_wp_version` 6.4 to 7.0 and `testVersion` 8.0- to 8.3-, read back from style.css by `tests/phpcs-floors.php`.
- **Removed dead code and dead weight.** The `wlwmanifest_link` remove_action (core dropped the hook in WP 6.3), `sn_prov_brow_reset()` (reset nothing, no caller), the four unregistered latin-ext font files (`tests/font-files.php` pins shipped = registered), and the pull-quote block's second `wp_kses_post` per slot.

### Fixed
- **Compare-columns cards fit their column on phones and tablets.** `.sn-compare__col` padded a content-box column whose flex-basis is 100%, so each card was 423px at a 375px viewport (812 at 768) and its text was chopped. `box-sizing: border-box`; measured on the live note with the rule injected: 375 and 764 wide, 1440 unchanged. Pinned by `tests/compare-columns-fit.php`.
- **The superseded combined stylesheet keeps its 7-day grace after a quiet week.** The grace counted from when a hash was built, so a release more than 7 days after the last one deleted the previous stylesheet in the same request that replaced it. It now counts from supersession (the next newer hash's build time). Pinned by `tests/asset-combine.php`.
- **A theme update's `.git` can no longer be restored into the directory being replaced.** A Heartbeat from another wp-admin tab could run the `admin_init` recovery mid-update. A 15-minute lock is held from pre_install to post_install. New suite `tests/wp-update-git-preservation.php`.
- **Re-filing a tag into a later group works.** A seeded tag whose term meta named a later group stayed in its seed group; the meta now wins in both directions.
- **Text under the 11px floor.** The search type chip (10px), the compact pillar eyebrow (10.4px) and the em-sized footnote marker now hold 11px. `tests/type-floor.php` scans every theme stylesheet for px, rem, `max()` and `clamp()` sizes under 11px.
- **The theme toggle's accessible name contains its visible word (WCAG 2.5.3).** "Light: switch to dark theme" / "Dark: switch to light theme". Pinned in `tests/theme-toggle-directives.php`.
- **/notes/ tag links are 24px tall (WCAG 2.5.8).** They measured 19.7px. Pinned by `tests/target-size.php`.
- **Password-protected notes never appear under "More on this".** Both queries and the kernel pass skip them, like every sibling surface.
- **JSON Feed item titles are plain text**, decoded and stripped like the siblings.
- **The pillar CTA arrow guards its own motion**, and `tests/motion-pairing.php` now reads block stylesheets.
- **llms.txt states facts in house style.** The Abilities API line says it requires authentication (it answers 401 anonymously); the cookie sentence follows the Privacy Policy (third-party players may set their own cookies); three em dashes become colons or parentheses; "licence" becomes "license" in prose (the `TDM-Licence-*` header name stays; `tests/fixtures/llms-rights.txt` regenerated for those two words only).
- **The /notes/ subscribe lines use colons, not em dashes.**
- **Headings promoted from h3 to h2 keep their phone size**, ahead of the staged h3-to-h2 changes on /services/ and /provenance/objections/: the 480px shrink rule now also reaches an h2 in a service-image column and an x-large h2 in a group. Measured on the live Objections page with the rule injected: 20.8px at 375 and 42px at 1440, identical to today's h3s. Pinned by `tests/heading-level-phone-size.php`.

## [15.8.2] - 2026-10-10 — llms.txt lists Start Here and Objections, and its counts follow the site

### Fixed
- **llms.txt lists Start Here and Objections, and its summary counts can no longer go stale.** Read live on 2026-10-10, the summary said "Forty-plus notes, two SSRN papers" while the site held 51 notes and its own Pillar essays section listed three papers. The counts are now derived (`sn_llms_txt_summary_facts()`): notes from the published, non-password posts, the same corpus /notes/ lists (`sn_llms_txt_note_count()`, filters suppressed), papers from the pillar descriptors the Pillar essays section lists; a count that is unknown is left out, never guessed. Key pages gains `/provenance/objections/` directly after Provenance and `/notes/start-here/` directly after Notes, listed by hand on purpose: the list is not derived from the children of `/provenance/`, whose pillar essays have their own section. The `get-llms-txt` ability now passes the same inputs as the route, so its index output is the served `/llms.txt` byte for byte and its full output carries Topics, which it had been missing. No cache was added: the file rides the edge's machine lifetime (5 minutes) and the plugin's publish purge. The Rights section is unchanged, pinned byte for byte against `tests/fixtures/llms-rights.txt`, captured before the change. Pinned by `tests/llms-txt.php`.

