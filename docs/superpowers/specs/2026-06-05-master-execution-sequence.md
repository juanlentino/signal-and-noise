# Master Execution Sequence — Signal & Noise (theme + plugin)

**Date:** 2026-06-05
**Status:** Active sequencing reference. Derived from a full-inventory pass (7 agents) that read every plan/stub/handoff in both repos **and verified each item against live code**.
**Live state at authoring:** theme **v9.5.2**, plugin **v4.5.6**. Theme abilities = 12, plugin = 34 distinct slugs. CI/phpcs shipped in both repos.
**Current state (updated 2026-06-05, post-v9.8.0):** theme **v9.8.0**, plugin **v4.5.8**. Shipped since authoring: theme v9.6.0 (R1) + v9.7.0 (search) + v9.8.0 (Notes-scoped search & archive); plugin v4.5.7→v4.5.8. All four frontier-candidate #1 work is DONE. See the Shipped log at the bottom.

> This document reconciles four artifact types (specs, plans, stubs, handoff-deferred items) across both repos into one dependency-ordered order. It supersedes ad-hoc sequencing scattered across handoffs. When an item ships, mark it and move the line to the "Shipped from this sequence" log at the bottom.

---

## The sequence (dependency-ordered)

| # | What | Repo | Target | Status | Size |
|---|------|------|--------|--------|------|
| ~~1~~ | `/notes` pagination **R1** — helpers + `paginate_links()` control + count fix + tests | theme | **v9.6.0** | ✅ **SHIPPED 2026-06-05** (tag `v9.6.0`, commit `b111f1c`) | M |
| 2 | `/notes` **R2** — `sn_notes_per_page` setting + Identity&SEO section refactor + paged SEO | plugin | TBD (own minor vs fold into v4.6.0) | backlog — needs BC + plan | M |
| ~~3~~ | **Pre-cycle gate** — verify WP 7.0 on Cloudways + reconcile v4.6.0 plan drift | both | pre-BC | ✅ **DONE 2026-06-05** — WP 7.0 confirmed live; drift reconciled into both plugin plans (`e9488c9`) | XS |
| ~~4~~ | **Plugin v4.6.0** — 6 abilities, WP<7.0 notice, `@deprecated` PHPdoc | plugin | v4.6.0 | ✅ **SHIPPED 2026-06-06** (`57dbe39`, tag `v4.6.0`). NOTE: the WS7 rate-monitor split + deferred-backlog fold-ins were NOT in the plan's task breakdown → still deferred to v4.6.x | L |
| 5 | Plugin **v4.6.x** — post-ship audit + LOW sweeps (+ optional `/sn-login` hardening) | plugin | v4.6.x | gated on #4 | M |
| ~~6~~ | **Theme prep-minor** — `get-latest-theme-tag` ability + WP<7.0 notice + announce v10 | theme | **v9.9.0** | ✅ **SHIPPED 2026-06-06** (`8bba8db`, tag `v9.9.0`) | S |
| 7 | Theme **v9.7.x** — post-ship patches; gate stable before v10 BC | theme | v9.7.x | gated on #6 | S |
| ~~8~~ | *Optional* feature minors — ✅ **v4.7.0 admin-bar** (`af4900b`) + ✅ **v4.8.0 post-editor consolidation SHIPPED 2026-06-06** (`aa860de`, tag `v4.8.0`); + frontier picks below | both | v4.7.0 + v4.8.0 done | mostly shipped | M |
| 9 | **v5.0.0 BC** — refresh tentative plan; resolve the Task 7 contradiction | plugin | pre-v5 | gated | XS |
| 10 | **Plugin v5.0.0** — WP 7.0 hard-raise + 10 REST removals + JS→Ability flip + orphan-option | plugin | v5.0.0 | gated; **real driver** | L |
| 11 | **Theme v10.0.0** — WP 7.0 floor-raise (policy major) | theme | v10.0.0 | gated; **no code driver** | M |
| 12 | Far-parked — Desktop Mode windows, Mimestream notes, content-migrations retire | both | post-v5 | gate unmet | L |

