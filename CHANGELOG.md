# Changelog

All notable changes to Signal & Noise are documented here.

## [Unreleased] — Notes MCP harness documented (docs only, no version bump)

**Headline:** the drafting-harness facts that were living in session memory are now written down, and two of the remembered ones were wrong.

### New

- **[docs/NOTES-MCP-HARNESS.md](docs/NOTES-MCP-HARNESS.md)** records how `sn` / `sn-write` behave when drafting and editing Notes: `create_draft` (revision-only, never publishes), `delete_draft` (v10.58.0, trash-only via `wp_trash_post`, draft-only, `change.fingerprint` required, `rollback.method` is `manual_untrash`), `link_reshape` (v10.58.0, the only path to move an anchor's boundaries, retargeting explicitly out of scope), `unlink` (v10.59.0), and `change.payload.edits` (v10.66.0, batches N prose splices into one write for the `sentence_replace` / `emdash_replace` / `drift_replace` family, all-or-nothing, max 50).

  Every statement was checked against the live tool descriptions, and the behavioral ones against live calls, on 2026-08-08.

### Fixed

- **Two claims that had been circulating were false and are corrected in writing.** A `link_reshape` change type and a `delete_draft` path were both described as undocumented or absent; both have existed since v10.58.0. A created draft was described as unrollable; `delete_draft` is exactly that rollback, and `create_draft`'s response carries the fingerprint it needs.

- **The corpus figure was stale.** The archive is **30 published and 11 scheduled**, not 37 with seven scheduled (`list-posts`, `status: any`, 2026-08-08). The doc says to re-count rather than quote.

- **The `links` validation surface is host-relative and the doc now says how.** With `compare_against: "none"`, `body` / `tags` / `excerpt` / meta evaluate on their own terms, but `links` does not detach from the host post: `target_exists` errors on any non-`publish` target, so a scheduled target is reported missing, and with `proposed.body` omitted both `not_already_linked` and `anchor_present` resolve against the host post's body. The practical rule is to omit `links` while drafting and re-validate against the real `post_id` after `create_draft`. Related editorial constraint: a Note body must not link to a scheduled post, because the target 404s until that post publishes, so publish order constrains the schedule date.

  Also recorded: **`checks: "all"` is accepted.** That one was asserted as broken and is not. The bare string works, including in the drafting harness, verified live.

> **Why no version bump:** documentation only. Per [CLAUDE.md](CLAUDE.md), `docs/` changes do not bump `Version:`.

## [11.5.1] - 2026-08-08 — the twin's fallback verify link stops pointing at a 404

**Headline:** a Note without a uid published a dead verify link in its machine-readable twin, and the test asserted that dead link was correct.

### Fixed

- **`sn_content_json_document()`'s fallback `verify_url` was `/provenance/verify/`, which returns 404 live** (confirmed 2026-08-08 against production, redirects followed). The uid-ful branch already upgrades to the real docket at `/verify?note=<uid>`, so this only ever surfaced for a Note whose uid meta was missing — but for those it published a dead link to every machine reading the twin.

### Changed

- **The assertion is now a relationship, not a literal.** The old line read `=== 'https://juanlentino.com/provenance/verify/'`, so it did not merely miss the defect — **it asserted the defect was correct**. It now requires the uid-less fallback and the uid-ful docket URL to share a base path, so the two can only drift together.

  This is the third place the same broken URL was frozen as expected behaviour (the plugin's credential fixtures were the other two, fixed in plugin v10.66.1 alongside the reader-facing "Verify it yourself" link that 404'd on every Note). A literal pin freezes whatever was true when it was written, and then defends the wrong side the moment reality moves.

> **Why PATCH:** one broken URL repaired and its test strengthened. No new capability, no markup change, no API change.

## [11.5.0] - 2026-08-05 — /stats joins the footer, and two aria-labels stop speaking em-dashes

**Headline:** the public stats page has been live and reachable only by typing the URL. It now has an icon in the footer meta-nav, beside the colophon.

### New

- **`/stats` joins the footer meta-nav**, as a fifth stroke icon (three ascending bars) between Colophon and Privacy. The row now reads *what I'm doing → how to use it → how it's made → how it's read → legal*, which puts the two "this site about itself" entries next to each other and keeps Privacy last as the legal anchor. The icon speaks the row's existing grammar: 16 grid, 1.5 stroke, `fill="none"`, `currentColor`, `aria-hidden` + unfocusable, with the accessible name on the anchor (icon-only links have no universal glyph, so the label is the only discoverable name).

### Fixed

- **Two footer `aria-label`s carried em-dashes** (`Now — what I'm focused on`, `Colophon — how this site is made`) and now use a colon. A screen reader speaks these, so they are reader-facing prose under the house style. The v11.4.7 sweep missed them because it measured rendered text with tags stripped, which makes attribute copy invisible. Worth recording: the em-dash scanner shipped in plugin v10.51.0 would also skip these, since it classifies anything inside a tag as `inside_markup` — correct for `href` and `class`, wrong for `aria-label`. A known, narrow gap.

### Changed

- **`tests/footer-meta-nav.php` counts are now relational rather than literal.** It pinned "four inline icons / three separators"; it now asserts one icon per link and one fewer separator than links. A hard-coded count only says somebody updated a number when the row grew; the relationship says the row is internally consistent, which is the property that matters. Same reasoning as the schema/registry equality assertion added in plugin v10.52.1.

> **Why MINOR:** a page that existed but could not be reached from anywhere on the site is now navigable. That is a new user-visible capability, not a fix.

## [11.4.10] - 2026-08-07 — the fixed footer stops eating page bottoms

**Headline:** the fixed-footer clearance rule finally applies — it had silently never worked on page templates.

### Fixed

- **`main.wp-block-group { padding-bottom: 140px }` is now `!important`** ([assets/css/layout.css](assets/css/layout.css)). The block templates serialize their spacing as an INLINE style on `<main>` (`padding-bottom: var(--wp--preset--spacing--60)` ≈ 36px), and an inline style beats every selector — so the clearance rule written for the fixed footer had silently never applied on `page.html`/`single.html` pages. Measured live 2026-08-07 on the maturity hub at 2000px: at maximum scroll the last content row sat provably under the fixed `.sn-footer` bar (last card bottom 1108 vs bar top 1097) — the final ~58px of every page-template page was permanently covered, reported as "the pages don't scroll all the way down to see everything." Dense-bottomed pages (the maturity family) made a site-wide defect visible. The `!important` is load-bearing and documented in place: a structural clearance requirement that page-level spacing must never defeat.

## [11.4.9] - 2026-08-05 — "we do not know" stops reading as "it failed"

**Headline:** the purge report's Varnish leg carries the companion's two remaining qualifiers, so an inconclusive purge is no longer recorded as a failed one.

### Fixed

- **`sn_write_purge_report()` carries `inconclusive` and `stage`** ([inc/purge-verify.php](inc/purge-verify.php)), alongside the `coalesced` and `reauthed` markers added in v11.4.8. Companion v10.52.5 marks a transport failure — a timeout, a DNS failure, a reset — as **inconclusive** rather than failed, because it means we never heard back, not that the purge did not run. That distinction was earned live on 2026-08-05: a 5-second timeout was recorded as a failure, and the operation it started was found still running three seconds later by the next call, which coalesced onto it. `stage` says which step the attempt reached (`auth` or `dispatch`) instead of leaving it to be inferred from which keys are present.

  Copied **only when present**, so a report written against an older companion keeps its existing shape rather than gaining `false` values that read as "checked, and negative". 5 new assertions in [tests/purge-verify.php](tests/purge-verify.php) pin both the inconclusive and auth-stage cases, including that an auth failure is *not* marked inconclusive.

> **Why PATCH:** two optional fields added to one leg of a diagnostic record. No behaviour, template, or public surface changed.

## [11.4.8] - 2026-08-05 — the purge report stops making its reader infer

**Headline:** the Varnish leg gains the two qualifier markers the companion plugin now emits, so `ok: true, http: 422` reads as what it is instead of as a contradiction.

### Fixed

- **`sn_write_purge_report()` carries `coalesced` and `reauthed` through to the durable report** ([inc/purge-verify.php](inc/purge-verify.php)). The Varnish leg reads the companion plugin's `sn_cloudways_last_purge`, and that record gained two qualifiers: `coalesced` (companion v10.52.2 — Cloudways serializes cache operations, so a purge issued while one is open is rejected 422, and when the blocking operation is itself a running purge the plugin now rides it) and `reauthed` (companion v10.52.4 — a cached OAuth token the API rejected, replaced and retried once).

  The theme copied only `via`/`ok`/`http`/`operation_id`, so the durable record showed `ok: true, http: 422` with no explanation, and the reader had to already know that combination means "coalesced" rather than a contradiction. That is precisely the inference that cost an afternoon on 2026-08-05, when a `422` in this leg was first read as a broken Varnish tier and then as a three-week outage, before a control reading showed it was neither.

  Copied **only when present**, so a report written against an older companion keeps its current shape rather than gaining `false` values that read as "checked, and negative" — 7 new assertions in [tests/purge-verify.php](tests/purge-verify.php) pin both directions, including that a plain 200 purge gains neither key.

> **Why PATCH:** one leg of a diagnostic record gains two optional fields. No behaviour, no template, no public surface changed — the theme still writes the same report at the same time.

## [11.4.7] - 2026-08-05 — the four em-dashes the theme itself put on the page

**Headline:** a live-site audit for house-style em-dashes found the published writing already clean (3 in 30 notes, and only 2 notes affected). Nearly everything flagged turned out to be CMS page content or invisible CSS comments. These four are the ones the **theme** emits into a reader's page, so they are the theme's to fix.

### Changed

- **The three `/notes` section labels drop the em-dash:** `Notes — Index`, `Notes — Search · "term"`, and `Notes — Tag · "name"` become `Notes: …`. They are one family and change together. A crawl of the default `/notes` view only ever sees the Index variant, so fixing that alone would have left the other two inconsistent the moment anyone searched or opened a tag archive. The `&middot;` stays as the secondary separator, matching the site's existing byline vocabulary.
- **The empty-search state reads as two sentences:** `Nothing matches "x" — notes, essays, and pages all searched.` becomes `Nothing matches "x". Notes, essays, and pages all searched.` Reaching this string requires a search that matches nothing, which is why a live crawl never surfaced it; it is now pinned by a source assertion instead.

### Not changed (recorded so it is not re-flagged)

- `sn_index_title()` keeps `Index — <site>`. The em-dash there is the **site-wide document-title separator**, not prose: every page already titles as `Notes — Juan Lentino`, and that shape is set plugin-side. Changing it here alone would make `/index` the only page on the site with a different title. If the title separator is ever revisited it has to move everywhere at once.
- The four `inc/wp-update-integration.php` updater messages are **wp-admin** copy, not reader-facing. They belong to a separate admin-copy pass, which is deliberately not bundled here because roughly 50 of those strings are pinned verbatim by tests, including `analytics-i18n`-style assertions that pin exact `__()` msgids as an i18n contract.

> **Why PATCH:** reader-visible copy only. No markup, structure, capability, or API change; the strings are unlinked and untranslated.

## [11.4.6] - 2026-08-05 — audit follow-ups: the palette's third array, and a linter that could not see SQL

**Headline:** the 2026-08-05 CMA post-ship audit returned **zero** findings against the `v10.44.5..v11.4.5` increment, so this release actions its three INFO observations plus a gap the audit's own falsification probe exposed: `phpcs.xml.dist` pulled in `WordPress.Security`, which does **not** carry the DB sniffs, so unprepared SQL was invisible to the theme's linter while the plugin's caught it.

### Fixed

- **Pillar CTA is built from `home_url()`, not a bare `/<slug>/`.** `blocks/pillar-essays/render.php` emitted a root-relative href while the command palette emitted an absolute one for the very same pillar. The audit filed this as cosmetic; it is not — on a subdirectory install (`home_url()` = `example.com/blog`) a root-relative `/slug/` resolves to `example.com/slug/`, outside the install. Latent rather than live, since this site is root-installed, and now pinned by a test whose `home_url()` stub is deliberately a subdirectory.

### Improvements

- **`phpcs.xml.dist` gains `WordPress.DB.PreparedSQL`** (with `DirectDatabaseQuery` silenced, matching the plugin's ruleset). The theme's two `$wpdb` call sites already hold — `inc/cited-by.php` uses `->prepare()` + `esc_like()`, `inc/template-maintenance.php` interpolates only `$wpdb->options`, a table name, which cannot be a placeholder — so the rule enters green and stays a live gate instead of becoming a backlog. Verified by injecting a violation inside the scanned tree and confirming `PreparedSQL.NotPrepared` fires, per this ruleset's own standing warning that exit 0 can mean "scanned nothing."
- **The command palette normalizes all three corpus arrays identically.** `recent` and `notes` ran titles through `html_entity_decode()`; `pillars` passed the descriptor title through raw, so a wptexturized pillar title would render its entity literally. Cosmetic only — the JS writes every label via `textContent`, never `innerHTML`.
- **Password-protected entries stay off the bulk corpus surfaces.** `has_password => false` added to the palette's `recent` and `notes` queries and to `sn_notes_query_posts()` (both browse and search). No leak was open: those surfaces emit titles and permalinks, which WordPress treats as public, and a protected post's `get_the_excerpt()` is already the "There is no excerpt because this is a protected post." placeholder. This states the convention at the query — matching `inc/feed-json.php` and `inc/llms-txt.php`, which gate because they render actual content — so a future change that starts emitting excerpts cannot silently widen the surface. It also drops the useless placeholder rows from search results.

> **Why PATCH:** no new capability and no public API change. One latent-correctness fix, one linter gate, and two convention-consistency changes, all behavior-preserving for a root install with no protected posts on the index.

## [11.4.5] - 2026-08-04 — the uniform title scale becomes uniform

**Headline:** v11.4.1 named `clamp(3rem, 8vw, 7rem)` the site-wide title scale and moved `/notes` onto it. Four surfaces never followed, and a phone-width rule was quietly shrinking the one surface that had. Measured against the live site before fixing: at 768px `/now` rendered its headline at **92px** against `/notes`' **61px**; at 375px `/resume` rendered **37.5px** against `/notes`' **48px**. Three "uniform" titles at three sizes. The old and new clamp curves coincide only between roughly 933px and 1400px, which is exactly the band the screenshot reviews happened in, so five consecutive releases each fixed one surface without the pattern becoming visible.

> **Why PATCH:** consistency cleanup, dead-rule removal, a print fix, one ability bug fix, and new test coverage. No new user-visible capability, no removed or renamed public API, nothing requiring user action. Per [docs/VERSIONING.md](docs/VERSIONING.md), a bug fix is not breaking even when it changes observable output.

### Fixed

- **`/now`, `/uses`, `/index`, `/accessibility` headlines join the uniform title scale** ([now.css](assets/css/now.css), [uses.css](assets/css/uses.css), [index.css](assets/css/index.css), [accessibility.css](assets/css/accessibility.css)). All four still declared the superseded `clamp(3.5rem, 12vw, 7rem)` with `line-height: 0.9`; they now match `/notes` and `/resume` at `clamp(3rem, 8vw, 7rem)` / `0.95`. Verified by injecting the new declarations into the live page: `/now` resolves to 48px at 375px and 61.44px at 768px, byte-identical to `/notes` at both widths.
- **`/resume` headline stops being force-shrunk on phones** ([responsive.css](assets/css/responsive.css)). The `@media (max-width: 480px)` block matched `.wp-block-heading[style*="clamp(3rem"]` with `!important`. That attribute-substring selector was unique to the front-page hero when written, but plugin v10.37.0 normalized every generated page title onto `clamp(3rem, 8vw, 7rem)`, so it silently began matching the plugin-emitted `/resume` H1 and overriding its inline style (an `!important` author rule outranks an inline declaration). The rule is now scoped to `.sn-hero-title`, the class `templates/front-page.html` already carries. **Never match a value the design system has standardized on** — the selector's reach widens as the codebase converges on the token.
- **`get-page-notes-pillars` returned an empty `last_modified` for every pillar** ([inc/abilities-content.php](inc/abilities-content.php)) — confirmed live over MCP: all three pillar essays returned `"last_modified":""` while every other field populated. The lookup asked `get_page_by_path( $path, OBJECT, 'post' )`, but pillars are **Pages** (`sn_theme_pillar_descriptors()` selects on `post_type => 'page'`), and core matches `post_type IN ($post_type, 'attachment')`, so the lookup could never resolve. Identical root cause to the v11.2.2 `get-reading-time-for-slug` fix, which corrected two test stubs and missed this third site.
- **The stub guarding that ability ignored both of its arguments** ([tests/ability-page-notes-viewability.php](tests/ability-page-notes-viewability.php)) — it returned a modified post object unconditionally, which is why three assertions stayed green against a production path that always returned empty. It now models core's real `post_type` filter, and pins the page-resolves, wrong-type, and unknown-path cases. Falsified: reverting the fix turns three of six assertions red.
- **`/resume` split hero stacks at 900px, matching `/notes`** ([resume.css](assets/css/resume.css)). `/notes` gates its hero grid at 900px while core's `wp:columns` stacks at 782px, so between 782px and 899px `/resume` was two columns while `/notes` had already stacked. Verified live at 840px before the fix.
- **The early-career fold no longer vanishes from printed resumes** ([print.css](assets/css/print.css)). `.sn-resume-fold` is a `<details>` that ships closed, and a closed `<details>` omits its content from print entirely, so the most-printed page on the site was silently dropping a section. Folds now force open for paper and the `+`/`–` affordance is suppressed. The "Download PDF" button is also stripped, having previously printed as a bordered box followed by its own raw URL.
- **Two bare `font-size: 0.7rem` declarations join the 11px floor** ([inc/page-notes-render.php](inc/page-notes-render.php)) — the `/notes` eyebrow, meta, section label, and subscribe line now use `max(0.7rem, 11px)` like every peer, including two overrides in the same file.
- **The cross-package listener count disagreed in three places** — `functions.php` said 7, `docs/WORDPRESS-REFERENCE.md` said 8, and `tests/cross-package-listeners.php` said 8 while testing 9. Nine are live; `sn_seo_robots_directives` (added v10.51.1) was never tabled. All three now say 9, the §10.0 table gains its row, and the suite's meta summary accounts for all nine. Two suite sections were also both numbered "Contract 9"; the robots section is renumbered 11.
- **A stale module path in [docs/MONITORING.md](docs/MONITORING.md)** pointed at the theme's `inc/og-image.php`, which moved to the plugin as `inc/og-card-generator.php` in v8.4.0.

### Removed

- **A dead reading-measure rule in [resume.css](assets/css/resume.css)** (`padding-right: 14rem` on a direct-child `p.has-body-font-family`, plus its 781px reset). It has matched nothing since plugin v10.35.0 split the hero: the band's direct children are now the eyebrow and the columns wrapper, while the summary paragraph sits two levels down and carries its own measure. Confirmed against the live page, where the selector matched zero elements. Its comment also described a 960px column that has been 1320px since v11.3.0. Same dead-rule class v11.4.4 removed from `/now` and `/uses`.

### Changed

- **Hero eyebrows unify on blood** ([now.css](assets/css/now.css), [uses.css](assets/css/uses.css), [index.css](assets/css/index.css), [accessibility.css](assets/css/accessibility.css)). `/notes`, `/resume`, `/about`, `/services`, and `/music` render the eyebrow in blood at `margin-bottom: 1rem`; these four rendered it in rust (`#333`) at `0.75rem`. `components.css` declares `.sn-catalog-eyebrow` the standardized treatment, so the four were the outliers. Markup is untouched; the plugin emits these class names and the theme owns their color.
- **British spellings corrected to American English** across `readme.txt`, `inc/`, and `assets/css/` prose (honour, behaviour, normalise, recognise, colour).

### New

- **[tests/notes-hero-structure.php](tests/notes-hero-structure.php)** — the `/notes` hero had **zero** test coverage across the five releases (v11.3.0 through v11.4.4) that restructured it; no suite referenced a single hero class. Source-level assertions pin the two-column split, the source ordering, the uniform title scale, and most importantly the `! $sn_filtered` guard, which is a behavioral contract rather than styling: corpus counts describe the whole corpus, so rendering them over a search or tag result set mislabels the result set. Falsified by breaking the guard and confirming the suite goes red.

## [11.4.4] - 2026-08-03 — /notes hero gets the resume treatment

### Changed

- **/now and /uses split-hero grid CSS removed** ([assets/css/now.css](assets/css/now.css), [assets/css/uses.css](assets/css/uses.css)) — plugin v10.37.5 returns those heroes to plain left stacks (no real side content), so the `:has()`-guarded grid is dead code. Stale width/alignment comments refreshed in [assets/css/resume.css](assets/css/resume.css) and the notes renderer.
- **/notes hero restructured to match /resume** ([inc/page-notes-render.php](inc/page-notes-render.php)). Eyebrow becomes a kicker spanning the hero grid; the dek reads under the NOTES. title in the left column; the side column carries the subscribe line first with the corpus meta ("N entries · Last updated") as its closing stamp — inverted from before, per owner direction. Search/tag suppression of the meta unchanged.

## [11.4.3] - 2026-08-03 — /resume credential chips become a hairline ledger

### Changed

- **/resume hero right column de-blocked** ([assets/css/resume.css](assets/css/resume.css)). Owner review: summary + boxed chips + contact + PDF read as one dense block. The bordered chip boxes become a credential ledger — 2px bone rule on top, one credential per row on a concrete hairline — echoing the notes-index rhythm. Markup unchanged (pure CSS; plugin untouched).

## [11.4.2] - 2026-08-03 — top alignment becomes the single split-hero rule

### Fixed

- **/notes, /now, /uses hero grids switch bottom → top alignment** ([inc/page-notes-render.php](inc/page-notes-render.php), [assets/css/now.css](assets/css/now.css), [assets/css/uses.css](assets/css/uses.css)). With the uniform title scale (v11.4.1) the side column is taller than the title block, so bottom alignment floated the dek above the eyebrow (owner screenshot review). /resume set the pattern in plugin v10.36.1; every split hero now top-aligns — eyebrow and dek start on the same line. The baseline-compensation paddings are gone with the rule that needed them.

## [11.4.1] - 2026-08-03 — /notes headline joins the uniform title scale

### Fixed

- **/notes headline drops from clamp(4rem, 14vw, 11rem) to the site-wide clamp(3rem, 8vw, 7rem)** ([inc/page-notes-render.php](inc/page-notes-render.php)). Owner audit: it was the lone 176px outlier against 112px titles everywhere else. Companion to plugin v10.37.0's one-frame normalization.

## [11.4.0] - 2026-08-03 — /now + /uses split heroes

### Changed

- **/now and /uses heroes join the split-hero system** ([assets/css/now.css](assets/css/now.css), [assets/css/uses.css](assets/css/uses.css)). At ≥900px each hero lays out as a bottom-aligned two-column grid — eyebrow + headline left; dek + meta right — matching /notes (v11.3.0) and /resume (plugin v10.35.0). The grid is `:has(.sn-*-hero-side)`-guarded so pre-split page bodies keep the single stack until plugin v10.36.0 regenerates them; below 900px the stack is unchanged.
- **/notes index excerpts loosen 60ch → 80ch** ([inc/page-notes-render.php](inc/page-notes-render.php)) — the tighter cap left the right half of each row empty inside the v11.3.0 1320px container.
- **/now and /uses containers widen 60rem → 1320px** (same files), completing the uniform width across the split-hero surfaces.

## [11.3.0] - 2026-08-03 — /notes split hero: title left, dek + meta + subscribe right

### Changed

- **/notes hero is a two-column editorial split** ([inc/page-notes-render.php](inc/page-notes-render.php)). The single stack (eyebrow → NOTES. → dek → meta → subscribe) left the right half of wide viewports empty since the pillar rail moved out in v10.47.0. At ≥900px the hero is now a bottom-aligned grid: eyebrow + headline in the left column; dek, corpus meta, and the subscribe line in the right, sitting on the headline baseline. Below 900px the original vertical order is unchanged.
- **/notes container widens 1180px → 1320px** (same file) so the index rows and hero use the viewport, matching the /resume band width shipped in plugin v10.35.0.

## [11.2.2] - 2026-08-01 — get-reading-time-for-slug: resolve posts, compute minutes directly

### Fixed

- **`get-reading-time-for-slug` returned `minutes=0` for every published post over REST/MCP** ([inc/abilities-content.php](inc/abilities-content.php)) — verified live 2026-08-02 on `provenance-signs-the-claim-not-the-truth` (556 words → reported 0). Root cause: the ability's oracle gate resolved slugs with `get_page_by_path( $slug, OBJECT, 'page' )` — **pages only** — so a post slug never resolved and the uniform non-viewable `minutes=0` fired before any computation. Two changes:
  - **Resolution now tries pages first, then posts.** The public-only viewability gate (v9.15.6) applies identically to both — a draft/private post returns the same uniform `minutes=0` as a missing slug, so the existence-oracle posture is unchanged.
  - **Minutes are computed directly via the plugin's `sn_get_reading_time()`** (the 225-wpm source of truth with its post-meta cache) instead of rendering and string-parsing the `[sn_reading_time]` shortcode. The shortcode and the theme's `sn_notes_reading_time_for_slug()` helper are untouched for front-end use. If the plugin's reading-time module is absent the ability now fails loudly with `plugin_dependency_missing` (503) rather than a fabricated "5 min".
  - The plugin's `sn-site-facts` `reading_time` fact dispatches to this ability, so it inherits the fix with no plugin change.
- **Test-stub drift fixed in both ability suites** ([tests/abilities-registration.php](tests/abilities-registration.php), [tests/abilities-integration.php](tests/abilities-integration.php)) — both stubbed `get_page_by_path()` ignoring its `$post_type` argument (one said so in a comment), which is exactly why 2,000+ assertions stayed green while the live ability failed. The stubs now model core's real filter (`post_type IN ( $post_type, 'attachment' )`), and new fixtures pin the published-post (556 words → 3 min), draft-post (uniform 0), and page (unchanged) paths.

## [11.2.1] - 2026-07-31 — Cross-repo audit hygiene batch: stale answer + stale metadata + doc drift

Fixes-and-docs pass from the 2026-07-31 cross-repo audit. Each item was re-verified against current code before fixing (some audit premises had already expired — see below).

### Fixed

- **`get-reading-time-for-slug` ability served the wrong `wpm_basis`** ([inc/abilities-content.php](inc/abilities-content.php)) — reported `220`, but the plugin's reading-time module has computed at **225 WPM** since the module's own rebuild (see the CHANGELOG history at "225 WPM default"). A stale answer served to agents; corrected to `225` in both response sites and the docblock.
- **Stale plugin-version / model claims in ability metadata** — `inc/abilities-helpers.php`'s AI-unavailable error message named a specific floor ("v3.7.x+") that no longer communicates anything useful at plugin v10.x; reworded to name the requirement (AI helper) without a version pin. `inc/abilities-ai-generation.php`'s `ai-generate-page-note-summary` description claimed "Sonnet 4.6 pinned via plugin v3.7.2+"; the plugin's actual default model is `claude-sonnet-5` (configurable via the plugin's Front-End settings, `inc/ai-bootstrap.php`), not a hard Sonnet-4.6 pin — description corrected to match.
- **Doc drift in readme.txt / README.md** — readme.txt still recommended Contact Form 7 as an optional plugin; CF7 was fully removed in v10.12.0 and `tests/cf7-removal.php` is a standing regression guard against its reintroduction — replaced the recommendation with a note pointing at the removal. README.md's Stack line claimed "PHP 8.0+"; the real floor (style.css `Requires PHP`, readme.txt) is `8.3` — corrected. README.md's "Pages & templates" list still named Colophon among the theme's block templates; `templates/page-colophon.html` was deleted in v11.1.11/#145 (CMS-owned since plugin v10.13.0, rendered via a Site Editor `wp_template` override of `[sn_colophon]`) — corrected to describe it as CMS-owned rather than a theme template.

### Verified, no change needed

- **Command palette `recent` key** ([inc/command-palette.php](inc/command-palette.php)) — flagged as possibly redundant with the v11.2.0 `notes` key (an extra `WP_Query` per pageview). Verified `assets/js/command-palette.js` consumes `data.recent` distinctly for the empty-query default view (before `data.notes` search-ranking kicks in) — removing it would break that state. Left as-is per the audit's own guardrail ("a removal that breaks ⌘K is worse than the extra query").
- **`get-seo-route-meta` ability's premise** (`/colophon` lacks a Page Excerpt) — confirmed live: the WP REST API reports an empty excerpt for the Colophon page, yet `https://juanlentino.com/colophon/` serves a real meta description sourced from the theme's own `inc/seo-route-meta.php` hardcoded fallback map, matching verbatim. Premise holds; ability retirement is out of scope for this pass.

> **Why PATCH:** doc corrections, a stale-answer fix, and stale-metadata wording — no behavioral or schema change.

## [11.2.0] - 2026-07-30 — The theme adopts the ML kernel: kernel-ranked related notes + ⌘K ranked search

The companion plugin's deterministic ML kernel (plugin v10.15.0–v10.17.0) now has its first two theme-side reader surfaces. Both honor the house discipline: the kernel computes server-side, the theme renders — and **no model ever ships to the reader's browser** (the ⌘K ranking is transparent token arithmetic, source-asserted in the test suite).

### Added

- **Related Notes goes kernel-ranked** ([inc/related-notes.php](inc/related-notes.php)) — `sn_related_notes_query()` now leads with the plugin's `snt_ml_related_for_post( $post_id, $limit )` blended-relatedness ranking when the accessor exists. Contract honored exactly as shipped in the plugin's `inc/ml-artifacts.php`: ranked rows of `{post_id, score}`, `null` when the artifacts were never built, `[]` when the post is unindexed (an empty ANSWER, per the null-vs-zero house rule). Kernel picks are re-verified theme-side (publish-only via `get_post()` + `get_post_status()`, never self, deduped) against upstream drift; any shortfall — null, error, empty, or partial — tops up through the **unchanged** shared-tag + recency heuristic, excluding self and everything already selected. When the plugin is absent the code path is byte-identical to v11.1.11 — the test suite's legacy assertions all run with the accessor genuinely undefined before the kernel stubs are conditionally introduced.
- **⌘K ranked search over the full notes corpus** ([inc/command-palette.php](inc/command-palette.php), [assets/js/command-palette.js](assets/js/command-palette.js)) — the data island gains a `notes` key: ALL published notes as `{t,u}`, bounded (`sn_palette_notes_cap`, default 200, hard-clamped at 200; `no_found_rows`; date DESC — ~3-4 KB inline at the current ~34-note corpus). Typing now ranks the whole corpus client-side with plain token scoring: lowercase whitespace tokenization, per-token title-word **prefix** match (2) beats substring match (1), every token must land or the note is disqualified; score DESC with recency as the stable tiebreak, capped at 8. The best match is the active row, so Enter opens it directly; the existing "Search notes for …" `/notes/?s=` action survives as the final fallback row, and the empty-query state (search + pillars + recent) is exactly as before.

### Unchanged on purpose

- The v9.11.0 palette accessibility contract (APG dialog + combobox, `aria-activedescendant`, focus trap) and XSS discipline (JSON_HEX_TAG island, `textContent`-only DOM writes — now source-asserted: no `innerHTML`, no `fetch`/`eval`/WASM) carry through untouched; `isFormField` stays verbatim-locked to tests/keyboard-nav.php.
- The related-notes footer markup, the `sn_related_count` filter, and the render_block bridge are untouched — only the ranking source changed.

> **Why MINOR:** two new user-visible capabilities (kernel-ranked related notes, full-corpus ranked palette search); no breaking change — both degrade to prior behavior when the plugin or its artifacts are absent.

## [11.1.11] - 2026-07-30 — The dead colophon template leaves the theme

### Removed

- **`templates/page-colophon.html` + `patterns/colophon.php`** — the colophon moved to CMS ownership in plugin v10.13.0: /colophon renders the plugin's `[sn_colophon]` shortcode through a Site Editor `wp_template` override of `page-colophon`. The theme's slug-bound template file was therefore permanently shadowed dead code that would have resurrected stale hardcoded stack/type credits if the DB override were ever deleted. The `signal-noise/colophon` pattern existed only to serve that template (verified referenced nowhere else; the live DB override carries a detached, since-edited copy, not a pattern reference), and the `page-colophon` `customTemplates` entry in theme.json registered the removed file.
- [tests/colophon-template.php](tests/colophon-template.php) is rewritten as a tombstone suite guarding against resurrection (template, pattern, and theme.json entry stay gone; the footer's /colophon link and the absence of stray `signal-noise/colophon` references stay checked).

### Unchanged on purpose

- `inc/colophon-meta.php` (`[sn_build]`) and the setup.php render_block shortcode bridge stay — they serve CMS-owned content, not the removed template.

> **Why PATCH:** dead-code removal with no behavioural change; /colophon keeps rendering from the CMS-owned override.

## [11.1.10] - 2026-07-29 — Resume bullet emphasis goes quiet

### Changed

- **Bullet `<strong>` renders at body weight on /resume** — the owner rejected visual bolding mid-sentence; the tags stay in the content so machine readers keep the semantic emphasis, but `.sn-resume-list li strong` now renders `font-weight: 400` ([assets/css/resume.css](assets/css/resume.css)). Credentials-column bolds (degree names, member lines) are untouched.

> **Why PATCH:** design calibration of an existing surface; no new capability.

## [11.1.9] - 2026-07-29 — Resume highlights done the site's way: credential chips

### Changed

- **The v11.1.8 blood `<mark>` rule is removed** — inline color in body copy broke the site's discipline (red is chrome: eyebrows, meta labels, hover states — never content). Cohesive highlighting on this site is structural.
- **`.sn-resume-chips`** — a credential chip row for the hero (GRAMMY / Latin GRAMMY voting membership, MBA, SSRN research), mirroring the discography's `.sn-disco-chip` idiom: mono 11px-floor uppercase, hairline bone border, authored as a plain list block ([assets/css/resume.css](assets/css/resume.css)).

> **Why PATCH:** design calibration of an existing surface; no new capability.

## [11.1.8] - 2026-07-29 — Resume highlight primitives

### Improvements

- **`<mark>` as the /resume highlight primitive** — renders as blood ink with no background, so the editor's own Highlight tool becomes the way to flag credentials worth flagging (first use: the GRAMMY / Latin GRAMMY voting-member line); future highlights need no code or paste ([assets/css/resume.css](assets/css/resume.css)).
- **`<strong>` metric emphasis in achievement bullets** — proper 700-weight mono for the one key figure per bullet.

> **Why PATCH:** design calibration of an existing surface; no new capability.

## [11.1.7] - 2026-07-29 — Resume left-pin ROOT CAUSE: the width overrides matched <main>

**Headline:** the "page sits left" report that survived six patches is fixed at its actual root. The `:has()` width overrides introduced in v11.1.2 used bare `.wp-block-group` selectors, which also matched the template's `<main class="wp-block-group">` — capping the entire post-content wrapper at 1400px with no centering (main is a flow layout, not constrained). On any viewport wider than 1400px the whole page rendered in a left-pinned box. At viewports ≤1400px the box filled the screen, which is why the defect was invisible in narrower verification.

### Fixed

- **Removed every container-width override from [assets/css/resume.css](assets/css/resume.css)** — the Page content now declares its constrained widths directly (960px sections, 1400px stat band) and WordPress's constrained layout centers them with auto margins, the block-theme handbook mechanism. The stylesheet keeps only component styling.
- **Remaining structural selectors qualified as `div.wp-block-group`** (intro measure, section dividers) so no rule in this sheet can ever reach `<main>` again; the file header documents the trap.

> **Why PATCH:** fixes the v11.1.2 regression at its root; no new capability.

## [11.1.6] - 2026-07-29 — Resume intro paragraph respects the column

### Fixed

- **The hero intro dragged the page's optical center left** — its reading-measure `padding-right: 14rem` sat on a content-box paragraph (the theme does not force `border-box` on `p`), so the box GREW 224px past the shared 960px column, starting far left of every other element. `box-sizing: border-box` on the rule keeps the measure inside the column ([assets/css/resume.css](assets/css/resume.css)).

> **Why PATCH:** fixes the v11.1.5 intro overflow; no new capability.

## [11.1.5] - 2026-07-29 — Resume hero joins the 60rem measure

### Fixed

- **The page read left-shifted (confirmed from a clean incognito session)** — the hero stayed on the 760px reading track while the body sections centered at 60rem, so every section started ~100px left of the hero's edge; the v11.1.4 divider rules made the mismatch loud. The hero now joins the 60rem dossier measure so all sections share one left edge (the stat band alone stays the full 1400px). The intro paragraph keeps its reading measure via right padding, because a plain max-width would re-center it off the shared edge (the constrained layout's auto margins carry `!important`); the padding drops at mobile widths ([assets/css/resume.css](assets/css/resume.css)).

> **Why PATCH:** fixes the v11.1.2–v11.1.4 edge misalignment; no new capability.

## [11.1.4] - 2026-07-29 — Resume dossier design pass

**Headline:** one batched polish pass on /resume, all in the site's existing vocabulary: 2px file-divider rules above each numbered section, tabular numerals on dates and stats, an external-link `↗` on the SSRN titles, a date-rail hover scan affordance, and blood text selection on the page.

### Improvements

- **Dossier file-dividers** — each body section's eyebrow opens with a hard 2px bone rule, echoing the fold's and stat band's line weight so the whole page carries the file-separator skeleton ([assets/css/resume.css](assets/css/resume.css)).
- **Tabular numerals** on the date rails and stat numbers (`font-variant-numeric: tabular-nums`).
- **`↗` affordance** on publication titles, rust at rest and blood on hover.
- **Scan affordance** — hovering an experience row lifts its date rail from rust to ink (transition guarded under `prefers-reduced-motion`).
- **Blood selection** — `::selection` on this page renders blood on void; the sheet only loads on /resume, so the site-wide default is untouched elsewhere.

> **Why PATCH:** design calibration of an existing surface; no new capability, no API change.

## [11.1.3] - 2026-07-29 — Resume download button style actually applies

### Fixed

- **The hero download button stayed the default black slab** — the combined sheet's `.wp-block-file a.wp-block-file__button` (0,2,1) outranked v11.1.2's `.sn-resume-download .wp-block-file__button` (0,2,0) for background, padding, and type. The selector is now `.wp-block-file.sn-resume-download a.wp-block-file__button` (0,3,1), so the small mono outline idiom wins ([assets/css/resume.css](assets/css/resume.css)).

> **Why PATCH:** fixes a v11.1.2 rule that never took effect.

## [11.1.2] - 2026-07-29 — Resume sections center on a 60rem measure

**Headline:** third same-day UAT pass on the text resume. v11.1.1 widened the body sections to the full 1400px container but capped the prose inside, which piled the visual mass on the left with dead air right. The section children now cap at a 60rem dossier measure (date rail + 80ch bullets, exactly) and the constrained layout's auto margins center them; the stat strip keeps the full 1400px band.

### Fixed

- **Body sections read left-shifted** — the `:has()`-scoped promotion in [assets/css/resume.css](assets/css/resume.css) now sets the experience/credentials/publications/skills section children to `max-width: 60rem` instead of 1400px. Wider than the 760px reading track, zero right-side slack, and centered for free by the layout's `margin: auto` — matching how /music and /services center their constrained content. The stat strip stays on the 1400px wide track as a deliberate full band.
- **The hero download button was too heavy** — `.sn-resume-download .wp-block-file__button` restyles the default `wp-element-button` black slab as the site's small mono outline button (11px-floor uppercase, hairline border, blood on hover), sized to sit inside the hero rather than dominate it.

> **Why PATCH:** design calibration of an existing surface; no new capability, no API change.

## [11.1.1] - 2026-07-29 — Resume width + hierarchy calibration

**Headline:** same-day UAT pass on the v11.1.0 text resume, delivered entirely in the stylesheet so the pasted Page content never needs a re-paste: body sections promote to the 1400px wide track, job titles stop reading as metadata, and the duplicate bottom download CTA retires.

### Fixed

- **The reading track crammed the body sections** — `:has()`-scoped rules in [assets/css/resume.css](assets/css/resume.css) promote every section group carrying `sn-resume-*` components (Experience, Credentials, Publications, Skills, stats) to the 1400px wide track, overriding the content's declared 760px; the hero (the one group with an `h1`) stays on the reading track. The date rail widens from its inline 180px to 240px. Works because constrained-layout child rules use zero-specificity `:where()`, so body-prefixed selectors win regardless of print order — the width system is now owned by the theme, not the pasted markup.
- **Prose at the wide measure** — `.sn-resume-list li` and `.sn-resume-pub-title` cap at 80ch so line length stays readable on the wide track.
- **Job titles were too quiet** — `.sn-resume-title` (role lines, credential column heads) was on the 11px label scale; now 0.95rem medium mono with 0.08em tracking, clearly subordinate to the Bebas company heads but no longer lost against the bullets.
- **Skills table on small screens** — under 600px the label/items cells stack (label above its items) instead of squeezing beside each other; the label column's `width: 1%` hack becomes an explicit `15rem`, echoing the date rail.

### Removed

- **The duplicate bottom download CTA** — retired in CSS (the closing group hides; the hero's File-block download button is the single PDF entry point), again so the content needs no edit.

> **Why PATCH:** design calibration of an existing surface; no new capability, no API change.

## [11.1.0] - 2026-07-29 — The text resume

**Headline:** /resume drops the embedded PDF viewport for a text-first dossier: the resume reads as styled page content (stat strip, date-rail experience rows, folded early career, publication cards, skills table) with the PDF kept as a download button. The content itself lives in the CMS Page body; the theme ships the component styling.

### New

- **`assets/css/resume.css`** — the `sn-resume-*` component layer for the /resume Page: stat strip (`-stats`, `-stat-n`, `-stat-l`), date rail (`-rail`), role heads and titles (`-role`, `-title`), hairline achievement lists (`-list`), the early-career `<details>` fold (`-fold`, `-fold-co`), publication cards (`-pub`, `-pub-meta`, `-pub-title`), the restyled skills table (`-skills`), and File-block download rows (`-download`). Clones the /now + /uses dossier idiom: Bebas heads, mono 11px-floor labels, hairline rows, blood on hover only, preset tokens throughout, motion neutralized under `prefers-reduced-motion`.
- **`/resume` branch in [inc/cms-page-styles.php](inc/cms-page-styles.php)** — enqueues the sheet only on that Page, depending on `sn-components`, mirroring the /now, /about/uses, and /accessibility branches.

### Changed

- The Resume Page's block content (DB-side, pasted through the editor) replaces the core File block's inline PDF `<object>` preview with the text resume plus two download-button-only File blocks. The PDF embed never rendered reliably on mobile, fought the site's typography, and gave machine readers an empty tag on a page `llms.txt` advertises as "professional experience and credentials."

> **Why MINOR:** a new user-visible surface (the styled text resume) with no API or schema change.

## [11.0.0] - 2026-07-28 — The Search-corpus major

**⚠️ Action required: requires PHP 8.3+.** Nothing else needs attention — no content, no settings, no templates change. **Pairs with plugin v10.0.0**, which the search noindex depends on (`sn_seo_robots_directives`, added in plugin v9.88.0).

**Headline:** the theme half of the paired major. Search grew from the Notes index to the whole corpus across the v10.5x line; v11.0.0 makes that the stated contract, raises the PHP floor with the plugin, and drops the one deprecated URL the theme still answered.

### Changed

- **PHP floor raised to 8.3** ([style.css](style.css), [readme.txt](readme.txt), `composer.json`, CI matrix). 8.0/8.1/8.2 are all past end of life; Cloudways runs 8.4. Matched with plugin v10.0.0 so the pair states one floor.

### Removed

- **The deprecated top-level `/security.txt` alias** ([inc/security-txt.php](inc/security-txt.php)). RFC 9116 names `/.well-known/security.txt` as the canonical location and every compliant scanner reads it there; the alias existed only for pre-RFC probes. The canonical route is untouched, and the fixture now pins that the alias 404s.

### The search story this major states

Shipped across v10.51.0 and v10.51.1 and made permanent here: site search covers the **whole public corpus** (Notes and Pages) in one type-labeled list — **Note**, **Essay** (pillar-designated Pages), **Page** — while browsing `/notes` stays Notes-only by construction. Search-mode renders carry `noindex, follow` so a crafted `?s=` URL can never be indexed as site content. Entry stays keyboard-first: the command palette's first row and the `/` shortcut, with no new chrome.

> **Why MAJOR:** a removed public URL plus a PHP-floor raise, shipped as the paired half of plugin v10.0.0.

## [10.51.1] - 2026-07-28

### Security

- **/llms-full.txt no longer bakes a password-protected post's content into a public file** ([inc/llms-txt.php](inc/llms-txt.php)): a protected post IS `status=publish`, and with no manual excerpt the summary fell to `wp_trim_words( wp_strip_all_tags( $post->post_content ), 28 )` — raw content, bypassing the `post_password_required()` guard core applies inside `get_the_excerpt()`. The file's audience is explicitly LLM crawlers, so ingestion would be irreversible. The query now excludes protected posts (`has_password => false`, matching feed-json) with a row-level belt behind it. No protected post exists on the site today, so this closed a latent gate gap rather than active exposure. Found by the v9.88.0 hardening gate; pinned in [tests/llms-txt.php](tests/llms-txt.php).

### Fixed

- **The v10.51.0 search noindex actually fires now.** It filtered core's `wp_robots` — but the companion plugin removes that action so it can emit the robots meta itself, so the theme was mutating an array nobody printed (live-verified: a cache-busted `/notes/?s=` returned no `noindex`). It now answers the plugin's new `sn_seo_robots_directives` seam (plugin v9.88.0), the ninth cross-package listener. `sn_notes_search_robots()` takes and returns the seam's directive LIST rather than `wp_robots`' map — the v10.51.0 map shape was pinning a contract that could never fire. [tests/cross-package-listeners.php](tests/cross-package-listeners.php) gains Contract 9, including the assertion that would have caught this: nothing may hook `wp_robots`.

> **Why PATCH:** a latent leak gate and a shipped-inert feature made real; no new capability, no API change. **Requires plugin v9.88.0+** for the noindex (the theme degrades to v10.51.0 behavior on older plugins — the listener simply never fires).

## [10.51.0] - 2026-07-28

**Headline:** search grows to the whole corpus — the owned /notes search now surfaces essays and Pages in one type-labeled list, and search-mode renders are noindexed.

### New

- [inc/notes-index-helpers.php](inc/notes-index-helpers.php): **search mode queries the whole public corpus** (`post` + `page`) while browse mode stays Notes-only by construction — the owner-decided session-4 shape (one list, type-labeled). `sn_notes_result_type_label()` labels each result row: **Note** (posts), **Essay** (pillar-designated Pages via the `_sn_pillar` curation meta), **Page** (everything else); [inc/page-notes-render.php](inc/page-notes-render.php) renders the chip in the row spec column, search mode only. The search empty state now says what was searched.
- **Search-mode renders are noindexed** ([inc/page-notes-template.php](inc/page-notes-template.php) + pure `sn_notes_search_robots()`): a crafted `?s=` URL can no longer be indexed as site content (query-stuffing abuse); `noindex, follow` joins the existing robots directives, browse mode untouched. Live-verified gap: `/notes/?s=` carried no noindex before this.
- [assets/css/components.css](assets/css/components.css): the `.sn-notes-row-type` chip in the spec-column vocabulary (DM Mono, uppercase, concrete border).

### Changed

- [tests/notes-search.php](tests/notes-search.php): the "search stays Notes-only" pin — the old contract itself — updated to pin the new corpus-wide contract (an owner-decided behavior change, not a test accommodation). New assertions in [tests/notes-index-helpers.php](tests/notes-index-helpers.php) pin corpus/browse post types, all three labels, and the robots directives.

> **Why MINOR:** a user-visible capability change on the owned search surface (corpus + labels + noindex); no API removed. Note for session-4 history: the scope doc's "no search exists" premise was wrong — the surface, funnel, palette entry, and `/` shortcut all predated this; the real delta was the corpus, exactly what shipped here.

## [10.50.0] - 2026-07-28

**Headline:** the rights surfaces reach discovery — agents.json advertises TDMRep, the RSL license, and the TDM policy, and llms.txt gains a Rights section, so the machine surfaces that declare the site's rights are now themselves machine-discoverable (and smoke-guarded hourly).

### New

- [inc/agents-manifest.php](inc/agents-manifest.php): three rights surfaces join the discovery manifest — `tdmrep` (`/.well-known/tdmrep.json`, application/json), `rsl-license` (`/license.xml`, application/xml), and `tdm-policy` (`/tdm-policy/`, text/html). All three are worker-served at the edge; the theme owns discovery. Each was live-verified (status + content-type) before advertising, because the hourly smoke test's advertised-surface loop hard-fails an advertised 404 — which is exactly the guarantee this buys: a Cloudflare- or worker-side regression on any rights surface now turns CI red within the hour. URLs pinned in [tests/agents-manifest.php](tests/agents-manifest.php).
- [inc/llms-txt.php](inc/llms-txt.php): a **Rights** section (after Machine surfaces, both `/llms.txt` and `/llms-full.txt`) linking the RSL license and the TDM policy — the rights declaration now sits in the file AI agents actually read. Pinned in [tests/llms-txt.php](tests/llms-txt.php).

> **Why MINOR:** two new user-visible (machine-visible) capabilities on public surfaces; no behavior removed or changed.

## [10.49.0] - 2026-07-23

**Headline:** the structural tier — the head sheds ~575 lines of article-only CSS that every route was paying for, the /notes renderer gives up its load-bearing mid-file hack, llms.txt finally mentions the pillar essays, and the smoke test starts probing every machine surface the site advertises.

### New

- [inc/llms-txt.php](inc/llms-txt.php): a **Pillar essays** section (between Key pages and Feeds, in both `/llms.txt` and `/llms-full.txt`) derived from `sn_theme_pillar_descriptors()` — rows carry the block cards' "№ 1.01 · Title" vocabulary, link the essay Page, and ride the excerpt dek. Injected as a parameter (the `$notes` precedent) so [tests/llms-txt.php](tests/llms-txt.php) stays standalone; an empty descriptor set omits the section entirely.
- [.github/workflows/smoke-test.yml](.github/workflows/smoke-test.yml): the hourly smoke test now fetches the live `/.well-known/agents.json` discovery manifest and probes EVERY advertised surface for its expected status + content-type (jq-parsed, so plugin-filtered surfaces are covered the moment they ship). Templated URLs (`{note-uid}`/`{post-id}`) and the content-json convention entry are skipped; `/wp-json/` surfaces count as alive on any JSON-speaking response (auth/method gates); everything else must answer 200 with the advertised type. An advertised-but-404 surface hard-fails — the exact "advertised resource vanished" rot class. Job names unchanged. NOTE: writing this check immediately surfaced one REAL live drift — the manifest still advertised `/provenance/verify/`, which 404s live (the docket ships at `/verify`) — fixed in this same release (see Fixed) so the check lands green.
- [inc/note-uid.php](inc/note-uid.php): `sn_theme_note_uid()` — the canonical lowercase+**trim** read of the plugin-owned `_sn_prov_uid` meta, replacing three inlined copies ([inc/content-json-document.php](inc/content-json-document.php), [inc/feed-json.php](inc/feed-json.php), [inc/feed-enrichment.php](inc/feed-enrichment.php)). Pinned (including the trim) in [tests/note-uid.php](tests/note-uid.php).

### Improvements

- [assets/css/critical.css](assets/css/critical.css) → [assets/css/article.css](assets/css/article.css): the inlined critical CSS's back half (~575 lines — the v9.0.0 view-transitions block, the pull-quote/compare-columns/steps patterns, post-closing, all `.single-post` article internals, frontmatter, typography detailing, the cmdk kill-switch) moved VERBATIM into a new `assets/css/article.css`, appended LAST to `sn_css_combine_sources()`. Cascade-safe by construction: those rules were the last stylesheet layer in the document (inline at `wp_head` 50, after every `<link>`), and last-in-the-combined preserves every source-order tie against base/layout/components/responsive/command-palette; the remaining inline front half shares no selector+property pair with the moved rules (verified per selector family). Zero FOUC: the combined sheet has been render-blocking since v10.21.6. Every page head sheds the article payload. Guarded against the v10.21.6 "63 green suites missed it" class in [tests/asset-combine.php](tests/asset-combine.php): the inlined file must NOT contain a known article selector, the source list must include article.css, and the built combined output must contain the moved selectors. Fallback enqueues ([inc/assets-frontend.php](inc/assets-frontend.php)), the editor list ([inc/setup.php](inc/setup.php)), and the stale custom.css-era header comments updated to match.
- [inc/notes-index-helpers.php](inc/notes-index-helpers.php): the 13 pure /notes-index helpers extracted VERBATIM from [inc/page-notes-render.php](inc/page-notes-render.php) (966 → ~700 lines, render path only). The documented load-bearing `SN_NOTES_RENDER_TEST` mid-file return — the ordering hack that already forced the v10.42.2 emergency extraction — is retired; the three fixtures that required the renderer for its helpers now require the module directly, and the new [tests/notes-index-helpers.php](tests/notes-index-helpers.php) proves the helpers load with no renderer and no sentinel.
- [inc/frontend-filters.php](inc/frontend-filters.php): all five anonymous hook closures are now named functions (`sn_skip_link`, `sn_spotify_embed_dark`, `sn_social_link_relative_url`, `sn_generator_meta_buffer_start`, `sn_strip_generator_meta`) behind the standard `SN_FRONTEND_FILTERS_TEST` wiring guard. One of the last behavior-bearing modules with no tests file gets one: [tests/frontend-filters.php](tests/frontend-filters.php) pins the output-buffer generator rewrite in/out AND the core/social-link path-relative shim in/out, plus the hook wiring. Behavior unchanged; the upstream-WP-issue TODO stays a TODO (filing it is an owner action).
- [docs/WORDPRESS-REFERENCE.md](docs/WORDPRESS-REFERENCE.md) §10.0: the cross-package contract table now lists all **8** live theme-listener hooks (it had drifted to 4) — adding `sn_seo_singular_description` (v10.13.0), `sn_og_image_url` (v10.39.0), `sn_cf_purge_urls_for_post` (v10.38.0), and `sn_gh_latest_theme_tag_error_result` (v10.43.0, the plugin's v9.54.0 deploy-card error seam) — and [tests/cross-package-listeners.php](tests/cross-package-listeners.php) extends from 4 to 8 pinned listener contracts (the error seam's pin proves the theme's own recorded failure reason wins over the plugin-resolved value, via now-stateful site-transient stubs).
- [functions.php](functions.php): the header module map regenerated against the actual require list (it had drifted to ~33 of 56 requires; the map now names every module in load order).
- [style.css](style.css): the frozen 3.9.x-era changelog block (~178 lines nothing parsed) deleted, leaving a one-line pointer at CHANGELOG.md.

### Fixed

- [inc/agents-manifest.php](inc/agents-manifest.php): the `provenance-verify` surface now advertises the live `/verify/` docket instead of the retired `/provenance/verify/` path, which 404s live — the exact advertised-404 the new smoke-test surface loop hard-fails on. URL pinned exactly in [tests/agents-manifest.php](tests/agents-manifest.php) so a dead verify path can never be re-advertised.
- The `.json` content twin now **trims** the Note uid like every other reader (it previously only lowercased — the drift the shared helper closes). Behavior change is the correct direction: a `_sn_prov_uid` stored with stray whitespace now republishes normalized, so the /verify docket's paste-a-URL match cannot be broken by invisible whitespace. Pinned in [tests/content-json-document.php](tests/content-json-document.php).

> **Why MINOR:** one new user-visible capability (the llms.txt/llms-full.txt Pillar essays section); everything else is refactoring, test scaffolding, CI coverage, and documentation-drift repair with no public API removed or renamed.

## [10.48.1] - 2026-07-22

### Fixed

- **The pillar eyebrow now actually renders on the pillar essays.** v10.48.0 hooked only `core/post-title`, but the essays render `templates/page-provenance.html`, which has no post-title block (their hero heading lives in content) — the feature was inert on exactly its target pages, caught in post-install live verification. The filter now also attaches to `core/post-content` (eyebrow prepended above the essay body) with a once-per-request flag so templates rendering both blocks emit a single eyebrow; a rejected candidate never burns the flag.

> **Why PATCH:** fixes a v10.48.0 feature that never fired on its target pages; no new capability, no API change.

## [10.48.0] - 2026-07-22

**Headline:** the pillar arc reaches the feeds and the essay Page itself — every feed item that is a verifiable Note now republishes its uid (JSON Feed `_signal_noise` extension + RSS `<sn:noteUid>`), so subscribers can reach the /verify docket without a second fetch, and a flagged essay's own title now carries its designation eyebrow ("№ 1.01 · Pillar Essay" → /provenance/). Rides along: three audited hardenings of the pillar descriptor derivation.

### New

- [inc/feed-json.php](inc/feed-json.php): JSON Feed items whose Note carries the plugin-owned `_sn_prov_uid` get an underscore-prefixed `_signal_noise` custom extension (JSON Feed 1.1 requires the prefix): `note_uid` (lowercased, the content-json-document precedent), `verify_url` (the Note's own /verify docket), `json_url` (the .json content twin, same derivation as the head link), and `reading_time_minutes` (plugin-owned `sn_get_reading_time()`, omitted when the plugin is absent or reports none). Items without a uid carry NO extension key at all. Pinned in [tests/feed-json.php](tests/feed-json.php).
- [inc/feed-enrichment.php](inc/feed-enrichment.php): RSS2 items mirror the uid as a `<sn:noteUid>` element under the `sn:` namespace the module already declares, escaped at the sink. No uid, no element. Pinned in [tests/feed-enrichment.php](tests/feed-enrichment.php).
- [inc/pillar-title-eyebrow.php](inc/pillar-title-eyebrow.php): new module — a `render_block_core/post-title` filter prepends an escaped designation eyebrow (`№ 1.01 · Pillar Essay`, linking to /provenance/, the block cards' vocabulary) ONLY on the reader-facing main-query singular Page that is flagged `_sn_pillar` = '1' AND carries a non-empty designation. Everywhere else — other blocks, secondary query loops, feeds, REST (covers the editor's ServerSideRender path), wp-admin, unflagged Pages — the input passes through byte-identical. Styling reuses `.sn-catalog-eyebrow` plus minimal `.sn-pillar-designation` link rules in [assets/css/components.css](assets/css/components.css) (part of the combined stylesheet, so it loads on every singular page; the mtime-keyed combine hash regenerates on its own). Pinned in [tests/pillar-title-eyebrow.php](tests/pillar-title-eyebrow.php).

### Improvements

- [inc/abilities-helpers.php](inc/abilities-helpers.php): `sn_theme_pillar_descriptors()` memoizes per request (the command palette derives on every front-end request, and the pillar block + the pillars Ability can run in the same request; each derivation costs 1-2 meta queries). Global-keyed, not a static — the `sn_css_combined_memo` precedent — so tests and future invalidation seams can clear it. Pinned in [tests/pillar-descriptors-dynamic.php](tests/pillar-descriptors-dynamic.php).

### Fixed

- **Security:** the JSON feed no longer includes password-protected posts. The custom feed rendered raw `post_content` through the `the_content` filter, bypassing `post_password_required()`, so a protected post's full body would have republished at `/feed/json` (the same trap class as the OG-card leak fixed plugin-side in v9.25.2). The query now excludes protected posts (`has_password => false`) and `sn_feed_json_build_item()` refuses them outright as defense in depth.

- [inc/abilities-helpers.php](inc/abilities-helpers.php): the hub-children fallback no longer fails open when the owner deliberately unflags the LAST pillar essay. The fallback is now gated to a NEVER-seeded meta system (the plugin's seed sentinel is invisible theme-side, so the closest observable signal gates it: any Page carrying the `_sn_pillar` key with ANY value means curation is live, and an empty flagged set stays empty). Behavior change: after unflagging every essay, the rail/palette/Ability now go honestly empty instead of resurrecting the derived hub-children list; a corpus with zero `_sn_pillar` rows keeps the v10.46.0 fallback byte-for-byte. Pinned in [tests/pillar-descriptors-dynamic.php](tests/pillar-descriptors-dynamic.php).
- [inc/abilities-helpers.php](inc/abilities-helpers.php): a flagged essay under a drafted/private parent Page no longer leaks the unpublished parent's slug into the block CTA and Ability payload — `get_page_uri()` walks ancestors regardless of status, so the hierarchical slug is only taken when every ancestor is published; otherwise the bare `post_name` stands. Pinned in [tests/pillar-descriptors-dynamic.php](tests/pillar-descriptors-dynamic.php).

> **Why MINOR:** two new user-visible capabilities (feed-level provenance for subscribers, the designation eyebrow on the essay Page itself); the descriptor hardenings are PATCH-grade ride-alongs and nothing public was removed or renamed.

## [10.47.1] - 2026-07-21

**Headline:** the pillar block stops looking broken (owner-reported same day): the card's fixed 48px number column clipped the new designations mid-digit ("No. 1.0..." behind the title, since the card is overflow:hidden — it was sized for the old two-character positional numbers), and the editor showed only a bare unstyled text line where the block should preview.

### Fixed

- [blocks/pillar-essays/style.css](blocks/pillar-essays/style.css): the number column is `auto` sized with `white-space: nowrap` on the designation, so "No. 1.01" (and any future "No. 10.02") renders whole on one line; pinned in [tests/pillar-essays-block.php](tests/pillar-essays-block.php).
- [blocks/editor.js](blocks/editor.js): the pillar block's editor view now previews the REAL server render via ServerSideRender (`wp-server-side-render` added to the editor script deps in [inc/blocks-register.php](inc/blocks-register.php)); the static text line survives only as a fallback when the module is absent. Dep pinned in [tests/blocks-registry.php](tests/blocks-registry.php).

> **Why PATCH:** two fixes to just-shipped v10.47.0 behavior; no new capability, no API change. Pairs with plugin v9.79.1 (the one-time designation seed).

## [10.47.0] - 2026-07-21

**Headline:** pillar selection moves to per-Page meta owned by the plugin (flag `_sn_pillar`, free-text `_sn_pillar_designation` for the owner's editorial numbering: over-detection=1.00, cheap-option=1.01, as-substrate=2.00), and the rail leaves the /notes index to become the owner-placeable `signal-noise/pillar-essays` dynamic block, ready to drop into the /provenance/ hub Page.

### New

- [blocks/pillar-essays/](blocks/pillar-essays/): new dynamic block rendering the pillar rail anywhere the owner places it. Self-contained styling (the rail CSS moved out of the notes-index inline stylesheet into the block's own style.css, with block-scoped copies of the section-header rules so they cannot fight the notes index's instances). The number line renders the editorial designation when set ("№ 1.01"), positional %02d only as fallback; the header count is a plain "N essays" (the old "03 / 03" positional counter retired: designations make it a false positional claim). Honest empty: no pillars, no output. Pinned in [tests/pillar-essays-block.php](tests/pillar-essays-block.php).
- [inc/abilities-helpers.php](inc/abilities-helpers.php): `sn_theme_pillar_descriptors()` now derives primarily from published Pages carrying the plugin-owned `_sn_pillar` = '1' meta (precedent: the `_sn_prov_uid` twin). Slug comes from the page URI so an essay outside /provenance/ works someday. Sort: designations parsing as major.minor first, compared numerically part by part ("1.10" after "1.09", a bare "2" as (2,0)); undesignated entries after, date ASC. Stable. Zero flagged Pages anywhere keeps the v10.46.0 hub-children fallback byte-for-byte (date ASC, 'verify' excluded), so the live site is identical until the owner flags Pages. Every descriptor now carries a `designation` key. Pinned in [tests/pillar-descriptors-dynamic.php](tests/pillar-descriptors-dynamic.php).
- [inc/abilities-content.php](inc/abilities-content.php): get-page-notes-pillars now returns the `designation` field per pillar (additive output plus schema).

### Changed

- [inc/editor-block-palette.php](inc/editor-block-palette.php): the curated post/page inserter now includes the theme's own blocks. Required for pillar-essays (the /provenance/ hub is a DB CMS page edited in the curated editor; without the seat the owner-placeable block is uninsertable) and closes a pre-existing enumeration gap for sidenote and pull-quote, which appear in patterns/ and were owed a seat by the "union of every block in patterns" mandate.

### Removed

- [inc/page-notes-render.php](inc/page-notes-render.php): the "Pillar Essays — Featured" rail, its derivation, and all rail CSS leave the /notes index (markup and styles now live in the block). The hero+pillars two-column desktop composition collapses to a full-width hero, and the search-state specificity hack that existed only to hide the pillar column simplifies away. Search, tag, and index labels, counts, and clear links are unchanged.

> **Why MINOR:** a new user-visible capability (an owner-placeable pillar rail block with editorial numbering) plus meta-driven pillar selection; nothing public was removed or renamed (the /notes rail's departure is the feature, and the descriptor/ability surfaces stay additive with a byte-identical fallback until Pages are flagged).

## [10.46.0] - 2026-07-21: Pillar essays derive from content — publishing one now surfaces it

**Headline:** the notes index's "Pillar Essays — Featured" rail and the pillar descriptors behind it were hardcoded to two essays with a literal "02 / 02" counter — the newly published third essay (/provenance/cheap-option/, the "1.01" essay) appeared nowhere (owner-caught live). Descriptors now derive from the published child Pages of the /provenance/ hub: content publishes → the rail grows.

### New

- [inc/abilities-helpers.php](inc/abilities-helpers.php): `sn_theme_pillar_descriptors()` queries the /provenance/ hub's published children, date ASC (the earliest essay keeps № 01, new essays append), excludes the `verify` how-to page, and maps each Page to {slug, title, dek (tag-stripped excerpt — an empty excerpt stays empty, no fabricated copy), last_path, date}. Feeds the notes-index rail, the command palette, and the get-page-notes-pillars ability from one source. Pinned in [tests/pillar-descriptors-dynamic.php](tests/pillar-descriptors-dynamic.php).
- [inc/page-notes-render.php](inc/page-notes-render.php): the rail is now a loop — dynamic numbering, dynamic "0N / 0N" counter, dek from the Page excerpt, per-essay reading time. The eyebrow deliberately drops the hardcoded month labels ("March 2026"/"May 2026"): the Page dates are CMS-flip artifacts, and printing them would fabricate essay dates.

### Changed

- [tests/abilities-registration.php](tests/abilities-registration.php) + [tests/abilities-integration.php](tests/abilities-integration.php): pillar-consumer tests now seed the hub + child Pages (the dynamic source) instead of relying on the retired hardcode.

> **Why MINOR:** a user-visible capability change — the pillar rail is content-driven; publishing an essay under /provenance/ is now sufficient to surface it.

## [10.45.0] - 2026-07-21: The .json twin republishes the Note uid — paste-a-URL verification becomes possible

**Headline:** a Note's public `.json` twin now carries `provenance.note_uid` and points `provenance.verify_url` at that Note's own /verify docket, giving the verifier (and any external tool) the key it needs to resolve a pasted Note URL into a credential; plus tap-target sizing fixes across the discography and note frontmatter.

### New

- [inc/content-json-document.php](inc/content-json-document.php) republishes the plugin-owned `_sn_prov_uid` meta as `provenance.note_uid` in every Note twin, and upgrades `provenance.verify_url` from the static how-to page to the per-note `/verify?note=<uid>` docket when a uid exists. This was the missing half of the verifier's paste-a-URL affordance: the plugin's resolver probes `provenance.note_uid` first, and no deployed twin carried it under any name — so pasting a Note URL into /verify could never resolve (found in this session's live sweep; the plugin ships the companion resolver fix in signal-and-noise-tools v9.75.0). Uid-less Notes keep the how-to link and never fabricate a uid. Pinned in [tests/content-json-document.php](tests/content-json-document.php).

### Fixed

- Tap targets: the discography's "Credits ↗" links and "Liner notes" disclosure toggles ([assets/css/components.css](assets/css/components.css)) and the note-frontmatter tag links + pillar chip ([assets/css/critical.css](assets/css/critical.css)) measured 16–22px tall — under the 24px standalone-target minimum. All now reach ≥24px via padding cancelled by equal negative margins (pure hit-area growth, zero layout shift; the pillar chip's visible box grows ~4px because its border IS the hit box).

> **Why MINOR:** a new machine-readable capability in the public twin schema (additive field + smarter verify_url); the tap-target work alone would be PATCH.

## [10.44.5] - 2026-07-21: The scroll-milestone contract gets the same pins as engaged time

**Headline:** the beacon's `sc` scroll-milestone behavior is now test-pinned in the same detail as the v10.44.4 `tm` delta contract, because the plugin's durable `scroll_sum` load-bears on it.

### Improvements

- [tests/beacon.php](tests/beacon.php) pins the full `sc` contract the plugin's durable analytics re-based on (signal-and-noise-tools v9.64.0/v9.66.0: `scroll_sum` = 25 × scroll_events): the exact `[25, 50, 75, 100]` milestone array, cumulative `pct >= m` firing, the `sent[m]` at-most-once-per-view guard, the `{e,u,d}` payload shape, short-page `pct=100` behavior, listener detach after 100, and the sent-map reset firing only on a bfcache restore. Previously the suite asserted only that the literal `'sc'` appeared in the source — an edit to milestone spacing or the once-guard would have passed the tests while silently corrupting the plugin's durable table. Falsified: mutating the milestone array makes the suite fail.

> **Why PATCH:** test-only hardening of existing shipped behavior; no runtime change.

## [Repository operations] - 2026-07-20: Hard CI gates and lower Actions spend

**No theme package change.** PHPStan is now a hard failure instead of an
advisory result. The live-site smoke schedule moves from every 15 minutes to
hourly, and scheduled probes skip the unrelated PHP checkout/setup/lint job.
This reduces scheduled job executions from roughly 192 per day to 24 while
preserving push-time lint and smoke checks. GitHub secret scanning, push
protection, and Dependabot security updates are enabled; the existing main
branch deletion and non-fast-forward protections remain active.

## [10.44.4] - 2026-07-18: Engaged-time beacon re-arms after the first tab switch — per-flush deltas

### Fixed

The `tm` engaged-time event in [assets/js/sn-beacon.js](assets/js/sn-beacon.js) fired ONCE per view, at the first `visibilitychange→hidden`: the `flushed` flag never reset except on a bfcache restore. Engaged time kept accumulating after the visitor returned to the tab — but it was never sent. Every tab-switch-heavy visit permanently under-reported engaged time.

**Old timeline** (read 60s → switch away → return, read 90s more → close): one `tm` with `ms≈60000` at the first hide; the further 90s tracked, never sent. **New timeline**: `tm ms≈60000` at the first hide, then `tm ms≈90000` on close — the rollup sums them to the true ~150s.

Fix: **delta semantics.** On each `visibilitychange→hidden` or `pagehide`, flush sends the engaged ms accrued *since the last successful flush*, resets the accumulator, and re-arms (`flushed = false`) when the page becomes visible again. Deltas — not accumulated totals — because the plugin rollup **SUMS** `tm.ms` per view; sending totals would double-count every episode before the last.

Unchanged: the DNT/GPC gate, the transport (sendBeacon → fetch keepalive), and the payload shape `{e:'tm',u,ms}` — `ms` just becomes a per-flush delta, which is already what the rollup arithmetic assumes.

### Changed

- A zero/negative delta is never sent. A background-opened tab that is closed unviewed no longer emits a `tm ms:0` (previously it did).
- **Metric-semantics note:** `time_events` now counts flush episodes, not views (a view can flush several times), so any per-event mean (`time_avg` = sum/`time_events`) shifts from "engaged ms per view-exit" to "engaged ms per visibility episode" — smaller per-event values on tab-switch-heavy visits, while the SUM becomes complete and correct for the first time.
- [tests/beacon.php](tests/beacon.php): 8 new content-contract assertions pin the delta flow — re-arm on visible, per-flush delta, zero/negative guard, reset-before-send ordering, the one-send-per-hidden-episode guard, and the bfcache full reset (76 total, all green; full sweep 78 suites / 1758 assertions green).

## [10.44.3] - 2026-07-16: Not the jail I thought it was — no scp, rsync over the ssh shell

**No theme code change.** Byte-identical to 10.44.2 apart from this version header.

v10.44.2 moved staging out of `$HOME` and into `/tmp`, on the theory that home wasn't writable. The next dispatch disproved it:

```
Remote stage: /tmp/…-stage.uJf12C          ← ssh CREATED it, successfully
scp: dest open "/tmp/…-stage.uJf12C/payload.tar.gz": No such file or directory
```

The `ssh` session created that directory. The `scp` a moment later couldn't see it.

**`scp` runs over the SFTP subsystem, which on this host is jailed differently from the interactive SSH shell.** One cause explains both earlier failures: `~` rejected as "Permission denied" *and* a `/tmp` path the shell had just created reported as missing. **"Home isn't writable" was a story that fit the evidence and was wrong** — the real invariant is that anything `scp` touches is a different world from anything `ssh` touches.

**So: no `scp`, and no remote staging at all.** `rsync -e ssh` runs over the SSH *shell*, sidesteps the sftp jail entirely, and needs no staging area — extract on the runner, sync straight into the live directory. The result is **simpler than what it replaces**: one fewer moving part, no temp dir to create, guard, or clean up.

**Four dispatches, three real bugs, zero impact on the live site.** Every failure landed before a single file was written.

## [10.44.2] - 2026-07-16: The SSH user's home is not writable — and our own deleted comment said so

**No theme code change.** Byte-identical to 10.44.1 apart from this version header.

With v10.44.1's guard fix in, the next dispatch got further — past the payload build, past the four guards, past SSH setup — and died shipping the payload:

```
scp: dest open "…-payload.tar.gz": Permission denied
```

**The SSH user's home isn't writable.** The workflow this rewrite *replaced* said so, in a comment removed along with the mechanism:

> *"The deploy runs as Cloudways' restricted 'additional SSH user'. Their `~/.ssh` is root-owned and read-only, so the GitHub deploy key lives in the user-writable `~/.openssh/` directory (Cloudways convention)."*

The old code **knew** home was hostile and worked around it explicitly. The rewrite `scp`'d into `~` **and** then `mktemp -d ~/…` on top — two writes to a directory that rejects them. The code being replaced was itself the documentation, and it was treated as obsolete rather than as evidence.

Both repos now stage in a **remote `mktemp -d /tmp/…`** — created `0700` and owned by us, so it's writable *and* safe from a symlink race on a shared host. Nothing touches `$HOME`.

**Three dispatches, two real bugs, zero impact on the live site.** Every failure landed before a single file was written. That is the whole argument for exercising a dormant fallback on a quiet evening instead of during the next outage — which is exactly how tonight started.

## [10.44.1] - 2026-07-16: The first real dispatch found a flaky guard

**No theme code change.** The payload is byte-identical to 10.44.0 apart from this version header. v10.44.0's rewritten `deploy.yml` was dispatched for the first time and **died at its own guard**:

```
tar: stdout: write error
##[error]payload has no style.css
```

The payload **contained** `style.css`. The guard was:

```bash
tar -tzf payload.tar.gz | grep -q '^signal-and-noise/style.css$' || { echo "::error::…"; exit 1; }
```

Under `set -o pipefail`, `grep -q` exits on its **first match**, `tar` takes SIGPIPE (`tar: stdout: write error`), and pipefail propagates **tar's** failure — even though grep **succeeded**. It's a **race**: it depends on whether `tar` finished writing before `grep` quit. That's exactly why it passed local verification on a small payload and failed on the runner.

**A guard that fires at random is worse than no guard** — it reads as "CI being weird" and gets ignored. Both repos' workflows had the pattern; both now capture the listing once and match against the variable, with no pipe from the producer and no race.

Reproduced deterministically by forcing the stream large: `seq 1 500000 | grep -q '^1$'` under pipefail **fails despite the match existing**; `L=$(seq 1 500000); grep -q '^1$' <<<"$L"` passes.

**Why a version bump for a workflow fix:** a tag-guarded deploy workflow can only be exercised by a tag cut *after* it lands — `workflow_dispatch --ref X` runs the workflow file as it exists at X, and the guard rejects non-tag refs. v10.44.0 carries the broken guard, so proving the fix needs this tag. Mirrors the plugin's v9.54.2.

**This is what testing a dormant fallback buys.** The guards were verified locally and passed. Only a real dispatch surfaced the race.

## [10.44.0] - 2026-07-16: The emergency fallback can finally ship a tag — and the theme install halves

**Headline:** `deploy.yml`'s own header told you to run `gh workflow run deploy.yml --ref vX.Y.Z`. **That instruction was a lie**, and the lie was the bug.

The workflow POSTed to Cloudways' `/git/pull` API with a **hardcoded `branch_name=main`**. It could not deploy a tag. Ever. `--ref` only selected which *version of the workflow file* ran — the deploy target was always `main`.

So `--ref v10.42.3` would have shown **"v10.42.3"** in the Dashboard's Recent deploys row while pulling `main`: a precise, confident, wrong label. On 2026-07-16 it shipped `main` and was safe only by luck — main happened to equal the tag commit. **The honest display, the one that read `main` and looked broken, was the only thing telling the truth.**

**Now it builds the payload on the runner with `git archive`** (byte-identical to what WP's native updater installs from the tag archive) and rsyncs it over the SSH channel this workflow already used. That makes `--ref` real:

- Deploys the **exact tag dispatched**, and **asserts** the landed `style.css` `Version:` matches it. A deploy that silently lands the wrong code is worse than one that fails — it looks successful. The old `git/pull` could never have made that claim.
- A **tag guard** rejects a branch ref. The plugin's guard caught a bare `gh workflow run deploy.yml` (which defaults to `main`) on 2026-07-16; this workflow had none, accepted it, and shipped a branch to production.
- **No Cloudways API dependency** — the `CLOUDWAYS_*` secrets are now unused here.
- **No server → GitHub dependency**: the GitHub REST outage that made this fallback necessary would not have blocked it.

**Deliberately different from the plugin's deploy:** this one rsyncs with `--exclude='.git/'`. The plugin *deletes* `.git` on purpose (its footprint janitor targets it, and a restored one would just be deleted again). Here, `.git` may be Cloudways' own git integration — the mechanism this replaces. Wiping it would burn the old fallback before the new one has ever run against the live server. Harmless if absent; a second fallback if present.

**The theme install halves.** [.gitattributes](.gitattributes) now carries the plugin's proven `export-ignore` list (first live at its v9.42.0):

| | Before | After |
|---|---|---|
| Payload | 812 KB | **396 KB** |
| Files | 266 | **157** |

`tests/` alone was **79 files / ~591 KB**, shipped to production on every single theme update, for no reason — plus `docs/` at ~170 KB. This affects the WP updater too, not just the deploy: the next theme update installs less than half of what the last one did.

**Safety:** verified by **tokenizing** every runtime PHP file — comments stripped — that no runtime code references any export-ignored path. `manifest ∩ runtime = ∅`. That check mattered: a plain grep reported hits in seven files, and **every single one was a comment**. Never widen the list without re-running it.

**Not yet exercised against the live server.** The first dispatch is the real test. Failure is safe and loud: nothing lands, and the version assertion refuses to lie.

## [10.43.0] - 2026-07-16: The theme's card learns to say why

**Headline:** During the 2026-07-16 GitHub outage, the two S&N version cards sat six inches apart on the same dashboard. The **plugin's** said:

> GitHub returned an unexpected HTTP 503

The **theme's** said nothing at all — a bare red **"unknown"**. Same outage, same second, same screen: one surface could explain itself and the other could not. The plugin's v9.54.0 opened the seam for exactly this (`sn_gh_latest_theme_tag_error_result`) and then only implemented its own side. This closes it.

**What GitHub did:** at 22:51 UTC, [Degraded REST API Availability](https://www.githubstatus.com) — *"approximately 35% of REST API requests to fail."* The owner noticed four minutes later. Not the token, not a timeout, not this theme.

**What was ours:** the theme cached **every** failure for `HOUR_IN_SECONDS`, so a one-second blip cost sixty minutes — and the next hourly poll had another ~35% chance of re-arming it. The tell sat on the same dashboard the whole time: "Recent deploys" rode out the entire incident because *its* fetch caches failures for **five minutes** and self-heals. Same host, same token, same timeout — only the failure TTL differed.

**Both halves now match the plugin (v9.54.0 + v9.54.1):**

| Failure | Reads as | Cached | Retry |
|---|---|---|---|
| 5xx, 429, network/timeout | **transient** — the far end is unwell; it recovers | **5 min** | **once** |
| 401 | *"GitHub rejected the credential (401) — SNT_GITHUB_TOKEN…"* | 1 hour | never |
| 404 | repo renamed/deleted/made private | 1 hour | never |
| 200 + unparseable body | we reached *something* that wasn't the API | 5 min | once |
| 200 + no `vX.Y.Z` tags | GitHub answered fine; nothing is tagged | 1 hour | never |

A `WP_Error` carries the real driver message (`cURL error 28: Operation timed out after 8001 ms`) rather than a generic string — the number in it *is* the diagnosis. Reasons are redacted against token-shaped strings before reaching a screen, and **success clears the reason**, or a stale caption would outlive the fix and send someone hunting for a problem that already resolved itself.

Against ~35% independent failures, the single retry recovers roughly two-thirds of the polls that would otherwise blind the card.

**Tests** ([tests/updater-failure-modes.php](tests/updater-failure-modes.php), 24 asserts) were verified RED first and **mutation-checked twice**: remove the filter listener → the card-reads-it assertion goes red; collapse the TTLs back to durable-only → the 60-minute bug's assertions go red. The `wp_remote_get` stub models the actual incident (503, then 200 on the second call), because a stub returning one fixed response cannot express *"flaky"* — a retry test written against it would pass without a retry ever happening. Three sibling suites needed `MINUTE_IN_SECONDS` / `delete_site_transient()` / `__()` stubs, caught by the full 78-suite sweep rather than the feature's own tests.

## [10.42.3] - 2026-07-16: The theme stops borrowing the plugin's immune system

**Headline:** Desktop Mode's AI Copilot auto-enrols **every** read-only ability on the site, with no opt-out — and this theme's ability schemas would 400 it dead. They never did, but only because the companion plugin happened to be active and was normalizing the whole tool list on the theme's behalf. Deactivate the plugin, or run this theme without it, and Ask AI dies site-wide from the theme's own schemas.

That was an undeclared cross-dependency, and nothing recorded it. A theme cannot rely on a plugin being active to stay compatible with a *third* plugin. The theme now owns its conformance: [inc/desktop-mode-copilot-schema.php](inc/desktop-mode-copilot-schema.php) is deliberately self-contained and does **not** call the plugin's normalizer. Both running is harmless — normalizing is idempotent, so the second pass is a no-op.

**The three shapes, all of them load-bearing here:**

| Shape | Where it lives in this theme | Provider error |
|---|---|---|
| `'type' => array( 'object', 'null' )` | the GET/null run-path | `type: Input should be 'object'` |
| top-level `anyOf` | `get-active-template-structure` — "post_id **or** slug" | `does not support oneOf, allOf, or anyOf at the top level` |
| `'properties' => array()` | no-args abilities (PHP has no empty-map literal) | `properties: Input should be an object` |

**Nothing is weakened.** This projects an ability into a *tool* schema; the ability is untouched. `WP_Ability::execute()` still validates against the real schema and `permission_callback` still gates it, so a stripped `anyOf` is still enforced server-side — the model is told in prose (the description) rather than in schema. Only top-level combinators are removed; a nested `anyOf` is a real constraint the provider accepts and is preserved.

Registered at `PHP_INT_MAX` so nothing can inject a tool downstream of it, and with no "already looks fine, skip it" guard — that guard is what turned one bug into three releases in the companion plugin. The list of unsupported constructs belongs to the provider, not to us.

Upstream: [WordPress/desktop-mode#362](https://github.com/WordPress/desktop-mode/issues/362) (still open — the converter passes `input_schema` through raw).

**Tests** ([tests/desktop-mode-copilot-schema.php](tests/desktop-mode-copilot-schema.php)) never define the plugin's normalizer — standing alone *is* the property under test. They also assert `functions.php` actually loads the module, and that assertion was mutation-checked: remove the `require_once` and the suite goes red. Without it, every other test would pass while the module sat dead in production.

## [10.42.2] - 2026-07-16: Reading-time helper reaches REST/MCP

**Headline:** `get-reading-time-for-slug` errored over the plugin's MCP server (`sn_notes_reading_time_for_slug() unavailable — theme module not loaded`), and `get-page-notes-pillars` silently returned the "5 min" fallback instead of real reading times. Both Abilities run over REST, but the helper they call lived in [inc/page-notes-render.php](inc/page-notes-render.php) — a full-page renderer that runs top-level output at include time and is therefore loaded ONLY on the `/notes` `template_include`, never in a REST request. The helper is now extracted to a dependency-free, always-required file ([inc/notes-reading-time.php](inc/notes-reading-time.php)); the renderer keeps calling the same function name. Found live via the MCP server.

> **Why PATCH:** bug fix — an Ability that errored now works, and a second one returns real data instead of a fallback. No new capability, no behavior change on the `/notes` route.

### Fixed
- The reading-time helper is available in every request context, not just the `/notes` template route. New [tests/notes-reading-time.php](tests/notes-reading-time.php) (6 asserts: no render side effects on include, the shortcode wrap, esc_attr on the slug, the "5 min" fallback); the renderer's own tests stay green (no redeclare). Full sweep 76 files / 1,708 asserts / 0 failed.

## [10.42.1] - 2026-07-15: Design-token abilities read real tokens — and refuse to fabricate

**Headline:** The `get-design-tokens` MCP/ability read was **born broken**: `wp_get_global_settings()` returns presets keyed by ORIGIN (`default`/`theme`/`custom`), and the reader iterated those origin buckets as if they were token entries — so colors resolved to nothing, and fontFamilies/fontSizes/spacingSizes returned arrays of buckets (the live tell: exactly 1/2/2 "entries", all fields empty — the origin counts, not tokens). Downstream, `get-design-system-summary` faithfully formatted that hollow shell into a plausible empty document — a silent fabrication, since nothing ever threw. Fixed in layered order in [inc/abilities-diagnostics.php](inc/abilities-diagnostics.php): **first** the summary gains an anti-fabrication gate (hollow tokens → `WP_Error('design_tokens_empty')` in all three formats — surface the failure, never invent a valid-looking answer), **then** the reader unwraps origins with core precedence (default → theme → custom, later wins by slug) into flat token entries, erroring itself when a read is genuinely hollow so the class dies at the source too. `spacingScale` is a resolved value, not a preset list — passthrough pinned unchanged. Cross-consumer `ai-validate-brand-alignment` verified safe by reading (its existing guards degrade gracefully).

> **Why PATCH:** bug fix, non-breaking — the abilities now return what their contracts always promised.

### Fixed
- Origin-unwrap + hollow-read errors + the summary's anti-fabrication gate. New [tests/design-tokens.php](tests/design-tokens.php) (**26 asserts**: real origin-keyed fixtures with a 3-way precedence pin and bucket-count-decoy entry counts, hollow → WP_Error in every format, spacingScale passthrough); full theme sweep 75 files / **1,702 assertions / 0 failed**; RED-verified in T1→T2 order; adversarial probe review SHIP with zero findings.

## [10.42.0] - 2026-07-11: UTM campaign attribution (named params only)

**Headline:** The first-party beacon now captures campaign attribution. On the **first pageview only**, it reads the five named `utm_*` params (`utm_source`, `utm_medium`, `utm_campaign`, `utm_term`, `utm_content`) from the query string and attaches them to the pageview payload as a compact `utm` object. This is the deliberate, disclosed exception to the beacon's "the query string never leaves the browser" stance: it sends **only those five named marketing tags** — the ones the site owner puts on their own campaign links — and never the raw query string. The pageview path stays `location.pathname` as before. A plain in-memory flag remembers the capture fired, so no web storage is touched (the storageless invariant holds) and a Back-button/bfcache restore never re-attributes the same landing. This is the theme-side half of the cookieless campaign-attribution pipeline; the analytics worker packs these into the row's last free field and the companion plugin surfaces a Source/Campaign breakdown.

> **Why MINOR:** a new user-visible capability (campaign attribution the beacon never captured before), additive. No public function, template, or ability removed or renamed, no settings-schema change, no WP-floor raise. Pageviews with no UTM params are byte-for-byte unchanged on the wire; the payload only grows when a campaign tag is actually present.

### New
- [assets/js/sn-beacon.js](assets/js/sn-beacon.js): `readUtm()` parses the five named params via `URLSearchParams`, trims and length-clamps (128) each value, and returns a compact `{ s, m, c, t, o }` object (or nothing when none are present). `pageview()` attaches it on the first call, gated by an in-memory `utmSent` flag — no `sessionStorage`/`localStorage`.

### Privacy
Consistent with the cookieless, storageless beacon. Only the five explicitly-named `utm_*` params are transmitted — never arbitrary query keys, never the raw query string. These are disclosed in the site privacy policy's analytics section.

### Tests
- [tests/beacon.php](tests/beacon.php): eight assertions covering the `URLSearchParams` read, all five named params, the `pv.utm` attach, the once flag, first-pageview-only capture, the no-web-storage (storageless) guarantee, the pathname-only pv path (no raw-query leak), and the value length clamp. Beacon suite 68/68.

## [10.41.0] - 2026-07-11: Privacy policy link in the footer meta-nav

**Headline:** The footer meta-nav gains a fourth icon — a shield linking to `/privacy-policy` — joining Now, Accessibility, and Colophon on the right side of the footer (owner request). It reuses the exact icon-link pattern already there: a mono stroke glyph drawn in `currentColor` (quiet rust, blood on hover), a middot separator, a `>=28px` WCAG target, an `aria-label` + `title` for its accessible name, and a decorative `aria-hidden` SVG. No CSS change was needed — `.sn-footer__meta-nav` styling is generic and the new link inherits every token for free.

> **Why MINOR:** a new user-visible capability (a footer link that did not exist), additive. No public function, REST route, or ability removed or renamed, no settings-schema change, no WP-floor raise. The `/privacy-policy` href is a raw `<a>` (not a `core/social-link`), so it resolves natively against the current origin like the sibling Now/Accessibility/Colophon links.

### New
- [parts/footer.html](parts/footer.html): a Privacy policy link (shield glyph → `/privacy-policy`) appended to the `sn-footer__meta-nav`, with a middot separator, `aria-label="Privacy policy"`, `title="Privacy"`, and a decorative shield SVG that draws with `currentColor` so it inherits the rust base and blood hover.

### Tests
- [tests/footer-meta-nav.php](tests/footer-meta-nav.php): assertions raised from three links to four (four icons, three separators, four accessible names/tooltips/decorative SVGs) plus a new `href="/privacy-policy"` check. Full theme sweep 74 suites / 0 failed.

## [10.40.0] - 2026-07-11: File-download tracking (extension only)

**Headline:** The first-party beacon now records file downloads. Its delegated click listener already classified feed subscriptions and cross-host outbound clicks; it now also recognises links to downloadable files — any link whose path ends in a known download extension (`.pdf`, `.zip`, office/media/archive formats, …) or any link carrying an explicit `download` attribute — and fires a `download` event carrying **only the extension** (e.g. `{ ext: 'pdf' }`). Never the filename, path, or query, exactly consistent with the host-only stance the outbound event already takes. Download is the more specific classification, so a cross-host file link (`example.com/report.pdf`) is counted as a download, not an outbound. This flows through the existing custom-event (`ce`) pipeline the worker and companion plugin already handle, so no worker or plugin change is required to receive it.

> **Why MINOR:** a new user-visible capability (a `download` conversion the beacon never emitted before), additive. No public function, template, or ability removed or renamed, no settings-schema change, no WP-floor raise. The listener path is unchanged for every non-download link; downloads are simply classified ahead of outbound. With the analytics worker/plugin absent the event is a no-op, as with every other beacon event.

### New
- [assets/js/sn-beacon.js](assets/js/sn-beacon.js): a `DOWNLOAD_EXT` allow-list and a download branch in the click listener. A same- or cross-host link to a matching file (or one with a `download` attribute) fires `cfg.event('download', { ext })` — extension only — and returns before the outbound check, so downloads and outbounds never double-count and no filename/path/query ever leaves the browser.

### Tests
- [tests/beacon.php](tests/beacon.php): five assertions covering the download event, the extension allow-list, the extension-only prop shape (no filename/path leak), the explicit `download`-attribute path, and that download classification precedes outbound. Beacon suite 60/60.

## [10.39.1] - 2026-07-11: Content-JSON excludes the static front page's twin

**Headline:** Closes a consistency gap the family-close CMA audit flagged (INFO-1). The `.json` content-twin resolver served `/<front-page-slug>.json` for a static front page, even though the same module deliberately never advertises it (the `<head>` link skips `is_front_page()`) and never purges it (a static front page's twin derives from the site root, a malformed `host.json`). The resolver now excludes the front page too, so advertise, purge, and serve agree on one rule. No data was ever exposed: the twin only served the already-public home page's own content. This stops a front-page edit from leaving a stale, unpurgeable twin in the edge cache, and makes the three call sites consistent.

> **Why PATCH:** a consistency/hardening fix to one resolver, no new capability. No public function, REST route, or ability removed or renamed, no settings-schema change, no WP-floor raise. It reuses the exact site-root predicate already in `sn_content_json_purge_url()`.

### Fixed
- [inc/content-json.php](inc/content-json.php): `sn_content_json_resolve()` returns `0` for the static front page (permalink equal to the site root), matching the front-page skip already in `sn_content_json_head_link()` and `sn_content_json_purge_url()`. A `/<front-slug>.json` request now 404s like any other non-twin path instead of serving an unadvertised, unpurgeable twin.

### Tests
- [tests/content-json.php](tests/content-json.php): new assertion that the static front page resolves to `0` (16 assertions total). Full theme sweep 74 suites / 0 failed; PHPCS falsified-clean; PHPStan clean.

## [10.39.0] - 2026-07-11: Bespoke share card for the /notes index

**Headline:** The `/notes` index now has its own 1200x630 social share card instead of falling back to the small square site logo. Sharing `juanlentino.com/notes` previews a card in the site's own design language (red tick, `JUANLENTINO.COM` eyebrow, a big Bebas `NOTES`, the notes dek in DM Mono, and a blood-red tagline) rather than a generic logo. This is the theme-side follow-up to plugin v9.25.4, which stopped non-singular views from borrowing a single Note's card and correctly fell them through to the site default; this gives the index a real card of its own. Single Notes, `/notes` tag archives, search results, and every other view are unchanged.

> **Why MINOR:** a new user-visible capability (a bespoke share image for a route that previously had only the generic default), additive. No public function, REST route, or ability removed or renamed, no settings-schema change, no WP-floor raise. It plugs into the companion plugin's existing `sn_og_image_url` filter (present since plugin v9.3.0) at priority 20, so on the notes index the theme card wins and every other view passes through untouched; with the plugin absent the filter simply never fires.

### New
- `assets/images/og-notes-card.png`: a committed 1200x630 share card for the `/notes` index, baked in the plugin's card design language with the theme's own Bebas Neue + DM Mono OG fonts (`assets/fonts/og/`), so it is pixel-consistent with every per-post card. Copy is drawn from the site's notes description, split into a DM Mono dek and a red evergreen footer (no reading-time or post count that would go stale in a static asset).
- `inc/notes-og-card.php`: listens on the plugin's `sn_og_image_url` filter at priority 20 and returns the bespoke card only when `sn_notes_is_index_request()` is true (the same matcher that owns the /notes render). Plugin-guarded via `function_exists`; a no-op when the plugin or filter is absent. The plugin declares the correct 1200x630 dimensions and `twitter:card=summary_large_image` on its own, so no dimensions filter is needed.
- `tests/notes-og-card.php` (7 assertions): the index override, non-index passthrough, and that the committed asset is a valid 1200x630 PNG.

## [10.38.4] - 2026-07-11: Footer social icons keep their look (no list dots)

**Headline:** After v10.38.3 hid the exposed labels, the footer social links still showed default `<ul>` disc bullets ("big dots") between the icons and rendered the glyphs black instead of the muted rust. Same cause one layer out: the base `wp-block-social-links` styling that removes the list markers and colours the icons is core CSS the theme leaned on. The theme now owns its footer social-icon appearance, so the dots and black glyphs don't appear when core's copy is absent. Reproduced live and confirmed fixed.

> **Why PATCH:** same resilience class as v10.38.1/.2/.3, additive CSS scoped to `.sn-footer`. It removes the `<li>` disc markers and sets the icon fill to the footer rust colour, in the always-inlined `critical.css`; a no-op duplicate when core's social-links styling is present. No public API/schema/floor change.

### Fixed
- `assets/css/critical.css` — the theme now owns the footer social-icon essentials under `.sn-footer .wp-block-social-links`: `list-style: none` on the list + items (kills the default disc bullets), `display: flex` on each `.wp-social-link` (the `<li>` is no longer a list-item), and an explicit icon `fill` (`var(--wp--preset--color--rust, #666666)`) so the glyphs stay muted rather than defaulting to black ([assets/css/critical.css](assets/css/critical.css)).
- `tests/footer-social-critical.php` — new standalone test (4 assertions) guarding the marker removal, footer scoping, and icon fill ([tests/footer-social-critical.php](tests/footer-social-critical.php)).

## [10.38.3] - 2026-07-11: Theme owns .screen-reader-text (no exposed labels)

**Headline:** Fixes visually-hidden labels showing as real text: the footer social links rendered as "Spotify / LinkedIn / Instagram / X / Subscribe via RSS" text instead of icons-only, and the search field's hidden `<label>` appeared before the input. Root cause is the same architectural flaw as the nav fixes: the `.screen-reader-text` accessibility utility that hides those labels ships **only** in WordPress core's inline block styles (`wp-block-library`, the skip-link block), and the theme relied on it without owning it. When those core inline styles don't take effect in a given environment (a CSS optimizer that strips block styles, or the edge serving a stripped document), every screen-reader-only label becomes visible. Reproduced live by removing core's inline `.screen-reader-text` and confirmed fixed by a theme-owned copy.

> **Why PATCH:** same resilience class as v10.38.1/.2, additive CSS. The rule is the standard WordPress `.screen-reader-text` (visually-hidden + `:focus` reveal), placed in the always-inlined `critical.css` so it is guaranteed present; it is a no-op duplicate when core's copy is applied. No public API/schema/floor change.

### Fixed
- `assets/css/critical.css` — the theme now owns the `.screen-reader-text` primitive (visually-hidden hide + `:focus` reveal for keyboard parity), so visually-hidden labels stay hidden even when WordPress core's inline `.screen-reader-text` styles are absent. This keeps the footer social links icon-only and the search `<label>` hidden ([assets/css/critical.css](assets/css/critical.css)).
- `tests/screen-reader-text-critical.php` — new standalone test (6 assertions) guarding the hide rule, its clip/1x1px technique, the `:focus` reveal, and that it targets the bare `.screen-reader-text` (not only the skip link) ([tests/screen-reader-text-critical.php](tests/screen-reader-text-critical.php)).

## [10.38.2] - 2026-07-11: Nav fallback keeps the header ink (no red flash)

**Headline:** Completes the v10.38.1 fallback. When core's navigation stylesheet is dropped, the menu was rendering in the theme's red accent colour instead of black, because the theme relied on core's `color: inherit` rule to tint the links with the header ink — and that rule lives in the same dropped file. Reproduced live: with core's nav stylesheet disabled, the desktop menu fell back to the theme.json global link colour (blood/red). The theme now owns the nav link colour, so the menu stays black through a drop and the intermittent edge failure is invisible.

> **Why PATCH:** finishes the same resilience fix as v10.38.1, additive CSS only. `color: inherit` on the theme's existing `.wp-block-navigation a` rule (specificity 0,1,1) beats the theme.json global link rule (0,0,1) and is identical to core's behaviour when core's stylesheet IS present, so it is a no-op in the normal case. No public API/schema/floor change.

### Fixed
- `assets/css/critical.css` and `assets/css/layout.css` — added `color: inherit` to `.wp-block-navigation a` so the desktop nav links inherit the header ink even when core's navigation stylesheet (which supplies the same `color: inherit`) is absent. Previously they fell back to the red global link colour ([assets/css/critical.css](assets/css/critical.css), [assets/css/layout.css](assets/css/layout.css)).
- `tests/nav-responsive-fallback.php` — added an assertion (now 10) that the nav link colour fallback ships ([tests/nav-responsive-fallback.php](tests/nav-responsive-fallback.php)).

## [10.38.1] - 2026-07-11: Navigation survives a dropped core stylesheet

**Headline:** The header menu no longer breaks when WordPress core's navigation-block stylesheet goes missing. That one separate file (`wp-includes/blocks/navigation/style.min.css`) is all that hid the `☰`/`✕` toggles on desktop, kept the closed menu dialog hidden, and stripped the list bullets — the theme owned none of it. When a CSS optimizer ("combine"/"remove unused CSS"), a CSP rule, or a network hiccup dropped that file, the header degraded into both toggles showing at every width plus a raw bulleted menu dumped over the page (reproduced live on the 404 page by disabling that one stylesheet — it matched the reported screenshot exactly). The always-inlined `critical.css` now carries the essential closed-state responsive rules itself, so the nav stays correct even without core's file.

> **Why PATCH:** a resilience/bug fix, no new capability. Pure additive CSS in the always-inlined critical layer; no public function/REST route/ability removed or renamed, no settings-schema change, no WP-floor raise. The rules mirror core's exact selectors and values, so they are idempotent when core's stylesheet IS present (the normal case) and only take effect when it is absent.

### Fixed
- `assets/css/critical.css` — added a defensive fallback for the navigation block's **closed-state** responsive collapse (the open-overlay counterpart was already made critical-path in v8.5.7). Mirrors core's essential visibility logic: hide the open (`☰`) and close (`✕`) toggles on desktop (the `min-width: 600px` breakpoint), keep the closed dialog hidden until opened, strip `<ul>` list chrome, and restore the desktop flex-row layout + item-gap chain. Reused core's exact selectors/specificity so it beats `layout.css`'s single-class `inline-flex` rule when core is gone, and is a no-op duplicate when core is present ([assets/css/critical.css](assets/css/critical.css)).
- `tests/nav-responsive-fallback.php` — new standalone structural test (9 assertions) guarding that the closed-state fallback ships and is not merely the pre-existing `is-menu-open` overlay ([tests/nav-responsive-fallback.php](tests/nav-responsive-fallback.php)).

## [10.38.0] - 2026-07-11: Content-as-data — every Note and Page as JSON

**Headline:** Append `.json` to any Note or Page URL (`/notes/some-note.json`, `/about.json`) to get a clean JSON representation of its content — title, canonical, breadcrumb, the body as `content_html` + `content_text`, and references to the page's schema.org graph and (for Notes) its provenance. Every URL is now dual-purpose. This is sub-project C of the machine-readability program; the `.json` convention is advertised per-page (a `<head>` link) and site-wide (in `/.well-known/agents.json`).

> **Why MINOR:** a new agent-visible representation, additive. No public function/REST route removed or renamed, no settings-schema change, no WP-floor raise. A distinct `.json` URL is edge-cache-safe (its own cache key) and rides the plugin's existing per-URL purge via the `sn_cf_purge_urls_for_post` filter.

### Added
- `inc/content-json.php` — a flush-free `.json` virtual route (`template_redirect` priority 0, no rewrite flush): resolves `/<path>.json` to a **published, non-password-protected** singular Note/Page and serves `application/json`. Collection/listing paths (e.g. `/notes`, the front page) are excluded — the JSON Feed already serves the Notes collection. Advertises the twin from each singular page's `<head>` and in the `sn_agents_surfaces` manifest, and registers the `.json` URL for cache purging ([inc/content-json.php](inc/content-json.php)).
- `inc/content-json-document.php` — the document builder: `content_html` + `content_text` (mirroring the JSON Feed convention), breadcrumb (Notes: Home → Notes → self; Pages: Home → ancestors → self), ISO-8601 dates, and schema/provenance references ([inc/content-json-document.php](inc/content-json-document.php)).
- `tests/content-json.php` + `tests/content-json-document.php`, plus the `content-json` surface assertion in `tests/agents-manifest.php`.

## [10.37.0] - 2026-07-11: /.well-known/agents.json machine-surfaces discovery manifest

**Headline:** A single machine-readable "front door." The site already exposes many machine surfaces — `llms.txt`, RSS + JSON feeds, OpenSearch, the sitemap, the Abilities API, provenance verification — but an agent or crawler had to know each convention independently. This adds one JSON index at `/.well-known/agents.json` that enumerates them all, so a machine discovers every surface from one entry point. The manifest is advertised from every page's `<head>` (an `alternate`/`json` `<link>`) and from a new `## Machine surfaces` section in `/llms.txt`. This is sub-project A of the machine-readability program; the surface list is filterable (`sn_agents_surfaces`) so later phases append their entry with no edit here.

> **Why MINOR:** a new agent-visible discovery surface, additive. No public function/REST route removed or renamed, no settings-schema change, no WP-floor raise. Same flush-free virtual-route mechanism as `/.well-known/gpc.json`.

### Added
- `inc/agents-manifest.php` — serves `/.well-known/agents.json` (200, `application/json`, byte-stable/edge-cacheable). A pure, filterable `sn_agents_surfaces()` builds the surface index `{type, url, title, description, format}`; `sn_agents_head_link()` advertises it via a `<head>` `<link rel="alternate" type="application/json">`. Mirrors `inc/gpc-json.php`'s `template_redirect` priority-0 route ([inc/agents-manifest.php](inc/agents-manifest.php), [functions.php](functions.php)).
- `tests/agents-manifest.php` — 23 standalone assertions covering the request matcher, surface purity/absoluteness, the `sn_agents_surfaces` filter seam (proves a later phase can append an entry), defensive drop of malformed entries, JSON validity with unescaped slashes, and the head link ([tests/agents-manifest.php](tests/agents-manifest.php)).

### Improvements
- `/llms.txt` and `/llms-full.txt` gain a `## Machine surfaces` section pointing at the discovery manifest and the Abilities API, so an LLM reader is routed to the programmatic index ([inc/llms-txt.php](inc/llms-txt.php)).

## [10.36.0] - 2026-07-10: /accessibility + /contact/personal render from CMS Pages (pages-to-CMS flip, Phase 2c)

**Headline:** The theme half of Phase 2c, completing the pages-to-CMS flip. The last two postless virtual routes — `/accessibility` (top-level) and `/contact/personal` (child of `/contact`) — become real CMS Pages. This adds their bare-frame templates, registers them as selectable, enqueues `accessibility.css` on the real `/accessibility` Page, and removes the four route files. It also retires the theme's last `sn_seo_route_meta` handler: with every former virtual route now a real Page, that filter chain is empty, so the postless-route SEO path is gone (real Pages resolve meta from their Excerpt and WebPage JSON-LD from `is_singular`). Deploy the paired plugin **v9.21.0 first** (it creates the Pages), then this.

> **Why MINOR:** two former virtual routes become real, editable Pages rendered from their templates; a new `accessibility.css` enqueue keyed on the Page. No public function/REST removed (the retired `sn_seo_route_meta_for_accessibility` is an internal filter handler), no settings-schema change, no WP-floor raise.

### Added
- `templates/page-accessibility.html` + `templates/page-personal.html` (bare `header` + `<main>` + `wp:post-content` + `footer` frames) and matching `theme.json` `customTemplates` entries ([templates/](templates/), [theme.json](theme.json)).
- `accessibility.css` enqueue on `is_page('accessibility')` in `inc/cms-page-styles.php` (mirrors the `now.css` / `uses.css` pattern; depends on `sn-components`). The seeded block content carries the `sn-a11y-*` classes, so the statement keeps its bespoke design while being block-editable ([inc/cms-page-styles.php](inc/cms-page-styles.php)).

### Removed
- The `/accessibility` and `/contact/personal` virtual-route machinery: `inc/page-accessibility-template.php`, `inc/page-accessibility-render.php`, `inc/page-personal-template.php`, `inc/page-personal-render.php`, and their `require_once` lines ([functions.php](functions.php)).
- `sn_seo_route_meta_for_accessibility` and the `sn_seo_route_meta` filter registration — the theme no longer answers that postless-route filter (all former virtual routes are real Pages). `sn_seo_route_singular_description` + the `/colophon` description map stay ([inc/seo-route-meta.php](inc/seo-route-meta.php)).
- `tests/page-accessibility.php` + `tests/page-personal.php` (the removed routes' fixtures).

### Notes
- `/accessibility`'s meta description now comes from its Page Excerpt (seeded by the plugin); `/contact/personal` gains `WebPage` JSON-LD as a real Page. `tests/seo-route-meta.php` updated to assert the route-meta handler is retired.
- After the flip both pages are edited in the block editor (Gutenberg). Deploy order matters: plugin **v9.21.0 first**, then this theme release; theme-first would 404 the routes. Purge the edge cache after (a newly-created route can serve a stale pre-Page 404). Tests: full sweep 66 suites / 0 failures.

## [10.35.0] - 2026-07-10: /about/uses is a CMS Page + /now dossier design restored (pages-to-CMS flip, Phase 2b)

**Headline:** `/about/uses` joins `/now` as a real CMS Page, and this restores the bespoke "dossier" design for both. The companion plugin (v9.20.0) now reproduces the original `sn-now-*` / `sn-uses-*` markup in the Page body, so this release brings back `now.css` (removed in v10.34.0) and enqueues `now.css` on `/now` and `uses.css` on `/about/uses`. It also adds `templates/page-uses.html` and removes the `/about/uses` virtual route. The result renders identically to the pre-flip pages, including any Site-Editor global styles that target those classes.

> **Why MINOR:** a new CMS Page template + design-fidelity restoration for `/now`. No settings-schema change. Requires plugin v9.20.0+.

### Added
- `inc/cms-page-styles.php`: enqueues `now.css` on `is_page('now')` and `uses.css` on `is_page('about/uses')` (the dossier stylesheets, formerly enqueued by the virtual-route templates) ([inc/cms-page-styles.php](inc/cms-page-styles.php)).
- `templates/page-uses.html` (bare frame) + a `page-uses` entry in `theme.json` `customTemplates` — renders the `/about/uses` child Page body ([templates/page-uses.html](templates/page-uses.html), [theme.json](theme.json)).
- Restored `assets/css/now.css` (removed in v10.34.0) — the plugin now emits `sn-now-*` markup again, so this stylesheet drives the design ([assets/css/now.css](assets/css/now.css)).

### Removed
- The `/about/uses` virtual route: `inc/page-uses-template.php`, `inc/page-uses-render.php`, `inc/uses-data.php`, `tests/page-uses.php`, and their `require_once` lines. `/about/uses` now resolves to the real child Page ([functions.php](functions.php)).
- `sn_seo_route_meta_for_uses()` and its filter registration from `inc/seo-route-meta.php`. The Uses Page Excerpt supplies the meta description; the plugin's `is_singular` branch emits `ProfilePage` JSON-LD ([inc/seo-route-meta.php](inc/seo-route-meta.php)).

### Notes
- The `/now` and `/about/uses` bodies are generated by the plugin from their Content text boxes (reproducing the original dossier markup); this theme just enqueues the stylesheets and provides the frames. `uses.css` was already present and is unchanged.
- **Requires plugin v9.20.0+ and must deploy AFTER it** (the plugin creates the Pages first; removing the `/about/uses` interceptor before the Page exists would 404 the URL). `/now` was never deployed at v10.34.0, so this is its first live form.
- Full sweep 68 suites, 1616 assertions.

## [10.34.0] - 2026-07-10: /now is a real CMS Page (pages-to-CMS flip, Phase 2a: Now pilot)

**Headline:** The first Phase-2 route. `/now` was a postless PHP virtual route; it is now a real Page rendered through a new `templates/page-now.html` frame (`header` + `<main>` + `wp:post-content` + `footer`). This release **removes the virtual-route interceptor** (`page-now-template.php`, its render + data files, and `now.css`) and the `sn_seo_route_meta_for_now` handler, so WordPress resolves the real Page, whose body (seeded by the companion plugin v9.19.0 from the Content → Now Page text box) renders with a native Excerpt + WebPage schema. The hero, including its automatic "Updated" byline (a `core/post-date` modified block), now lives in the editable Page body. **Requires plugin v9.19.0+ and must deploy after it** (the plugin creates the Page first; removing this interceptor before the Page exists would 404 `/now`).

> **Why MINOR:** `/now` becomes CMS-authored on the front end. No settings-schema change. Requires plugin v9.19.0+.

### Added
- `templates/page-now.html`: the bare frame that renders the Now Page body via `wp:post-content` ([templates/page-now.html](templates/page-now.html)).
- `page-now` registered in `theme.json` `customTemplates` (selectable in the Page → Template picker) ([theme.json](theme.json)).

### Removed
- The `/now` virtual route and its assets: `inc/page-now-template.php`, `inc/page-now-render.php`, `inc/now-data.php`, `assets/css/now.css`, and their `require_once` lines in `functions.php`. `/now` now resolves to the real Page ([functions.php](functions.php)).
- `sn_seo_route_meta_for_now()` and its filter registration from `inc/seo-route-meta.php`. The Now Page's Excerpt supplies the meta description, and the plugin's `is_singular` schema branch emits its JSON-LD ([inc/seo-route-meta.php](inc/seo-route-meta.php)).

### Notes
- The footer nav link to `/now` is unchanged (still a valid URL).
- Tests: `tests/page-now.php` removed (route retired); `tests/seo-route-meta.php` drops the `/now` assertions (keeps `/about/uses` + `/accessibility`). Full sweep 69 suites, 1659 assertions.

## [10.33.0] - 2026-07-10: /resume + /music render from the Page body (pages-to-CMS flip, Phase 1c — completes Phase 1)

**Headline:** The last two Phase-1 templates. `templates/page-resume.html` and `templates/page-music.html` are slimmed to bare frames (`header` + `<main>` + `wp:post-content` + `footer`), rendering the merged bodies the companion plugin (v9.18.0) placed in each Page's `post_content`. This **also fixes a duplicate render**: because these templates already had a `wp:post-content` slot, once the plugin merged the prose into the DB the un-slimmed templates showed the hero (and Music's discography/credits) twice — slimming resolves it. Same pages, now fully CMS-authored. **Requires plugin v9.18.0+ and must deploy together with it** (the plugin merge and this slim are two halves of one change). With this, all five editorial pages (About/Contact/Services/Resume/Music) are CMS-authored.

> **Why MINOR:** two more pages become CMS-authored on the front end. No settings-schema change. Requires plugin v9.18.0+.

### Changed
- `templates/page-resume.html` + `templates/page-music.html` slimmed to `header` + `<main class="wp-block-group">` + `wp:post-content` + `footer`; their bodies now render from the Pages' `post_content` (Resume: hero + PDF; Music: hero + featured player + `[sn_discography]` + Muso credits — all seeded/merged by the plugin) ([templates/page-resume.html](templates/page-resume.html), [templates/page-music.html](templates/page-music.html)).
- Dropped the last hardcoded description (`'music'`) from `sn_seo_page_descriptions()` — only `'colophon'` (still file/pattern-based) remains; every flipped page now resolves SEO from its Page Excerpt ([inc/seo-route-meta.php](inc/seo-route-meta.php)).

## [10.32.0] - 2026-07-10: /contact + /services are now CMS-authored — templates render the Page bodies (pages-to-CMS flip, Phase 1b)

**Headline:** Two more pages follow About into the WordPress editor. `templates/page-contact.html` and `templates/page-services.html` are slimmed to designed frames — `header` + `<main>` + `wp:post-content` + `footer` — and render the Page bodies the companion plugin (v9.17.0) seeded into `post_content`. Same pages, same design; now editable from Pages → …, with native Excerpts driving their SEO. Their `[sn_availability]` / `[sn_email]` shortcodes resolve unchanged inside `post_content`. The visible pages are unchanged.

> **Why MINOR:** two new user-visible capabilities — Contact + Services are CMS-authored and the front end reflects it. No settings-schema change. Requires the companion plugin at **v9.17.0+** (which seeds the Page bodies); deploy the plugin and confirm both Page bodies before deploying this.

### Changed
- `templates/page-contact.html` and `templates/page-services.html` slimmed to `header` + `<main class="wp-block-group">` + `wp:post-content` + `footer`; their content now comes from the respective Pages' `post_content` (design/widths carried in the seeded bodies) ([templates/page-contact.html](templates/page-contact.html), [templates/page-services.html](templates/page-services.html)).
- Dropped the hardcoded `'contact'` and `'services'` descriptions from `sn_seo_page_descriptions()` — their native Excerpts now supply the meta description. `'colophon'` and `'music'` remain (not yet flipped) ([inc/seo-route-meta.php](inc/seo-route-meta.php)).

## [10.31.0] - 2026-07-10: /about is now CMS-authored — template renders the Page body (pages-to-CMS flip, About pilot)

**Headline:** The About page's content now lives in the WordPress editor (Pages → About), not in the template file. `templates/page-about.html` is slimmed to a designed frame — `header` + `<main>` + `wp:post-content` + `footer` — and renders the Page body that the companion plugin (v9.16.0) seeded into `post_content`. Same page, same widths, same design; now editable in wp-admin with a native Excerpt driving its SEO description. The visible page is unchanged.

> **Why MINOR:** a new user-visible capability — About is now editable from the CMS and the front end reflects it. No settings-schema change. Requires the companion plugin at **v9.16.0+** (which seeds the Page body); deploy the plugin and confirm the About Page body before deploying this.

### Changed
- `templates/page-about.html` slimmed to `header` + `<main class="wp-block-group">` + `wp:post-content` + `footer`; the six content sections now come from the About Page's `post_content` (the design/widths moved with them — the `1400px` wide track is carried in the seeded body) ([templates/page-about.html](templates/page-about.html)).
- Dropped the hardcoded `'about'` description from `sn_seo_page_descriptions()` — the About Page's native Excerpt now supplies its meta description via the plugin's resolver (excerpt precedes this filter). Other slugs (contact/colophon/music/services) are untouched pending their own flips ([inc/seo-route-meta.php](inc/seo-route-meta.php)).

## [10.30.0] - 2026-07-09: Provenance surfacing on public Notes (byline chip + closing record)

**Headline:** The companion signal-and-noise-tools plugin already computes and renders the public provenance of each Note (its commit-chain byline chip and its expandable record panel), but nothing placed that output on the front end. This wires it in: a new theme module registers `[sn_prov_chip]` and `[sn_prov_panel]`, and the two single-Note template parts carry them — the chip joins the byline spec-row after the pillar slot, and the record sits in the closing footer between the rule and the prev/next nav. Both are thin placement seams: the plugin owns the markup, the theme only decides where it appears.

> **Why MINOR:** a new user-visible surface (provenance now renders on public Notes) via two additive shortcodes and their template-part placement — no removed/renamed API, no `theme.json` change, no settings-schema change. Per [docs/VERSIONING.md](docs/VERSIONING.md), a new visible capability is a MINOR.

### Added
- [inc/provenance-surface.php](inc/provenance-surface.php): registers `[sn_prov_chip]` → `sn_prov_render_chip( get_the_ID() )` and `[sn_prov_panel]` → `sn_prov_render_panel( get_the_ID() )`, each `function_exists()`-guarded so the Note degrades cleanly to `''` when the companion plugin is absent — mirroring how [inc/related-notes.php](inc/related-notes.php) guards `sn_get_reading_time`. A `render_block` bridge (priority 10, 2 args) gated on the two literal tokens resolves them inside the block template parts, because `core/shortcode` only `wpautop()`s its content and never runs `do_shortcode` on block-template output; `shortcode_unautop()` strips the invalid `<p>` that would otherwise wrap the block-level panel. Structure mirrors `sn_related_notes_render_block_bridge` and `sn_404_suggestions_render_block_bridge`.
- [parts/post-frontmatter.html](parts/post-frontmatter.html): a `wp:shortcode` block with `[sn_prov_chip]` at the end of the byline group, after `[sn_post_pillar]`.
- [parts/post-closing.html](parts/post-closing.html): a `wp:shortcode` block with `[sn_prov_panel]` between the opening separator and the prev/next nav group.
- [tests/provenance-surface.php](tests/provenance-surface.php): black-box fixture coverage — both shortcodes register, each returns the plugin helper's output (with `get_the_ID()` flowing through) when the plugin fn exists and `''` when it does not, and the bridge resolves `do_shortcode` only on blocks carrying a token (no-op otherwise, panel not `<p>`-wrapped). 23 assertions.

### Notes
- The provenance markup is plugin-owned (`sn_prov_render_chip` / `sn_prov_render_panel`) and both helpers already return `''` for non-Notes and for Notes without a chain, so the shortcodes stay inert off a provenance-bearing Note. The tokens live only in the two single-Note template parts (used solely by `templates/single.html`), and the `render_block` bridge — like its related-notes/404 siblings — matches on the literal token rather than the query, so the chip/panel cannot leak into related-note excerpts, the /notes index, or widgets.

## [10.29.2] - 2026-07-08: About page rebuild (five sections; Panacea founding year corrected)

**Headline:** The `/about` page grew from two content sections to five. The bio (Who I Am) and the mentorship section keep their block structure with refreshed prose, and three new sections slot between them: Studio and Clients, Research, and Service. Each new section is cloned from the existing mentorship chassis (same group, columns, eyebrow, and heading classes; no new patterns; no `theme.json` change). Panacea's founding year is corrected from 2015 to 2016, verified against both resumes.

> **Why PATCH:** the rebuild adds three new content sections as block-template markup (new `group`/`columns`/`heading`/`spacer` blocks that affect rendering), plus a factual date correction and a copy refinement. Per [docs/VERSIONING.md](docs/VERSIONING.md) a block-template markup change that affects rendering bumps, and this adds no new capability, API, route, or `theme.json` change, so it is a PATCH, not a MINOR. It ships now (rather than riding a later release) so the visible 2016 correction reaches production in the same window as the companion signal-and-noise-tools plugin's matching JSON-LD `foundingDate` fix, keeping the page and its structured data consistent. (The `/about` content was first merged under an `[Unreleased]` no-bump entry in `#86`; this promotes it to a cut release.)

### Changed
- `/about` now renders five sections in order: Who I Am, Studio and Clients, Research, Service, Education & Mentorship. The three middle sections are new; the first and last keep their structure with new copy. All content lives in the template (the page has no `wp:post-content`), cloned verbatim from the mentorship section's group/columns/`sn-catalog-eyebrow`/`clamp()`-heading markup, with no invented classes, inline styles, or patterns.
- Corrected Panacea's founding year from 2015 to 2016 in the bio's visible prose (the only visible occurrence of the year in the theme).
- Softened the Service section's closing line at the owner's request, from "Taking it seriously means doing the unpaid part." to "That includes the work no one bills for.", stating the principle without an implied judgment of peers.

### Notes
- **Schema follow-up (separate repo, out of scope here):** the live page's JSON-LD emits `{"@type":"Organization","name":"Panacea","foundingDate":"2015"}` from the companion signal-and-noise-tools plugin, not this theme. It still reads 2015 and now mismatches the visible 2016. Correcting it is a plugin change and is flagged for a follow-up so schema and copy agree.
- The `/about` meta description is theme-owned ([inc/seo-route-meta.php](inc/seo-route-meta.php), via `sn_seo_page_descriptions()`), carries no founding year, and is unchanged.

## [10.29.1] - 2026-07-08: Audit-hygiene patch — readme parity, ability-count comments, and a CI guard to close the drift class

**Headline:** The 2026-07-08 CMA diff audit (v10.28.1→v10.29.0, verdict *satisfied*, 0 critical/high/medium) flagged one LOW and two INFO — all documentation/metadata drift introduced by v10.29.0. `readme.txt` `Stable tag` had slipped back to `10.28.1` (v10.29.0 bumped `style.css` but not the hand-maintained readme field — the same desync the v10.28.1 patch had just fixed), and several docblock comments still counted 13 abilities after the two SEO abilities took the theme to 15. This resyncs both and, per the audit's key recommendation, adds a CI guard so the readme/version desync can never recur silently.

> **Why PATCH:** documentation/metadata corrections plus a CI check — no runtime, route, template, or capability change. The runtime version is derived from `style.css` (`wp_get_theme()`), so none of these edits alter behavior; they make the repo's self-description match reality and mechanically enforce it going forward.

### Fixed
- Synced `readme.txt` `Stable tag: 10.28.1` → `10.29.1` (audit LOW-1). The field is hand-maintained and decoupled from the runtime version, so it silently lagged when v10.29.0 bumped `style.css` — this is the second time the pair drifted (v10.28.1 fixed it; v10.29.0 re-broke it), which is why the CI guard below exists.

### Changed
- The `changelog` CI job ([.github/workflows/ci.yml](.github/workflows/ci.yml)) now also asserts `readme.txt` `Stable tag` **equals** `style.css` `Version` on every pull request, failing with a `readme.txt` annotation on a mismatch. This closes the drift *class*: the exact v10.29.0 slip (Version bumped, Stable tag forgotten) would now fail CI at PR time instead of surfacing in a later audit.

### Documentation
- Corrected stale ability-count comments after v10.29.0 took the theme from 13 to 15 abilities (audit INFO-1): [functions.php](functions.php) module map (13 → 15, 8 → 10 read), the `inc/abilities-registration.php` orchestrator docblock (diagnostics 5 → 7 with the two SEO abilities named; `Total: 13` → `15`), and the `tests/abilities-registration.php` header (13 → 15).
- Completed the `tests/abilities-integration.php` registration roster (audit INFO-2): it asserted "All 12" while listing 12, missing `get-latest-theme-tag` (v9.9.0), `get-seo-route-meta`, and `get-llms-txt` (v10.29.0). All 15 registered slugs are now enumerated and checked.

## [10.29.0] - 2026-07-08: Two SEO abilities — expose the theme's route meta + llms.txt to agents

**Headline:** The theme registers 13 WP Abilities across design-system, templates, content, and version — but its signature owned surface, **SEO**, had none. WordPress can't describe the theme's template-driven Pages (their content lives in FSE templates, not `post_content`, so there's no excerpt) or its AI-crawler manifest, so both were invisible to any agent introspecting the site. This adds two read-only abilities that expose exactly that: the per-route meta the theme supplies, and the llms.txt manifest it generates.

> **Why MINOR:** two new agent-facing capabilities (public Abilities, `show_in_rest`), additive. Both are read-only (`readonly`/`idempotent` annotations), gated by the existing `sn_theme_perm_read` (`read` cap), and wrap generators that already exist (`sn_seo_page_descriptions()`, `sn_llms_txt_body()`) — no new query, route, or settings-schema.

### New
- **`signal-and-noise/get-seo-route-meta`** — returns the theme-supplied SEO descriptions for its template-driven Pages (about, contact, colophon, music, services). Pass a `slug` (`"services"` or `"/services"`) for one route, or omit it for the full route→description map — which doubles as a coverage check for a Page shipped without a description (the exact class of gap a prior release hit on `/services`). Reads `sn_seo_page_descriptions()`; content-free public copy.
- **`signal-and-noise/get-llms-txt`** — returns the theme-generated `llms.txt` manifest, the site's machine-readable index/summary for LLMs and answer engines (the AEO counterpart to `robots.txt`/`sitemap.xml`). `full: true` appends the recent Notes corpus (queried only then, mirroring the live route); omit for the concise index. Reads `sn_llms_txt_body()`.

Both join the `diagnostics` category (now 7 read abilities). `tests/abilities-registration.php` extended: registration + category + `readonly` annotation + execute-path assertions (slug filter incl. path-form input, unknown-slug empty, index-vs-full variant, notes-only-when-full).

## [10.28.1] - 2026-07-08: Cap the freshness-probe redirect chain (CMA audit follow-up)

**Headline:** The 2026-07-08 CMA diff audit (v10.21.8→v10.28.0, verdict *satisfied*, 0 critical/high/medium) flagged one LOW: the box-direct freshness probe `sn_purge_probe()` followed redirects uncapped. It carries no credential (so the credential-forwarding case for `redirection => 0` is genuinely moot), and the precondition — a same-host open redirect planted on one of the three fixed probed routes (`/`, `/notes/`, `/provenance/`) — needs admin/misconfig, so it is not unprivileged-exploitable. But an uncapped chain is still SSRF-relevant, so the probe now caps at **one** hop: enough to follow a legitimate canonicalizing 3xx (trailing-slash / http→https) and measure the final page, while preventing a chain that walks the origin box toward an internal endpoint. Also folds in the audit's two INFO doc/hygiene notes.

> **Why PATCH:** SSRF-hardening (a `redirection` cap on one existing outbound call) plus doc/readme corrections; no user-visible, route, template, or capability change. Same class as the plugin's v8.7.1/v8.8.5 outbound-hardening PATCHes.

### Fixed
- `sn_purge_probe()` ([inc/purge-verify.php](inc/purge-verify.php)) now passes `'redirection' => 1`, capping the freshness probe's redirect chain at a single canonicalizing hop (AUDIT-CHECKLIST §1.3). Pinned by a new assertion in `tests/purge-verify.php` (the stub now records request args, not just the URL).

### Documentation
- Corrected the `sn_bump_render_epoch()` docblock (audit INFO-1): the render epoch **is** read on every front-end `wp_head`, not "only during a purge" — the accurate rationale for keeping it non-autoloaded is to avoid loading it into `alloptions` on the many admin/AJAX/cron/REST requests where it is never read.
- Synced `readme.txt` `Stable tag: 10.18.0` → `10.28.1` (audit INFO-2): it had drifted 10 minors behind `style.css` despite claiming to mirror it.

## [10.28.0] - 2026-07-07: Count contact-email clicks as cookieless conversions (1:1 with plugin funnels)

**Headline:** The plugin's v8.8.0 shipped automatic conversion funnels that group the goal events the site already tracks. The theme's beacon (`assets/js/sn-beacon.js` §6) emits those goals — `data-sn-subscribe` on the Notes email links, plus auto-classified RSS `subscribe` and cross-host `outbound` — but the single most important conversion on the site was structurally invisible to it: the `/contact` email aliases. Those are `mailto:` links assembled at runtime by `contact-aliases.js`, and the beacon deliberately ignores `mailto:`/`tel:` (protocol isn't http/https) so DOM-built contact links are never *miscounted*. The consequence was that someone actually emailing to hire — the money event — fired nothing, and the plugin's new funnels had no `/contact` conversion to anchor on. This tags each alias with the beacon's author-intent escape hatch, `data-sn-goal`, so a click now fires a named `contact-<alias>` conversion. No plugin change is needed: the funnel surfaces any goal name it sees.

> **Why MINOR:** new user-visible capability — the site now emits `contact-<alias>` conversion events that feed the plugin's funnels. Additive and on-pattern: the `data-sn-goal` hook lives in the same PHP-rendered `[sn_email]` markup as the existing `data-sn-subscribe` convention (never in static block markup, which would risk Gutenberg block-validation recovery). Cookieless and aggregate — a bare counter, no address leaves the browser, and the beacon's existing DNT/GPC gate still suppresses opted-out visitors. No API, route, or settings-schema change.

### New
- `[sn_email]` aliases now carry `data-sn-goal="contact-<alias>"` on the persistent `.sn-email` span (`inc/contact-email.php`). `contact-aliases.js` appends the runtime `mailto:` anchor *inside* that span, so a click bubbles to `closest('[data-sn-goal]')` and `sn-beacon.js` fires the named conversion. The `/contact` routes become: `contact-research`, `contact-press`, `contact-speaking`, `contact-music`, `contact-role`. Goal names are slugged so an odd alias can't emit a malformed event name.

### Notes
- The `/services` closing-CTA buttons were intentionally left untagged. "Record at Panacea" already fires an automatic `outbound` (cross-host to panaceastud.io), and "Work with me remotely" (→ `/contact`) is upstream navigation the plugin's v8.8.0 page-transition tracking already captures — so the funnel path `/services → /contact → contact-<alias>` is fully visible without adding `data-*` to static `core/button` markup (which would risk block-validation recovery).
- No plugin- or worker-side change: the `SN_BEACON.event()` → worker `ce`/`cp` path and the plugin's goal-grouping already accept free-form goal names.

## [10.27.1] - 2026-07-06: Drop the per-offering delivery-mode labels on /services

**Headline:** v10.27.0 tagged each /services offering with a delivery-mode label ("In-studio · Buenos Aires" on production, "Remote · with me" on the rest). That was a mistake: delivery mode is a property of the engagement, not of the service. Production can happen remotely, and mixing or mastering can happen in-studio if a client travels to Buenos Aires, so a fixed per-card label mislabels the work (production was wrongly pinned to in-studio only). The two-mode story already lives where it is actually true: the page copy and the two-path closing CTA ("Record at Panacea, Buenos Aires" / "Work with me remotely"). The per-offering tags were both inaccurate and redundant, so they are removed.

> **Why PATCH:** removes a copy/UI element added in the same day's minor; no API, route, schema, or capability change. The two-path CTA, the reconciled copy, and the machine-readable meta from v10.27.0 all stay.

### Removed
- The six `.sn-service-mode` delivery-mode tags under the /services offerings, and the `.sn-service-mode` / `.is-studio` CSS component (`templates/page-services.html`, `assets/css/components.css`).

### Changed
- `tests/services-routing.php`: the assertion flipped from "mode tags present" to "mode tags absent" — a regression guard so the mislabeling cannot creep back. The two-path routing contract (both pages reach panaceastud.io, /services links /contact, no "not here" framing) is unchanged.

## [10.27.0] - 2026-07-06: Reconcile /services and /contact into one principal, two delivery modes

**Headline:** /services marketed production, mixing, and mastering in the first person and funnelled every inquiry to /contact through a "Tell me about your project" button, but /contact then told exactly those people they were in the wrong place ("that's the right channel for studio bookings, not here") and bounced them to panaceastud.io, while offering no route at all for remote music work. The two pages contradicted each other because both described the split as "me versus Panacea," which framed the owner's own Buenos Aires studio as an outside vendor. The reconciled model, matching how the work is actually delivered, is one principal with two delivery modes: hands-on recording and production happen in the room at Panacea in Buenos Aires (you travel in, and his partners execute under his direction), while mixing, mastering, songwriting, production direction, plus strategy and AI run remotely from the US. Both pages now tell that one story, and a new music@ route finally gives remote music work a home. It previously had none and fell into the /contact/personal catch-all, which is about time scarcity, not project intake. The machine-readable half is reconciled too: /services finally ships the meta description it never had (it was missing from the theme's route-description map while about/contact/colophon/music were present), and /contact's is updated from the pre-split wording, so search, social, and AI crawlers see the same two-mode story the humans do.

> **Why MINOR:** new user-visible capability, the music@ remote-music contact route (a new channel), plus a two-path Services CTA and per-offering delivery-mode tags. Additive: music@ renders through the existing generic [sn_email] shortcode with no code-path change; the rest is template copy and one CSS component. No API, route, or settings-schema change.

### New
- `/contact`: a fifth email-alias route, `[sn_email user="music"]` (music@juanlentino.com), for remote mixing, mastering, songwriting, and production direction, the delivery mode that travels over the wire. Closes the gap where remote-music inquiries had no route (`templates/page-contact.html`; docblock in `inc/contact-email.php` corrected four to five).
- `.sn-service-mode` component: an inline delivery-mode tag under each /services offering, "Remote · with me" by default and "In-studio · Buenos Aires" (`.is-studio`) for production. Built in the catalog vocabulary (body caps, 11px floor, concrete hairline) (`assets/css/components.css`).
- `/services` machine-readable meta description (`inc/seo-route-meta.php`). The theme supplies descriptions for template-driven Pages that have no post excerpt; services was absent from that map while about/contact/colophon/music were present, so the site's key commercial page shipped with no `<meta name="description">`/OG summary for search, social, or AI crawlers. Now present, two-mode-aware, and length-checked by the suite.

### Changed
- `/services` closing CTA is now two-path instead of a single dead-end: "Record at Panacea, Buenos Aires" links to panaceastud.io, and "Work with me remotely" links to /contact. The old single button sent studio inquiries to a page that rejected them (`templates/page-services.html`).
- `/contact` Panacea line reframed from a third-party redirect ("the right channel for studio bookings, not here") to a first-person delivery mode ("Panacea, my studio in Buenos Aires ... book the room at panaceastud.io") (`templates/page-contact.html`).
- `/services` Operations credential updated to present tense: Panacea is "the Buenos Aires studio I still direct," consistent with the /contact/personal page's "I run Panacea Studio" (`templates/page-services.html`).
- `/contact` machine-readable meta description reconciled to the two-mode framing (was "mixing, production, and creative work", which predated the split), and em-dashes removed from every route meta description (/contact, /about, /music, /now, /about/uses, /accessibility) per the site's no-em-dash house style, since these are SERP/social/AI-facing strings (`inc/seo-route-meta.php`).

### Improvements
- `tests/services-routing.php` (new): locks the cross-page routing contract so the pages cannot silently drift apart again (both paths present on /services, `.sn-service-mode` tags present, both pages route studio work to panaceastud.io, no "not here" framing on /contact).
- `tests/contact-email.php`: the alias leak-guard and presence loop now cover `music` as the fifth alias.
- `tests/seo-route-meta.php`: asserts the description map covers `services`, and that no route meta description (page map or virtual route) carries an em-dash.

## [10.26.0] - 2026-07-06: Goal-action click tracking — cookieless conversion events on the beacon

**Headline:** The first-party beacon now records conversion-relevant clicks so the companion plugin's Visits/funnel dashboard can surface client-side goals, without a single new identifier. One delegated `click` listener classifies each click and fires at most one `ce` custom event through the beacon's existing `SN_BEACON.event()` sender (already wired to the worker's `ce`/`cp` rows): an **outbound** link → `outbound` with the destination HOST ONLY (never the path or query — no page/query-string PII leaves the browser), an RSS/feed link → `subscribe`/`{target:'rss'}`, and two documented, zero-JS-change conventions — `data-sn-goal="<name>"` on any element fires a named goal, and `data-sn-subscribe="<target>"` fires `subscribe` with that target. The Notes "via email" links (Blogtrottr/Feedrabbit) are tagged `data-sn-subscribe="email"` so the email-subscribe funnel actually fires. Priority is author-intent-first, so a tagged link is counted once and never double-fires as outbound. Cookieless and identifier-free by construction; the whole section runs after the DNT/GPC gate, so an opted-out visitor never binds the listener, and `mailto:`/`tel:` links (the theme's DOM-built contact aliases) match nothing and are never miscounted.

> **Why MINOR:** new user-visible capability (client-side conversion events the plugin's funnels consume). Purely additive — it reuses the existing `ce` beacon payload/endpoint/token and the existing `SN_BEACON.event()` API; no payload key, function, or filter contract changes, and no new network path is introduced. The event names (`outbound`, `subscribe`, plus author-defined goal names) are the stable identifiers the plugin references.

### New
- `assets/js/sn-beacon.js` section 6: one delegated, first-match-wins `click` listener that fires `ce` goal events. Detects `[data-sn-goal]` (named goal) → `[data-sn-subscribe]` (subscribe target) → RSS/feed links (feed `type` attr, a `/feed/` path segment, or `?feed=`) → cross-host `http(s)` outbound links. Outbound sends `{ host: url.hostname }` only. Lives after the DNT/GPC gate (never binds for opted-out visitors) and reuses the existing clamps in `SN_BEACON.event()` (name ≤ 64, prop key ≤ 60, value ≤ 180, ≤ 4 props).
- `inc/page-notes-render.php`: the Notes "via email" links (Blogtrottr, Feedrabbit) carry `data-sn-subscribe="email"`, so they count as `subscribe`/email conversions instead of anonymous outbound clicks.

### Improvements
- `tests/beacon.php`: extended the beacon JS-content contract — exactly one `click` listener (no double-fire), the two `data-sn-*` conventions, `outbound` carrying a host-only prop (no path/query leak), the cross-host gate, first-match-wins priority (explicit classification precedes outbound), and the listener sitting after the privacy gate.

## [10.25.0] - 2026-07-06: Auto-purges verify their routes too, asynchronously, off the save path

**Headline:** The v10.24.0 route probe only ran on the manual "Purge All Caches"; an auto-purge (a theme/plugin update completing, a Site Editor Styles save) wrote its report fast and skipped the probe, so a purge that quietly failed to reach the edge was never checked. Now it is: an auto edge-purge schedules a single one-shot WP-Cron event ~75s out (enough for edge propagation), and that cron request, not the save request, runs the same probe/escalate loop (`sn_purge_verify_routes()`: read each route's served `sn-render-epoch` through Cloudflare, re-evict + back off + re-probe a stale one) and folds `routes` + `resolved` back into the durable `sn_last_purge_report`, stamped `verify => cron` + `verified_at`. The save path stays O(1): scheduling only, all probing is deferred. Rapid successive auto-purges (a batch of Styles saves) collapse to one pending verify targeting the latest render, and a run whose report has since been superseded by a newer purge skips rather than clobbering the fresher freshness.

> **Why MINOR:** new user-visible capability (auto-purges now self-verify and report per-route freshness, where before only manual purges did). Purely additive: `sn_last_purge_report` gains fields on auto reports, no function or filter contract is removed or changed, and the probe reuses the existing Tier-2 loop verbatim, so an unreachable route stays `fresh => null` and is never coerced to a pass.

### New
- `inc/purge-verify-cron.php`: `sn_schedule_auto_purge_verify()` schedules the deferred verify (guarded with `wp_next_scheduled()` on the epoch arg + `wp_unschedule_hook()` replace, so rapid auto-purges never stack duplicate events). `sn_after_purge_schedule_verify()` on `sn_after_full_cache_flush` (priority 20, after the report writer) defers only auto edge-purges; verified purges probe inline, non-edge flushes change nothing cached. `sn_verify_auto_purge_cron()` runs the probe in the cron request and merges the result onto the matching report, skipping when a newer purge has since written a fresher one (epoch is the discriminator, re-checked after the blocking probe too).
- `sn_auto_purge_verify_delay` filter (default 75s) for the edge-propagation wait; `sn_verify_auto_purge` cron hook.
- New suite `tests/purge-verify-cron.php`: registration + priority, one-event-no-stacking (same-epoch idempotent, newer-epoch replace), verified/non-edge purges defer nothing, the handler merges `routes`/`resolved`/`verify`/`verified_at` onto the matching report, skips a superseded (newer-epoch) report, and records `fresh => null` honestly on an unreachable route.

## [10.24.0] - 2026-07-03: Verified purge Tier-2: box-direct freshness probe + auto-heal

**Headline:** The manual "Purge All Caches" now proves the edge actually serves the current render, instead of assuming it. After the purge legs fire, the box probes each cache-critical route (`/`, `/notes/`, `/provenance/`) straight through Cloudflare and compares the served `sn-render-epoch` against the epoch this purge bumped to. A route reading a stale (older) epoch is escalated: re-evict Cloudflare, back off for propagation, re-probe, up to a small budget. The durable `sn_last_purge_report` gains per-route rows (`fresh`/`stale`/`unknown` + `cf-cache-status`) and a `resolved` verdict, so a purge that doesn't fully land is reported honestly rather than fabricating success. This is the Tier-2 slice, unblocked by a live box-curl confirming the origin box reaches its own Cloudflare edge — so no analytics-worker endpoint is needed; the box probes directly.

> **Why MINOR:** new capability (verified probe + auto-heal + per-route report rows). The `sn_last_purge_report` option only gains fields, the filter contract still returns int — nothing breaks. Auto-purges are untouched (the probe runs only on the synchronous manual purge; a deferred cron verify for auto-purges is a later slice).

### New
- `sn_purge_probe()` (`inc/purge-verify.php`): an anonymous, redirect-following GET of a route through CF from the box; parses the `sn-render-epoch` meta + reads `cf-cache-status`. Returns `fresh` = true/false, or `null` (unknown) on transport error / missing marker — never coerced to a pass.
- `sn_purge_verify_routes()`: probes every route, escalates a stale one (re-evict CF + bounded backoff + re-probe), returns per-route results + a `resolved` verdict. Wired into the report writer for verified (manual) purges only.
- `sn_verified_purge_routes()` filter (default `/`, `/notes/`, `/provenance/`) + `sn_purge_verify_backoff_us` filter (default 1.5s). `sn_last_purge_report` gains `routes[]` + `resolved` on a verified purge.
- `tests/purge-verify.php` extended: route list, probe fresh/stale/unknown, escalate (all-fresh / stale-then-fresh / persistently-stale), report integration + the auto-purge-defers-the-probe guard.

## [10.23.0] - 2026-07-03: Verified purge Tier-1: render-epoch marker + durable per-leg purge report

**Headline:** The purge chain stops being fire-and-forget and starts leaving a record. A monotonic render-epoch now rides every page (`<meta name="sn-render-epoch">`), bumping at the start of every edge-affecting purge, so a still-stale edge carries the old value while a fresh origin render carries the new one: a universal freshness differential (any render change, not just CSS) the plugin's dashboard dot compares canonical-vs-cache-busted. The manual "Purge All Caches" now confirms its Cloudflare leg (a blocking read of CF's real `{success:true}` body) and writes a durable per-leg report (`sn_last_purge_report`) recording what Breeze, Cloudways Varnish, and Cloudflare each returned. This is Tier-1 of the verified-purge arc; the external-worker probe + verify-and-escalate loop (Tier-2) is deferred, because the analytics worker's workers.dev URL is off by security design and box-to-edge reachability is an on-box empirical. The browser-vantage dot is the freshness signal until then.

> **Why MINOR:** new capability (render-epoch marker, verified-purge report, `sn_before_cache_flush` seam). The `sn_purge_all_caches_result` filter still returns int and every existing arg keeps its meaning, so nothing breaks.

### New
- `inc/purge-verify.php`: `sn_render_epoch()` / `sn_bump_render_epoch()` (autoload=no option, seeds 1) + `sn_render_epoch_meta()` on `wp_head`. The epoch bumps at the START of every edge-affecting purge via the new `sn_before_cache_flush` action, so a purge that fails to propagate is detectable. New suite `tests/purge-verify.php`.
- Durable `sn_last_purge_report` option (NOT a transient: the purge itself deletes `_transient_sn_%`, so a transient report would erase itself). Captures per-leg state: the Breeze file listener, the Cloudways Varnish `{ok, operation_id}` (read from the plugin's `sn_cloudways_last_purge`), and the CF leg (a confirmed `{accepted, http, cf_success}` on a verified purge, dispatched-but-unconfirmed on a fast auto-purge). Written via `sn_after_full_cache_flush`.
- `sn_before_cache_flush` action, fired at the top of `sn_purge_all_caches()`, symmetric with the existing `sn_after_full_cache_flush`.

### Changed
- `sn_purge_all_caches()` accepts `verified => true`: it routes the Cloudflare leg to the plugin's blocking `sn_cf_purge_everything_verified()` so the report carries a real accept-confirmation. The manual "Purge All Caches" / "Full reset" buttons pass it; fast auto-purges (theme/plugin update, Styles save) stay non-blocking so a save or update never waits on the CF API. `tests/template-maintenance.php` gains the verified-path + seam assertions.

## [10.22.0] - 2026-07-02 — Automatic cache purges: updates and Styles saves now ride the full chain

**Headline:** Installing v10.21.9 and deleting one Additional-CSS rule left three cache layers (Breeze file cache → Varnish → Cloudflare) serving a morning-stale render through four manual layer-by-layer purges — and each outer purge re-cached the still-stale inner layer. The coordinated `sn_purge_all_caches()` chain already existed, in the right inner→outer order, wired to the admin-bar button; nothing fired it automatically. Now the two events that change rendered HTML outside a post save do: our own theme/plugin update completing triggers the full chain (once per request, Site Editor overrides untouched), and every Site Editor Styles save — including Additional CSS, which rides no other purge path — triggers a focused origin + CDN purge. The multi-console purge dance is gone.

> **Why MINOR:** new user-visible capability (automatic purge behavior on two new triggers); no API change, no breaking change.

### New
- `sn_auto_purge_on_update()` on `upgrader_process_complete`: full-chain purge when the Signal & Noise theme or the signal-and-noise-tools plugin finishes updating (`template_overrides => false` — an update never nukes Site Editor edits; debounced once per request for batch updates). Runs in the updating request after new files land — safe for old-code execution, unlike migrations (`inc/template-maintenance.php`).
- `sn_auto_purge_on_styles_save()` on `save_post_wp_global_styles`: focused Breeze + Varnish + Cloudflare purge on every Site Editor Styles save (the `wp_global_styles` CPT is invisible to Breeze's own post-save purging).
- New suite `tests/template-maintenance.php`: trigger registration, full-chain assertions, debounce, negatives (foreign themes/plugins, installs, translations, malformed hook_extra), and the focused Styles-save profile.

## [10.21.9] - 2026-07-02 — Combined mode: restore the five stylesheets that depended on sn-components

**Headline:** The first CMA diff-audit of the v10.21.3–v10.21.8 arc found the combined-CSS branch registered only `sn-styles`, while five unchanged sibling modules — keyboard-nav (every single post) plus the /now, /index, /accessibility, and /uses routes — still hard-depend on `sn-components`, a handle only the fallback branch registers. WordPress silently drops a handle whose dependency was never registered (no `<link>`, just a `_doing_it_wrong()` nobody sees), so all five stylesheets vanished on production whenever the combiner worked as designed — verified live before fixing (`/now` rendered with no page CSS). The fix is core's own alias pattern: one false-src `sn-components` registration depending on `sn-styles`, and every dependent resolves again with zero call-site changes. A classic blast-radius miss: the diff's own tests were green; the bug lived where new code met unchanged code.

> **Why PATCH:** restores the v10.21.6 feature's intended behavior at five pre-existing call sites; no API change.

### Fixed
- Combined mode registers `sn-components` as a false-src alias of `sn-styles`, so `sn-keyboard-nav`, `sn-now`, `sn-index`, `sn-a11y`, and `sn-uses` print again (`inc/assets-frontend.php`). New suite `tests/assets-frontend.php` exercises BOTH enqueue branches — the combined branch had zero coverage; every prior suite only ever reached the fallback.
- url() guard: bare `%` allowance narrowed to `%23` (encoded `#`, the grain texture's one legit case). The WHATWG URL Standard normalizes `%2e`/`%2e%2e` to `.`/`..`, so bare-`%` waved percent-encoded RELATIVE paths into the combined file instead of failing open (`inc/asset-combine.php`; fixtures for `url(%2e%2e/…)` + `url(%2E/…)` in `tests/asset-combine.php`).
- Phantom `steel` color slug (the `rust` swatch's display name typed as its slug) replaced with `rust` in the six occurrences the v10.21.1 footer fix never covered: `patterns/colophon.php` ×1, `templates/page-services.html` ×4, `templates/page-music.html` ×1. New suite `tests/color-slug-integrity.php` validates EVERY named-color reference in templates/parts/patterns against the theme.json palette — closes the class, not the instance.

### Improvements
- Combiner temp file is PID-suffixed (concurrent cold-cache builders never share a temp path) with age-gated cleanup of crash-orphaned temps (`inc/asset-combine.php`).

## [10.21.8] - 2026-07-02 — Combiner url() guard: allow fragment + percent-encoded references

**Headline:** The v10.21.7 guard fix was still one prefix short: base.css's grain texture embeds an inline SVG whose encoded payload contains `url(%23noise)` (a percent-encoded `url(#noise)` filter reference), and the guard scans every `url(` occurrence, including ones inside an already-safe `data:` URI. Production stayed on the per-file fallback and the combined file was never built. The guard now also allows `#` (same-document SVG fragments, not file fetches) and `%` (percent-encoded payload content); real relative paths start with letters or dots and still trip it. Validated against the five actual production stylesheets before shipping, not just constructed cases.

> **Why PATCH:** one-character-class guard calibration; restores the v10.21.6 feature's intended behavior.

### Fixed
- `sn_css_ensure_combined()` url() guard: exclusion set extended to `data:|https?:|//|/|#|%`. Regression fixture embeds the real grain-texture pattern (encoded SVG payload with internal `url(%23noise)`) plus a bare `url(#fragment)` (`tests/asset-combine.php`).

## [10.21.7] - 2026-07-02 — Combiner url() guard: move the quote inside the lookahead (no backtracking hole)

**Headline:** v10.21.6's relative-url() guard let its optional quote backtrack to empty, so the negative lookahead tested the quote character itself and the guard fired on EVERY quoted url() — including the data: URIs live components.css actually contains. Production silently fell back to per-file enqueues (the fail-open contract worked exactly as designed; the site never lost styling) and no combined file was ever built. The quote now lives inside the lookahead; quoted data:/https:/absolute urls combine, relative urls still abort. Found via the computed-hash probe returning 404 on the expected uploads URL.

> **Why PATCH:** regex fix restoring the v10.21.6 feature's intended behavior; no API change.

### Fixed
- `sn_css_ensure_combined()` url() guard regex: `url\(\s*(?!['"]?(?:data:|https?:|//|/))` (quote inside the lookahead, no backtracking hole). Regression fixtures: quoted data:/https:/absolute urls in a source must combine; relative urls (quoted or bare) must still fail open (`tests/asset-combine.php` Test 6b).
- Memoized fallback verdicts now short-circuit correctly (`array_key_exists`, not `isset` — stored nulls report false to isset).

## [10.21.6] - 2026-07-02 — Runtime combined + minified stylesheet: the theme owns production CSS delivery

**Headline:** The front-end loader has documented since the modular-CSS split that "Breeze will concatenate them in production anyway." It never did: the live site served six blocking stylesheets totaling ~100.4 KB, which is what Performance Lab's blocking-assets audit was flagging (source-verified thresholds: count > 10 OR bytes > 100,000, and the site tripped the SIZE half by 0.3%). The theme now owns its production delivery: one combined, lightly minified stylesheet built at runtime into uploads/sn-css/ (hash-named per source mtimes, so releases self-bust every cache layer), with a strict fail-open contract back to the per-file enqueues.

> **Why PATCH:** delivery/perf mechanics; zero visual or API change.

### New
- `inc/asset-combine.php`: `sn_css_combine_sources()` / `sn_css_minify()` / `sn_css_combine_signature()` / `sn_css_ensure_combined()`. Safe-transform minifier (comments, whitespace runs, brace/semicolon trims; never touches colons, commas, or calc() spacing). Relative-url() build guard fails open (moving CSS to uploads/ would break relative references; no current source has any). Stale hash siblings pruned on rebuild. Suite: `tests/asset-combine.php`.

### Changed
- `inc/assets-frontend.php`: enqueues the single combined `sn-styles` handle when available; the original four-file cascade remains as the verbatim fallback.
- `inc/command-palette.php`: palette CSS rides the combined file; its separate enqueue only fires in fallback mode.

## [10.21.5] - 2026-07-02 — Colophon: remove the federation line (ActivityPub adoption declined)

**Headline:** The owner reassessed the ActivityPub adoption before anything federated and declined it: no fediverse presence, no need for the colophon line advertising a handle. The line added in v10.21.3 (corrected in v10.21.4) is removed; the colophon facts list returns to its pre-federation shape. The site keeps every collateral improvement from the arc (analytics UA classifiers, hardening fixes live in the companion plugin).

> **Why PATCH:** single content-line removal in a template pattern.

### Removed
- Colophon facts list: the "Federation" line (`patterns/colophon.php`). ActivityPub adoption declined by owner decision, 2026-07-02.

## [10.21.4] - 2026-07-02 — Colophon: correct the fediverse handle

**Headline:** The federation line shipped in v10.21.3 said @juan@juanlentino.com, but the handle derives from the WP user nicename, which is `juanlentino`; webfinger 404'd for @juan. Owner decision: keep the real handle (zero-risk, domain-consistent) rather than DB-edit the nicename. The line now reads @juanlentino@juanlentino.com, verified resolving via webfinger.

> **Why PATCH:** one-line content correction in a template pattern.

### Fixed
- Colophon Federation line: @juan → @juanlentino (the actual webfinger-resolving handle) in `patterns/colophon.php`.

## [10.21.3] - 2026-07-02 — Colophon: federation line

**Headline:** The colophon's facts list gains one line: the site federates via ActivityPub, with the @juan@juanlentino.com handle for anyone who wants to follow from the fediverse. Sole reader-facing chrome of the ActivityPub adoption (by design: no footer icon, no follow widget).

> **Why PATCH:** single template-pattern content addition, no code or style change.

### New
- Colophon facts list: "Federation" line with the fediverse handle (`patterns/colophon.php`).

## [10.21.2] - 2026-07-01 — Footer meta-nav: icons instead of text labels

**Headline:** Owner request on the v10.21.1 footer line: icons instead of the Now / Accessibility / Colophon text labels, middot separators kept. Three mono stroke glyphs in the brutalist vocabulary — a clock for Now, the standard accessibility figure, and a pilcrow (a printer's mark — literally what a colophon is). Because "Now" and "Colophon" have no universal iconography, every link carries an aria-label and a hover title, the SVGs are decorative (aria-hidden, unfocusable), and each link keeps a 28px hit area (WCAG 2.5.8 target size). The markup rides a `wp:html` block (paragraph rich-text would strip inline SVG); icons draw with `currentColor` so the rust base + blood hover come from the same tokens as everything else.

> **Why PATCH:** a visual treatment change on an existing surface — no new capability.

### Changed
- Footer meta-nav: text labels → aria-labelled mono icons ([parts/footer.html](parts/footer.html), [assets/css/layout.css](assets/css/layout.css)); contract suite [tests/footer-meta-nav.php](tests/footer-meta-nav.php) rewritten for the icon form (accessible-name + hit-area + currentColor assertions).

## [10.21.1] - 2026-07-01 — Footer meta-nav treatment + /now updated-date plugin seam

**Headline:** Owner feedback on v10.21.0's footer: three separate bare text links (Now / Accessibility / Colophon) beside the copyright read as clutter, rendered in the loud global blood link color. Root cause found in passing: the footer paragraphs' `textColor:"steel"` is a phantom slug — `steel` exists nowhere in theme.json or CSS, so the color silently never applied and links fell through to the global link color. Fix: ONE quiet meta-nav paragraph (`.sn-footer__meta-nav`) with middot separators, colored with the real `rust` slug (links inherit it via core's has-text-color behavior; a scoped hover rule brings blood back deliberately, reduced-motion guarded). The © line gets the same rust correction. Also completes the /now plugin seam: `sn_now_updated()` is now filterable (`sn_now_updated`) so the companion plugin's upcoming Now editor can supply its live save-stamp alongside the `sn_now_sections` content it was already able to feed.

> **Why PATCH:** a visual-treatment fix + a filter-seam completion on an existing surface — no new user-visible capability.

### Fixed
- Footer: Now/Accessibility/Colophon folded into one middot-separated meta-nav line; phantom `steel` slug replaced with the real `rust` palette slug ([parts/footer.html](parts/footer.html), [assets/css/layout.css](assets/css/layout.css)); contract suite [tests/footer-meta-nav.php](tests/footer-meta-nav.php) guards against future phantom slugs.

### Improvements
- `sn_now_updated()` applies the `sn_now_updated` filter ([inc/now-data.php](inc/now-data.php)) — the plugin-editor seam, asserted in [tests/page-now.php](tests/page-now.php).

## [10.21.0] - 2026-07-01 — Interlinking batch: cited-by footer, /now, /accessibility, provenance backlink CSS

**Headline:** Four owner-approved surfaces in one release. (1) `[sn_cited_by]` ([inc/cited-by.php](inc/cited-by.php)) — the reverse of related-notes: up to 5 published notes whose body links to the current note, mounted in [templates/single.html](templates/single.html) beside `[sn_related_notes]`; boundary-aware `/notes/<slug>` matching (a link to `/notes/craft-two` is not a citation of `craft`, mirrored in the plugin's v7.4.0 unlinked-mentions check); empty renders no chrome. Pingbacks are the native answer but deliberately dead here (XML-RPC + pings_open killed by the plugin), so this bounded reverse query is the complement. (2) `/now` — an indie-web now page as a /uses-style virtual-route trio with an owner-editable data file ([inc/now-data.php](inc/now-data.php)) carrying its own `updated` date so staleness is honest. (3) `/accessibility` — a statement page (WCAG 2.1 AA target, genuinely shipped measures, honest limitations, /contact feedback channel). (4) `.sn-provenance-extended-by` — the sub-pillar launch-kit backlink class finally has a treatment (mono-uppercase rust lead-in, blood link, hairline top rule; the kit's inline font-size deliberately left in charge of the cascade), so the July launch needs no theme release. Both new pages force `status_header(200)` (WP-REF gotcha #40) with behavioral tests, join llms.txt and the footer chrome, and register `sn_seo_route_meta` entries.

> **Why MINOR:** four new user-visible surfaces (a shortcode footer, two pages, a styled component), no breaking change.

### New
- `[sn_cited_by]` shortcode + render_block bridge; `.sn-cited-by` grouped into the related-notes row idiom in [assets/css/components.css](assets/css/components.css) + hidden in print.
- `/now` virtual page: [inc/now-data.php](inc/now-data.php) (edit surface + `sn_now_sections` filter seam), [inc/page-now-template.php](inc/page-now-template.php), [inc/page-now-render.php](inc/page-now-render.php), [assets/css/now.css](assets/css/now.css).
- `/accessibility` virtual page: [inc/page-accessibility-template.php](inc/page-accessibility-template.php), [inc/page-accessibility-render.php](inc/page-accessibility-render.php), [assets/css/accessibility.css](assets/css/accessibility.css).
- Footer chrome links to /now + /accessibility; llms.txt lines for both; `sn_seo_route_meta` filters for both routes ([inc/seo-route-meta.php](inc/seo-route-meta.php)).
- `.sn-provenance-extended-by` treatment in [assets/css/components.css](assets/css/components.css) with a CSS-contract test.
- Test suites: [tests/cited-by.php](tests/cited-by.php), [tests/page-now.php](tests/page-now.php), [tests/page-accessibility.php](tests/page-accessibility.php), [tests/provenance-extended-by.php](tests/provenance-extended-by.php); assertions added to [tests/llms-txt.php](tests/llms-txt.php) + [tests/seo-route-meta.php](tests/seo-route-meta.php).

### Fixed
- llms.txt pointed Uses at `/uses/`, which 404s — the route lives at `/about/uses` ([inc/llms-txt.php](inc/llms-txt.php)).

## [10.20.0] - 2026-06-30 — Forced-colors / high-contrast accessibility (Batch B)

**Headline:** Adds a `@media (forced-colors: active)` block (Windows High Contrast Mode) plus a `@media (prefers-contrast: more)` block to [assets/css/base.css](assets/css/base.css) — the one genuinely-bare item from Batch B (source-verified: the theme already respects `prefers-reduced-motion` across CSS *and* JS, so that part was a non-gap). The brutalist black-on-white base is already maximal contrast, so the block is deliberately narrow: it pins the keyboard focus ring to the `Highlight` system colour (custom outline colours get remapped by the OS, which can weaken the ring) and preserves the reading-progress fill as a system colour (it conveys scroll position via colour and would otherwise flatten into the system background). This is the sanctioned high-contrast answer to the deliberately-omitted dark mode.

> **Why MINOR:** a new user-visible accessibility capability (respecting two OS contrast preferences). Purely additive CSS — no markup, API, or settings change; no required user action.

### New

- **`forced-colors` + `prefers-contrast` support** ([assets/css/base.css](assets/css/base.css)): under Windows High Contrast Mode, the focus ring on links/buttons/inputs/`.wp-block-button__link`/`summary` becomes a 3px `Highlight` outline and the `.sn-article-progress__fill` stays a system colour; under `prefers-contrast: more`, the focus ring widens to 3px. `tests/forced-colors.php` (5 assertions) guards that the block ships and targets the focus rings + progress fill with system colours.

## [10.19.0] - 2026-06-30 — Agent-readable discoverability files: llms.txt, GPC declaration, OpenSearch

**Headline:** Three new root-served virtual routes that make the site legible to AI answer engines, privacy agents, and browsers — the reader-facing half of Batch A's discoverability push (the plugin's v6.53.0 ships the matching robots.txt AI-crawler policy). `/llms.txt` (+ `/llms-full.txt`) follows the llmstxt.org convention, pointing LLM crawlers at the canonical pages and feeds; `/.well-known/gpc.json` is the server-side Global Privacy Control declaration that matches the beacon's existing client-side GPC bail; `/opensearch.xml` (+ a `rel="search"` head link) lets browsers register the site as a search provider over the owned `/notes/?s=` route. All three clone the established flush-free virtual-route idiom of `inc/humans-txt.php` / `inc/security-txt.php` — `template_redirect` priority 0, `status_header(200)` so a postless path is not served under a 404 (WORDPRESS-REFERENCE #40).

> **Why MINOR:** three new user-visible/agent-visible capabilities (three new served routes + an OpenSearch autodiscovery head tag). Purely additive — no API removed or renamed, no settings-schema change, no required user action.

### New

- **`/llms.txt` + `/llms-full.txt`** ([inc/llms-txt.php](inc/llms-txt.php)): the llmstxt.org AEO discoverability file. `/llms.txt` is a curated markdown index (About, Notes, Résumé, Music, Uses, Contact + RSS/JSON feeds); `/llms-full.txt` additionally appends a Notes section of recent published posts (title + permalink + summary, via `WP_Query`). Body built from `home_url()` + `get_bloginfo()` so it stays portable. `tests/llms-txt.php` (15 assertions): variant matcher, body structure, full-vs-basic divergence, and a `status_header(200)` behavioral assertion on the send handler.
- **`/.well-known/gpc.json`** ([inc/gpc-json.php](inc/gpc-json.php)): the Global Privacy Control support resource (`{"gpc":true,"lastUpdate":"…"}`) — the server-side declaration counterpart to the analytics beacon's client-side GPC bail. `lastUpdate` is a fixed date (not request-time) so the JSON is byte-stable and edge-cacheable. `tests/gpc-json.php` (9 assertions).
- **`/opensearch.xml` + `rel="search"` autodiscovery** ([inc/opensearch.php](inc/opensearch.php)): an OpenSearch Description Document targeting the owned `/notes/?s={searchTerms}` route, plus a `<link rel="search">` head tag so browsers can register the site as a search provider. The `{searchTerms}` token is preserved literal through `esc_url()` via an alnum sentinel. `tests/opensearch.php` (12 assertions).

## [10.18.0] - 2026-06-26 — Allow the companion plugin's scheduled block in the page editor

**Headline:** The companion plugin (signal-and-noise-tools v6.40.0) shipped a dynamic block, `signal-noise/scheduled`, a date-window content gate. But this theme curates the post/page inserter through an `allowed_block_types_all` allowlist (`inc/editor-block-palette.php`), and the new block was not on it. On this site that meant the block was invisible in the inserter and flagged not-allowed on paste, making the whole scheduled-content subsystem unusable. This adds the one block to the curated allowlist so it becomes insertable again. The firewall (Site Editor by name, post-less contexts, already-an-array bail) and every existing core/contact entry are untouched. Conservative by design: exactly one block added.

> **Why MINOR:** a new user-visible authoring capability (the Scheduled block becomes insertable in the page/post editor) that was previously curated out. Purely additive. No API removed or renamed, no settings-schema change, no required user action.

### New

- **`signal-noise/scheduled` joins the curated inserter allowlist** ([inc/editor-block-palette.php](inc/editor-block-palette.php)): a new `$companion` group (mirroring the existing `$contact` group) carries the companion plugin's date-window content-gate block into the post/page `allowed_block_types_all` list. Without it the block was hidden from the inserter and rejected on paste. `tests/editor-block-palette.php` gains an assertion (now 47 passing) that the curated post-editor allowlist includes the slug; the firewall cases (Site Editor, post-less contexts), the already-an-array bail, and the core-block coverage assertions are unchanged.

## [10.17.0] - 2026-06-25: Provenance sub-pillar subordinate-card design component

**Headline:** Adds the `.sn-prov-subcard` component to the brutalist card system: a smaller, rail-connected variant of a `.sn-prov-paper-card` that nests inside a parent pillar card on the `/provenance` hub to present a "sub-pillar" (a pillar-weight, talk-derived or applied essay with no paper of its own, decimal-numbered after its parent, e.g. № 01.1). The component carries the indent, blood left-rail, title-colour override, and secondary-link affordance; per-instance type sizing stays inline in the page markup, matching how the existing pillar cards are authored. It is dormant until a hub page references it. No template, post type, taxonomy, or query loop is involved. Adding a future child (01.2, 02.1) is a copy-the-markup job, by design.

> **Why MINOR:** new user-visible design capability (a card treatment the theme did not previously provide), purely additive. No API removed or renamed, no settings-schema change, no required user action.

### New

- **`.sn-prov-subcard` subordinate-card component** ([assets/css/components.css](assets/css/components.css)): an indent plus a 2px `blood` left-rail signalling the parent/child relationship, a `bone` title-colour override (beats theme.json's global link colour at 0,0,1, mirroring the `.sn-prov-paper-title` rationale) with `blood` on hover, a `.sn-prov-subcard-blurb` normal line-height, and a `.sn-prov-subcard-more` italic-rust "Read the essay →" affordance matching `.sn-prov-paper-longform`. A tablet/phone rule ([assets/css/responsive.css](assets/css/responsive.css), `max-width: 781px`) eases the indent back to the `--20` spacing step so the rail keeps breathing room at the 11px floor. The first instance (the № 01.1 sub-pillar "Honesty has to be the cheap option", nested under № 01 on `/provenance`) lives in the hub page in the database, not in this repo.

## [10.16.3] - 2026-06-23 — Updater /tags poll pins redirection => 0 (no PAT forwarding)

**Headline:** A post-ship security audit of v10.16.2 found the theme clean except for one defense-in-depth drift in the self-updater. The GitHub `/tags` poll attaches a `Bearer SNT_GITHUB_TOKEN` header on private-repo installs but did not cap redirects, so on a 3xx WordPress's HTTP layer would re-issue the request (with the same args, including that header) to whatever host the redirect named. `api.github.com/.../tags` returns `200` and never redirects, so there is no live exploit. This pins the request to a single hop so the token can never be forwarded off-host, matching the host-scoped download path (`sn_gh_theme_inject_token_header`) and the plugin's hardened outbound peers.

> **Why PATCH:** security hardening on an existing outbound call. Behaviour-preserving (the `/tags` endpoint does not redirect), no new capability, no API removed, no required user action.

### Fixed

- **Updater `/tags` poll pins `redirection => 0`** ([inc/wp-update-integration.php](inc/wp-update-integration.php)): the authenticated tag-fetch sets `redirection => 0` so the `Authorization: Bearer SNT_GITHUB_TOKEN` header can never be forwarded to a redirect target. `tests/updater-github-auth.php` adds a regression assertion (now 18 assertions) that the captured request args carry `redirection => 0`.

## [10.16.2] - 2026-06-23 — Harden two diagnostic abilities (subscriber-safe)

**Headline:** A non-AI abilities audit found the theme surface clean (no IDOR; the theme `signal-and-noise/` and plugin `signal-noise/` ability namespaces are distinct and complementary), but two read-gated theme abilities exposed a little more than they should over the WP 7.0 `/wp-abilities` run-path. `get-latest-theme-tag` let any read-capable subscriber pass `force_refresh:true` to drive unthrottled outbound GitHub API calls; it now honors `force_refresh` only for operators, while still returning the cached tag to everyone. And `get-page-notes-pillars` now withholds a pillar's `last_modified` date unless the resolved post is publicly viewable.

> **Why PATCH:** security hardening + contract fixes on existing abilities. No new capability, no API removed (the abilities stay readable; only the privileged `force_refresh` path tightened).

### Fixed

- **`get-latest-theme-tag` force_refresh is operator-gated** ([inc/abilities-diagnostics.php](inc/abilities-diagnostics.php)): the fresh-outbound-GitHub-call path now requires `manage_options`; a read-capable subscriber gets the cached tag instead of a way to hammer the GitHub API. Schema description updated to match.
- **`get-page-notes-pillars` viewability gate** ([inc/abilities-content.php](inc/abilities-content.php)): `last_modified` is emitted only when the resolved post is publicly viewable (defense in depth, parity with the v9.15.x sibling fixes), so a draft or private pillar never leaks its mtime.

### Changed

- **`get-theme-version` contract drift** ([inc/abilities-diagnostics.php](inc/abilities-diagnostics.php)): the description now documents the `supports_fse` output key (an alias of `is_block_theme`) it already returns.

## [10.16.1] - 2026-06-21 — Contact aliases assemble into a clickable mailto link (still scraper-safe)

**Headline:** v10.16.0 rendered the assembled `/contact` aliases as plain text. They are now assembled into a **clickable `mailto:` link** instead — but the link is built entirely client-side in the DOM (`assets/js/contact-aliases.js`), so the scraper protection is unchanged: the served HTML still contains only the split base64 `data-*` attributes + the `user [at] domain [dot] com` fallback. The contiguous `user@domain` string and the `mailto:` only ever exist at runtime, after JS runs — a non-JS bulk harvester (and Cloudflare's edge email scan) still sees nothing, and Cloudflare email obfuscation stays enabled. Restores the one-click "reach out" affordance without putting a harvestable address in the source.

### Changed
- **`assets/js/contact-aliases.js`** — on `DOMContentLoaded`, each `.sn-email` span's `[at]/[dot]` fallback is replaced with `<a href="mailto:user@domain">user@domain</a>` created via `document.createElement` (never written into markup). The link inherits the existing `.sn-prose-links a` blood→signal styling. With JS off (or malformed data) the readable `[at]/[dot]` fallback stays in place — just not clickable.

> **Why PATCH:** a refinement of the v10.16.0 contact-alias rendering (plain text → clickable), client-side only. No source-surface change (the template + `sn_email_markup()` output are byte-identical — still no `@`, no `mailto`, no contiguous address), no API/schema change, no required user action. `tests/contact-email.php` updated (47 assertions): the source leak-guards are unchanged; the JS-contract assertions now verify the runtime-built anchor + `mailto:` href.

## [10.16.0] - 2026-06-21 — Scraper-proof the /contact email aliases (client-side assembly)

**Headline:** The four `/contact` aliases (research@, press@, speaking@, role@) were plain `mailto:` links — a contiguous `user@domain` string sitting in the page source for any bulk email harvester to regex out. They are now assembled client-side: each address is split into user + domain, base64-encoded into `data-*` attributes, with a readable but non-harvestable `user [at] domain [dot] com` fallback as the visible text. A small enqueued script writes the clean address as plain text on `DOMContentLoaded` — no `mailto:`, no anchor (the no-link design is intentional). An email regex over the HTML, meta, OG, JSON-LD, or RSS now matches nothing for the four aliases. Cloudflare email obfuscation stays enabled as a second layer.

### New
- **`[sn_email user="…" domain="…"]` shortcode** (`inc/contact-email.php`) — renders one scraper-resistant alias: `<span class="sn-email" data-eu="<b64 user>" data-ed="<b64 domain>">user [at] domain [dot] com</span>`. `domain` defaults to `juanlentino.com`; a missing user renders nothing. A `render_block` bridge (mirroring `inc/related-notes.php`) guarantees resolution of the inline-in-paragraph token regardless of render path.
- **`assets/js/contact-aliases.js`** — vanilla IIFE, enqueued only on `/contact` (footer + deferred, `is_page('contact')`-gated, mirroring `sn_enqueue_discography`). Decodes the parts and writes `user@domain` via `textContent` (plain text, never an anchor); leaves the `[at]/[dot]` fallback in place if JS is off or the data is malformed.

### Changed
- **`templates/page-contact.html`** — the four alias `mailto:` anchors are replaced by `[sn_email]` tokens. The surrounding routing copy, sentence structure, and the parent/child (`/contact` → `/contact/personal`) routing are unchanged; the other links (`/provenance`, `panaceastud.io`) are untouched. (This reverses the v10.12.1 decision to make the aliases clickable, in favor of anti-scraping per request.)

### Security
- No contiguous `user@domain` and no `mailto:` for the four aliases appears in any rendered source surface; the parts are stored separately and only joined at runtime in JS. Defeats non-JS bulk harvesters; the filtered Proton aliases + Cloudflare email obfuscation (untouched) handle the residual JS-executing scraper. New `tests/contact-email.php` (46 assertions) is built around leak guards (no `@`, no contiguous domain, no `mailto`, no anchor) plus the enqueue gating + JS/template contracts; full sweep 51 suites / 0 failures; phpcs falsified-clean.

> **Why MINOR:** a new user-visible capability (the `[sn_email]` shortcode + client-assembled aliases) with no breaking change — no public API removed, no settings-schema migration, no required user action. Degrades to the readable `[at]/[dot]` form without JS.

## [10.15.1] - 2026-06-21 — Remove the /notes topics tag cloud

**Headline:** v10.15.0 added a browse-by-topic tag list to the `/notes` index on the assumption of a small, curated tag set. In practice every Note carries ~5 tags across ~17 Notes, so `hide_empty` returned **60+ terms** and the "Topics" row rendered as a massive tag cloud — visual noise that overwhelmed the page. Removed entirely; no replacement widget. Tags stay reachable from each Note's own frontmatter/closing links, and those still land on the branded tag archives shipped in v10.15.0.

### Removed
- **The `/notes` "Topics" tag list** (`inc/page-notes-render.php`) — the `.sn-notes-topics` nav + its CSS, and the now-dead `sn_notes_all_tags()` helper (and its two test assertions). The pinned "Start here" row, the dropped "Vol. 01" eyebrow, and the branded `/notes/tag/{slug}/` archives from v10.15.0 are all unchanged.

> **Why PATCH:** removes a just-shipped UI element that was a UX regression; no API change, no behavioural shift requiring user action. `sn_notes_all_tags()` was internal/unreleased-helper-grade (one caller, removed in the same change) — not a public-contract removal.

## [10.15.0] - 2026-06-21 — /notes: time → topic (drop "Vol. 01", pin Start-here, tag browsing + branded tag archives)

**Headline:** The owner added a "Start here" post and tags on every Note, shifting the `/notes` index from a time axis (a periodical with "Vol. 01") to a topic axis (an evergreen body you enter by Start-here or by subject). The page now matches that: the misleading "Vol. 01" eyebrow is gone, the stickied "Start here" note pins to the top of the index (standard WP sticky behavior the custom PHP renderer didn't provide on its own — a secondary `WP_Query` doesn't float stickies), a browse-by-topic tag list appears on the index, and `/notes/tag/{slug}/` archives now render through the catalog renderer instead of the generic `index.html` fallback. Theme-only, no JS, no build step.

### New
- **Browse-by-topic tag list** on the `/notes` index (`inc/page-notes-render.php`, `sn_notes_all_tags()`) — every non-empty `post_tag`, name-ascending, as mono-uppercase chips linking to its archive. Shown in browse + tag mode (hidden during free-text search); the active tag is flagged with `aria-current` and a solid bone fill so the row doubles as a tag switcher.
- **Branded tag archives** — `/notes/tag/{slug}/` now routes through the PHP catalog renderer (`sn_notes_is_tag_request()` joins the `template_redirect` / `template_include` short-circuits) with a `post_tag` `tax_query`, a `Notes — Tag · "{name}"` header + "All notes" clear link + count, a tag-aware empty state, tag-correct pagination (`sn_notes_pagination_base()`), and the document title `Notes — {tag} — {site}`. A non-existent tag slug fails the gate so WordPress still serves its 404 (we never force a bogus tag to 200); a real-but-empty tag archive is forced to 200 (WORDPRESS-REFERENCE gotcha #40).
- **Pinned "Start here" row** — the stickied note (`sn_notes_start_here_id()`, reads `get_option('sticky_posts')`) floats to the top of the index on page 1, rendered as an ordinary `.sn-notes-row` with a blood "Start here" label so the out-of-date-order position reads as intentional. Excluded from its chronological slot (`post__not_in`) so it never doubles; page 2+ doesn't repeat it; the corpus count includes it. Editorial control: stick a different post to move the pin, no code change.

### Changed
- **The `/notes` eyebrow drops "Vol. 01"** — `Index · {year}` in browse mode, `Topic · {name}` on a tag archive. The volume framing promised a periodical with discrete issues, which contradicts the evergreen, continuously-revised framing of the corpus.
- **`sn_notes_query_posts()`** gains `ignore_sticky_posts => true` (the sticky is floated explicitly, never by WP), a `post_tag` `tax_query` in tag mode, and a `post__not_in` for the pinned note in browse mode — all additive; existing query-shape contracts unchanged.

### Tests
- New `tests/notes-topic-reframe.php` (23 assertions, RED→GREEN): the sticky/tag/start-here helpers, the additive query branches, the `sn_notes_is_tag_request()` matcher (incl. the bogus-slug → no-short-circuit gate), and the tag document title. The four existing notes suites (`notes-pagination`, `notes-search`, `notes-redirect`, `notes-title-paged`) stay green — every new WP-function touch is `function_exists`-guarded so the standalone fixtures don't need new stubs. Full sweep: 50 suites, 0 failures; phpcs falsified (a real `EscapeOutput` violation is caught) before trusting clean.

> **Why MINOR:** new user-visible capabilities (tag browsing, branded tag archives, the pinned front-door row) with no breaking change — no public API removed/renamed, no settings-schema migration, no WP-floor change, no required user action. The pinned row and tag list degrade to nothing when there's no sticky / no tags.

## [10.14.2] - 2026-06-20 — Uniform Note prose width (drop the 68ch paragraph cap)

**Headline:** Body paragraphs on single Notes were capped at a `max-width: 68ch` reading measure while the title, headings, and rules used the full 760px content column. Because the constrained layout centres blocks, the narrower paragraphs sat **indented** from the full-width headings — the width mismatch that read as inconsistent indentation. Paragraphs now use the same 760px column, so the prose is uniform width throughout. (Trade-off: lines run wider — ~90ch in DM Mono — since the reading-measure cap is gone; deliberate, per request.)

### Changed

- **Body paragraphs use the full content column** ([assets/css/critical.css](assets/css/critical.css)): removed `max-width: 68ch` from `.single-post .wp-block-post-content > p`. Paragraphs fall back to the WP constrained-layout content-size (760px), matching the headings/title/rules — same left edge, same width. The drop cap and ragged-right alignment (v10.14.1) are unchanged.

> **Why PATCH:** a visual refinement aligning paragraph width to the column; no capability, settings/schema, or template-structure change. 49 suites green (1258 asserts). `style.css` + `readme.txt` Stable tag → 10.14.2.

## [10.14.1] - 2026-06-20 — Note typography: ragged-right body + de-duplicated tags

**Headline:** Two single-Note fixes. Body paragraphs are no longer justified — on the DM Mono body face, `text-align: justify` could only stretch inter-word spaces (every glyph is one width), so the slack collapsed into visible "rivers" and `hyphens: auto` chopped words mid-line (need-ed, journal-ist). Ragged-right (left-aligned) reads cleanly on a monospace measure. And tags, which rendered in both the top spec-row *and* the closing footer, now appear once — in the top spec-row only.

### Fixed

- **Body copy is ragged-right** ([assets/css/critical.css](assets/css/critical.css)): removed the desktop-only (`min-width: 1024px`) `text-align: justify` + `-webkit-hyphens` / `hyphens: auto` on single-note body paragraphs. The drop cap and the ~68ch measure are unchanged. Fixes the stretched word-spacing + mid-word hyphenation across every paragraph.
- **Tags de-duplicated** ([parts/post-closing.html](parts/post-closing.html)): removed the `wp:post-terms` "Tagged …" block from the closing footer — tags already render in the top spec-row ([parts/post-frontmatter.html](parts/post-frontmatter.html), v9.3.0). The footer keeps its prev/next nav + share. The orphaned `.sn-post-closing__tags` CSS rule was removed too.

> **Why PATCH:** visual refinement + a redundant-render fix; no new capability, no settings/schema change, no template-structure break (the closing part stays dynamic via the prev/next `post-navigation-link`). [tests/patterns-registry.php](tests/patterns-registry.php) Test 3 updated — the closing part's dynamic-block assertion now accepts `post-navigation-link`; frontmatter tag rendering is unchanged. 49 suites green (1258 asserts).

## [10.14.0] - 2026-06-19 — Field Core Web Vitals beacon (real-user LCP/INP/CLS)

**Headline:** The beacon now measures **real-user (field) Core Web Vitals** — LCP, INP, CLS — and reports them to the first-party collector, so the dashboard shows what actual visitors experience (and what Google ranks on via CrUX), not just the synthetic Lighthouse lab score. Lever 4 (final) of the CF-analytics-headroom program; the plugin half (worker v1.8.0 capture + a "Core Web Vitals (field)" panel) ships alongside.

### New

- **Vendored `web-vitals` v4.2.4** ([assets/js/web-vitals.iife.js](assets/js/web-vitals.iife.js)) — Google's battle-tested CWV library (Apache-2.0), **self-hosted** (no CDN), ~2.6 KB gzipped, with a provenance header. Enqueued deferred + in-footer as a **dependency of the beacon** so `window.webVitals` is defined before the beacon's CWV section runs.
- **Field-CWV section in the beacon** ([assets/js/sn-beacon.js](assets/js/sn-beacon.js)) — wires `onLCP`/`onINP`/`onCLS`; each metric posts its own tiny event as the library finalizes it (on interaction / page-hide): `vl`=LCP, `vi`=INP, `vc`=CLS (CLS sent ×1000 as an integer). Guarded — a no-op if the library is absent. Async, no render-blocking; one `sendBeacon` per metric on page lifecycle.

> **Why MINOR:** a new user-visible analytics capability (field CWV) with one new self-hosted asset; no template/markup change, no breaking change, and it honors the existing DNT/GPC privacy gate (CWV beacons are suppressed for opted-out visitors like every other event). Cost is small + async (the perf-conscious choice the owner approved). RED→GREEN: [tests/beacon.php](tests/beacon.php) (+8: web-vitals enqueue + dep wiring, the `window.webVitals` guard, `onLCP/onINP/onCLS`, the `vl/vi/vc` events, vendored-file provenance). Theme suites green; JS syntax-checked.

## [10.13.4] - 2026-06-19 — Literal em-dashes → straight hyphens + X footer link

**Headline:** Completes the v10.13.2 "smart quotes and em-dashes" fix. That release stopped WordPress from *auto-creating* curly glyphs, but the bio/colophon/humans.txt prose still had **literal em-dashes typed into the source** — `wptexturize` never touched those. Replaced them with straight hyphens across all rendered prose. Also adds the X profile to the footer.

### Fixed

- **Literal em-dashes are now straight hyphens** in rendered prose — `/about`, `/services`, `/music`, `/colophon`, `/humans.txt`, and the logo `aria-label` ([templates/page-about.html](templates/page-about.html), [page-services.html](templates/page-services.html), [page-music.html](templates/page-music.html), [patterns/colophon.php](patterns/colophon.php), [inc/humans-txt.php](inc/humans-txt.php), [parts/header.html](parts/header.html)). Docblocks/PHP comments were intentionally left untouched (they don't render), and the eyebrow middots (`·`) are preserved — they're intentional design separators, not dashes.

### Added

- **X profile in the footer** ([parts/footer.html](parts/footer.html)) — a Gutenberg `wp:social-link` `x` block at `x.com/juan_lentino`, so the row reads Spotify · LinkedIn · Instagram · X · RSS. Mirrored into the humans.txt PROFILES list to keep the two surfaces in lockstep. X was previously schema-only (`twitter:site` / `sameAs`); it's now surfaced visibly.

> **Why PATCH:** the em-dash sweep is the completion of the v10.13.2 typography fix (the literal-glyph half `wptexturize` couldn't reach), and the X icon is a single `social-link` block added to the existing row — no new surface, no template restructure. Bundled into one patch per the owner's call. Locked by [tests/humans-txt.php](tests/humans-txt.php) (X profile present + no `U+2014` in the body). 47 suites green (1250 assertions), WPCS clean. The `/about` / `/colophon` rendering can't be exercised in a local preview (FSE) — verify live after install.

## [10.13.3] - 2026-06-19 — /contact/personal: drop the inaccurate "family at home" line

**Headline:** The `/contact/personal` "why the answer is no" paragraph asserted "there's a family at home" — factually wrong (the owner is single, no kids). Removed. The studio, the solo infrastructure projects, and the MBA already carry the structural reason; the line added nothing true.

### Fixed

- **Removed the false "family at home" clause** ([inc/page-personal-render.php](inc/page-personal-render.php)). Was inherited from the original v10.12.0 copy and carried through the v10.13.1 rewrite unchallenged. The paragraph now reads "…projects alone. I'm finishing an MBA in August 2026. After all of that, the week is already spent." — accurate, same rhythm.

> **Why PATCH:** a one-clause factual copy correction. It would normally be a "content edit doesn't bump" case, but the theme ships through the version-gated updater, so a PATCH is the only way to land it on the live site. No markup/logic change; the page-personal contract test still passes unchanged (no asserted phrase touched). 47 suites green, WPCS clean.

## [10.13.2] - 2026-06-19 — Straight quotes (disable wptexturize)

**Headline:** WordPress's `wptexturize()` was auto-converting the straight quotes and dashes the source actually contains into curly "smart" quotes + en/em-dashes on every render — a typographic accident in a DM-Mono / brutalist setting (and a foot-gun for byte-level tooling). Disabled site-wide with the one canonical filter.

### Fixed

- **Straight quotes everywhere** ([inc/disable-smart-quotes.php](inc/disable-smart-quotes.php)). `add_filter( 'run_wptexturize', '__return_false' )` — one line, the canonical gate, no per-route plumbing, fully reversible. Source is authored with straight quotes; they now render verbatim. Intentional literal design separators (the eyebrow middot `·`) are unaffected — they're literal characters in the templates, not texturize output.

> **Why PATCH:** a rendering fix, not a new capability — neutralises an unwanted core auto-conversion. No structural/layout change; the only visible delta is `"` `'` `--` rendering as themselves instead of `"` `'` `—`. Locked by [tests/disable-smart-quotes.php](tests/disable-smart-quotes.php) (the `run_wptexturize` gate resolves to false for any input). 47 suites green (1248 assertions), WPCS clean.

## [10.13.1] - 2026-06-19 — 404 recovery list + contact-page link fixes

**Headline:** Three front-end fixes. The 404 page's "Recent notes" recovery list was rendering each note title at the full notes-index heading step (huge red Bebas) — fixed to a compact, scannable list. The `/contact` link to the personal page now reads as a worded link, not a pasted URL. And `/contact/personal` gains the two essays it was paraphrasing without crediting — Paul Graham's "Maker's Schedule" and Ryan Holiday's piece on "just a little" of your time — with the closest-to-Casey lines reworked.

### Fixed

- **404 "Recent notes" list is compact again** ([assets/css/components.css](assets/css/components.css)). The `[sn_404_suggestions]` shortcode reuses `.sn-notes-row` markup, but the compact title rule is scoped to `.sn-related-notes`, so on the 404 the `<h2>` titles fell back to the full heading step (the oversized red headings). Added a `.sn-404-suggestions .sn-notes-row` treatment: a hairline-ruled list with a mono date spec and a small (1.2rem) title — de-emphasised recovery content, not a second hero.
- **`/contact` → personal-page link is a worded link** ([templates/page-contact.html](templates/page-contact.html)). "read **the next page** before reaching out" instead of pasting `juanlentino.com/contact/personal` as the anchor text.

### Improvements

- **`/contact/personal` cites its sources** ([inc/page-personal-render.php](inc/page-personal-render.php)). The page borrows Casey Neistat's structure (credited in the footnote) but was missing the two essays he points to. Added a paragraph linking **Paul Graham — Maker's Schedule** and **Ryan Holiday — "just a little of your time"** (in original wording, not Casey's), and reworked the para-4 closing that most echoed his phrasing. The contact friction is unchanged — still one contact channel (LinkedIn), no email; the two new links are outbound references, not ways to reach me.

> **Why PATCH:** visual/content fixes and a small reference addition — no new capability, no template restructure, no schema change. The link contract test ([tests/page-personal.php](tests/page-personal.php)) updated 1 → 3 anchors (LinkedIn + 2 references) with a `mailto:`-absent assertion so the no-email friction stays locked. 46 suites green (1245 assertions), WPCS clean. **Note:** the 404 + personal-page rendering can't be exercised in a local preview (FSE + virtual route) — verify on the live site after install.

## [10.13.0] - 2026-06-19 — Machine-readable full pass (theme half)

**Headline:** The theme half of a coordinated machine-readable hardening pass (plugin **v6.24.0** is the other half). Adds a `security.txt`, brings the JSON Feed up to par with the RSS enrichment, and — driven by a live audit — fixes the two routes that were shipping with no description and (for `/about/uses`) no structured data at all, by answering the plugin's new SEO route filters.

### New

- **`/.well-known/security.txt` (RFC 9116)** ([inc/security-txt.php](inc/security-txt.php)). A flat security-policy file (and the legacy top-level `/security.txt` some scanners probe), served via the same flush-free virtual-route mechanism as `/humans.txt` (`template_redirect` priority 0 + `status_header(200)`). The mandatory `Expires` field is derived ~1 year ahead of request time, so it never silently expires and needs zero recurring maintenance; `Contact`/`Canonical` are built from `home_url()`.
- **Route SEO descriptions + `/about/uses` meta** ([inc/seo-route-meta.php](inc/seo-route-meta.php)). Answers the plugin's new `sn_seo_singular_description` filter with descriptions for the template-driven Pages (`/about`, `/contact`, `/colophon`, `/music`) — which carry no excerpt and previously shipped with **no description** — and the `sn_seo_route_meta` filter with full title/description/url/breadcrumb for the postless `/about/uses` route, which previously emitted **zero** og/canonical/JSON-LD. Copy is filterable via `sn_seo_page_descriptions`.

### Improvements

- **JSON Feed parity with the RSS enrichment** ([inc/feed-json.php](inc/feed-json.php)): a feed-level `authors` block (name + `/about/` url + site-icon avatar, applies to every item per JSON Feed 1.1) and a per-item `image` from the featured thumbnail, so readers like NetNewsWire / Reeder / Feedbin render thumbnails. Both omitted when absent.

> **Why MINOR:** new user-visible capabilities (a new well-known file + richer feed + route descriptions); no breaking change, no template restructure. The route-meta module is inert until the companion plugin v6.24.0 (which defines the filters) is installed, and every addition is omitted when its data is absent. RED→GREEN: new [tests/security-txt.php](tests/security-txt.php) (17) + [tests/seo-route-meta.php](tests/seo-route-meta.php) (17), and [tests/feed-json.php](tests/feed-json.php) (+7: authors + per-item image). 46 suites green (1242 assertions), WPCS falsified-clean.
>
> **Held (considered, not shipped):** an h-card microformat on `/about`. It touches the brutalist front-end markup for near-zero consumer benefit (webmention-receiving is an intentional permanent anti-feature, so the only consumer is external IndieWeb parsers reading the site as a source) — the audit's own recommendation was "lean skip."

## [10.12.3] - 2026-06-19 — Contact: make the inline links actually look like links

**Headline:** The v10.12.1 links were present and clickable but rendered as **plain black text** — no colour, no underline. Root cause: WordPress core emits `:where(p.has-text-color:not(.has-link-color)) a{color:inherit}`, which (same specificity as the theme's `elements.link` rule but emitted later) forces inline links inside any colour-bearing paragraph to inherit the body text colour. The contact + personal body paragraphs have `has-text-color` (from `textColor:"bone"`) but no `has-link-color`, so every link went black. Fixed with a scoped class.

### Fixed

- **Inline content links now show the theme's `blood` link colour.** Added `.sn-prose-links a` to [assets/css/components.css](assets/css/components.css) (specificity 0,1,1 — beats core's 0,0,1 regardless of source order; no `!important`): `blood` at rest, `signal` + underline on hover/focus, mirroring the existing `.sn-404-browse` / `.sn-prov-*` link idiom. The class is applied to the `/contact` routing group ([templates/page-contact.html](templates/page-contact.html)) and the `/contact/personal` body group ([inc/page-personal-render.php](inc/page-personal-render.php)). `blood` on `bone` body text is a ~4.2:1 luminance ratio, so the links are distinguishable by colour alone (WCAG 1.4.1) even before the hover underline. Contract locked in [tests/page-personal.php](tests/page-personal.php) (class present + CSS rule present).

> **Why PATCH:** a CSS/markup fix to the just-shipped contact pages — no new capability, no schema/API/structural change. Diagnosed from the live emitted CSS, not guessed. Note (latent, not fixed here): the `Panacea` link on `/about` has the same `has-text-color`-without-`has-link-color` pattern and would benefit from the same class — deferred as out of scope. Bumps because the version-gated self-updater is the only path to live.

## [10.12.2] - 2026-06-19 — Remove the dead Contact Form 7 asset code

**Headline:** v10.12.0 turned `/contact` into a routing directory and deliberately left the CF7 + Flamingo plugins active-but-unused, to be removed once the new pages were verified live. Both render correctly (confirmed on `juanlentino.com/contact` and `/contact/personal` — no form, no 404), so this release deletes the now-dead CF7 plumbing from the theme. A grep of **both** the theme and the `signal-and-noise-tools` plugin confirmed Contact Form 7 was the **only** consumer of every removed hook: nothing else enqueues `contact-form-7` / `wpcf7`, and nothing else loads the Cloudflare Turnstile SDK.

### Removed

- **`assets/css/forms.css`** — 225 lines of exclusively `.wpcf7-*` / `.wp-block-contact-form-7-*` / `.cf-turnstile` styling. The redesigned `/contact` has no `<form>` (linked text, never a form), so the file styled nothing. Removed wholesale, along with its `sn-forms` enqueue in [inc/assets-frontend.php](inc/assets-frontend.php) and its entry in the `add_editor_style()` list in [inc/setup.php](inc/setup.php).
- **The `is_page('contact')` CF7 asset gate** in [inc/assets-frontend.php](inc/assets-frontend.php) — it dequeued `contact-form-7` / `wpcf7-recaptcha` styles + scripts on non-contact pages. Dead: no plugin enqueues those handles anymore.
- **`contact-form-7` from the `style_loader_tag` defer list** — the `media='print'` onload deferral now applies only to the handles that still exist (`wp-block-library`, `trp-language-switcher`).
- **The Cloudflare Turnstile strip filters** in [inc/frontend-filters.php](inc/frontend-filters.php) — a `script_loader_tag` filter that blanked the Turnstile `<script>` on non-contact pages, plus a `wp_resource_hints` filter that dropped its dns-prefetch hint. CF7 was the sole Turnstile consumer site-wide, so both matched nothing; worse, they whitelisted `/contact`, a page that no longer has a form.

### Changed

- **The CSS cascade is now four stylesheets, not five.** `sn-responsive` now depends on `sn-components` (was `sn-forms`), preserving load order base → layout → components → responsive. No visual change — `forms.css` only ever styled CF7 markup that no longer renders.

### Tests

- **[tests/cf7-removal.php](tests/cf7-removal.php)** (new, +13) — a behavioral regression guard that **runs** the real `wp_enqueue_scripts` and `style_loader_tag` hook closures from `inc/assets-frontend.php` (captured via stubbed `add_action` / `add_filter`, not string-matched) and asserts: `forms.css` is gone, `sn-forms` is not enqueued, `sn-responsive` depends on `sn-components`, nothing dequeues `contact-form-7`, `contact-form-7` is no longer deferred while `wp-block-library` still is, and `inc/setup.php` + `inc/frontend-filters.php` carry no CF7/Turnstile references. RED → GREEN (11 of 13 assertions failed before the removal).

### Note

- **The CF7 + Flamingo plugins themselves are deactivated and deleted on the live site as a separate wp-admin action** — plugins are not part of this repo, so that step is not a code change. The dormant `[contact-form-7 …]` shortcode left in the `/contact` DB page body is inert (the template no longer outputs `wp:post-content`, so it never renders) and may be cleaned in the same wp-admin pass.

> **Why PATCH:** pure dead-code removal — no new capability, no public-API removal (the deleted hooks are anonymous closures, not exported functions), no settings-schema change, no WP-floor change, no user-visible behaviour change (the removed CSS and filters only ever affected CF7 markup that no longer renders). Locked RED → GREEN by [tests/cf7-removal.php](tests/cf7-removal.php); full standalone sweep 46 suites / 1198 assertions green; WPCS falsified-clean.

## [10.12.1] - 2026-06-19 — Contact: make the routing destinations clickable

**Headline:** Reverses the v10.12.0 plain-text-only choice on `/contact` — at the owner's call, the destinations are now **hyperlinks**: the five inquiry emails are `mailto:` links, and the three URLs (`juanlentino.com/provenance`, `panaceastud.io`, `juanlentino.com/contact/personal`) are links. Usability over the anti-scraper friction; Proton's filtering remains the spam backstop.

### Changed

- **`/contact` destinations are linked.** [templates/page-contact.html](templates/page-contact.html): the four inquiry emails (`research@`, `press@`, `speaking@`, `role@`) become `mailto:` links; `juanlentino.com/provenance` → `/provenance` and `juanlentino.com/contact/personal` → `/contact/personal` (internal); `panaceastud.io` → external (`target="_blank" rel="noopener"`). Visible text is unchanged (the addresses/URLs read exactly as before) — only the markup gained `<a>` wrappers. Copy, masthead, and the availability line are untouched. The Personal page already carried its single LinkedIn link.

> **Why PATCH:** a markup refinement of the just-shipped v10.12.0 contact page — no new capability, no settings-schema or public-API change, no structural/layout change. It bumps because the version-gated self-updater is the only path to the live site (same rationale as v10.11.1). Note: `mailto:` links re-expose the addresses to scrapers, which the v10.12.0 plain-text version deliberately avoided — an accepted trade for usability.

## [10.12.0] - 2026-06-18 — Contact: a two-page Proton-alias routing system (replaces the CF7 form)

**Headline:** `/contact` is no longer a Contact Form 7 form — it's a **plain-text routing directory**. Each kind of inquiry (research, press, speaking, studio, recruiting) is pointed at a dedicated filtered Proton alias, written as plain text with **zero hyperlinks** so the address has to be typed: the friction is the spam filter, and Proton's own filtering is the backstop. A new child page, **`/contact/personal`**, holds the honest "your synchronous-time ask is a no, and here's why" note for everything else — its structure borrowed, with credit, from Casey Neistat's contact page.

### New

- **`/contact/personal` page** — a postless virtual route (child of `/contact`), built the same way `/about/uses` is: [inc/page-personal-template.php](inc/page-personal-template.php) matches `REQUEST_URI` at `template_redirect` priority 0 (beats `redirect_canonical`, no rewrite flush) and [inc/page-personal-render.php](inc/page-personal-render.php) emits the document, forcing HTTP 200 for the postless path (WORDPRESS-REFERENCE gotcha #40). The body is authored as block markup in `sn_personal_content_blocks()` (the edit surface) and rendered through `do_blocks`, so it reuses the theme's type/colour/spacing presets with no bespoke CSS. Exactly one hyperlink in the body — **LinkedIn** — the only asynchronous channel offered; everything else is a "no". Wired in [functions.php](functions.php); contract locked by [tests/page-personal.php](tests/page-personal.php) (route matcher, forced-200, exactly-one-link, footnote presets, verbatim copy).
- **Casey Neistat credit.** A de-emphasised footnote on `/contact/personal` ("The structure here is borrowed from Casey Neistat's contact page. He worked out the honest version of this first."), set in the theme's smallest font-size preset (`small`) + muted colour preset (`rust`) — named presets, no inline values — separated from the body by a spacer.

### Changed

- **`/contact` is now a routing directory, not a form.** [templates/page-contact.html](templates/page-contact.html) drops the `wp:post-content` form area (which rendered the CF7 shortcode) and the social-links "Connect" block, and replaces them with six plain-text paragraphs that route each inquiry type to its Proton alias. The masthead (eyebrow + `CONTACT` + availability line) is unchanged. Emails and URLs are rendered as plain text — never auto-linked (the theme hooks no `make_clickable`, and the one `the_content` filter is gated to single posts, so a heading-less page is exempt). Product names are kept generic (Panacea Studio, "music infrastructure projects") for compartmentalisation. No email is configured in WordPress — routing is entirely Proton-side.

### Removed

- **The Contact Form 7 form no longer renders on `/contact`.** Because the template no longer outputs `wp:post-content`, the CF7 shortcode in the page's DB body is dormant (never rendered) — the "archive" without a DB edit. The CF7 + Flamingo **plugins are intentionally left active and untouched**; their removal (and the now-dead `is_page('contact')` CF7 asset gate in [inc/assets-frontend.php](inc/assets-frontend.php)) is deferred to a separate session, after this ships and is verified live.

> **Why MINOR:** a new user-visible page (`/contact/personal`) plus a redesigned `/contact` — new capability, no public-API removal and no settings-schema change. The `/contact` URL is unchanged, so inbound links (nav, sitemap) keep resolving. RED→GREEN via [tests/page-personal.php](tests/page-personal.php). All standalone suites green, WPCS falsified-clean.

## [10.11.2] - 2026-06-17 — Notes hero accuracy + two defense-in-depth hardenings

**Headline:** Three small fixes from a post-ship audit: the `/notes` hero now reports the **whole-corpus** entry count on every page (it read the per-page slice on page 2+), the self-updater's `?force-check` cache-bust is now **capability-gated**, and the colophon's git-ref reader rejects path traversal in a tampered `.git/HEAD`.

### Fixed

- **`/notes` hero "N entries" is now the corpus total on every page.** The hero meta read `$query->post_count` (this page's ≤per-page slice), so on page 2+ it showed e.g. "8 entries" instead of the real total; the section count below already used `found_posts`. Extracted a testable `sn_notes_hero_stats()` using `found_posts`; "Last updated" now shows only when the first post is genuinely the newest (page 1), rather than a wrong date on deeper pages. [inc/page-notes-render.php](inc/page-notes-render.php).

### Security

- **`?force-check` update-check cache-bust is capability-gated.** The `pre_set_site_transient_update_themes` handler honored a raw `?force-check=` query param, so any logged-in user — or a CSRF `<img>` to an admin URL — could force live GitHub API calls and spend the rate-limit budget. The query-string path now requires `current_user_can('update_themes')` (extracted as `sn_gh_theme_force_refresh_requested()`); WP's own capability-gated "Check Again" (`WP_FORCE_UPDATE_CHECK`) path is unchanged. [inc/wp-update-integration.php](inc/wp-update-integration.php).
- **Colophon git-ref reader rejects path traversal.** `sn_colophon_resolve_ref()` used the `.git/HEAD` ref string as a filesystem path segment; a tampered `HEAD` (e.g. `ref: refs/heads/../../…`) could point a read outside `refs/`. Now validated against `^refs/[A-Za-z0-9._/-]+$` with an explicit `..` bar, failing closed. Exploiting it requires write access to `.git` (server compromise) and the output is only a 7-char hex SHA, so this is defense-in-depth. [inc/colophon-meta.php](inc/colophon-meta.php).

> **Why PATCH:** one correctness fix + two defense-in-depth hardenings; no new capability, no settings-schema or public-API change. Each is locked by a failing-first test (RED→GREEN): `tests/notes-pagination.php` (+6, corpus count + page-2 suppression), `tests/updater-github-auth.php` (+5, the capability gate), `tests/colophon-meta.php` (+3, a planted-file traversal that the guard fails closed). 44 suites green, WPCS falsified-clean.

## [10.11.1] - 2026-06-17 — Colophon: credit Claude; fix the stale readme Stable tag

**Headline:** The `/colophon` page now names **Claude (Anthropic)** in its tooling list, as a plain factual credit in keeping with the colophon's "stack, type, tooling, build — anti-self-promotion by design" ethos. Also corrects the `readme.txt` Stable tag, which had drifted a version behind `style.css`.

### New

- **AI-assistance line on the Colophon.** Added one list item to [patterns/colophon.php](patterns/colophon.php): "AI assistance — engineered with Claude (Anthropic) as a pair-programmer." Build-scoped (it sits in the tooling/build list, so it credits the site's engineering, not the music), un-versioned (durable as the model line moves; the live `[sn_build]` line carries the time-specific snapshot), and dry to match the existing entries. Renders live — `templates/page-colophon.html` references the pattern by slug (`wp:pattern`), so the file is the source of truth.

### Fixed

- **`readme.txt` Stable tag drift.** It read `10.10.1` while `style.css` was `10.11.0` (the v10.11.0 / #24 bump updated the header but missed the readme mirror). Both are now `10.11.1`. readme.txt states the Stable tag mirrors the `Version:` header — this restores that invariant.

> **Why PATCH:** content/copy refinement of a shipped theme pattern (delivered via the version-gated self-updater, so it must bump) + a docs-mirror fix. No new capability, no template/markup-structure change, no settings change. The colophon structural tests ([tests/colophon-template.php](tests/colophon-template.php)) stay green (they assert structure + the pattern-slug round-trip, not copy).

## [10.11.0] - 2026-06-17 — Self-updater: authenticated downloads (private-repo capable)

**Headline:** The WP-native self-updater can now install from a **private** GitHub repo. When `SNT_GITHUB_TOKEN` is set, the version check *and* the package download both authenticate, so wp-admin → Dashboard → Updates keeps working if this repo goes private. With no token (public repo), behaviour is unchanged.

### New

- **Authenticated update downloads.** [inc/wp-update-integration.php](inc/wp-update-integration.php) now builds the package URL from the GitHub **API zipball** endpoint when `SNT_GITHUB_TOKEN` is defined (`sn_gh_theme_package_url()`), and an `upgrader_pre_download` handler (`sn_gh_theme_authenticated_download()`) performs the download with a `Bearer` token. The token is scoped to `api.github.com` only (`sn_gh_theme_inject_token_header()`) — **never** forwarded to the pre-signed `codeload.github.com` host the zipball 302-redirects to (no credential leak on redirect). Without a token the updater falls back to the public archive URL (unchanged), and the existing `upgrader_source_selection` rename is dir-name-agnostic so the install lands at the correct slug either way. Guarded by new assertions in [tests/updater-github-auth.php](tests/updater-github-auth.php) (incl. the codeload no-leak property + foreign-package non-interception).

> **Why MINOR:** a new user-visible capability — the site can self-update from a private repo. No settings-schema change, no public-API removal; public-repo behaviour is unchanged when no token is set. The private path requires a fine-grained `SNT_GITHUB_TOKEN` (Contents: read) in `wp-config.php`.

## [10.10.1] - 2026-06-17 — Availability line alignment fix

**Headline:** The `/contact` + `/services` availability status line (the red-dot "Available for…" line) now sits inside the hero column under the intro, where it belongs — it had been falling into the far-left page gutter.

### Fixed

- **Availability line rendered in the page gutter instead of the hero column.** `[sn_availability]` is placed inside the constrained 760px Contact/Services hero group, which centers its children with `margin-inline: auto`. `.sn-availability` was `display: inline-flex`, and auto inline-margins are a no-op on inline-level boxes — so it was never centered and fell to the full-width group's left edge (the gutter) while keeping its in-flow vertical position. Switched to block-level `display: flex` so the constrained layout centers it and it aligns with the eyebrow / heading / intro. CSS-only; no markup or shortcode change. Guarded by a new layout-regression assertion (tests/availability.php T7). [assets/css/components.css](assets/css/components.css)

> **Why PATCH:** a one-property CSS fix to an existing feature's layout — no new capability, no markup/API/settings change.

## [10.10.0] - 2026-06-15 — /about/uses gear page (D6)

**Headline:** A new `/about/uses` page — the indie-web "what I use" list, nested under the About (bio) page — rendering the studio hardware, instruments, and software behind the work, grouped and server-rendered in the brutalist row idiom.

### New

- **`/about/uses` virtual route (D6).** A postless `template_redirect` route nested under the About/bio page (mirrors `/index` + `/humans.txt`: `status_header(200)` for the gotcha-#40 404 inheritance, `template_include` fallback, custom document title, route-scoped stylesheet; priority 0 also beats `redirect_canonical` so the nested path isn't stripped to `/about`). Renders the kit grouped by category — Interface & control, Microphones, Headphones, Instruments & keys, Software & licensing — each item a name + optional quiet qualifier. No JS, no external links (link-rot-free), every field `esc_html`'d. [inc/page-uses-template.php](inc/page-uses-template.php), [inc/page-uses-render.php](inc/page-uses-render.php), [assets/css/uses.css](assets/css/uses.css)
- **Editable gear data + `sn_uses_groups` filter seam.** The list lives in a plain, grouped PHP array in [inc/uses-data.php](inc/uses-data.php) (the edit surface — add a piece of gear in one line). `sn_uses_groups()` applies the `sn_uses_groups` filter and normalizes the result (prunes label-less/empty groups + nameless items; accepts bare-string items), so the companion plugin — or any `add_filter` — can supply the list later without a theme change (the deferred-admin-UI seam). Standalone-safe: an empty list renders the page with no sections, no fatal.

> **Why MINOR:** a new additive front-end page; no removal, no breaking change, no user action. No plugin dependency (theme-only).

## [10.9.0] - 2026-06-15 — Availability line + WebSub feed push (D5 + D4, theme half)

**Headline:** The `/contact` and `/services` heroes now show an owner-edited **availability line** ("Available for select mixing work") when set, and the RSS2 + Atom feeds advertise a **WebSub hub** so feed readers can subscribe for instant push. Pairs with plugin v6.17.0 (the setting + the hub ping).

### New

- **`[sn_availability]` line (D5).** A new shortcode placed in the `/contact` and `/services` page heroes that surfaces the owner-edited availability string from the companion plugin's `sn_settings` (subtree `identity.availability`, set on Site → Identity & SEO). A small uppercase status line with a leading signal dot — mirrors the `.sn-music-featured__label` idiom. Standalone-safe: plugin/option absent or the string empty → renders nothing (no empty box); the value is `esc_html()`'d at output. [inc/availability.php](inc/availability.php), [assets/css/components.css](assets/css/components.css), [templates/page-contact.html](templates/page-contact.html), [templates/page-services.html](templates/page-services.html)
- **WebSub feed advertisement (D4).** The RSS2 + Atom feeds now advertise a hub via `<atom:link rel="hub">` / `<link rel="hub">`, the discovery half of WebSub (PubSubHubbub). Default hub is the public `https://pubsubhubbub.appspot.com/`, overridable via the `sn_websub_hub` filter — the same filter (and identical default literal) the plugin reads to ping that hub, keeping the advertised and pinged hub in sync. Filtering the hub to `''` advertises nothing (opt-out); the href is `esc_url()`'d. [inc/feed-websub.php](inc/feed-websub.php)

> **Why MINOR:** two additive front-end capabilities; no template/markup removal, no breaking change, no user action required. Both degrade to nothing when unconfigured. Theme and plugin use distinct function/const names (sharing only the `sn_websub_hub` filter tag) so both can load in one runtime without a redeclare.

## [10.8.0] - 2026-06-15 — Liner notes: tracklists, per-track credits & cookieless previews (B1)

**Headline:** Each `/music` release now expands to a liner-notes panel — the tracklist, the per-track role credits, and a 30-second audio preview per track played from a cookieless native `<audio>` (no Spotify embed, no cookie). Pairs with plugin v6.14.0, which carries the per-track data.

### New

- **Expandable liner notes on each discography card (B1).** A native `<details>` disclosure (keyboard-accessible, works with JS off) reveals the tracklist with each track's own role credits and, where a preview exists, a play button. Tracks come from the entry's `tracks[]` (plugin v6.14.0+); a release with no track data simply shows no panel, so old/un-synced entries and a plugin-absent install degrade cleanly. [inc/discography-render.php](inc/discography-render.php) (`sn_discography_render_liner()`)
- **Cookieless 30-second previews.** One shared native `Audio` element plays a track's `p.scdn.co` preview MP3 — one track at a time, click-to-toggle, no third-party embed or cookie. A dead preview (should Spotify ever pull a URL) retires its own button via the `audio` error event rather than failing loudly; the album-level Spotify embed (cover click-to-play) is unchanged. [assets/js/discography.js](assets/js/discography.js)
- **Brutalist tracklist styling** in the existing `.sn-disco-*` vocabulary — mono credits, a blood play/pause control, hairline rows, `prefers-reduced-motion` honoured, 11px floor. [assets/css/components.css](assets/css/components.css)

> **Why MINOR:** a new user-visible capability on existing cards; additive, no markup change to non-music content, no settings-schema change, no user action required. Inert (no panel) until the plugin supplies `tracks[]`.
>
> **Post-deploy:** the previews load audio from `p.scdn.co` — confirm the live Cloudflare CSP `media-src` allows it (the CSP is edge-set, not in this repo). Without it the `<audio>` is blocked and previews won't play (the tracklist + credits still render).

## [10.7.0] - 2026-06-14 — Wayfinding cluster: /index dossier, music URL facets, keyboard nav

**Headline:** Three reader-facing wayfinding additions (cluster 5) — a whole-site `/index` dossier, URL-addressable music role filters with per-role counts, and `j`/`k` keyboard navigation between notes with a `?` cheat-sheet.

### New

- **`/index` whole-site dossier (C3).** A new server-rendered `/index` page collects the entire site into one brutalist dossier — the Notes corpus, the standalone Pages, and the discography — in the tabular row idiom. A postless `template_redirect` virtual route (no add_rewrite_rule), forcing HTTP 200 so the page isn't served under WP's inherited 404 (WORDPRESS-REFERENCE gotcha #40). Usable with JS off; the music section reads the same cross-package filter as `/music`, so it degrades cleanly when the plugin is absent. New modules [inc/page-index-template.php](inc/page-index-template.php) + [inc/page-index-render.php](inc/page-index-render.php), [assets/css/index.css](assets/css/index.css).
- **Keyboard navigation on notes (C5).** On a single note, `j` jumps to the next note and `k` to the previous (following the post-closing prev/next links), and `?` opens a keyboard cheat-sheet overlay. All skipped while typing in a form field (the same `isFormField` guard as the command palette, kept in lockstep). Pure progressive enhancement, single-post only; the overlay fade is gated under `prefers-reduced-motion: no-preference`. New module [inc/keyboard-nav.php](inc/keyboard-nav.php), [assets/js/keyboard-nav.js](assets/js/keyboard-nav.js), [assets/css/keyboard-nav.css](assets/css/keyboard-nav.css).

### Improvements

- **URL-addressable music role filters + per-role counts (B2).** The `/music` discography role chips are now deep-linkable: selecting a role writes `?role=Mixing` via the History API (back/forward restore the filter, the URL is shareable), and each chip carries a per-role count badge ("MIXING · 6"). The role from the URL is validated against the actual chips — a stray or hostile `?role=` value falls back to "All", and no selector is ever built from the raw param. Server-rendered counts (work with JS off); progressive — without the History API the chips still filter. [inc/discography-render.php](inc/discography-render.php), [assets/js/discography.js](assets/js/discography.js), [assets/css/components.css](assets/css/components.css)

> **Why MINOR:** three new user-visible capabilities (a new route, deep-linkable filters, keyboard navigation) — additive, no public-API removal, no settings-schema change, no user action required. Existing pages and content are unchanged.

## [10.6.0] - 2026-06-14 — Editorial blocks: epigraph + references

**Headline:** Two new opt-in editorial block styles + insertable patterns — a quiet epigraph opener and a hanging-indent references list — extending the brutalist block vocabulary for citation-bearing essays.

### New

- **Epigraph block style (D2)** — a `core/quote` style ("Epigraph") for a quiet framing quotation to open an essay: italic grey body, a thin concrete left rule (no field), small-caps attribution. The opener counterpart to the Signal quote's mid-text emphasis. Ships with an insertable `signal-noise/epigraph` pattern. [inc/block-styles.php](inc/block-styles.php), [patterns/epigraph.php](patterns/epigraph.php)
- **References block style (D3)** — a `core/list` style ("References") for a hanging-indent bibliography: first line flush, continuation lines indented, long source URLs wrapped. A print rule keeps each entry whole (`break-inside: avoid`) and reveals the source URL after each link so a printed page is self-contained. Ships with an insertable `signal-noise/references` pattern. [inc/block-styles.php](inc/block-styles.php), [patterns/references.php](patterns/references.php)

> **Why MINOR:** two new opt-in, user-visible block styles + patterns — additive, no markup change to existing content, no settings-schema change, no user action required. Both remain unselected until an author chooses them in the editor.

## [10.5.0] - 2026-06-14 — Head + craft cluster

**Headline:** Machine-readable identity in `<head>`, a humans.txt, a live build colophon, and prose typography polish — additions cluster 2 (theme head + craft).

### New

- **`<link rel="me">` identity links (A4).** The configured social profiles now emit `rel="me"` head links for IndieAuth / Mastodon verification. Reads the companion plugin's `sn_settings` → `social.same_as` directly (the documented `sn_schema_same_as` filter passes its default inline, so calling it yields nothing on its own), passing the list *through* that filter as an override hook. Standalone-safe: zero links and no fatal when the plugin is absent. New module [inc/identity-rels.php](inc/identity-rels.php).
- **`/humans.txt` + maker's mark (C4).** A flat humans.txt (humanstxt.org / IndieWeb convention) served as a `template_redirect` virtual route — owner/theme facts read from `wp_get_theme()` so they never drift, stack lines in lockstep with the colophon. Advertised with a `rel="author"` autodiscovery link, plus one dry maker's-mark comment in `<head>`. New module [inc/humans-txt.php](inc/humans-txt.php).
- **Live colophon build line (C2).** The /colophon page now shows real build provenance — theme version, companion-plugin version (`SNT_VERSION`), git short SHA, and deploy time — via a `[sn_build]` shortcode resolved through the existing `render_block` bridge. The git short SHA is read straight off `.git` (no shell-out — process spawning is disabled on Cloudways; `.git` is preserved on-server) handling loose-ref, packed-refs, and detached-HEAD layouts. Every segment degrades independently; never fatals. New module [inc/colophon-meta.php](inc/colophon-meta.php), pattern token in [patterns/colophon.php](patterns/colophon.php).

### Improvements

- **Head-sweep (A4).** Dropped the dead `rsd_link` (EditURI/RSD) and `wlwmanifest_link` from `<head>` — both advertised the disabled xmlrpc surface — and added a brand `<meta name="theme-color" content="#e00404">` for mobile browser chrome (single value, pinned to the `blood` palette slug; no dark variant by design). [inc/frontend-filters.php](inc/frontend-filters.php), [inc/assets-frontend.php](inc/assets-frontend.php)
- **Typographic detailing (C1).** Broadened `hanging-punctuation` from single-post body copy to the pull-quote prose root (a real refinement on Safari; silently ignored elsewhere). Added `font-variant-numeric: tabular-nums` on the numeric columns and an opt-in `.sn-frac` diagonal-fractions utility. Honest note: the body face (DM Mono) is monospaced and its subset carries no `tnum`, so tabular-nums is a forward-compat / intent declaration today, not a visible change; the `.sn-frac` utility is dormant until fraction content exists. [assets/css/critical.css](assets/css/critical.css)

> **Why MINOR:** new user-visible capabilities (rel=me, humans.txt, live colophon) plus additive head/typography refinements — no markup break, no settings-schema change, no user action required. A3 is inert until social profiles are configured; the live colophon degrades to the theme version alone when `.git`/plugin are absent.

## [10.4.2] - 2026-06-14 — Front-end polish

**Headline:** Visual + responsive refinements from the comprehensive audit.

### Fixed

- **Discography controls rail no longer hides behind the header.** The sticky role-filter rail stuck at `top:0` under the fixed header (z-index 10000) and went unclickable mid-scroll; it now sticks below the header (108/80/65px staircase) at a z-index above page content. [assets/css/components.css](assets/css/components.css)
- **Reading-progress bar sits flush under the header on mobile.** It was 15px too high at ≤480px (using the 80px tablet offset against the 65px mobile header); now matched.
- **Discography card titles** use `line-height: 1.0` (was 0.95, which clipped descenders when a title wrapped to two lines).
- **Article TOC** tightens (line-height 1.6 + hyphenation) at ≤480px so long section headings don't overflow the narrow rail.

### Improvements

- **Single-post reading measure** capped to ~68ch (body paragraphs ran ~90ch in the 760px column with DM Mono); headings, quotes, and patterns keep the full column width for editorial scale contrast. [assets/css/critical.css](assets/css/critical.css)

> **Why PATCH:** presentation fixes + one reading-measure refinement to existing surfaces — no new features, no markup change.

## [10.4.1] - 2026-06-14 — Accessibility pass (front-end)

**Headline:** Front-end accessibility refinements from the comprehensive audit — touch targets, reduced-motion, screen-reader state, and the 11px text floor.

### Fixed

- **Reduced motion now honored on scroll.** The header padding-shrink, logo width/height resize, and nav-underline transitions fired unconditionally; they're now suppressed under `prefers-reduced-motion: reduce` (opacity stays — it isn't motion; the scrolled state still applies, just instantly). [assets/css/layout.css](assets/css/layout.css)
- **Touch targets meet the 44px floor (WCAG 2.5.5).** The mobile hamburger / overlay-close (was 28px — the sole mobile-nav affordance), discography role-filter chips (28px), note-share COPY/SHARE buttons (29px), the /notes search submit (18px), and the pillar "Read essay" CTA (14px) all grow to a ≥44px hit area while the visible glyphs stay compact. Verified at 44px in a headless browser.
- **11px text floor applied to 10 small-text rules.** Pull-quote attribution, compare/steps labels, post-closing tags + prev/next labels, sidenote, post-frontmatter, catalog eyebrow/meta, and the pillar CTA used bare `0.7–0.75rem` (9.8–10.5px at a 14px base); now wrapped in `max(…, 11px)` to match the guard already used elsewhere. [assets/css/critical.css](assets/css/critical.css), [components.css](assets/css/components.css), [inc/page-notes-render.php](inc/page-notes-render.php)
- **Discography filter chips expose `aria-pressed`** so assistive tech announces the active filter (markup + the JS toggle). [inc/discography-render.php](inc/discography-render.php), [assets/js/discography.js](assets/js/discography.js)
- **404 page semantics:** the primary `<nav>` now carries `aria-label="Main navigation"` (it sat unlabeled beside the named "Recent notes" nav), and the recent-notes titles are `<h2>` (was `<h3>`, skipping a level under the `SIGNAL LOST` h1). [parts/header.html](parts/header.html), [inc/404-recovery.php](inc/404-recovery.php)
- **404 search input** restores a visible 2px focus outline (was `outline:none` with only a 1px border-colour change). [assets/css/components.css](assets/css/components.css)

> **Why PATCH:** accessibility-conformance fixes to existing surfaces — no new features; the only change for sighted mouse users is larger hit areas.

## [10.4.0] - 2026-06-14 — Beacon custom-event API

**Headline:** Extends the first-party beacon with a public, privacy-respecting custom-event API. Pairs with plugin **v6.10.0** (the `ce`/`cp` rollups + Events-tab display) and worker **v1.2.0** (`ce`/`cp` capture).

### New

- **`window.SN_BEACON.event(name, props)`** ([assets/js/sn-beacon.js](assets/js/sn-beacon.js)) — fire a named custom event (e.g. `subscribe-click`, `code-copy`) from any page. Posts a `ce` beacon to the same-origin Worker; **no-op under DNT/GPC** (a no-op stub is installed *before* the privacy gate, so `window.SN_BEACON.event(…)` is always safe to call). Name clamped to 64 chars; up to 4 string properties (key ≤60, value ≤180). `tests/beacon.php` extended (36 assertions).

## [10.3.0] - 2026-06-12 — First-party analytics beacon (ships inert)

**Headline:** The theme half of the first-party, cookieless edge-analytics arc. Adds a tiny front-end **beacon** ([assets/js/sn-beacon.js](assets/js/sn-beacon.js) + [inc/beacon.php](inc/beacon.php)) that emits pageview / scroll-depth / time-on-page hits to a same-origin Cloudflare Worker (`/_sn/px`) → Analytics Engine. **Ships dormant:** the beacon JS no-ops entirely until `SN_BEACON_TOKEN` is defined in `wp-config.php` (and must equal the Worker's `SN_PX_TOKEN`), so installing this changes nothing on the live site until the token is wired. Honors Do-Not-Track / Global-Privacy-Control. Pairs with plugin **v5.2.0** (the read-side dashboard). `tests/beacon.php` (25 assertions); full theme suite green.

### New

- **First-party analytics beacon** — `sn_beacon_enqueue()` ([inc/beacon.php](inc/beacon.php)) localizes a `window.SN_BEACON` island (same-origin `/_sn/px` endpoint + public site token + post id) and enqueues [assets/js/sn-beacon.js](assets/js/sn-beacon.js), which sends `pv`/`sc`/`tm` hits via `navigator.sendBeacon` (fetch fallback). **Inert until `SN_BEACON_TOKEN` is set** — no token, no island, no requests. Respects `navigator.doNotTrack` / `globalPrivacyControl`. Cookieless; the Worker derives a daily-rotating visitor hash at the edge. `tests/beacon.php` (25 assertions). Wired in [functions.php](functions.php).

## [10.2.0] - 2026-06-12 — /notes paged title (pagination R2)

**Headline:** The theme half of `/notes` pagination **Release 2**. The `/notes` index `<title>` now appends "— Page N" on paginated views (`/notes/?paged=2` → "Notes — Juan Lentino — Page 2"), so paged pages are distinguishable in search results and browser tabs. **Single owner:** the theme's `pre_get_document_title` filter is the sole authority for the suffix — it short-circuits the plugin's `document_title_parts` (verified against WP core), so no double-append is possible. Pairs with plugin **v5.1.0**'s paged self-canonical + Notes-per-page knob. R1 shipped in v9.6.0.

### Improvements

- **Paged `/notes` `<title>` suffix** — the `pre_get_document_title` filter for the Notes index appends "— Page N" for N>1, extracted into a named `sn_notes_index_title()` builder. The paged read is inlined (reads `get_query_var('paged')` with a `$_GET` fallback) so it has no load-order dependency on the render file. New `tests/notes-title-paged.php` (4 assertions); `/notes` build marker bumped. PHPCS clean (security ruleset).

## [10.1.0] - 2026-06-12 — Reader wayfinding: in-article TOC + helpful 404 recovery

**Headline:** Two reader-facing front-end additions. Long notes (3+ H2 sections) get an automatic table-of-contents card at the article top plus a thin reading-progress bar under the header; the 404 page becomes a recovery surface — a search box into Notes plus a recent-notes list — instead of a dead end. No admin surface, no settings; both work without JS and honor `prefers-reduced-motion`. 32 suites / 844 assertions green; PHPCS falsification-verified.

### New

- **In-article table of contents + reading-progress bar** on long notes. A `the_content` filter (single notes with 3+ H2 sections) auto-renders a "Contents" card of jump links at the top of the article, and a thin blood-red reading-progress bar pins under the header. Front-end only, no admin surface — H2 anchors are slugged + deduped (author-set ids respected), the TOC works with JS disabled, and the smooth-scroll honors `prefers-reduced-motion`. New [inc/article-toc.php](inc/article-toc.php) + [assets/js/article-toc.js](assets/js/article-toc.js) + styles in [components.css](assets/css/components.css); `tests/article-toc.php` (23 assertions). PHPCS falsification-verified.
- **Helpful 404 recovery** — the 404 page now offers a search box (into Notes) and a list of recent notes instead of a dead end. New [inc/404-recovery.php](inc/404-recovery.php) (`[sn_404_suggestions]`) + enriched [templates/404.html](templates/404.html) + styles in [components.css](assets/css/components.css); `tests/404-recovery.php` (19 assertions). PHPCS falsification-verified.

## [10.0.0] - 2026-06-10 — Modernization major: WordPress 7.0 floor

**Headline:** The theme half of the paired modernization major (with plugin **v5.0.0**). v10.0.0 hard-raises the WordPress floor to 7.0 — no new features; the floor raise is itself the breaking change (the theme's first major since v9.0.0). Drops the now-obsolete WP<7.0 pre-warning notice + dead pre-6.7 compat.

### Removed

- **WordPress < 7.0 is no longer supported** — `Requires at least: 7.0` in [style.css](style.css). WordPress refuses to load the theme on older versions.
- **The WP<7.0 pre-warning admin notice** (`inc/admin-notice-wp-version.php`, deleted) — obsolete now that 7.0 is enforced (its own docblock scheduled it for v10.0.0 deletion).

### Changed

- **Simplified the diagnostics WP-version read** — `wp_get_wp_version()` (added in WP 6.7) is always available on the 7.0 floor, so the `$wp_version`-global fallback in `get-system-status` ([inc/abilities-diagnostics.php](inc/abilities-diagnostics.php)) is dropped.

> **Why MAJOR:** the WP-floor raise is a SemVer breaking change requiring user action (install/update refuses below 7.0). `theme.json` stays v3 — WordPress hasn't shipped the v4 schema, so that migration defers to v11.0.0. No flagship — pure modernization, paired with plugin v5.0.0. New guard `tests/manifest-floor.php`; 30 suites green; PHPCS falsification-verified.

## [9.15.6] - 2026-06-09 — Reconcile reading-time ability with the v4.14.5 resolver (public-only)

**Headline:** A consistency fix following plugin **v4.14.5**, which closed the same reading-time existence oracle at the `[sn_reading_time]` shortcode-resolver layer (it now returns empty for *any* non-public post, regardless of cap). The theme's v9.15.5 `get-reading-time-for-slug` gate allowed a `read_post`-authorized user through — intending to still return a draft's real reading time — but the v4.14.5 resolver blocks that downstream, so the branch was **dead** and left the two layers with divergent policies. The ability is now **public-only**, matching the resolver.

### Fixed

- **`get-reading-time-for-slug` is now public-only, consistent with the plugin v4.14.5 resolver.** Dropped the `current_user_can('read_post')` allowance from the viewability gate — it was dead (the `[sn_reading_time]` resolver returns empty for any non-public post regardless of cap) and made the theme ability and the plugin resolver diverge, surfacing the wrapper's 5-min fallback to an authorized editor instead of a real time. The gate is now `is_post_publicly_viewable()` only; a non-public page yields a uniform `minutes=0`, identical to a non-resolving slug. The regression test now asserts a `read_post`-authorized user also gets `0` — the prior assertion (`→ real time`) encoded behavior that didn't hold end-to-end against the real plugin resolver (it passed only via the test stub). Theme suite 103 integration assertions / 29 suites, 0 failures; PHPCS falsification-verified. ([inc/abilities-content.php](inc/abilities-content.php))

> **Why PATCH:** internal consistency cleanup of a just-shipped gate — removes a dead, divergent code path. No new capability, no public-API or settings-schema change. The security outcome (oracle closed) is unchanged and now enforced identically by both layers. (Contrast `get-active-template-structure`, which computes in-theme and still honors `read_post` — the policy difference is justified by the dependency difference.)

## [9.15.5] - 2026-06-09 — Delta-audit hardening: reading-time existence oracle

**Headline:** A direct sibling of the v9.15.4 fix, surfaced by a delta security audit of the v9.15.2→v9.15.4 / plugin v4.14.1→v4.14.3 changes (the surface the original back-audit never saw, because it ran pre-fix). The `get-reading-time-for-slug` ability — gated only by the `read` cap (any logged-in user) — resolved an arbitrary slug to a page of *any* status and returned its real reading time, while a non-resolving slug returned a 5-minute fallback. A subscriber could therefore enumerate slugs and distinguish "a draft/private page with this slug exists" (real minutes, a coarse length proxy) from "no such slug" (5). Not a content-disclosure bug — an existence/length *oracle*, closed exactly as the diagnostics sibling was.

### Improvements

- **`get-reading-time-for-slug` no longer leaks which non-public pages exist.** The ability now resolves the same page the `[sn_reading_time]` resolver would (`get_page_by_path`, post_type `page`) and, unless the page is publicly viewable *or* readable by the current user (`is_post_publicly_viewable()` / `current_user_can('read_post', …)`), returns a uniform `minutes=0` — identical to a non-resolving slug, so "exists but private" is indistinguishable from "doesn't exist". Only the integer minutes was ever returned (never content), so this is defense-in-depth. Behavioral regression test in [tests/abilities-integration.php](tests/abilities-integration.php) pins: a subscriber gets `minutes=0` on a draft slug *and* on a missing slug; a user who can `read_post` the draft still gets the real time; a public page is unchanged. Theme suite 98 → 103 integration assertions (+ a registration fixture), 0 failures; PHPCS falsification-verified (security ruleset proven live). Also corrects a doc-drift — the registration already claimed "minutes=0 if the slug does not resolve". ([inc/abilities-content.php](inc/abilities-content.php))

### Docs

- **Delta security audit report** ([docs/superpowers/audits/2026-06-09-delta-audit.md](docs/superpowers/audits/2026-06-09-delta-audit.md)) — adversarial re-audit of the security-fix delta: 10 review clusters (9 fix clusters + a generalized IDOR-class sweep) with 3-lens verification + a completeness critic. It verified the shipped fixes sound (incl. the v9.15.3 IDOR fix against upstream `WP_Ability` dispatch), found no untouched IDOR siblings, and the completeness critic surfaced this reading-time oracle. Runbook for periodic audits: [docs/SECURITY-BACK-AUDIT.md](docs/SECURITY-BACK-AUDIT.md).

> **Why PATCH:** defense-in-depth hardening of an existing read ability — no new capability, no public-API or settings-schema change, no required user action beyond installing. Legitimate callers (published pages; editors/authors on their own drafts) are unaffected.

## [9.15.4] - 2026-06-09 — Back-audit INFO hardening: diagnostics existence oracle

**Headline:** The theme-side INFO/defense-in-depth item from the 2026-06-09 security back-audit. Not a content-disclosure bug — it closes an existence/post-type *oracle* in a diagnostics ability.

### Improvements

- **`get-active-template-structure` no longer leaks which non-public posts exist.** The ability is gated by the `read` cap (any logged-in user) and resolved an arbitrary `post_id`/`slug` without a per-post check — so a subscriber could enumerate `post_id` and learn from a 200-vs-404 response which draft/private/pending posts exist and whether they're pages. It now requires the post to be publicly viewable *or* readable by the user (`is_post_publicly_viewable()` / `current_user_can('read_post', …)`); on failure it returns the **same `post_not_found`** as a genuinely missing post, so "exists but private" is indistinguishable from "doesn't exist". Post *content* was never exposed (only the theme's template structure), so this is defense-in-depth, not a disclosure fix. Regression test in [tests/abilities-integration.php](tests/abilities-integration.php) (96 → 98 assertions). ([inc/abilities-diagnostics.php](inc/abilities-diagnostics.php))

> **Why PATCH:** defense-in-depth hardening of an existing diagnostics surface — no new capability, no public-API or settings-schema change, no required user action beyond installing. Legitimate callers (admins/editors via the AI client) can read posts and are unaffected.

## [9.15.3] - 2026-06-09 — Security: per-post gate on the /notes-summary ability (IDOR fix)

**Headline:** Closes an IDOR in the `ai-generate-page-note-summary` WP ability surfaced by a whole-codebase security back-audit. The ability gated on the blanket `edit_posts` capability, so any Contributor-level account could summarize — and thereby read the body of — **any** draft, pending, scheduled, or private post by passing its `post_id`, regardless of authorship. It now checks the per-post `edit_post` capability, matching the convention every post-scoped ability in the companion plugin already uses.

### Fixed

- **IDOR — arbitrary draft/private post-content disclosure via the /notes summary ability.** `ai-generate-page-note-summary` read `get_post($post_id)->post_content` after a permission check (`sn_theme_perm_edit_posts` → `current_user_can('edit_posts')`) that never inspected `post_id`. A Contributor could enumerate `post_id` and receive AI summaries of posts they cannot view. Now gated by a new `sn_theme_perm_edit_post($input)` callable doing `current_user_can('edit_post', (int) $input['post_id'])` — denying access to posts the user cannot edit (`rest_forbidden`, AI helper never invoked). ([inc/abilities-ai-generation.php](inc/abilities-ai-generation.php), [inc/abilities-helpers.php](inc/abilities-helpers.php))

### Improvements

- **Regression test pins the per-post gate.** [tests/abilities-integration.php](tests/abilities-integration.php) now asserts a Contributor who can edit their own post is denied on another author's draft (`rest_forbidden`, zero AI calls), allowed on their own, and that the named callable returns false for non-editable / null / missing `post_id`. Suite 85 → 94 assertions, 0 failures; PHPCS falsification-verified (security ruleset proven live).
- **The 4 content-string generative abilities are unchanged** — they take a raw content/draft string (no post reference), so the blanket `edit_posts` cap remains correct for them. Only the one post-reading ability moved to the per-post gate.

### Docs

- **Security back-audit report** ([docs/superpowers/audits/2026-06-09-security-back-audit.md](docs/superpowers/audits/2026-06-09-security-back-audit.md)) — 12-dimension whole-codebase audit with 3-lens adversarial verification: this IDOR (MEDIUM) plus four plugin LOW hardening items and a JSON-LD encoder note (queued for a plugin patch), and the surfaces verified clean.

> **Why PATCH:** a security bug fix that removes unintended access — no new capability, no public-API or settings-schema change, and no legitimate workflow depended on the over-broad permission. Per the project's "majors/minors gate on capability" rule, this is a patch.

## [9.15.2] - 2026-06-08 — Two-track width system across all pages

**Released:** 2026-06-08.

**Headline:** Every page now sits on one of two intentional content widths instead of an ad-hoc scatter (600 / 680 / 700 / 720 / 800 / 900 / 1000 / 1100 / 1400). A **reading track (760px)** carries single-column prose — page titles, intros, body copy, forms, CTAs, posts, notes — and a **wide track (1400px)** carries galleries, card grids, credibility strips, timelines, multi-column media, and the landing hero. The wide track is the near-full width the `/music` cover grid already used; the system extends that consistency site-wide while protecting text readability (prose never goes wider than the ~70-character reading measure).

### Improvements

- **Two global width tracks defined in [theme.json](theme.json)** — `contentSize` 720px → **760px** (reading), `wideSize` 1200px → **1400px** (wide). These are the only two widths the site uses.
- **Every template snapped to a track:**
  - *Reading (760px):* 404 (600→760), contact title + form + connect (800/700→760), services title + closing CTA (1000/680→760), music title + credits (900→760), resume title (900→760), about title (1000→760). home · index · single · notes · page · provenance · colophon inherit the 760 default automatically.
  - *Wide (1400px):* about bio + education sections (1000→1400, photo-left/text-right columns) and the front-page hero (1100→1400) join the wide track. music gallery, services cards + credibility strip, and the resume timeline were already 1400 and are unchanged.
- **Per-page rhythm preserved** — pages keep their "narrow intro → wide content" cadence (e.g. music: 760 title → 1400 gallery; services: 760 title → 1400 cards → 760 CTA); only the *narrow* number was unified to the reading track.
- **Guard test** ([tests/layout-width-system.php](tests/layout-width-system.php)) — fails if any template carries a `contentSize` outside {760px, 1400px}, or if theme.json's two tracks drift. Locks the system so the old scatter can't creep back. Theme suite 30 → 31 files, 0 failures.

> **Why PATCH:** layout calibration of existing pages — no new capability, no public-API or settings-schema change, no required user action beyond installing the update. Per the project rule, majors/minors gate on capability, not on how many files a calibration touches.

## [9.15.1] - 2026-06-08 — /music gallery renders from the template

**Released:** 2026-06-08.

**Headline:** The `/music` cover-grid discography now renders structurally from the page template — install the update and it appears, no block-editor step. Since v9.13.0 the gallery only showed if `[sn_discography]` was hand-placed into the Music Page's `post_content`; if that edit was never made (or never saved), the page kept showing only the curated playlist while the plugin's `MusicAlbum` JSON-LD and full album store were already live. Moving the shortcode into [templates/page-music.html](templates/page-music.html) makes the discography a feature of the `/music` route itself.

### Fixed

- **`/music` showed only the curated playlist, not the cover-grid gallery.** The data-driven `[sn_discography]` gallery (v9.13.0 / v9.14.0) only rendered when the shortcode was hand-placed in the Music Page body. On the live site that placement never persisted (the page was untouched since February), so visitors saw the old single playlist embed even though Signal & Noise Tools was synced and emitting 11 `MusicAlbum` JSON-LD nodes. Root cause: the page body never received the shortcode, and manual per-page placement is fragile and easy to lose. Fixed by wiring the gallery into the template instead. ([templates/page-music.html](templates/page-music.html))

### Improvements

- **Discography is now template-driven, not content-driven.** `[sn_discography]` is wired into the template's "Spotify Embeds Area" via a `wp:shortcode` block — the same FSE-resolves-shortcodes pattern already shipped in [templates/single.html](templates/single.html) for `[sn_related_notes]` and [parts/post-frontmatter.html](parts/post-frontmatter.html) for `[sn_updated_date]`. It sits below the curated playlist, which stays as the hero **and** the plugin-absent fallback: with the plugin gone the shortcode self-degrades to `''`, leaving the playlist alone — no blank region. The plugin, the store, and the `sn_discography_entries` contract are untouched.
- **Structural guard** ([tests/page-music-template.php](tests/page-music-template.php)) — asserts the `/music` template wires `[sn_discography]` in a valid `wp:shortcode` block, preserves `wp:post-content` (hero + fallback), and keeps the header/footer parts + the Muso.AI credits CTA. Theme suite: 29 → 30 files, 0 failures; phpcs falsification-verified.

> **Why PATCH (not minor):** no new capability — the gallery shortcode, store, schema, and styles all shipped in v9.13.0–v9.15.0. This release only makes the already-shipped feature render where manual placement failed to. No public API change, no required user action beyond installing the update (per the project's "majors/minors gate on capability, not surface area" rule).

## [9.15.0] - 2026-06-08 — Featured release player (/music hero)

**Released:** 2026-06-08.

**Headline:** A new `[sn_music_featured]` shortcode renders the single "press play" Spotify player at the top of `/music` from a release the owner picks in the S&N admin (Signal & Noise Tools v4.14.0 → Monitoring → Music → **Featured release**). No more hand-editing an embed block into the page: paste a Spotify link in settings and it appears here, styled to match. Standalone-safe — plugin absent or unset → renders nothing. Companion to plugin v4.14.0.

### New

- **`[sn_music_featured]` shortcode** ([inc/music-featured-render.php](inc/music-featured-render.php)) — reads the featured release off the cross-package `sn_music_featured` filter and renders a single Spotify embed inside a brutalist "Featured · Press play" card. Height adapts to the embed type (compact for a track, full for an album/playlist). This is the one eager iframe on `/music`; the cover grid below stays click-to-play. Escaped, and `''` when nothing is configured (joins the cross-package contract set in [tests/cross-package-listeners.php](tests/cross-package-listeners.php) as Contract 6).
- **`.sn-music-featured` styles** ([assets/css/components.css](assets/css/components.css)) — bordered card with the signal-dot label, on the existing palette.

### Notes

- **One-time placement:** in the Music page content, replace your hand-curated featured Spotify embed block with a Shortcode block containing `[sn_music_featured]`. Thereafter you change the featured release entirely from Monitoring → Music — no page editing.

## [9.14.0] - 2026-06-08 — /music cover-grid redesign

**Released:** 2026-06-08.

**Headline:** The `/music` discography is redesigned from a vertical list of text rows into an **album-cover gallery** — the covers are the hero. A sticky controls rail adds a live release count and **filter-by-role** chips (Producer · Mixing · Mastering · Engineer · …), so a visitor can instantly surf, say, *everything Juan produced*. Same brutalist vocabulary (Bebas titles, DM Mono labels, blood-red accents), same data source — this is theme-side only; the companion plugin and the `sn_discography_entries` store contract are unchanged.

### New

- **Cover-grid gallery** ([inc/discography-render.php](inc/discography-render.php) + [assets/css/components.css](assets/css/components.css)) — `[sn_discography]` now emits a responsive grid of large square album covers grouped by year (descending), each with the title (Bebas), primary artist, credited roles, and a per-album Muso `Credits ↗` link. Replaces the v9.13.0 row list.
- **Role filter + live count** ([assets/js/discography.js](assets/js/discography.js)) — a sticky rail shows `N releases · <span>` and a chip per credited role. Filtering shows only releases carrying that credit, collapses empty year sections, updates the count, and reveals an empty state when nothing matches. `All` resets. Pure progressive enhancement (no JS → all releases visible).
- **Click-to-play covers** — the whole cover is the play affordance: click (or Enter/Space) swaps it for the lazy Spotify embed in place. Zero eager iframes (unchanged performance contract); a release with no Spotify match renders a static cover with its Credits link.

### Notes

- The page header, the curated "press play" featured player, and the Muso.AI CTA are unchanged. With the `[sn_discography]` shortcode already placed in the Music page content, the redesigned gallery appears as soon as the theme updates — no content edit needed.
- Verified against the live 11-album catalog (covers load, 3-col grid, reactive count, role-accurate filtering, no console errors). Design contract: [docs/superpowers/mockups/2026-06-08-music-redesign.html](docs/superpowers/mockups/2026-06-08-music-redesign.html).

## [9.13.0] - 2026-06-08 — Music Identity (release timeline)

**Released:** 2026-06-08.

**Headline:** The `/music` page gains a data-driven release timeline. The companion plugin (Signal & Noise Tools v4.13.0) mirrors Juan's Muso.AI producer credits + Spotify album media into a cached store and exposes it via the standalone-safe `sn_discography_entries` filter; the theme's new `[sn_discography]` shortcode reads that filter and renders a brutalist, year-grouped timeline with click-to-play embeds. The theme works **standalone** — with the plugin absent (or before the first sync) the filter yields `array()` and the shortcode renders nothing, so `/music` falls back to its existing content. Companion to plugin v4.13.0.

### New

- **`[sn_discography]` release-timeline shortcode** ([inc/discography-render.php](inc/discography-render.php)) — reads the normalized discography entries off `apply_filters( 'sn_discography_entries', array() )` and renders a year-grouped (descending) timeline: lazy artwork, title, primary artist, Juan's credited role(s), a click-to-play control, and a Muso credits link. Every external-data field is escaped (`esc_html` / `esc_url` / `esc_attr`).
- **Lazy click-to-play embeds** ([assets/js/discography.js](assets/js/discography.js)) — the server emits **zero** Spotify iframes (N live embeds would wreck the page); each release ships a `.sn-disco-play` button that this script swaps for the Spotify embed on demand (`/embed/album/` or `/embed/track/` per the release type). Enqueued only on `/music`, footer + deferred. With JS off the button no-ops and the Credits link still works — pure progressive enhancement.
- **Discography timeline styles** ([assets/css/components.css](assets/css/components.css)) — brutalist `.sn-discography*` treatment matching the existing catalog tokens (mono year labels, hairline rows, fixed artwork sizing).

### Standalone-safety

- The new `sn_discography_entries` filter joins the cross-package contract set covered by [tests/cross-package-listeners.php](tests/cross-package-listeners.php) (Contract 5): the theme is the consumer; plugin absent → `array()` → shortcode returns `''` → `/music` static fallback. No fatal, no blank.

### Notes

- **One-time `/music` placement (manual):** edit the Music page in wp-admin and replace the hand-curated Spotify-embed blocks in the page **content** with a Shortcode block containing `[sn_discography]`. The page header and Muso CTA live in the `page-music.html` template, so the content should hold only the shortcode.

## [9.12.0] - 2026-06-08 — Front-end render knobs (plugin-configurable)

**Released:** 2026-06-08.

**Headline:** Several recently-added front-end behaviors that were hardcoded are now exposed through filters the companion plugin (Signal & Noise Tools v4.12.0) drives from a new **Tools → Front-End** settings tab. Every default equals the previous hardcoded value, so this update changes nothing on its own — the theme works standalone, and the plugin (if active) supplies the configured value.

### New

- **`sn_related_count`** (default 3) — number of related notes in the single-note footer (was hardcoded to 3 at the shortcode call site).
- **`sn_palette_recent_count`** (default 8) — recent-notes count in the reader command palette (⌘K).
- **`sn_palette_enabled`** (default true) — kill-switch for the reader command palette: when off, the palette JS/CSS is not enqueued and the footer trigger is hidden via a `body.sn-cmdk-off` class (rule lives in always-loaded `critical.css`).
- **`sn_json_feed_items`** (default 20) — item count in the JSON Feed.

These join the existing `sn_updated_date_threshold_days` and (plugin-side) `sn_reading_time_wpm` filters, which the plugin's Front-End tab now also drives.

### Internal

- Extracted `sn_feed_json_query_args()` (pure, testable) from the JSON-feed render path. Suite: 26 suites / 697 assertions / 0 failures.

## [9.11.3] - 2026-06-08 — Search trigger moved into the footer bar

**Released:** 2026-06-08.

**Headline:** The command-palette **SEARCH ⌘K** trigger was a `position: fixed` button pinned to the viewport's bottom-right corner — so at the bottom of every page it floated *on top of* the footer colophon ("Colophon · © Juan Lentino"). This surfaced now because the v9.11.2 deploy finally purged the stale Breeze bundle that had been suppressing `command-palette.css` (the v9.11.1 "renders unstyled / mispositioned" known issue). The trigger now lives **in the footer utility bar** beside the colophon — no overlap, ever. ⌘K / Ctrl-K / "/" still open the palette globally.

### Improvements

- **Command-palette trigger relocated to the footer bar** — moved the visible trigger from a `wp_footer`-injected `position: fixed` overlay to an in-flow element inside `parts/footer.html` (the `.sn-footer__meta` cluster). `assets/js/command-palette.js` binds it by the `.sn-cmdk-trigger` class, so the click handler and the global ⌘K/Ctrl-K/"/" shortcuts are unchanged. Restyled for the dark footer: transparent with a faint bone hairline, bone label, blood on hover/focus (the floating brutalist hard-shadow no longer made sense in-flow).

### Fixed

- **SEARCH ⌘K button overlapped the footer colophon** at the bottom of every page (the fixed button and the colophon both sat bottom-right). Resolved by the relocation above.

### Tests

- Added four guards to `tests/command-palette.php`: the footer template renders the trigger, it keeps `aria-keyshortcuts`, the floating `wp_footer` injection is gone, and the trigger is no longer `position: fixed`. Suite: 26 suites / 687 assertions / 0 failures.

## [9.11.2] - 2026-06-08 — Hotfix: the *real* single-notes critical error (undefined function)

**Released:** 2026-06-08.

**Headline:** Single notes were *still* returning "There has been a critical error on this website." after v9.11.1 — because v9.11.1 fixed the wrong thing. The Cloudways error log named the real culprit all along: a call to the **nonexistent WordPress function `get_the_queried_object_id()`** (the real function is `get_queried_object_id()` — there is no `the_`) at `inc/related-notes.php:136` and `inc/post-share.php:45`. Both were introduced in **v9.10.0** (Related Notes footer + Share row), not v9.11.0. Every single-note render fataled on the first of these calls. This release renames both calls to the correct core function and repairs the test stubs that had been hiding the bug.

### Fixed

- **Single notes returned a PHP fatal on every render** — `[sn_related_notes]` (`inc/related-notes.php:136`) and `[sn_note_share]` (`inc/post-share.php:45`) called `get_the_queried_object_id()`, which does not exist in WordPress. PHP aborts on the first undefined-function call, so the error log showed only `related-notes.php:136`; `post-share.php:45` carried the identical typo and would have fataled next, so both are fixed. Both now call `get_queried_object_id()` (real core function since WP 3.1.0). Adversarially verified: it returns the queried note's ID on the single-note render path (set via `is_singular` before the loop, independent of loop cursor — the *most* robust choice for a `render_block` bridge call site), and the existing `< 1` / `is_singular('post')` guards degrade to empty output rather than fataling if it ever returns 0.
- **bfcache ("Page prevented back/forward cache restoration") on single notes** — this Lighthouse/Cloudways flag was a *symptom* of the same fatal: WordPress serves its 500 error page with `Cache-Control: no-store`, which blocks the back/forward cache. With the fatal fixed, single notes return 200 and bfcache is restored — no separate change needed.
- **Closed the false-green that let this ship** — `tests/related-notes.php` and `tests/post-share.php` had *stubbed the misspelled* `get_the_queried_object_id()`, modeling a fictional WordPress in which the typo'd function existed, so the suite passed green while production fataled. Both stubs now define the real `get_queried_object_id()`; the suite faithfully models core, and any future reintroduction of the typo fails the tests (verified red→green: with the stub corrected and the production typo still in place, both suites reproduce the exact production fatal). Full suite remains green — 26 suites / 683 assertions / 0 failures.

### Notes

- A multi-agent audit independently confirmed completeness: the full single-note render path (4 template parts, 6 shortcodes incl. the plugin-owned `[sn_reading_time]`, 2 dynamic blocks) was swept and contains **no other undefined-function call**, and a false-green stub audit across all 26 test files found **no other misspelled-core-function stubs**.
- v9.11.1's hotfix reverted the v9.11.0 Block Bindings front-matter migration on the hypothesis that it was the cause; the error log identified `related-notes.php:136` as the actual fatal. That revert is left in place (orthogonal, already shipped); this release addresses the true root cause.
- The other two Cloudways perf flags ("Enable text compression", "inefficient static-asset cache policy") did not reproduce against live headers — compression is enabled and static assets are edge-cached. Re-run Lighthouse after installing this update + a Breeze/Cloudflare cache purge to clear the stale audits.

## [9.11.1] - 2026-06-08 — Hotfix: single notes critical error

**Released:** 2026-06-08.

**Headline:** Emergency revert of the v9.11.0 Block Bindings migration of the post front-matter, which caused a PHP fatal ("There has been a critical error on this website.") on **single notes**. `parts/post-frontmatter.html` is restored to the proven v9.10.0 `[sn_reading_time]` / `[sn_post_pillar]` shortcode slots, which renders single notes correctly. The other five v9.11.0 features (JSON Feed, RSS enrichment, sidenote/pull-quote blocks, `/colophon`, reader command palette) are untouched.

### Fixed

- **Single notes returned a critical error** (`parts/post-frontmatter.html`) — the v9.11.0 migration of the reading-time and pillar front-matter slots to the `signal-noise/post-field` Block Bindings source caused a PHP fatal on the single-note render path. The change was isolated as the cause: `single.html` (the only template that renders the front-matter part) was the only single-note-path change in v9.11.0, and the homepage — which never renders the front-matter — was unaffected. Reverted the front-matter to the v9.10.0 shortcode slots, restoring single notes. The `signal-noise/post-field` source stays registered (proven safe at registration; unused) pending a proper runtime root-cause before the binding is re-introduced.

### Known issues (not yet fixed)

- **Reader command-palette trigger renders unstyled / mispositioned** — `assets/css/command-palette.css` sets the trigger to `position: fixed` bottom-right, but it is rendering in the footer flow, which points to a stale Breeze CSS bundle that predates the new file (gotcha #28). Purging Breeze + Cloudflare caches should resolve it; under investigation.

## [9.11.0] - 2026-06-08 — Feeds, blocks & pages

**Released:** 2026-06-08.

**Headline:** Six additive, reader/editor-facing capabilities for the B4 cycle — a JSON Feed 1.1 endpoint and richer RSS for the Notes corpus, two first-class custom blocks (sidenote + pull-quote) that supersede the old patterns, a Block Bindings source that reads post fields into the front-matter, an editable `/colophon` page, and a reader-facing Notes-scoped command palette. Each capability is a self-contained `inc/*.php` module (named functions, `require_once` from `functions.php`, guarded behind a `SN_*_TEST` sentinel) with its own standalone CLI test fixture. The theme never flushes rewrite rules and has no JS build step — the blocks and palette are buildless ES5. Every cross-package (plugin) read is `function_exists`-guarded so the feed, bindings, and palette degrade gracefully when the companion plugin is absent.

### Added

- **JSON Feed 1.1 endpoint** (`inc/feed-json.php`) — `?feed=json` serves the latest 20 notes as a [JSON Feed 1.1](https://jsonfeed.org/version/1.1) document (`application/feed+json`), registered via `add_feed('json', …)`. The `?feed=json` query resolves immediately (no rewrite flush — `feed` is a core public query var); the pretty `/feed/json/` path materializes only on the plugin's next flush, never the theme's. The item builder is a pure, testable function that emits raw values for `wp_json_encode` to escape (no `esc_html` double-escaping in JSON).
- **RSS item enrichment** (`inc/feed-enrichment.php`) — declares the Media RSS namespace on `rss2_ns` and emits `<media:content>` for each note's featured image plus a `<sn:readingTimeMinutes>` element on `rss2_item`. Reading time is plugin-owned (`sn_get_reading_time`, `function_exists`-guarded) so the feed degrades cleanly when the plugin is absent; no `<media:content>` is emitted when a note has no featured image. Core already emits `<category>` tags, so the theme does not re-emit them.
- **Sidenote + pull-quote custom blocks** (`blocks/sidenote/`, `blocks/pull-quote/`, `inc/blocks-register.php`) — two buildless `apiVersion: 3` dynamic blocks in a new "Signal & Noise" block category. `editorScript` is a manually-registered handle with explicit `wp-blocks`/`wp-element`/`wp-block-editor` deps (NOT a `file:` path, which would load with empty deps and throw `wp is undefined` in a no-build theme). The pull-quote block emits `.sn-pull-quote` — the class `critical.css` already targets — not the pattern's `.sn-pattern-pull-quote`. The `patterns/sidenote.php` / `patterns/pull-quote.php` scaffolds are retained as no-block fallbacks and annotated as superseded.
- **`signal-noise/post-field` Block Bindings source** (`inc/block-bindings.php`) — a read-only Block Bindings source resolving `reading_time | pillar | canonical | og_title` for the current post, registered on `init`. The callback returns a string to fill the block or `null` to keep the block's fallback markup (avoiding an empty `<p>`). Reading time formats with a min-1 floor; pillar reuses `sn_post_pillar_shortcode()`; canonical/og_title resolve via the plugin's post-settings helpers — all `function_exists`-guarded. Only `reading_time` and `pillar` are bound in the front-matter; `canonical`/`og_title` are resolvable but deliberately unbound so they don't duplicate the plugin's `<head>` output.
- **`/colophon` page template** (`templates/page-colophon.html`, `patterns/colophon.php`) — a custom FSE page template (registered in `theme.json` `customTemplates`, applies to pages) plus an editable `signal-noise/colophon` pattern carrying factual stack/type/tooling credits (anti-self-promotion by design), with a quiet "Colophon" link added to `parts/footer.html`. **One-time manual step:** a published Page at `/colophon` must be created in wp-admin (assigned the Colophon template) for the route and footer link to resolve.
- **Reader-facing Notes-scoped command palette** (`inc/command-palette.php`, `assets/js/command-palette.js`, `assets/css/command-palette.css`) — ⌘/Ctrl-K or `/` opens an accessible (APG dialog + combobox: focus trap, `aria-activedescendant`, Escape restores focus) overlay to search notes (navigates to `/notes/?s=`, no REST), jump to recent notes, or jump to pillar pages. Enqueued site-wide as buildless ES5. The data island is printed via `wp_add_inline_script(…, 'before')` with `wp_json_encode($data, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES)` so a note titled `</script>` can't break out of the inline tag. Recent notes come from a bounded `WP_Query(posts_per_page=8, no_found_rows, publish-only)`; pillars from `sn_theme_pillar_descriptors()` (guarded). Distinct from the plugin's wp-admin `@wordpress/commands` palette.

### Improvements

- **`parts/post-frontmatter.html` migrated to Block Bindings** — the reading-time and pillar slots are now bound paragraphs driven by the new `signal-noise/post-field` source (`{key:"reading_time"}` and `{key:"pillar"}`) instead of inline shortcode markup. The `wp:post-date`, `[sn_updated_date]` shortcode, the two `·` dividers, and `wp:post-terms` are byte-identical to v9.10.0; only the two bound slots changed. When a value is absent the slot keeps its empty fallback `<p>`, which CSS hides (`:empty`) so the row stays tight.

### Fixed

These post-build fixes were caught by an adversarial behavioral review (reviewers reading the committed code + real WordPress-core/plugin source) — green tests didn't catch them.

- **Custom blocks rendered empty content on the front end** (`blocks/sidenote/block.json`, `blocks/pull-quote/block.json`, `blocks/editor.js`) — both blocks declared `source:html` attributes, but a dynamic block's render callback receives only comment-delimiter attributes server-side (WordPress never sources `source:html` in PHP — verified vs `wp-includes/class-wp-block.php`), so `render.php` read empty `content`/`body`/`attribution` and every sidenote/pull-quote rendered as an empty element. Attributes are now plain (value persists in the block's comment JSON → populated in `render.php`); `save()` returns `null` (fully dynamic, no block-validation churn). The green suite missed it because the fixture injected `$attributes` directly into `render.php`.
- **Pull-quote block lost its brutalist frame** (`assets/css/critical.css`) — the box treatment (top/bottom rules, padding, `asphalt` field) targeted only `.sn-pattern-pull-quote`, so the block (which emits `.sn-pull-quote`) rendered as bare text. The box selector now targets `.sn-pull-quote` too.
- **Blocks were unstyled in the editor canvas** (`inc/setup.php`) — `critical.css` (which holds all sidenote/pull-quote styling) was not in `add_editor_style`; added so the blocks preview correctly while editing.
- **Empty front-matter binding slots ate a flex gap** (`assets/css/critical.css`) — when a binding returns `null` (most posts have no pillar; or the plugin is absent) WordPress keeps the slot's empty fallback `<p>`, which still consumed a row gap. Empty `.sn-post-frontmatter__rt`/`__pillar-slot` slots are now hidden, and the pillar slot carries the right-aligning auto margin the inner anchor used to.
- **Pillar binding ignored the resolved post context** (`inc/block-bindings.php`, `inc/post-frontmatter.php`) — the `pillar` key called `sn_post_pillar_shortcode()`, which reads the global post, defeating the callback's own `postId` resolution that the other keys honor (a latent wrong-post bug if the part is reused in a Query Loop). Extracted `sn_post_pillar_html($post_id)` and pass the resolved id; the shortcode delegates to it.
- **Command-palette trigger printed on every PDF** (`assets/css/print.css`) — the fixed trigger button is server-rendered site-wide; print.css now hides `.sn-cmdk-trigger`/`.sn-cmdk` alongside the other navigational affordances.
- **JSON Feed advertised an unreachable `feed_url`** (`inc/feed-json.php`) — `feed_url` pointed at the pretty `/feed/json/` path, which 404s until the plugin's next rewrite flush; it now advertises the always-live `?feed=json`, and a `<link rel="alternate" type="application/feed+json">` autodiscovery tag makes the feed findable. A zeroed/invalid post date is now omitted rather than serialized as an invalid `"date":false`.
- **Dangling `aria-controls` on the palette trigger** (`inc/command-palette.php`) — the button referenced the `#sn-cmdk` dialog that's built lazily on first open (a dangling IDREF on page load); dropped it (`aria-haspopup="dialog"` conveys the relationship).

### Tests

- **683 assertions across 26 suites, 0 failures** (was 610 across 20 at v9.10.0). Six new standalone CLI fixtures: `tests/feed-json.php` (8 — item shape, RFC 3339 dates, JSON escaping discipline, JSON Feed 1.1 round-trip, non-string date omission), `tests/feed-enrichment.php` (5 — Media RSS + `sn:` namespace declarations, `media:content` presence/absence by featured image, well-formed `<sn:readingTimeMinutes>` element), `tests/blocks-registry.php` (28 — `block.json` validity, plain-attribute guard against the `source:html` server-side-empty trap, `editorScript` handle-not-`file:` trap, behavioral `render.php` output, `.sn-pull-quote` box-selector guard, editor-script deps, category registration), `tests/block-bindings.php` (14 — source registration, `reading_time` formatting + floor, pillar/canonical/og_title resolution + null fallbacks, unknown-key and `postId`-context paths for both reading-time and pillar), `tests/colophon-template.php` (9 — `customTemplates` entry, template/pattern/footer wiring, `wp:pattern` slug round-trip), and `tests/command-palette.php` (9 — data-island shape, bounded recent query, HTML-decoded titles, `JSON_HEX_TAG` XSS contract). The post-review fixes were landed test-first (each defect reproduced as a failing assertion before the fix). Tests assert observable output, not just registration shape.

## [9.10.0] - 2026-06-07 — Reader experience I

**Released:** 2026-06-07.

**Headline:** A front-end-only batch that improves the reading and sharing experience on single notes and pages, plus two new whole-site Style Variations and editor polish. Eight features, no new wp-admin chrome, no dark mode, brutalist/white-first throughout. The per-post dynamic surfaces (Related Notes, the "Updated" line, the share row) are emitted via shortcodes that WordPress core resolves in templates/parts (`get_the_block_template_html()` / `render_block_core_template_part()` run `shortcode_unautop()` + `do_shortcode()` before `do_blocks()`); the theme also keeps belt-and-suspenders `render_block` bridges for parity with the `[current_year]` / `[sn_reading_time]` / `[sn_post_pillar]` (v9.9.1) convention.

### Added

- **Related Notes footer** (`inc/related-notes.php`) — single notes now end with a "Related Notes" block surfacing up to three other notes chosen by a shared-tag heuristic, rendered in the established `.sn-notes-row` two-column vocabulary (mono date + reading-time spec column / Bebas-Neue title). Wired into `templates/single.html` via the theme's `render_block` shortcode bridge. Degrades silently when there are no tag matches.
- **Print / save-as-PDF stylesheet** (`assets/css/print.css`) — a dedicated `media="print"` stylesheet for single posts and pages, conditionally enqueued in `inc/assets-frontend.php`. Strips site chrome (header, nav, footer, share row, related notes), flattens colour to black-on-white, and sets readable print typography so a browser "Save as PDF" produces a clean document.
- **Reader-visible "Updated" date** (`inc/post-updated-date.php`) — materially-revised notes now show an "Updated" line in the front-matter (`parts/post-frontmatter.html`), shown only when the modified date is meaningfully later than the published date so trivial edits don't churn the byline.
- **Copy-permalink + native Web Share** (`inc/post-share.php`, `assets/js/note-share.js`) — single notes gain a share row (`parts/post-closing.html`) with a copy-permalink button and, where the browser supports it, the native Web Share sheet. Progressive enhancement: the JS is conditionally enqueued and the row degrades to a plain copyable link without it.
- **Monolith + High Contrast Style Variations** (`styles/monolith.json`, `styles/high-contrast.json`) — two new whole-site variations selectable in the Site Editor, theme.json v3, settings-only (no `blockTypes`).
- **Hairline + Signal block styles** (`inc/block-styles.php`) — a `hairline` separator variation and a `signal` quote variation registered via `register_block_style` with `inline_style`, available in the block inserter's Styles panel.
- **Curated editor block palette** (`inc/editor-block-palette.php`) — a conservative `allowed_block_types_all` allowlist for the post/page editor, keeping every block the templates actually need while trimming inserter noise. Bails (returns the full set) on non-post contexts so the Site Editor is unaffected.

### Improvements

- **Brutalist caption + cite element styles** (`theme.json`) — `elements.caption` and `elements.cite` now carry brand-consistent typography (mono, restrained scale, 11px+ floor) so figure captions and citations read as part of the system instead of browser defaults.

### Fixed

- **Signal quote block style rendered inverted** (`inc/block-styles.php`) — the `signal` quote variation used a `bone` field (`#000000` black) with `void` text (`#ffffff` white), i.e. a black box with white text — the exact opposite of the theme's white-first brutalist vocabulary. It now uses an `asphalt` light field (`#f5f5f5`) with `bone` dark text (`#000000`) and the `blood` left rule. (theme.json slugs are deliberately inverted from their literal names.)
- **Hairline separator border colour was silently dictated by an unrelated rule** (`inc/block-styles.php`) — the base `.wp-block-separator{border-color:concrete !important}` in `components.css` overrode the Hairline's own non-`!important` border declaration (an `!important` always beats a normal declaration regardless of specificity). The Hairline now sets its own `border-top-color` with `!important` on the more-specific `.is-style-hairline` selector, so the style owns its colour.
- **Curated block palette wrongly applied in the Site Editor** (`inc/editor-block-palette.php`) — editing a *page* in the Site Editor sets `$context->post` to the page object, so the `empty($context->post)` firewall didn't catch it and the trimmed allowlist starved the Site Editor (which needs every block for templates). It now also bails when `$context->name === 'core/edit-site'` (verified vs WP trunk `WP_Block_Editor_Context`).
- **Synced patterns could vanish from the inserter** (`inc/editor-block-palette.php`) — `core/block` (the reusable/synced Pattern block) is now in the allowlist so synced patterns stay insertable in the post/page editor.
- **Print stylesheet didn't strip the Related Notes footer** (`assets/css/print.css`) — the Related Notes footer is a nav-like affordance that paper can't use, and the CHANGELOG already claimed print strips it. Added `.sn-related-notes` to the print hide list.
- **Copy-link label swap was silent to screen readers** (`assets/js/note-share.js`) — the COPY -> COPIED button-text swap changed the control's accessible name without re-announcing it. Each share row now carries a visually-hidden `aria-live="polite"` `role="status"` region that announces "Link copied to clipboard" / "Copy failed".

### Tests

- **610 assertions across 20 suites, 0 failures** (was 415 across 12 at v9.9.0), merged on top of v9.9.1. Eight new standalone fixtures (`tests/related-notes.php`, `tests/print-styles.php`, `tests/post-updated-date.php`, `tests/post-share.php`, `tests/style-variations.php`, `tests/block-styles.php`, `tests/editor-block-palette.php`, `tests/theme-json-elements.php`) plus colour-intent assertions in `block-styles.php` (resolving `var()` tokens to theme.json hex so an inverted palette fails red), a Site-Editor firewall case in `editor-block-palette.php`, and a print-CSS content assertion. The bridge fixtures exercise the real `do_shortcode` path rather than a str-replace stub.

## [9.9.1] - 2026-06-07 — Pillar shortcode render_block bridge (belt-and-suspenders)

**Released:** 2026-06-07.

**Headline:** Adds a `render_block` bridge for the `[sn_post_pillar]` frontmatter shortcode, completing the convention already used for `[current_year]` and `[sn_reading_time]`. **No user-visible change.** The pillar slot already resolved correctly on the front end — WordPress core `do_shortcode()`s a template part's raw markup *before* `do_blocks()` via `render_block_core_template_part()` — so this filter is belt-and-suspenders: redundant on the standard front-end path, kept for parity and as version-independent insurance if the frontmatter part is ever rendered outside the template-part render path (e.g. inlined into a pattern). Verified on the live front end before adding: `[sn_post_pillar]` was resolving, not leaking.

### Added

- **`sn_post_pillar_render_block()` bridge** (`inc/post-frontmatter.php`) — a `render_block` filter that `do_shortcode()`s any block whose content contains `[sn_post_pillar`. Mirrors the `[current_year]` bridge in `inc/setup.php`. A `strpos` guard makes it a no-op for blocks without the token.

### Tests

- **`tests/post-frontmatter.php` → 18 assertions (was 9).** Test 7 locks the front-end contract (core's template-part `do_shortcode` pass resolves the token before any bridge runs); Test 8 covers the bridge itself — registration on `render_block`, resolution when the token is present, and the `strpos` pass-through guard.

### Docs

- **Corrected `docs/WORDPRESS-REFERENCE.md` §1.2.** It claimed shortcodes are "NOT processed" in FSE template parts. They are: `render_block_core_template_part()` and `get_the_block_template_html()` both run `do_shortcode()` on raw markup before `do_blocks()`. The section now documents the real mechanism with source citations and notes that the shortcode bridges are belt-and-suspenders, not load-bearing on the front end.

## [9.9.0] - 2026-06-06 — Prep minor for v10.0.0 — 1 new ability + WP 7.0 pre-warning

**Released:** 2026-06-06.

**Headline:** v9.9.0 is the theme's prep-minor cycle for the v10.0.0 + plugin v5.0.0 paired-major event. It adds the one ability the v10.0.0 scope audit identified as a meaningful gap (`get-latest-theme-tag`), warns admins via a dismissible notice that **v10.0.0 will require WordPress 7.0**, and ships the CHANGELOG announcement so site owners can plan their WP upgrade. No front-end behaviour changes.

**v10.0.0 plan** (per `docs/superpowers/specs/2026-05-27-v5-and-v10-paired-cycle-design.md`):

- **HARD-raise `Requires at least: 7.0`.** The v10.0.0 theme will refuse to load on WordPress < 7.0.
- DROP pre-7.0 compat code (minimal — the theme is already mostly WP 7.0+).
- PROMOTE any `@deprecated` PHPdoc on the non-Ability surface to `_deprecated_function()` runtime warnings (removal scheduled for v11.0.0).
- (Conditional) theme.json v3 → v4 schema migration if WP ships v4 by then; otherwise defer to v11.0.0.

The dismissible WP-version notice in this release is the user-facing heads-up for that hard raise.

### Added

- **`signal-and-noise/get-latest-theme-tag` ability** (`inc/abilities-diagnostics.php`). Returns the latest GitHub release tag for the Signal & Noise theme as `{ ok, tag }`. Wraps the existing `sn_gh_latest_theme_tag()` update-integration helper (GitHub Tags API + cache + retry pipeline), exposing it to AI agents and automation that want to check whether a theme update is available. Read-only; `force_refresh` bypasses the cache.
- **WP 7.0 pre-warning admin notice** (`inc/admin-notice-wp-version.php`). Dismissible `notice-warning` on every wp-admin page when WP < 7.0, gated to `manage_options`. Per-user dismissal via the `sn_theme_dismissed_wp_version_notice_v990` user-meta sentinel. Self-contained file — deleted in v10.0.0 after the hard WP 7.0 raise.

### Changed

- **Total abilities registered: 13** (was 12 at v9.8.0) — diagnostics category grows from 4 to 5.

### Tests

- **415 assertions across 12 suites, 0 failures.** New: 17 assertions for `get-latest-theme-tag` (registration + behavioral tag/null/empty/force-refresh paths) in `tests/abilities-registration.php`; new `tests/wp-version-admin-notice.php` (4 tests — renders < 7.0, suppressed ≥ 7.0, suppressed when dismissed, suppressed for non-admin).

**Sequenced after:** plugin v4.x prep work + UAT stable. Per the paired-cycle spec, prep minors ship in sequence before the v5.0.0 / v10.0.0 BC convenes.

## [9.8.0] - 2026-06-05 — Notes-scoped search & archive

**Released:** 2026-06-05.

**Headline:** Search now lives inside the Notes archive, scoped to Notes only — no more header search icon. The v9.7.0 header `core/search` trigger rendered as a solid black "blob" (it dragged the theme's `.wp-element-button` chrome — a black icon on a black pill) and, more fundamentally, search didn't belong in the header. This release removes that trigger and the global Notes+Pages results template, and rebuilds search as a hand-rolled field on `/notes`: the index page becomes a searchable archive. `/notes/?s=term` runs a `post_type=post` query (Notes only — Pages excluded by construction), shows results in the existing catalog rows with a count and a Clear link, hides the pillar essays to focus, and paginates within the search. Any stray site-wide `/?s=` is funnelled to `/notes/?s=` so there is exactly one search surface.

### Added

- **Notes archive search** — a hand-rolled `<form role="search">` on `/notes` (no `core/search` block, so no `.wp-element-button` blob). Browse state shows the field above the index; search state hides the pillar essays + divider, echoes the query, shows a result count + Clear link, and renders matches in catalog rows with a branded empty state. (`inc/page-notes-render.php`)
- **`sn_notes_search_term()` / `sn_notes_pagination_add_args()`** — pure, unit-tested helpers; `sn_notes_query_posts()` now injects `s` when a term is present. (`inc/page-notes-render.php`)
- **`/?s=` → `/notes/?s=` funnel** — `sn_notes_search_redirect_target()` + a `template_redirect` (priority 1) redirect so all search lands on the single Notes surface. (`inc/page-notes-template.php`)
- **`tests/notes-search.php` (13 assertions), `tests/notes-redirect.php` (4 assertions)** — standalone fixtures for the new helpers.

### Removed

- **Header search trigger** — the `core/search` block + the `sn-header-actions` wrapper in `parts/header.html` (nav returns to a direct child of `.sn-header`); the `.sn-header-search` / `.sn-header-actions` CSS in `assets/css/components.css`.
- **Global posts+pages search** — `templates/search.html`, `inc/search-query.php` (+ its `functions.php` require and `tests/search-query.php`). Search is Notes-only now.

### Fixed

- **Header "black blob"** — the v9.7.0 search icon rendered as a solid black pill (black icon on the `.wp-element-button` black background). Removed at the root by dropping the block entirely.

## [9.7.0] - 2026-06-05 — On-site search

**Released:** 2026-06-05.

**Headline:** Adds a real on-site search experience. Previously a `/?s=` query fell through to `index.html` (a generic Query Loop) with no "results for X" heading, no result count, and no search input. Now a dedicated FSE `templates/search.html` groups results into **Notes** (posts) and **Pages**, with a header search icon and a refine field. The two grouped Query Loops use `inherit:false` and are made search-aware by one small filter (`inc/search-query.php`) that injects the search term via WordPress's `query_loop_block_query_vars` — guarded by `is_search()` plus a post-type discriminator so it never bleeds into unrelated custom queries. Note: the search-aware results render on the **front end only** (the filter isn't active in the Site Editor preview, by design).

### Added

- **`templates/search.html`** — grouped search results: a `query-title` "Search results for: …" heading, a refine `core/search` field, then a **Notes** group (`postType:post`, date · title · excerpt) and a **Pages** group (`postType:page`, title · excerpt), each with its own `query-no-results` message. (`templates/search.html`)
- **Header search trigger** — a `core/search` icon (`buttonPosition:"button-only"`) wrapped with the nav in a right-aligned `sn-header-actions` group; expands to an input (WP auto-enqueues the search Interactivity module; degrades to a non-expanding icon if blocked). (`parts/header.html`)
- **`inc/search-query.php`** — `sn_search_inject_term()` hooks `query_loop_block_query_vars` to set `s` on the two grouped loops when `is_search()` and the loop targets `post`/`page` (discriminator `sn_is_search_loop()`). Verified against WP core source: the filter runs only on the `inherit:false` path. (`inc/search-query.php`)
- **`tests/search-query.php`** — 10 assertions (discriminator true/false cases, term injected only on search pages for post/page loops, existing query args preserved). Theme suite total: **387 assertions across 10 suites**.

### Changed

- **Search results styling** added to `assets/css/components.css` — `.sn-search-section-label` hairline labels, `.sn-search-row` separators, `.sn-search-no-results`, and the `.sn-header-search` icon (bone → blood on hover, reduced-motion-safe). 11px floor throughout. (`assets/css/components.css`)

## [9.6.0] - 2026-06-05 — /notes index pagination

**Released:** 2026-06-05.

**Headline:** The `/notes` index now paginates. The custom PHP renderer queried `posts_per_page => 50` with `no_found_rows => true`, so the archive was an unbounded single page with no paging UI and a misleading "N / N" count. `/notes/?paged=N` now pages at a default of 20 per page (overridable by the plugin via the new `sn_notes_per_page` filter — Release 2), with a styled `paginate_links()` control and an honest grand-total count. Theme-only, no JS, no build step. Query-string paging was chosen over pretty paths (`/notes/page/2/`) because the exact-path short-circuit router strips the query string before matching, so `?paged=N` works with zero routing changes on an incident-prone page. At 13 published notes (< 20/page) the control stays hidden until published volume exceeds the per-page value — dormant, not broken.

### Added

- **Query-string pagination on `/notes`** — `/notes/?paged=N` at 20 notes/page. Two new tested helpers: `sn_notes_per_page()` (default 20, clamped [1,100]) and `sn_notes_current_page()` (`paged` query var with a `$_GET['paged']` fallback for the short-circuit router, floored at 1). (`inc/page-notes-render.php`)
- **`sn_notes_per_page` filter** — 5th theme↔plugin contract (the plugin *producer* arrives in Release 2; until then the contract-registry tests still enumerate the 4 contracts that have a live producer+consumer pair). The theme applies it with a default of 20 (works standalone); the plugin will optionally supply the configured value in Release 2. (`inc/page-notes-render.php`)
- **Styled `paginate_links()` control** — numbered `← 1 2 3 →` rendered after the index only when `max_num_pages > 1`. DM Mono numerals, 11px floor, current page in bone. (`inc/page-notes-render.php`)
- **`tests/notes-pagination.php`** — 16 assertions locking the helpers + pagination query args (default/override/clamp, query-var + `$_GET` fallback + floor, `posts_per_page`/`paged`/`no_found_rows`/`post_status`/`post_type`). Theme suite total: **377 assertions across 9 suites**.

### Changed

- **`/notes` count display now shows the grand total**, not the per-page row count — was `'%02d / %02d', $entry_count, $entry_count` (e.g. "20 / 20" on a 22-note archive); now `$query->found_posts`. (`inc/page-notes-render.php`)
- **`sn_notes_query_posts()` flips `no_found_rows` to `false`** (one extra COUNT query, negligible at tens of posts) to expose `found_posts` / `max_num_pages` that pagination requires, and now passes `posts_per_page` + `paged`. (`inc/page-notes-render.php`)
- **`/notes` build marker** bumped to `2026-05-30-pagination-v10` for deploy verification. (`inc/page-notes-template.php`)

## [9.5.2] - 2026-05-29 — Self-updater authenticates to GitHub (60/h → 5000/h)

**Released:** 2026-05-29.

**Headline:** The theme self-updater's GitHub tag-fetch (`sn_gh_latest_theme_tag`) only ever sent `Accept` + `User-Agent` — never an `Authorization` header — so every WP update-check spent from GitHub's **60/h unauthenticated** pool (shared per-server-IP on Cloudways). When exhausted, the fetch 403s, the function returns `null`, and the Updates page silently shows "no update available" even when a release exists. Now authenticates with the same `SNT_GITHUB_TOKEN` wp-config constant the plugin uses (both run in one WP process). Paired with plugin v4.5.6.

### Fixed

- **`sn_gh_latest_theme_tag()` now sends `Authorization: Bearer <SNT_GITHUB_TOKEN>`** when the constant is defined — 60/h → 5000/h. Conditional: absent constant → byte-for-byte the previous unauthenticated request, so no regression for tokenless installs. (`inc/wp-update-integration.php`)

### Added

- **`tests/updater-github-auth.php`** — 6 assertions locking the token-auth contract (Authorization present when defined, headers preserved, `defined()`-guarded fallback). Theme test total: **361 assertions across 8 suites**.

## [9.5.1] - 2026-05-29 — Post-ship QA audit fixes — skip-link a11y + duplicate title + sub-11px floor + reduced-motion scroll

**Released:** 2026-05-29. (CHANGELOG entry backfilled 2026-05-29 — the v9.5.1 commit + tag shipped without it due to an editor no-op; code + version were correct.)

**Headline:** Front-end accessibility + polish from the full-codebase QA audit (7 parallel review agents) run before the v9.6.0 cycle. No behavioural changes to settings, templates, or abilities. A follow-up PHPCS/WPCS handbook conformance pass (2026-05-29) confirmed the theme's PHP is clean (0 errors / 0 warnings) and added a committed `phpcs.xml.dist` + `composer run lint` workflow (dev-tooling only, no runtime change, no version bump).

### Fixed

- **Broken skip-link on `/notes`** — the "Skip to content" link targeted `#wp--skip-link--target`, but the custom `/notes` renderer bypasses WP's block-template pipeline (which is what stamps that id onto the first `<main>`), so the anchor dangled. The `/notes` `<main>` now carries `id="wp--skip-link--target"`. WCAG 2.4.1. (`inc/page-notes-render.php`)
- **Duplicate "Skip to content" link on every block-template page** — WP core's native skip-link (`<a id="wp-skip-link">`) and the theme's own `.sn-skip-link` both rendered. Core's duplicate is now hidden via an ID-specificity CSS rule; the theme's brand-styled link (first in tab order, same target) remains. (`assets/css/critical.css`)
- **Duplicate `<title>` on `/notes`** — the renderer echoed a manual `<title>` and `wp_head()` also emitted one via `title-tag` support (registered for the v8.5.5 TSF cutover). Removed the manual echo; core's `_wp_render_title_tag()` now owns it. (`inc/page-notes-render.php`)
- **Sub-11px type below the project floor** — `.sn-catalog-section-label`/`-count` (services + music pages) and `.sn-notes-section-label`/`-count` (`/notes`) rendered at `0.65rem` (10.4px). Raised to `max(0.7rem, 11px)`. (`assets/css/components.css`, `inc/page-notes-render.php`)
- **`scroll-behavior: smooth` now respects `prefers-reduced-motion`** — the one motion vector not already gated (the theme disables all keyframe animations, hover transitions, and View Transitions under reduce). Added a reduced-motion override. (`assets/css/base.css`)

### Tooling

- **Added `phpcs.xml.dist` + `composer require-dev`** (PHPCS + WordPress-Coding-Standards + PHPCompatibilityWP) with a `composer run lint` workflow. The theme's PHP passes clean (0/0). Dev-tooling only — `vendor/` is gitignored and nothing ships to the runtime site, so no version bump.

---

## [9.5.0] - 2026-05-27 — Cross-package listener tests + WCAG contrast baseline + v10 scope audit

**Released:** 2026-05-27.

**Headline:** v9.5.0 closes the consumer-side seal on all 4 plugin→theme filter contracts (mirrors plugin v4.4.0's producer-side lock from `tests/contracts-stub.php`), turns `docs/ACCESSIBILITY.md` measurements into machine-enforced WCAG contrast assertions, and produces the theme v10.0.0 scope audit. Convened after plugin v4.5.x gate (v4.5.1 shipped 2026-05-27 with the audit-driven dead-Suggest-button fix).

### Added

- **Cross-package listener tests** (`tests/cross-package-listeners.php`): theme-side seal for the 4 plugin→theme filter contracts. 25 assertions verify each listener is registered AND returns the documented type when invoked. Mirrors plugin's `tests/contracts-stub.php` producer-side lock from v4.4.0. Standalone fixture pattern with WP function stubs — no WP load required.
- **Build-time WCAG 2.1 contrast verification** (`tests/contrast-baseline.php`): pure PHP relative-luminance computation from `theme.json`'s palette. 20 assertions covering required slugs, AA-normal pairings (>= 4.5:1), AA-large pairings (>= 3.0:1), and baseline drift tolerance (±0.20). The tight `blood-on-asphalt` margin (4.60, only 0.10 above AA threshold) is locked — any deliberate palette evolution must update BOTH `theme.json` AND this test in the same commit. Negative-test verified during development: mutating `asphalt` `#f5f5f5` → `#dddddd` triggers 5 failures including the tight margin.
- **Theme v10.0.0 scope audit** (`docs/superpowers/specs/2026-05-27-v10.0.0-scope.md`): public surface inventory + dispositions across 7 dimensions (36 `sn_*` functions, 4 dispatched hooks, 4 contract listeners, theme.json v3 schema, 13 templates + 4 parts, 6 patterns, 12 abilities). 79 KEEP / 0 RENAME / 0 REMOVE / 1 SCHEMA-CHANGE-conditional (theme.json v3→v4, contingent on WP). Mirrors plugin's `2026-05-26-v5.0.0-scope.md`. Conclusion: v10.0.0 has no current driver — cap-drop intact.

### Changed

- Theme aggregate test count: 303 → **355 assertions** across 7 suites, 0 failed (348 pre-sidenote-fix → 355 after sidenote adds 7 assertions to patterns-registry).

### Fixed

- `tests/patterns-registry.php` now covers the `signal-noise/sidenote` pattern (discovered as untested gap during the v10.0.0 audit). Pattern was auto-registered by WP's pattern engine but had no test enforcement. +7 assertions (42 → 49 in that suite).
- `readme.txt` Stable tag drift: was `9.4.3`, now correctly tracks the shipped version (bumped directly to `9.5.0`, skipping intermediate stale values).

### Implementation notes

- v9.5.0 BC convened on **v4.5.x gate** (relaxed from v5.0.x per spec §1 — escape hatch from roadmap §3 exercised). Cap-drop intact; v10.0.0 stays unforced.
- Paired with [plugin v4.5.0](https://github.com/juanlentino/signal-and-noise-tools/releases/tag/v4.5.0) + [v4.5.1 post-ship patch](https://github.com/juanlentino/signal-and-noise-tools/releases/tag/v4.5.1). Plugin shipped first per spec §5 sequencing; this release reacts to it.
- Spec: [docs/superpowers/specs/2026-05-26-v4.5.0-and-v9.5.0-paired-design.md](docs/superpowers/specs/2026-05-26-v4.5.0-and-v9.5.0-paired-design.md).
- Plan: `docs/superpowers/plans/2026-05-26-v9.5.0.md` (this repo).

**Post-install user actions:**

- Install v9.5.0 via wp-admin → Dashboard → Updates.
- No cache purge required — no CSS or JS changes in this release. Theme version bump refreshes `sn_asset_ver()` mtime automatically.

---

## [9.4.6] - 2026-05-26 — Body heading scale for single-post context (h2/h3/h4 no longer beat h1)

**Released:** 2026-05-26.

**Headline:** theme.json maps body `h2` → `xx-large` (`clamp(3rem, 7vw, 6rem)`) and `h3` → `x-large` (`clamp(2rem, 4vw, 3.5rem)`) — both bigger than the `.sn-note-title` h1 (`clamp(2.5rem, 6vw, 5rem)`) at typical viewports. The body heading scale was calibrated for a catalog-hero h1 (`xxx-large = clamp(4rem, 10vw, 9rem)`) and never re-tuned after long-form post layout shrank h1 to 5rem max. Result: body section headings competed with or exceeded the post title visually.

**Why bumped:** real visual regression caught immediately after the v9.3.0 long-form post layout shipped. Affects every single-note post.

**Fix:**

- **`.single-post .wp-block-post-content h2.wp-block-heading`** — `clamp(1.875rem, 3.5vw, 3rem)`, `line-height: 1.15`, `margin-top: 1.8em`, `margin-bottom: 0.6em`. Caps at 3rem (48px) — clearly subordinate to the 5rem (80px) max h1.
- **`.single-post .wp-block-post-content h3.wp-block-heading`** — `clamp(1.5rem, 2.5vw, 2.25rem)`, `line-height: 1.2`, `margin-top: 1.6em`, `margin-bottom: 0.5em`.
- **`.single-post .wp-block-post-content h4.wp-block-heading`** — `clamp(1.25rem, 2vw, 1.75rem)`, `line-height: 1.25`, `margin-top: 1.4em`, `margin-bottom: 0.4em`.

At 1440px viewport: h1=80px → h2=48px → h3=36px → h4=28px. Each ~1.3–1.7× the next. Clean Bebas Neue rhythm.

**Files changed:**

- `assets/css/critical.css` — body heading scale block added immediately above the existing v9.3.0 drop-cap rules
- `style.css` — version 9.4.5 → 9.4.6

**Why this is decoupled from the PA-01 WCAG fix:** Audit D PA-01 flagged that body headings on `/notes/*` posts are stored as `<h3>` (h1→h3 skip violates WCAG 1.3.1 Level A). The proposed content sweep promotes them to `<h2>`. **The sweep is still pending** — the previous SQL recipe (`REPLACE` against literal `<!-- wp:heading {"level":3}`) matched 0 rows, indicating the heading blocks have additional attrs in the JSON or whitespace variations that broke the prefix match. v9.4.6's CSS fix lands the visual hierarchy regardless of the WCAG state — whether body headings are stored as h3 (today) or h2 (after the sweep), they render at the right size.

**Audit reference:** [`docs/superpowers/specs/2026-05-26-audit-d-perf-a11y-findings.md`](docs/superpowers/specs/2026-05-26-audit-d-perf-a11y-findings.md) §3 PA-01 — visual coupling.

**Tests:** 303 assertions / 5 theme suites — all green. CSS-only change; no test surface affected.

**Post-install user actions:**

- Install v9.4.6 via wp-admin → Dashboard → Updates.
- **Purge caches** — Signal & Noise → Dashboard → "Purge all caches" (the new CSS is `?ver=`-busted automatically by `sn_asset_ver()` mtime tracking, but Cloudflare + Breeze still need a poke). Or wait ~30s for natural cache rotation.
- Reload any `/notes/<slug>/` post — section headings should now sit clearly below the post title in size.

**Related — re-running the PA-01 SQL fix (separate from this release):**

The earlier SQL pattern (`'<!-- wp:heading {"level":3}'` literal) didn't match. Likely cause: block JSON has extra keys (e.g., `{"level":3,"className":"..."}`) so the closing `}` in the prefix fails. Try this diagnostic SELECT first to see the actual stored format:

```sql
SELECT
    ID, post_title,
    SUBSTRING(post_content, LOCATE('wp:heading', post_content), 100) AS heading_block_start
FROM wp_posts
WHERE post_type='post' AND post_status='publish'
    AND post_content LIKE '%wp:heading%level":3%'
LIMIT 5;
```

Once we see the actual format, a corrected `REGEXP_REPLACE` (MySQL 8+) can target the variations — happy to write it after seeing the SELECT output.

---

## [9.4.5] - 2026-05-26 — Tier B fixes — static template img lazy/async + footnote keyboard parity

**Released:** 2026-05-26. (CHANGELOG entry added as follow-up docs commit — the v9.4.5 tag itself was pushed without this entry due to a string-mismatch in the original edit; rather than re-tag, this entry lands separately as a CHANGELOG-only commit per CLAUDE.md's "CHANGELOG-only commits don't bump" rule.)

**Headline:** Two Tier B fixes from Audit D, both small and shipped together.

**Fixes:**

- **PA-08 (BUG-LOW → confirmed real) — Static template `<img>` tags now have `loading="lazy" decoding="async"`.** Live HTML probe of `/about/` and `/services/` confirmed that WP core's `wp_filter_content_tags` does NOT inject these attributes for block-template `<img>` tags that lack a `wp-image-<id>` class. Manual probe found 7 affected images: 1 portrait on `/about/` (`templates/page-about.html:31`) + 6 service-card images on `/services/` (`templates/page-services.html:92, 112, 143, 163, 208, 228`). All seven now explicitly carry the attributes. The header logo (`parts/header.html:8`) remains `loading="eager" fetchpriority="high"` — it's the LCP candidate per audit, intentional override.
- **PA-11 (BUG-LOW) — Footnote popover now opens on keyboard focus, not just pointer hover.** `assets/js/footnotes-popover.js` previously listened for `pointerenter`/`pointerleave` only — keyboard users tabbing through `<sup>` anchors in long-form notes got no preview. Added `focusin` + `focusout` listeners that mirror the hover behaviour (build popover on focus, dismiss on blur). Progressive enhancement — the underlying `<a href="#footnote-N">` scroll-to-footnote behaviour still works without JS, this just adds parity for keyboard users. `(pointer: coarse)` early-return remains (mobile keyboard-only navigation is rare enough that the simpler ungate-everything is fine).

**Files changed:**

- `templates/page-about.html` — 1 `<img>` augmented with `loading="lazy" decoding="async"`
- `templates/page-services.html` — 6 `<img>` augmented (same)
- `assets/js/footnotes-popover.js` — `focusin` / `focusout` handlers added alongside existing `pointerenter` / `pointerleave`
- `style.css` — version 9.4.4 → 9.4.5

**Audit reference:** [`docs/superpowers/specs/2026-05-26-audits-c-d-cycle-findings.md`](docs/superpowers/specs/2026-05-26-audits-c-d-cycle-findings.md) — Tier B PA-08 + PA-11.

**Tests:** 303 assertions / 5 theme suites — all green. PA-08 is HTML-attribute-only; PA-11 is event-listener add; no test surface change.

**Post-install user actions:**

- Install v9.4.5 via wp-admin → Dashboard → Updates (canonical) or `gh workflow run deploy.yml --ref v9.4.5` (emergency).
- View source on `/about/` and `/services/` — every `<img>` should now have `loading="lazy"` and `decoding="async"` (except the header logo, which stays `loading="eager"` as LCP).
- On any `/notes/<slug>/` post with footnotes, Tab to a footnote `<sup>` anchor — the popover should appear on focus (same as hover). Tab away — it dismisses.

---

## [9.4.4] - 2026-05-26 — Audit D fixes — Turnstile strip via script_loader_tag, reduced-motion gate, Tested up to: 7.0

**Released:** 2026-05-26.

**Headline:** Bundles two Tier A fixes from Audit D (perf + a11y) — closes the Turnstile leak onto `/notes/` (and any other route that short-circuits `template_redirect`) and gates two hover transforms on `prefers-reduced-motion`. Also bumps the `Tested up to:` header to WP 7.0 since the theme has been actively tested against 7.0 since v9.2.0.

**Why bumped:** PA-07 is a real perf regression (~17 KiB of Turnstile JS leaking onto every `/notes/*` page) and PA-12 is a real a11y issue (motion-sensitive users got hover transforms despite asking for none).

**Fixes:**

- **PA-07 (BUG-MED) — Turnstile + dns-prefetch no longer leak onto `/notes/`.** Pre-v9.4.4, the Turnstile strip lived inside a `template_redirect` priority-10 ob_start in `inc/frontend-filters.php`. But `inc/page-notes-template.php` registers a `template_redirect` priority-0 callback that calls `include $render; exit;` — the exit bypassed every later `template_redirect` hook, including the ob_start. Result: Turnstile script + dns-prefetch hint were emitted on `/notes/` and `/notes/<slug>/` despite those routes having no contact form. Fix: moved the strip from the ob_start to a pair of filters (`script_loader_tag` for the `<script>` tag, `wp_resource_hints` for the dns-prefetch) — both fire inside `wp_head()` regardless of the renderer short-circuit. The ob_start remains in place but only for its generator-meta-strip defense-in-depth role. Net: ~17 KiB render-blocking JS saved on every non-contact route.
- **PA-12 (UI-UX) — Service-card image scale + button hover translate now gated on `prefers-reduced-motion: no-preference`.** Two hover-transform rules in `assets/css/components.css` (service card image `scale(1.02)` at line 34-37; button `translateY(-1px)` at line 64-67) were emitting motion to users who asked for reduced motion. Wrapped both in `@media (prefers-reduced-motion: no-preference)` so the transform only applies for users who haven't opted out. Filter changes and box-shadow remain unconditional (those aren't motion per WCAG). Matches the gating already in place on the hero entrance, view-transitions, and `/notes` page reveal animations.
- **OBS-HYG-02 — `Tested up to: 6.9 → 7.0` in `style.css` header.** Theme has been actively tested against WP 7.0 since v9.2.0 (patterns + view transitions); the header was just lagging.

**Files changed:**

- `inc/frontend-filters.php` — replaced Turnstile regex strip inside ob_start with `script_loader_tag` + `wp_resource_hints` filters
- `assets/css/components.css` — wrapped two hover `transform` rules in `@media (prefers-reduced-motion: no-preference)` blocks
- `style.css` — version 9.4.3 → 9.4.4, Tested up to: 6.9 → 7.0

**Audit reference:** [`docs/superpowers/specs/2026-05-26-audits-c-d-cycle-findings.md`](docs/superpowers/specs/2026-05-26-audits-c-d-cycle-findings.md) §3 (PA-07), §5 (PA-12), §6 (OBS-HYG-02).

**Tests:** 303 assertions / 5 theme suites — all green. PA-07 fix is a filter swap with the same semantic outcome (Turnstile stripped on non-contact pages); PA-12 is CSS-only.

**Post-install user actions:**

- Install v9.4.4 via wp-admin → Dashboard → Updates (canonical) or `gh workflow run deploy.yml --ref v9.4.4` (emergency).
- Verify on a `/notes/<slug>/` page: view source, search for "turnstile" and "challenges.cloudflare.com" — both should return zero results (pre-v9.4.4 returned 2 references).
- With macOS System Settings → Accessibility → Display → "Reduce motion" ON, hover a service card on `/services/` — image should NOT scale (only the filter/color change). Hover a button — should NOT translate (only the shadow appears).

---

## [9.4.3] - 2026-05-26 — Drop cap toned down + post-closing prev/next parity

**Released:** 2026-05-26.

**Headline:** Two visual fixes from live-site verification after v9.4.2 install:

1. **Drop cap toned down** — v9.3.0 shipped the first-paragraph drop cap at 5rem (Bebas Neue, blood red), which occupied 4-5 body lines and overpowered the page. v9.4.3 reduces it to 2.5rem (occupies 2-3 lines, conventional editorial typography). Keeps the Bebas Neue character + blood-red color — same design language, less aggressive scale.
2. **Post-closing Previous/Next visual parity** — v9.4.2 added a Previous link paralleling the existing Next, but `.sn-post-closing__prev` had no CSS rules so it fell back to default styling (monospace red). v9.4.3 unifies the selector so both sides render with the same Bebas Neue large-cap display treatment for the post title.

**Files changed:**
- `assets/css/critical.css` — drop cap font-size + margin/padding tweaks; `.sn-post-closing__prev` added to existing `.sn-post-closing__next` selectors (combined-selector approach)

**Cap math:** theme patch 2/7 → **3/7** in v9.4.x. 4 patches remaining.

---

## [9.4.2] - 2026-05-26 — Test file security guards + Previous post link

**Released:** 2026-05-26.

**Headline:** Bundled patch from the v4.4.x + v9.4.x cycle audit. Two changes: (a) CLI-only guards on all theme `tests/*.php` files to close the info-leak surface flagged in the audit (parallel to plugin v4.4.2 which shipped the same defense for the plugin's 22 test files); (b) symmetric Previous/Next post navigation in `parts/post-closing.html` — readers can now move backward through publication order, not just forward.

**Changes:**

- **CLI-only guard on every theme `tests/*.php`** (5 files: abilities-integration, abilities-registration, patterns-registry, post-frontmatter, view-transitions). Top-of-file check:
  ```php
  if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) {
      http_response_code( 404 );
      exit;
  }
  ```
  Theme tests stub WordPress so no destructive trigger like the plugin's `contracts-smoke.php` — pure info-leak surface (function names, ability slugs, capability matrices). Closed.
- **`tests/.htaccess` with `Require all denied`** as Apache defense-in-depth.
- **`parts/post-closing.html`** now renders symmetric "← Previous" + "Next →" navigation via paired `core/post-navigation-link` blocks. Previously only the Next link was rendered, leaving readers at the most-recent post with no navigation target. Both links are wrapped in a flex `sn-post-closing__nav` group (space-between), each with its own label paragraph + `showTitle:true` nav link — matching the existing `sn-post-closing__next` pattern exactly.

**Audit reference:** [`docs/superpowers/specs/2026-05-26-v4.4.x-and-v9.4.x-cycle-audit-findings.md`](docs/superpowers/specs/2026-05-26-v4.4.x-and-v9.4.x-cycle-audit-findings.md) — Bug-A2 (test file info-leak) + U-10 (Next-only navigation).

**Tests:** 303 assertions / 5 suites — all green (no PHP changes affect tests; CSS unchanged from v9.4.1).

**Cap math:** theme patch 1/7 → **2/7** in v9.4.x. 5 patches remaining.

---

## [9.4.1] - 2026-05-26 — Sidenote justification regression fix

**Released:** 2026-05-26.

**Headline:** v9.4.0's typography polish accidentally included sidenote patterns in the justified+hyphenated body-paragraph rule. The `signal-noise/sidenote` pattern emits a bare top-level `<p class="sn-sidenote">` (not nested in `<aside>` as v9.4.0's spec assumed), so the `.single-post .wp-block-post-content > p` direct-child selector matched it. At ≥1280px (where sidenotes float right into a 180px column), this produced catastrophic rivers and aggressive hyphenation. v9.4.1 excludes sidenotes explicitly via `:not(.sn-sidenote)`.

**Change:**

- [`assets/css/critical.css`](assets/css/critical.css) line 886 — selector changed from `.single-post .wp-block-post-content > p` to `.single-post .wp-block-post-content > p:not(.sn-sidenote)`. ~2 LOC delta.

**Why v9.4.0's spec was wrong:** the spec's selector-specificity defense table (§4.5) claimed "Sidenote is `<aside class="sn-sidenote">` containing its own `<p>` — also nested, not direct child. Excluded by `> p`." Actual `patterns/sidenote.php` source shows the pattern emits a bare `<p>`. The spec assumption was never cross-checked against the pattern source; the v9.4.x QA pass caught it.

**Visual impact:** at viewports <1024px no effect (rule doesn't apply). At 1024-1279.98px inline sidenotes were justified+hyphenated (looked unprofessional but readable). At ≥1280px the 180px-wide right-floated sidenote had severe rivers and forced hyphenation — visibly broken.

**Tests:** 303 PHP assertions / 5 suites, all green (CSS-only patch, no PHP impact).

**Cap math:** theme patch 0/7 → **1/7** in v9.4.x. 6 patches remaining.

**Plan reference:** This patch is the v9.4.1 candidate predicted by [v9.4.0 spec §10](docs/superpowers/specs/2026-05-26-v9.4.0-typography-polish-design.md) and confirmed by the v9.4.x post-ship QA pass.

---

## [9.4.0] - 2026-05-26 — Typography polish (justified + hyphenation + hanging punctuation)

**Released:** 2026-05-26.

**Headline:** Direct sequel to v9.3.0's editorial-spread layout — this minor brings the typography of running prose into the same register. Three CSS rules applied to body paragraphs in single-note `/notes/<slug>/` posts: justified text (≥1024px), hyphenation (≥1024px, both `-webkit-hyphens` and `hyphens`), and hanging punctuation (universal, Safari-only renders today). Smart quotes intentionally dropped — `wptexturize()` already handles them natively.

**Components:**

| # | Component | Selector | Gating |
|---|---|---|---|
| 1 | Justified text | `.single-post .wp-block-post-content > p` | `@media (min-width: 1024px)` |
| 2 | Hyphenation | `.single-post .wp-block-post-content > p` | `@media (min-width: 1024px)` |
| 3 | Hanging punctuation | `.single-post .wp-block-post-content` | Universal (progressive enhancement) |

**Browser support reality:**

| Component | Safari (macOS + iOS) | Chrome / Edge | Firefox |
|---|---|---|---|
| Justified text | ✓ Full | ✓ Full | ✓ Full |
| Hyphenation | ✓ Full (requires `-webkit-hyphens` prefix) | ✓ Full (unprefixed) | ✓ Full (unprefixed) |
| Hanging punctuation | ✓ Full | ✗ Not supported | ✗ Not supported |

Unsupported browsers cascade silently — no fallback rule needed; the effect simply doesn't render. Chrome/Firefox users see the same body-paragraph rendering as Safari users for justified + hyphenation; only hanging punctuation differs (Safari hangs; others stay inside the edge).

**Files affected:**

- `assets/css/critical.css` (+37 LOC in new `/* v9.4.0 — Typography polish */` section after line 866)
- `style.css` (Version: 9.3.0 → 9.4.0)
- `CHANGELOG.md` (this entry)

**Scope cuts** (explicit non-goals — see [spec §3.2](docs/superpowers/specs/2026-05-26-v9.4.0-typography-polish-design.md)):
- NO editor opt-in / per-post toggle (universal application matches v9.3.0 pattern)
- NO touching headings, lists, blockquotes, pull-quote bodies, sidenote bodies, footnote items, frontmatter spec card
- NO `<html lang>` change (relies on WP's `language_attributes()`)
- NO smart-quote work (`wptexturize()` handles)
- NO automated CSS tests (CSS-only change; manual smoke recipe in spec §5.2)

**Tests:** 303 PHP assertions across 5 theme suites — all green (unchanged; CSS-only change).

**Cap math:** theme minor 4/6 → **5/6** — **v9.5.0 is the only remaining minor before forced v10.0.0**. Theme patch resets to 0/7 for v9.4.x (7 patches available; anticipated triggers in [spec §10](docs/superpowers/specs/2026-05-26-v9.4.0-typography-polish-design.md)).

**Plan reference:** [`docs/superpowers/plans/2026-05-26-v9.4.0-typography-polish.md`](docs/superpowers/plans/2026-05-26-v9.4.0-typography-polish.md)
**Spec reference:** [`docs/superpowers/specs/2026-05-26-v9.4.0-typography-polish-design.md`](docs/superpowers/specs/2026-05-26-v9.4.0-typography-polish-design.md)

---

## [9.3.0] - 2026-05-26 — Long-form post layout (drop caps + footnotes + sidenotes + frontmatter spec card)

**Released:** 2026-05-26.

**Headline:** Single-note `/notes` posts now feel like a printed editorial spread — drop caps on the first paragraph (Bebas Neue 5rem blood-red), proper footnotes built on `core/footnotes` (brutalist styling + hover-popover JS), Tufte-style sidenotes via CSS float-right at ≥1280px (inline-below at narrower), and a frontmatter spec card above the post title (DATE / READ TIME / TAGS / PILLAR) mirroring the catalog row's DM Mono spec-row vocabulary.

**Scoped to single-note posts only.** The `/notes` catalog (`inc/page-notes-render.php`) and pillar essays at `/provenance/*` are unchanged.

**4 components:**
1. **Drop caps** — `.single-post .wp-block-post-content > p:first-of-type::first-letter` with Bebas Neue 5rem blood-red. The `:first-of-type` selector skips posts that open with a heading or quote naturally.
2. **Footnotes** — built on WP core's `core/footnotes` block (6.3+; theme requires 6.4+). Brutalist CSS: DM Mono blood-red `<sup>` markers; end-of-post list with zero-padded counter prefix (`01`, `02`, `03` — mirrors `patterns/steps-enumerated.php`); 1px bone separator. Hover-popover JS enhancement uses safe DOM cloning (no `innerHTML` — XSS surface eliminated). Mobile falls back to WP default scroll-to-footnote.
3. **Sidenotes** — new `signal-noise/sidenote` pattern (`patterns/sidenote.php`). CSS `float: right` at ≥1280px / inline-below with hairline at narrower. Pure CSS-only Tufte technique — no JS.
4. **Frontmatter spec card** — new `parts/post-frontmatter.html` template part replaces the v8.x `.sn-note-meta` group. Horizontal DM Mono spec row above the post title with DATE (blood) / READ TIME (blood) / TAGS (rust) / PILLAR (bordered, hovers blood). Mobile (<600px): wraps + pillar drops to its own line.

**New modules:**
- `inc/post-frontmatter.php` (~51 LOC) — `[sn_post_pillar]` shortcode + convention-based pillar tag-slug map
- `parts/post-frontmatter.html` (~30 LOC) — frontmatter block markup
- `patterns/sidenote.php` (~21 LOC) — `signal-noise/sidenote` pattern
- `assets/js/footnotes-popover.js` (~115 LOC) — hover-popover with safe DOM cloning

**Modified files:**
- `style.css` — bump `Version:` 9.2.1 → 9.3.0
- `functions.php` — `require_once __DIR__ . '/inc/post-frontmatter.php';`
- `templates/single.html` — replace `.sn-note-meta` group with `<!-- wp:template-part {"slug":"post-frontmatter"} /-->`
- `inc/assets-frontend.php` — conditional `wp_enqueue_script` for footnotes-popover.js on `is_singular('post')` at priority 30 (defer strategy)
- `assets/css/critical.css` — append 184 LOC: drop caps + footnote styling + popover styling + sidenote styling + frontmatter spec card

**Pillar map (convention-based):**

| Post tag slug | Pillar label | Links to |
|---|---|---|
| `provenance` | PROVENANCE | `/provenance/over-detection/` |

Add additional rows in `inc/post-frontmatter.php` `$pillar_map` as future pillar essays are published. Graceful degradation: posts whose tags don't match any pillar return empty string; the frontmatter spec card simply omits the PILLAR slot.

**Tests:**

| Suite | Assertions | Status |
|---|---|---|
| `tests/post-frontmatter.php` (new) | 9 (6 fixtures: no-tags, non-pillar-tags, provenance-tag, mixed-tags-with-provenance, null-post, shortcode-registration) | green |

Theme test totals: 85 + 154 + 42 + 13 + 9 = **303 assertions, all green**. Pure CSS components verified via manual UI smoke (matches v9.2.0 convention; theme has no automated CSS testing).

**Security note (footnote popover):** the JS module uses safe DOM cloning (`cloneNode` + `appendChild`) — never `innerHTML`. Source content (the footnote `<li>`) is already-sanitized WP `post_content`, but avoiding `innerHTML` eliminates the XSS surface entirely. Documented as plan decision D11.

**Cap math:** theme minor 3/6 → **4/6** (v9.0, v9.1, v9.2, v9.3 used; **2 minors remaining before v10.0.0**). Patch cap resets from 1/7 → **0/7** for v9.3.x (7 patches available).

**Plan reference:** [`docs/superpowers/plans/2026-05-26-v9.3.0-long-form-post-layout.md`](docs/superpowers/plans/2026-05-26-v9.3.0-long-form-post-layout.md)
**Spec reference:** [`docs/superpowers/specs/2026-05-26-v9.3.0-long-form-post-layout-design.md`](docs/superpowers/specs/2026-05-26-v9.3.0-long-form-post-layout-design.md)

**Carry-forward to v9.4.0:**
- Cluster "next-in-series" transitions (deferred from v9.2.0 brainstorm; carried again through v9.3.0)
- Callout boxes / definition list patterns (additional pattern library expansion)
- /notes catalog utility (search/sort) — deliberately deferred; tilts the "no subscription, no schedule" posture

---

## [9.2.1] - 2026-05-26 — Polish: theme tokens for v9.2.0 patterns + abilities-tests back-compat shim

**Released:** 2026-05-26 (same day as v9.2.0).

**What this patch fixes:**

1. **Pattern + post-closing CSS now uses theme design tokens** instead of generic web-safe hardcodes. v9.2.0 shipped with `Georgia, "Times New Roman", serif` for body text and `"Courier New", monospace` for mono — but the actual theme is `Bebas Neue + DM Mono` per `style.css` and `theme.json`. The mismatch made the new patterns feel bolted-on. Now everything resolves to `var(--wp--preset--font-family--body)` (DM Mono) for body and labels, with `var(--wp--preset--font-family--heading)` (Bebas Neue) reserved for the `post-closing__next-link` title — matches how actual post titles render via `core/post-title`, creating a "visual breadcrumb" from current post to next.
2. **Color hardcodes replaced with palette tokens:** `#111` → `bone`, `#666` → `rust`, `#444` → `rust`, `#fafafa` → `asphalt`, `#cc1414` → `blood`, `#ddd` → `concrete`. All 6 token names come from the existing `theme.json` palette (v9.0.0+). The new patterns now feel native, not bolted on.
3. **`sn_theme_register_abilities()` back-compat shim** added to `inc/abilities-registration.php`. The v9.1.7 abilities split refactored the monolithic function into 3 per-category registration functions, but `tests/abilities-{integration,registration}.php` weren't updated — they've been failing at the worktree baseline since v9.1.7. The shim restores the single-call entry point those tests expect.

**Tests post-shim:**

| Suite | Pre-v9.2.1 | Post-v9.2.1 |
|---|---|---|
| `tests/abilities-integration.php` | Fatal (sn_theme_register_abilities undefined) | **85 passed, 0 failed** |
| `tests/abilities-registration.php` | Fatal (same) | **154 passed, 0 failed** |
| `tests/patterns-registry.php` | 42 passed, 0 failed | 42 passed, 0 failed |
| `tests/view-transitions.php` | 13 passed, 0 failed | 13 passed, 0 failed |
| **Total** | (2 suites unreachable) | **294 passed, 0 failed** |

**What this patch does NOT touch:**

- Pattern HTML structures (`patterns/*.php`) — unchanged.
- `parts/post-closing.html` template part — unchanged.
- `inc/blocks-view-transitions.php` filter — unchanged.
- Any of the existing patterns / templates / abilities functions — purely additive.
- The plugin `tests/theme-ability-commands.php` expected-pattern-count bump (2 → 5) ships as v4.2.2 plugin patch, separate from this theme patch.

**Cap math:** theme minor unchanged at 3/6. Patch cap 0/7 → **1/7** for v9.2.x. 6 patches available.

---

## [9.2.0] - 2026-05-26 — Pattern library expansion + View Transitions catalog morph

**Released:** 2026-05-26.

**Highlights:**

- **Three new block patterns** tuned to /notes analytical content: `signal-noise/pull-quote` (brutalist thesis-statement callout), `signal-noise/compare-columns` (A vs B framing with vertical divider), `signal-noise/steps-enumerated` (monospace 01/02/03 auto-numbered list). All cluster under the existing "Signal & Noise" pattern category in the inserter.
- **`post-closing` template part** auto-rendered on every /notes post. Renders tags row + chronologically-next post link below `post-content` (above the existing pillar-link + back-link footer). Zero content changes — purely additive template wrap.
- **View Transitions catalog morph.** Clicking a note card on /notes morphs the title smoothly into the single-note article hero. Implemented as a `render_block` filter on `core/post-title` that injects `view-transition-name: sn-note-<slug>` — same hook covers BOTH catalog cards and the single-note hero. Mobile carveout disables the morph on small screens where the geometry change feels disorienting.

**Architecture notes:**

- Pattern category infrastructure already existed (`inc/patterns.php`, v7.5.0) — new patterns just declare `Categories: signal-noise` in their docblock.
- The View Transitions filter approach (single `render_block` hook) was chosen over per-template editing because block themes render the catalog via core/query loops where per-post inline styles can't easily be emitted from template markup. The filter covers every render of `core/post-title` anywhere on the site, not just the catalog — so the morph works from any source page that links to a single note.
- Reduced-motion respect inherited from v9.0.0's `@media (prefers-reduced-motion: reduce) { @view-transition { navigation: none; } }`. Per-element morphs auto-disable when navigation is `none`.

**`list-block-patterns` ability:**

- Pattern count enumerated grows from 2 → 5 (no code change in the ability itself). The plugin's `tests/theme-ability-commands.php` expects 2; this test bump ships as a separate plugin patch (v4.2.2). The v9.2.0 theme ship intentionally does NOT couple to the plugin test count update.

**Tests:**

- 2 new test files in `tests/`: `patterns-registry.php` (42 assertions, green) and `view-transitions.php` (13 assertions, green).
- **Pre-existing baseline issue noted:** `tests/abilities-integration.php` and `tests/abilities-registration.php` have been broken since the v9.1.7 abilities split — both call `sn_theme_register_abilities()` which is undefined since the orchestrator refactor. This is orthogonal to v9.2.0; logged as a v9.2.1 candidate (the test files need updating to match the v9.1.7 architecture).

**Cap math:** theme minor 2/6 → 3/6 (v9.0, v9.1, v9.2 used; 3 minors remaining before v10.0.0). Patch cap resets to 0/7 for v9.2.x. Plugin untouched (stays at v4.2.1).

**Spec:** [docs/superpowers/specs/2026-05-26-v9.2.0-patterns-view-transitions-design.md](docs/superpowers/specs/2026-05-26-v9.2.0-patterns-view-transitions-design.md).

---

## [9.1.7] - 2026-05-25 ⚠️ patch-cap rollover

Structural refactor — theme-side companion to plugin v4.1.3. Closes audit finding **B-11 theme-side**: `inc/abilities-registration.php` was 1814 lines (12× the CLAUDE.md 150-line guideline). Now a 52-line orchestrator that requires 5 per-feature ability files. All 12 abilities still register identically.

⚠️ **This is the last allowed patch in v9.1.x** (7/7). Any subsequent theme change rolls to v9.2.0.

### Changed

- **[`inc/abilities-registration.php`](inc/abilities-registration.php): 1814 → 52 lines (97% reduction).** Now a thin orchestrator that `require_once`s the 5 split files below. Bootstrap require at [`functions.php:52`](functions.php) unchanged — drop-in swap.
- **Per-feature ability files added under `inc/`:**
  - [`abilities-helpers.php`](inc/abilities-helpers.php) (154 LOC) — Shared constants (`SN_THEME_BRAND_VOICE_SYSTEM`, `SN_THEME_NOTES_VOICE_SYSTEM`), AI helpers (`sn_theme_ai_helper_available`, `sn_theme_ai_unavailable_error`, `sn_theme_parse_ai_json`), pillar descriptors, and 2 named permission callables (`sn_theme_perm_read`, `sn_theme_perm_edit_posts`). The named permission callables replace the in-function closure pattern (`$permission_read`, `$permission_edit_posts`) — closures don't survive the split into multiple registration functions, but named callables work as `'permission_callback' => 'sn_theme_perm_read'` string references identically.
  - [`abilities-categories.php`](inc/abilities-categories.php) (56 LOC) — 3 category registrations on `wp_abilities_api_categories_init` (idempotent vs. plugin via `wp_has_ability_category()` guards).
  - [`abilities-diagnostics.php`](inc/abilities-diagnostics.php) (520 LOC) — 4 abilities: `get-active-template-structure`, `get-theme-version`, `get-design-system-summary`, `get-design-tokens`.
  - [`abilities-content.php`](inc/abilities-content.php) (337 LOC) — 3 abilities: `list-block-patterns`, `get-page-notes-pillars`, `get-reading-time-for-slug`.
  - [`abilities-ai-generation.php`](inc/abilities-ai-generation.php) (687 LOC) — 5 abilities: `ai-generate-page-note-summary`, `ai-suggest-block-pattern`, `ai-validate-brand-alignment`, `ai-generate-pattern-content`, `ai-rewrite-in-brand-voice`.

### Architectural notes

- WordPress's `add_action()` queues all callbacks for a hook regardless of registration order, so splitting one `wp_abilities_api_init` action into 3 parallel ones is semantically identical to the original.
- Cross-file impl calls work naturally because PHP function resolution is by global name. The diagnostic `sn_theme_ability_design_system_summary()` calls `sn_theme_ability_design_tokens()` (same file). The AI `ai-validate-brand-alignment` calls `sn_theme_ability_design_tokens()` (different file). The AI `ai-suggest-block-pattern` calls `sn_theme_ability_list_block_patterns()` (different file). All work because all 5 files are required before any hook fires.
- Helper file is required FIRST in the orchestrator because constants are evaluated at registration-call time. The 3 feature files can load in any order (action registration doesn't depend on impl functions being defined yet — `permission_callback` and `execute_callback` are resolved lazily when an ability is invoked, not when it's registered).
- Largest remaining file (`abilities-ai-generation.php` at 687 LOC) still exceeds the 150-line guideline. Further splitting (per-ability files) was rejected — the 5 AI abilities share the brand-voice mental model and per-ability fragmentation would isolate the JSON parsing + sanitization patterns that are intentionally consistent across them.
- The original file's docblock noted the registration functions were "public (not anonymous) so the test harness can invoke it directly." There is no PHP test suite in the theme repo (verification is structural mirroring vs. plugin v4.1.3 + manual smoke walk post-deploy). The new per-feature registration functions (`sn_theme_register_diagnostics_abilities` etc.) preserve the named-function pattern for future testability.

### Verification

- `php -l` clean on all 6 files (orchestrator + 5 split files).
- Plugin tests still green: `php tests/abilities-integration.php` → 157/0, `php tests/health-checks.php` → 76/0 (sanity-only — these tests don't exercise theme abilities, but they confirm nothing at the WP Abilities API level broke).
- No theme PHP test suite. Manual smoke walk post-deploy:
  1. wp-admin command palette ⌘K → type "design tokens" or "reading time" — abilities should appear if registered.
  2. `curl https://juanlentino.com/wp-json/wp-abilities/v1/abilities/signal-and-noise/get-design-tokens` → expect 401 (auth required), NOT 404.
  3. AI Copilot ⌘K dock → any `aiCallable: true` theme abilities should appear.

### Audit provenance

Closes audit finding B-11 across both repos (plugin shipped same fix as v4.1.3, commit `503b23a`, tag `v4.1.3`). All 50 audit findings tracked in `signal-and-noise-tools/.planning/audit-2026-05-25/` are now either shipped or formally deferred to Tier B/C per the v4.1.2-deferred-audit handoff.

**v9.x cap state:** patch **7/7** in v9.1.x ⚠️ — **THIS IS THE LAST PATCH** before forced rollover to v9.2.0 · minor 1/5 on 9.x.

## [9.1.6] - 2026-05-25

### Companion to plugin v4.1.1 audit pass

Audit-driven cleanup on the theme side. Two code changes + one documentation refresh.

**Added — `sn_gh_latest_theme_tag_result` filter (X-01).** Plugin v4.1.1 was calling theme function `sn_gh_latest_theme_tag()` directly via `function_exists` guard for its deploy-status card — a documented contract violation per `docs/WORDPRESS-REFERENCE.md §10` ("never let plugin code directly call a theme function — even with function_exists guards"). Added `add_filter('sn_gh_latest_theme_tag_result', 'sn_gh_latest_theme_tag')` in [`inc/wp-update-integration.php`](inc/wp-update-integration.php) so the plugin can fetch via filter dispatch. Now the deploy-status card is tolerant of theme-absent/inactive states by contract rather than by lucky timing. (`4991c94`)

**Removed — dead `self_heal_state` branch in `sn_purge_all_caches()` (X-07).** The branch read `SN_SELF_HEAL_LAST_CHECK_OPT` + `SN_SELF_HEAL_FAILURES_OPT` — both defined inside `inc/template-self-heal.php`, which was retired in v8.3.0. The `defined()` guards meant the entire branch was permanently dead code on the current codebase. Also removed the `'self_heal_state'` default from `$args` and the `@type bool` docblock entry. No behavior change — just deleting a misleading pointer to a retired module to reduce confusion for future maintainers. (`4991c94`)

**Refreshed — `docs/WORDPRESS-REFERENCE.md §10.0` (X-04 / X-05 / X-06 / X-08).** Section 10's "theme + companion plugin split" was stale by multiple versions. X-04: intro paragraph cleaned up — removed references to retired `inc/updater.php` + `inc/template-self-heal.php`, corrected "7 WP hooks" → "4 WP hooks", changed "split is partial as of v8.2.0" → "split is complete as of v8.4.0 / Tools v1.3.0 — no further migrations planned." X-05: added 3 missing files to the modules-in-theme list (`wp-update-integration.php` v8.5.0, `wp-update-git-preservation.php` v8.5.2, `abilities-registration.php` v9.1.0). X-06: removed the `SN_GITHUB_REPO / SN_THEME_SLUG` line from "Direct dependencies kept" — both constants went away with the v8.3.0 updater retirement; replaced with the actually-relevant dependencies (`[sn_reading_time]` shortcode, `sn_after_full_cache_flush` action). X-08: documented the theme/plugin namespace split for Abilities API (`signal-and-noise/*` in theme vs `signal-noise/*` in plugin) — designed v9.1.1 but never written down in §10.0 until now. Also added the new `sn_gh_latest_theme_tag_result` filter row to the contract hooks table. (`23588dd`)

**v9.x cap state:** patches 6/7 in v9.1.x · minors 1/5 in v9.x. (Patch cap close — next plugin/theme bump may justify a minor.)

## [9.1.5] - 2026-05-25

### Fixed — `sn_purge_all_caches` now invalidates plugin metadata cache

WordPress's `get_plugin_data()` caches plugin metadata in a private cache that isn't cleared by `wp_cache_flush()` alone. SSH-based plugin deploys (companion-plugin's `signal-and-noise-tools` Phase 2c pipeline) update the plugin file on disk but leave the cached metadata stale — so the SN admin Dashboard widget would report e.g. "PLUGIN 3.7.6 • v3.8.0 available" even after the v3.8.0 file was deployed. Two lines added to `sn_purge_all_caches()`'s `object_cache` branch:

- `delete_site_transient( 'update_plugins' )` — symmetric with the existing `update_themes` deletion
- `wp_clean_plugins_cache()` — symmetric with the existing `wp_clean_themes_cache()`

After this release deploys, every subsequent plugin deploy will properly refresh WordPress's cached plugin metadata as part of the standard cache-purge filter chain.

**Patch cap status:** 5/7 patches used in v9.1.x line (was 4/7). Two patches remain before rollover to v9.2.0.

**Companion ship:** plugin v3.8.1 ships shortly after this, completing the v3.8.0 IA reorg follow-up (sub-tabs + 6-entry submenu fix). See `signal-and-noise-tools/docs/superpowers/specs/2026-05-25-v3.8.1-sub-tabs-and-cache-fix-design.md`.

## [9.1.4] - 2026-05-25

### Changed — Deploy cache purge migrated from HTTP+App Password to SSH+wp-eval

Item D from the [2026-05-24 AI-readiness arc handoff](docs/superpowers/handoffs/2026-05-24-ai-readiness-arc-complete.md). Mirrors the architectural fix the companion plugin shipped in v3.7.3 (commit [`4e5addd`](https://github.com/juanlentino/signal-and-noise-tools/commit/4e5addd)).

**Before:** `.github/workflows/deploy.yml`'s final step did `curl -X POST https://juanlentino.com/wp-json/signal-noise/v1/purge-cache` using HTTP Basic Auth with `WP_DEPLOY_USER` + `WP_DEPLOY_APP_PASSWORD` GH secrets. The App Password was rotatable (and had been rotated at least once after the 2026-05-16 Phase 13 incident), creating a recurring operational task.

**After:** two new steps replace it — `Configure SSH for Cloudways` (writes the deploy key + known_hosts to the GHA runner) and `Purge caches via WP-CLI in-process` (SSHes in as the app-scoped `sn-plugin` user — same user the plugin repo uses — and runs `wp eval 'echo (int) apply_filters("sn_purge_all_caches_result", 0, array());'`). The empty `array()` arg is deliberate: theme deploys want all defaults including `template_overrides => true` (clears `wp_template`/`wp_template_part` DB records that mask updated theme files — the literal symptom of the 2026-05-07 "/notes still showing one card after Update" incident documented in `inc/template-maintenance.php`). The plugin's deploy passes `template_overrides => false` because plugin updates don't touch theme files; the theme deploy is the opposite case.

**Outcome:** After this release ships and a verified manual deploy succeeds, `WP_DEPLOY_USER` + `WP_DEPLOY_APP_PASSWORD` GH secrets on the theme repo can be deleted, and the corresponding App Password revoked in wp-admin → Users → Profile. With those gone, **the SN stack has zero rotatable credentials anywhere** (the Cloudways API key remains, but it's a platform-account credential, not a per-deploy rotatable secret). Cleanest rotation strategy is "no credential to rotate" — see [`feedback_eliminate_credentials_before_rotating.md`](.claude/projects/-Users-juanlentino-Projects-signal-and-noise/memory/feedback_eliminate_credentials_before_rotating.md).

**Patch cap status:** 5/7 patches used in the v9.1.x line. Two patches remain before the cap rollover to v9.2.0.

## [9.1.3] - 2026-05-24

### Added — AI-invocation integration tests + JSON Schema examples for tool-use accuracy

Continuation of the v9.1.2 AI-readiness pass. Two additions:

1. **85 integration tests** at `tests/abilities-integration.php` exercising `wp_get_ability($slug)->execute($args)` for all 12 abilities. Covers happy paths, capability denials, missing-required validation, enum violations, and edge cases. ([`d01137a`](https://github.com/juanlentino/signal-and-noise/commit/d01137a))
2. **JSON Schema `examples` arrays** on 11 input properties across 7 abilities. Improves LLM tool-use accuracy — models resolve parameter ambiguity faster when example values are visible in the function-calling tool spec. ([`8a6fab6`](https://github.com/juanlentino/signal-and-noise/commit/8a6fab6))

#### Test additions

`tests/abilities-integration.php` — NEW (685 lines, 85 assertions):
- Dispatch fundamentals (unknown slug returns null, 12 registered checks)
- Read happy paths (each of 7 read abilities → schema-conformant output)
- Read capability denial (anonymous visitor → `rest_forbidden`)
- Read validation (missing required slug, empty slug, invalid enum format, non-existent post)
- Generative AI gating (missing-required + subscriber denial + enum violations across all 5 AI abilities)
- Plugin AI helper unavailable path (`ai_helper_unavailable` error code)

Theme test count: 239 total (154 registration + 85 integration). All green.

#### Schema example additions

Verified real values where possible — `signal-noise/hero-dossier` is an actual registered plugin pattern, not a placeholder. Properties enhanced:

| Ability | Property | Example values |
|---|---|---|
| `get-reading-time-for-slug` | `slug` | notes-pillar slug examples |
| `get-design-system-summary` | `format` | enum examples (`markdown`, `compact`) |
| `ai-generate-page-note-summary` | `post_id`, `max_words` | integer examples |
| `ai-suggest-block-pattern` | `draft_content`, `topic_hint` | text + topic examples |
| `ai-validate-brand-alignment` | `content`, `content_type` | string + enum examples |
| `ai-generate-pattern-content` | `pattern_name`, `topic`, `tone_hint` | real pattern slugs + topic + enum examples |
| `ai-rewrite-in-brand-voice` | `source_text`, `intensity` | text + enum examples |

No validation changes; examples are advisory metadata. Non-breaking.

#### Files

- `inc/abilities-registration.php` — schema example additions on 11 properties
- `tests/abilities-integration.php` — NEW test file

#### Patch-cap status

Patch cap is 7 per minor. v9.1.3 is the 4th patch in v9.1.x. 3 patches remain before v9.2.0.

## [9.1.2] - 2026-05-24

### Refactor — AI-readiness preparation pass for the 12 theme abilities

Preparation work for [WordPress/desktop-mode PR #240](https://github.com/WordPress/desktop-mode/pull/240) (the future Agents framework, which will auto-harvest `wp_register_ability()` registrations into LLM-shaped tools). The 12 theme abilities shipped in v9.1.0 were correct but written for human/wp-cli consumption — this release tightens them for AI tool-calling consumption when the harvester ships.

No breaking changes. Pure quality improvements. Tests pass: 154 assertions, 0 failures.

#### What changed

**Security — explicit `read` capability check on read abilities** ([`5dacac0`](https://github.com/juanlentino/signal-and-noise/commit/5dacac0))

The shared `$permission_read` helper at `inc/abilities-registration.php:169` previously used `is_user_logged_in() ? true : current_user_can('read')`, which short-circuited the cap check on logged-in users. The `read` cap is held by every WP user from subscriber up, so this changed nothing in practice — but the explicit form is correct and removes a subtle bypass that could matter if WP ever changes default subscriber caps.

**Typed `items` schemas on every array-returning ability** ([`b9c66fe`](https://github.com/juanlentino/signal-and-noise/commit/b9c66fe), [`f51a8f2`](https://github.com/juanlentino/signal-and-noise/commit/f51a8f2))

Six abilities had `output_schema` properties typed as bare `array` with no `items` shape — consumers couldn't infer return structure from the schema. Now every array output declares its element shape:

| Ability | Array properties fixed |
|---|---|
| `list-block-patterns` | `patterns`, `categories` |
| `get-active-template-structure` | `template_part_slugs`, `blocks` (shallow summary shape: `blockName + attrs + innerBlocksCount`) |
| `get-page-notes-pillars` | `pillars` |
| `ai-suggest-block-pattern` | `suggestions` (`{pattern_name, reasoning, confidence}`) |
| `ai-validate-brand-alignment` | `findings` (`{dimension, verdict, note}`) |
| `ai-generate-pattern-content` | `warnings` |
| `get-design-tokens` | `fontFamilies`, `fontSizes`, `spacingScale`, `spacingSizes` (each with typed sub-properties matching theme.json conventions) |

The plugin's `signal-noise/list-cron-events` schema was the reference pattern — items.properties for every column.

**Annotation key normalization** ([`86ce984`](https://github.com/juanlentino/signal-and-noise/commit/86ce984))

Renamed `meta.annotations.read_only` → `readonly` (one-word, matches MCP convention + the plugin's existing usage). Twelve abilities affected. Non-behavioral; only affects filterability of the merged ability registry.

#### Why now

Reading WordPress/desktop-mode source ([`commands.php:145-153`](https://github.com/WordPress/desktop-mode/blob/trunk/includes/commands.php#L145), the [provider registry](https://github.com/WordPress/desktop-mode/blob/trunk/includes/ai-copilot/providers-registry.php), [PR #240](https://github.com/WordPress/desktop-mode/pull/240)'s Agents framework mock, and [issue #271](https://github.com/WordPress/desktop-mode/issues/271)) revealed that:

1. The future Agents framework will auto-promote `wp_register_ability()` registrations into AI tools, harvesting their `description` (LLM-visible), `input_schema` (parameters), `permission_callback` (capability gating), and `output_schema` (for documentation + testing). The quality of these fields directly determines how well our abilities function as AI tools.
2. We were planning to ship manual `desktop_mode_register_ai_tool()` wrappers in plugin v3.8.0 to bridge this gap. Reading PR #240 made it clear that work would be obsoleted by Agents — better to invest in making the underlying ability registrations great.
3. Desktop-mode maintainers have explicitly signaled they're waiting for WordPress Core's AI provider story to crystallize before adding more built-in providers (per [issue #271 comment](https://github.com/WordPress/desktop-mode/issues/271)).

This pass leaves the theme well-positioned for whichever harvester ships first.

#### Files

- `inc/abilities-registration.php` — permission helper + 7 schemas + 12 annotation key renames

#### Patch-cap status

Patch cap is 7 per minor. v9.1.2 is the 3rd patch in v9.1.x (after v9.1.1). 4 patches remain before v9.2.0.

## [9.1.1] - 2026-05-24

### Fixed — Theme abilities now classified as "Theme" in WP 7.0 Abilities Explorer

The 12 theme abilities shipped in v9.1.0 used the namespace `signal-noise/*` (matching the SN plugin's namespace for cohesion). But the `ai/ai` plugin's `Ability_Handler::detect_provider()` classifies an ability as "Theme" only when its namespace literally matches `get_stylesheet()` (the theme directory slug, which is `signal-and-noise`). Mismatch → classifier returned `Plugin`, so the Abilities Explorer + AI Capabilities dashboard widget showed `0 Theme` and our 12 abilities appeared in the Plugin bucket (when cache was fresh) or didn't appear at all (when OPcache/Breeze were stale).

#### Fix

Renamed all 12 theme ability slugs from `signal-noise/*` to `signal-and-noise/*`. The new namespace matches the theme directory slug → `detect_provider()` correctly returns `Theme`.

| OLD slug | NEW slug |
|---|---|
| `signal-noise/get-design-tokens` | `signal-and-noise/get-design-tokens` |
| `signal-noise/list-block-patterns` | `signal-and-noise/list-block-patterns` |
| `signal-noise/get-active-template-structure` | `signal-and-noise/get-active-template-structure` |
| `signal-noise/get-theme-version` | `signal-and-noise/get-theme-version` |
| `signal-noise/get-page-notes-pillars` | `signal-and-noise/get-page-notes-pillars` |
| `signal-noise/get-reading-time-for-slug` | `signal-and-noise/get-reading-time-for-slug` |
| `signal-noise/get-design-system-summary` | `signal-and-noise/get-design-system-summary` |
| `signal-noise/ai-generate-page-note-summary` | `signal-and-noise/ai-generate-page-note-summary` |
| `signal-noise/ai-suggest-block-pattern` | `signal-and-noise/ai-suggest-block-pattern` |
| `signal-noise/ai-validate-brand-alignment` | `signal-and-noise/ai-validate-brand-alignment` |
| `signal-noise/ai-generate-pattern-content` | `signal-and-noise/ai-generate-pattern-content` |
| `signal-noise/ai-rewrite-in-brand-voice` | `signal-and-noise/ai-rewrite-in-brand-voice` |

#### Cross-package coordination

Companion plugin v3.7.4 plan ([`signal-and-noise-tools/docs/superpowers/plans/2026-05-24-plugin-v3.7.4-ability-command-palette.md`](https://github.com/juanlentino/signal-and-noise-tools/blob/main/docs/superpowers/plans/2026-05-24-plugin-v3.7.4-ability-command-palette.md)) updated in lockstep — the 12 Command Palette command definitions reference the new `signal-and-noise/*` slugs. The plugin v3.7.4 release (not yet shipped) will land the commands using the renamed slugs.

#### Source-verification trail

Verified `Ability_Handler::detect_provider()` behavior from upstream source: [`WordPress/ai`](https://github.com/WordPress/ai/blob/develop/includes/Experiments/Abilities_Explorer/Ability_Handler.php) lines 200-213. The classifier's design assumes each integration namespaces abilities with its own directory slug; the SN-cohesion design (single `signal-noise/*` namespace for both plugin and theme) violated this assumption silently.

#### No functional changes

All 154 assertions in `tests/abilities-registration.php` still pass — the rename is purely cosmetic to the API surface, not behavioral. Abilities continue to function identically; only the classifier's bucket changes.

### Files

- `inc/abilities-registration.php` — 12 `wp_register_ability()` slug renames
- `tests/abilities-registration.php` — 12 test assertion slug references updated to match
- `style.css` — version bump 9.1.0 → 9.1.1
- `functions.php` — module map comment line updated to reference v9.1.1

## [9.1.0] - 2026-05-24

### Added — Theme-owned WP 7.0 Abilities API surface (12 new abilities)

The Signal & Noise theme becomes a first-class WP 7.0 Abilities API consumer-surface. Twelve new abilities expose the theme's design knowledge and brand-aware generative capabilities to any AI consumer (the companion plugin's AI features, the WP 7.0 AI Copilot, WP-CLI agents, future integrations) — making every AI-driven feature brand-aware instead of brand-blind.

**Read abilities (7):**

1. `signal-noise/get-design-tokens` — theme.json palette + typography + spacing scale (flattened name→hex map).
2. `signal-noise/list-block-patterns` — enumerates all registered block patterns + categories; optional category filter.
3. `signal-noise/get-active-template-structure` — shallow FSE block tree for a given post (by ID or slug).
4. `signal-noise/get-theme-version` — theme + WP environment metadata for drift detection.
5. `signal-noise/get-page-notes-pillars` — `/notes` pillar essay descriptors with reading time + last-modified.
6. `signal-noise/get-reading-time-for-slug` — wraps `sn_notes_reading_time_for_slug()` with typed integer output.
7. `signal-noise/get-design-system-summary` — pre-formats design tokens for AI prompt embedding (markdown / compact-text / json formats — typical 70-80% token reduction vs raw token JSON on compact-text).

**Generative abilities (5):** all call the companion plugin's `snt_ai_generate_with_constraints` helper (Sonnet 4.6 pinned via plugin v3.7.2+). Guarded with `function_exists` — if the plugin is missing, generative abilities return `WP_Error('ai_helper_unavailable')` with status 503 and a clear remediation message.

8. `signal-noise/ai-generate-page-note-summary` — single-sentence /notes-voice summary of a post.
9. `signal-noise/ai-suggest-block-pattern` — AI recommends 1-3 SN patterns for a draft; validates suggestions against the live registry.
10. `signal-noise/ai-validate-brand-alignment` — scores content (0-100) for fit with SN voice + palette across 5 dimensions.
11. `signal-noise/ai-generate-pattern-content` — fills a chosen pattern's shell with brand-voiced copy (no DB writes).
12. `signal-noise/ai-rewrite-in-brand-voice` — transforms external copy into SN voice; intensity + preservation flags.

### Theme test harness — new

`tests/abilities-registration.php` is the theme's FIRST test file. Establishes a standalone PHP test harness matching the companion plugin's `tests/health-checks.php` pattern: ~130+ assertions across the 12 abilities, no PHPUnit dependency, runs via `php tests/abilities-registration.php`. Covers happy paths, schema-validation, helper-unavailable fallbacks, markdown-fence stripping (per v3.7.0 Task B lesson), and the `error_log` instrumentation at every catch site (per v3.7.1 lesson).

### Architecture decisions

- **Theme-owned registration, not plugin-proxied.** Theme-domain knowledge (design tokens, patterns, /notes pillars) belongs in the theme. Lifecycle coupling makes natural sense: swap themes, the abilities swap with them. See [`docs/superpowers/specs/2026-05-24-theme-ai-abilities-design.md`](docs/superpowers/specs/2026-05-24-theme-ai-abilities-design.md) §3 for the full rationale.
- **Defensive category registration via `wp_has_ability_category` guard.** Per source-verified WP behavior at `class-wp-ability-categories-registry.php:57-67`, double-registration fires `_doing_it_wrong`. The plugin also registers `diagnostics`, `content`, `ai-generation`; the theme's first-mover guard handles theme-only / plugin-only / both-installed install states cleanly.
- **`function_exists('snt_ai_generate_with_constraints')` guard for generative abilities.** Theme→plugin coupling is a one-directional function call, not a filter. Brief windows where the theme ships before the plugin produce clean `ai_helper_unavailable` errors instead of fatals.
- **Cross-package filter contract stays at 3.** No new filters added. The existing `sn_purge_all_caches_result`, `sn_clear_template_overrides_result`, `sn_og_font_paths` from v8.4.0 are unchanged. See [`docs/WORDPRESS-REFERENCE.md`](docs/WORDPRESS-REFERENCE.md) §10.0.
- **Model pinning inherited from plugin.** Theme abilities don't re-pin the AI model — `snt_ai_generate_with_constraints` already pins Sonnet 4.6 (v3.7.2+). One source of truth, theme inherits.

### Files

- **Created:** `inc/abilities-registration.php` (12 abilities, 3 categories, 2 voice constants), `tests/abilities-registration.php` (harness + ~130 assertions)
- **Modified:** `functions.php` (+2 lines: module map + require_once), `style.css` (Version: 9.0.0 → 9.1.0), `CHANGELOG.md`

### Companion plugin v3.7.4 (separate release)

Command Palette (⌘K) commands for all 12 abilities ship in companion plugin v3.7.4 (separate release). WP-CLI access via `wp ability run signal-noise/*` works automatically once the theme is installed — no companion plugin update required for CLI consumption.

### Release-cap status

Minor cap is 5 per major (project override). v9.0 → v9.1 is well within the cap.

### Process

`superpowers:brainstorming` (architecture re-evaluated mid-session from plugin-proxied to theme-owned) → spec at `docs/superpowers/specs/2026-05-24-theme-ai-abilities-design.md` → plan at `docs/superpowers/plans/2026-05-24-theme-v9.1.0-ai-abilities.md` → TDD execution with subagent-driven development.

## [9.0.0] - 2026-05-20

### Added — WP 7.0 alignment + browser-native modernization

Three additive features for the WP 7.0 "Armstrong" launch day (also 2026-05-20):

1. **`settings.dimensions` opt-in** (`theme.json`): `width: true`, `height: true`, and 4 `dimensionSizes` presets (Hairline / Short / Medium / Tall — sizes `1px / 20rem / 32rem / 48rem`). Editors gain block-level width + height controls + a size-picker matching the SN spacing scale. Reference: [Dimensions Support Enhancements in WordPress 7.0](https://make.wordpress.org/core/2026/03/15/dimensions-support-enhancements-in-wordpress-7-0/).

2. **`settings.typography.textIndent: true`** (`theme.json`): Paragraph block gains a text-indent typography control. Reference: [New Block Support: textIndent](https://make.wordpress.org/core/2026/03/15/new-block-support-text-indent-textindent/).

3. **Cross-document View Transitions** (`assets/css/critical.css` +30 LOC): browser-native CSS `@view-transition { navigation: auto; }` rule + `view-transition-name` annotations on `.sn-header`, `.sn-footer`, `main`. Subtle fade between page navigations on Chrome/Edge 111+ and Safari 18+; silently no-op elsewhere. `prefers-reduced-motion: reduce` disables it via the standard media query.

**Note on View Transitions:** WP 7.0's own View Transitions are **admin-only** (smooth dashboard nav). The frontend opt-in here is the **browser CSS feature** ([CSS View Transitions Module Level 2](https://drafts.csswg.org/css-view-transitions-2/)) — same primitive WP itself uses, but for the site frontend instead of wp-admin. Theme-side adoption is independent of WP version.

### Cap rollover note

**v9.0.0 is a minor + patch cap rollover, NOT a semantic breaking change.** v8.x consumed patch 7/7 + minor 5/5 (per CLAUDE.md versioning rules), so the next functional theme change MUST roll to a new major. This release ships zero breaking changes — all settings additions are additive, no template / part / PHP changes, no removed CSS. Existing content renders identically.

### Files

- **Modified:** `theme.json` (+18 LOC for dimensions + 1 LOC for textIndent), `assets/css/critical.css` (+32 LOC for View Transitions), `style.css` (Version: 8.5.7 → 9.0.0), `CHANGELOG.md`

### Explicit non-changes

No template changes. No new PHP or `inc/` modules. No new JS. No new blocks. No removed CSS rules. No changes to existing dimensions or typography styles (only additions).

### Process

`superpowers:brainstorming` (with WP 7.0 Field Guide + 3 specific dev notes read mid-session) → spec written at `docs/superpowers/specs/2026-05-20-theme-v9.0.0-design.md` → executed inline due to small scope (~70 LOC across 2 files). Source-reading during the brainstorm caught a substantive scope error: my original bundle proposed "View Transitions + Block Visibility + Dimensions" as WP-7.0-native, but the dev notes revealed View Transitions are admin-only in 7.0 and Block Visibility's `theme.json` integration is deferred to 7.1. Corrected bundle ships actual adoptable features.

## [8.5.7] - 2026-05-18

### Hotfix — restore `.is-menu-open` styles to critical.css

User reported the 404 page looked "messed up" on a fresh incognito visit. Root cause: the v8.5.6 critical.css pruning removed the `.wp-block-navigation__responsive-container.is-menu-open` cascade on the theory that "it only renders after the hamburger tap, so it's below-the-fold." That conflates **render timing** with **interaction timing**. Tapping the hamburger can happen at any moment — including milliseconds after first paint, before deferred `layout.css` has fetched on slow/empty-cache connections. When that race happens, the menu renders with WP's default right-aligned vertical nav instead of the centered brutalist overlay.

### Fixed

- **Restored the 77-LOC `.is-menu-open` cascade to [assets/css/critical.css](assets/css/critical.css)** verbatim from layout.css. Critical-path styles must cover any user-triggered state that can fire before deferred CSS loads, regardless of where the visual element sits on initial paint.

### Kept

- The other v8.5.6 pruning stays:
  - Animations block (`@keyframes` + block-level entrance) — animations are progressive enhancement; missing them on first paint just removes the entrance fade, not the layout
  - `.wp-block-button__link` resting + hover — buttons can paint without hover styles, hover happens after mouseover (already past first paint window)
  - CF7 form rules — forms are deep below the fold; CSS race is improbable
- WCAG fixes from v8.5.6 (form `:focus-visible`, contrast darkening, 404 heading restructure) remain.

### CLAUDE.md correction

**Theme deploy is `workflow_dispatch:` only since v8.5.1** — not "auto-deploys on annotated-tag push" as CLAUDE.md previously claimed. Updated to match the workflow file. The canonical install path for theme updates is wp-admin → Dashboard → Updates (same as the plugin since v1.10.1).

### Notes

- **PATCH within `8.5.x`.** Patch headroom: 6/7 → **7/7 on 8.5.x — last patch slot used. Next change rolls to v9.0.0.**
- Lesson logged: critical CSS scope = "what can render before the deferred stylesheet roundtrip completes," not "what's above the fold geometrically." Different time horizons.

## [8.5.6] - 2026-05-17

### Audit consolidation — critical.css pruning + WCAG 2.1 AA fixes

Two parallel subagents (critical.css size review + WCAG 2.1 AA accessibility audit) both flagged work in the same files. Bundled into one patch.

### Fixed — critical.css pruning (176 LOC, ~5 KB removed)

[assets/css/critical.css](assets/css/critical.css) was 504 LOC; pure duplication or below-the-fold content accounted for ~35% of it. Four blocks removed:

- **Animations block (~41 LOC)** — 5 @keyframes + block-level entrance animation. Keyframes only need to load before the animation fires; block-level entrances animate content below the fold (`.wp-block-group`, etc.). Full definitions live in `assets/css/base.css` (deferred).
- **Button hover + transform (~26 LOC)** — `.wp-block-button__link` resting + hover + outline-style variants. Button hover is not first-paint critical. Full definitions in `assets/css/components.css` (deferred).
- **Mobile nav overlay (~77 LOC, ~40 `!important` declarations)** — the entire `.is-menu-open` cascade. Definitionally not above-the-fold — it only renders AFTER the hamburger tap. Full definitions in `assets/css/layout.css` (deferred).
- **CF7 form rules (~32 LOC)** — submit button + label styling. Forms are never above the fold. Full definitions in `assets/css/forms.css` (deferred).

Result: 504 → ~328 LOC. Further pruning of grain/scanline/scrollbar/skip-link sections deferred to a follow-up patch (medium-risk; needs visual verification to confirm zero FOUC).

### Fixed — WCAG 2.1 AA compliance (1 critical + 4 serious findings)

1. **Form fields now have proper `:focus-visible` outline** ([assets/css/forms.css](assets/css/forms.css)) — was bare `outline: none` on plain `:focus` (WCAG 2.4.7 critical fail; mouse clicks suppressed the indicator). Now: keep the border-color flourish for visual feedback, ADD a real outline only on `:focus-visible` (keyboard navigation).
2. **Global `:focus-visible` rule added** ([assets/css/base.css](assets/css/base.css)) — covers `a`, `button`, `[role="button"]`, `[role="link"]`, `input[type=submit|button|checkbox|radio]`, `.wp-block-button__link`, `summary`. Brand red (`var(--wp--preset--color--blood)`) outline with 3px offset, consistent across the theme. Replaces browser UA blue rings.
3. **Placeholder + Akismet notice color** `#999` → `#767676` ([assets/css/forms.css](assets/css/forms.css)) — was 2.85:1 contrast (fails WCAG 1.4.3 normal-text 4.5:1). Now 4.54:1 (passes).
4. **Form input borders** `#d9d9d9` → `#949494` ([assets/css/forms.css:72,177](assets/css/forms.css)) — was 1.39:1 (fails WCAG 1.4.11 non-text 3:1 for interactive UI components). Now 3.02:1 (passes). Surgical: only the input-element borders changed; `concrete` (`#d9d9d9`) stays the brand color for decorative separators where 3:1 doesn't apply.
5. **404 template heading hierarchy** ([templates/404.html](templates/404.html)) — the giant "404" digits were marked `<h1>` in concrete color (1.39:1 contrast, unreadable AND structurally wrong since they're decorative). "SIGNAL LOST" — the actual page identity — was an `<h2>`. Now: 404 digits are a decorative `<p aria-hidden="true">`, and SIGNAL LOST is promoted to `<h1>`. Fixes both WCAG 1.4.3 and 1.3.1.

### Notes

- **PATCH bump within `8.5.x`.** Patch headroom: 5/7 → **6/7 on 8.5.x**. Next minor still rolls to v9.0.0.
- The link-hover `#ff4c47` (3.4:1) finding was reviewed and accepted as-is — underline carries the affordance per WCAG 1.4.1 (color is not the only indicator); hover is transient. Could revisit if needed.
- Verified against actual WP Theme Handbook + WCAG 2.1 AA criteria. Visual treatment unchanged for sighted mouse users; keyboard + screen-reader experience materially improved.

## [8.5.5] - 2026-05-17

### Added
- **`add_theme_support('title-tag')` in [inc/setup.php](inc/setup.php).** Block themes do NOT auto-declare title-tag support — verified against [WordPress/wp-includes/theme.php on trunk](https://raw.githubusercontent.com/WordPress/WordPress/master/wp-includes/theme.php); no auto-declaration logic exists for block themes. Until now, The SEO Framework plugin was the only source of the `<title>` tag in `<head>`. With Phase 13 TSF cutover landing (companion plugin v2.0.0), WP core's `_wp_render_title_tag()` needs explicit theme support declared to take over title emission. Companion to plugin v2.0.0's `document_title_parts` filter, which controls the title format (still `Page Name — Site Name` matching what TSF emitted).

### Why this matters
- Without this declaration, deactivating TSF would leave the page with **no `<title>` tag at all**. That's an SEO catastrophe — title is one of the most-weighted on-page signals.
- The plugin's `document_title_parts` filter cooperates with WP-native title rendering rather than fighting it. Both pieces together produce the same brand format TSF was emitting, with zero user-visible change at cutover.

### Notes
- **PATCH bump within `8.5.x`.** From a user-visible perspective the page still has a `<title>` tag after this change — no new capability, no behavior shift. Pure infrastructure restoration of a capability TSF was previously providing externally.
- Cap headroom: 4/7 → **5/7 patches on 8.5.x**. Two patches of headroom remaining before next minor would roll to v9.0.0.
- Companion release: plugin v2.0.0 (MAJOR — TSF dependency dropped) shipping in the same session.

## [8.5.4] - 2026-05-16

### Fixed
- **`style.css` `Theme Name` header had a literal `&amp;` HTML entity** (`Theme Name: Signal &amp; Noise`) instead of a plain `&`. WP reads the header raw and renders it through its own escaping pipeline, so the entity got double-encoded to `&amp;amp;` and displayed in wp-admin Appearance → Themes as the literal text `Signal &amp; Noise`. Changed to plain ampersand: `Theme Name: Signal & Noise`.
- **`inc/wp-update-integration.php` `admin_init` version-change handler** now also calls `wp_clean_themes_cache()` on every detected version change. The parsed-theme-headers cache (set in `WP_Theme::get_data()` and friends) is invalidated automatically by WP's installer on `Update Now`, but the canonical SSH-checkout deploy path doesn't touch the installer — so the header cache went stale across each `gh workflow run` deploy. The watchdog mirrors the existing pattern for `sn_gh_latest_theme` + `update_themes` transient invalidation; same admin_init pageview, no new request overhead.

### Why this matters
- Theme name renders correctly in:
  - wp-admin → Appearance → Themes (the gallery + active-theme label)
  - wp-admin → Updates (when a theme update is available)
  - wp-admin → Plugins (cross-references to the theme by name)
  - Any third-party plugin's theme list (e.g., the desktop-mode dock submenu — the original visible-bug surface that surfaced this)
- Without the watchdog, every future SSH-checkout deploy that bumps theme version would leave the header cache stale until the next `wp_clean_themes_cache()` call (e.g., manual deactivation/reactivation). Now it self-heals on the next admin pageview.

### Notes
- **PATCH bump within `8.5.x`.** Bugfix to header metadata + cache watchdog; no functional behavior change.
- Cap headroom: 4/7 patches used on `8.5.x`; 3 remaining. Theme is at minor cap (5/5) — next minor rolls to **v9.0.0**.
- Companion fix shipped in plugin v1.15.1 (mirror watchdog for `wp_clean_plugins_cache()`).

## [8.5.3] - 2026-05-16

### Fixed
- **Theme update cache was too sticky.** Mirrors the plugin-side fix shipped in v1.11.1 — closes the asymmetry where the theme's `inc/wp-update-integration.php` still had the 12h TTL + no force-check support + no version-change cache invalidation, while the plugin had moved to a much more responsive model.
- **Three fixes** in `inc/wp-update-integration.php`:
  1. `sn_gh_latest_theme_tag()` gains an optional `$force_refresh` parameter that bypasses the cache.
  2. The `pre_set_site_transient_update_themes` filter callback now detects WP's force-check signals (`WP_FORCE_UPDATE_CHECK` constant OR `?force-check=1` query arg) and passes through to the new parameter. Clicking "Check Again" in `wp-admin/update-core.php` now actually re-fetches from GitHub.
  3. New `admin_init` hook stores the on-disk theme version in an option (`sn_last_seen_theme_version`). On every admin pageview, if the on-disk version differs from the stored last-seen, the GitHub-tag transient AND WP's own `update_themes` transient are cleared. This handles the upgrade-just-happened case automatically — whether the upgrade came via WP UI install or manual `workflow_dispatch` deploy.
- **Cache TTL reduced from 12 hours → 1 hour.** 12h was too long for "I just pushed a tag, where's my update?" Even with force-check working, the autonomous background poll cadence matters. 1h is responsive enough that pushed tags surface naturally within minutes-to-an-hour without any explicit user action.

### Behaviour
- Both probability lever (shorter TTL = cache misses oftener) and causality lever (version-change detection = cache MUST be wrong) are now in place together.
- No public-site emission change. No cross-package contract change.
- Docblock on `pre_set_site_transient_update_themes` filter clarified — theme transient uses arrays keyed by stylesheet, NOT stdClass objects keyed by basename like the plugin transient (subtle WP core quirk worth documenting).

### Notes
- **PATCH bump within `8.5.x`.** Bugfix in the update-detection path; no functional change to the theme's actual user-facing features.
- **Cap headroom:** 3/7 patches used on `8.5.x`; 4 remaining before minor rollover. Theme is already at minor cap (5/5) — next minor bump rolls to **v9.0.0**, not v8.6.0.
- Symmetry with plugin v1.11.1 was the right move: both repos now share identical cache-behavior code paths in their respective `wp-update-integration.php` files (modulo the theme/plugin transient shape difference).

## [8.5.2] - 2026-05-16

### Added
- `inc/wp-update-git-preservation.php` (200 LOC) — `.git`-preservation filter pair + admin_init self-recovery. Closes the footgun where clicking "Update Now" in wp-admin destroyed the theme's `.git` directory (via WP_Upgrader's recursive `clear_destination()`) and broke the canonical `gh workflow run deploy.yml` install path.

### How it works
- `upgrader_pre_install` (priority 10, accept_args=2) — atomically `rename()`s `.git/` → `wp-content/upgrade/sn-signal-and-noise-git-backup/` before WP's `clear_destination()` runs. Returns `WP_Error` to abort the install if the backup fails (better than silent .git destruction).
- WP runs its normal install (clear_destination + `upgrader_source_selection` rename of the unpacked archive dir → `move_dir`).
- `upgrader_post_install` (priority 10, accept_args=3) — atomically `rename()`s the backup back into the (now newly installed) destination dir. On WP-side install failure (WP_Error response), restores `.git` to the original theme dir so the rolled-back code keeps its checkout intact.
- `admin_init` self-recovery — on every admin pageview, if an orphaned backup is detected (post_install never fired — PHP timeout mid-install, fatal in another plugin's update hook, etc.), restore intelligently. Idempotent.

### Behaviour
- Both install paths now coexist. `gh workflow run deploy.yml --ref vX.Y.Z` stays the canonical/fast path; clicking "Update Now" in wp-admin no longer breaks the subsequent workflow_dispatch.
- Same-filesystem `rename()` is **atomic at the kernel level** — no window where `.git` exists in both places or neither. Cross-FS rename silently falls back to copy+delete (NOT atomic) — that's why the backup lives under `wp-content/upgrade/` (same mount as `wp-content/themes/` in standard WP installs incl. Cloudways).
- `inc/wp-update-integration.php` docblock updated to remove the "DO NOT CLICK UPDATE NOW" warning from v8.5.1 → both paths now safe.
- `functions.php` module map updated; `require_once` for the new file added below the existing wp-update-integration include.

### Verification
- WP core source re-fetched (`wp-admin/includes/class-wp-upgrader.php`) to confirm exact filter timing: `pre_install → source_selection → clear_destination → move_dir → post_install`. Pre_install can abort via WP_Error; post_install receives `$result['destination']`; `$hook_extra['theme']` stays populated through both.
- Mirrors plugin v1.10.1's `upgrader_source_selection` pattern from v8.5.1; adds the missing pre/post pair that the plugin also needs (queued as plugin v1.11.2).

### Notes
- This release ships via the canonical `gh workflow run deploy.yml --ref v8.5.2` (the new code is dormant on this install since workflow_dispatch is git-pull, not WP-installer). The filter pair activates only on the NEXT update if the maintainer chooses WP UI. After that first WP UI install, subsequent workflow_dispatch deploys should still succeed — confirming the footgun is closed.
- `error_log()` is used for restoration failures, not `WP_Error` — the WP install itself succeeded; a failed `.git` restore is post-hoc and shouldn't fail the install. The admin_init self-recovery retries on next pageview.

## [8.5.1] - 2026-05-16

### Changed
- `.github/workflows/deploy.yml` — trigger reduced from `push: tags: v*` to `workflow_dispatch:` only. Tag pushes no longer auto-deploy. Theme updates now land via the WP admin Updates page (the standard WordPress flow other site owners already use). Manual emergency-hotfix path: `gh workflow run deploy.yml --ref vX.Y.Z --repo juanlentino/signal-and-noise`.
- `inc/wp-update-integration.php` — replaced the `upgrader_pre_install` rejection (which blocked WP-driven installs because the legacy auto-deploy pipeline owned the .git checkout) with an `upgrader_source_selection` filter that renames GitHub's unpacked archive directory from `signal-and-noise-X.Y.Z/` to `signal-and-noise/` so WP installs to the active stylesheet slug.

### Behaviour
- After this tag is bootstrapped via one `gh workflow run`, future releases follow: edit code → bump `Version:` → CHANGELOG → tag → push tag → wait up to 12h for WP's cache to roll, or click "Check Again" in `wp-admin/update-core.php` → "Update Now". WP downloads the GitHub tag ZIP, the filter renames the unpacked dir, the new version overwrites the old one in place.
- No theme-side functional change. The cross-package contract surface (3 hooks documented in WORDPRESS-REFERENCE.md §10.0) is untouched.

### Notes
- Mirrors plugin v1.10.1's pattern exactly — same code shape, same filter pair, same emergency-hotfix workflow_dispatch fallback. Both repos now use WP-UI updates as the default install path.
- The first `gh workflow run` against v8.5.1 is a one-time bootstrap because pre-v8.5.1 the WP-side gate (the `upgrader_pre_install` WP_Error) would reject any install attempt. From v8.5.2 onward the WP UI flow works end-to-end.

## [8.5.0] - 2026-05-16

### Added
- `inc/wp-update-integration.php` — registers the theme with WordPress's native update system. Theme now appears in `wp-admin/update-core.php` and Appearance → Themes alongside other themes, showing current version and "up to date" status (or "update available" if auto-deploy ever falls behind a tag). ~120 LOC.

### Behaviour
- Polls GitHub Tags API every 12h (cached in `sn_gh_latest_theme` site transient). Picks the highest `v\d+\.\d+\.\d+` semver tag.
- Hooks `pre_set_site_transient_update_themes` to inject the theme into WP's update registry: into `->no_update` when local matches GitHub (the normal case under auto-deploy), into `->response` when GitHub is ahead.
- Hooks `upgrader_pre_install` to intercept "Update Now" with a WP_Error directing the maintainer to push a git tag instead — preserves the git checkout that auto-deploy depends on.

### Notes
- ~70 LOC of new code restores the user-facing visibility that was deleted in Phase 2b (`inc/updater.php` at 683 LOC) without bringing back the polling-heavy / self-heal / SHA-tracking machinery that auto-deploy made redundant.
- GitHub API is queried unauthenticated. 60 requests/hour limit per IP is plenty (cache TTL means 2 requests/day max). Graceful failure: empty cache for 1h on API error.

## [8.4.1] - 2026-05-16

### Fixed
- `style.css` Version field bumped to `8.4.1`. The v8.4.0 release shipped all the Phase 3 code changes correctly but the Version header in `style.css` was left at `8.3.0` due to an editor-tool sequencing error during the release commit. Cosmetic only — WP admin → Themes would show "Version 8.3.0" until this patch. No functional behavior depends on the field value.

## [8.4.0] - 2026-05-16

### Removed
- `inc/og-image.php` — moved to plugin `inc/og-card-generator.php`. Plugin generates OG cards via PHP GD; theme provides Bebas Neue + DM Mono TTFs through new `sn_og_font_paths` filter.
- `inc/reading-time.php` — moved to plugin `inc/reading-time.php`. Calculation + caching + `[sn_reading_time]` shortcode + `render_block` bridge all plugin-side.
- `inc/notes-and-provenance.php` (1,058 LOC) — moved to plugin and split into three smaller files: `inc/content-surfaces.php`, `inc/content-migrations.php`, `inc/content-rendering-helpers.php`.
- `inc/seed-content/` directory — moved to plugin alongside the migrations that consume it.

### Added
- `inc/og-fonts.php` — registers the theme's typefaces as the response to the plugin's `sn_og_font_paths` filter.

### Changed
- Cross-package contract surface grows from 2 hooks to 3 (added `sn_og_font_paths`).
- `docs/WORDPRESS-REFERENCE.md §10.0` updated to reflect the new contract.
- `functions.php` module-map docblock refreshed.

### Notes
- Requires plugin v1.3.0+ for full functionality. While plugin v1.2.0 is still active (during the ~30-60s deploy gap before plugin v1.3.0 ships), the `[sn_reading_time]` shortcode renders as the literal token string in any page that uses it (notably /provenance byline). Cosmetic, recoverable on next pageload after plugin v1.3.0 lands. Theme's `inc/page-notes-render.php` calls into reading-time via `function_exists()` guard — /notes index degrades gracefully (skips reading-time enrichment) rather than failing.

## [8.3.0] - 2026-05-15

### Removed
- `inc/updater.php` (~683 LOC) — GitHub-poll self-updater, obsolete since Cloudways auto-deploy (Phase 2a).
- `inc/template-self-heal.php` (~488 LOC) — file-drift recovery, redundant under atomic git-pull deploys.
- `inc/template-maintenance.php` — `upgrader_process_complete` hook + two `admin_init` detectors (version-change + template-mtime tracker), ~100 LOC.

### Added
- `.github/workflows/deploy.yml` — third step posts to `/wp-json/signal-noise/v1/purge-cache` after Cloudways `/git/pull` so theme deploys atomically refresh Cloudflare edge cache.

### Changed
- Cross-package contract surface shrinks from 7 hooks to 2. Updater filters (`sn_updater_branch`, `sn_updater_revcount`, `sn_updater_force_check`, `sn_updater_clear_error`) and the self-heal filter (`sn_self_heal_force_run_result`) are retired. Plugin v1.2.0 expects this and renders correctly.
- `docs/WORDPRESS-REFERENCE.md §10` updated to reflect the new contract surface (§10.1 + §10.2 marked retired).

## [8.2.1] — RSS Plausible Tracker migrated to companion plugin (early Phase 4 slice)

Brings the only Phase 4 file forward into the early-completion bucket, ahead of Phase 2's updater migration. The theme repo's `mu-plugins/` directory is now empty and deleted entirely. Tracking infrastructure (`wp_rss_feed_log` table, `sn_rss_tracker_*` options, `sn_rss_tracker_daily_prune` cron) lives in the [signal-and-noise-tools companion plugin v1.1.0](https://github.com/juanlentino/signal-and-noise-tools/releases/tag/v1.1.0) from this release onwards.

### Changed

- **Deleted `mu-plugins/` directory from the theme repo.** Contained `README.md`, `rss-plausible-tracker.php`, and `tests/bot-detection.php`. All three moved to the companion plugin (`inc/rss-plausible-tracker.php` + `tests/bot-detection.php`).
- **[`docs/WORDPRESS-REFERENCE.md`](docs/WORDPRESS-REFERENCE.md) §10.0:** updated to reflect Phase 4 partial completion. Phase 4 is now empty — the only file it was scheduled to migrate is in the plugin.
- **[`docs/WORDPRESS-REFERENCE.md`](docs/WORDPRESS-REFERENCE.md) §4, §5, §6, §7:** four `mu-plugins/rss-plausible-tracker.php` reference pointers updated to point at the new location in the companion plugin repo. §5's framing about "supports both install paths" rewritten to historical past tense.

### Coordinated plugin release

Ships alongside [signal-and-noise-tools v1.1.0](https://github.com/juanlentino/signal-and-noise-tools/releases/tag/v1.1.0). **No mandatory order** — the plugin's pre-flight guard #2 handles all scenarios:

- Plugin installed first, MU file still on server: plugin defers loading the rss tracker module to the MU file. Tracking continues uninterrupted.
- MU file deleted first, plugin not yet upgraded: tracking stops temporarily (data in `wp_rss_feed_log` is preserved). Resumes when plugin v1.1.0 lands.
- Both upgraded simultaneously (most likely scenario): guard sees MU file, defers. Then maintainer deletes MU file via SFTP. Next request, guard passes, plugin's module takes over.

### Migration steps for the maintainer

1. Click theme update in WP admin → installs v8.2.1 (deletes theme repo's `mu-plugins/` directory but does not touch the live server's `wp-content/mu-plugins/`).
2. Upload plugin v1.1.0 zip → activates new module loader.
3. Delete `wp-content/mu-plugins/rss-plausible-tracker.php` via SFTP (or `wp mu-plugin delete rss-plausible-tracker` via WP-CLI).
4. Next admin pageview → plugin's tracker module loads, admin notice clears.

### Why patch (not minor)

Structural file removal + docs updates. No new theme capability, no schema change, no breaking API change. The Phase 4 *milestone* completion is the plugin v1.1.0's minor bump; the theme's role is just cleanup. Patch bump.

### Spec

[docs/superpowers/specs/2026-05-15-rss-tracker-migration-design.md](docs/superpowers/specs/2026-05-15-rss-tracker-migration-design.md). Compact spec/plan combined since the scope is small.

## [8.2.0] — Phase 1 of theme + companion plugin split

First minor in the 8.x line. Nine modules (`seo.php`, `security-headers.php`, `cloudflare-purge.php`, `plausible-api.php`, `plausible-admin.php`, `plausible-widget.php`, `admin-bar.php`, `admin-page.php`, `rest-api.php`) moved out of `inc/` into the new companion plugin [`signal-and-noise-tools`](https://github.com/juanlentino/signal-and-noise-tools) `v1.0.0`. Cross-package coupling resolves via **7 WP hooks (5 filters, 2 actions)** — the theme registers the listener side; the plugin dispatches.

This is Phase 1 of a 4-phase split. Phase 2 will migrate the self-updater itself. See [docs/superpowers/specs/2026-05-15-companion-plugin-phase-1-design.md](docs/superpowers/specs/2026-05-15-companion-plugin-phase-1-design.md).

### Changed

- **[`functions.php`](functions.php) — 9 `require_once` lines removed.** Down from 20 to 11. Module-map docblock updated to reflect the reduced theme surface; companion plugin referenced.
- **[`inc/`](inc/) — 9 files deleted.** Files moved to companion plugin's `inc/` with same filenames preserved for parity.
- **[`inc/updater.php`](inc/updater.php) — 2 new functions + 4 hook listeners.** `sn_updater_force_check()` consolidates the cache-clearing sequence previously duplicated in `admin-page.php`, `admin-bar.php`, and `rest-api.php` (all of which moved to the plugin). `sn_updater_clear_error()` handles the lightweight error-dismiss path. Filter listeners on `sn_updater_branch` and `sn_updater_revcount` expose updater state to plugin code.
- **[`inc/template-maintenance.php`](inc/template-maintenance.php) — 2 filter listeners added.** Wrap existing `sn_purge_all_caches()` and `sn_clear_template_overrides()` for plugin dispatch.
- **[`inc/template-self-heal.php`](inc/template-self-heal.php) — filter listener added.** Wraps existing `sn_self_heal_force_run()` for plugin dispatch.

### Added (docs)

- **[`docs/WORDPRESS-REFERENCE.md`](docs/WORDPRESS-REFERENCE.md) §10.0** — new "Theme + companion plugin split" section documenting the contract surface (7 hooks: 5 filters + 2 actions), migration phases, and conventions for adding new cross-package interactions.
- **[`CLAUDE.md`](CLAUDE.md)** — companion plugin pointer added to the *Project* section.

### Contract surface (7 hooks)

| Hook | Type | Owner |
| --- | --- | --- |
| `sn_purge_all_caches_result` | filter | template-maintenance.php |
| `sn_clear_template_overrides_result` | filter | template-maintenance.php |
| `sn_self_heal_force_run_result` | filter | template-self-heal.php |
| `sn_updater_branch` | filter | updater.php |
| `sn_updater_revcount` | filter | updater.php |
| `sn_updater_force_check` | action | updater.php |
| `sn_updater_clear_error` | action | updater.php |

### Coordinated release

Ships with companion plugin `v1.0.0`. **Install order matters:**
1. Install + activate `signal-and-noise-tools` `v1.0.0` plugin first (download zip from `https://github.com/juanlentino/signal-and-noise-tools/archive/refs/tags/v1.0.0.zip`, WP admin → Plugins → Add New → Upload).
2. Click the theme's *Update* in WP admin to install `v8.2.0` (which removes the now-duplicate files).

During the brief window between steps 1 and 2, both packages have the 9 modules — WP registers hooks twice (duplicate admin menus, REST endpoints last-write-wins, dashboard widgets duplicated). The theme's menu entry continues to work; the plugin's menu shows but its purge/heal/check-updates buttons silently no-op until step 2 lands and registers the contract listeners. Maintainer should use the theme's menu entry during the window and ship the theme update promptly.

### Why minor

Meaningful capability shift — PHP includes shrink 45% (from 20 to 11), new contract surface introduced, theme becomes swappable in principle — but no breaking user-visible change. First minor in 8.x; well within the 5-per-major cap.

### Migration

None for end users; runtime behavior is identical after both releases land. For the maintainer: follow the install order above.

### Note on contract count vs spec

The spec ([2026-05-15-companion-plugin-phase-1-design.md](docs/superpowers/specs/2026-05-15-companion-plugin-phase-1-design.md)) anticipated 5 contracts. During execution, an audit grep of the moving files surfaced two additional cross-couplings (`sn_clear_template_overrides` and `sn_updater_revcount`) that the planning phase missed. Both are wired with the same contract pattern; the final count is 7. The spec is preserved as-is for historical accuracy.

### Spec + plan

- [docs/superpowers/specs/2026-05-15-companion-plugin-phase-1-design.md](docs/superpowers/specs/2026-05-15-companion-plugin-phase-1-design.md)
- [docs/superpowers/plans/2026-05-15-companion-plugin-phase-1.md](docs/superpowers/plans/2026-05-15-companion-plugin-phase-1.md)

Authored via the `superpowers:brainstorming` → `superpowers:writing-plans` → `superpowers:subagent-driven-development` skill chain.

## [8.1.1] — Handbook hygiene pass — strip i18n, refresh headers

Five mechanical hygiene items aligning the theme with the [WordPress Theme Developer Handbook](https://developer.wordpress.org/themes/) where it costs us little. The deliberate deviations (custom self-updater, external HTTP from theme code, business logic in `inc/`, `mu-plugins/` shipped from the theme repo) remain intentional and are NOT addressed here — they're documented in [docs/WORDPRESS-REFERENCE.md](docs/WORDPRESS-REFERENCE.md) §10 and accepted as the price of running a private single-site theme. The companion plugin split and inline-styles refactor are deferred to their own future phases.

### Changed

- **[`inc/setup.php`](inc/setup.php) — i18n bootstrap removed.** `load_theme_textdomain( 'signal-noise', ... )` and its docblock paragraph deleted. The function `signal_noise_after_setup_theme()` retains its `add_editor_style()` block. `Text Domain: signal-noise` in `style.css` kept as passive metadata.
- **[`inc/rest-api.php`](inc/rest-api.php) — 22 `__()` calls unwrapped.** All REST handler messages (`WP_Error` errors, `sn_rest_ok` success, sprintf placeholders) become plain string literals. JSON encoding is the rendering path; HTML escape was never applicable.
- **[`inc/patterns.php`](inc/patterns.php) — 2 `__()` calls unwrapped.** `register_block_pattern_category()` label + description become plain strings. The block editor's Patterns inserter now shows English directly.
- **[`inc/admin-page.php`](inc/admin-page.php) — 1 `esc_html__()` call unwrapped.** The permission-denied `wp_die()` message becomes `esc_html( '...' )` — escape preserved per original intent.
- **[`style.css`](style.css) — header updates.** Dropped stale `dark` tag (theme is white-first by design). Bumped `Tested up to: 6.8` → `6.9` (current WP is 6.9.4). Bumped `Version: 8.1.0` → `8.1.1`.
- **[`theme.json`](theme.json) — `$schema` bumped.** `https://schemas.wp.org/wp/6.7/theme.json` → `https://schemas.wp.org/wp/6.9/theme.json` for editor / IDE completion against current FSE schema.

### Why patch

All five items are mechanical changes to code or static metadata. No new user-visible capability, no schema migration, no breaking API change. First patch in the v8.1 line; well within the 7-per-minor cap.

### Migration

None required. Behavior is identical at runtime — string contents unchanged, function signatures unchanged, REST responses byte-identical (the `__()` calls already fell through to the source strings since no `.mo` file ever existed).

### Spec + plan

- [docs/superpowers/specs/2026-05-15-handbook-hygiene-pass-design.md](docs/superpowers/specs/2026-05-15-handbook-hygiene-pass-design.md)
- [docs/superpowers/plans/2026-05-15-handbook-hygiene-pass.md](docs/superpowers/plans/2026-05-15-handbook-hygiene-pass.md)

Authored via the `superpowers:brainstorming` → `superpowers:writing-plans` → `superpowers:executing-plans` skill chain.

## [8.1.0] — Notes subscribe info nested in hero (cap rollover from 8.0.7; not a new capability)

The v8.0.7 placement put the `<footer class="sn-notes-feed">` block in column 2 of the `.sn-notes-top` 5fr/7fr grid (because adding a third grid child to a 2-column grid placed it where the pillar essays section had been, displacing pillars to a second row). The visual result was co-equality with the hero — nothing read as the focal point. This release nests the subscribe info inside `<header class="sn-notes-hero">` as a single compact `<p>`, drops the standalone footer block, and lets the pillars section return to column 2 of the grid.

### Changed

- **[`inc/page-notes-render.php`](inc/page-notes-render.php) — markup.** Removed the `<footer class="sn-notes-feed">` block (was at the top of `.sn-notes-top` between hero and pillars after v8.0.7). Added `<p class="sn-notes-subscribe">` as the last child of `<header class="sn-notes-hero">`, with a `<span class="sn-notes-cursor">` blinking-cursor span at the sentence end. Single sentence: *"No subscription form. No schedule. Notes via RSS, or via email through Blogtrottr or Feedrabbit."* Three inline links (RSS internal, Blogtrottr + Feedrabbit external with `target="_blank" rel="noopener noreferrer"`).
- **[`inc/page-notes-render.php`](inc/page-notes-render.php) — CSS.** Removed the entire `.sn-notes-feed-*` rule block (`.sn-notes-feed`, `.sn-notes-feed-status`, `.sn-notes-feed-status a`, `.sn-notes-feed-status a:hover`, `.sn-notes-feed-cursor`, `.sn-notes-feed-note`, `.sn-notes-feed-note + .sn-notes-feed-note`, `.sn-notes-feed-note a`, `.sn-notes-feed-note a:hover`). Added `.sn-notes-subscribe`, `.sn-notes-subscribe a`, `.sn-notes-subscribe a:hover`, and `.sn-notes-cursor`. Renamed the selector inside `@media (prefers-reduced-motion: reduce)` from `.sn-notes-feed-cursor` to `.sn-notes-cursor`. The `@keyframes sn-blink` rule is preserved (referenced by the new cursor class).
- **Layout restored.** Pillar essays section now occupies column 2 of the desktop 5fr/7fr grid as it did in v8.0.6 and prior. The two-row layout introduced by v8.0.7 is gone.

### Why minor (cap rollover, not a new capability)

This change is patch-shaped — UX calibration, no new feature, no breaking API change, no migration. But v8.0.7 used the 7th and final patch slot in the v8.0 minor (per the project's 7-per-minor cap documented in [docs/VERSIONING.md](docs/VERSIONING.md)). The cap forces a roll to **v8.1.0**. Future-readers: the minor-digit bump reflects the cap rollover, not a new capability — read the `### Changed` section above for what actually shipped.

### Migration

None required. Placement-only change. Existing RSS subscribers unaffected. The `<footer class="sn-notes-feed">` element no longer exists in the rendered HTML; any external CSS or JS that selected it would break, but no external code does.

### Spec + plan

- [docs/superpowers/specs/2026-05-15-notes-subscribe-in-hero-design.md](docs/superpowers/specs/2026-05-15-notes-subscribe-in-hero-design.md) (supersedes the v8.0.7 spec which is preserved on disk with a SUPERSEDED banner).
- [docs/superpowers/plans/2026-05-15-notes-subscribe-in-hero.md](docs/superpowers/plans/2026-05-15-notes-subscribe-in-hero.md).

Authored via the `superpowers:brainstorming` (with visual companion) → `superpowers:writing-plans` → `superpowers:executing-plans` skill chain.

## [8.0.7] — Relocate /notes feed footer above the fold (move-and-replace)

The v8.0.6 email-via-RSS line landed in the right place semantically (`<footer class="sn-notes-feed">` at the bottom of `<main>`) but the wrong place practically — readers had to scroll past 7 note rows + 2 pillar essay cards before encountering the subscribe info. Functionally hidden for the first-impression case, which defeats the purpose of adding the line in the first place.

### Changed

- **[`inc/page-notes-render.php`](inc/page-notes-render.php) — `<footer class="sn-notes-feed">` block.** Relocated from its bottom-of-main position to immediately after the hero `<header>` (inside the `.sn-notes-top` wrapper, between hero and pillar essays section). Same markup, same CSS, same `aria-label`. The blinking cursor in `.sn-notes-feed-cursor` reads as "live feed status" at the top rather than "end of output" at the bottom — arguably more apt for a continuously-updating notes catalog.
- **[`inc/page-notes-render.php`](inc/page-notes-render.php) — `<hr class="sn-notes-rule">` removal.** Deleted the second `<hr>` (the one that previously preceded the bottom footer). The remaining `<hr>` between pillars and index is preserved — it still divides those two sections.

### Approaches considered + rejected

Documented in the design spec at [docs/superpowers/specs/2026-05-15-notes-feed-relocation-design.md](docs/superpowers/specs/2026-05-15-notes-feed-relocation-design.md):

- *Keep bottom + add compact top callout (redundancy)* — rejected; two design languages on one page.
- *New labeled "Subscribe" section between hero and pillars* — rejected as out of scope; would be most design-coherent with the catalog metaphor but adds a section the reader scrolls through before the pillar essays. Deferred for a future redesign pass if the simple relocation doesn't surface enough subscriptions.

### Not changed

- No CSS edits. Existing `.sn-notes-feed { margin-top/bottom: clamp(2rem, 4vw, 3rem) }` translates cleanly to the new context.
- No new copy, no new links, no new design tokens.
- `templates/page-notes.html` (FSE fallback) untouched — per [WORDPRESS-REFERENCE.md §10.4](docs/WORDPRESS-REFERENCE.md), it's deliberate incident-response infrastructure that's allowed to drift.
- `<footer>` element retained (vs. switching to `<aside>`). At HTML5-spec level, `<footer>` is not strictly position-bound; the markup-semantic shift to `<aside>` isn't worth a placement-only change.

### Why patch + cap note

Structural change to the live `/notes` renderer → patch bump per project rules. **Patch slot 7 of 7 in the v8.0 minor — the cap is now exhausted.** Any further bump in this branch rolls to `8.1.0`. Documented here so future-me doesn't try to ship `8.0.8`.

## [8.0.6] — Sync repo to live: drop Book a Call surface, add email-via-RSS hint to Notes footer

The live theme had drifted from this repo. The "Work With Me" Cal.com booking page was removed from production, the "Book a Call" nav link was pulled, and the strategy-call CTA was stripped from `/services` — but the repo still carried all of it. This release brings the repo in line with live, then adds one new line to the Notes-index footer pointing readers at email-by-RSS bridges (Blogtrottr, Feedrabbit) so the "no subscription form" line isn't a dead end for non-RSS-native subscribers.

### Drift removed

- **[`parts/header.html`](parts/header.html) — header nav.** Removed the "Book a Call" → `/work-with-me` `wp:navigation-link`. Live nav is now the canonical 7 items: Home, About, Services, Music, Resume, Notes, Contact.
- **[`templates/page-work-with-me.html`](templates/page-work-with-me.html) — deleted.** Cal.com booking page (tab bar + 30/60-minute embeds) is gone from production (`/work-with-me/` returns HTTP 404). The orphan template was the last theme-side reference; the page would re-spawn in the FSE template picker if left in place.
- **[`theme.json`](theme.json) — `customTemplates`.** Removed the `page-work-with-me` registration (was line 283). Without this, deleting the template file would leave WordPress trying to register a phantom custom template that has no source HTML — surfaces as a Site Editor template-picker entry that errors on selection.
- **[`templates/page-services.html`](templates/page-services.html) — closing CTA.** Removed the inline outline `wp:button` "Book a strategy call →" → `/work-with-me`. Closing CTA is now a single "Tell me about your project →" → `/contact` button, matching the live `/services/` page exactly.
- **[`patterns/cta-closing.php`](patterns/cta-closing.php) — deleted.** Two-button CTA pattern (`Tell me about your project →` + `Book a strategy call →` → `/work-with-me`). Pattern slug `signal-noise/cta-closing` was registered but not inserted by any template in the repo — orphan from the v7.5.x IA pass. Deleting the file removes it from the block inserter so the booking CTA can't be re-introduced by accident.
- **[`templates/home.html`](templates/home.html) — dead RSS-footer block.** Stripped the `<!-- RSS FOOTER -->` separator + spacer + `<p class="sn-notes-rss">` block. The `/notes` URL is rendered by [`inc/page-notes-render.php`](inc/page-notes-render.php) via a `template_include` short-circuit; the FSE template's RSS footer never fires. Cleanup, not a behavior change.

### Added

- **[`inc/page-notes-render.php`](inc/page-notes-render.php) — Notes footer second line.** Added `<p class="sn-notes-feed-note">For email, pipe the <a href="/notes/feed/">feed</a> through <a href="https://blogtrottr.com/">Blogtrottr</a> or <a href="https://www.feedrabbit.com/">Feedrabbit</a>.</p>` directly below the existing "No subscription form. No schedule." line. External links use `target="_blank" rel="noopener noreferrer"`. Closes the gap where readers who want email subscriptions had no path forward.
- **[`inc/page-notes-render.php`](inc/page-notes-render.php) — `.sn-notes-feed-note a` styles.** Mirrors the `.sn-notes-feed-status a` pattern (blood-red, no underline, hover slides in a 1px bottom border). Also added `.sn-notes-feed-note + .sn-notes-feed-note { margin-top: 0.4rem }` so the two adjacent footer lines don't touch.

### Why patch (not content-only)

Originally scoped as a content-only edit (one line added to the Notes footer). The audit revealed the live site had also dropped the booking surface entirely — nav link, page template, services-page CTA, and the orphan pattern. Per project versioning rules ([`docs/VERSIONING.md`](docs/VERSIONING.md)), structural template changes (deleted templates, deleted pattern, removed nav/button blocks) and CSS additions all bump version. So this ships as a patch even though the user-visible behavior change is minimal. Patch 6 in v8.0; within the 7-per-minor cap.

### Migration notes

None required. The `/work-with-me/` URL has been 404 in production for some time — this release just removes the stale theme-side surface. Existing Notes RSS subscribers are unaffected; the footer addition is additive. WP self-updater will offer the bump within ~30 seconds of push (per the v8.0.5 latency tighten).

## [8.0.5] — Tighten auto-surface latency from up-to-5-min to up-to-30-sec

The v8.0.1 auto-surface fix restored the "push → updater shows the offer" pipeline that had been broken since `fbd6b30`, but the perceived latency was still up to 5 minutes — long enough that an actively-iterating maintainer notices and complains. Reducing the freshness window collapses that gap.

### Where the latency came from

The admin_init warmer in [inc/updater.php](inc/updater.php) gates the background refresh on `SN_UPDATER_FRESHNESS` (was 5 min). Until the cache aged out, every admin pageview skipped scheduling a new fetch — even when the maintainer had just pushed. 5 minutes was a leftover from the pre-SWR architecture where the cache served the page-render path directly; a long TTL made sense there. With the SWR refactor in v7.3.1, the cache is read-only on the render path and refreshed in a non-blocking spawn_cron loopback. The freshness gate is now purely a soft rate-limit on outbound GitHub calls, not a render-latency knob.

### Changed
- **[`inc/updater.php`](inc/updater.php) — `SN_UPDATER_FRESHNESS`** reduced from `5 * MINUTE_IN_SECONDS` to `30` (seconds). Auto-surface latency goes from "up to 5 min" to "up to 30 sec." Comment block now documents the rationale for the chosen number.
- **[`inc/updater.php`](inc/updater.php) — `SN_UPDATER_RETENTION_SHORT`** reduced from `15 * MINUTE_IN_SECONDS` to `2 * MINUTE_IN_SECONDS`. A transient GitHub blip that lands an empty-sentinel in the cache should not lock auto-surface out of the next 15 minutes; 2 minutes is a more proportionate cooldown.

### GitHub API cost
Worst case during active admin browsing: ~120 calls/hour (one per pageview at the 30s floor). Token budget is 5000/hour, so ~2.4% utilisation in the busiest scenario. In normal use the loopback fires far less often.

### Why patch
Constant tweak. No functional change beyond timing. No schema change, no API change. Patch 5 in v8.0; within the 7-per-minor cap.

## [8.0.4] — Proper fix for the Gutenberg social-link relative-URL bug

v8.0.3 worked around the bug by hardcoding the full URL (`https://juanlentino.com/notes/feed/`) in the `wp:social-link` block. That fixed the symptom but coupled the template to a specific host — any future dev/staging environment would render a link pointing at production. This release replaces that hack with the structural fix.

### The upstream bug, exact source

WordPress core's `render_block_core_social_link()` in `wp-includes/blocks/social-link.php`:

```php
/**
 * Prepend URL with https:// if it doesn't appear to contain a scheme
 * and it's not a relative link or a fragment.
 */
if ( ! parse_url( $url, PHP_URL_SCHEME ) && ! str_starts_with( $url, '//' ) && ! str_starts_with( $url, '#' ) ) {
    $url = 'https://' . $url;
}
```

The comment says "not a relative link" but the check only recognises **two** flavors: protocol-relative (`//`) and fragment (`#`). Path-relative URLs (`/foo`) — which ARE relative links per RFC 3986 — fall through and get `https://` prepended, producing `https:///foo` (three slashes, empty host). Chrome silently normalises the result on click as `https://foo/...`, routing to a non-existent server. The check is missing a `! str_starts_with( $url, '/' )` branch.

### The fix in this release

A `render_block_data` filter in [inc/frontend-filters.php](inc/frontend-filters.php) intercepts every `core/social-link` block before WP core's render runs. If the block's `url` attribute starts with a single `/` (path-relative), it gets swapped for `home_url($path)` — which carries the correct scheme + host for whatever environment WordPress is running in. WP core then sees a complete URL with scheme and skips its broken prepend branch entirely.

```php
add_filter( 'render_block_data', function( $parsed_block ) {
    if ( 'core/social-link' !== ( $parsed_block['blockName'] ?? '' ) ) {
        return $parsed_block;
    }
    $url = $parsed_block['attrs']['url'] ?? '';
    if ( '' !== $url && '/' === $url[0] && ( ! isset( $url[1] ) || '/' !== $url[1] ) ) {
        $parsed_block['attrs']['url'] = home_url( $url );
    }
    return $parsed_block;
} );
```

### Why this is the right shape

- **No host hardcoded anywhere.** `home_url()` returns whatever the site is configured to be, so the same template renders correctly on dev, staging, and prod.
- **Catches every social-link, not just this one.** Any future `wp:social-link` block with a relative URL (Mastodon at `/mastodon/`, GitHub at `/code/`, whatever) gets the same correction. The trap can't be re-introduced via the template.
- **No-op when core is fixed.** The day WP core adds the missing `! str_starts_with($url, '/')` branch, this filter becomes redundant but harmless — the URL already has a scheme via `home_url()` so core's check passes either way. Comment in the filter documents this so a future maintainer knows when to remove it.
- **Doesn't touch the upstream code.** No monkey-patching `wp-includes/`, no wp-content/mu-plugins/ load-order risk, no override of a core function. Just a vanilla WP filter.

### Changed
- **[`inc/frontend-filters.php`](inc/frontend-filters.php) — new `render_block_data` filter** for `core/social-link` blocks. ~15 lines + a 16-line docblock. Sits alongside the existing skip-link / Spotify-embed / generator-stripping filters in the same file.
- **[`parts/footer.html`](parts/footer.html) — `wp:social-link` `url` attr** reverted from `https://juanlentino.com/notes/feed/` (the v8.0.3 hack) back to `/notes/feed/`. The inline comment now points at the filter so the relationship is discoverable from either direction.

### Upstream
No core ticket filed yet. File one at https://core.trac.wordpress.org/ if you touch this again — the fix in core would be a one-line addition (`! str_starts_with( $url, '/' )`) to the existing scheme check, after which this filter could be retired.

### Why patch
Same fix, better implementation. No new capability, no schema change, no user-visible difference vs v8.0.3 *except* that the template is now host-agnostic. Patch 4 in v8.0; within the 7-per-minor cap.

## [8.0.3] — Footer RSS link uses absolute URL (works around Gutenberg core bug)

The v8.0.0–v8.0.2 footer used a relative URL (`/feed/` then `/notes/feed/`) in the `wp:social-link` block's `url` attribute. WordPress core's `block_core_social_link_render()` callback in `wp-includes/blocks/social-link.php` does this:

```php
if ( $url ) {
    $url = esc_url( $url );
    if ( ! parse_url( $url, PHP_URL_SCHEME ) ) {
        $url = 'https://' . $url;
    }
}
```

The scheme check returns null for any path-relative URL, so core prefixes `https://`. Result for `/notes/feed/`: `https:///notes/feed/` — three slashes, empty host. Chrome silently normalizes this on hover/click to `https://notes/feed/` (treats "notes" as the hostname), routing the user to a non-existent server.

The same bug affected v8.0.0 with `/feed/` (rendered as `https:///feed/` → `https://feed/`); it just took someone hovering over the icon for the maintainer to notice. Caught from a screenshot showing the status bar.

### Changed
- **[`parts/footer.html`](parts/footer.html) — `wp:social-link` `url` attr.** `"/notes/feed/"` → `"https://juanlentino.com/notes/feed/"`. Hardcoding the host is acceptable here because this is a single-site theme — the host never moves. Inline comment in the template now documents the core-bug constraint so this trap doesn't get re-introduced.

### Why patch
URL string correction for a previously-broken link. No behavioural change beyond "the link now goes to the correct URL." Patch 3 in v8.0; well within the 7-per-minor cap.

## [8.0.2] — Footer RSS link points at /notes/feed/ (not /feed/)

The global footer's RSS icon was pointing at the site-wide WordPress feed (`/feed/`) when the canonical subscription surface for this site is the Notes feed specifically (`/notes/feed/`). The bottom of `templates/page-notes.html` already linked at `/notes/feed/`; this aligns the global footer with that existing pattern so both surfaces point readers at the same feed.

### Changed
- **[`parts/footer.html`](parts/footer.html) — `wp:social-link` `url` attr.** `/feed/` → `/notes/feed/`. One-attribute change in the existing Gutenberg core social-link block.

### Subscriber-tracking impact
None. The MU plugin's `template_redirect` hook fires on `is_feed()` regardless of feed slug, so requests to `/notes/feed/` are tracked the same way `/feed/` was — same `wp_rss_feed_log` row, same Plausible event. The `feed_url` column already captured the full URL per request, so the Plausible URL breakdown will simply show `/notes/feed/` as the dominant feed going forward instead of `/feed/`.

### Why patch
URL string correction. No behavioural change, no schema change, no API change. Patch 2 in v8.0; within the 7-per-minor cap.

## [8.0.1] — Restore auto-surface for theme updates after push to main

Fixes a regression introduced by [`fbd6b30`](https://github.com/juanlentino/signal-and-noise/commit/fbd6b30) ("Fix Updates page showing 'no updates' due to transient nuke") several minor versions ago. That commit fixed a real bug — `load-update-core.php` was clearing WP-Core's `update_themes` site transient mid-render, causing `list_theme_updates()` to read empty and falsely report "all up to date" — but its narrower gate (`if ( empty( $_GET['force-check'] ) ) return;`) removed a side effect the previous bug had been accidentally providing: every admin pageview was force-invalidating WP's update_themes transient, which in turn forced WP to re-run our `pre_set_site_transient_update_themes` filter against the fresh SN GitHub-cache. That side effect was what made pushes appear in the updater within ~5 minutes without any manual "Check Again" click.

Symptom of the regression: after pushing a new commit to `main`, the SN cache picks up the new SHA within 5 min (per the admin_init warmer + spawn_cron loopback), but WP-Core's `update_themes` site transient is gated by its own 7200-second freshness window in `_maybe_update_themes()`. During that window WP doesn't re-run our filter, so the fresh SHA goes nowhere visible. Maintainer experience: "I just pushed v8.0.0 and the updater doesn't see it."

### Changed
- **[`inc/updater.php`](inc/updater.php) — `sn_updater_refresh_cache()`.** Capture the previously-cached SHA before overwriting; after the new fetch lands, if the SHA actually moved, call `delete_site_transient('update_themes')` to force WP to re-evaluate the offer on the next admin pageview. Five-line addition. Safe to do here because this function runs in a `spawn_cron()` loopback context, not during a page render — the original `fbd6b30` race (clearing the transient mid-render of `update-core.php`) is not reachable from this code path.

### Why this is a patch (not a minor)
Bug fix in existing behavior. No new user-visible capability, no schema change, no API change. First patch in v8.0; well within the 7-per-minor cap.

### One-time activation step
Because this fix has to be present in the installed code for it to work, the very first deploy after this commit still requires a manual `?force-check=1` click — the broken state can't surface its own fix. After that one click → click Update → install 8.0.1, subsequent pushes auto-surface within ~5 minutes again.

## [8.0.0] — Site-wide RSS surfacing + server-side subscriber tracking + admin settings tab

RSS was previously only linked from a hairline footer on `/notes`. This release surfaces it on every page, adds a self-hosted-Plausible-backed measurement layer (no Jetpack, no FeedBlitz, no third-party tracker), and exposes the whole subsystem through a new **Appearance → Signal & Noise → RSS** settings tab. The measurement table is local to the database so a Plausible outage doesn't blank the trend data.

### Added
- **[`parts/footer.html`](parts/footer.html) — RSS subscribe link in the global footer.** New `<!-- wp:social-link {"url":"/feed/","service":"feed","label":"Subscribe via RSS"} /-->` inside the existing social-links list. Uses Gutenberg core's built-in `feed` service, which renders an inline SVG identical in weight to the other social glyphs and gets `aria-label`-equivalent semantics from a screen-reader-text span. Visible on homepage, `/provenance`, `/resume`, `/notes`, individual posts — everywhere `parts/footer.html` is included. URL hardcoded as `/feed/` because FSE template parts are pure HTML and don't execute PHP; same pattern `page-notes.html` already uses.
- **[`mu-plugins/rss-plausible-tracker.php`](mu-plugins/rss-plausible-tracker.php) — server-side feed-request tracker.** 482 lines, single self-contained file. Hooks `template_redirect` at priority 1, gates on `is_feed()`, drops requests whose User-Agent matches the bot regex (Googlebot/Bingbot/preview-card bots/curl/wget/uptime monitors — **but never aggregators**; see "Settings tab" below). For surviving requests: (1) inserts a row into the new `wp_rss_feed_log` table with UTC timestamp, first 16 hex chars of `sha256(UA)`, and the feed URL; (2) fires a fire-and-forget POST to the configured Plausible event endpoint with event name, full feed URL, and the `ua_hash` as a custom prop. Non-blocking + 2-second connect timeout so analytics never delays the feed response. Forwards the original `User-Agent` and Cloudflare's `CF-Connecting-IP` (with `X-Forwarded-For` / `REMOTE_ADDR` fallbacks) so Plausible's own bot detection and geo lookup function correctly.
- **`wp_rss_feed_log` table.** Columns: `id BIGINT PK`, `ts DATETIME`, `ua_hash CHAR(16)`, `feed_url VARCHAR(255)`. Index on `ts` for the rolling-window queries. Created via `dbDelta` on a version-gated `admin_init` hook (MU plugins have no activation hook, so we install lazily and idempotently — at most one option read per admin pageview).
- **`sn_rss_tracker_settings` option + new admin tab — Appearance → Signal & Noise → RSS.** Hosts everything operational about the tracker: enable/disable toggle, Plausible event endpoint URL, Plausible site domain, custom event name, and log retention window (7–365 days). All form-edited per host, no code changes needed when the Plausible install moves or the event name changes. The tab also renders three activity cards (24h / 7d / 30d, each showing total + unique clients), a 20-row recent-requests table, "Open in Plausible" deep link, and a maintenance section for purging old log entries. Form submissions flow through `admin_init` with nonce + `manage_options` capability gates, then redirect with a flash query arg.
- **Updated dashboard widget — "RSS Subscribers (30 days)".** Still surfaces the headline 30-day count and unique-client figure for at-a-glance visibility on the WP dashboard, now with a "Settings & activity" link to the new RSS tab and a Plausible deep link built from the configured domain + event name (not the hardcoded values it used to embed).
- **[`mu-plugins/tests/bot-detection.php`](mu-plugins/tests/bot-detection.php) — standalone fixture test.** 33 fixtures covering real aggregator UAs (Feedly, NewsBlur, Inoreader, NetNewsWire, Reeder, Tiny Tiny RSS, Miniflux, FreshRSS, BazQux, The Old Reader), three modern browsers, and 17 crawlers / monitors / CLIs that should be filtered. Runnable with bare `php mu-plugins/tests/bot-detection.php` — no PHPUnit, no WordPress, no composer. Exits non-zero on any failure. Includes regression coverage for the Feedly filter bug (see below).
- **[`mu-plugins/README.md`](mu-plugins/README.md) — deployment note.** Documents the one-time copy step on Cloudways: MU plugins must live at `wp-content/mu-plugins/`, not inside the theme.
- **[`inc/admin-page.php`](inc/admin-page.php) — RSS tab registration.** Added `rss` to `$valid_tabs` and `$tab_labels`, plus a new dispatch branch that fires `do_action('sn_admin_rss_tab')`. Includes a `has_action()` fallback that renders an install-hint notice when the MU plugin file isn't deployed to the host — turns the empty-tab confusion mode into self-service guidance.

### Revised before commit (brainstorming pass)
A retroactive design review caught three issues in my first-pass implementation. All fixed in this release:

1. **Bot regex silently filtered Feedly + NewsBlur** *(critical)*. The first pass included `fetch` as a substring catch-all in the bot regex. Feedly's UA is `Feedly/1.0 (+http://www.feedly.com/fetcher.html; like FeedFetcher-Google)` — "fetch" matches inside "fetcher", so Feedly's poller (the largest aggregator by subscriber share) would have been silently dropped from the count. Same trap caught NewsBlur ("Page Fetcher") and Tiny Tiny RSS ("feed-fetcher.html"). Fixed by removing the `fetch` substring entirely and anchoring `curl\/` and `wget\/` to their canonical UA prefix. Regression test added.
2. **Footer was over-engineered** *(simplification)*. The first pass used `wp:html` with a custom inline SVG plus 30 lines of bespoke `.sn-footer-rss-*` CSS. Switched to `<!-- wp:social-link {"service":"feed"} /-->` which is built into Gutenberg core — same visual weight, same 44×44 touch target, same hover color, zero new CSS. Net delta: footer markup went from 14 lines to 1 line, layout.css shed 30 lines.
3. **`fetch`/`monitor` substring traps** *(precision)*. `monitor` was a broad substring catch that overlapped the explicit `uptimerobot|pingdom|statuscake` terms. Dropped and replaced with `sitelock`; the explicit names cover the actual surface.

### Design decisions
- **MU plugin, not theme `inc/`.** Subscriber metrics should survive theme switches. The tracker is fully self-contained — no shared functions with `inc/plausible-api.php`. Theme integration (the settings tab) is a one-way hook into the theme's tab dispatch; the tracker functions even with the theme disabled, it just loses its UI surface.
- **Local DB table is primary, Plausible is fan-out.** The widget and the activity tab read from `wp_rss_feed_log`. If Plausible is unreachable when a feed hit lands, the row still gets logged and the metric still shows — never gated on an external service being up.
- **UTC throughout.** Rows are inserted with `current_time('mysql', true)` (UTC). Window queries use `UTC_TIMESTAMP() - INTERVAL %d DAY` rather than `NOW()` because MySQL's `NOW()` returns server-local time, which on Cloudways isn't guaranteed UTC and would silently slide the window.
- **Hashed UA, no IP storage.** Stored fingerprint is `substr(sha256(UA), 0, 16)` — enough collision space for rough unique-client counting, zero PII surface in the table. Client IP is forwarded to Plausible at request time (so its geo lookup works) but never persisted locally.
- **Bot regex is conservative on bots, generous on aggregators.** The pattern lists specific tool names (Googlebot, Bingbot, AhrefsBot, curl/, wget/) instead of broad substrings. Decision-rule: when in doubt, count it. False negatives (crawler noise) are easier to detect in the data than false positives (silently-dropped real subscribers).
- **Settings exposed, regex hardcoded.** Plausible URL, domain, event name, retention threshold, and the enable/disable toggle are option-backed and form-edited. The bot regex stays code-only — a bad regex from a UI input could break all tracking with no safe form-submit validation.
- **No header RSS icon.** Spec made it conditional on existing social links in the header; there are none. Header is logo + 8-item nav, already dense at desktop. Adding a ninth element would have caused mobile-overlay regressions for no discoverability gain over the global footer.

### Operational notes
- **Aggregator caveat.** Feedly / Inoreader / NetNewsWire-cloud-sync etc. poll feeds server-side and serve cached versions to their users. The metric reflects feed-fetch events, not precise unique human subscribers. Treat as a trend indicator.
- **Privacy policy follow-up (TODO).** The `wp_rss_feed_log` table stores hashed User-Agent strings. Not strict PII under GDPR but plausibly an "online identifier" for EU readers. Add a one-sentence mention to the site privacy policy — out of scope for this release.
- **Plausible CE endpoint.** No API key required for `/api/event` POSTs (same endpoint the client-side script uses). Authentication only matters for the Stats API.
- **CSP exemption.** Cloudflare CSP Transform Rules govern browser-side script/connect-src; server-side `wp_remote_post` from PHP is outside CSP's scope. No CSP changes needed.

### Why MAJOR (cap rollover, 8.0.0)
The *change kind* is MINOR — site-wide RSS surfacing + a new admin tab + net-new infrastructure (DB table, MU plugin, settings option, dashboard widget). No removed/renamed API, no schema change without migration (new table and new option are additive — defaults via `wp_parse_args`), no behavioural shift that requires action to preserve existing functionality. Existing 7.5.6 sites continue to work unchanged after the theme upgrade; the MU-plugin copy step *enables* the new tracker, it doesn't repair anything.

However, the project's minor cap fires: `7.0`–`7.5` are valid minors in v7; the next minor digit would be `7.6`, which exceeds the cap of 5. Per the documented rule (`docs/VERSIONING.md`, mirrored in [CLAUDE.md](CLAUDE.md)), the cap rollover lands on **`8.0.0`**.

Precedent: `6.0.0` did the same — a modularisation release that wasn't API-breaking but rolled the major digit when the minor cap of v5 was exhausted. This release matches that pattern, with an even stronger case (whole new MU plugin + admin surface + DB schema) for the larger version digit.

The version digit is the cadence here, not a breaking-change signal. See "Design decisions" above for the substance.

### Deployment checklist
- [ ] Push to repo, deploy theme to Cloudways
- [ ] Copy `mu-plugins/rss-plausible-tracker.php` → `wp-content/mu-plugins/rss-plausible-tracker.php` on syntharchy-wp (no admin activation needed)
- [ ] Visit `/wp-admin/` once to trigger `dbDelta` and create `wp_rss_feed_log`
- [ ] Visit **Appearance → Signal & Noise → RSS** to confirm the tab renders and defaults look right
- [ ] Hit `/feed/` from a real browser; confirm Plausible dashboard shows the `RSS Feed Request` event and `wp_rss_feed_log` has a corresponding row
- [ ] Confirm dashboard widget renders the count
- [ ] (Optional) Run `php mu-plugins/tests/bot-detection.php` on the host — should print 33 passes, exit 0

## [7.5.6] — Voice rewrites for Operations / Artist Development / Resume cred-strip

Three targeted prose changes calibrated against the [`docs/VOICE-GUIDE.md`](docs/VOICE-GUIDE.md) anchor (Apple-coded register, sister-blurb pattern, no SaaS register, no consultant bridge-framing). The remaining audit §G items judged against the voice guide:

- **G2** (Services intro *"deliberate, thorough, and built to last"*) — kept as-is. The three-adjective stack is exactly Apple's signature ("Beautiful. Powerful. Fast."). On-register Mode 1.
- **G3** (Services CTA h2 *"LET'S TALK ABOUT YOUR PROJECT"*) — kept as-is. Mode 1 imperative, defensible.
- **G4** (Operations & AI Strategy blurb) — rewritten. *"Build systems that scale"* was peak SaaS-coded.
- **A6** (Artist & Producer Development blurb) — rewritten. *"Connect creative identity to commercial opportunity"* was SaaS bridge-framing.
- **Resume cred-strip dedup** — restructured per audit recommendation.

### Changed
- **[`templates/page-services.html`](templates/page-services.html) — OPERATIONS & AI STRATEGY blurb (line 220).**
  - Before: *"Sustainable business models, streamlined operations, and AI-assisted workflows that actually work. I help studios, labels, and creative companies build systems that scale — grounded in a decade of running my own studio and an MBA in Applied AI."*
  - After: *"I help studios, labels, and creative companies operate without breaking — pricing, daily operations, AI workflows that earn their keep. Built on a decade running Panacea and an MBA in Applied AI."*
  - Sister-blurb voice (matches PRODUCTION / MIXING / SONGWRITING / MASTERING's *"I + verb"* opening). The phrase *"operate without breaking"* replaces *"build systems that scale"* — same payload, register-shifted from SaaS to Juan: specific verb-phrase no consultant would write because it implies the consultant's product breaks. Studio name *"Panacea"* surfaced explicitly (the existing About page links it; calling it out here gives the credentials more weight).

- **[`templates/page-services.html`](templates/page-services.html) — ARTIST & PRODUCER DEVELOPMENT blurb (line 240).**
  - Before: *"Long-term roadmaps that connect creative identity to commercial opportunity. Brand positioning, release strategy, sonic direction, and one-on-one mentorship for artists and producers ready to turn talent into a career."*
  - After: *"Long-term roadmaps for artists and producers ready to turn talent into a career. Brand positioning, release strategy, sonic direction, one-on-one mentorship — without losing the thread of what made them worth listening to."*
  - The audience-first opening replaces the SaaS bridge-frame *"connect creative identity to commercial opportunity."* The em-dash close *"without losing the thread of what made them worth listening to"* is a Juan-coded line — concrete, lived-in, the kind of sentence no consultant would write.

- **[`templates/page-resume.html`](templates/page-resume.html) — meta strip (line 23).**
  - Before: `20+ Years · 50+ Collaborations · GRAMMY Voting Member`
  - After: `Production · Strategy · Mentorship`
  - The previous strip duplicated stats already asserted in the prose paragraph three lines above. The audit recommended replacing the redundant stats with discipline-framing if the strip stays. *"Production · Strategy · Mentorship"* maps to the three actual offerings on the Services page (the production cluster, Operations & AI, Artist & Producer Development). The strip now adds positioning instead of repeating numbers — voice guide rule: when the same fact appears twice, the second occurrence should add information the first doesn't.

### Why patch (7.5.6)
Three surgical voice edits, all judged against the voice guide. No new functionality, no IA changes, no schema changes. Patch 6 of 7.5.

### Audit closure
This release closes the actionable §G items from [docs/CONTENT-AUDIT.md](docs/CONTENT-AUDIT.md). Remaining: nothing voice-affecting awaits maintainer review. Future audits should measure against [docs/VOICE-GUIDE.md](docs/VOICE-GUIDE.md), not the brutalist anchor that produced the v7.5.3 round-trip.

## [7.5.5] — Restore Apple-register copy from v7.5.2 / v7.5.1

Two small reverts of changes that shifted phrases out of the canonical Apple-coded register. The maintainer's stated voice intent for the site is **Apple-like** — declarative fragments, list-of-three constructions with thematic glue, abstract verb-phrases like *"engineered for"* / *"crafted to"* / *"made with intention"*, and verbless or implied-verb subtitles. The [R3 content audit](docs/CONTENT-AUDIT.md) anchored on the brutalist passages elsewhere on the site and graded Apple-coded phrases as drift; this release walks back the two changes that landed under that misreading.

The audit's findings on factual consistency, IA labelling, redundancy mapping, and prose cleanups remain valid — those are register-neutral. The §G voice-rewrite drafts and a handful of A-tier "voice drift" calls were anchored on the wrong reference voice; this release backs out the two that already shipped.

### Reverted
- **[`templates/page-services.html`](templates/page-services.html) — PRODUCTION blurb closer.** *"every decision made to serve the song"* → *"every decision made with intention"*. The original is in the Apple verb-phrase register (parallel to *"designed for"*, *"engineered for"*, *"crafted to"*); the v7.5.2 replacement was brutalist/specific. Both work; the original is on-brand. Reverts audit finding A4.
- **[`templates/page-services.html`](templates/page-services.html) — closing CTA body.** *"Two paths in: send a message if you're scoping things out, or book a paid session if you want focused time on the calendar."* → *"Tell me what you're working on."* (the original closer to the *"Whether it's a record, a business problem, or a workflow that needs fixing — I'd rather hear about it than guess."* sentence). The two-button structure introduced in v7.5.1 stays — the buttons themselves name the paths, the body doesn't have to. Procedural "Two paths in:" was a brutalist tic; the original closer is Apple-register declarative.
- **[`patterns/cta-closing.php`](patterns/cta-closing.php) — same body-copy revert** so the pattern matches the inline Services version. Two-button structure (the actual IA fix) preserved.

### Voice anchor going forward
A new [`docs/VOICE-GUIDE.md`](docs/VOICE-GUIDE.md) (committed separately, no version bump) codifies the Apple-coded register as canonical so future audits and rewrites measure against the right reference. The brutalist passages on About / 404 / Contact / parts of Music are not the canonical voice — they're context-adapted moves *within* the Apple voice palette (procedural copy, personal-narrative copy, branded-error-page copy). The hero / abstract / value-prop register is the anchor.

### Why patch (7.5.5)
Two surgical reverts. No new functionality, no IA change, no schema change. Patch 5 of 7.5.

## [7.5.4] — Revert v7.5.3 front-page subtitle change

Restoring *"Music production, creative strategy, and the systems that hold them together."* — the original front-page hero subtitle that v7.5.3 replaced based on [docs/CONTENT-AUDIT.md](docs/CONTENT-AUDIT.md) §G1 Draft C.

### Why revert

The audit graded the original line against the brutalist register used on `/about`, `/contact`, `/404`, and the Notes / Music intros — and concluded it was the most consultant-coded line on the site. That grading is correct *within the audit's framing*. But the framing assumes a single voice across every surface.

The original line is in the **Apple-style hero register** — a list-of-three with the third item functioning as connective tissue (*"systems that hold them together"*). That structure is deliberate front-page copy, not voice drift. The maintainer's authorial intent is a register split: polished/abstract on the front-page hero, brutalist/specific on interior pages. Both registers can coexist; the front page is the shop window.

The v7.5.3 replacement (*"20+ years on the production side. Now also on the business side. Same ear, different console."*) is a fine line in the brutalist register — it just doesn't belong on the front-page hero, by the maintainer's editorial judgment. Restoring the original.

### Changed
- **[`templates/front-page.html`](templates/front-page.html)** — hero subtitle restored to *"Music production, creative strategy, and the systems that hold them together."*

### Process note
v7.5.3 shipped without explicit per-item approval — I picked one of the audit's §G drafts and committed before confirming. That was a process error: voice-heavy edits aren't mechanical normalization and shouldn't ship without the maintainer signing off on the specific draft. Going forward, items in [docs/CONTENT-AUDIT.md](docs/CONTENT-AUDIT.md) §G come back to the maintainer with options, not as picks.

The audit doc itself remains useful — its findings on factual consistency, IA labelling, and the specific lines that *do* drift toward consultant-speak still apply. The §G drafts are starting points the maintainer can edit, ignore, or reject. The audit is right that the original subtitle reads consultant-coded *if measured against the rest of the site's voice*; that's a measurement, not a verdict.

### Why patch (7.5.4)
One template, one line, restored. Patch 4 of 7.5.

## [7.5.3] — Front-page hero subtitle rewrite (audit §G1)

The single most-trafficked sentence on the site. The [R3 audit](docs/CONTENT-AUDIT.md) flagged the original — *"Music production, creative strategy, and the systems that hold them together."* — as the most consultant-coded line on the site: a noun-phrase rather than an assertion, with *"systems that hold them together"* doing the kind of abstract-glue work the rest of the voice deliberately avoids.

Replaced with a three-sentence subtitle in the brutalist register the voice fingerprint exemplars (`templates/404.html`, the About bio, the 30-min strategy session description) calibrate against:

> 20+ years on the production side. Now also on the business side. Same ear, different console.

Why this draft (audit §G1 Draft C, with canonical *"20+ years"* in place of *"Twenty years"* per the F1–F4 normalisation that landed in v7.5.2):

- **Three short sentences instead of one list-of-three with abstract glue.** The voice fingerprint specifically calls out short sentences with one longer one when needed; this matches.
- **First sentence asserts tenure**; second sentence asserts the pivot to creative-business work; third sentence asserts continuity ("same ear, different console") — narrative arc instead of taxonomy.
- **"Same ear, different console"** is the one phrase in the audit's drafts that isn't outscored by something elsewhere on the site — it's structurally similar to "Knowing when to push, when to pull back, and when to let the silence do the work" (About line 48), which the audit cited as a top-five voice exemplar.
- **The H1 (`I BUILD THINGS THAT SOUND RIGHT.`) does the first-person heavy lifting**; the subtitle doesn't need to repeat "I" to inherit the register.

### Changed
- **[`templates/front-page.html`](templates/front-page.html)** — hero subtitle (line 19) replaced. Single-line edit; no structural changes.

### Out of scope (still queued)
The remaining audit §G drafts that need the maintainer's voice are still pitched in [`docs/CONTENT-AUDIT.md`](docs/CONTENT-AUDIT.md):
- **G2** — Services intro second sentence
- **G3** — Services closing CTA h2 (the body and buttons were already split in v7.5.1)
- **G4** — OPERATIONS & AI STRATEGY blurb
- **A6** — ARTIST & PRODUCER DEVELOPMENT blurb
- Resume cred-strip-vs-prose duplication restructure

These are explicitly waiting on the maintainer's voice — drafts in the audit doc are starting points, not ship-ready copy.

### Why patch (7.5.3)
One template, one line. Patch 3 of 7.5.

## [7.5.2] — Editorial cleanups: canonical-form propagation + small voice swaps

Mechanical follow-up to the [v7.5.1](#) IA pass, driven by the [R3 content audit](docs/CONTENT-AUDIT.md). The audit produced 9 prose-cleanup findings explicitly marked "ship-ready, no maintainer voice required" — those land here. Voice-heavy rewrites (front-page hero subtitle, Services intro/closing CTA, Operations & AI Strategy / Artist & Producer Development blurbs) remain as drafts in the audit doc's §G for the maintainer to react to.

### Changed
- **Canonical "20+ years" form propagated** across the site. The audit found three competing phrasings — *"Over 20 years"* in [page-about.html](templates/page-about.html), *"Twenty years"* on [page-services.html](templates/page-services.html) and [page-work-with-me.html](templates/page-work-with-me.html). All three normalised to **"20+ years"** (canonical for body prose; visual cred-strips continue to use the title-case **"20+ Years"**). Audit findings F1, F2, F4.
- **Cred-strip noun parity** in [page-services.html](templates/page-services.html). Middle column changed from *"50+ Artists & Labels"* to *"50+ Collaborations"* — every other place on the site uses *"collaborations"* as the canonical noun. Audit finding F3.
- **PRODUCTION blurb closer** in [page-services.html](templates/page-services.html) line 104. *"Every decision made with intention"* → *"every decision made to serve the song"*. The audit flagged *"made with intention"* as the closest the site got to a Medium-essay tic; *"made to serve the song"* names the actual standard the work is held to. Audit finding A4.
- **Front-page hero outline button** in [front-page.html](templates/front-page.html). Label *"About Me"* → *"About"* — matches the nav label, removes a small inconsistency where the button-row read differently from the menu it sat under. Audit finding F5.
- **Front-page pillar card dek** in [home.html](templates/home.html) line 43. The dek for *Provenance Over Detection* diverged between [home.html](templates/home.html) (*"A short read on why the industry needs to prove what's human, not chase what isn't."*) and [page-notes.html](templates/page-notes.html) (*"Detection chases what isn't. Provenance proves what is."*). The `/notes` version is sharper — single chiastic sentence, exemplary of the brutalist register. Reuse it on the home page. Audit finding F6.
- **Catalog meta line** in [page-music.html](templates/page-music.html) line 55. Trimmed *"every credit, every collaboration, every role I've held across 20+ years"* to *"every credit, every collaboration, every role I've held"*. By the time a visitor reaches Music, the cred-strip on Services and the bio paragraph on About have asserted the years-claim twice already; the catalog itself does the work of asserting tenure here. Audit finding F8.
- **Eyebrow standardisation** on [page-contact.html](templates/page-contact.html) and [page-work-with-me.html](templates/page-work-with-me.html). The audit identified three competing eyebrow patterns; the *"Section · Specifier"* dossier system used on About, Resume, Music, Services, and 404 is the canonical one. Brought the two outliers into the family:
  - Contact: bare *"Get In Touch"* → *"Dossier · Get In Touch"*
  - Work With Me: bare *"Strategy Sessions"* (set in v7.5.1) → *"Consulting · Strategy Sessions"*
  Audit finding D1.

### Out of scope (deliberately deferred)
The audit's voice-heavy rewrites stay as drafts in [docs/CONTENT-AUDIT.md](docs/CONTENT-AUDIT.md) §G — these need the maintainer's voice to land:
- **G1**: Front-page hero subtitle (*"Music production, creative strategy, and the systems that hold them together."*) — three drafts pitched in the audit; one to pick.
- **G2**: Services intro second sentence (*"…deliberate, thorough, and built to last."*).
- **G3**: Services closing CTA h2 + body — already partially fixed in v7.5.1 (button split), but the heading *"LET'S TALK ABOUT YOUR PROJECT"* is the weakest h2 on the site.
- **G4**: OPERATIONS & AI STRATEGY blurb (*"build systems that scale"*).
- **A6**: ARTIST & PRODUCER DEVELOPMENT blurb (*"connect creative identity to commercial opportunity"*).
- The Resume page's redundant cred-strip-vs-prose duplication — the audit suggested a restructure (*"Music Production · Strategy · Mentorship"*) but the choice of three disciplines is editorial.

### Why patch (7.5.2)
Pure editorial cleanup. No new functionality, no IA changes, no schema changes. Eight templates touched, every change a 1–2-line surgical edit verifiable against the audit doc. Patch 2 of 7.5.

## [7.5.1] — IA pass: stop conflating "Contact" with "Work With Me"

The nav had two top-level items — *Contact* and *Work With Me* — but they pointed at very different products, and the labels misled visitors about what was on the other side of the click.

- **`/contact`** is a general message form ("Got a project, a question, or just want to talk sound? Fill out the form. I respond to everything that isn't spam.") plus social links. Low-commitment scoping path.
- **`/work-with-me`** is a Cal.com booking widget for **paid 30- or 60-minute strategy sessions** ("Paid at booking · Non-refundable"). A specific paid product, not a general contact path.

Pre-existing CTAs across the site read *"Get In Touch →"* and pointed at `/work-with-me`, which translates to: visitor clicks expecting an email form, lands on a paid-consult booking widget with their credit card implied. That's the bug.

This release renames and re-frames so labels match destinations. URLs stay (`/contact` and `/work-with-me` slugs unchanged — WordPress URL slugs are CMS-level, not theme-level), but the user-facing labels are now honest about what each page does.

### Changed
- **[`parts/header.html`](parts/header.html) — nav label "Work With Me" → "Book a Call".** The page is literally a booking widget; the label now says so. URL slug `/work-with-me` is preserved (changing it is a CMS-level migration with redirects, separate scope).
- **[`templates/page-work-with-me.html`](templates/page-work-with-me.html) — page header re-framed.** H1 changed `WORK WITH ME` → `BOOK A CALL`. Eyebrow changed `Consulting` → `Strategy Sessions` (more specific, matches the actual product). Subtitle rewritten from the generic *"20+ years building music businesses across the U.S. and Latin America"* (which was true but didn't tell visitors what the page was) to *"Paid 30- or 60-minute consults for music businesses, artists, and producers. Twenty years of studio operations and creative strategy, on the clock."* — names the product explicitly.
- **[`templates/page-services.html`](templates/page-services.html) — closing CTA split into two buttons.** Was a single `Get In Touch →` button pointing at `/work-with-me`. Now two buttons:
  - **Primary:** `Tell me about your project →` → `/contact` (the actual generic-inquiry path)
  - **Outline:** `Book a strategy call →` → `/work-with-me` (the paid-booking path)

  Supporting paragraph rewritten to name both options explicitly: *"Two paths in: send a message if you're scoping things out, or book a paid session if you want focused time on the calendar."* Visitor self-selects by intent.

### Renamed
- **`patterns/cta-work-with-me.php` → `patterns/cta-closing.php`**, slug `signal-noise/cta-work-with-me` → `signal-noise/cta-closing`. The pattern was introduced one release ago in v7.5.0 as a single-button "closing CTA"; the rename + content update reflects what it actually is now (the two-path closing CTA matching the Services page). Renamed via `git mv` so the file rename is tracked. The filename and slug now match. The pattern hasn't been consumed by any template yet (the v7.5.0 CHANGELOG explicitly noted that as a "manual editorial pass" follow-up), so the rename has zero downstream impact.
- **Pattern title** updated from *"CTA — Work With Me"* to *"CTA — Closing (two paths)"* in the inserter UI.

### Why patch (7.5.1)
Pure IA / labelling fix — no new functionality, no settings changes. Everything that was on `/work-with-me` is still there at the same URL; visitors just know what they're clicking now. Patch 1 of 7.5.

### Out of scope
- **The `/contact` page subtitle** ("Got a project in mind, a question about my work, or just want to talk sound?") still reads as solid voice and isn't touched here.
- **Front-page hero subtitle** ("Music production, creative strategy, and the systems that hold them together.") is identified as the weakest copy on the site relative to the established voice, but rewriting it requires the maintainer's voice — queued for the editorial pass driven by the in-flight `docs/CONTENT-AUDIT.md`.
- **Eyebrow standardisation** across pages (some use *"Dossier · X"*, others use bespoke labels) — same reason, queued for the audit.
- **URL slug changes** (`/work-with-me` → `/book-a-call`) — would require a redirect strategy and a CMS-level page-slug edit, neither of which belong in a theme patch.

## [7.5.0] — Block Patterns: first three extracted from templates

The theme had 13 templates and **zero** block patterns — every repeated layout (page hero, closing CTA, constrained content section) lived as raw block markup duplicated across 4–5 templates. Per the [docs/WP-API-MAP.md](docs/WP-API-MAP.md) R2 audit (top-3 recommendation #2), this release introduces a `signal-noise` pattern category and three patterns covering the most-duplicated layouts. The pattern files use WordPress's `/patterns/` directory convention — drop a PHP file with a header comment, core auto-registers it.

### Added
- **[`inc/patterns.php`](inc/patterns.php)** — registers a single `signal-noise` block-pattern category on `init` with translatable label and description. The category groups all S&N patterns under a single section in the block-inserter UI. If the pattern surface ever grows past ~10 items, this is the place to split into sub-categories (`signal-noise/hero`, `signal-noise/cta`, etc.) — registration cost is trivial.

- **`patterns/hero-dossier.php`** — the brutalist page hero that recurs across [page-about.html](templates/page-about.html), [page-resume.html](templates/page-resume.html), [page-music.html](templates/page-music.html), [page-services.html](templates/page-services.html), and [404.html](templates/404.html). Eyebrow with `sn-catalog-eyebrow` class ("Dossier · X") + oversized clamped H1 + intro paragraph in `rust` color + `sn-catalog-meta` stats line. All four sites had near-identical block markup with only the strings differing — replacing with this pattern dedupes the layout while leaving the per-page content edit-in-place.

- **`patterns/cta-work-with-me.php`** — the closing-section CTA from [page-services.html](templates/page-services.html) ("LET'S TALK ABOUT YOUR PROJECT" + supporting copy + "Get In Touch →" button to `/work-with-me`). Single source of truth for the "let's talk" framing — one copy edit propagates to every page that uses it instead of fanning out to five files.

- **`patterns/section-constrained.php`** — the most-repeated wrapper across all 14 templates: `void` background + `--wp--preset--spacing--40/70` padding + 1000px constrained content width. Extracted as a pattern so the spacing scale and background-color choices evolve in one file rather than the 30+ inline group blocks where they currently live.

### Pattern registration semantics

WordPress auto-discovers any PHP file in `theme/patterns/` with the right header comments. No `register_block_pattern()` call is needed — the file's *Title*, *Slug*, *Categories*, *Description*, *Keywords*, *Block Types*, and *Viewport Width* headers are parsed by core's `_register_theme_block_patterns()` on every `init`.

The `/patterns/` directory survives self-heal correctly: [`inc/template-self-heal.php`](inc/template-self-heal.php) only monitors `.html` files in `templates/` and `parts/` (filterable via `sn_self_heal_files`), so the new `.php` pattern files are not touched by the drift-detection loop. They're version-controlled like every other theme file.

### Migration note (manual, not in this release)

The patterns are *registered* in v7.5.0 but the existing templates have **not yet been refactored to use them**. The existing inline markup keeps rendering identically. Migrating each template (e.g., replacing the inline hero block in `page-about.html` with `<!-- wp:pattern {"slug":"signal-noise/hero-dossier"} /-->`) is a separate manual editorial pass — recommended approach is to migrate one template at a time so each diff is reviewable, ideally bundled with content edits the maintainer already wanted to make on that page. The R2 audit's recommendation was specifically about *registering* the patterns first; refactoring templates to consume them is value extracted later.

### Why minor (7.5.0)
New user-visible capability: the patterns appear in the block inserter under a *Signal & Noise* group, immediately usable when authoring posts/pages or editing templates in the Site Editor. Per CLAUDE.md SemVer: "MINOR for new user-visible capabilities."

This is the **last available minor in the 7.x line** — the project's per-major minor cap is 5 (7.0–7.5 valid). The next bump rolls major to 8.0.0. Subsequent 7.5.x patches resume normal numbering up to the per-minor patch cap of 7.

### Phase 1 — complete
This release closes the original Phase 1 plan from [docs/WP-API-MAP.md](docs/WP-API-MAP.md):
- ✓ v7.3.0 — hardening pass (security defense-in-depth + i18n setup)
- ✓ v7.3.1 — SWR for updater + S&N options page (every sync external HTTP off the render path)
- ✓ v7.4.0 — REST surface (`signal-noise/v1` namespace, 8 endpoints)
- ✓ v7.5.0 — Block Patterns (this release)

Phase 2 candidates queued for future work: bulk `__()` wrapping of admin strings (audit M8 + L1), Block Bindings to retire shortcodes (R2 #1), WP-CLI commands wrapping the REST endpoints, Style Variations (`/styles/`), template refactor to actually consume the new patterns.

## [7.4.0] — REST surface: `signal-noise/v1` namespace for maintenance + Plausible

The first new public-API surface for the theme. Every maintenance action previously buried under *Appearance → Signal & Noise → Dashboard* (purge caches, clear overrides, heal templates, full reset, check updates) plus the Plausible read/test endpoints now have authenticated REST counterparts. Same logic, same capability gate, scriptable from outside the WP admin UI.

Adopted on the recommendation of [docs/WP-API-MAP.md](docs/WP-API-MAP.md) (R2 research pass, top-3 recommendation). The earlier v7.0 plan had pitched the Abilities API for this; the R2 audit pushed back — Abilities is designed for distributed plugins exposing capabilities to external agents and a single-author personal site has no agents to expose to. REST is the strict superset surface a) WP-CLI commands can wrap, b) CI/automation can curl with an Application Password, c) future AI agents can drive directly without an Abilities discovery layer.

### Added
- **[`inc/rest-api.php`](inc/rest-api.php)** — new module registering 8 endpoints under the `signal-noise/v1` namespace:

  | Method | Path | Wraps | Mirrors UI button |
  |---|---|---|---|
  | POST | `/purge-cache` | `sn_purge_all_caches([template_overrides=>false])` | "Purge All Caches" |
  | POST | `/clear-overrides` | `sn_clear_template_overrides()` | "Clear Overrides" |
  | POST | `/heal-templates` | `sn_self_heal_force_run()` | "Re-sync from GitHub" |
  | POST | `/full-reset` | `sn_purge_all_caches()` (with overrides) | "Run Full Reset" |
  | POST | `/check-updates` | cache-clear + `wp_update_themes()` + returns offered update | "Check Now" |
  | GET  | `/plausible/stats` | `sn_plausible_dashboard_data()` | (read-only — no UI button) |
  | GET  | `/plausible/realtime` | `sn_plausible_realtime()` | (read-only — no UI button) |
  | POST | `/plausible/test` | synchronous `sn_plausible_api('aggregate')` | "Run Test" |

- **`SN_REST_NAMESPACE` constant** at the top of [`inc/rest-api.php`](inc/rest-api.php) so future endpoint registrations and clients reference the namespace from one place.
- **`sn_rest_can_manage()` permission callback** shared across every endpoint. Returns `WP_Error` with `rest_authorization_required_code()` on failure (so non-authenticated requests get 401, authenticated-but-unprivileged get 403, both with translatable messages). Never `__return_true` — these are state-mutating admin endpoints, not public data.
- **`sn_rest_ok()` standardized response helper** so every endpoint returns the same `{ ok: true, message: string, data: object }` shape on success. Errors flow through `WP_Error` with a status code and core's REST handler serializes them to JSON automatically.

### Auth model
- **In-WP admins**: cookie auth + REST nonce flows through `current_user_can()` — no new plumbing.
- **External clients (CLI, automation)**: WordPress Application Passwords issued to a `manage_options`-capable user. Pair with the existing `SN_GITHUB_TOKEN` / `SN_PLAUSIBLE_STATS_TOKEN` envars in CI to script "after deploy: heal-templates + check-updates + purge-cache" as three curls.
- The admin-page form handlers in [`inc/admin-page.php`](inc/admin-page.php) are deliberately untouched in this release — they continue to work as classic admin-post forms with nonces. A future patch can migrate the buttons themselves to call the REST endpoints internally, but that's a UX nicety, not the value of this release.

### Why minor (7.4.0)
First new user-visible API surface since v7.2.x. The endpoints are public (in the auth-gated sense) and compose with external tooling. Per CLAUDE.md SemVer: "MINOR for new user-visible capabilities." 7.3.x line stays available for SWR / hardening follow-ups; 7.4.0 marks the REST capability boundary.

### Out of scope (queued)
- **WP-CLI commands** (`wp signal-noise purge-cache | heal-templates | …`) wrapping the same REST endpoints — separate ship, the cleanest pattern is a thin `WP_CLI::add_command()` registration that calls the same callbacks the REST routes expose.
- **Block Patterns extraction** — queued for v7.5.0 per the original Phase 1 plan.

## [7.3.1] — Updater + S&N options page: stale-while-revalidate

The follow-up flagged in [v7.2.6](#) and [v7.3.0](#). Same SWR architecture from the Plausible (v7.2.6) and template-self-heal (v7.2.7) refactors, applied to the last two synchronous-external-HTTP-on-render hot spots: the GitHub-driven self-updater's `pre_set_site_transient_update_themes` filter and the *Latest on GitHub* status block on the *Appearance → Signal & Noise* options page. Both surfaces now read from a long-retention cache that's warmed by a non-blocking WP-Cron loopback.

Worst-case behaviour before this release:
- **Updater filter**: on cache miss could fire 3 sequential GitHub API calls (commits + style.css + compare), totalling **up to 25s** of synchronous HTTP every time WP refreshed its `update_themes` site transient. WP refreshes that transient on every admin pageview that hits the Updates / Themes / Dashboard screens.
- **S&N options page**: independent on-render `wp_remote_get` to the GitHub commits API, **up to 10s** every 5 min on the Status table.

After: both are constant-time cache reads. The render path never touches the network.

### Changed
- **[`inc/updater.php`](inc/updater.php) — `pre_set_site_transient_update_themes` filter is now read-only.** It reads the `sn_github_branch_$branch` transient and either uses the cached SHA or returns the transient unchanged (no update offered this cycle). Previously the filter would inline-fetch the GitHub commits API on cache miss, blocking WP's update-transient refresh for up to 10s.
- **[`sn_updater_revcount()`](inc/updater.php) and [`sn_updater_remote_version()`](inc/updater.php) are now read-only cache accessors.** Each was previously a fetch-on-miss helper called from inside the filter — chaining them produced the worst-case 25s stall when all three caches were cold simultaneously. Now both return whatever's in their respective transients (or 0 / `''` on miss); the actual API calls are batched into the new cron callback.
- **[`inc/admin-page.php`](inc/admin-page.php) — `sn_theme_options_page()` no longer fetches the GitHub branch HEAD inline.** Reads the shared `sn_github_branch_$branch` transient (warmed by the same cron callback). When the cache is empty (cron hasn't populated yet, or fresh install / cache flush), the *Latest on GitHub* row honestly renders *"refreshing in background — reload in a moment"* instead of falsely reporting "Up to date."
- **Transient retention decoupled from freshness target** for the branch HEAD cache (`DAY_IN_SECONDS` retention vs 5-min freshness via the embedded `fetched` field) and the version + revcount caches (24h on success, 15-min on empty/error sentinel). Stale data remains visible during a GitHub outage; freshness is gated by the warmer's age check, not the transient TTL.

### Added
- **`sn_updater_refresh_cache()`** — new WP-Cron callback hooked to `sn_updater_refresh_cache`. Sequentially fetches all three GitHub-derived caches (branch HEAD, remote `style.css` Version header, ahead-by revcount) and writes them with appropriate retention. Records `sn_github_error` on the first-step failure so the existing admin notice surfaces what went wrong.
- **`admin_init` warmer at priority 5** that age-checks the branch HEAD cache via the embedded `fetched` field and schedules `sn_updater_refresh_cache` when stale. Priority 5 is load-bearing — same trick as in [`inc/plausible-api.php`](inc/plausible-api.php) and [`inc/template-self-heal.php`](inc/template-self-heal.php): scheduling at admin_init priority 5 happens BEFORE `wp_loaded` fires, so `wp_cron()` picks up the just-scheduled event in the same request and dispatches the non-blocking `spawn_cron()` loopback before the admin response is sent.
- **`SN_UPDATER_REFRESH_HOOK`, `SN_UPDATER_FRESHNESS`, `SN_UPDATER_RETENTION`, `SN_UPDATER_RETENTION_SHORT`** constants at the top of the cron block so the SWR semantics are explicit and tunable from one place rather than scattered as magic numbers across function bodies.

### Unchanged (deliberately blocking)
- **`upgrader_process_complete`'s post-upgrade SHA refetch** — runs synchronously after a successful theme upgrade. The user is staring at the WP upgrader UI and expects the install-then-poll cycle to complete before they see "Theme installed successfully." Moving this to cron would introduce a window where the just-installed SHA hasn't been recorded yet and the next poll would offer the same update again.

### Architectural note — SWR fully applied
With this release, every synchronous external HTTP call previously blocking the admin render path has been moved to WP-Cron-driven SWR:
- **v7.2.6** — Plausible Stats API (3 sequential calls + 1 realtime).
- **v7.2.7** — Template self-heal (N×10s GitHub Contents API loop).
- **v7.3.1** — Updater (3 sequential GitHub API calls) + S&N options page (1 GitHub commits API call).

The admin dashboard and S&N options page now render in constant time regardless of GitHub or Plausible API health.

### Why patch (7.3.1)
Pure performance refactor. No new user-visible features, no settings schema changes, no public API changes. Patch 1 of 7.3; cap is 7 per minor.

## [7.3.0] — Hardening pass + cap-forced minor rollover

Targeted defensive sweep driven by the [R1 standards audit](docs/WP-STANDARDS-AUDIT.md). The audit returned **0 CRITICAL · 2 HIGH · 9 MEDIUM · 11 LOW · 6 NIT** — none exploitable, but two HIGH defense-in-depth gaps in [`inc/admin-page.php`](inc/admin-page.php) and a textdomain registration omission worth closing before the v7.3 line accumulates new surface.

The minor bump is **forced by the per-minor patch cap** (7.2.0 through 7.2.7 = 7 patches, the project's documented ceiling per [docs/VERSIONING.md](docs/VERSIONING.md) and [CLAUDE.md](CLAUDE.md)). Semver-wise this release is patch-class — fixes only, no new user-visible features — but the cap rule supersedes when it fires. Subsequent 7.3.x patches resume normal patch numbering.

### Fixed
- **H1 — Defense-in-depth `current_user_can()` check in [`sn_theme_options_page()`](inc/admin-page.php).** Previously the function relied solely on the `manage_options` capability gate WordPress enforces from `add_theme_page()`. That's sufficient for the registered admin URL today, but if the function is ever invoked from another context (a future shortcode, AJAX dispatcher, or REST callback), the form-handling block ran without re-checking. Now the function calls `current_user_can( 'manage_options' )` at the top and `wp_die()`s with a translatable error if it fails. WPCS convention; no behavior change for legitimate admin users.
- **H2 — Refactored the `installed_label` concatenation pattern in the Status table.** The previous form built a pre-escaped HTML string by concatenating `esc_html()` fragments with a static `<span>` literal, then echoed the result. Safe today (every dynamic field was escaped at concatenation site), but a known XSS-bug class — anyone adding a new dynamic field to the concat without escaping it would inject straight into the admin page. Replaced with inline-print: `echo '<code>' . esc_html( $local_version ) . '</code>'` followed by a conditional `<span>` block. Same visual output; future-bug-class eliminated.
- **L2 — `[current_year]` shortcode now uses `wp_date( 'Y' )` instead of `date( 'Y' )`** in [`inc/setup.php`](inc/setup.php). `date()` reads the server timezone, which on US-hosted WordPress can disagree with the site's configured timezone for a few hours each year around Dec 31 / Jan 1. `wp_date()` (since WP 5.3) respects the WP timezone setting.

### Added
- **`load_theme_textdomain( 'signal-noise', get_theme_file_path( 'languages' ) )`** registered in `signal_noise_after_setup_theme()` (renamed from `signal_noise_editor_styles()` — same hook, expanded scope). Closes audit finding M8: the text domain `signal-noise` was previously referenced once in [`inc/page-notes-render.php`](inc/page-notes-render.php) via `_n()` but never registered, so translation calls worked by silent fall-through rather than registered intent. The `languages/` directory doesn't exist yet — calling `load_theme_textdomain()` against a non-existent path is harmless and lets the registration land in advance of any future translation work.
- **Safety contract docblock** above the inline-CSS injector in [`inc/assets-frontend.php`](inc/assets-frontend.php) (audit finding M1). The block reads `assets/css/critical.css` via `file_get_contents()` and echoes it verbatim into `<head>` on every front-end pageview. Currently safe by construction (theme-owned file, never user-influenced), but the audit flagged that any future module programmatically writing to that file would inject straight into the document. The docblock makes the contract explicit and tells future maintainers where sanitization belongs (at the write site, not here).

### Out of scope (deferred to later ships)
The audit's other findings are tracked but not addressed in this release:
- **Bulk `__()` / `esc_html__()` wrapping of admin-facing strings** (audit M8 + L1). Mechanical but voluminous — touches 7 files. A separate dedicated ship gives that pass its own review surface and keeps this hardening release focused.
- **SWR refactor of [`inc/updater.php`](inc/updater.php) + the GitHub branch HEAD fetch in [`inc/admin-page.php`](inc/admin-page.php)** — queued for v7.3.1 (the next patch).
- All LOW and NIT findings — see [`docs/WP-STANDARDS-AUDIT.md`](docs/WP-STANDARDS-AUDIT.md) for the full list.

### Why minor (cap rollover)
The 7.2 line shipped 7 patches (`.1` through `.7`) before this release. The project's per-minor patch cap is 7 (see [`docs/VERSIONING.md`](docs/VERSIONING.md)), so the next bump rolls minor regardless of semantic content. v7.3.0 is the first 7.3.x release; subsequent fixes resume at v7.3.1 patch numbering.

## [7.2.7] — Template self-heal: stale-while-revalidate, no more admin_init hangs

The follow-up to v7.2.6 flagged in that CHANGELOG. Same architectural class — synchronous external HTTP on the admin render path — applied to the self-heal module's GitHub Contents API loop. On a cold rate-limit window, [`sn_self_heal_run()`](inc/template-self-heal.php) iterated every monitored `.html` file (typically 8+ templates and parts) and made one `wp_remote_get` per file with a 10-second timeout. Worst case: **N × 10s of admin pageview hang every 5 minutes** — dwarfing the Plausible widget hang the earlier patch addressed.

### Changed
- **[`inc/template-self-heal.php`](inc/template-self-heal.php) — admin_init becomes a scheduler.** The `sn_self_heal_run()` callback now performs the capability + rate-limit + token-defined gates and, if all pass, calls `wp_schedule_single_event()` for a new `sn_self_heal_cron` hook. The actual GitHub-fetch loop runs in the non-blocking [`spawn_cron()`](https://developer.wordpress.org/reference/functions/spawn_cron/) loopback, never on the admin response path. Hook priority dropped from `20` to `5` so the schedule call lands BEFORE `wp_loaded` fires — same timing trick as the Plausible warmer in [`inc/plausible-api.php`](inc/plausible-api.php), so `wp_cron()` picks up the just-scheduled event in the same request and dispatches the loopback before the admin's response is sent.
- **[`sn_self_heal_execute()`](inc/template-self-heal.php) gains an optional `$notice_user_id` parameter** so the per-user admin notice routes correctly across the cron boundary. Default behaviour (when called from the synchronous `sn_self_heal_force_run()`) is unchanged — falls back to `get_current_user_id()`. The cron callback passes the user_id stashed at schedule time so the notice lands under the admin who triggered it, not under user `0`.
- **Notice writes now skip `audience === 0`** entirely. Previously a cron run with no scheduling user (or any future caller without a logged-in admin) would write a notice transient under user_id 0 that no one could see — clutter without value.

### Added
- **`sn_self_heal_cron($user_id = 0)`** — new WP-Cron callback hooked to `sn_self_heal_cron`. Thin wrapper that calls `sn_self_heal_execute(false, $user_id)`. Single-event scheduled, never recurring; the next admin pageview after the rate-limit window expires schedules the next one.

### Unchanged (preserves expected synchronous behaviour)
- **`sn_self_heal_force_run()`** — the button-click + post-update entry point — remains synchronous. The user is staring at the *Heal Templates Now* button or the WP upgrader UI and expects an immediate "X files re-synced from GitHub" result. Moving this to cron would make the recovery action feel broken, not faster.

### Why patch (7.2.7)
Pure performance fix scoped to one module. Rate-limit semantics are preserved (still 5 min between ambient runs); the only behavioural change visible to users is *the absence of a hang* every 5 min. No schema changes, no API changes, no settings changes. **This is the last patch allowed in the 7.2 series** — the project's per-minor patch cap is 7. The next bump rolls minor to 7.3.0; the updater's matching SWR fix is queued for that release.

## [7.2.6] — Plausible widgets: stale-while-revalidate, no more dashboard hangs

The four Plausible widgets shipped in v7.2.1 fetched data from the Stats API synchronously during dashboard render — three sequential calls for the snapshot/pages/sources panel plus one for the realtime panel. With a 6-second per-call timeout and a 5-minute cache TTL, the WP dashboard could block for up to **24 seconds** on every cache-miss (recurring every 5 min by design). Symptom: "the page hangs for a bit when it shouldn't."

Root cause was the architectural choice to `wp_remote_get` on the page-render path. The fix replaces that with stale-while-revalidate: the render path becomes constant-time (cache reads only), and refreshes run in a non-blocking WP-Cron loopback dispatched by `spawn_cron()` (`wp_remote_post` with `blocking=false, timeout=0.01`).

### Changed
- **[`inc/plausible-api.php`](inc/plausible-api.php) — `sn_plausible_dashboard_data()` and `sn_plausible_realtime()` are now read-only.** They return whatever's in the transient (possibly empty on first-ever load, possibly stale during a refresh in flight) and never make a network call. Dashboard render is now constant-time regardless of Plausible API health.
- **Transient retention decoupled from freshness target.** Batch retention is `DAY_IN_SECONDS` (was 5 min); freshness threshold is still 5 min, gated by the `fetched` field embedded in the payload. Realtime retention is 5 min (was 30s); freshness is still 30s. Effect: stale data remains visible if Plausible is unreachable (the widget footer shows "cached X ago" so the staleness is honest), and refresh failures don't poison the transient with `null`.

### Added
- **`sn_plausible_refresh_dashboard()` and `sn_plausible_refresh_realtime()`** in [`inc/plausible-api.php`](inc/plausible-api.php) — WP-Cron callbacks that do the actual API work. Hooked to `sn_plausible_refresh_dashboard` and `sn_plausible_refresh_realtime` actions. Run in a separate process via the cron loopback, never on a user-facing request.
- **`sn_plausible_warm_caches()` admin warmer** at `admin_init` priority 5. Checks the cached payload's `fetched` timestamp; if it's older than the freshness threshold (or missing entirely), schedules a single-event cron job. Priority 5 is intentional — it runs before `wp_loaded`, so `wp_cron()` picks up the just-scheduled event in the same request and dispatches the non-blocking loopback before the admin response is sent. Capability gate (`view_stats` / `manage_options`) matches the widget registration in [`inc/plausible-widget.php`](inc/plausible-widget.php) so we don't warm caches for users who can't see the widgets.
- **`SN_PLAUSIBLE_BATCH_RETENTION`, `SN_PLAUSIBLE_REALTIME_RETENTION`, and the two refresh-hook constants** in [`inc/plausible-api.php`](inc/plausible-api.php) so the SWR semantics are explicit at the top of the file rather than hardcoded inline.

### Fixed
- **First-ever-load footer** in [`inc/plausible-widget.php`](inc/plausible-widget.php) — `sn_pl_footer()` no longer renders `human_time_diff()` against an epoch-zero `fetched` timestamp (which would have read as "cached 56 years ago"). When `fetched=0`, the footer instead shows *"refreshing in background — reload in a moment"* so the user knows what they're waiting on.

### Why patch (7.2.6)
Pure performance/architecture fix scoped to the existing Plausible module — no new user-visible features, no settings schema changes, no API surface changes. The widget rendering, configuration tab, and token resolution chain are all untouched. The batch cache key is unchanged because the payload shape is unchanged; old transients written under v7.2.5's 5-min TTL age out naturally and the next warmer run writes new transients under the longer retention. The realtime cache key bumps `v2 → v3` because the payload shape changed from a bare int to `{ value, fetched }` so the warmer can age-check the data without hitting the network — old `v2` transients age out in 30s and the new `v3` shape takes over on the next warmer run. Patch 6 of 7.2; cap is 7 per minor.

### Out of scope (follow-ups)
The same blocking pattern exists in two other places that pre-date v7.2.x and weren't part of this complaint:
- [`inc/template-self-heal.php`](inc/template-self-heal.php) — iterates monitored templates with sequential GitHub Contents API calls on `admin_init`, 10s each. Worst-case dwarfs the Plausible hang on installs with many templates.
- [`inc/updater.php`](inc/updater.php) and [`inc/admin-page.php`](inc/admin-page.php) — GitHub commits/compare API calls on `pre_set_site_transient_update_themes` and on the S&N options page render, 10s each.

Both are candidates for the same SWR pattern in a future patch.

## [7.2.5] — Plausible: admin tab for Stats API key (no more wp-config edits)

The constant-only token storage from v7.2.4 worked but required SSH/SFTP into Cloudways to rotate. Adds an admin UI tab so the Stats API key can be saved, tested, and rotated from inside WordPress — same precedence pattern as the Cloudflare module's token storage at [`inc/cloudflare-purge.php:58-83`](inc/cloudflare-purge.php).

### Added
- **`inc/plausible-admin.php`** — new tab at *Appearance → Signal & Noise → Plausible*. Surfaces:
  - **Status card** — domain (read from the Plausible plugin), current token source (constant / option / plugin fallback), last API call result with `human_time_diff()` timestamp.
  - **Stats API Key field** — paste to save, type `clear` to remove, last 4 chars displayed obscured (`••••WXYZ`). Hidden when `SN_PLAUSIBLE_STATS_TOKEN` is defined in `wp-config.php` — the constant takes precedence and the form locks itself with an explanation.
  - **Test Connection button** — fires a synchronous 7-day aggregate call against the Stats API and reports success (`✓ N visitor(s) in last 7 days`) or failure with the actual HTTP code + body excerpt. No more guessing whether the credentials work.
  - **Embedded Stats link** — quick path to the Plausible plugin's in-admin dashboard.
  - Saving or clearing the token automatically invalidates the dashboard data + realtime + error transients via `sn_pl_admin_invalidate_caches()` so the next dashboard pageview fires fresh API calls. Without this, users would paste a new key and still see cached 401 errors for 5 minutes.
- **Token resolution priority** in [`sn_plausible_config()`](inc/plausible-api.php) is now three-tier:
  1. `SN_PLAUSIBLE_STATS_TOKEN` constant (file-based, locked, preferred for CI-deployed credentials).
  2. `sn_plausible_stats_token` option (admin-saved via the new tab, non-autoloaded so it isn't in PHP memory on every request).
  3. Plausible plugin's `api_token` from `plausible_analytics_settings` (last-resort fallback for setups where the namespaces happen to overlap).
- **`SN_PLAUSIBLE_TOKEN_OPT` const** in [`inc/plausible-api.php`](inc/plausible-api.php) so any module can reference the option key without hardcoding the string.

### Changed
- **[`inc/admin-page.php`](inc/admin-page.php)** — `Plausible` added to `$valid_tabs` and `$tab_labels` (between Cloudflare and Reading Time). Tab body dispatches via `do_action( 'sn_admin_plausible_tab' )`, the same module-owned-UI pattern used by Cloudflare and Reading Time. The dispatcher in `sn_theme_options_page()` doesn't know about Plausible's internals — it just hands control to whoever's listening on the action.

### Why patch (7.2.5)
Pure additive: new admin tab + a third tier in the existing token resolution chain. The `wp-config` constant path from v7.2.4 still works unchanged (and is still preferred for security). The Plausible plugin fallback still works unchanged. Existing widget rendering, caching, and API client are untouched. Patch 5 of 7.2; cap is 7 per minor.

## [7.2.4] — Plausible: SN_PLAUSIBLE_STATS_TOKEN constant + corrected token-source assumption

The diagnostic added in v7.2.3 caught a real architectural mistake from v7.2.1: the Plausible plugin's stored `api_token` is a **Plugin Token** scoped to `/api/plugins/wordpress/*` (the namespace the plugin uses for proxy resource management, the embedded stats page wizard, etc.), **not** a Stats API key. The Stats API at `/api/v1/stats/*` rejects Plugin Tokens with HTTP 401 `"Invalid API key or site ID"` — confirmed live on the Plausible CE install.

These are two separate token namespaces in Plausible. They look identical (both are bearer-style strings), and the WP plugin uses both kinds internally — but only Stats API Keys (created in *Plausible → Settings → API Keys*) have `stats:read` scope.

### Added
- **`SN_PLAUSIBLE_STATS_TOKEN` wp-config constant** as the preferred token source. Matches the existing pattern for sensitive credentials in this codebase (`SN_GITHUB_TOKEN`, `SN_CLOUDFLARE_API_TOKEN`) — file-based, can't be exfiltrated through a SQL injection or compromised admin login. Setup:
  ```
  // 1. In Plausible CE → Settings → API Keys → New API Key
  //    (scope: stats:read on the site domain)
  // 2. In wp-config.php:
  define( 'SN_PLAUSIBLE_STATS_TOKEN', 'plnt_…' );
  ```

### Changed
- **`sn_plausible_config()` token resolution priority.** Now checks the `SN_PLAUSIBLE_STATS_TOKEN` constant first; falls back to `plausible_analytics_settings.api_token` only if the constant is undefined. The fallback is kept in case a future Plausible release unifies the two token namespaces, or for Plausible Cloud setups where the distinction may not apply — but for self-hosted CE in 2026, the constant is what works.
- **"Not configured" error message** rewritten to walk users through the correct setup explicitly: set domain in plugin settings, create a Stats API key separately, drop the constant in wp-config. The previous message said "set domain + Plugin Token in *Settings → Plausible Analytics*" which was actively misleading.
- **Cache key bumped `v3 → v4`.** Same reason as the v7.2.3 bump: forces a fresh fetch immediately after the constant is added, so users don't have to wait 5 minutes for the cached 401 errors to age out before seeing the widgets work.

### Why patch (7.2.4)
Targeted bug fix for the silent-failure mode v7.2.3's diagnostic exposed. New constant is purely additive (the plugin's `api_token` fallback is unchanged for sites where it happens to work). Patch 4 of 7.2; cap is 7 per minor.

## [7.2.3] — Plausible widgets: surface API errors + defensive scheme handling

The widgets in v7.2.2 rendered "—" across the board on the live install — meaning the API calls were failing silently. The original `sn_plausible_api()` returned `null` on any non-200 with no breadcrumb, so the maintainer couldn't tell whether they were looking at a bad URL, a bad token, a scope mismatch, or a network blip. This release adds a self-debugging surface and fixes the most likely root cause.

### Added
- **Inline API error diagnostic.** [`inc/plausible-api.php`](inc/plausible-api.php) now captures the URL + HTTP status + first 240 bytes of the response body into a `sn_plausible_last_error` 5-min transient on every failure. [`inc/plausible-widget.php`](inc/plausible-widget.php) renders this inline below the snapshot widget, gated behind `manage_options` so non-admins never see internals. Token is never written to the transient — only the URL (which doesn't carry credentials), the HTTP code, and the body excerpt. Error is auto-cleared on the next successful API call so transient outages don't leave stale "API failed" banners on the dashboard.
- **Diagnostic shows once, not four times.** Only the snapshot widget renders the diagnostic — the other three panels are downstream of the same API + cache, so a single inline notice is enough.

### Fixed
- **Defensive `https://` prepend on self-hosted Plausible base URL.** [`sn_plausible_config()`](inc/plausible-api.php) now prepends `https://` to `self_hosted_domain` when the plugin's saved value lacks a scheme. The Plausible WP plugin's settings field accepts both forms (hostname or full URL), but `wp_remote_get()` requires a scheme to dispatch. A bare hostname like `plausible-analytics-ce-production-fcb9.up.railway.app` previously produced a silent `WP_Error` that bubbled up as `null` and then "—" in every widget. Now the URL is normalised before dispatch.

### Changed
- **Cache key bumped `sn_plausible_dashboard_v2` → `sn_plausible_dashboard_v3`.** Without this, sites updating from v7.2.2 keep reading the empty-data cache that v7.2.2 wrote during its silent failure, and the new error capture never gets a chance to populate the diagnostic transient. The bump forces a fresh fetch on the first pageload after update. The 30-sec realtime cache (`sn_plausible_realtime_v2`) wasn't bumped — it ages out naturally too quickly to matter.

### Why patch (7.2.3)
Diagnostic addition + targeted bug fix. No new user-visible feature; existing widgets get smarter when something's wrong, and the most likely silent-failure mode for self-hosted CE installs gets defensively fixed. Patch 3 of 7.2; cap is 7 per minor.

## [7.2.2] — Plausible widgets: native WP admin styling + correct dashboard link

The four Plausible dashboard widgets shipped in v7.2.1 imported theme-front-end styling (Bebas Neue display font, DM Mono labels, blood-red accents, asphalt card backgrounds with a red left rail) into the WordPress admin. The admin is a shared surface — different themes and plugins coexist there, and users expect WP's own UI conventions, not theme-brand styling. The widgets read as foreign-pasted instead of native WP. Fixed.

### Changed
- **`inc/plausible-widget.php`** — inline CSS rewritten to WP admin conventions:
  - System font stack inherited from WP admin (no `font-family` override; previously forced `Bebas Neue` and `DM Mono`).
  - Numbers are bold + slightly larger but use the inherited admin font, matching the visual weight of native widgets like *At a Glance* and *Activity*.
  - Palette swapped to WP admin tokens: `#1d2327` primary text, `#646970` muted, `#f0f0f1` hairlines, `#d63638` error. Dropped `#000`/`#666`/`#e00404`/`#f5f5f5`.
  - Removed the asphalt card background and `border-left: 3px solid #e00404` on stat tiles — the brand left-rail belongs on the front end, not in the admin.
  - Removed `letter-spacing: 0.18em` + `text-transform: uppercase` on labels — WP admin doesn't use that treatment.
  - Realtime widget's giant red Bebas Neue numeral is now a bold `#1d2327` figure at 2.5rem.
  - Added `font-variant-numeric: tabular-nums` to breakdown-list values so visitor counts align vertically — small detail, makes the lists scan as native.
- **Dashboard footer link** in all four widgets now points to `admin_url( 'index.php?page=plausible_analytics_statistics' )` — the Plausible plugin's *own* embedded stats page inside WP admin — instead of constructing a public `https://plausible.io/{domain}` URL. Same surface the user is already authenticated on, no `target="_blank"`, no separate plausible.io login required. Arrow changed from `↗` (external convention) to `→` (internal navigation).

### Why patch (7.2.2)
Visual calibration on a v7.2.1 feature. No behaviour change to the data flow, the cache layers, the API client, the security module, or anything else — purely styling + a one-line URL swap that fits an existing admin route the user already had configured. Patch 2 of 7.2; cap is 7 per minor.

## [7.2.1] — Hardening pass + Plausible dashboard widgets + escaping cleanup

A QA / security pass against [WordPress's hardening guide](https://wordpress.org/documentation/article/hardening-wordpress/), plus a four-widget Plausible Analytics panel for the WP dashboard.

### Added
- **`inc/security-headers.php`** — empirically scoped against what Cloudflare's edge already covers for juanlentino.com (verified 2026-05-08 via `curl -I`), so the module only does work that *isn't* already happening at the edge:
  - **`/wp-json/wp/v2/users` 401 for anonymous requests.** This is the genuine fix — production was leaking `{"id":616000,"name":"Juan","slug":"juanlentino"}` to anyone hitting the endpoint, free reconnaissance for brute-force attackers. Implemented via `rest_authentication_errors` so authenticated callers (block editor, REST clients, the new Plausible widget proxy) keep working.
  - **`Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()`.** Cloudflare's edge config wasn't sending one; this fills exactly that gap. The other common security headers (`X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, HSTS, full CSP) are already emitted at the edge — re-sending them from PHP would be redundant since CF proxies all traffic.
  - **Belt-and-suspenders fallbacks:** XML-RPC disabled via `xmlrpc_enabled` + `xmlrpc_methods` + `pings_open` + `header_remove('X-Pingback')`, and `?author=N` redirected to home for anonymous visitors. Both effectively no-op against the current Cloudflare config (XML-RPC already returns 520 at the edge, `?author=N` already returns 404 since no author archive is registered) but cost nothing and survive an edge config drift.
  - All four hardenings are individually filterable (`sn_security_permissions_policy`, `sn_security_lock_rest_users`, `sn_security_block_author_enum`, `sn_security_disable_xmlrpc`).
- **`inc/plausible-api.php` + `inc/plausible-widget.php`** — four discrete Plausible Analytics dashboard widgets, all reading from one shared 5-min cache:
  - **Last 7 days** — visitors / pageviews / bounce rate / average visit duration in a 2×2 brutalist tile grid.
  - **Right now** — large red Bebas Neue numeral of current visitors (Plausible realtime endpoint), 30-sec cache so it actually feels real-time.
  - **Top pages (7d)** — top 7 URLs by visitors.
  - **Top sources (7d)** — top 7 referrers, with `Direct / None` label for blank source values.
  - Reads `domain_name` + `api_token` from the Plausible plugin's existing `plausible_analytics_settings` option — no separate `wp-config.php` constant needed. Self-hosted Plausible is supported via the same option's `self_hosted_domain` key. Failure modes (plugin missing, token absent, API error) degrade to inline notices, never fatal.
  - One batched API fetch every 5 min covers all four "last 7 days" widgets; only the realtime widget makes a second round-trip (every 30 s).

### Fixed
- **`/notes` reading-time meta-key bug.** [`inc/page-notes-render.php:74`](inc/page-notes-render.php) read from `_sn_reading_time` but the canonical cache key is `_sn_reading_time_minutes` (defined as `SN_READING_TIME_META_KEY` in [`inc/reading-time.php:36`](inc/reading-time.php)). Result: every row in the /notes index missed the cache and recomputed `str_word_count` per render. Now reads through the constant with `sn_get_reading_time()` as the cache-populating fallback, so misses self-heal.
- **`/resume` duplicate "20+ Years".** Hero eyebrow read `Dossier · Background · 20+ Years` while the meta line below read `20+ Years · 50+ Collaborations · GRAMMY Voting Member` — the same tag in both places. Eyebrow trimmed to `Dossier · Background`, matching `/about`'s two-part `Dossier · Who I Am` form. The meta line keeps "20+ Years" since it anchors the credentials sequence.

### Changed
- **Admin notice escaping.** [`inc/admin-page.php`](inc/admin-page.php) was echoing notice severity + body unescaped. Severity now wrapped in `esc_attr()`; body in `wp_kses_post()` (some entries deliberately ship inline `<a>`/`<code>` markup so `esc_html` would mangle them). Theme-Version label in the dashboard status table now wrapped in `esc_html()` against the `Version:` header value.
- **`wp_unslash` on `$_POST` reads.** [`inc/admin-page.php`](inc/admin-page.php) and [`inc/cloudflare-purge.php`](inc/cloudflare-purge.php) read `$_POST['sn_action']` directly into `sanitize_text_field()` without unslashing first. Now `wp_unslash` ahead of sanitize, per WP coding standards. Cloudflare module refactored to a single sanitised `$posted_action` shared between the save and purge branches.
- **`esc_url()` on theme asset URLs.** [`inc/assets-frontend.php`](inc/assets-frontend.php) emitted `get_theme_file_uri()` directly into `<link href>` and `@font-face src` without escaping. `get_theme_file_uri()` returns a URL but isn't context-escaped — wrapping with `esc_url()` is the WP convention.

### Why patch (7.2.1)
Bug fixes + security cleanup + a hardening module + a new dashboard widget set. The widgets are visible new behaviour, but they're admin-only and additive; the security headers similarly add defensive output without changing what the site does. Per the project's convention (see 7.1.6 for prior art — accessibility/animation work landed as patch), this stays at patch level. Patch cap is 7 per minor; this is patch 1 of 7.2.

## [7.2.0] — /services № markers — breathing room

The catalog-number markers (`№ 01` through `№ 06`) on the /services cards rendered with only a 4px gap between the number and the card heading. The number read as part of the heading rather than as an eyebrow above it. Cause: the inline markup set `margin-bottom: 0` on the number paragraph and `margin-top: 0.25rem` on the heading.

Fix: bumped each number's `margin-bottom` from `0` to `var:preset|spacing|10` (8px) and removed the `0.25rem margin-top` from each heading. Result: ~12-16px gap between the number and heading on every card; image → number gap stays at the existing 16px. Numbers now read as proper eyebrows.

Why MINOR (rolling over from .7): the project's patch cap is 7 per minor (per CLAUDE.md). v7.1.0 through v7.1.7 used the full cap, so this calibration rolls to v7.2.0 even though it would normally be a patch.

## [7.1.7] — Remove scripture quotes from /404 and /contact

The 404 page carried Isaiah 30:21 ("Your own ears will hear him...") and the contact page carried Matthew 7:7 ("Keep on asking, and you will receive..."). Both removed — the brand voice doesn't otherwise lean on religious framing, so these read as out-of-register against the rest of the site. The pages keep their other editorial copy unchanged: the 404 still says "SIGNAL LOST / The frequency you're looking for doesn't exist..." and contact still has its existing dek about projects/sound/spam.

In the v7.1.6 notes I'd called the 404 quote "music-themed scripture" — that conflated two unrelated passes (the music-themed `SIGNAL LOST` line is editorial brand copy; the scripture is a separate thing) and I shouldn't have left the scripture in place when I touched the file. Sweeping for similar patterns elsewhere came up clean — no other scripture references in templates or seed content.

## [7.1.6] — Accessibility + 404 polish

Two findings from the design review:

### Fixed
- **Home hero animations now honour `prefers-reduced-motion`.** The site already gated block-level fade-ins behind `@media (prefers-reduced-motion: no-preference)` (in [base.css:135](assets/css/base.css:135) and [critical.css:116](assets/css/critical.css:116)), but the hero's own staggered cascade — `.sn-header`, `.sn-hero-title`, `.sn-hero-subtitle`, `.sn-hero-accent`, `.sn-hero-cta` — was declared outside that gate. Motion-sensitive users got a 1.8-second cascading fade-in on the FIRST screen they saw, with no way to suppress it. Wrapped all five animations in a single `prefers-reduced-motion: no-preference` block in both [critical.css](assets/css/critical.css) and [layout.css](assets/css/layout.css). Also consolidates the v7.1.4 `prefers-reduced-motion: reduce` block for `.sn-hero-accent` into the new gate (single source of truth).

### Added
- **404 page eyebrow.** Added an `Error · 404 · No Signal` `.sn-catalog-eyebrow` above the giant "404" headline in [templates/404.html](templates/404.html), bringing the page into the catalog vocabulary established in v7.1.0. The page's existing editorial copy ("SIGNAL LOST", music-themed scripture quote) didn't need changing — the eyebrow just gives it a small additional tonal anchor that ties it to the rest of the index pages.

## [7.1.5] — Revert hero accent to 120px editorial mark

v7.1.4 set the hero accent to `width: 100%; max-width: 640px;` to make it match the dek's max-width and read as an underline. In practice it overshot: the dek wraps at natural word boundaries (well before its 640px max), so the accent always ended up wider than the visible text on either line. The "underline" reading didn't land, and the editorial-mark reading from v7.1.0 was lost.

Reverting to `width: 120px;` fixed. The 120px length was correct as an editorial flourish — a stamp beneath the dek — which is what fits the brutalist hero. Keeping the CSS-class form (no inline styles) and the `prefers-reduced-motion` rule from v7.1.4. Comment in the CSS documents the failed attempt so it doesn't get re-tried.

Lesson: container `max-width` ≠ rendered text width. When you want a graphic to align with text, you need to measure the text (which CSS doesn't expose), not the container. Better to commit to the editorial-mark interpretation than to half-implement an underline.

## [7.1.4] — Home hero accent: responsive, matches dek width

The blood-red accent rule on the front page was hardcoded to `120px` wide via inline style — read as a small editorial mark next to a 640px-wide dek. Made it responsive: `width: 100%; max-width: 640px;` so the accent's right edge now lands at the same point as the dek's right edge ("together."). On narrower viewports both shrink together (100% of the available column width), so the accent always reads as the dek's underline rather than a floating mark.

Implementation moves the styling out of inline-on-the-element and into [layout.css:247](assets/css/layout.css:247) where it can express the constraint (`max-width: 640px` matches `.sn-hero-subtitle`'s own `max-width: 640px` from the same file). Honours `prefers-reduced-motion` for the existing fade-in.

## [7.1.3] — Catalog rollout: skipped items + redundancy fix

Three small follow-ups to v7.1.0–v7.1.2:

### Fixed
- **`/about` secondary eyebrow** read `Education & Mentorship · Pass-On` directly above the heading `What I Know, I Pass On.` — the "Pass-On" repeated immediately on the next line. Trimmed the eyebrow to just `Education & Mentorship`.

### Added
- **`/resume` meta line** below the dek paragraph using `.sn-catalog-meta`: `20+ Years · 50+ Collaborations · GRAMMY Voting Member`. Pattern matches the meta-line on /notes ("N entries · last updated YYYY.MM.DD"). All three values come from existing copy on the live site (the /resume dek itself, the /about bio, the /services credibility strip), so no invented facts.
- **`/music` Muso.AI section-marker** — converted the `.sn-catalog-eyebrow` from v7.1.0 (`Full Discography · Verified Credits`) into a full `.sn-catalog-section` block with hairline border. The Muso.AI panel now reads as a labelled section inside the right column rather than a standalone caption.

These were items I'd called out in the catalog audit but skipped during the v7.1.0 rollout. Closing the gap.

## [7.1.2] — Drop unverified location from /about eyebrow

In v7.1.0 the catalog rollout introduced a hero eyebrow on /about that read `Dossier · Buenos Aires → Miami · Who I Am`. The "Miami" was an invented geographic claim (no source in the existing copy) and the user's actual current city isn't settled, so the location shouldn't appear in load-bearing identity copy at all. Reverting the eyebrow to a non-geographic form: `Dossier · Who I Am`.

Lesson: when applying a design vocabulary that wants "specificity" in the eyebrow, the specificity must come from existing copy or from facts the user has already published, not from filling in plausible-sounding details to make the line denser. /resume's `Dossier · Background · 20+ Years` and /music's `Catalog · Discography · 2005 → 2026` reused values that were already on the live site; /about's geographic interpolation didn't, and shouldn't have.

## [7.1.1] — Eyebrow alignment fix

The catalog eyebrows on `/about`, `/services`, `/music`, `/resume` rendered against the viewport's far-left edge instead of centered with the rest of the constrained content. Cause: `.sn-catalog-eyebrow` and `.sn-catalog-meta` in [components.css](assets/css/components.css) used `margin: ... !important` shorthands which silently set `margin-left: 0 !important` and `margin-right: 0 !important`, overriding WP's constrained-layout rule that centers children via `margin-left: auto !important; margin-right: auto !important;`.

Fix: specify only `margin-top` / `margin-bottom` so the inline-margin auto-centering rule still wins. Added a comment in the CSS explaining the gotcha so it doesn't get reintroduced. Section labels and counts kept `margin: 0` because they're inside a grid container — margins don't participate in grid placement, so the bug wouldn't have manifested there anyway.

Lesson: when adding CSS that targets WP block-rendered children of a constrained layout, never use a margin SHORTHAND with `!important`. Either use `margin-top` / `margin-bottom` only, or specify `margin-inline: auto !important` explicitly to participate in the centering rule.

## [7.1.0] — Catalog vocabulary rollout

The "Industrial Catalog" design vocabulary developed for `/notes` extends across the site's index/listing surfaces. New shared CSS components in [assets/css/components.css](assets/css/components.css) — `.sn-catalog-eyebrow`, `.sn-catalog-meta`, `.sn-catalog-section` (label + count), `.sn-catalog-number` — replace the ad-hoc blood-mono-caps eyebrow patterns each page reinvented at slightly different sizes. The vocabulary is applied selectively: index pages get the full treatment, action/CTA pages stay untouched.

### Why minor

User-visible design changes across five page templates plus a new shared CSS surface. No public API or settings schema change. Per the project's SemVer policy this is a `MINOR` bump (`new user-visible capabilities`), continuing the v7 series.

### `/services` — Tier 1 full treatment
- Hero eyebrow: `What I Do` → `Services · 06 Offerings · 02 Sections` (catalog-eyebrow class).
- The two blood-uppercase `<h2>` section headings (`Music & Production`, `Business & Strategy`) replaced with `.sn-catalog-section` blocks — small mono caps label, hairline divider, count counter (`04 / 06`, `02 / 06`).
- Each of the six service cards now carries a `№ 01` through `№ 06` mono-blood marker above its heading, sized 0.85rem to read as quiet meta rather than competing with the heading.
- The four blood-eyebrow elements existed at three different sizes (0.75rem, 0.85rem) before — now unified through the component.

### `/provenance` — Tier 1 numbered pillars
- The two pillar cards in `sn_provenance_papers_index_markup()` now lead with a `№ 01` / `№ 02` catalog-number marker, mirroring the /notes pillar treatment for visual continuity between the two index pages.
- New constant `SN_PROV_CATALOG_NUMBERS_OPT` and migration `sn_migrate_provenance_catalog_numbers()` re-renders existing installs' pillar body — without it, the markup-function update would only take effect on fresh installs because earlier migrations' flags lock in the prior shape.
- Seed file [inc/seed-content/provenance-body.html](inc/seed-content/provenance-body.html) updated in lockstep so fresh installs ship the same shape.
- Defensive: gated on the SSRN abstract_id 6730343 anchor; if missing, admin has hand-edited away from seed shape and the migration bails without flagging so a future run can complete after recovery.

### Tier 2 — mono hero eyebrows
- `/resume`: `Background` → `Dossier · Background · 20+ Years`.
- `/music`: `Listen` → `Catalog · Discography · 2005 → 2026`. Secondary `Full Discography` → `Full Discography · Verified Credits`.
- `/about`: `Who I Am` → `Dossier · Buenos Aires → Miami · Who I Am`. Secondary `Education & Mentorship` → `Education & Mentorship · Pass-On`.

### Not changed
- `/` (front page) — landing/identity energy, not browse energy.
- `/contact`, `/work-with-me` — action pages, different tone.
- `/notes/{slug}/`, `/provenance/over-detection/`, `/provenance/as-substrate/` — long-form reading surfaces, not catalog surfaces.

## [Unreleased] — Operational fixes (post-v7.0.0)

### `/notes` rebuilt from scratch — PHP-rendered, redesigned

After three incidents in two months where `/notes` rendered stale content despite the canonical version being correct in `main` (deploy silently skipping the file, broken self-heal corrupting it, stale `wp_template` DB row surviving the one-shot migration), the page is now rendered entirely from PHP via `template_include`. WordPress's block-template resolution chain — file ↔ DB ↔ object cache ↔ registry — never runs for this route. Filter approach from previous commits (118336b, cb055eb) replaced; those filters had to fight every layer of the resolution chain and lost when an unexpected layer cached the wrong version.

#### Architecture

- **[inc/page-notes-template.php](inc/page-notes-template.php)** — `template_include` filter at priority 999 short-circuits to our render file when `is_page('notes')`. Defensive: if the render file is missing for any reason, falls through to WP's normal resolution (which uses `templates/page-notes.html` as a kept-on-disk fallback with the correct two-card content). Also runs an `admin_init` sweep to delete any stale `wp_template` DB row for `page-notes` — keeps the Site Editor template list clean and prevents the row from re-appearing.
- **[inc/page-notes-render.php](inc/page-notes-render.php)** — full PHP renderer. Builds the entire HTML document from scratch: `wp_head()`, `body_class()`, `wp_body_open()`, the existing `header` block template part, the page body, the existing `footer` block template part, `wp_footer()`. Inline `<style>` block in the document head so the rendering and the design ship together as a single atomic unit — if the file deploys, the whole page deploys; if it doesn't, the fallback in `templates/page-notes.html` takes over.

#### Design — "Industrial Catalog"

The page now reads as a directory listing for the brand, like a library card catalog or a vinyl-store archive. The aesthetic stays inside the existing brutalist white/asphalt/blood vocabulary but adds editorial precision:

- **Hero** — `INDEX · VOL. 01 · {year}` eyebrow in mono caps, oversized "Notes." display headline (clamp 4-11rem), dek line, entry count + last-updated date in a meta line.
- **Pillar essays — numbered** (`№ 01`, `№ 02`) in mono blood-red on a light asphalt card with a 6px-wide blood-red left rail. The rail expands to 14px and the card translates 2px on hover — a subtle physical-feeling response. CTA is a tracked uppercase "Read essay →" with the arrow shifting on hover.
- **Notes index — tabular**. Each row is a 2-column grid on desktop: `[140px date+meta col] [1fr title+excerpt col]`. Date renders as `2026.05.07`, reading time as `03 MIN` (zero-padded for tabular alignment with the date). Title in Bebas Neue display, excerpt in body grey. Title link uses an animated underline that fills from 0 to 100% on hover.
- **RSS footer — terminal status line**. Mono caps `Feed — /notes/feed/` followed by a blinking blood-red cursor (`@keyframes sn-blink` at 1.05s, steps(2)). Subtitle `No subscription form. No schedule.` underneath.
- **Page entry** — staggered reveal animation on first paint (cubic-bezier ease, 12px translateY, 60ms cascade across the six top-level sections). Honours `prefers-reduced-motion`.

The 140px main `padding-bottom` from the prior layout fix still applies for fixed-footer clearance — verified the new RSS line sits well above the footer.

#### Trade-off

`/notes` can no longer be edited via Site Editor — the canonical layout lives in `sn_render_notes_page()` (PHP). Given the page has only ever been edited by code commits in practice, this trade is correct: removing the editing surface removes the failure mode.

### `/notes` template now PHP-authoritative

Even after `padding-bottom: 140px` shipped (proving 7ad2dd8 reached disk) and the post-update force-run self-heal hook fired, `/notes` STILL rendered only the first pillar card. PHP `wp-template;dur=50ms` confirmed live-render under `x-cache: MISS` — meaning the renderer was producing single-card output FRESH, not from a cache. Three layers could be responsible: (1) `templates/page-notes.html` on disk still stale despite multiple deploys, (2) a `wp_template` DB override that survived `sn_clear_template_overrides()`, or (3) a registry/object-cache holding a parsed block tree.

The fix sidesteps all three: we hook `pre_get_block_template` and return a `WP_Block_Template` object built from a PHP heredoc literal in [inc/page-notes-template.php](inc/page-notes-template.php). That filter runs BEFORE WP's DB-then-file resolution chain — DB override can't win, file drift can't win, registry cache can't go stale because PHP rebuilds the object from the literal on every call. The `templates/page-notes.html` file is kept for Site Editor preview parity and as a reference, but is no longer load-bearing for front-end rendering.

This is the third incident where `templates/page-notes.html` has been the source of stale-content drift on `/notes` (2026-04 deploy-skip, 2026-05 corrupt-self-heal, 2026-05 mystery-still-stale). Pulling it out of the rendering path eliminates the surface entirely.

#### Added
- **[inc/page-notes-template.php](inc/page-notes-template.php)** — new module with `sn_page_notes_template_content()` returning canonical block markup, `sn_page_notes_build_template_object()` constructing a `WP_Block_Template` matching the shape WP's `_build_block_template_result_from_file()` produces (so consumers — rendering pipeline, Site Editor, REST API — see no behavioural difference). Registered on `pre_get_block_template` filter (front-end / single-template lookups) AND `get_block_templates` filter (Site Editor template list endpoint) so the editor's template picker reflects what the front-end actually renders.
- **functions.php** require_once added between `template-self-heal.php` and `admin-page.php` so the filter is registered before any block-template lookup.

#### Editing /notes layout going forward
Edit the heredoc in `sn_page_notes_template_content()` in [inc/page-notes-template.php](inc/page-notes-template.php). The .html file is no longer authoritative.

### Recovery hardening — self-heal force-run + RSS layout fix

Two unrelated production issues that surfaced post-v7.0.0:

1. `/notes` was rendering the OLD single-pillar-card content for hours despite (a) `main` having the correct two-card content, (b) the deploy reporting success, (c) `wp_template` DB overrides at zero, and (d) Cloudflare reporting `cf-cache-status: DYNAMIC`. The theme self-heal module (added in [390c14b](https://github.com/juanlentino/signal-and-noise/commit/390c14b)) was designed for exactly this failure mode but had two gaps: its 5-minute rate-limit option was set by the broken initial run (which corrupted templates with JSON; fixed in [7c820ec](https://github.com/juanlentino/signal-and-noise/commit/7c820ec)), blocking the FIXED self-heal from running again; and its only trigger was ambient `admin_init` pageviews, so recovery wasn't immediate after clicking Update. There was no manual button to force a re-sync — recovery required SSH/SFTP or waiting for the rate-limit to expire.

2. The RSS link at the bottom of `/notes` was unreachable — the fixed-position `.sn-footer` (z-index 9990) was overlapping the last lines of `<main>` because the `padding-bottom: 90px` buffer was too tight. On desktop the buffer was just enough (~14px clearance over a ~76px footer); on mobile the footer wraps to two rows (~120px tall) and ate 30px of the RSS line.

#### Added
- **`sn_self_heal_force_run()`** in [inc/template-self-heal.php](inc/template-self-heal.php). New entry point that bypasses the 5-min rate-limit gate AND clears the per-file failure cooldown, so files in 1-hour back-off get retried immediately. The original `sn_self_heal_run()` (ambient `admin_init` path) and this force-run path now share an internal `sn_self_heal_execute( $force )` implementation — same validation gates apply to both. The 7 content-shape gates from [7c820ec](https://github.com/juanlentino/signal-and-noise/commit/7c820ec) (HTTP 200, JSON parse, content+encoding fields, base64 decode, size match, starts-with-`<` for HTML files, differs from local) are unchanged; force-run only changes WHEN the check happens, never WHAT gates the write.

- **"Heal Templates Now" admin button** on the Dashboard tab in [inc/admin-page.php](inc/admin-page.php). One-click manual recovery for the "deploy didn't take effect on a route" failure mode. Calls `sn_self_heal_force_run()` synchronously and reports per-file results in admin notices: success notice lists the paths that were re-synced; error notice lists paths where drift was detected but the write failed (with a hint to check SFTP file permissions). Sits next to *Purge Caches* in the Actions row.

- **Post-update auto-heal hook** in [inc/updater.php](inc/updater.php). Hooks into `upgrader_process_complete` at priority 20 (after the existing SHA-stash hook at priority 10). Force-runs `sn_self_heal_force_run()` immediately after every successful theme update, so every Update click ends with a verified file-content sync against `main` HEAD. Closes the loop on the original silent-skip failure mode: the file system either matches `main` or admin sees an error notice naming exactly which paths failed.

#### Fixed
- **`sn_purge_all_caches()` now clears self-heal state.** New `self_heal_state` flag (default `true`) deletes the `sn_self_heal_last_check` rate-limit option and the `sn_self_heal_failures` cooldown map. These are stored as regular options, so the existing `_transient_sn_*` SQL DELETE didn't reach them. Closes the surprising user-experience gap where clicking *Run Full Reset* didn't actually unblock a stuck self-heal run. Both options are gated on `defined()` of their option-name constants, so the helper stays safe if the self-heal module is ever disabled.

- **RSS link layout on `/notes`.** Bumped `main.wp-block-group { padding-bottom }` from `90px` to `140px` in [assets/css/layout.css](assets/css/layout.css). Sized for the worst case (mobile-wrapped footer ≈ 120px) plus a 20px buffer. Comment in the file documents the constraint so a future "this padding looks excessive" cleanup pass doesn't re-introduce the bug. Universal fix — every page benefits, but `/notes` was where the failure manifested because `.sn-notes-rss` is the last element in `<main>` and sat directly inside the previous overlap zone.

### Earlier in this Unreleased band

Two related fixes that surfaced after the v7.0.0 deploy: (a) `/notes` continued to render the old single pillar card after the user clicked Update, despite `wp_template` DB overrides being cleared and the theme files being correctly replaced, because Breeze's HTML page cache wasn't being invalidated on theme file changes; (b) the admin maintenance page accumulated four sections in a single Dashboard tab and grew unwieldy — Cloudflare config + Reading Time cleanup + Status + Actions all stacked vertically.

### Fixed
- **`sn_purge_all_caches()` unified helper** in [inc/template-maintenance.php](inc/template-maintenance.php). Single source of truth for "make sure no stale rendered HTML or stale metadata is being served anywhere". Covers WP object cache, theme metadata cache, our own `sn_*` transients (targeted DELETE — leaves plugin transients alone), Breeze + Varnish via plugin action hooks, Cloudflare zone via the new purge module, DB template overrides via `sn_clear_template_overrides()`, and an `update_themes` repopulate so the Updates page renders correct state. Accepts a flags array for partial flushes (e.g., `'template_overrides' => false` for a "purge caches but keep my Site Editor edits" semantic).
- **All theme-file-change triggers now use the unified helper.** Three call sites previously ran *subsets* of the necessary clears: (1) `upgrader_process_complete` only cleared DB overrides, leaving Breeze stale; (2) the Version-compare check on `admin_init` cleared object cache + overrides but not Breeze; (3) the new mtime check (v7.0.0) only cleared overrides. All three now call `sn_purge_all_caches()`. The "/notes still showing one card" symptom after v7.0.0 deploy resolves because the upgrader hook now flushes Breeze synchronously during the install.
- **Admin "Purge All Caches" and "Full Reset" buttons** now thin wrappers over the unified helper. "Purge All Caches" passes `template_overrides => false` so it doesn't nuke admin Site Editor edits; "Full Reset" lets the helper run with all defaults including overrides. Behavior identical for users; less duplicated code.

### Changed
- **Admin maintenance page split into four tabs.** Dashboard (status + actions only), Cloudflare (token + zone + status + manual purge), Reading Time (legacy cleanup tool), Links (existing). Each subsystem gets its own dedicated action hook (`sn_admin_cloudflare_tab`, `sn_admin_reading_time_tab`) so the module that owns the logic also owns the UI, colocated. The legacy `sn_admin_dashboard_extras` hook still fires on the Dashboard tab for backward compatibility with any third-party additions.
- **Removed redundant section headings** from the Cloudflare and Reading Time tab bodies — the tab name in the nav serves as the section label, so internal `<h2>Cloudflare</h2>` was just visual noise.

### Added
- **Admin bar quick-action dropdown** (new module [inc/admin-bar.php](inc/admin-bar.php)). Top-bar "S&N" menu with one-click access to the maintenance actions that previously required navigating to the Signal & Noise dashboard: *Purge All Caches*, *Clear DB Overrides*, *Purge Cloudflare* (only shown when configured), *Check for Updates*, plus a link back to the full dashboard. Available from any admin page AND from the front-end (when the admin bar is shown). Each action runs over admin-ajax with a per-action nonce, with a toast notification confirming success/failure — no page navigation, no scroll loss, no form-state loss. Capability gate on `manage_options` (server-side double-check); items aren't rendered for users without that capability. JS uses `textContent` (not `innerHTML`) when manipulating link labels so a future server-side bug in the response can't escalate to XSS.

- **Template file self-heal** (new module [inc/template-self-heal.php](inc/template-self-heal.php)). Safety net against the failure mode where the WP self-updater extracts a new theme zip but silently misses some files (a Cloudways file lock, a permission issue on a specific path, etc.). On 2026-05-07 this exact failure pattern left `templates/page-notes.html` stuck at the pre-cbe3ee5 single-pillar-card content for hours despite multiple successful theme updates — every other theme file updated cleanly, but the rendering of `/notes` kept showing OLD content because the file on disk didn't match what was in `main` HEAD on GitHub. Diagnosis was slow because the failure was silent: no error logged anywhere, no cache layer involved (`cf-cache-status: DYNAMIC`, no Breeze x-cache header), PHP was actively rendering from a stale file on disk. The module makes this class of failure recoverable without SSH/SFTP intervention.

  **How it works:** on `admin_init` (rate-limited to 5-min intervals via an option), the module iterates a whitelist of theme files (default: every `.html` under `templates/` and `parts/`, filterable via `sn_self_heal_files`). For each file, it fetches the canonical version from GitHub via the Contents API using the existing `SN_GITHUB_TOKEN` (with `Accept: application/vnd.github.v3.raw` so GitHub returns raw file bytes rather than the base64-in-JSON wrapper). Byte-for-byte comparison against the local file. On drift, the module overwrites the local file using `WP_Filesystem` — the same write API the WP self-updater itself uses, so anything WP can write, this can write. After any successful write, fires `sn_purge_all_caches()` so the new content is served immediately rather than waiting for the next deploy-time cache invalidation.

  **Defensive properties:** rate-limited (one set of GitHub calls per 5 min, well under API rate limits); per-file failure counter that backs off for 1 hour after 3 consecutive write failures (so a permission-locked file doesn't retry-storm); whitelist-based scope (won't ever touch random theme files); `manage_options` capability gate plus `admin_init`-only (never runs on front-end); graceful degradation if `SN_GITHUB_TOKEN` isn't set or GitHub is unreachable. Admin notices on every check that performed a write or encountered a failure, so the module's activity is visible — successes show *"updated N theme file(s) from GitHub"* in green; failures show the failed paths with retry-cooldown info in red.

  **Why this isn't overreach:** the module only re-syncs files to match what's already in `main` — it doesn't create files, doesn't modify config, and the canonical source is the same Git repository the WP self-updater is already pulling from. The net effect is "deploys are now self-correcting if they silently skip a file". Future-proofing against an unsolved class of failure with a small, scoped, opt-out-able mechanism (filter the whitelist down to `[]` to disable).

## [7.0.0] — 2026-05-07

**Post-incident hardening + new capabilities.** Marks the architectural shift to *"decorative work never blocks essential rendering"* after a `/notes` outage on 2026-05-07 was traced to a UTF-8 truncation loop in the OG card generator that pinned PHP-FPM workers at 100% CPU. The fix to that specific bug is necessary but not sufficient — the deeper change is structural: lazy-on-request synchronous OG generation is gone, replaced with proactive backfill in admin contexts; CI smoke tests catch regressions before users notice; Cloudflare HTML caching with auto-purge reduces origin load and improves global TTFB; mtime-based template-override clear self-heals on every deploy regardless of `Version:` bump policy.

Plus the second long-form companion essay landed at `/provenance/as-substrate/`, both pillar essays now surface directly on `/notes`, and assorted bug fixes (eyebrow drift, byline date `displayType` bug, pillar Card 2 longform link, render_block filter for slug-attributed shortcode in templates).

**Why a major bump.** Per `CLAUDE.md`: minor cap is `.5` per major; we were at `6.5`. The accumulated user-visible capabilities (Cloudflare admin UI, CI/monitoring infrastructure, smoke test workflow, two pillar essays surfaced on `/notes`) plus the architectural shift in defensive posture make a coherent v7.0.0 milestone. No public API was removed or renamed — this isn't a SemVer-MAJOR-by-breakage; it's the project's own minor-cap rule rolling at the natural milestone.

### Highlights

- **`/notes` hang root-caused, fixed, and architecturally hardened.** UTF-8 byte-vs-character bug in `sn_og_wrap_lines()` truncation loop fixed with `mb_substr` + `$guard` ceiling. OG card generation is now non-blocking on the request path; cards generate proactively via `wp_after_insert_post` and one-time backfill, never lazily on cache-miss. (`e006841`, `3645cc3`)
- **CI smoke test workflow** at [.github/workflows/smoke-test.yml](.github/workflows/smoke-test.yml). PHP lint + 6-route live check on every push and 15-min schedule. Catches regressions within 15 seconds. (`38cc5b0`)
- **Cloudflare HTML caching support.** New [inc/cloudflare-purge.php](inc/cloudflare-purge.php) module: configurable token + zone (constants or admin UI), auto-purge on post save and theme update, manual purge button, last-purge timestamp display. New [docs/CACHING.md](docs/CACHING.md) with full Cache Rule setup. (`0e9518a`)
- **Two pillar essays surfaced on `/notes`.** Cards link to on-site long-forms (not SSRN); read-times pulled dynamically via `[sn_reading_time slug="..."]` so figures stay in sync with the cached value on each long-form post. (`cbe3ee5`)
- **`/provenance/as-substrate/` long-form** — companion to SSRN paper 2 ("Provenance as Substrate: A Cryptographic Identifier Framework for Music Rights and Royalty Infrastructure", Abstract 6730343). Six anchored sections, paired SVG analogy diagram (envelopes-with-tags ↔ file-with-fingerprint), cost-scaling SVG in Section 6. (`73082e6` + `b841daf` + `28a0cde` + `2ca4d1c`)
- **Self-healing template-override clear.** mtime-based detection in [inc/template-maintenance.php](inc/template-maintenance.php) closes the gap where template-only deploys (no `Version:` bump) didn't trigger `wp_template` DB override clears, leading to silent stale-template rendering on `/notes`. Now self-heals on every admin pageview after any `templates/*.html` or `parts/*.html` change. (`0e9518a`)
- **Pillar card read-times made dynamic** via new `slug` attribute on `[sn_reading_time]`. Single source of truth across `/provenance`, `/notes`, and each long-form's byline. (`949007e`, `cbe3ee5`)
- **Operational documentation.** New [docs/MONITORING.md](docs/MONITORING.md) covers all four monitoring tiers (architectural, CI smoke, Uptime Kuma, future) with copy-pasteable Uptime Kuma monitor config and incident-response checklist that routes through the `superpowers:systematic-debugging` skill.

### Original detailed entries follow

The original commit-by-commit entries are preserved below in the order they were written during the 2026-05-07 session. They remain useful as audit trail and cross-reference for the migration option flags introduced.

---

Ship the second long-form companion essay at `/provenance/as-substrate/` — the web-adapted, jargon-free version of SSRN paper 2 ("Provenance as Substrate: A Cryptographic Identifier Framework for Music Rights and Royalty Infrastructure", Abstract 6730343). Mirrors `/provenance/over-detection/` block-for-block: same hero/eyebrow/TOC/section/byline structure, same diagram block treatment, same footer CTA pair, same dynamic byline + reading-time block. Six anchored sections (`#setup`, `#analogy`, `#what-it-is`, `#why-it-matters`, `#the-shift`, `#economics`) match the first long-form's pattern.

### Added
- **New seed file** at [inc/seed-content/as-substrate-body.html](inc/seed-content/as-substrate-body.html). Hero with `[sn_reading_time]` shortcode in the eyebrow (single source of truth — no manual minute counts), six properly-wrapped `<section class="sn-provenance-section">` groups, paired SVG diagrams in Section 2 (administrative-codes envelopes-with-drifting-tags ↔ cryptographic-identifiers file-with-fingerprint, both 240×180 viewBox, line-art aesthetic, `sn-provenance-svg-accent` for blood-color fills on circles/lines per the existing CSS contract), a single-panel cost-scaling SVG in Section 6 (two-axis line chart: administrative cost rising linearly versus cryptographic cost staying flat — flat line uses the accent class for blood-color emphasis on the punchline; grid is inline-overridden to `1fr` with `max-width:340px;margin:0 auto` so the existing paired-grid CSS still drives layout for both diagrams), footer CTA row, byline with `displayType:"modified"` post-date + `[sn_reading_time]`.
- **`sn_ensure_as_substrate_page()`** in [inc/notes-and-provenance.php](inc/notes-and-provenance.php). Parallel to `sn_ensure_over_detection_page()` — creates the new child page under `/provenance` with `post_parent` set, `page_template` = `page-provenance`, post excerpt populated for the meta description, idempotent on re-run.
- **`sn_load_as_substrate_body()`** loader for the new seed file. Same fallback semantics as the existing `sn_load_over_detection_body()` — empty string if the file is missing, so the template renders an empty post-content area instead of fatalling.
- **`sn_migrate_as_substrate_seed()`** + `SN_AS_SUBSTRATE_SEED_OPT` flag. One-time migration on `admin_init` for installs whose `SN_SEED_FLAG_OPTION` was already set before this page existed (i.e. every production site since v6.0). The main `sn_seed_content_surfaces()` flow short-circuits on those installs, so the new ensure-call needs its own gate. Idempotent: bails if the dedicated flag is set; bails (without flagging) if the parent page doesn't yet exist so the next admin_init can complete it after the parent lands.
- **`SN_AS_SUBSTRATE_SLUG` constant** alongside the existing slug constants for consistency with the established naming convention.
- **Pillar-page Card 2 wired up to the long-form.** `sn_provenance_papers_index_markup()` updated so Card 2 mirrors Card 1's full pattern: `MAY 2026 · 5 MIN READ` in the meta line (hardcoded to match Card 1's existing precedent — the long-form is evergreen so this won't drift), and a discreet `Read the long-form on this site →` affordance pointing at `/provenance/as-substrate/`. Visual asymmetry between the two cards is now resolved — both have on-site equivalents, both surface the affordance.
- **`sn_migrate_provenance_card2_longform()`** + `SN_PROV_CARD2_LF_MIGR_OPT` flag. One-time migration that rewrites the live pillar page's body via `sn_provenance_papers_index_markup()` so existing installs (where v6.5.4's `SN_PROV_SPLIT_MIGR_OPT` was already set) pick up the new Card 2 markup. Defensive: gates on the SSRN abstract_id 6730343 anchor — if the live body doesn't contain that marker, admin has hand-edited away from the seed shape, so the migration bails *without* setting the flag (allowing a future run to complete after manual recovery, matching the pattern in `sn_migrate_provenance_split()`). Self-idempotent: if the body already contains the `/provenance/as-substrate/` URL, sets the flag and exits.
- **`[sn_reading_time]` shortcode now accepts an optional `slug` attribute** in [inc/reading-time.php](inc/reading-time.php). No-args form keeps the legacy current-post behaviour unchanged; `[sn_reading_time slug="path/to/page"]` resolves a different post via `get_page_by_path()` and reports its cached reading time. The render_block bridge filter at [reading-time.php:148](inc/reading-time.php:148) was loosened from exact-match `[sn_reading_time]` to prefix-match `[sn_reading_time` so both forms route through `do_shortcode()`. Returns empty string if the slug-targeted post doesn't exist (graceful during the migration window).
- **Both pillar cards now use the dynamic shortcode** for read-time meta. `sn_provenance_papers_index_markup()` Card 1 reads `March 2026 · [sn_reading_time slug="provenance/over-detection"]` and Card 2 reads `May 2026 · [sn_reading_time slug="provenance/as-substrate"]`. Eliminates the hardcoded-vs-byline drift the live site exhibited (pillar Card 1 said "4 min read" while the over-detection byline said "5 min read" because the byline was dynamic and the card was a hand-typed estimate from before edits).
- **`sn_migrate_provenance_card_readtimes_dynamic()`** + `SN_PROV_RT_DYNAMIC_OPT` flag. New one-time migration that rewrites the live pillar body so existing installs pick up the dynamic shortcode form for both cards. Same defensive pattern as the prior pillar migrations: gates on the SSRN abstract_id 6730343 anchor; self-idempotent on the `[sn_reading_time slug=` marker; needed because `SN_PROV_CARD2_LF_MIGR_OPT` from the previous push has already flagged the older migration complete on production.

### Fixed
- **As-substrate byline date was rendering empty.** The seeded byline used `displayType:"modified"` on the `wp:post-date` block (mirrored from over-detection), but WordPress core's `render_block_core_post_date()` returns null when `displayType === 'modified'` *and* `post_modified === post_date`. Newly-inserted posts have those equal, and as-substrate is evergreen by maintainer convention — it never gets edited — so the byline date stayed permanently empty. Both the seed file and a new migration (`sn_migrate_as_substrate_post_date_displaytype()` + `SN_AS_DATE_DISPLAYTYPE_OPT`) drop the `displayType` attribute, defaulting the block to publish-date display (always renders). Defensive str_replace: bails *without* flagging if the precise attribute pattern doesn't match (admin has touched the post-date block separately).
- **Over-detection eyebrow drift.** Live page still showed `A short read · 4 min` (hardcoded since v6.5.3) while the byline shortcode computed `5 min read` — within-page mismatch the user pointed out. v6.5.4's seed already simplified the eyebrow to `A short read` only, but the live page wasn't migrated. New migration `sn_migrate_over_detection_eyebrow_dynamic()` + `SN_OD_EYEBROW_DYN_OPT` does a precise regex swap: `A short read · N min[ read]` becomes `A short read · [sn_reading_time]`, matching the as-substrate seed shape and ensuring eyebrow + byline always agree. Defensive on the pattern match — bails without flagging if admin has already customised the eyebrow.
- **`/notes` hang.** Reverted the `render_block` filter's prefix-match variant introduced in 949007e back to the original exact-match `[sn_reading_time]`. The prefix-match (`[sn_reading_time` without the closing bracket) was a misdiagnosis on my part — the actual root cause turned out to be in [inc/og-image.php](inc/og-image.php) (see next entry). The revert is still appropriate (the prefix-match wasn't necessary; slug-attributed shortcodes inside post_content resolve via WordPress core's `the_content` filter chain at priority 11), but it didn't fix `/notes`. Documented the diagnostic mistake in the next entry's "lessons" note so the next iteration doesn't repeat it.

- **Real `/notes` hang root cause: UTF-8 corruption infinite loop in `sn_og_wrap_lines()` ([inc/og-image.php:213-220](inc/og-image.php:213)).** Symptom: `/notes/{slug}/` and `/notes/` server-side hang for 60+ seconds with zero response bytes; only specific posts affected; REST API and RSS feed (`/notes/feed/`) work normally; other pages render fine. Diagnosis trail (recorded so future me can replay it): (1) `/notes/feed/` works in 0.3s — proves the data layer and post query are fine; (2) the WP REST API renders all post content correctly — proves `the_content` filter chain works; (3) testing each individual note URL revealed that exactly two posts hang and three render normally; (4) checking `/wp-content/uploads/sn-og/post-{id}.png` for each post showed the **two hanging posts had no cached OG card (404), the three working posts had cached cards (200)**; (5) reading [og-image.php:74-103](inc/og-image.php:74) showed `sn_og_image_url_for_post()` synchronously calls `sn_generate_og_card()` when the cache is missing, via `wpseo_opengraph_image` and `sn_og_image_url` filters that fire on every page render through `wp_head`; (6) reading [og-image.php:191-231](inc/og-image.php:191) showed the truncation loop in `sn_og_wrap_lines()` used `substr($rest, 0, -1)` (byte-based) on a `$rest` value that already ended with `…` (UTF-8 0xE2 0x80 0xA6, 3 bytes — and `wp_trim_words(..., 36, '…')` at line 163 always appends one). Each iteration stripped only the last byte (0xA6), leaving 0xE2 0x80 dangling; `rtrim($rest, ".,;:!? \t")` couldn't remove those bytes (not in its strip set); a fresh `…` was appended; net effect was the string grew by 2 bytes per iteration, the loop never terminated, and PHP execution-time-limit (or memory) eventually killed the worker after the user's HTTP timeout. **Fix:** track `$core` (without ellipsis) separately, shrink with `mb_substr( $core, 0, -1, 'UTF-8' )` (character-aware), and reconstruct `$rest = $core . '…'` each iteration so no encoding state carries between iterations. Added defensive `$guard < 1000` ceiling — for any reasonable text the loop terminates in <300 iterations, but the guard ensures even unforeseen edge cases can't repeat the hang.

- **Why posts split into "hangs" vs "works".** Posts whose excerpt fits in `$max_lines` (3) without overflow never enter the truncation loop — that's the working group. Posts where the excerpt exceeds 3 lines hit the loop, and on iteration 2+ corrupted the UTF-8 — that's the hanging group. The bug had been latent in the codebase since [og-image.php was added in v6.3.2](CHANGELOG.md) (Mar 2026); it surfaced now because the user's recent posts have longer prose that triggers truncation. **Lesson for next iteration:** when a hang appears post-shortcode-change, check whether the change actually touches the hanging code path before reverting things. The shortcode prefix-match (949007e) was on the wrong code path; the OG generator's truncation loop has nothing to do with shortcode rendering. Reading the actual symptoms (per-post asymmetry, missing OG cards) earlier would have pointed at `og-image.php` directly.

### Architectural — incident hardening

After the `/notes` hang incident, the actual fix to the truncation bug is necessary but not sufficient. The **structural** issue that made one bug into a site-down event was that OG card generation ran synchronously inside `wp_head` on every cache-miss page render, with no time budget and no failure isolation. One bad post pinned a PHP-FPM worker at 100% CPU; subsequent visits trapped more workers; eventually the whole pool was exhausted. This change encodes a non-blocking contract on the request path so the same class of failure can't recur even if a future bug breaks the generator.

- **`sn_og_image_url_for_post()` is now non-blocking on cache miss.** The synchronous `sn_generate_og_card()` call inside the function is removed. Cache miss returns `null`, and existing callers (the `sn_og_image_url`, `wpseo_opengraph_image`, and `wpseo_twitter_image` filter wrappers) already fall back to the site default OG image when null is returned. A function-header comment now documents the non-blocking contract — *"never run unbounded synchronous work in the request path for content that has a safe default"* — so future iterations don't reintroduce the lazy-sync path. OG cards are decorative; the site logo card is a perfectly serviceable fallback for any post that doesn't have a cached card yet.
- **`sn_migrate_backfill_og_cards()`** + `SN_OG_BACKFILL_OPT` flag. One-time `admin_init` (priority 5) migration that scans every published post and page and generates any missing OG cards. Replaces the lazy-on-request path with a proactive backfill that runs in the admin context (where slowness is acceptable). After this runs, the wp_after_insert_post hook handles cards for new content, and the request path never has a reason to attempt generation. Each generation is independent and best-effort — a failure on one post doesn't abort the rest.
- **Net effect on the architecture.** Three independent paths now create OG cards, none of them on the request path: (1) `wp_after_insert_post` for new content (admin save context), (2) `sn_migrate_backfill_og_cards` for pre-existing content (one-time admin migration), (3) explicit re-saves for content edits. The front-end render path only *reads* cached cards. Even if the generator hits a future bug, no front-end request can hang on it. If a card is missing for any reason, the page falls back to the site default OG image — a graceful degradation, not a hang.

### Operational — Tier 2 hardening (shipped)

After confirming the architectural fix held, layered the rest of the
post-incident defenses so the next regression has multiple chances
to be caught before it reaches a user-visible failure.

- **GitHub Actions smoke test workflow** at [.github/workflows/smoke-test.yml](.github/workflows/smoke-test.yml). Two jobs: (1) `lint` runs `php -l` on every `.php` file in the repo on every push — catches parse errors before they can deploy. (2) `smoke` runs against the live site, hitting six key routes (`/`, `/notes/`, `/provenance/`, `/provenance/over-detection/`, `/provenance/as-substrate/`, `/notes/feed/`) and asserting per-route HTTP 200, response time under 5 s, body over 1 KB, and the presence of an expected content marker (e.g., `On Provenance` for the pillar). Marker checks defeat false-positive 200s from cached error pages or empty shells. Triggers on `push: main`, `schedule: */15 * * * *`, and `workflow_dispatch`. The 15-minute schedule bounds detection latency for issues that emerge between pushes — content edits, plugin/WP updates, server-side drift. Failure surfaces as a red ❌ on the commit, an email to the committer, and annotated `::error::` lines in the Actions UI.

- **Workflow security:** the `run:` blocks consume only hardcoded URLs and never interpolate `github.event.*` fields (commit messages, PR titles, branch names) into shell, so there is no command-injection surface. Documented as a top-of-file comment so future edits don't introduce one.

- **Documented monitoring playbook** at [docs/MONITORING.md](docs/MONITORING.md). Covers the four tiers (architectural, smoke tests, Uptime Kuma, future), step-by-step Uptime Kuma monitor setup with copy-pasteable URL/keyword/interval table for the six routes, notification routing recommendations, and an incident response checklist that points back to the `superpowers:systematic-debugging` skill so the next incident gets diagnosed before being patched.

- **Uptime Kuma monitors** are documented in `docs/MONITORING.md` for the user to add via the UK web UI on the existing Railway instance. UK doesn't have a public API for programmatic monitor creation, so this step requires manual UI entry — but the guide has the table ready to paste.

### Operational — Tier 3 (still not in this commit)

Flagged for future iterations:

- **Local PHP runtime** (or `wp-env`) so PHP changes can be exercised end-to-end before pushing. The structural gap that allowed the byte-vs-char UTF-8 bug to ship.
- **Production error log access** — Cloudways → Loggly/BetterStack forward, or just SSH access to the WP debug.log. Would have shown the truncation loop firing repeatedly before user-visible impact.
- **Local pre-commit hook** running `php -l` on staged files. Belt-and-suspenders alongside the CI lint, but only useful once local PHP is set up.

### Content

- **Both pillar essays now surface directly on `/notes`.** [templates/page-notes.html](templates/page-notes.html) replaces the prior single "Provenance Over Detection" pillar card with a stacked pair covering both long-forms. Each card uses the existing `sn-pillar-card` brutalist treatment (asphalt background, concrete border) so the visual vocabulary stays consistent. CTAs link to the **on-site long-forms** (`/provenance/over-detection/` and `/provenance/as-substrate/`), not to SSRN — the long-forms are the canonical reading experience on this site, and SSRN is reachable from each long-form's own CTA. Subtitles compressed from the academic SSRN versions to claim-style one-liners: *"Detection chases what isn't. Provenance proves what is."* and *"Music files need fingerprints, not name tags."* Eyebrows use the dynamic `[sn_reading_time slug="..."]` shortcode so the read-time figure stays in sync with the cached value on each long-form's post — single source of truth across `/provenance`, `/notes`, and the byline of each essay.

- **`render_block` filter in [inc/reading-time.php](inc/reading-time.php) extended** to handle the slug-attributed shortcode form (`[sn_reading_time slug="..."]`) in addition to the no-args form. Two specific `strpos` checks rather than a prefix-match — catches both forms but doesn't false-positive on lookalikes like `[sn_reading_timex]`. This is the targeted version of what 949007e tried with the loose prefix-match. Why both forms need this hook: post_content shortcodes resolve via WP core's `the_content` filter chain, but template files (page-notes.html) aren't post_content and don't get `the_content`, so any shortcode in template markup needs this render_block bridge. The OG-truncation root-cause investigation (e006841) confirmed this filter was unrelated to the `/notes` hang; the targeted form here is correct by design.

### Fixed — `/notes` was still showing the old single pillar card after deploy

After cbe3ee5 deployed and the user clicked Update in WP admin, `/notes` continued to render the prior single-card layout despite the theme file changing. Diagnosis:

- `git show origin/main:templates/page-notes.html` confirmed the new two-card markup IS in the deployed branch.
- Cloudflare reported `cf-cache-status: DYNAMIC` and `x-cache: MISS` on `/notes` — origin response, no edge caching.
- `/provenance/` correctly served the new dynamic content. So PHP execution and the recent template-related changes were working, just not on `/notes`.
- Asset mtimes (`components.css?ver=…`) were recent, confirming the theme files were physically replaced on the server.

**Root cause:** a `wp_template` database override for the `page-notes` template, scoped to the `signal-and-noise` theme. WordPress 6.x's Site Editor (Appearance → Editor) creates these whenever an admin opens or edits a template; from that point on, WP serves the DB version and ignores the .html file in the theme directory — even across theme updates. This is intentional WP behavior to preserve admin customizations, but it's a silent footgun when a theme author iterates on the file expecting changes to take effect.

**Fix:** new one-time migration `sn_migrate_clear_notes_template_override()` + `SN_NOTES_TPL_OVERRIDE_CLEARED_OPT` flag in [inc/notes-and-provenance.php](inc/notes-and-provenance.php). Runs on `admin_init`, queries for any `wp_template` post with `post_name = 'page-notes'` AND `wp_theme` taxonomy term `signal-and-noise`, and force-deletes them. After deletion, WP falls back to the theme file, which carries the new two-card layout. Defensive: bails (and flags) if `wp_template` post type isn't registered (e.g., some WP setups without block-theme support active at this hook timing). Idempotent: runs at most once per install. Future admin edits via Site Editor would create new DB records, which this migration deliberately won't clear — admin customizations stay opt-in.

**Lesson:** when a theme file change deploys cleanly but the live page still shows old content AND Cloudflare confirms it's not edge-cached, suspect a WP-level template/block override. The signature is per-template asymmetry: some template-backed pages render new code (their templates haven't been overridden in DB) while specific routes don't.

### Self-healing: file-mtime-based template override clear

The structural reason the existing `sn_clear_template_overrides()` mechanism didn't fire on the `/notes` template change: it's gated on the style.css `Version:` header changing, but project policy reserves Version: bumps for code/functional changes (not template/content edits). So a template-only push went out without bumping Version, the version-compare check returned false, and overrides were never cleared.

**Fix:** added a parallel detection mechanism in [inc/template-maintenance.php](inc/template-maintenance.php) that tracks the most-recent mtime among `templates/*.html` and `parts/*.html` files. When the latest mtime advances past the cached value, `sn_clear_template_overrides()` fires and the cached value updates. Self-healing on every deploy that touches templates, regardless of Version bump policy. Cost: cheap glob + filemtime per admin_init (<1ms when no change). The original Version-compare logic is preserved unchanged so existing self-healing behavior on Version bumps still works — the two mechanisms are complementary.

### Cloudflare HTML caching + auto-purge

The existing CF default profile caches static assets only — CSS, JS, images. HTML responses returned `cf-cache-status: DYNAMIC` (every visitor request hit origin PHP). For a content-heavy site this leaves a lot of CDN performance and origin-load reduction on the table. New module enables HTML caching at the edge with event-driven invalidation.

- **`inc/cloudflare-purge.php`** — auto-purges CF edge cache on post saves and theme updates. Configurable via either `wp-config.php` constants (`SN_CLOUDFLARE_API_TOKEN`, `SN_CLOUDFLARE_ZONE_ID`) or via the admin UI card on the Signal & Noise dashboard. Constant takes precedence when both are set so `wp-config` can lock the value against accidental admin edits. All API calls are non-blocking (`'blocking' => false` on `wp_remote_post`) so a slow CF response never delays an admin save. Without configuration, all hooks no-op silently — fail-safe by default.

  Two automatic purge triggers: (1) `wp_after_insert_post` on `publish` status purges the post URL + homepage + `/notes/` + `/provenance/` + `/notes/feed/` + parent permalink if any (filterable via `sn_cf_purge_urls_for_post`); (2) `upgrader_process_complete` on theme updates purges the entire zone (theme updates can change global elements). Plus a manual "Purge Cloudflare" button on the admin dashboard for ad-hoc purges. Last-purge timestamp displayed in the admin UI for verification.

  Security: API token stored as a non-autoloaded option (loaded only when needed); admin UI obscures saved value (`••••` + last 4 chars); all admin POST actions nonce-protected.

- **`docs/CACHING.md`** — full dashboard-side setup guide. Covers the four caching layers (browser, CF edge, Varnish, WP object cache), why CF doesn't cache HTML by default, two configuration paths (Cloudflare APO at $5/mo for the simplest setup, OR free Cache Rule + this theme's purge module), step-by-step Cache Rule expression with cookie bypass for logged-in users, API token generation, verification curl commands, and a troubleshooting section. The Cache Rule expression specifically excludes `/wp-admin/`, `/wp-login.php`, `/wp-cron.php`, `/wp-json/`, `/feed/`, and any request carrying a `wordpress_logged_in_*` / `wp-postpass_*` / `comment_author_*` cookie — so admin views and feeds always hit origin.

### Notes
- **No theme version bump.** Per maintainer directive and the project's "Only bump version for code/functional changes. Never for content-only template edits" rule. The PHP scaffolding here is wiring for a content asset — the substantive change is the new prose. Cache-busting still works (mtime-driven, v6.5.4).
- **The pillar-page index card for this paper already exists** as Card 2 in `sn_provenance_papers_index_markup()` (shipped in v6.5.4). That card currently has no `Read the long-form on this site →` affordance because the long-form didn't exist yet; updating the pillar to link to this page is a separate task that runs after this page is live (per the original task's scope boundary).
- **Eyebrow reading-time uses the shortcode**, not a hardcoded value. This avoids the drift the live `/provenance/over-detection/` had (eyebrow said "4 min" while byline said "5 min" before v6.5.4 simplified it). The eyebrow renders as "A short read · X min read" — slightly more verbose than the prior hand-typed pattern but always accurate.
- **SVGs are new** — drawn from scratch in the same line-art idiom as the detection/provenance pair (currentColor strokes at width 2, accent group for blood-color circle/line fills, white-stroke check sigil overlaid on the seal). Captions: "Administrative codes / Assigned by clerks" ↔ "Cryptographic identifiers / Born with the file" for the Section 2 pair; "Cost per track / Linear vs constant scaling" for the Section 6 chart.
- **Section 6 carries one diagram, Section 2 carries two.** A deliberate divergence from the over-detection page (which is single-diagram in Section 2 only). The economics section's prose is the closing argument — a chart visualising "linear admin cost vs flat crypto cost" is the punchline made visible. The single-panel layout reuses the paired-grid wrapper with inline `grid-template-columns:1fr` and `max-width:340px` to centre it without a CSS file edit.
- **Section 6 wrapped properly.** The live `/provenance/over-detection/` Section 6 was added to the live page after seeding and ended up as loose paragraphs and an h2 without the `font-display` class or `sn-provenance-section` wrapper. The new seed restores the architectural pattern for all six sections so the typography and spacing stay consistent.
- **SEO meta inherits the existing fallback.** The `is_singular()` branch in [inc/seo.php](inc/seo.php) already produces `{post_title} — Juan Lentino` for og:title/twitter:title and `{post_excerpt}` for the description. Setting `post_title = "Provenance as Substrate"` and `post_excerpt = "A short read on why music files need fingerprints, not just name tags."` yields the user-spec'd `Provenance as Substrate — Juan Lentino` browser-tab title and matching social card description. OG image generates lazily through [inc/og-image.php](inc/og-image.php) on first request — no manual image needed.
- **Evergreen.** Per maintainer convention: this is a static dated piece. Once published it doesn't get edited.

### Deploy
After the next push to `main`, the `sn_migrate_as_substrate_seed()` migration runs on the next admin-side request and creates the page. URL becomes available at `/provenance/as-substrate/`. No cache purge required (mtime cache-busting + WP rewrite-rules flush from the seed flow). The pillar-page card linking to this URL is the next iteration's task.

## [6.5.5] — 2026-05-07

Add a consecutive-revision counter (`-rN`) to the updater's synthetic version label, so the iteration-between-milestones sequence reads as a clean count instead of just an opaque SHA. Resolves the "version bumping should be as consecutive as they are" tension introduced when v6.5.4 moved CSS cache-busting off the theme Version: header — cache-busting is now mtime-driven (frictionless), and the updater label now carries a readable consecutive marker for every commit between tags (so the audit trail isn't lost).

### Added
- **`sn_updater_revcount()`** in [inc/updater.php](inc/updater.php). Calls GitHub's compare API (`/compare/v{Version}...{branch}`) and returns the `ahead_by` count — i.e., the number of commits the tracked branch is ahead of the v{Version} tag. Cached 5 min alongside the existing branch-HEAD cache to keep API hits low. Returns 0 on any failure (missing tag, API error, rate-limited) so the synthetic label gracefully degrades to "no -rN suffix" rather than blocking the update.
- **`-rN` suffix in the synthetic update label.** New format: `{Version}{-rN}+{branch}.{sha7}`. Example: `6.5.5-r3+main.a1b2c3d` reads as "3rd commit on main since v6.5.5 was tagged, at SHA a1b2c3d". Counter resets to 0 each time the maintainer ships a milestone (bumps Version + tags).
- **Rev count surfaced in the admin notice.** Dashboard / Updates / Themes now show "Tracking branch `main` at `<sha>` (default) · `r3` commits since the last tag." so the iteration position is visible without waiting for an update offer.

### Notes
- **The compare API call is incremental**, not a separate HTTP round-trip per page load — it's cached behind a 5-min transient (same TTL as the branch-HEAD cache) and shares the manual-clear hook on `load-update-core.php`. Net cost: one extra cached API request per 5 min when there's an active iteration window.
- **What this resolves.** The v6.5.4 cache-busting refactor decoupled "fire on every file change" (mtime) from "fire on milestones" (Version: bumps). That left "audit trail of shipped iterations" without its own primitive — between v6.5.4 and v6.5.5 in the new model, the version history would read as sparse milestones with no per-commit counter. `-rN` fills that gap: every commit between tags has a unique consecutive identifier, milestone semver stays clean, and cache-busting stays frictionless.
- **Reading the version progression**: `6.5.5` (just-shipped milestone) → `6.5.5-r1+main.<sha>` (1 commit later) → `6.5.5-r2+main.<sha>` → … → `6.5.6` (next milestone) → `6.5.6-r1+main.<sha>` → … and so on.

### Deploy
After the WP updater shows v6.5.5 available, click Update. The new `-rN` label takes effect for any commit pushed to main *after* this install (since the rev counter is computed by the new updater code). Subsequent commits will appear in the WP UI as `6.5.5-r1+main.<sha>`, `6.5.5-r2+main.<sha>`, etc.

## [6.5.4] — 2026-05-07

Three things landing together: (1) restructure `/provenance` into a two-paper index with the long-form essay moved to its own child URL, (2) overhaul iteration UX — mtime-based asset cache-busting + simpler updater that always tracks `main`, no dev branch dance, and (3) a design pass on the index after the v6.5.3-shipped first cut rendered as a wall of red shouting (titles inheriting theme.json's global link colour, mid-word "DISTRIB/UTION" wraps, no title hierarchy).

### Added
- **Two-paper "On Provenance" index** at `/provenance`. New seed in [inc/seed-content/provenance-body.html](inc/seed-content/provenance-body.html): heading + framing intro paragraph + two-entry typographic list. Each entry has a meta line (date + on-site read time on Card 1 only), a short primary title linked to SSRN, an academic-full-form subtitle in DM Mono rust sentence-case, a ~50-word distilled blurb, and (Card 1 only) a discreet "Read the long-form on this site →" affordance pointing at the child page. Index closes with a "Read more notes →" link below the cards.
- **Long-form essay child page at `/provenance/over-detection`.** New seed in [inc/seed-content/over-detection-body.html](inc/seed-content/over-detection-body.html) — lifted verbatim from the prior pillar body (hero, TOC, six anchored sections, SVG diagram, footer CTA, byline). Reuses `page-provenance.html` so the prose inherits the existing essay treatment.
- **`sn_ensure_over_detection_page()`** in [inc/notes-and-provenance.php](inc/notes-and-provenance.php) creates the child page on fresh installs with `post_parent` set to `/provenance`, yielding the `/provenance/over-detection` URL via WordPress's hierarchical-pages routing.
- **`sn_migrate_provenance_split()`** — one-time live-page migration. Splits the existing `/provenance` body using the essay's `sn-provenance-hero` className as a stable anchor: everything from that anchor onward becomes the child page's body (lifted verbatim — no editorial change), and the parent body is replaced with the cards-only index. Idempotent: bails *without* setting its done-flag if the anchor is missing (so a future run after manual recovery still completes the split). Gated by the new `SN_PROV_SPLIT_MIGR_OPT` option.
- **CSS for `.sn-prov-papers` + `.sn-prov-paper-card`** in [assets/css/components.css](assets/css/components.css). Treatment matches the existing `.sn-notes-list` pattern: hairline divider between entries, no fill, no border-all-around, no shadow. Subtitle styles, defensive specificity on title and longform-link colors (so they win cleanly over theme.json's global link rule), `hyphens: none; word-break: normal; overflow-wrap: normal` on `.sn-prov-paper-title` to stop WP-core's default `break-word` from producing mid-word wraps on long academic titles. Mobile stack rule in [assets/css/responsive.css](assets/css/responsive.css) at the theme's tablet breakpoint (≤781px).
- **`sn_asset_ver()` helper** in [inc/assets-frontend.php](inc/assets-frontend.php). Computes `?ver=` from each enqueued file's `filemtime()` instead of the theme Version header. CSS/JS changes auto-bust browser, Cloudflare, and Breeze caches the moment a file changes on disk — no theme Version: bump required for visual tweaks. Falls back to the theme Version if `filemtime()` fails so we never emit a versionless URL. Applied to all five modular stylesheets and the sticky-header script.

### Changed
- **`/provenance` no longer hosts the long-form essay.** It's now a lean two-paper index, ~1 viewport on desktop. Essay text unchanged — just on its own URL now. Page title updates from "Provenance Over Detection" to "On Provenance" to reflect the new role.
- **Title hierarchy redesigned.** Each card now shows a short primary title (Bebas Neue 1.5rem black uppercase — "Provenance Over Detection" / "Provenance as Substrate") with the academic full-form below as a small DM Mono rust sentence-case subtitle. Replaces the previous single-heading-with-everything that produced 14-word red shouting matches.
- **`/provenance/over-detection/` no longer shows reading time twice.** The eyebrow's hardcoded "A short read · 4 min" had no way to stay synced with the dynamic `[sn_reading_time]` in the byline. Eyebrow simplified to "A short read"; the byline `[sn_reading_time]` shortcode is the single source of truth.
- **GitHub self-updater simplified.** [inc/updater.php](inc/updater.php) now always SHA-tracks `main` directly. The auto-detect-dev-branch logic, the release-tag fallback, and the `sn_github_dev_exists` / `sn_github_release` transients are gone. New helper `sn_updater_branch()` resolves once — defaults to `main`, overridable via the existing `SN_GITHUB_BRANCH` constant for tests/staging. Push to main → updater offers the new SHA on next poll. Tagged releases remain useful for changelog correlation and the GitHub Releases UI but no longer drive the update mechanism. Net effect: no dev/main branch dance, no version-bump-for-CSS-changes, no manual cache purges (between mtime cache-busting + Breeze auto-flush on theme update).
- **Removed the per-card `Read on SSRN →` CTA and the `SSRN Abstract NNNN` line from the meta.** The title link does the routing to SSRN; meta line is just date + read time. Less chrome, more typography.

### Notes
- **The split migration is non-editorial.** It moves prose between pages rather than editing it. If an admin had hand-edited the essay before this update, those edits are preserved as-is on the new child page.
- **Visual asymmetry between cards is intentional.** Card 1 has the long-form link + read-time meta because the essay lives on this site. Card 2 doesn't because there's no on-site equivalent. The asymmetry is honest signal — it tells the reader before they click that Paper 1 has a 4-min local read and Paper 2 doesn't.
- **Last manual version bump for CSS-only iteration.** From 6.5.5 onward, CSS/JS changes will cache-bust automatically via mtime. Theme Version: bumps are reserved for milestones the maintainer wants to mark.
- **Lessons from the v6.5.3 ship.** The first cut of this work shipped to dev and rendered with bugs the maintainer reasonably described as "this sucks": titles red because Cloudflare/Breeze were still serving the old `components.css?ver=6.5.3` (no version bump = no cache key change), mid-word title wraps because WP-core's `break-word` default kicked in on long Bebas Neue uppercase titles, and reading time appearing twice on the child page. All three are addressed structurally here so they can't recur the same way: mtime cache-busting kills the version-bump dependency for CSS work, defensive `:where`-beating specificity locks down our title colour, `hyphens: none` removes the wrap risk, and the eyebrow's hardcoded reading time is gone. Should have invoked the design-review skill *before* the first push, not after the user reported the regression — flagged here so the next iteration starts there.

### Deploy
After the WP updater shows v6.5.4 available (Dashboard → Updates), click Update. Caches will self-clear thanks to mtime-based cache-busting + the existing v6.5.0 theme-version-mismatch auto-flush. If anything still looks stale after a hard refresh, manually purge Breeze + Cloudflare once — but that should be the last time this dance is required for a non-milestone iteration.

## [6.5.3] — 2026-05-05

First release shipped via the dev-mode workflow proven by the v6.5.2 sanity check. Two iteration commits (`2718eb4` nav-underline tweak + `7f1ac3e` two updater fixes) were squashed into this single ship commit. The dev branch is deleted as part of this release; the auto-detect logic falls back to release-tag mode for the next user poll.

### Fixed
- **Updater `upgrader_process_complete` hook now handles auto-detect mode** in addition to the explicit `SN_GITHUB_BRANCH` constant case. Previously the hook early-returned if the constant wasn't defined, meaning in auto-detect mode the local branch SHA was never stored after install — every subsequent poll re-offered the same commit as a "new update", an infinite loop. The hook in [inc/updater.php](inc/updater.php) now resolves the active branch from constant OR auto-detect transient, fetches the branch HEAD live (instead of trusting a possibly-stale 5-min cache), and stores the SHA in `sn_github_local_sha` after a successful theme upgrade. Also accepts both `themes` (bulk) and `theme` (single) hook payload shapes for robustness.
- **`load-update-core.php` cache-bust hook now also flushes WP's own `update_themes` site transient.** Without this, WP serves frozen update info from its standard cache and never re-runs `pre_set_site_transient_update_themes`, so the displayed dev branch SHA stays stale even after the theme's custom transients are cleared. Adding `delete_site_transient( 'update_themes' )` forces a fresh poll on every Updates page load. (This is what was producing the "f5a884b" stale-SHA display during the sanity check despite my custom transients being cleared correctly.)

### Changed
- **Nav hover-underline thickness `1px` → `2px` in [assets/css/critical.css](assets/css/critical.css).** Aligns with [assets/css/layout.css](assets/css/layout.css), which was already at 2px. Eliminates a brief first-paint flash where the nav hover indicator was 1px before the deferred layout.css loaded and overrode it. At the v6.5.1 nav font size of 1.125rem, 2px reads better as an interactive affordance than the hairline 1px.

### Notes
- **Dev branch deleted at ship time.** The branch's existence on the remote is the auto-detect's signal; deleting it triggers fallback to release-tag mode. Next session, I'll create dev again from main and the cycle repeats — no `wp-config.php` edits, no admin UI clicks.
- **The sanity check served its purpose.** Two real bugs in v6.5.2's dev-mode plumbing surfaced only when actually exercised end-to-end. Both fixes ship in this release. Future iteration cycles won't loop or display stale SHAs.

### Deploy
After Update in WP admin (6.5.2 → 6.5.3), purge Breeze + Cloudflare, hard-refresh. The dev-mode banner on the Dashboard / Updates / Themes screens disappears once the dev branch is deleted (it's already gone as of this release) — that's the visual confirmation you've fallen back to release-tag mode.

## [6.5.2] — 2026-05-05

Make dev mode fully automatic — no `wp-config.php` constant needed.

### Changed
- **Updater auto-detects a `dev` branch on the remote.** [inc/updater.php](inc/updater.php) now polls GitHub once every 5 minutes (cached transient) for the existence of a `dev` branch. When `dev` exists, the updater silently switches to SHA-tracking on that branch — no `SN_GITHUB_BRANCH` constant required. When `dev` is deleted (after a merge to `main` at ship time), the next poll's 404 expires the cache, and the updater falls back to release-tag tracking. The `SN_GITHUB_BRANCH` constant still works as an explicit override (e.g., to track `staging` instead), but is no longer needed for the standard iterate-on-dev workflow.
- **Admin notice updated to label the mode.** Dashboard / Updates / Themes screens now show a notice that distinguishes "explicit override" (constant set) from "auto-detected" (constant absent, dev branch exists), so it's obvious why dev mode is active.
- **`load-update-core.php` cache-bust hook clears the dev-detection transient too**, so visiting the Updates page forces a fresh check of whether `dev` still exists. This makes ship-time transitions (delete dev → fall back to releases) feel instant rather than waiting 5 minutes for the cache to expire.

### Notes
- **The user's flow is now genuinely "talk to Claude → click Update":**
  1. I create a `dev` branch with the iteration commits.
  2. WP admin shows "Update available" within 5 min, OR immediately on visiting Dashboard → Updates.
  3. User clicks Update + purges cache.
  4. Repeat until satisfied.
  5. Ship: I squash-merge `dev` → `main`, bump version, tag, create one GitHub release, **delete the `dev` branch**.
  6. Next user poll detects no `dev`, falls back to release mode automatically. Site is on the new tagged version.
- **Backwards-compatible.** If the user kept the `define( 'SN_GITHUB_BRANCH', 'dev' );` from v6.5.1, it still works (explicit override path). It can be safely removed from `wp-config.php` after this update — but doesn't have to be.

### Deploy
This is the **last manual update** the user has to think about. After v6.5.2, future iterations land via dev-branch auto-detection — no constants, no settings, no UI. Update via WP admin (6.5.1 → 6.5.2), purge Breeze, purge Cloudflare, hard-refresh.

## [6.5.1] — 2026-05-05

Two changes bundled to demonstrate the discipline introduced by the second one — bump the nav size, AND add a dev-mode updater that lets future iteration sessions skip version bumps entirely.

### Added
- **Dev mode for the GitHub self-updater.** [inc/updater.php](inc/updater.php) now supports a new `SN_GITHUB_BRANCH` constant. When set in `wp-config.php` (e.g., `define( 'SN_GITHUB_BRANCH', 'dev' );`), the updater stops polling `/releases/latest` and instead polls `/commits/{branch}` every 5 minutes, comparing the branch's HEAD commit SHA against the SHA stored in the `sn_github_local_sha` WP option. When SHAs differ, WP shows "Update available" with a synthetic version label like `6.5.1+dev.a1b2c3d` (the SHA-vs-stored check is the real gate; the synthetic version is just for the admin UI). On successful upgrade, the new SHA is stored via `upgrader_process_complete`, so the next poll skips the same commit. Net effect: push commits to the `dev` branch freely, click Update in WP admin, no version bump, no GitHub release. Remove the constant when work is final and resume normal release-tracking.
  - Admin notice on Dashboard / Updates / Themes screens names the branch and current SHA so it's obvious when dev mode is on.
  - The `load-update-core.php` cache-bust hook clears the branch transient too, matching existing behaviour for the release transient.
  - Branch zipballs go through the same `upgrader_source_selection` folder-rename hook as release zipballs, so the extracted directory ends up at `signal-and-noise/` correctly.
  - Why this exists: the v6.4.0 → v6.5.0 hero-centring debug session burned 8 versions on a single feature, because every iteration needed a tag for WP to pick up the change. With dev mode, that same session would have shipped exactly one release at the end.

### Changed
- **Nav font-size 1rem → 1.125rem.** [parts/header.html](parts/header.html) bumps the nav typography fontSize attribute to compensate for Bebas Neue's condensed weight. At the previous 1rem, the nav read visually smaller than other 1rem-equivalent elements (buttons, body) because Bebas Neue's narrower letterforms reduce optical mass at the same nominal pixel size. 1.125rem (18px) restores parity without pushing the 8-item nav into wrap territory at 1200px+ viewports.

### Notes
- **Dev-mode workflow.**
  1. In `wp-config.php`: `define( 'SN_GITHUB_BRANCH', 'dev' );`
  2. Push commits to `dev` branch as work progresses. No version bump, no tag, no release.
  3. WP admin shows "Update available" within 5 minutes (or immediately if you visit Dashboard → Updates, which clears the cache).
  4. Click Update; the branch zipball replaces theme files; the SHA is stored.
  5. When work is final: merge `dev` → `main`, bump version once, tag, create one GitHub release. Remove the `SN_GITHUB_BRANCH` constant from `wp-config.php`.
- **Patch bump because both changes are additive and non-breaking.** Dev mode is opt-in (gated on the constant); without the constant, the updater behaves identically to v6.5.0. Nav size goes up by ~2px which is a visual refinement, not a behavioural change.

### Deploy
Update via WP admin → **Dashboard → Updates** (6.5.0 → 6.5.1), then **Breeze → Purge All Cache**, then **Cloudflare → Caching → Purge Everything**, then hard-refresh.

## [6.5.0] — 2026-05-05

Patch cap reached on 6.4 — minor bump. The work is small (one CSS rule) but the 6.4 lane is full at 6.4.7.

### Fixed
- **Home hero — close the dead band between H1 and subtitle.** The H1 was inheriting the UA stylesheet's `h1 { margin-block: 0.67em }` default, which scales with font-size — at the hero's `clamp(3rem, 9vw, 7rem)` font (= 112px on desktop) that's ~75px above and below the H1 block. Combined with the subtitle's own `margin-top: 1.5rem` (24px), the visible gap from H1 baseline to subtitle was reading as a 100px+ empty band where it should be ~24px. WP block-library normally resets heading margins, but in this case the reset doesn't reach `.sn-hero-title` because it's inline-styled with a custom font-size inside a constrained group, and the cascade lets the UA `h1` rule win on `margin-block`. Fix: explicit `margin-block: 0 !important` on `.sn-hero-title` in [assets/css/critical.css](assets/css/critical.css) and [assets/css/layout.css](assets/css/layout.css). Subtitle's existing `margin-top: 1.5rem` becomes the actual visible gap.

### Notes
- **Why a minor bump for one rule?** The 6.4 patch cap (project rule: 7 patches per minor) was reached at v6.4.7. Any further change to this minor forces a 6.5.0 bump regardless of size. Bundling the H1 reset alone here is the right call — the next hero adjustment (if any) can land in 6.5.x.

### Deploy
Update via WP admin → **Dashboard → Updates** (6.4.7 → 6.5.0), then **Breeze → Purge All Cache**, then **Cloudflare → Caching → Purge Everything**, then hard-refresh on the home page (Cmd+Shift+R).

## [6.4.7] — 2026-05-05

Revert v6.4.6's text-align: center on the home hero. User feedback: "I don't like everything centered like that". Going back to the v6.4.5 state — wrapper at 1100px max-width centred via auto margins, content inside left-aligned (editorial). Patch cap reached for the 6.4 minor (project rule: 7 patches per minor).

### Changed
- **Home hero — remove `text-align: center` from `.sn-hero-inner` and remove the accent's `margin: auto` and the buttons row's `justify-content: center !important` overrides.** [assets/css/critical.css](assets/css/critical.css) and [assets/css/layout.css](assets/css/layout.css) reduce `.sn-hero-inner` back to four declarations: `width: 100%; max-width: 1100px; margin-left: auto; margin-right: auto`. Inside the wrapper, all children flow with default block layout — H1 fills the column, subtitle is constrained to its `max-width: 640px` at the column's left, accent stays 120px at column-left, buttons row's flex layout uses its block-markup `justifyContent: "left"` so buttons cluster at column-left.

### Notes
- **The visible asymmetry tradeoff is real and unavoidable without changing typography.** H1's `clamp(3rem, 9vw, 7rem)` font means line 2 ("THAT SOUND RIGHT.") naturally renders at ~1100px wide on desktop. The column max-width can't drop below 1100 without forcing H1 to wrap to 3+ lines. With H1 line 1 ("I BUILD THINGS") at only ~575px wide and the column at 1100px, there will always be empty space to the right of H1 line 1 in editorial left-aligned mode. That's the cost of preserving the existing typography untouched, which is in the original spec.
- **Patch cap reached.** Per project CLAUDE.md, this minor allows up to 7 patches (6.4.0 → 6.4.7). The next change to the 6.4 line forces a minor bump to 6.5.0. Recommend that the next iteration on this hero — if any — bundle related work and ship as 6.5.0.

### Deploy
Update via WP admin → **Dashboard → Updates** (6.4.6 → 6.4.7), then **Breeze → Purge All Cache**, then **Cloudflare → Caching → Purge Everything**, then hard-refresh on the home page.

## [6.4.6] — 2026-05-05

Follow-up to v6.4.5. The wrapper-based centring landed structurally — verified via curl that the hero column was mathematically centred at 1100px wide with auto margins — but the user's screenshot still read as "not centred" because the H1's natural text width (~575px at desktop fonts) is smaller than the 1100px column it lives in. With `text-align: left` on the H1, that 575px of text sits at the column's left edge, leaving ~525px of empty space on the column's right. Across the viewport, the visible content reads as "left half full, right half empty" — uncentred to the eye, even though the column is mathematically centred.

This is the standard editorial pattern (Apple, Stripe, etc), but it doesn't match the user's spec, which was "centred" in the visual sense. Fixing by centring the text within the column.

### Changed
- **Home hero — text-align: center on the inner wrapper.** [assets/css/critical.css](assets/css/critical.css) and [assets/css/layout.css](assets/css/layout.css) add `text-align: center` to `.sn-hero-inner`. H1 lines, subtitle text, and the accent (which centres via `margin: 0 auto`) all now visibly centre within the 1100px column. Buttons row gets `justify-content: center !important` to override its block-markup `justifyContent: "left"` setting (the markup attribute can't be removed without a template edit, and `!important` cleanly overrides at runtime).
- **Accent bar — explicit `margin: 0 auto` to centre under the centred text.** With `text-align: center` on the wrapper, inline-block content centres, but the accent is a `<div style="width: 120px">` block element — `text-align` doesn't centre block children. Adding `margin-left: auto; margin-right: auto` to `.sn-hero-inner .sn-hero-accent` does.

### Notes
- **Same column width (1100px), same wrapper, no markup change from v6.4.5.** Just the text-alignment within the wrapper changes. If you decide later you want left-aligned editorial-style text inside a centred column instead, removing the `text-align: center` and `justify-content: center` rules on `.sn-hero-inner` reverts to that mode without touching markup.
- **Mobile/tablet inherit** the centring — `text-align: center` on `.sn-hero-inner` applies at every viewport since it's not inside a media query. Responsive.css owns the hero's outer paddings and animation timings at narrow widths but doesn't touch text-alignment, so mobile gets centred text too. (If that's wrong for mobile, easy to add a `@media (max-width: 781px) { .sn-hero-inner { text-align: left } }` override in a follow-up.)

### Deploy
Update via WP admin → **Dashboard → Updates** (6.4.5 → 6.4.6), then **Breeze → Purge All Cache**, then **Cloudflare → Caching → Purge Everything**, then hard-refresh (Cmd+Shift+R) on the home page.

## [6.4.5] — 2026-05-05

Third (and last) follow-up to v6.4.1 hero centring. The CSS-only path through v6.4.1 → v6.4.4 successively tried `width: 100%` + `margin: auto` on `.sn-hero-cta`, calc-based `margin-left` on `.sn-hero-accent`, and column-padding via `padding-left: max(40px, calc((100% - 1100px) / 2))`. Each variant was inlined into `<style id="sn-critical-inline">` correctly (verified via Python on the live HTML), and yet the rendered output kept showing the hero column drifted into the viewport's left half, with significantly more empty space on the right than the left. Three independent CSS attempts not landing the visible result is the signal: switch from CSS-only to a markup wrapper.

### Changed
- **Home hero — wrap children in an inner `<div class="sn-hero-inner">` and centre that.** [templates/front-page.html](templates/front-page.html) now wraps the H1, subtitle, accent bar, and buttons row inside a single `sn-hero-inner` div (emitted as raw HTML around the existing block markup). [assets/css/critical.css](assets/css/critical.css) and [assets/css/layout.css](assets/css/layout.css) replace the prior `.sn-hero { padding: max(...) }` + `.sn-hero.is-layout-constrained > * { margin-left: 0 }` rules with a single rule on the wrapper: `.sn-hero-inner { width: 100%; max-width: 1100px; margin-left: auto; margin-right: auto }`. The wrapper is the centred 1100px column; children inside flow with default block layout (margin-left: 0 by default) so they all share the same column-left x-coordinate by construction. No selector battles with WP's per-block `margin: auto !important` rule, no calc gymnastics, no cache-sensitivity. The outer `.sn-hero` keeps its full-width gradient `::before` and its `display: flex` vertical centring; the wrapper is the only flex item, so `justify-content: center` (vertical, since `flex-direction: column`) still vertically centres the whole hero block.

### Notes
- **Why a markup change now and not earlier.** v6.4.1 deliberately tried to fix this with CSS only because the original spec said "do not modify mobile layout" and "do not change typography". Both are still respected — the wrapper is invisible to layout flow at <782px (responsive.css owns those breakpoints with explicit symmetric paddings on `.sn-hero` itself; the wrapper inherits its width from the hero's content area, which mobile padding already constrains). Typography untouched. The wrapper is just a structural shim.
- **Class-based selectors still apply.** `.sn-hero .sn-hero-subtitle { max-width: 640px }` keeps working because it uses a descendant combinator — the subtitle is a descendant of the hero whether the wrapper is there or not. Same for `.sn-hero-title`, `.sn-hero-cta`, `.sn-hero-accent` animation rules.
- **`.sn-hero > * { z-index: 2; position: relative }` now applies to the wrapper instead of each child individually.** Functional behaviour is the same: wrapper sits above the gradient `::before` overlay, and so does everything inside it (the wrapper establishes its own stacking context).

### Deploy
After Update in WP, **purge Breeze + Cloudflare** caches one more time. The markup change means the rendered HTML structure itself differs, so any cached HTML page-output from v6.4.4 won't include `.sn-hero-inner` and the wrapper's centring rule will have nothing to attach to. Hard-refresh (Cmd+Shift+R) on the home page to be sure.

## [6.4.4] — 2026-05-05

### Removed
- **All biblical / Scripture content stripped from theme files.** Five references gone:
  - [templates/front-page.html](templates/front-page.html) — `<!-- "Work willingly at whatever you do, as though you were working for the Lord rather than for people." -->` (Colossians 3:23, hidden HTML comment in the hero section).
  - [templates/page-services.html](templates/page-services.html) — `<!-- "Do you see any truly competent workers? They will serve kings rather than working for ordinary people." -->` (Proverbs 22:29, hidden comment above the page-title group).
  - [templates/page-music.html](templates/page-music.html) — `<!-- "Sing a new song of praise to him; play skillfully on the harp, and sing with joy." -->` (Psalm 33:3, hidden comment between the page-title group and the Spotify-embed group).
  - [templates/page-about.html](templates/page-about.html) — visible italic right-aligned quote `"As iron sharpens iron, so a friend sharpens a friend."` (Proverbs 27:17) plus its preceding `<wp:spacer>` block, removed from the Education & Mentorship group.
  - [parts/footer.html](parts/footer.html) — visible footer line `Soli Deo Gloria` (concrete-grey 0.6rem italic paragraph). The right-side group wrapper that contained it is also gone, since with only the copyright line left there's no need for the inner two-paragraph flex group; the copyright `<wp:paragraph>` is now a direct child of the footer's flex container, which lets the existing `space-between` justification continue placing copyright at the right edge alongside the social icons on the left.
- The Aug-2025 v3.5.1 patch (`Removed book/chapter/verse references from all Scripture quotes across six templates`) explicitly kept the verses themselves; this release removes them entirely.

### Changed
- **Home hero — also force `padding-right` to the centring calc.** Belt-and-suspenders: v6.4.3 set `.sn-hero { padding-left: max(40px, (100% - 1100px) / 2) !important }` but left `padding-right` inheriting from the inline `style="padding-right: var(--wp--preset--spacing--40)"` on the `<wp:group>` markup (40px). With children at `margin-left: 0; margin-right: auto`, the auto right-margin already absorbs the asymmetric inner-content space, so output IS centred — but if cache or minification ever truncates the auto-margin override, the fallback would silently land non-centred. Setting both paddings to the same calc means the hero's content area is symmetric independent of the child margin override.

### Notes
- **About page Education & Mentorship section now ends on the two-column paragraph block** (mentor-bridge-the-gap text on left, mix-critiques-with-context text on right). Previous trailing italic Scripture quote and its spacer are gone, so the section closes cleanly with the columns.
- **Footer markup simplified.** Right-side wrapper `<wp:group>` removed since it only had to host two `<wp:paragraph>` siblings (Scripture line + copyright). Copyright paragraph now sits directly inside the footer's flex layout container.

### Deploy
After Update in WP, **purge Breeze + Cloudflare** caches one more time. The previous v6.4.3 fix landed CSS-side — verified the deployed `critical.css` file content matches the spec — but the page-output cache that ships the inlined critical CSS in `<head>` had not regenerated yet at the time of the post-deploy screenshot. v6.4.4's templates-and-CSS combo should produce a clean centred hero plus the biblical removals after one cache purge cycle.

## [6.4.3] — 2026-05-05

Second follow-up to the v6.4.1 layout fix. The accent bar and buttons row on the home hero were still rendering at the section's left edge after v6.4.2 even though the inline critical CSS contained the desktop-only `@media (min-width: 782px)` rules — verified via Python on the live HTML, the rules were present in the rendered `<style id="sn-critical-inline">` block, but the visible result didn't match. Switching CSS strategy.

### Changed
- **Home hero — replace the per-element `@media` overrides with a column-padding approach.** Both [assets/css/critical.css](assets/css/critical.css) and [assets/css/layout.css](assets/css/layout.css) now centre the hero column by setting the `.sn-hero` container's `padding-left` to `max(var(--wp--preset--spacing--40), calc((100% - 1100px) / 2))` and forcing all `.sn-hero.is-layout-constrained > *` children to `margin-left: 0 !important; margin-right: auto !important`. This is the same shape as the original v6.4.0 hero (children flush-left within an offset inner-content area), with the offset switched from the over-aggressive `15vw` to a calc that *exactly* centres a 1100px column. By construction every child — H1, subtitle, accent bar, buttons row — shares the same column-left x-coordinate, so the accent and buttons can no longer drift relative to the H1. Removed the `width: 100% / margin: max(...) / margin-right: auto` overrides on `.sn-hero-cta` and `.sn-hero-accent` from v6.4.1/v6.4.2 — they were the speculative mechanism that didn't actually take effect in production.
- **Tablet/mobile preserved exactly.** [assets/css/responsive.css](assets/css/responsive.css) keeps owning `padding-left: 1.5rem` (≤781px) and `padding-left: 1.25rem` (≤480px) with `!important` and a later cascade position, so under-782px viewports drop straight to the symmetric mobile padding regardless of what the desktop calc evaluates to.

### Fixed
- **`readme.txt` — bumped `Stable tag` from `4.2.3` to `6.4.3`.** Out-of-date by ~2 major versions; the WordPress.org plugin/theme directory uses this header to identify the current stable release. Caught while editing `readme.txt` for v6.4.3.

### Deploy
After clicking Update in WP, **purge Breeze + Cloudflare** caches again (same instructions as v6.4.2). Hard-refresh with Cmd+Shift+R on the home page to drop browser-side cache too.

## [6.4.2] — 2026-05-05

Follow-up to v6.4.1 to address two issues raised after deploy.

### Changed
- **Services — Business & Strategy section now matches Music & Production width.** Bumped `contentSize` on the Business & Strategy `<wp:group>` in [templates/page-services.html](templates/page-services.html) from `1000px` → `1400px`. v6.4.1 widened only the Music & Production grid; this leaves both image-card grids on the page at the same width, which is the correct read since they're the same content type. Page header and the LET'S TALK closing CTA stay at their existing narrower widths (1000px and 680px respectively) — those are prose, not media grids.

### Fixed
- **Home hero — duplicate the desktop accent/buttons rules into critical.css.** v6.4.1 placed the `@media (min-width: 782px)` block (which keeps the 120px accent bar and the buttons row aligned with the H1's column-left edge) only in `assets/css/layout.css`. Because [inc/assets-frontend.php](inc/assets-frontend.php) loads `layout.css` as an external `<link rel="stylesheet">` with a `?ver=` query string, a stale Cloudflare CDN copy under the previous `?ver=6.4.0` URL kept serving the pre-v6.4.1 file (verified live: `cf-cache-status: HIT, age: 133145`, ~1.5 days old). The rules never reached the browser. Now duplicated into [assets/css/critical.css](assets/css/critical.css), which is **inlined** in `<head>` on every render via `wp_head` priority 50 — no CDN cache, no `?ver=` URL, can't go stale relative to the surrounding HTML. Both copies are kept (critical.css for cache-resilience, layout.css for editor parity); they're identical.

### Deploy notes
- **After clicking Update in WP admin, purge the page cache.** Breeze (WP page cache) and Cloudflare (HTML edge cache) both need a flush to actually emit the new template `contentSize` values and the new critical CSS. Without a flush, page output stays cached as v6.4.0/v6.4.1 HTML even after the theme files swap. Quickest path: **Breeze → Settings → Purge All Cache**, then **Cloudflare → Caching → Purge Everything** (or purge by URL: `https://juanlentino.com/`, `/services/`, `/music/`, `/resume/`, `/work-with-me/`).

## [6.4.1] — 2026-05-05

### Fixed
- **Home hero — center the content column horizontally.** Removed the `.sn-hero` `padding-left: max(40px, 15vw) !important` rule (originally added in v3.9.4 as an "Apple-style golden-ratio offset") together with the matching `margin-left: 0 !important; margin-right: auto !important` override on `.sn-hero.is-layout-constrained > *`. Both rules were duplicated in [assets/css/critical.css](assets/css/critical.css) (inline) and [assets/css/layout.css](assets/css/layout.css) (deferred); both copies removed. WordPress's stock per-block constrained-layout rule (`max-width: 1100px; margin-left: auto !important; margin-right: auto !important`) now centres the hero column. Also dropped `align-items: flex-start` from the `.sn-hero` flex container so default cross-axis behaviour applies.
- **Home hero — keep the 120px red accent bar and the buttons row aligned with the H1's column-left edge.** Added a desktop-only (`min-width: 782px`) block in [assets/css/layout.css](assets/css/layout.css) that sets `width: 100%` on `.sn-hero-cta` (so the buttons-row block expands to the full 1100px column width and `justifyContent: "left"` actually puts buttons at the column's left edge instead of the hero's centre) and overrides the auto-margins on `.sn-hero-accent` to `margin-left: max(0, calc(50% - 550px)); margin-right: auto` so the 120px accent line sits at the column's left edge instead of being centred inside the column. Tablet/mobile (`max-width: 781px` + `max-width: 480px`) preserved exactly — `responsive.css` already owns those breakpoints with explicit symmetric paddings and stack/row direction overrides.

### Changed
- **Services — wider stat row and Music & Production image grid.** Bumped `contentSize` on two `<wp:group>` sections in [templates/page-services.html](templates/page-services.html) from `1000px` → `1400px`: the `.sn-credibility-strip` panel (20+ YEARS / 50+ ARTISTS / GRAMMY / MBA) and the Music & Production image-card grid (production / mixing / mastering rows). Page header (eyebrow + H1 SERVICES + intro) and the Business & Strategy panel below stay at 1000px so prose continues to read at a comfortable measure.
- **Music — wider Spotify embed.** Bumped `contentSize` on the Spotify-embed `<wp:group>` (which wraps `<wp:post-content>` for the page body) in [templates/page-music.html](templates/page-music.html) from `900px` → `1400px`. Page header (eyebrow + H1 MUSIC + intro) and the Muso.AI credits section below stay at 900px.
- **Resume — wider PDF viewer.** Bumped `contentSize` on the `#resume-viewer` `<wp:group>` in [templates/page-resume.html](templates/page-resume.html) from `900px` → `1400px`. Page header section stays at 900px. The PDF embed itself (rendered via `<wp:post-content>`) is unchanged in this session — replacing the embed with native HTML is flagged as a follow-up decision.
- **Work With Me — wider HOW IT WORKS process strip and booking calendar.** Bumped `contentSize` on two `<wp:group>` sections in [templates/page-work-with-me.html](templates/page-work-with-me.html) from `800px` → `1400px`: the asphalt-background HOW IT WORKS three-column strip (01/02/03) and the Tab Bar + Cal.com booking-area panel (30-min / 60-min embeds). Page header section stays at 800px.

### Notes
- **About / Contact / Notes templates untouched.** All three were already correctly centred via WordPress's stock constrained-layout rules — verified by curling the live About page and confirming `<style id='core-block-supports-inline-css'>` emits `margin-left: auto !important; margin-right: auto !important` per section. The "every page" right-pin perception was driven entirely by the home hero. No template edits needed.
- **theme.json untouched.** Global `contentSize: 720px` and `wideSize: 1200px` left as-is. Wider sections express themselves at the per-section level via `contentSize` overrides on their own `<wp:group>` blocks rather than via `align: "wide"` (which would only widen children to `wideSize: 1200px` — barely a step up from the 1000px baseline).
- **Mobile preserved exactly.** No edits to existing `@media (max-width: 781px)` or `@media (max-width: 480px)` blocks. The wider `contentSize: 1400px` on desktop sections has no effect under 1400px-viewport since the constrained `max-width` simply caps at the available width inside the section's 40px horizontal padding.
- **One commit per page.** Five commits in this release (home / services / music / resume / work-with-me) so any single page can be reverted independently. Versioning, changelog, and release tag in a sixth commit on top.

## [6.4.0] — 2026-05-03

### Removed
- **All in-theme Plausible analytics — replaced by the official Plausible WP plugin.** Removed because the plugin is a better-supported home for tracking and admin reporting, and keeping a parallel implementation in the theme was duplicating responsibility (and dragging two CDN-loaded vendor libs into wp-admin for a feature the plugin already covers). Concretely:
  - **Frontend tracking script** (`<script defer data-domain=…>`) deleted from [inc/seo.php](inc/seo.php). The plugin will inject its own once activated.
  - **`inc/plausible-api.php` deleted** — the Plausible Stats API client (`sn_plausible_api()`), the `SN_PLAUSIBLE_URL` / `SN_PLAUSIBLE_SITE` constants, the `sn_plausible_error` admin notice, and the helper formatters (`sn_fmt`, `sn_duration`, `sn_metric_card`, `sn_ranked_list`) all go with it. `SN_PLAUSIBLE_KEY` in `wp-config.php` is now ignored by the theme — it can be removed at the user's leisure (the plugin uses its own settings UI, not that constant).
  - **`inc/dashboard-widgets.php` deleted** — the four WP Dashboard widgets (Visitors Today, 30-Day Trend, Top Stats tabbed, Visitor Map). The plugin ships its own dashboard widgets.
  - **Analytics tab on `Appearance → Signal & Noise` deleted** — the date-range bar, six aggregate metric cards, time-series chart, world map, and 13 tabbed breakdowns. The options page is now two tabs (Dashboard / Links) instead of three. The "Plausible Dashboard" external link in the Links tab is also gone.
  - **`inc/admin-assets.php` deleted** — the entire admin asset registration layer was scaffolding for the analytics surfaces above (jsvectormap 1.6.0 + Chart.js 4.4.4 vendor libs with SRI hashes, plus the three theme-owned admin JS files). With nothing left to register, the file has no purpose.
  - **`assets/js/admin-map.js`, `assets/js/admin-tabs.js`, `assets/js/admin-chart.js` deleted** — the client-side renderers for the map, tab switcher, and visitor-trend chart respectively. All three were Plausible-only.
  - **`functions.php` bootstrap pruned** — three `require_once` lines (`plausible-api`, `dashboard-widgets`, `admin-assets`) and their references in the module map docblock removed.
  - **Header doc on [inc/admin-page.php](inc/admin-page.php)** updated from "three-tab interface (Dashboard / Analytics / Links)" → "two-tab interface (Dashboard / Links)" and the page subtitle from "Theme management, maintenance, and analytics" → "Theme management and maintenance".
- **Net diff:** ~860 lines of PHP + ~3 standalone JS files removed. The frontend now ships zero analytics requests until the Plausible plugin is installed and activated; wp-admin loads no admin-only vendor JS at all.

### Notes
- **Why a minor bump (6.3 → 6.4) and not a patch.** Removing a whole feature surface (Analytics admin tab, four dashboard widgets, the entire `plausible-api.php` module) is more than the patch lane is meant to carry — minor bumps are the right place for "feature added or removed". Patch cap of 7 wasn't the constraint; semantic intent was.
- **`SN_PLAUSIBLE_KEY` in `wp-config.php`** is now dead. Safe to leave or delete. The Plausible WP plugin doesn't read it.
- **The delayed `gtag.js` loader stays.** v6.4 only removes Plausible — Google Tag is independent and untouched in [inc/seo.php](inc/seo.php).
- **The `sn_admin_dashboard_extras` action** on the options-page Dashboard tab is preserved. It's still emitted on line 218 of [inc/admin-page.php](inc/admin-page.php) and consumed by [inc/reading-time.php](inc/reading-time.php) — unrelated to analytics, kept as-is.
- **Activation step (manual)**: install the official Plausible Analytics plugin from wp-admin → Plugins → Add New, point it at `juanlentino.com`, and activate. No theme code change is required to plug it in — the plugin self-injects its tracking script via its own `wp_head` hook.

## [6.3.5] — 2026-05-02

### Fixed
- **Real fix for the `/notes` excerpt indent** that v6.3.4 missed. The actual culprit was the `max-width: 65ch` introduced in v6.3.3, not horizontal margin/padding bleed-through as v6.3.4 assumed. WordPress' generated layout CSS includes `.is-layout-constrained > :where(:not(.alignleft):not(.alignright):not(.alignfull)) { margin-left: auto !important; margin-right: auto !important }`, which auto-centres every direct child of a constrained group (`.sn-note-card` is one). Setting `max-width: 65ch` made the excerpt narrower than the sibling title (which uses the layout's 720px content-size), and the `!important` auto-margins centred the narrower box — what looked like a left-indent in the rendered page was actually horizontal centring. v6.3.4's `margin-left: 0` lost the cascade fight against `!important` and didn't reach the page. Removed `max-width: 65ch` (along with the now-unneeded margin/padding-left/right resets) in [assets/css/components.css](assets/css/components.css) — the constrained layout's 720px is already a sensible reading measure (~50ch at 0.9rem), and dropping the override lets the excerpt and title share the same width and the same auto-centring, so they align at the same x-position.

### Changed
- **`/notes` and `/` (home) post-card meta strip is now red.** Date, divider (`·`), and reading time on each card switched from `textColor:"rust"` (gray `#666666`) to `textColor:"blood"` (red `#e00404`) in [templates/page-notes.html](templates/page-notes.html) and [templates/home.html](templates/home.html). Brings the index meta strip in line with the red-accent treatment used on the Single-Note reading time (v6.3.1), the Provenance byline reading time (v6.3.1), and the Pillar Essay eyebrow on `/notes`. The previous v6.3.1 note arguing for keeping the meta strip gray ("internally consistent with gray dates") is now superseded — the user explicitly asked for red, and the brand's red-accent vocabulary is consistent across every other meta strip in the theme.

### Notes
- The general lesson from the indent regression: when introducing a `max-width` on a block inside an `is-layout-constrained` group, remember that core's `margin-left: auto !important; margin-right: auto !important` will *centre* the narrower box. To keep it left-aligned, either (a) match the parent's `--wp--style--global--content-size`, or (b) override with `margin-left: 0 !important` (specificity won't beat `!important` without it). Easiest is to not set `max-width` at all and rely on the constrained layout's content-size.
- Both home.html and page-notes.html were updated together because they render the same Notes list with identical card markup. Keeping them in sync is what readers expect — `/` and `/notes` should look the same.

## [6.3.4] — 2026-05-02

### Fixed
- **`/notes` excerpt left-alignment.** After v6.3.3 removed the `-webkit-line-clamp: 1` rule, `.sn-note-card-excerpt` was rendering visibly indented from its sibling title and meta — `display: -webkit-box` had been masking horizontal margin/padding bleed-through from WordPress core's `wp-block-post-excerpt` defaults, and removing it let those defaults push the excerpt rightward. Fix in [assets/css/components.css](assets/css/components.css): explicitly zero `margin-left` / `margin-right` / `padding-left` / `padding-right` on both the `.sn-note-card-excerpt` wrapper and its inner `<p>`. Excerpt now sits flush at the same x-position as the title.

### Notes
- The general lesson: when overriding core block CSS in a theme, be explicit about horizontal spacing rather than relying on browser/core defaults — `display` mode changes can mask bugs that resurface when you switch back to flow layout.

## [6.3.3] — 2026-05-02

### Changed
- **`/notes` excerpts now render in full.** Removed the `-webkit-line-clamp: 1` rule on `.sn-notes-list .sn-note-card-excerpt` (and its inner `<p>`) in [assets/css/components.css](assets/css/components.css). Excerpts were being visually truncated to a single line with a `…` ellipsis regardless of how much text the dek actually contained, which defeated the point of writing a dek at all — they exist to be read, not teased. Excerpts now wrap to their natural height; card rhythm is still handled by `.sn-note-card`'s margin- and padding-bottom, so the index continues to scan cleanly with multiple entries.
- **Auto-excerpt word cap raised from 24 → 55.** In [templates/page-notes.html](templates/page-notes.html) the `wp:post-excerpt` block's `excerptLength` attribute moved to WordPress' default. This only affects posts published *without* a manually-authored excerpt — when a dek is written in the editor, WP shows it verbatim and the cap doesn't apply. The previous 24 was paired with the CSS clamp; with the clamp gone, 24 was leaving auto-fallback excerpts mid-sentence.
- **`.sn-note-card-excerpt` gets `max-width: 65ch`** so excerpts that wrap stay inside a comfortable reading measure and don't run the full content width on wide screens.

### Notes
- This reverses the "one-line deks" half of v6.2.7 (the pillar card and RSS footer changes from that release stay). The v6.2.7 design choice optimised for index density on a list with 6+ entries; the new behaviour optimises for reading the deks as standalone sentences, which is closer to how they're actually written.
- No CSS for the single-Note view changed. `single.html` continues to render the full post body via `wp:post-content`.

## [6.3.2] — 2026-05-01

### Added
- **`inc/og-image.php` — per-post OG/Twitter card generator.** Every post and page now ships its own 1200×630 brutalist text card on Twitter / iMessage / Slack / LinkedIn unfurls. Cards are rendered server-side with PHP GD using the brand's own typefaces (Bebas Neue Regular for the title, DM Mono Light for the eyebrow / dek / footer), cached as PNGs in `wp-content/uploads/sn-og/post-{ID}.png`, and rebuilt automatically on every `wp_after_insert_post`. Layout: red 60×4px accent bar top-left, "JUANLENTINO.COM" eyebrow in DM Mono, post title in Bebas Neue at 88pt wrapped to 3 lines, 3-line dek (post excerpt or first ~36 words of cleaned content), and "X MIN READ" in red as the footer. Cache-busted via `?v={post-modified-time}` so re-shares pick up edits without manual invalidation.
- **TTF fonts for server-side rendering** — `assets/fonts/og/BebasNeue-Regular.ttf` (61 KB, fetched from `dharmatype/Bebas-Neue` 2018 release) and `assets/fonts/og/DMMono-Light.ttf` (49 KB, from `googlefonts/dm-mono`). Both files are SIL OFL and are loaded only by `imagettftext()` — they're never referenced from CSS, so the existing WOFF2 preload pipeline is unaffected.
- **Lazy backfill — no migration needed.** The URL helper `sn_og_image_url_for_post()` checks for the cached PNG on every request and generates it on miss. Existing posts will get cards on their first social share without any one-time admin button or scheduled job.
- **Yoast SEO integration.** Yoast emits OG/Twitter tags first in `<head>` and wins the social-card scrape race, so we hook `wpseo_opengraph_image`, `wpseo_twitter_image`, and `wpseo_opengraph_image_size` to feed Yoast the same generated card URL the theme uses. If Yoast isn't installed the filters never fire — the module is degradation-safe.

### Changed
- **Resolution order for `<meta property="og:image">` is now**: (1) post's featured image, if set — never overridden; (2) generated card from `inc/og-image.php`; (3) theme default (the existing site-icon URL). The theme's own emitter in [inc/seo.php](inc/seo.php) reads through the existing `sn_og_image_url` filter, so it picks up the generated card automatically alongside Yoast.

### Notes
- **Robustness**: every code path that touches GD is gated behind `function_exists('imagettftext')`, every font path is `file_exists()`-checked, and the upload dir is `wp_mkdir_p()`-ensured. On any failure (GD missing, FreeType missing, fonts missing, dir unwriteable) the helper returns `null` and callers cascade to the previous default — OG cards aren't user-blocking and shouldn't take down a request if something is misconfigured.
- **Why GD, not Imagick or `@vercel/og`**: GD ships with the standard Cloudways PHP build and needs no external service or Edge Worker; the brand's typography is plain TTF; the cards are static once written. Adding a Worker for this would be operational overkill given the target audience (a personal site that publishes Notes weekly, not at fan-out scale).
- **Why not a Yoast-only site icon override**: Yoast's per-post default image comes from a single global setting in admin, not a per-post programmatic value — there's no "URL function" hook in Yoast's UI. Filtering at the PHP level is the supported route.

## [6.3.1] — 2026-05-01

### Added
- **Reading time on the Provenance byline.** `[sn_reading_time]` now renders inside the `sn-provenance-byline` flex group on `/provenance/`, sitting after the (modified) post-date with a `·` divider in between. Coloured `blood` (`#e00404`) so it reads as an accent, overriding the byline group's parent `rust` (gray) inheritance. New `.sn-provenance-byline-reading-time` and `.sn-provenance-byline-divider` selectors are referenced from the markup but the classes carry no extra CSS rules — they're hooks for future styling if needed.
- **`inc/seed-content/provenance-body.html` updated** so fresh installs ship with the reading-time byline baked in. Migration `sn_migrate_provenance_byline_reading_time()` (gated by the new `sn_provenance_byline_reading_time_migrated_v1` option flag) injects the same markup into the existing live page on next `admin_init`. New helper `sn_provenance_byline_reading_time_markup()` keeps the seed and the migration in lockstep — change the markup once, both ship the same shape. The migration is idempotent (skips if the marker class is already present in the body, defending against manual paste).
- **`displayType:"modified"` on the Provenance byline post-date** is now part of the seed (it had been added by the 6.2.6 refinements migration but the seed file still carried the original markup). Fresh installs and the live page now match.

### Changed
- **Single-Note reading time → red.** `templates/single.html` reading-time block switched from `textColor:"rust"` (gray `#666666`) to `textColor:"blood"` (`#e00404`) so it matches the red post-date next to it. The previous gray-next-to-red mismatch was a visible inconsistency on every Note. `/notes` index cards and `/` home cards stay gray on purpose — their dates are gray, so the meta strip is internally consistent and switching reading time alone would unbalance it.

### Notes
- The `rust` colour slug in `theme.json` is named "Steel" with hex `#666666` — historical mis-naming of the slug; the value is correct for its actual role (secondary/dim text on deks, excerpts, post-meta). The fix in this release uses the `blood` slug rather than redefining `rust`, so existing usages across `/services`, `/work-with-me`, `/contact`, etc. are untouched.
- Version: first patch on 6.3.x. Patch cap is 7 per minor.

## [6.3.0] — 2026-05-01

### Added
- **`inc/reading-time.php` — Cached reading-time module.** New module owning calculation, caching, and legacy cleanup. The `[sn_reading_time]` shortcode (previously living at the bottom of `inc/notes-and-provenance.php` at 200 WPM with no cache) is rebuilt here at **225 WPM** default with the result stored in the private `_sn_reading_time_minutes` post meta. The cache is rebuilt automatically on every post save via `wp_after_insert_post`, populated lazily on first render for any pre-existing posts, and recomputed after the cleanup tool runs. WPM is filterable via `sn_reading_time_wpm`; output format via `sn_reading_time_format` (default `"{minutes} min read"`, supports `"{minutes}-minute read"` for the long form).
- **Calculation strips block delimiters before counting.** `sn_calculate_reading_time()` runs `<!-- wp:* -->` removal → `strip_shortcodes()` → `wp_strip_all_tags()` → `str_word_count()` so Gutenberg block markup, our own shortcodes, and embedded HTML don't inflate the word total. One-minute floor preserved so a haiku still renders "1 min read".
- **Legacy reading-time cleanup tool — Appearance → Signal & Noise → Dashboard.** Two-step Preview / Apply pair gated behind the existing `sn_theme_options_nonce`. The Preview button runs `sn_find_legacy_reading_time()`, which scans every `post`/`page` (any status) for the regex `/~?\s*\d+\s*[-\s]\s*(?:minutes?|mins?)\s+read\b/i` across `post_content`, `post_excerpt`, and public custom fields, then renders a table of every match with a 50-char-per-side context snippet (the match itself wrapped in `<<…>>` markers) and a link to edit each post. Apply removes the matched substrings, collapses any `<p></p>` / `<span></span>` / `<small></small>` / `<em></em>` / `<strong></strong>` / `<i></i>` / `<b></b>` shells the removal leaves behind, then deletes the cached reading-time meta and re-derives it from the now-clean content. Private meta keys (any starting with `_`, including our own `_sn_reading_time_minutes`) are excluded from the scan by design.
- **`do_action( 'sn_admin_dashboard_extras' )` extension point.** New action fired in `inc/admin-page.php` at the end of the Dashboard tab so future modules can inject cards without editing the admin page directly. The reading-time cleanup card is the first consumer.

### Changed
- **Default words-per-minute raised from 200 to 225.** Closer to the median adult silent reading pace cited in the literature; lines up with the Medium/Substack defaults. Existing posts will reflect the new pace on next save (or on first cache miss for posts never edited under the new module).
- **`inc/notes-and-provenance.php` — shortcode + render_block bridge removed.** Both moved to `inc/reading-time.php`. The file now ends at the `restrict_main_query_for_notes_page` hook; a one-line stub comment marks where the old shortcode lived.

### Notes
- Versioning: per the project rule, the patch cap of 7 was hit at 6.2.7, so this lands as **6.3.0** (next minor) rather than 6.2.8. Code-and-functional change, so a version bump is warranted.
- Templates (`single.html`, `home.html`, `page-notes.html`) continue to embed `[sn_reading_time]` literally — no markup change needed; they automatically pick up the cached/upgraded behaviour.
- Cleanup is intentionally additive-safe: the regex is anchored on the literal word `read` and tolerates `min`/`mins`/`minute`/`minutes`, optional hyphens, and an optional leading `~`. It will not match the shortcode token `[sn_reading_time]`, nor will it touch private (`_`-prefixed) meta. Always run **Preview** first; the Apply button shows the affected post count next to its label so there's no chance of running it blind.

## [6.2.7] — 2026-04-25

### Changed
- **`/notes` index — pillar essay promoted from inline link to featured card.** The italic one-liner above the page H1 ("The pillar essay: Provenance Over Detection →") is replaced by a visually distinct card sitting between the page header and the Notes list. The card uses the existing asphalt-background + concrete-border treatment from `.sn-provenance-panel` — no new tokens — and contains a `PILLAR ESSAY` eyebrow, an `<h2>` title in `font-display`, the dek "A short read on why the industry needs to prove what's human, not chase what isn't.", and a `Read essay →` CTA in the heading font (uppercase, 0.15em letter-spacing, blood-on-signal hover). When a second pillar essay ever exists this section will be generalised into a list — for now it's hardcoded.
- **`/notes` index — Notes list tightened to one-line deks.** Each card's excerpt is now CSS-clamped to a single line via `-webkit-line-clamp: 1` on `.sn-note-card-excerpt` (and its inner `<p>`). Single-Note pages render the full body via `wp:post-content` and are unaffected. Per-entry density goes down without changing the existing list rhythm.
- **`/notes` page subtitle copy.** Replaced "Short essays on music, AI, and the systems behind both." with "Working notes on music, AI, and the infrastructure underneath. Written when there's something worth writing." Same position, same type style. Updated in three places that must stay in lockstep: the visible markup in `templates/page-notes.html` and `templates/home.html`, the seed `post_excerpt` in `inc/notes-and-provenance.php`, and the hardcoded SEO description in `inc/seo.php` (which feeds the `<meta name="description">` tag and OG/Twitter cards on `/notes`).

### Added
- **`/notes` index — RSS footer line.** A caption-size, opacity-dimmed line below the Notes list, separated by a hairline rule and a `spacing-40` spacer: "No subscription form, no schedule. Notes available via RSS." The "RSS" word links to `/notes/feed/` (the WordPress-generated feed for the Notes category). New `.sn-notes-rss` selector in `assets/css/components.css`.

### Notes
- All four changes use existing theme tokens — no new entries in `theme.json`. The pillar card reuses the asphalt+concrete panel pattern; the eyebrow reuses the `.sn-provenance-eyebrow` size/weight/colour treatment; the CTA reuses the heading-font uppercase pattern from `.sn-note-pillar-link` on the single-Note template.
- The dropped `.sn-notes-pillar-link` selector in `assets/css/components.css` previously shared a rule with `.sn-provenance-toc`. The rule is preserved for the TOC; the unused first selector is removed.
- This consumes the seventh patch in 6.2.x. Per project versioning, the next change must bump to 6.3.0.

## [6.2.6] — 2026-04-25

### Added
- **One-time migration** `sn_migrate_provenance_refinements()` in `inc/notes-and-provenance.php` that runs on next `admin_init` and surgically:
  - Injects the inline TOC paragraph between the Provenance hero and the first separator (skipped if `.sn-provenance-toc` is already present in the body — defensive against the case where the snippet was already pasted manually).
  - Adds `displayType: "modified"` to the byline's `wp:post-date` block so the date reads as "last updated" rather than "first published" — more honest semantics for a permanent reference essay that gets iterated on.
- New TOC block markup factored into `sn_provenance_toc_block_markup()` so the seed file (`inc/seed-content/provenance-body.html`) and the migration share a single source of truth.
- Updated the seed file itself (TOC + `displayType: "modified"`) so future installs ship with both refinements baked in.

### Notes
- Migration is gated by `sn_provenance_refine_migrated_v1` option flag — runs once per site, never re-runs. Both edits are idempotent (skip if already applied), so manual snippet-paste before the update doesn't cause double-injection.
- Prose paragraphs are never touched. Migration only inserts net-new content (TOC) and modifies one block attribute (post-date display type).
- This is the sixth patch in 6.2.x — one more remaining (6.2.7) before the next change must bump to 6.3.0 per project versioning.

## [6.2.5] — 2026-04-25

### Fixed
- **In-page anchor jumps no longer hide the section heading behind the fixed header.** Added `scroll-padding-top` on `html` matched to the body's `padding-top` for the fixed header at each breakpoint (124px desktop, 96px tablet, 81px mobile — header height plus a 16px breathing buffer). Site-wide fix, not just for the Provenance TOC.

### Added
- **`.sn-provenance-toc` link styling** in `assets/css/components.css` — bone-coloured links with concrete-grey hairline underline that strengthens to red on hover. Folded into the existing `.sn-notes-pillar-link` selector group so the TOC links read as the same understated treatment used by the inline pillar link on `/notes`.

### Notes
- The TOC itself is editorial content (lives in the Provenance Page body, not in any template). If you've already pasted the TOC snippet from earlier into the page, the links pick up the hairline-grey treatment automatically once this version lands.

## [6.2.4] — 2026-04-25

### Added
- **Reading time on `/notes` index cards.** Each Note card now shows date · reading time (matching the meta strip on the single-Note template). Uses the existing `[sn_reading_time]` shortcode inside the query loop's post-template — resolves per-post automatically. Applied to both `templates/page-notes.html` (Page route) and `templates/home.html` (Posts-page route).
- **Open Graph + Twitter card meta** for the front page, the Notes index, and every singular post/page. Emits `og:type` (article for posts, website otherwise), `og:title`, `og:description`, `og:url`, `og:site_name`, `og:image`, plus the matching `twitter:*` set.
- **`sn_seo_meta_for_current_view()` helper** in `inc/seo.php` — returns the active page's `[ $title, $description, $url ]` so the description tag and OG/Twitter tags share one source of truth.
- **`sn_og_image_url` filter** so the OG image can be overridden per-route or globally without touching theme code. Default is the site logo (`/wp-content/uploads/2026/02/cropped-jl_logo-min-300x300.png`); `summary_large_image` Twitter card is emitted when an image is present, falling back to `summary` when filtered to empty.

### Notes
- No new design tokens. Reading time on cards uses the same `0.75rem / uppercase / letter-spacing 0.15em / rust` treatment as the existing card date — visually it just becomes "DATE · 3 MIN READ".
- The OG image default is square (300×300, the site logo); for richer previews on social, set a 1200×630 image via the filter:
  ```php
  add_filter( 'sn_og_image_url', function() {
      return 'https://juanlentino.com/path/to/og-1200x630.jpg';
  } );
  ```

## [6.2.3] — 2026-04-25

### Fixed
- **`/notes` was rendering the first Note inside the index chrome.** When `/notes` is wired as the WP Posts page (`is_home()` context), `wp:post-title` and `wp:post-content` outside a query loop both resolve to the first post in the loop — not to the `page_for_posts` Page — because `get_the_ID()` inside the template returns the loop's first post ID. So the v6.2.2 `home.html` rendered: pillar link → first post's title (as a giant H1) → dek → separator → query loop → first post's full body dumped underneath.
- Replaced `wp:post-title` with a hardcoded `<h1>NOTES</h1>` in `templates/home.html`. The Page is always called "Notes"; a hardcoded heading is the simplest correct answer for this template's only context.
- Removed the trailing `wp:post-content` block from `templates/home.html`. It had no purpose in `is_home()` context — the query loop above already shows the posts.
- Both fixes are scoped to `home.html` only. `page-notes.html` (the regular Page route) still uses `wp:post-title` and `wp:post-content` correctly because in that context `get_the_ID()` returns the queried Page.

### Note on patch ceiling
- This is the third patch in 6.2.x (6.2.1, 6.2.2, 6.2.3) — the project's Apple-style versioning rule caps patches at 3/minor. Any further changes in this area will need to bump to 6.3.0.

## [6.2.2] — 2026-04-25

### Fixed
- **`/notes` index pillar link wasn't rendering.** When `/notes` is wired as the WP **Posts page** (Settings → Reading), WordPress routes the URL through `home.html` → `index.html` instead of the Page's custom template (`page-notes.html`). The pillar link, the page title, the dek, and the separator all live in `page-notes.html`, so none of them surfaced — only the bare query loop from the v6.0.0 inherited `index.html` did.
- Added `templates/home.html` mirroring `page-notes.html` exactly. WP picks `home.html` over `index.html` for the Posts page, so the pillar link and chrome now render regardless of how `/notes` is wired in the install (custom Page template OR WP Posts page).

### Notes
- No changes to `index.html` — it stays as the generic fallback for other archive contexts (search, dates, etc.) so the pillar link doesn't pollute unrelated pages.
- If you'd rather route `/notes` through `page-notes.html` (Page template) and remove the Posts-page setting, that's still valid — the new `home.html` just makes the rendering consistent regardless of the route.

## [6.2.1] — 2026-04-25

### Removed
- **Homepage Featured Essay card.** Reverted `templates/front-page.html` to its v6.1.3 state — the asphalt-tinted section + Apple-style card below the hero is gone. Didn't fit the homepage's voice.
- **`.sn-featured-essay*` CSS** in `assets/css/components.css` and `assets/css/responsive.css` (all base, hover, link, mobile-padding, and touch-device-override rules). The card was the only consumer; removing it now keeps the stylesheet honest.

### Kept (unchanged from v6.2.0)
- `/notes` index inline pillar link (`.sn-notes-pillar-link`) — *"The pillar essay: Provenance Over Detection →"* above the page title.
- Single Note footer pillar link (`.sn-note-pillar-link`) — "Start with the pillar →" above "← All Notes".

## [6.2.0] — 2026-04-25

### Added
- **`/provenance` pillar surfaced in three places, with three distinct visual treatments calibrated to each surface:**
  1. **Homepage** (`templates/front-page.html`) — full Apple-style card directly below the hero, on an asphalt-tinted section. White card with eyebrow ("Featured Essay"), title (links to `/provenance`), dek, and "Read essay →" CTA. Subtle dual-layer shadow; hover lifts the card 2px and deepens the shadow. The card is the front door for someone who's never visited before.
  2. **`/notes` index** (`templates/page-notes.html`) — single muted italic line above the "NOTES" page title: *"The pillar essay: [Provenance Over Detection →]"*. Link uses a hairline-grey underline that strengthens on hover and the text turns red. No card, no eyebrow, no section background — reads as "by the way…", appropriate to a list page where the chronological notes themselves are the main affordance.
  3. **Every Note** (`templates/single.html`) — a "Start with the pillar →" footer link automatically rendered above the existing "← All Notes" link. Same heading-font / underline-on-hover treatment, so the two read as a coherent pair.
- New CSS classes in `assets/css/components.css`:
  - `.sn-featured-essay` (homepage card) — Apple-card style using only existing theme tokens.
  - `.sn-notes-pillar-link` (notes-index inline link) — bone-coloured link with hairline-grey underline.
  - `.sn-note-pillar-link` (single-Note footer) — folded into the existing `.sn-note-back` selector group for consistent treatment.
- Mobile padding tightening for the homepage card and its section wrapper at `max-width: 640px`. Hover transform on the card is disabled on touch devices via the existing `(hover: none)` block in `assets/css/responsive.css`.

### Notes
- No new design tokens. All surfaces use `var(--wp--preset--color--*)`, `var(--wp--preset--font-family--*)`, and the existing `--wp--preset--spacing--*` scale. Shadow values are CSS rules using rgba black, not new colour tokens.
- No new components, no new dependencies, no new plugins.
- Title sizing on the homepage card matches the existing Notes-list post-title clamp (`clamp(1.8rem, 3vw, 2.5rem)`), so the two surfaces feel related at a glance.

## [6.1.3] — 2026-04-25

### Fixed
- **"← All Notes" footer link no longer cramped against the fixed footer.** Bumped `main` bottom padding on `templates/single.html` and `templates/page-provenance.html` from `spacing--60` (96px) to `spacing--70` (128px). The fixed footer chrome (social icons + language toggle + copyright) is ~76–90px tall and overlays the bottom of the viewport; the previous 96px padding only bought ~6px of breathing room above the link when scrolled to the bottom. 128px gives a comfortable ~38px clearance.

## [6.1.2] — 2026-04-25

### Changed
- **Note layout is now the default for any single post.** Replaced `templates/single.html` with the Note layout (date · reading time, title, body, "← All Notes" footer link). Deleted the redundant `templates/single-note.html`. Removed the `single_template_hierarchy` filter from `inc/notes-and-provenance.php` — no longer needed now that there's a single source of truth.
- **New posts are now in the Notes category by default.** `sn_sync_default_category()` runs cheaply on every `admin_init` and points WordPress's `default_category` option at the Notes category term. Self-healing if the option ever drifts.
- Net effect: **Posts → Add New → write → Publish** produces a fully-formed Note. No template dropdown to find, no category checkbox to remember, no Site Editor visit. The post renders with the Note layout AND appears at `/notes` immediately.

### Removed
- `templates/single-note.html` (collapsed into `single.html`)
- `single_template_hierarchy` filter (no longer needed — there's only one single-post template now)
- `single-note` entry from `theme.json` `customTemplates` (already gone in this release; the dropdown surface was the wrong UX anyway)

## [6.1.1] — 2026-04-24

### Changed
- **Provenance pillar content moved from template to Page body.** All visible content (hero, five anchored sections, Section 2 SVG diagram, footer CTA, dynamic byline) now lives in the `/provenance` Page itself instead of `templates/page-provenance.html`. The template is now a thin shell: header part, `wp:post-content`, footer part. Editing prose is now a Pages → Provenance click — no more Site Editor required, and edits survive theme updates (Page bodies aren't purged by the existing `template-maintenance.php` version-bump auto-clear, which only targets `wp_template`/`wp_template_part`/`wp_navigation` post types).
- `sn_ensure_provenance_page()` now seeds the body from `inc/seed-content/provenance-body.html` on fresh installs.

### Migration
- `sn_migrate_provenance_body()` runs once per site on `admin_init`, guarded by `sn_provenance_body_migrated_v1`. Sites upgrading from v6.1.0 (where the Provenance Page body was empty) get the seed content auto-installed into the existing Page. Never overwrites a non-empty body.

## [6.1.0] — 2026-04-24

### Added
- **Notes content surface.** WordPress Posts re-enabled with a single `Notes` category (slug `notes`). Permalink structure set to `/notes/%postname%/` on first activation, guarded so it only fires when the current structure is different AND no existing posts would have their URLs broken.
- **`/notes` index** rendered via the `page-notes.html` custom Page template. Query loop is scoped to the Notes category at runtime via the `query_loop_block_query_vars` filter (queryId `42`) — keeps the markup independent of any specific category-term ID. No sidebar, no thumbnails, no pagination UI; empty/low-count state renders a graceful "No notes published yet" message via `wp:query-no-results`.
- **`single-note.html` template** for individual Notes — date, reading time, title, body, footer "← All Notes" link. No comments, pings, share buttons, related posts, or author bio markup. Auto-routed for posts in the Notes category via the `single_template_hierarchy` filter; editors can still pick a different template explicitly.
- **`/provenance` static Page** rendered via `page-provenance.html`: hero (title, subhead, "4 min read", SSRN secondary CTA), five anchored sections (`#setup`, `#analogy`, `#what-it-means`, `#why-it-matters`, `#the-shift`), the Detection-vs-Provenance SVG visual in Section 2, footer CTA with two equal-weight links (SSRN paper + `/notes`), and a dynamic byline using the page's published date via `wp:post-date`.
- **Detection-vs-Provenance SVG diagram** (Section 2): two side-by-side panels using `currentColor` plus a single `--wp--preset--color--blood` accent token. Accessible via `role="img"`, `aria-labelledby`, `<title>`, `<desc>`. Stacks vertically below 640px.
- **Meta description** dedicated copy for `/notes` ("Short essays on music, AI, and the systems behind both.") and `/provenance` (mirrors the subhead). Other singular pages continue to fall back to the post excerpt.
- **`Notes` link** added to the main nav between `Resume` and `Contact`. `Provenance` is intentionally NOT added to the main nav (it's reserved for a future homepage essay teaser).
- **`[sn_reading_time]` shortcode** — 200 wpm calculation with a one-minute floor — wired into the existing `render_block` shortcode resolver pattern (mirror of `[current_year]`).

### Architecture
- New module `inc/notes-and-provenance.php` (idempotent activation seeder for the Notes category, the `/notes` Page, and the `/provenance` Page; permalink structure guard; query/template filters; the reading-time shortcode). Module map in `functions.php` updated.
- `inc/seo.php` extended (no breaking changes) with `is_page('notes')` and `is_page('provenance')` branches in the existing meta-description handler. No SEO plugin work, no Open Graph / Twitter card additions (no existing theme OG defaults to mirror — left to the installed SEO plugin).
- Three new custom templates registered in `theme.json`: `page-notes`, `page-provenance`, `single-note`. No new colour tokens, font families, font sizes, font weights, or spacing values introduced — all new CSS references existing `theme.json` tokens.

### Notes
- Discussion features (comments, pings, trackbacks, XML-RPC) untouched — they remain disabled at the WordPress + infrastructure layer per project policy. New templates ship with no comment / ping / trackback markup.
- Provenance section bodies are marked `[DRAFT — replace with final prose]` for the user to fill in. Section copy lives in the template; the Page itself is created empty.
- No new plugins. No taxonomy work beyond the single `Notes` category (no tags).

## [6.0.0] — 2026-04-13

### Architecture
- `functions.php` split from 1267 lines into a 40-line bootstrap + 10 `inc/*.php` modules (setup, assets-frontend, seo, frontend-filters, plausible-api, admin-assets, dashboard-widgets, template-maintenance, admin-page, updater).
- `assets/css/custom.css` split from 1125 lines into 5 modules (base, layout, components, forms, responsive) loaded in a dependency chain so `responsive.css` always prints last. `add_editor_style()` receives the same five paths so the Site Editor matches the front end.

### Security
- Pinned CDN assets (jsvectormap 1.6.0, Chart.js 4.4.4) now carry `integrity="sha384-..."` and `crossorigin="anonymous"` via `script_loader_tag` / `style_loader_tag` filters.
- Scoped transient purges: `Purge All Caches` and `Full Reset` now delete only `_transient_sn_*` rows instead of wiping every plugin transient on the site.
- Fixed a latent CDN 404: old inline `<link>` referenced `/dist/css/jsvectormap.min.css`, which does not exist in 1.6.0. Correct path is `/dist/jsvectormap.min.css`. The visitor map had been rendering unstyled, which contributed to the zoom overflow symptoms.

### Admin UX
- **Visitor map zoom no longer escapes the card.** `overflow:hidden` + `position:relative` on the container; `zoomOnScroll`/`zoomButtons`/`panOnDrag` disabled (map is hover-tooltip only).
- Top Stats and Breakdowns tabs rewritten as `<button role="tab">` with full ARIA semantics (`aria-selected`, `aria-controls`, `aria-labelledby`, `hidden` panels) and arrow-key / Home / End keyboard navigation.
- GitHub updater surfaces failures: missing `SN_GITHUB_TOKEN` shows a warning notice; WP_Error or non-200 responses capture into `sn_github_error` and show an error notice on Dashboard / Updates / Themes screens.
- Plausible API mirrors the pattern: WP_Error and non-200 captured into `sn_plausible_error` with a matching admin notice on Dashboard and the theme options page.
- `check_updates` and `full_reset` handlers now also clear `sn_github_error` so the notice self-heals after a manual retry.
- `$_GET['tab']` on the theme options page is validated against `[dashboard, analytics, links]` with a fallback to `dashboard`.

### Code cleanup
- Four echoed inline `<script>` blocks (two map widgets, chart widget, Top Stats tabs) extracted to `assets/js/admin-map.js`, `admin-tabs.js`, `admin-chart.js`. Vendor + theme JS registered once and enqueued per screen via `admin_enqueue_scripts`.
- Two visitor-map implementations collapsed: both widgets now emit `<div class="sn-map-widget" data-sn-map="...">` and `admin-map.js` auto-inits any element with that attribute.
- 44 hardcoded hex colours in `custom.css` → `var(--wp--preset--color--*)` tokens from `theme.json`. Remaining two are `#999999` (neutral placeholder, not a brand colour).
- `SN_PLAUSIBLE_URL` outputs now wrapped in `esc_url()` and carry `rel="noopener"` alongside `target="_blank"`.
- `Tested up to:` corrected from `6.9` (unreleased) to `6.8`.

### Known deferred
- 181 `!important` rules remain across the stylesheets. Most override WP block-editor inline styles and can't be pruned without browser-side verification. Splitting the file has at least made the clusters easier to locate for a future dedicated pass.
- `inc/admin-page.php` is 437 lines — a single feature (three-tab options UI). Further sub-splitting would fragment an `elseif` chain across files.
- `assets/css/critical.css` at 501 lines hasn't been touched; flagged for a future audit.
- Dark mode (`data-theme="dark"`) is not implemented. The theme is intentionally light-only per the NIN/brutalist aesthetic; noting the deviation from the global rule for a future decision.

## [5.1.0] — 2026-03-31

### QA cleanup
- Removed dead CSS: .sn-line-accent, .sn-hero-image, .sn-accent-line, .sn-service-card (4 classes, ~30 lines across custom.css and critical.css)
- Removed dead @keyframes lineExpand (no longer referenced after accent-line removal)
- Footer copyright: hardcoded "2026" replaced with [current_year] shortcode (shortcode + block processor already existed)
- Services CTA: "Get In Touch →" now links to /work-with-me instead of /contact

## [5.0.3] — 2026-03-31

- Removed redundant "Book a session below." from Work With Me subtitle
- Swapped steps 2 and 3: Book & Begin is now 02, Custom Quote is now 03 (book first, quote for larger projects comes after)

## [5.0.2] — 2026-03-31

- Compacted How It Works on Work With Me: smaller numbers (2.5→1.8rem), shorter descriptions (10 words max), tighter padding, removed spacer

## [5.0.1] — 2026-03-31

- Moved "How It Works" process strip from Services to Work With Me (belongs where booking happens)
- Step 3 renamed from "Production" to "Book & Begin" with copy pointing to the calendar below
- Services page goes straight from service cards to closing CTA

## [5.0.0] — 2026-03-31

### Full Analytics Dashboard
- Replaced Analytics tab stub with a complete Plausible-powered dashboard
- Date range picker: 7d, 30d, 6 months, 12 months (query param, no page reload framework needed)
- 6 metric cards with period-over-period comparison: Visitors, Visits, Pageviews, Views/Visit, Bounce Rate, Visit Duration
- Visitor trend chart (Chart.js 4.4.4): dual-line visitors + pageviews with responsive axes
- Visitor map (jsvectormap 1.6.0): choropleth colored by traffic volume, respects selected date range
- 13 tabbed breakdown panels: Pages, Entry Pages, Exit Pages, Sources, Referrers, UTM Medium, UTM Source, UTM Campaign, Countries, Cities, Devices, Browsers, OS
- All data cached with transients (5 min for 7d, 15 min for longer ranges)
- Graceful degradation if SN_PLAUSIBLE_KEY not set
- Link to external Plausible dashboard preserved in header

## [4.5.2] — 2026-03-31

- Fixed Visitor Map not highlighting countries: removed strtolower() on country codes (jsvectormap expects uppercase ISO 3166-1 alpha-2 codes matching Plausible's format)
- Fixed map script loading race condition: replaced DOMContentLoaded with window load + polling fallback; pinned jsvectormap CDN to v1.6.0

## [4.5.1] — 2026-03-31

- Removed footer border-top separator (whitespace does the job)
- Hero accent line widened from 60px to 120px (aligns with "hold" in subtitle)

## [4.5.0] — 2026-03-31

- Footer: swapped SDG and copyright order (SDG → © rightmost)

## [4.4.2] — 2026-03-31

### QA pass — CSS sync audit
- Fixed film grain opacity mismatch: custom.css had 0.025, critical.css had 0.035 (synced to 0.035)
- Fixed footer border conflict: critical.css was overriding custom.css border-top with `none !important`. Synced both to show the 1px concrete border
- Fixed header transition: custom.css was missing `background-color 0.3s ease` from transition, causing the frosted-glass opacity to snap instead of fade on scroll
- Fixed header scrolled state: custom.css was missing `background-color: rgba(255,255,255,0.85)` and glass morphism properties. Both CSS files now match
- Removed dead `#trp-floater-ls` CSS (TranslatePress repositioned via plugin settings)

## [4.4.1] — 2026-03-31

- TranslatePress floating switcher: repositioned above fixed footer (bottom: 70px) so it doesn't overlap copyright/SDG text

## [4.4.0] — 2026-03-31

- Hero alignment: golden-ratio offset (15vw on wide screens) instead of flush container edge
- Footer redesign: socials left, copyright + Soli Deo Gloria right (space-between flex layout)
- Footer layout changed from centered constrained to full-width flex

## [4.3.3] — 2026-03-31

- Fixed hero left-alignment positioning: content was flush to viewport edge (only offset by section padding). Added dynamic padding-left using max() to position content where a centered 1100px container would start on wide screens, falling back to theme spacing on narrow screens

## [4.3.2] — 2026-03-31

- Fixed hero left-alignment: WP constrained layout applies margin-left:auto as inline styles on children, so :where() selector (v4.3.0) couldn't override them. Switched to scoped !important on .sn-hero.is-layout-constrained > *

## [4.3.1] — 2026-03-31

- Version bump to trigger self-updater (4.3.0 was already on server)

## [4.3.0] — 2026-03-31

### Sizing & Polish Pass
- Logo: 56→80px desktop, 44→56px tablet, 38→44px mobile; scrolled states scaled proportionally
- Nav text: 0.9rem→1rem
- Hero subtitle: 1.1→1.15rem (1.2rem on 1440px+)
- Hero left-alignment: CSS-only fix using `:where()` selector to override WP constrained layout auto-margins without breaking alignwide/alignfull; subtitle capped at 640px
- XL desktop breakpoint (1440px+): body text 1.05rem, button padding scaled, hero subtitle 1.2rem
- CF7 submit button: styles duplicated into critical.css as Breeze minification insurance
- Body padding-top and hero min-height synced across all breakpoints (desktop/tablet/mobile) in both custom.css and critical.css
- Header HTML `width`/`height` attributes updated to match CSS values

## [4.2.0] — 2026-03-31

- Fixed Breeze CSS minification: moved custom.css to `wp_enqueue_style` so `breeze_exclude_css` filter works
- Breeze was ignoring the filter because custom.css was echoed as raw HTML, not enqueued

## [4.1.0] — 2026-03-31

- Consolidated dashboard: 4 widgets instead of 6
- Visitor Trend: 30-day red bar chart with total count
- Top Stats: single tabbed widget (Pages/Sources/Countries/Devices/Browsers)
- Visitor Map: world choropleth via jsvectormap, colored by traffic

## [4.0.0] — 2026-03-31

- 6 native Plausible dashboard widgets pulling from Stats API
- Visitors Today, Top Pages, Top Sources, Top Countries, Devices, Browsers

## [3.14.3] — 2026-03-31

- Restored default WP dashboard widgets, Plausible full-width on top

## [3.14.2] — 2026-03-31

- Clean dashboard: removed default widgets (reverted in 3.14.3)

## [3.14.1] — 2026-03-31

- Plausible analytics widget on WP Dashboard

## [3.14.0] — 2026-03-31

- Tabbed admin page: Dashboard, Analytics, Links tabs

## [3.13.0] — 2026-03-31

- Plausible CE tracking script on frontend (defer, ~1 KiB, cookie-free)
- Plausible dashboard embedded in admin page

## [3.12.1] — 2026-03-31

- Removed WP Statistics cleanup code (plugin uninstalled)

## [3.12.0] — 2026-03-31

- Signal & Noise admin page: status panel, actions (Full Reset, Clear Overrides, Purge Caches, Check Updates), links

## [3.11.1] — 2026-03-31

- Test release for self-updater verification

## [3.11.0] — 2026-03-31

- GitHub self-updater: checks releases, one-click update from WP admin
- `upgrader_source_selection` filter fixes the `-1` folder rename problem

## [3.10.4] — 2026-03-31

- Fixed contact form radio label: `.wpcf7-form p.form-label` styled to match other labels

## [3.10.3] — 2026-03-31

- Red accent line (60px) between hero subtitle and CTA buttons with fadeInUp animation

## [3.10.2] — 2026-03-31

- Removed default nav underline (`text-decoration: none`)
- Thickened red accent from 1px to 2px

## [3.10.1] — 2026-03-31

- Auto-clear template parts + `wp_navigation` on theme update
- Version-change detector triggers full override clear

## [3.10.0] — 2026-03-31

- Logo switched to media library images (persistent across theme uploads)

## [3.9.9] — 2026-03-31

- Fixed 60-min Cal.com tab: lazy init on first click (can't render into hidden container)
- Removed prices from tab labels

## [3.9.8] — 2026-03-31

- Replaced Cal.com shortcodes with JS inline embeds (shortcodes don't render in block theme templates)

## [3.9.7] — 2026-03-31

- Added Work With Me to header navigation (hardcoded in parts/header.html)

## [3.9.6] — 2026-03-31

- Full revert of `front-page.html` to pre-session state (v3.8.1)
- Restored original subtitle paragraph (simple `1.1rem`, no clamp, no max-width)

## [3.9.5] — 2026-03-31

- Reverted hero CSS to pre-session state; removed `margin-left: 0` that pushed content to page edge

## [3.9.4] — 2026-03-31

- Fixed hero left-alignment: overrode WP constrained layout `margin-left: auto` on hero children
- WP's `is-layout-constrained` was centering content despite CSS flexbox `align-items: flex-start`

## [3.9.3] — 2026-03-31

- Excluded theme CSS from Breeze minification (`breeze_exclude_css` filter)
- Breeze was stripping the `onload` handler from deferred custom.css, leaving styles on `media=print`
- Removed Cloudflare "Cache Everything" page rules (were caching admin pages, API responses, theme/plugin thumbnails)
- Cloudflare default behavior (cache static assets) + Varnish handles frontend performance

## [3.9.2] — 2026-03-31

- Fixed hero layout: removed `justifyContent: left` which pushed container to page edge
- Reverted to original `constrained` with `contentSize: 1100px`
- Text left-aligns naturally within centered container via CSS `.sn-hero` flex align-items

## [3.9.1] — 2026-03-31

- Fixed hero layout: reverted from `default` to `constrained` with `justifyContent: left`
- Content stays within 800px container, left-aligned, matching Contact page pattern

## [3.9.0] — 2026-03-31

- Work With Me consulting page: tabbed 30/60-minute session booking via Cal.com
- Tab switching JS with theme-matched styling (Bebas Neue tabs, red active indicator)
- `hideEventTypeDetails` on Cal.com embeds (page provides its own description/price)
- Registered `page-work-with-me` template in theme.json

## [3.8.5] — 2026-03-31

- Auto-flush theme cache on deploy: detects version mismatch on first admin page load after CI/CD deploy
- Clears theme transients, object cache, and WP theme cache automatically
- Zero cost on subsequent loads; only fires when the deployed version changes

## [3.8.4] — 2026-03-31

- Left-aligned hero section: changed layout from constrained (centered) to default (flow)
- Added max-width on subtitle paragraph
- GitHub Actions CI/CD: push to `main` auto-deploys to Cloudways via rsync + SSH
- Deploy pipeline flushes WP object cache, transients, and Breeze minification cache
- Cloudflare edge caching enabled (31-day TTL, TTFB ~100ms cached)
- Homepage cache warmup after deploy
- Updated README and readme.txt documentation

## [3.8.3] — 2026-03-31

- Dequeued Contact Form 7 JS on non-contact pages
- Removed WP Statistics frontend CSS and tracker JS
- Deferred TranslatePress language switcher CSS via print/onload pattern
- Output buffer stripping for Breeze-bundled WP Statistics stylesheets

## [3.8.2] — 2026-03-31

- Added `composer.json` + `composer.lock` for Aikido supply chain scanning

## [3.8.1]

- Larger logo across all breakpoints: desktop 56→64px (scrolled 38→44px), tablet 44→52px, mobile 38→44px
- Body padding and hero calc adjusted to match

## [3.8.0]

- Bumped film grain opacity from 0.025 to 0.035 for more tactile texture
- Frosted glass header via `backdrop-filter: blur(12px)` with semi-transparent white background (72% default, 85% on scroll)
- Pill-shaped buttons site-wide (`border-radius: 999px`)

## [3.7.0]

- Removed Quoter from WordPress theme; moved to standalone Nginx-served app with basic auth
- Removed page-quoter template, quoter.js, auth gate, and jsPDF enqueue from functions.php

## [3.6.1]

- Quoter fix: robust script loading for block themes
- Added `get_page_template_slug` fallback in `wp_enqueue_scripts`
- Rebuilt quoter.js with DOM-ready fallback, createElement for deliverable rows, error handling for jsPDF

## [3.6.0]

- Private Quoter tool: admin-only page template for generating branded project quotes
- Hybrid pricing model (session days + deliverables) with live calculation
- Configurable revision policy, payment terms, and one-click PDF export via jsPDF

## [3.5.1]

- Removed book/chapter/verse references from Scripture quotes across six templates

## [3.5.0]

- Services page overhaul: consolidated Business & Strategy from 4 text blocks to 2 image cards
- Added "How It Works" process strip (Scope Call → Custom Quote → Production) on smoke background
- Updated credibility strip: swapped "Full Sail Valedictorian" for "GRAMMY Voting Member"
- Process strip CSS with vertical dividers desktop, horizontal dividers tablet

## [3.4.8]

- Fixed Services page images: updated upload paths from 2023/10 to 2026/02

## [3.4.7]

- Rewrote Resume page intro with site-native voice

## [3.4.6]

- Updated Resume template summary to match new resume content and positioning

## [3.4.5]

- Removed CF7 block wrapper border on contact page

## [3.4.4]

- Root cause fix: removed `justifyContent:right` and overlay colors from nav block; desktop right-align via CSS
- Removed header/footer separator lines for cleaner layout

## [3.4.3]

- Fixed mobile nav: inlined overlay styles at priority 99 to beat WP core CSS
- Moved nav overlay styles into inlined critical CSS to bypass cache

## [3.4.2]

- Mobile nav overlay: centered links, removed right-align override

## [3.4.1]

- Theme-level favicon fallback (32px + 180px apple-touch-icon)

## [3.4.0]

- Split critical/deferred CSS (8 KB inline vs 20 KB deferred)
- Delayed gtag.js until first user interaction (eliminates 147 KiB from initial load)

## [3.3.4]

- Preloaded DM Mono 300 font (breaks 434ms network chain)
- Properly sized logo (56px + 112px retina, saves 2.5 KiB)

## [3.3.3]

- Stripped Cloudflare Turnstile script on non-contact pages (17 KiB render-blocker)

## [3.3.2]

- Deferred wp-block-library CSS
- Fast hero animations on mobile
- `fetchpriority=low` on Interactivity API script modules

## [3.3.1]

- Fixed NO_LCP: changed animation keyframes from `opacity: 0` to `0.01` (Chromium bug)

## [3.3.0]

- Inlined custom.css to eliminate last render-blocking external CSS request
- Zero external render-blocking resources
- Dequeued CF7 CSS on non-contact pages; deferred on contact page

## [3.2.2]

- Inlined critical Bebas Neue `@font-face` in head to fix NO_LCP

## [3.2.1]

- SEO: added meta description tag for front page and singular posts with excerpts

## [3.2.0]

- Self-hosted fonts: Bebas Neue + DM Mono (300/400/500) woff2 files served locally
- Eliminated render-blocking Google Fonts CSS request

## [3.1.2]

- Removed all Breeze lazy-load workarounds; Breeze lazy images disabled at plugin level

## [3.1.1]

- Contact Form 7 styling: inputs, textarea, labels, submit button, focus states, validation, response messages

## [3.1.0]

- Replaced Site Kit with direct gtag.js snippet (GT-NMC3GVL)
- Removed all GSI script workarounds

## [3.0.3]

- Removed Optimization Detective workaround code (plugin deleted)

## [3.0.2]

- Output buffer reverses Breeze lazy-loading on logo img tag
- Restores fetchpriority stripped by Optimization Detective plugin

## [3.0.1]

- Logo switched from CSS background-image to real `<img>` with `loading="eager"` and `fetchpriority="high"`
- Fixes LCP detection (Lighthouse NO_LCP error)

## [3.0.0]

- PageSpeed Insights optimization pass
- Font preloading: preconnect hints + preload for Bebas Neue/DM Mono woff2 files
- Google Sign-In (GSI) script removed: dequeue + output buffer strip (93KB blocker)
- Generator meta tag stripping consolidated into single `template_redirect` ob_start
- Footer social icons: normal size + 44x44px min touch target (WCAG compliance)

## [2.9.3]

- Added Instagram to Contact page social copy

## [2.9.2]

- Fixed Contact page copy: removed claim of being "most active on Spotify"

## [2.9.1]

- Sticky footer: fixed to bottom viewport, compacted (~75px)
- Hero calc and body padding adjusted for both fixed header and footer

## [2.9.0]

- Sticky shrinking header: fixed position, compacts on scroll
- JS uses requestAnimationFrame + passive scroll for performance
- Breeze exclusion added for sticky-header.js

## [2.8.5]

- Fixed hero height: `calc(100vh - header)` so homepage fills exactly one screen
- Uses `100dvh` fallback for mobile address bar handling

## [2.8.4]

- Redesigned Muso.AI credits section: two-column layout with bordered card CTA

## [2.8.3]

- Expanded Spotify embed height: 600px desktop, 500px tablet, 400px mobile

## [2.8.2]

- Changed About page red label from "About" to "Who I Am"

## [2.8.1]

- Swapped About page portrait to B&W studio photo

## [2.8.0]

- Added Panacea studio link on About page

## [2.7.0]

- Audit fixes: service card CSS retargeted to actual HTML structure
- About page updated with business/strategy positioning
- Generator meta tags stripped
- Skip-to-content link added for accessibility

## [2.0.0]

- Full palette inversion to match nin.com
- White backgrounds, black text, red accents
- Inverted film grain and scanline overlays (multiply blend)
- Buttons now black with red hover
- Logo auto-inverts via CSS filter

## [1.5.0]

- NIN aesthetic shift: replaced warm palette with cold/clinical colors
- Removed Ember color, Portfolio CPT, Genre taxonomy, unused gradients

## [1.4.1]

- Fixed `&amp;` encoding in theme name
- Standardized fontFamily on Music page; unified container widths
- Rewrote Contact page with cleaner form layout

## [1.4.0]

- Merged Muso.AI page into Music page as a dedicated section
- Removed standalone Muso.AI template and nav link

## [1.3.0]

- Rewrote all four Services descriptions with personality-driven copy
- Rewrote About page bio with narrative voice

## [1.2.2]

- Completed theme metadata: author name, URIs, expanded description, additional tags

## [1.2.1]

- Fixed footer year rendering; replaced shortcode block with render_block filter

## [1.2.0]

- Dynamic footer year via `[current_year]` shortcode
- Baked full optimized resume content into Resume template with PDF download

## [1.1.1]

- Wired in existing site images from media library
- Added hero, portrait, and service card CSS effects

## [1.1.0]

- Rebuilt all templates to match juanlentino.com layout
- Added Services, Music, Resume, Muso.AI, and Contact page templates

## [1.0.0]

- Initial theme scaffold with QOTSA/NIN aesthetic
- Core templates, Portfolio CPT, Bebas Neue + DM Mono typography
- Film grain/scanline overlays, industrial color palette
