# Handoff — defending-code harness installed + delta-audit shipped (theme v9.15.5 + plugin v4.14.4)

**Date:** 2026-06-09 (latest; follows `2026-06-09-info-hardening-reviewer-fix-tier2.md`)
**Status:** The prior handoff's one open engineering item — the **defending-code-reference-harness install** ("IN PROGRESS, NOT DONE") — is **DONE**. Exercising it on the security-fix delta found **two net-new sibling findings**, both fixed under TDD, shipped, merged (all CI green incl. the AI security check), and tagged: **theme v9.15.5** + **plugin v4.14.4**. **0 open PRs** in either repo; both at their tagged HEADs; worktrees clean.

## What shipped this session

### 1. Harness install — DONE (was the literal pending item)
- **Persistent dedicated clone** at `~/Projects/defending-code-reference-harness` (moved off the ephemeral `/tmp/dcrh`). git clean on `main`, 7 skills + `_lib` intact, `python3`/`checkpoint.py` verified.
- **Decision resolved on facts (not deferred):** dedicated clone, **not vendored** into the product repos — because (a) `triage`/`threat-model`/`patch` only resolve `_lib/checkpoint.py` when cwd IS the harness root; (b) the product repos already gitignore `.claude/`, so a copy would bloat the WP-update zipball invisibly; (c) the autonomous `harness/` pipeline is C/C++-ASAN only and irrelevant to PHP. We use only the read/write-only interactive skills.
- **Runbook:** [docs/SECURITY-BACK-AUDIT.md](../../SECURITY-BACK-AUDIT.md) — location, the cwd constraint, the **stale-plugin-checkout trap** (audit a fresh worktree at the shipped tag, never the v4.7.0 local tree), and the skill sequence.

### 2. Delta security audit (method validated, 2 siblings found)
Rather than re-run the 0-day-old full back-audit on ~120 unchanged files (its completeness critic found no gaps), I audited the surface that audit **never saw — the fix code itself** (it ran pre-fix): theme `v9.15.2..v9.15.4` + plugin `v4.14.1..v4.14.3`. Workflow `security-delta-audit`: 10 clusters (9 fix clusters + a generalized IDOR-class sweep) → 3-lens adversarial verify → completeness critic; 32 agents, ~2.0M tokens. Report: [docs/superpowers/audits/2026-06-09-delta-audit.md](../audits/2026-06-09-delta-audit.md).
- **Positive verifications (no action):** the v9.15.3 IDOR fix verified sound against upstream `WP_Ability` dispatch; the IDOR sweep found **no untouched siblings** (58 abilities all gate per-resource); the 7 other fix clusters verified correct/regression-free.
- **The headline catch was the completeness critic's**, not a cluster reviewer's — it traced past where the NEEDS_HUMAN lenses gave up (into the plugin resolver) to find the reading-time oracle.

### 3. theme v9.15.5 — reading-time existence oracle ([#6](https://github.com/juanlentino/signal-and-noise/pull/6), merged + tagged)
`get-reading-time-for-slug` (gated only by the `read` cap) resolved an arbitrary slug to a page of **any status** and returned its real reading time; a non-resolving slug returned `5`. A subscriber could distinguish "a draft/private page exists" (real minutes, a length proxy) from "no such slug" (5) — the **exact sibling** of the v9.15.4 diagnostics-oracle fix. Fixed in-theme by mirroring it: resolve the page, gate on `is_post_publicly_viewable() || current_user_can('read_post')`, else uniform `minutes=0`. TDD (RED on draft-leak + missing-slug → GREEN); theme suite 98→103 integration assertions, **0 fail / 29 suites**; PHPCS falsification-verified.

### 4. plugin v4.14.4 — login-hide path-substring smuggle ([#6](https://github.com/juanlentino/signal-and-noise-tools/pull/6), merged + tagged)
The v4.14.2 fix narrowed the allowlist to the parsed path but kept a **substring** test, so `/wp-admin/feed`, `/wp-admin/<x>/admin-ajax.php`, `/wp-admin/<x>/wp-json/<y>` still skipped the decoy-404. Fixed by inverting the logic — **nothing under `/wp-admin/` except admin-ajax/async-upload is a real public endpoint** — plus a shared `//`-normalizer that also path-anchors Branch-3's decoy decision (closes the `//wp-admin` evasion + stops falsely 404-ing `/wp-administrator`). TDD (2 cycles); `login-intercept.php` +13 assertions; **63 suites, 0 fail**; PHPCS falsification-verified.

## Methodology lesson written to memory
- `feedback_adjudicate_lenses_by_argument_not_tally` — a **confident MAJORITY** of verifier lenses can be factually wrong (P4-2: 2 lenses "confirmed" that the test stub's `parse_url` diverges from WP's `wp_parse_url` on `//` paths — it doesn't; the lone dissenter was right). The CONFIRMED/DROPPED label is a sort key; independently re-derive the load-bearing claim before acting. Pairs with `feedback_workflow_verify_crash_not_refute`.

## PENDING / next session
1. **Owner installs:** theme **v9.15.5** + plugin **v4.14.4** via wp-admin → Dashboard → Updates (tag pushes don't auto-deploy). These supersede v9.15.4 / v4.14.3.
2. **Draft Releases:** the tag pushes will have triggered the release-notes drafter → unpublished draft Releases for v9.15.5 / v4.14.4 (plus the earlier unpublished v9.15.3/v9.15.4/v4.14.2/v4.14.3 drafts). **Publishing is outward-facing → owner's call**; not done this session.
3. **Carried forward (unchanged, owner's call):** tier-2 **drop-bypass** hard gate (breaks direct-push); on-site `/releases` page (net-new feature — needs a brainstorm).
4. **Optional follow-up the delta audit flagged (INFO, not shipped):** the plugin `[sn_reading_time]` shortcode resolver (`inc/reading-time.php`) uses `get_page_by_path` with no status filter; the **exposed** surface (the theme ability) is now gated, so this is only defense-in-depth if that shortcode ever takes untrusted slugs elsewhere.

## State
- Both repos: 0 open PRs, at tagged HEADs (theme v9.15.5, plugin v4.14.4). My worktrees (`/tmp/snt-fix`, `/tmp/snt-audit`) removed; merged branches deleted. Pre-existing `.claude/worktrees/*` (v4.8.0…v4.13.1, anthropic-tooling) are prior/sibling sessions — left untouched. The bare plugin checkout at `~/Projects/signal-and-noise-tools` remains stale at v4.7.0 (known trap — always audit/branch off `origin/main`).