### Gating truths (do NOT sequence on counter math)
- **v10.0.0 has no code-level driver** — v9.5.0 CHANGELOG + scope audit = 79 KEEP / 0 REMOVE. It is actionable *only* as the deliberate WP-7.0 floor-raise paired with v5.0.0.
- **v5.0.0 IS a genuine major** — 4 real breaking drivers (WP 7.0 floor, 10 REST removals, JS flip, orphan-option removal).
- The whole major chain is **plugin-first** and gated on **WP 7.0 certified on Cloudways** (unverified; cheap + blocking — do at #3).

---

## Version-collision resolution (DECIDED 2026-06-05)

`/notes` R1 **and** the prep-minor both claimed `v9.6.0`. Resolution:
- **R1 ships as v9.6.0** (shipped).
- **The prep-minor renumbers to v9.9.0** (updated 2026-06-05). It was bumped to v9.7.0, but v9.7.0 went to on-site search and **v9.8.0 to Notes-scoped search & archive**, so the next free minor is **v9.9.0**. Rebase the plan `docs/superpowers/plans/2026-05-27-v9.6.0.md` to v9.9.0 when the prep-minor cycle opens (after plugin v4.6.0 stable). Recorded here so the duplicate number never reaches a tag.

---

## Frontier candidates COMMITTED to the roadmap (chosen 2026-06-05)

All four constraint-checked against the documented don't-propose list (no new dashboard widgets / top-level admin-bar nodes; no brutalist wp-admin; not an `ai/ai` or desktop-mode duplicate; dark mode stays omitted). Each gets its own brainstorm→plan **after R1**.

1. ~~**On-site search**~~ — ✅ **DONE.** Shipped as theme v9.7.0 (grouped Notes+Pages results template), then **reworked in v9.8.0 into a Notes-scoped search & archive built into `/notes`** (per user re-decision: out of the header, Notes-only, no Pages). The standalone `templates/search.html` + `inc/search-query.php` were deleted in v9.8.0. Live-verified.
2. **In-article TOC + reading-progress rail** — auto-built from `h2/h3` + thin scroll rail honoring `prefers-reduced-motion`, for long-form notes. ‹theme, minor, M›
3. **Helpful 404 recovery** — latest-notes Query Loop + optional fuzzy-slug 301 (`template_redirect` Levenshtein vs published `post_name`s) instead of the static dead-end. ‹both, minor, S›
4. **IndexNow instant indexing** — ping Bing/Yandex on publish/update; key-file verified, **no credential to rotate**; reuses the webhooks delivery-log pattern; honors `_sn_noindex`. ‹plugin, minor, M›

### Other surfaced candidates (NOT committed — parked for later consideration)
Enrich Article JSON-LD (`timeRequired`/`wordCount`/`keywords`, patch); print/save-as-PDF stylesheet (no `@media print` exists); JSON Feed 1.1 endpoint; post-deploy smoke-check for the `/notes` renderer (admin-bar sub-item + read-only ability); series/reading-path nav; ETag/304 for OG PNGs + RSS.

### 📋 Full sequenced opportunities backlog (2026-06-06)
Two ultracode multi-agent sweeps (code-first + handbook-driven, adversarially verified, token-frugal-AI filtered) → **~42 distinct opportunities sequenced into patches → minors → majors**. This supersedes the ad-hoc "Other surfaced candidates" line above as the master backlog. **Doc:** `docs/superpowers/specs/2026-06-06-upgrade-opportunities-roadmap.md`. Highlights — Track A patches: Breeze cache-rollover bug-fix, schema.org enrichments, WCAG aria-current. Track B minors: CF security-header drift check, native Site Health cron test, pre-publish mistake gate, body-grounded Insights advisor, Related Notes footer, print stylesheet. Track C majors: the cleanup chain + the frugal-AI content-intelligence flagship (+ Plausible seed).

### ⭐ Flagship candidate for the v5.0.0 + v10.0.0 major (seeded 2026-06-06)
**Plausible content-intelligence layer** — the major is currently scoped *cleanup-only*; this is its marquee-feature candidate. Sizing pass found SN uses a sliver of Plausible CE (v1 API, `7d` only, 2 breakdowns) vs the full v2 query surface (timeseries, arbitrary filters, ~15 unused dimensions incl. custom props, `scroll_depth`/`time_on_page`/`conversion_rate`, and **zero** goals/conversions used). Theme consumes Plausible data nowhere → a front-end "read-next/popular" surface is the v10-visible piece. 5 directions + the enabling v1→v2 client refactor in the seed. **Seed (substantive):** plugin repo `docs/superpowers/specs/2026-06-06-plausible-content-intelligence-seed.md`. Deep design → the paired-major brainstorm.

---

## Drift / risks to reconcile at the v4.6.0 BC (found vs live code)

**A0 reconcile pass ran 2026-06-05 — items 1–5 fixed in the plugin plans (`e9488c9`); item 6 carries into the R2 plan.**

1. ✅ **RESOLVED — Ability-count baseline.** Live registers **34** distinct slugs (verified both grep patterns); the `abilities-registration.php` docblock undercounts at 30 (omits `abilities-block-migrations.php`'s 4 slugs). v4.6.0 plan corrected: count work is **34 → 40**, G6 gate target **40**, and the docblock fix (correct total + add the block-migrations line) is now an explicit task step.
2. ✅ **RESOLVED — baseline version.** Live is now **v4.5.8** (plan said v4.5.1). Recorded in the v4.6.0 A0 addendum; rebase task line-refs against v4.5.8 at BC.
3. ✅ **RESOLVED — no `readme.txt`.** Confirmed absent. The "bump Stable tag in readme.txt" row was dropped from the v4.6.0 file table (16→15 files); Stable-tag lives in `signal-and-noise-tools.php` + CHANGELOG.md.
4. ✅ **RESOLVED — v5.0.0 Task 7 contradiction.** Confirmed real; Task 7 rescoped to warn the surviving legacy surface **`sn_admin_pages()`** (`inc/admin-legacy-redirect.php:39`), not the deleted REST handlers.
5. ✅ **RESOLVED — WP 7.0 gate.** **WP 7.0 confirmed live on Cloudways** (juanlentino.com serves `/wp-includes/…?ver=7.0`). The major chain is unblocked.
6. ⏳ **PENDING (R2) — `/notes` R2 title double-append hazard.** Theme owns `pre_get_document_title` for `/notes`; R2's paged SEO must not also append "— Page N". Single owner for the paged suffix — carry into the R2 plan when written (no R2 plan exists yet).

---

## Shipped from this sequence
- **2026-06-06 — plugin v4.8.0 (post-editor cleanup + AI prepopulation) SHIPPED** (tag `v4.8.0`, `aa860de` on `main`). Sequence step #8 now complete. (A) `inc/ai-ai-dedupe.php` disables `ai/ai`'s duplicate meta-description + excerpt panels via `wpai_feature_*_enabled` filters (also kills a latent double `<meta name=description>` emit). (B) Deferred AI prepopulation at `draft→publish/future` — empty-only auto-fill of `_sn_meta_description` + `post_excerpt` + `_sn_og_card_title` via `wp_schedule_single_event` calling the `*_impl()` functions DIRECTLY (cron has no user → the ability permission layer would block it); `concise` param (tighter prompts + ≤155-char word-boundary truncation guard) on the 3 ai-post-editor abilities; consolidated show-once notice + dismiss REST route. Built brainstorm→spec→plan→1-implementer (7 TDD tasks)→**adversarial review** (5 lenses × refute-by-default: 8 raw→6 confirmed [1 MED, 5 LOW], 2 dismissed)→fixed→final review SHIP-READY. **MED catch:** prepop's post-type gate had been coupled to the unrelated `sn_webhook_post_types` filter (over-applying "mirror the webhook guard") → a webhook CPT would've triggered AI spend with no surface for the result; fixed to `SN_POST_SETTINGS_POST_TYPES`. New `inc/ai-prepopulate.php` split into engine + `inc/ai-prepopulate-notice.php` to honor the ~150-line rule. 37 suites/1192 assertions/0 fail; phpcs 85 files/0 (falsified). Lesson: [[feedback_verify_impl_contracts_behavioral_tests]]. **v4.8.0 was the last named rung — next: reassess toward ~v4.9.0 / frontier picks / the v5.0.0 + v10.0.0 major chain.**
- **2026-06-06 — theme v9.9.0 (prep-minor for v10.0.0) SHIPPED** (tag `v9.9.0`, `8bba8db`). Sequence step #6: new `signal-and-noise/get-latest-theme-tag` ability (→13 total) + WP<7.0 pre-warning admin notice (mirrors plugin's; key `_v990`) + CHANGELOG announcing v10.0.0. Focused adversarial review: 1 finding fixed (ability annotations now `{readonly,open_world_hint,idempotent}` matching the read-ability convention). 12 suites/415/0, phpcs clean+falsified. Also fixed pre-existing readme.txt Stable-tag drift (9.7.0→9.9.0).
- **2026-06-06 — plugin v4.7.0 (admin-bar expansion) SHIPPED** (tag `v4.7.0`, `af4900b`). Sequence step #8 (partial): 3 quick-actions on the grandfathered S&N admin-bar dropdown — ↺ Force Update Check, ⌕ Scan Pattern Adoption, ⟳ Regen OG Card (contextual, `edit_post`-gated). Promoted from the stub; deferred items decided (monochrome glyphs, ordering, contextual post-id via per-node script config since admin-bar anchors drop arbitrary data-*). Scan counts `result['candidates']` not the envelope (v4.6.0 lesson). 48 behavioral assertions; **adversarial review CLEAN** (no IDOR: validate→get_post→edit_post→impl). 34 suites/1146/0, phpcs clean+falsified. No new abilities (count 40). **v4.8.0 post-editor consolidation (toward ~v4.9.0) is a FUZZY stub — needs a brainstorm before build.**
- **2026-06-06 — A0 gate + plugin v4.6.0 SHIPPED** (tag `v4.6.0`, `57dbe39` on `main`). A0: WP 7.0 confirmed live on Cloudways (core asset `?ver=7.0`) + all v4.6.0/v5.0.0 plan drift reconciled (`e9488c9`). v4.6.0 (sequence step #4): 6 new abilities (Plausible ×3, run-cron-event, pattern-adoption scan/dismiss → 40 total), WP<7.0 pre-warning admin notice, `@deprecated since 4.6.0` on 6 REST handlers (2 closure→named refactors). Built via subagent-driven-development (3 batches) in a now-removed worktree. **Adversarial audit (5 dims → refute-by-default) caught 15 confirmed (≈6 real, 2 HIGH) that 1098 green assertions missed** — dead dismiss store (wrote an option the scanner never reads), double-wrapped scan envelope, unregistered `'tools'` category (also silently broke the 4 pre-existing block-migrations abilities), strictly-weaker run-cron dispatcher; ALL fixed + 24 behavioral tests. 33 suites/1098 assertions/0 fail; phpcs clean (both falsified). **Deferred (NOT in the executed task breakdown): WS7 GitHub rate-monitor per-caller split + the deferred-backlog fold-ins → carry to v4.6.x.** Next: A3 theme prep-minor **v9.9.0** (unblocked once v4.6.0 UAT-stable) → v5.0.0 BC → plugin v5.0.0 + theme v10.0.0. Lesson: [[feedback_verify_impl_contracts_behavioral_tests]].
- **2026-06-05 — theme v9.6.0** `/notes` pagination R1 SHIPPED (tag `v9.6.0`, release commit `b111f1c`, pushed to `main`). Tasks 1–6 complete; 16 new test assertions (theme suite 361→377/9); PHPCS 0/0. Install via wp-admin → Updates.
- **2026-06-05 — theme v9.7.0 (on-site search) SHIPPED** (tag `v9.7.0`, release commit `058417d`, on `main`). Frontier candidate #1 built per spec+plan: `inc/search-query.php` (query-vars filter, 10 assertions; theme suite 377→387/10), `templates/search.html` (grouped Notes+Pages), header search icon, `components.css` styling. Install via wp-admin → Updates; live-verify `/?s=…` (filter is front-end only — Site Editor preview shows empty groups by design). Because search took v9.7.0, the prep-minor renumbers to **v9.8.0**.
- **2026-06-05 — plugin v4.5.7 (pre-major do-now batch) SHIPPED** (tag `v4.5.7`, on `main`). Items 1+2 = patch: `/sn-login` X-Robots-Tag noindex header (`inc/login-hide.php` + `tests/login-noindex-header.php`, 6 assertions) + 22-inline-style consolidation into `admin.css` (fixes the brand-red wp-admin leak → native `#2271b1`, dedups 40/20/40). Items 3+4 = docs no-bump: `sn_`/`snt_` prefix convention in theme `WORDPRESS-REFERENCE.md` §10 (commit `f48458c`) + plugin CHANGELOG Mimestream backfill of 5 legacy entries. 4 plugin commits (`4f3b796`→`a607e62`) pushed + tagged; install via wp-admin → Updates. All 31 plugin suites green; PHPCS 0/0. The bigger v4.6.0 feature build + its stale-baseline reconciliations remain parked for the v5.0.0 BC.
- **2026-06-05 — theme v9.8.0 (Notes-scoped search & archive) SHIPPED** (tag `v9.8.0`, release `220522d`, on `main`, live-verified). Resolved the v9.7.0 header "black blob" + the search-placement re-decision: reverted the header `core/search` trigger, DELETED the global posts+pages search (`templates/search.html`, `inc/search-query.php`, test, `functions.php` require), and rebuilt search as a two-state archive in `/notes` (browse + `/notes/?s=term`), **Notes-only** (`post_type=post`; Pages dropped), with a hand-rolled `<form>` (no `.wp-element-button` blob), `/?s=`→`/notes/?s=` funnel, and new helpers `sn_notes_search_term`/`sn_notes_pagination_add_args`/`sn_notes_search_redirect_target`. TDD (+17 assertions; theme suite 11/394). Adversarial-review workflow (13 findings, 6 confirmed/fixed incl. a keyboard `:focus-visible` ring gap, 7 refuted). Live-verified (DOM matrix + browser screenshots both states). Spec/plan: `2026-06-05-notes-scoped-search-archive*`. Frontier candidate #1 is now fully closed; the prep-minor renumbers v9.8.0→**v9.9.0**.
- **2026-06-05 — POST-SHIP AUDIT (12 agents, adversarially verified): 5 confirmed findings, all LOW, ZERO ship-blockers.** Verdict: cycle safe to install. Fixes applied same-day: **plugin v4.5.8** (tag, `0d9bbb0`) restores the ~8px admin-table top-inset that v4.5.7's inline-style refactor dropped (AR-01 — inline-vs-class specificity, not byte-equivalent at render; supersedes v4.5.7). Theme docs/comment fixes (no bump, commit `e8d964b`): SR-01 (documented the header-pre-render-before-`wp_head()` dependency that keeps the `/notes` search icon working), RH-01 (readme.txt → CHANGELOG.md pointer, kills the recurring Stable-tag/changelog drift), RH-02 (functions.php module-map backfill), RH-03 (CHANGELOG "5th contract" clarified as consumer-only until R2). 2 findings refuted under adversarial verification (search architecture sound).
