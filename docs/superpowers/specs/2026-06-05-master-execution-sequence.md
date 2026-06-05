# Master Execution Sequence — Signal & Noise (theme + plugin)

**Date:** 2026-06-05
**Status:** Active sequencing reference. Derived from a full-inventory pass (7 agents) that read every plan/stub/handoff in both repos **and verified each item against live code**.
**Live state at authoring:** theme **v9.5.2**, plugin **v4.5.6**. Theme abilities = 12, plugin = 34 distinct slugs. CI/phpcs shipped in both repos.

> This document reconciles four artifact types (specs, plans, stubs, handoff-deferred items) across both repos into one dependency-ordered order. It supersedes ad-hoc sequencing scattered across handoffs. When an item ships, mark it and move the line to the "Shipped from this sequence" log at the bottom.

---

## The sequence (dependency-ordered)

| # | What | Repo | Target | Status | Size |
|---|------|------|--------|--------|------|
| ~~1~~ | `/notes` pagination **R1** — helpers + `paginate_links()` control + count fix + tests | theme | **v9.6.0** | ✅ **SHIPPED 2026-06-05** (tag `v9.6.0`, commit `b111f1c`) | M |
| 2 | `/notes` **R2** — `sn_notes_per_page` setting + Identity&SEO section refactor + paged SEO | plugin | TBD (own minor vs fold into v4.6.0) | backlog — needs BC + plan | M |
| 3 | **Pre-cycle gate** — verify WP 7.0 on Cloudways + reconcile v4.6.0 plan drift | both | pre-BC | blocking, unblocked | XS |
| 4 | **Plugin v4.6.0** — 6 abilities, WP<7.0 notice, `@deprecated` PHPdoc, rate-monitor split, deferred-backlog fold-ins | plugin | v4.6.0 | plan-locked (rebase at BC) | L |
| 5 | Plugin **v4.6.x** — post-ship audit + LOW sweeps (+ optional `/sn-login` hardening) | plugin | v4.6.x | gated on #4 | M |
| 6 | **Theme v9.6.0-prep** — `get-latest-theme-tag` ability + WP<7.0 notice + announce v10 | theme | **v9.7.0** (renumbered — see collision note) | plan-locked; blocked on #4 | S |
| 7 | Theme **v9.7.x** — post-ship patches; gate stable before v10 BC | theme | v9.7.x | gated on #6 | S |
| 8 | *Optional* feature minors (v4.7.0 admin-bar, v4.8.0 AI-dedup, + frontier picks below) | both | — | PARKED unless promoted | M |
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
- **R1 ships as v9.6.0** (in-flight, unblocked, code-complete this session).
- **The prep-minor renumbers to v9.7.0.** The existing plan `docs/superpowers/plans/2026-05-27-v9.6.0.md` is to be rebased to v9.7.0 when the prep-minor cycle actually opens (after plugin v4.6.0 stable). Not rewritten now — just recorded here so the duplicate number never reaches a tag.

---

## Frontier candidates COMMITTED to the roadmap (chosen 2026-06-05)

All four constraint-checked against the documented don't-propose list (no new dashboard widgets / top-level admin-bar nodes; no brutalist wp-admin; not an `ai/ai` or desktop-mode duplicate; dark mode stays omitted). Each gets its own brainstorm→plan **after R1**.

1. **On-site search** — `templates/search.html` (verified missing today; WP silently falls back to `index.html`). **Reuses R1's `.sn-notes-pagination` control.** Highest-fit interleave. ‹theme, minor, M›
2. **In-article TOC + reading-progress rail** — auto-built from `h2/h3` + thin scroll rail honoring `prefers-reduced-motion`, for long-form notes. ‹theme, minor, M›
3. **Helpful 404 recovery** — latest-notes Query Loop + optional fuzzy-slug 301 (`template_redirect` Levenshtein vs published `post_name`s) instead of the static dead-end. ‹both, minor, S›
4. **IndexNow instant indexing** — ping Bing/Yandex on publish/update; key-file verified, **no credential to rotate**; reuses the webhooks delivery-log pattern; honors `_sn_noindex`. ‹plugin, minor, M›

