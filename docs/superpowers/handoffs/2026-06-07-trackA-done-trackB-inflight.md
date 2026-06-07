# Handoff — Track A DONE, Track B in flight (v4.8.1 + v4.9.0 shipped; v4.10.0 planned) (2026-06-07)

**Read this first.** Long execution session building the **upgrade-opportunities roadmap** (`docs/superpowers/specs/2026-06-06-upgrade-opportunities-roadmap.md`). The user committed to executing it **A → B → C** (patches → minors → majors), **no majors this pass**. Paused at ~81% context mid-Track-B.

## ⭐ TOP: where to resume — build **plugin v4.10.0** (Track B bundle 2)
- **It's fully PLANNED + ready to build, NOT yet implemented.** Plan: `signal-and-noise-tools/docs/superpowers/plans/2026-06-06-v4.10.0-webhooks-privacy-perf.md` (on plugin `main` `5eed1fa`). Worktree exists: `signal-and-noise-tools/.claude/worktrees/v4.10.0` (branch `claude/v4.10.0`, off `e665bdc`, composer installed, baseline **47 suites / 1392 / 0**).
- **6 items (all grounded, ready):** multi-event webhooks (post.updated/unpublished/deleted), `list-abilities` meta-ability, Privacy exporter/eraser, suggested Privacy Policy text, audit-log CSV/JSON export, Speculation Rules tuning.
- **Resume recipe:** dispatch one implementer (TDD, commit-per-item) against the plan in that worktree → adversarial-review workflow (3 lenses × refute-by-default verify) → fix confirmed findings → ship (push `HEAD:main` + tag `v4.10.0`). **Same cycle that shipped v4.8.1 + v4.9.0.** The implementer dispatch was drafted but interrupted at the context limit.
- **Highest-risk item:** T1 webhooks refactor (widening `sn_webhook_enqueue`/`sn_webhook_dispatch` signatures — keep defensive 4-arg defaults for in-flight cron events) + T6 Speculation Rules `sn_settings_save()` perf-subtree preservation guard (whole-option-replace hazard; the test must cover it).

## What SHIPPED this session (plugin `main`, tagged)
| Release | Items | Adversarial review | Tag |
|---|---|---|---|
| **plugin v4.8.1** (Track A) | 9 — Breeze cache-rollover (gotcha #28 bug), JSON-LD enrichments (wordCount/timeRequired/keywords/section, Person image, CollectionPage ItemList, WebSite SearchAction), `og/twitter:image:alt`, sitemap trim, feed-304, a11y `aria-current` TOC | **5 fixed** incl. 1 MED — Person/publisher image wrongly per-post (must be stable) | `v4.8.1` (`70e5786`) |
| **plugin v4.9.0** (Track B/1) | 5 — CF security-header drift check, native Site Health cron async test, Site Health > Info panel, opt-in Uptime Kuma heartbeat, Heartbeat live-refresh | **7 fixed** incl. **1 HIGH false-green** — CF check `(array)`-cast a `CaseInsensitiveDictionary` (protected `$data`) → all headers always "missing", cached 6h; test passed via a `public $data` stub. Fixed to `->getAll()` + edge-bypass guard + falsifying `protected` stub | `v4.9.0` (`e665bdc`) |

**Lesson reinforced (both reviews):** green tests + clean phpcs ≠ correct. The v4.9.0 HIGH was a textbook `[[feedback_falsification_test_before_trusting_clean]]` violation — the review caught it. Keep the per-bundle adversarial-review workflow; it found a real HIGH and a real MED that TDD missed. Also `[[feedback_verify_impl_contracts_behavioral_tests]]`.

## ⚠ Pending installs (all shipped+tagged, NOT auto-deployed — wp-admin → Updates)
Plugin: **v4.6.0, v4.7.0, v4.8.0, v4.8.1, v4.9.0** (confirm which the user has installed; v4.8.1 + v4.9.0 are brand-new). Theme: **v9.9.0**. (v4.10.0 not yet built.)

## Track status (sequence LOCKED A→B→C, no majors this pass)
- **Track A — DONE.** v4.8.1 shipped (9 items). Fluid-headings resolved = **keep the hand-clamps** (the WP fluid engine can't reproduce the brutalist `vw`-proportional growth; a `clamp()` already is fluid — converting would only degrade the signature display type). The "raw-token render bug" follow-up = **not a real bug** (verified false positive; tokens live only in templates/parts + are registered shortcodes stripped everywhere). One trivial leftover: **theme.json `caption`/`cite` element styles** (ready) → fold into the first THEME minor (don't ship a 1-item theme patch).
- **Track B — in flight.** Bundle 1 (v4.9.0) shipped. Bundle 2 (**v4.10.0**) PLANNED+ready (resume above). **Still to ground+build:**
  - Plugin **"Editor UX + frugal AI" minor** (5, NOT yet grounded): body-ground the Insights advisor (frugal-AI, ~$0.42/yr), pre-publish mistake gate (`PluginPrePublishPanel`), ⌘K palette expansion, AI release-notes drafter, insights "Create draft" button.
  - **Theme minors** (NOT yet grounded): Related Notes footer (taxonomy heuristic), print/PDF stylesheet, Web Share/copy-permalink, Style Variations, brutalist block-style variations, editor block-palette curation, JSON Feed, reader ⌘K palette, `/colophon`+`/now`, "Updated DATE" line, RSS enrichment, PHP-registered sidenote/pull-quote blocks, Block Bindings, broken-link re-probe — **+ the caption/cite carryover**. (Theme work = new theme worktree off theme main; theme baseline 12 suites/415.)
- **Track C — NOT this pass** (per user). The cleanup major (v5.0.0+v10.0.0) + the frugal-AI content-intelligence flagship + the Plausible seed (`signal-and-noise-tools/docs/superpowers/specs/2026-06-06-plausible-content-intelligence-seed.md`).

## Working context / mechanics
- **Grounding is reusable:** the v4.9.0 + v4.10.0 specs came from ONE "operational hardening" grounding workflow (11 items → split 5+6). For the editor-UX + theme bundles, run a fresh grounding workflow (parallel agents → per-item implementation-ready specs) before planning — that's what made the implementers reliable.
- **Per-bundle cycle:** worktree off plugin/theme `main` → `composer install` (plugin; vendor not shared) → baseline sweep → plan → one-implementer TDD → adversarial-review workflow → fix → push `HEAD:main` + annotated tag. Exclude `contracts-smoke.php` from local sweeps (WP-only).
- **Plugin worktrees present:** v4.8.0 (old), v4.8.1, v4.9.0 (shipped — safe to `git worktree remove`), v4.10.0 (planned, keep).
- **Two workflow gotchas seen:** (1) verify-agent StructuredOutput stalls (the handbook sweep lost 24 candidates; reviews are slow — give them long timeouts, check transcript-dir activity before assuming hung). (2) the v4.10.0 implementer dispatch was interrupted at the context limit — nothing was built, only the plan exists.

## Docs to reconcile at session close (deferred — context budget)
- `docs/superpowers/specs/2026-06-05-master-execution-sequence.md` shipped-log: add v4.8.1 + v4.9.0.
- `docs/superpowers/specs/2026-06-06-upgrade-opportunities-roadmap.md`: mark Track A done + the v4.9.0 items shipped.
