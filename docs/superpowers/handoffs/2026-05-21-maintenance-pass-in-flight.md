# Handoff — 2026-05-21 (maintenance pass in flight; Waves 1 + 2 shipped)

**Hand off because:** Major architectural improvements landed this session (v3.7.1 / v3.7.2 / v3.7.3), context is filling, and the maintenance pass has 5 more waves to go. Fresh-context restart point.

---

## TL;DR — production state

| Surface | Version | Commit | Live? |
|---|---|---|---|
| **Plugin** | **v3.7.3** | [`4e5addd`](https://github.com/juanlentino/signal-and-noise-tools/commit/4e5addd) | ✅ Fully green deploy (first plugin run with ALL 4 steps ✅) |
| **Theme** | v9.0.0 | unchanged | ✅ |

**Test grid (post-v3.7.3):** 6 suites, 441 total assertions, all green.
**Wave 3 in flight:** subagent writing `tests/ai-bootstrap.php` (no version bump; tests don't ship).

---

## Session arc — what happened (4-release sprint in one session)

### v3.7.0 → v3.7.1: The AI gate bug

After v3.7.0 (drift detection) shipped, the user clicked the Insights "Run Analysis" button and saw "AI client not available" — exactly the prior handoff's item #1 prediction. Initial hypothesis-driven guessing (model selection, cache, Connector Approval, parsing differences) was all wrong. User redirected: *"You'd read the documentation on this before doing anything."*

Reading [wp-ai-client/includes/Builders/Prompt_Builder.php](https://github.com/WordPress/wp-ai-client/blob/trunk/includes/Builders/Prompt_Builder.php) revealed: **the parent class declares only `__construct`, `using_abilities`, and `__call`. Every snake_case method (`using_temperature`, `is_supported_for_text_generation`, etc.) is `__call`-routed magic dispatch.** PHP's `method_exists()` cannot detect `__call`-routed methods — only `is_callable()` can.

Our `inc/ai-bootstrap.php` had a guard:
```php
if ( ! method_exists( $builder, 'is_supported_for_text_generation' ) ) {
    return false;
}
```

That guard **always returned false on every install since v2.5.0** (2026-05-17). Six months of SN AI features have been silently no-op'ing — Insights, drift detection, meta description, excerpt, OG card title, alt-text. Tests passed because every test mocks `snt_ai_is_available()` and doesn't exercise the actual gate function.

**v3.7.1 fix** ([`793ed81`](https://github.com/juanlentino/signal-and-noise-tools/commit/793ed81)): removed the guard. Try/catch already handles "method missing" via `BadMethodCallException`. Verified live: SN's first-ever AI Request Log entry attributed to `signal-and-noise-tools (plugin)` fired immediately after.

### v3.7.1 → v3.7.2: The Sonnet pin

First successful SN AI call was an Insights scan at **4.9K tokens using `claude-opus-4-7`**. The v3.6.0 plan budgeted ~$0.01/scan assuming Sonnet; Opus is 5x the cost, putting actual at ~$0.10/scan — 10x over plan.

Root cause: `snt_ai_generate_with_constraints()` didn't pin a model preference. The WP AI Client routes to the Anthropic provider's default (most-capable = Opus). The `ai/ai` plugin's editor features pin Sonnet explicitly via their own builder chains.

**v3.7.2 fix** ([`4544ff7`](https://github.com/juanlentino/signal-and-noise-tools/commit/4544ff7)): added `->using_model_preference('claude-sonnet-4-6')` to the builder chain + `snt_ai_model_preference` filter for per-feature overrides. Restored plan budget (~$0.02/scan, ~$1/year weekly).

Also in v3.7.2: plugin deploy.yml CF purge step got the theme's `continue-on-error: true` pattern (handoff item #4), plus a cosmetic comment fix (handoff item #20).

### v3.7.2 → v3.7.3: Eliminate the App Password entirely

User pushed back on the App Password rotation flow: *"We need to find a way to get the App Password some other way, even if it can be rotated automatically."*

Reading SN's purge architecture: the REST endpoint at `/wp-json/signal-noise/v1/purge-cache` is just a thin auth + dispatch wrapper around the theme's `sn_purge_all_caches_result` filter (at [`inc/template-maintenance.php:187`](https://github.com/juanlentino/signal-and-noise/blob/main/inc/template-maintenance.php)). The actual purge work (object cache + Breeze + Varnish + Cloudflare) lives in the theme. Admin form, desktop-mode commands, abilities API all call the same filter.

**v3.7.3 fix** ([`4e5addd`](https://github.com/juanlentino/signal-and-noise-tools/commit/4e5addd)): replaced HTTP Basic Auth purge step with `ssh ... wp eval` calling the filter in-process. The deploy SSH key (already authorized for git checkout) is the only auth needed.

Eliminated: `WP_DEPLOY_USER` + `WP_DEPLOY_APP_PASSWORD` GH secrets (referenced nowhere now), HTTP Basic Auth path, the entire 401/403 `sn_rest_forbidden` failure mode, manual password rotation forever.

The user pasted the App Password in chat to facilitate the rotation — that password is now compromised but irrelevant since v3.7.3 doesn't use it. Recommended (not yet done): delete both GH secrets + revoke the App Password in wp-admin for hygiene.

---

## Maintenance pass — waves

User's stated scope: *"QA, bugfixes, UI, UX, cleanup, all we can do."*

| Wave | Scope | Status |
|---|---|---|
| **1 — Cost + deploy hygiene** | Pin Sonnet, deploy.yml continue-on-error, comment fix | ✅ v3.7.2 |
| **2 — Deploy auth** | Diagnose 401, rotate, then eliminate App Password | ✅ v3.7.3 |
| **3 — Test coverage** | `tests/ai-bootstrap.php` integration tests | 🟡 in flight (subagent) |
| **4 — Code quality + doc fixes** | Prior-handoff items #6, #7, #8 + v3.7.x lessons in WORDPRESS-REFERENCE.md | ⏸️ pending |
| **5 — Security review** | Prior-handoff items #10–13 (gh secret pattern, live secret scan, REST + abilities review) | ⏸️ pending |
| **6 — UI/UX polish** | Better "AI gate failure" diagnostic surfaces; loading + empty states across tabs | ⏸️ pending |
| **7 — Cleanup** | Pre-WP-7.0 stale code; dead/unused functions; stale TODOs | ⏸️ pending |

**Out of scope (memory rule):** Dark mode. Do NOT add — Signal & Noise is intentionally white-first brutalist per design.

---

## Wave 3 — what the subagent is doing

Creating `tests/ai-bootstrap.php` (standalone PHP harness matching `tests/health-checks.php` style). 14 test scenarios:

**Lock in v3.7.1 (method_exists removal):**
1. `function_exists('wp_ai_client_prompt')` returns false → gate returns false
2. Builder is null → gate returns false
3. `is_supported_for_text_generation()` via `__call` returns true → gate returns true **(the test that would have caught the v3.7.1 bug)**
4. Same returns false → gate returns false
5. Builder construct throws → caught, returns false
6. `is_supported_for_text_generation()` throws → caught, returns false

**Lock in v3.7.2 (Sonnet pin):**
7. Gate false → returns `WP_Error('snt_ai_unavailable')` with status 503
8. Happy path → returns trimmed string
9. Builder returns WP_Error → propagates
10. Builder returns empty string → `WP_Error('snt_ai_empty_response')` 502
11. Builder throws → `WP_Error('snt_ai_runtime_error')` 500
12. **Builder chain records `using_model_preference('claude-sonnet-4-6')` before `generate_text`** (the v3.7.2 lock-in)
13. `snt_ai_model_preference` filter override works
14. Max tokens clamped to [1, 4096]

Mock: `TestAiBuilder` class with `__call` magic dispatch — mirrors the real `wp-ai-client` Prompt_Builder behavior exactly. The `method_exists` vs `is_callable` asymmetry the v3.7.1 fix corrected is the same asymmetry the mock exhibits.

Expected commit: test-only, no version bump, plugin's Version stays at 3.7.3.

---

## Remaining waves — what's queued

### Wave 4 — Code quality + doc fixes (~45 min, mostly inline)

From prior handoff:
- **Item #6:** CHANGELOG version-count precision (estimated vs. actual; minor doc fix)
- **Item #7:** v3.6.0 plan doc Task 1 step 5 — `insights-admin.php` require_once that would fatal at Task 1 (one-line plan-doc edit)
- **Item #8:** Tiebreak ordering test (already shipped in `9f4e46d`; just docs)
- **New from this session:** Update `docs/WORDPRESS-REFERENCE.md` with the v3.7.1 lesson — when integrating with libraries that use `@method` PHPDoc + `__call` dispatch, use `is_callable` not `method_exists`. Also document the `wp eval` over SSH purge pattern (v3.7.3) for future deploy refactors.

### Wave 5 — Security review (~30 min, mostly grep)

From prior handoff:
- **Item #10:** Grep `--body` flag in any `gh secret set` calls (per memory `feedback_gh_secret_set_stdin`)
- **Item #11:** Grep `docs/` in both repos for any pasted live secrets that slipped in (per memory `feedback_never_inline_live_secrets_in_plan_docs`)
- **Item #12:** Insights REST endpoints — input validation review on `POST /signal-noise/v1/insights/run` + `GET /signal-noise/v1/insights/last`. Confirm `sn_rest_can_manage` is consistently applied.
- **Item #13:** Insights abilities API annotations — verify `idempotent: true` + `open_world_hint: false` are correctly applied for AI Copilot callability per memory `reference_desktop_mode_ai_copilot`.
- **New:** Audit the SSH+wp-eval purge pattern (v3.7.3) for command injection vectors. The `apply_filters` call is a static string, so direct injection is impossible, but worth a confirmatory pass.

### Wave 6 — UI/UX polish (~1-2 hours)

Open questions:
- The "AI client not available" warning in Insights still renders with the v3.6.1 helper text pointing at Settings → AI + Settings → Connectors. Now that we know the real failure modes (was always a gate bug, not config), should the helper text be revised? Or removed entirely since the gate now works correctly?
- Loading states during scans (Insights, Drift) — currently no visible loading indicator on the button click.
- Empty states across tabs — what does it look like before first scan?
- The SN Dashboard "Recent deploys" widget now correctly shows green for v3.7.2 + v3.7.3. The widget itself could surface MORE info — e.g., per-step status (SSH ✅, purge ✅) instead of just overall conclusion.

Per memory `feedback_no_dashboard_widgets`: do NOT propose WP admin dashboard widgets — operational info goes in SN's own settings tabs.

### Wave 7 — Cleanup (~30 min - 2 hours depending on scope)

- Grep for stale TODO/FIXME markers (the v3.7.0 audit-related ones should be removed since they're addressed)
- Dead code from pre-WP-7.0 era — anything that branches on `function_exists('wp_ai_client_prompt')` could be simplified since WP 7.0 is GA'd
- Unused imports / dead functions

---

## Process discipline outcomes from this session

### What worked

- **The user redirected once, productively.** *"You'd read the documentation on this before doing anything"* → triggered systematic-debugging skill + actual upstream source reads → root cause in 5 minutes. Memory file `feedback_read_framework_source` paid for itself.
- **Subagent-driven execution kept commit hygiene clean.** v3.7.0 arc shipped 5 commits with 0 force-pushes, 0 amends. Each release commit has a meaningful message + diagnostic provenance.
- **`continue-on-error: true` + `::warning::` annotations** in deploy.yml shipped exactly when needed. Wave 2 (v3.7.3) confirmed the value: even after eliminating the underlying 401 cause, the pattern stays in place as defense-in-depth.

### What broke / lessons

- **The v3.7.1 bug shipped because we never integration-tested the function that decides whether anything works.** All existing tests mock `snt_ai_is_available()`. Six months of features were silently no-op'ing. Wave 3 directly closes this gap.
- **Hypothesis-driven debugging without source reading wasted time.** Four messages of guessing (Connector Approval, model selection, cache, parsing differences) before reading the actual `Prompt_Builder.php`. The 5-line PHP one-liner that proved `method_exists` returns false for `__call`-routed methods was the entire diagnosis.
- **CI failure UX vs. actual deploy state.** The deploy.yml red-runs trained us to ignore failures for months. v3.7.2's `continue-on-error` patch + diagnostic warnings closes that loop.

### Memory updates worth making (TODO for cleanup phase)

- Reinforce `feedback_read_framework_source` with the v3.7.1 incident as evidence
- New memory: `feedback_method_exists_vs_is_callable` — when libraries use `@method` PHPDoc + `__call` dispatch, `method_exists` is the wrong probe; use `is_callable`
- New memory: `architecture_eliminate_credentials_before_rotating` — when designing a rotation system, ask first whether the credential needs to exist

---

## Where to pick this up next session

### Recommended sequence

1. **Read this handoff** + skim `2026-05-21-post-v3.7.0-shipped-handoff.md` for the 17-item prior backlog (most items addressed by v3.7.x; remaining tracked in Waves 4-7 above).

2. **Check Wave 3 status** — if the subagent completed, the commit will be on `main` (no push). If not yet, you can wait for it or kick off a fresh subagent dispatch from the brief above.

3. **Cleanup of unused GH secrets** (~1 min):
   ```bash
   gh secret delete WP_DEPLOY_USER --repo juanlentino/signal-and-noise-tools
   gh secret delete WP_DEPLOY_APP_PASSWORD --repo juanlentino/signal-and-noise-tools
   ```
   Then wp-admin → Users → Profile → revoke the leaked App Password.

4. **Continue Waves 4 → 5 → 6 → 7** in that order. Each is independent of the others; can be parallelized via subagents if context budget allows.

### Key files map (delta from prior handoff)

| Plugin path | Status |
|---|---|
| `inc/ai-bootstrap.php` | v3.7.1 + v3.7.2 patches landed; Wave 3 tests pending |
| `.github/workflows/deploy.yml` | v3.7.2 (continue-on-error) + v3.7.3 (SSH+wp-eval purge) |
| `tests/ai-bootstrap.php` | **Being written by Wave 3 subagent** |
| `CHANGELOG.md` | Three entries added this session (v3.7.1, v3.7.2, v3.7.3) |
| `signal-and-noise-tools.php` | Version: 3.7.3 |

### Memory entries that matter for resume

- [`feedback_read_framework_source`](../../../.claude/projects/-Users-juanlentino-Projects-signal-and-noise/memory/feedback_read_framework_source.md) — paid off in v3.7.1 diagnosis; reinforce in Wave 7
- [`feedback_skills_plugins_docs_always`](../../../.claude/projects/-Users-juanlentino-Projects-signal-and-noise/memory/feedback_skills_plugins_docs_always.md) — held cleanly through the session
- [`feedback_never_inline_live_secrets_in_plan_docs`](../../../.claude/projects/-Users-juanlentino-Projects-signal-and-noise/memory/feedback_never_inline_live_secrets_in_plan_docs.md) — partially violated when user pasted password in chat; v3.7.3 eliminates the credential entirely so it's moot going forward
- [`feedback_gh_secret_set_stdin`](../../../.claude/projects/-Users-juanlentino-Projects-signal-and-noise/memory/feedback_gh_secret_set_stdin.md) — held (Wave 2's `gh secret set` used `echo -n ... |` correctly)
- [`feedback_versioning_patch_cap`](../../../.claude/projects/-Users-juanlentino-Projects-signal-and-noise/memory/feedback_versioning_patch_cap.md) — v3.7.0 → v3.7.1 → v3.7.2 → v3.7.3 = 3 patches used; 4 remaining in the 7-patch cap before rolling to v3.8.0

---

## One-line summary

**Wave 1+2 of the maintenance pass shipped as v3.7.2 + v3.7.3. AI gate fixed (v3.7.1), cost overage fixed (v3.7.2 — Sonnet pinned), App Password eliminated entirely (v3.7.3 — SSH+wp-eval purge). Wave 3 (AI bootstrap tests) in flight via subagent. Waves 4-7 (docs, security, UX, cleanup) queued.**