### Other surfaced candidates (NOT committed — parked for later consideration)
Enrich Article JSON-LD (`timeRequired`/`wordCount`/`keywords`, patch); print/save-as-PDF stylesheet (no `@media print` exists); JSON Feed 1.1 endpoint; post-deploy smoke-check for the `/notes` renderer (admin-bar sub-item + read-only ability); series/reading-path nav; ETag/304 for OG PNGs + RSS.

---

## Drift / risks to reconcile at the v4.6.0 BC (found vs live code)

1. **Ability-count baseline stale** — v4.6.0 plan + `inc/abilities-registration.php` docblock say "30 abilities" but live registers **34 distinct slugs**. The G6 gate math (30→36) is wrong; real target ~34→40. Fix docblock + gate before executing.
2. **Plan written against v4.5.1; live is v4.5.6** — rebase the v4.6.0 plan at BC.
3. **`readme.txt` does not exist in the plugin repo** — the plan's "bump Stable tag in readme.txt" step is moot; drop or re-spec it.
4. **v5.0.0 Task 7 self-contradiction** — "promote `@deprecated` → `_deprecated_function()`" but Tasks 2+3 DELETE the 10 handlers, leaving nothing to warn. Real target is likely a different non-Ability surface (e.g. `sn_admin_pages()`). Resolve at v5.0.0 BC.
5. **WP 7.0 adoption gate unverified** — the entire major chain depends on Cloudways + juanlentino.com being on WP 7.0. Verify before the v4.6.0 BC.
6. **`/notes` R2 title double-append hazard** — theme owns `pre_get_document_title` for `/notes`; R2's paged SEO must not also append "— Page N". Single owner for the paged suffix, decided at R2 BC.

---

## Shipped from this sequence
- **2026-06-05 — theme v9.6.0** `/notes` pagination R1 SHIPPED (tag `v9.6.0`, release commit `b111f1c`, pushed to `main`). Tasks 1–6 complete; 16 new test assertions (theme suite 361→377/9); PHPCS 0/0. Install via wp-admin → Updates.
- **2026-06-05 — theme v9.7.0 (on-site search) SHIPPED** (tag `v9.7.0`, release commit `058417d`, on `main`). Frontier candidate #1 built per spec+plan: `inc/search-query.php` (query-vars filter, 10 assertions; theme suite 377→387/10), `templates/search.html` (grouped Notes+Pages), header search icon, `components.css` styling. Install via wp-admin → Updates; live-verify `/?s=…` (filter is front-end only — Site Editor preview shows empty groups by design). Because search took v9.7.0, the prep-minor renumbers to **v9.8.0**.
- **2026-06-05 — plugin v4.5.7 (pre-major do-now batch) SHIPPED** (tag `v4.5.7`, on `main`). Items 1+2 = patch: `/sn-login` X-Robots-Tag noindex header (`inc/login-hide.php` + `tests/login-noindex-header.php`, 6 assertions) + 22-inline-style consolidation into `admin.css` (fixes the brand-red wp-admin leak → native `#2271b1`, dedups 40/20/40). Items 3+4 = docs no-bump: `sn_`/`snt_` prefix convention in theme `WORDPRESS-REFERENCE.md` §10 (commit `f48458c`) + plugin CHANGELOG Mimestream backfill of 5 legacy entries. 4 plugin commits (`4f3b796`→`a607e62`) pushed + tagged; install via wp-admin → Updates. All 31 plugin suites green; PHPCS 0/0. The bigger v4.6.0 feature build + its stale-baseline reconciliations remain parked for the v5.0.0 BC.
- **2026-06-05 — POST-SHIP AUDIT (12 agents, adversarially verified): 5 confirmed findings, all LOW, ZERO ship-blockers.** Verdict: cycle safe to install. Fixes applied same-day: **plugin v4.5.8** (tag, `0d9bbb0`) restores the ~8px admin-table top-inset that v4.5.7's inline-style refactor dropped (AR-01 — inline-vs-class specificity, not byte-equivalent at render; supersedes v4.5.7). Theme docs/comment fixes (no bump, commit `e8d964b`): SR-01 (documented the header-pre-render-before-`wp_head()` dependency that keeps the `/notes` search icon working), RH-01 (readme.txt → CHANGELOG.md pointer, kills the recurring Stable-tag/changelog drift), RH-02 (functions.php module-map backfill), RH-03 (CHANGELOG "5th contract" clarified as consumer-only until R2). 2 findings refuted under adversarial verification (search architecture sound).
