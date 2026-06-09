# Delta Security Audit — Signal & Noise security-fix delta

**Date:** 2026-06-09 (follows the same-day [whole-codebase back-audit](2026-06-09-security-back-audit.md))
**Scope:** the just-shipped security-fix **delta** — theme `v9.15.2..v9.15.4` + plugin `v4.14.1..v4.14.3`. Rationale: the back-audit's completeness critic found *no coverage gaps* on the unchanged tree hours earlier, so re-scanning ~120 unchanged files would be redundant. The surface the back-audit never saw is the **fix code itself** (it ran pre-fix) — a new permission callback, an autoload migration, a length-aware mask, a `wp_parse_url` allowlist rewrite. A botched security fix is itself a vuln, and a fix's most likely miss is an *untouched sibling* of the bug it closed.
**Method:** 10 review clusters (9 fix clusters + a generalized IDOR-class sweep across every ability registration) → 3-lens adversarial verification per candidate (exploitability / existing-mitigation / reachability; a crashed/null lens ≠ refutation; split → human review) → completeness critic over the whole delta. 32 agents, ~2.0M tokens. Run via the `security-delta-audit` workflow against the current theme tree (v9.15.4) + a fresh plugin worktree at tag v4.14.3.

## Executive summary

7 candidates reached verification: **2 actionable**, 2 positive-verifications (fixes confirmed sound), 1 clean sweep result, 2 correctly dropped.

The headline is a **completeness-critic catch**: the v9.15.4 diagnostics-oracle fix has an **untouched sibling** — `get-reading-time-for-slug` (theme `inc/abilities-content.php`) is the same existence-oracle class, fixable in-theme exactly as the diagnostics ability was. **Fixed under TDD → theme v9.15.5.** The plugin's `login-hide` query-string fix likewise left a path-substring sibling + a raw-`REQUEST_URI` decoy anchor → **plugin v4.14.4** (login-hide anchoring).

A methodological note worth keeping: two verifier lenses "confirmed" a finding (P4-2) on a **factually false premise** (claiming the test stub's `parse_url` diverges from WP's `wp_parse_url` on `//`-prefixed URIs — it does not; both return the same `PHP_URL_PATH`). The lone "refuted" lens did the correct simulation. **Adjudication must reason over the lenses, not tally them** — see [[feedback_workflow_verify_crash_not_refute]] generalized: a confident majority can be wrong.

---

## Actionable findings

### ✅ LOW — Existence/length oracle: `get-reading-time-for-slug` (theme `inc/abilities-content.php`)
**FIXED — theme v9.15.5 (`claude/reading-time-oracle`), under TDD.**

- **Source→Sink:** subscriber-callable ability (`permission_callback => sn_theme_perm_read` = `current_user_can('read')`) takes a caller-supplied `slug` → wrapper `sn_notes_reading_time_for_slug()` → plugin `[sn_reading_time]` shortcode → `get_page_by_path($slug)` (post_type `page`, **no status filter**) → real reading-time minutes for any-status page; a non-resolving slug returns the wrapper's `'5 min'` fallback (→ `minutes=5`).
- **Why real:** a subscriber enumerating slugs distinguishes "a draft/private page with this slug exists" (real minutes, a coarse length proxy) from "no such slug" (exactly 5). Same class the v9.15.4 fix closed for `get-active-template-structure`; the cluster reviews missed it because the resolver lives in the companion plugin — the *gate* that is too weak is the theme's own `permission_callback` + unconditional return, both in-theme and fixable in-theme.
- **Fix shipped:** resolve the page in-theme (`get_page_by_path($slug, OBJECT, 'page')`), gate on `is_post_publicly_viewable() || current_user_can('read_post', $id)`, else return a uniform `minutes=0` — indistinguishable from a missing slug. Behavioral regression test: subscriber gets `0` on a draft *and* a missing slug; a `read_post`-authorized user still gets the real time; a public page is unchanged. (Also fixes a doc-drift: the registration already claimed "minutes=0 if the slug does not resolve.")

### 🔧 LOW — `login-hide` allowlist substring + decoy anchor (plugin `inc/login-hide.php`)
**QUEUED — plugin v4.14.4.**

- **P4-1 (sibling of the v4.14.2 fix):** the v4.14.2 fix narrowed the allowlist match from the whole `REQUEST_URI` to the parsed `PATH` (closing `/wp-admin/?x=/feed`), but kept a **substring** test over broad needles (`/feed`, `admin-ajax.php`, …). `GET /wp-admin/feed` (path *contains* `/feed`) is still allowlisted → skips the unauth `/wp-admin` decoy-404. INFO/LOW existence-oracle hygiene, not an auth bypass (core still enforces auth). Fix: anchor the needles — basename/`str_ends_with` for the file needles, a `/feed` segment/suffix check.
- **P4-2 residual (after discarding the false stub-divergence claim):** the Branch-3 decoy check uses `strpos($request_uri, '/wp-admin') === 0` on the **raw** URI, so `//wp-admin/...` (slug at offset 1) skips the decoy. Fix: anchor Branch-3 on the parsed path (`'/wp-admin' === $path || str_starts_with($path, '/wp-admin/')`).

---

## Positive verifications (no action — audit trail)

- **v9.15.3 IDOR fix verified sound against upstream.** The fix's load-bearing assumption — that `WP_Ability` passes `$input` to `permission_callback` — was verified against `WordPress/abilities-api` trunk: `invoke_callback()` passes `$input` only when `get_input_schema()` is non-empty, and the ability *has* a non-empty schema (`required: ['post_id']`). Dispatch order `validate_input → check_permissions($input) → do_execute` means `post_id` is validated, the per-post `edit_post` cap fires, *then* the body is read. IDOR genuinely closed; no new bug on null/missing/0 input.
- **Generalized IDOR sweep — no untouched siblings.** Every resource-id-consuming ability across both repos (58 total) gates on a per-resource cap matching the id its `execute_callback` operates on; the destructive `ai-orphan-apply` correctly uses `delete_post` on its `attachment_id`. `manage_options`-gated abilities take no per-user resource id.
- The 7 other fix clusters (mask ×4, webhooks autoload + migration, SSRF `redirection=>0`, `JSON_HEX_TAG`, discography `roles[]`, plausible-widget `wp_kses_post`) each verified correct, complete, and regression-free.

## Dropped (3-lens refuted)

- **P5 music JSON-LD sibling** (`seo-schema-music.php`) — relies on implicit slash-escaping not `JSON_HEX_TAG`; refuted (no reachable breakout; the fragile-if-changed note is hygiene, not a finding).
- **`pattern-adoption-dismiss` takes post_id but gates `manage_options`** — refuted (admins can edit any post; no IDOR).

---

## Recommended next actions
1. ✅ **Reading-time oracle — DONE** (theme v9.15.5, this branch).
2. 🔧 **login-hide anchoring** (plugin v4.14.4): anchor the allowlist needles + anchor the Branch-3 decoy check on the parsed path; add a `/wp-admin/feed` non-allowlist assertion.
3. ℹ️ Optional forward-reference: the plugin `[sn_reading_time]` shortcode resolver (`reading-time.php`) uses `get_page_by_path` with no status filter; the exposed surface (the theme ability) is now gated, but a status filter on the resolver would be defense-in-depth if the shortcode ever takes untrusted slugs elsewhere.
