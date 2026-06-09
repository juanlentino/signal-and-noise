# Security Back-Audit — Signal & Noise (theme v9.15.2 + plugin v4.14.1)

**Date:** 2026-06-09
**Method:** 12-dimension whole-codebase finder sweep + 3-lens adversarial verification (exploitability / existing-mitigation / reachability). Crashed/dissenting verifier ≠ refutation — split verdicts are routed to *Needs human review*, never silently dropped. Run off fresh `origin/main` worktrees; 41 agents, ~3.0M tokens.

## Executive summary

Nine candidate findings reached verification: **8 confirmed** (1 MEDIUM, 4 LOW, 3 INFO incl. one positive-verification note), **1 MEDIUM split-verdict** (adjudicated below → INFO + cheap hardening), **0 false positives**.

The headline is an **IDOR** in the theme's `ai-generate-page-note-summary` ability — a Contributor could summarize, and thereby exfiltrate, the body of any draft/private/scheduled post by enumerating `post_id`, because the ability gated on the blanket `edit_posts` cap instead of the per-post `edit_post` check used everywhere else. **This was fixed under TDD on branch `claude/security-idor` (theme v9.15.3).**

Overall posture is strong: no unauthenticated RCE/SQLi/auth-bypass; the v4.14.1 outbound-hardening pattern is consistently applied except in one peer module; the codebase's own per-post-cap and constant-lock conventions are correct everywhere checked. Every confirmed issue is a *convention drift* — the right primitive exists; one call site diverged.

## Status legend
- ✅ **FIXED** — patched + TDD-verified in this audit's branch.
- 🔧 **QUEUED** — remediation recommended, batched for the plugin patch.
- ℹ️ **INFO** — defense-in-depth / no live exploit; fold opportunistically.

---

## Confirmed findings

### ✅ MEDIUM — IDOR: `ai-generate-page-note-summary` leaks arbitrary post content (theme `inc/abilities-ai-generation.php:47`)
**FIXED — theme v9.15.3 (`claude/security-idor`).**

- **Source→Sink:** caller-supplied `$input['post_id']` (POST `/wp-abilities/v1/abilities/signal-and-noise/ai-generate-page-note-summary/run`) → `get_post((int)$post_id)` (no status/ownership check) → `snt_ai_extract_post_text($post_id,1000)` reads `post_content` of any-status post → returned as an AI summary.
- **Why real (3/3 lenses confirmed):** `permission_callback` was `sn_theme_perm_edit_posts` = bare `current_user_can('edit_posts')`, never inspecting `post_id`; no mitigating guard anywhere in the path; ability is registered, `show_in_rest=true`, no default-off toggle.
- **Attack:** an authenticated Contributor POSTs `{"post_id": <victim_draft_id>}`; iterating `post_id` harvests summaries of every draft/pending/private/scheduled post they cannot otherwise view.
- **Fix shipped:** added `sn_theme_perm_edit_post($input)` (mirrors the plugin's `snt_ability_perm_edit_post` — used by 13+ plugin abilities) doing `current_user_can('edit_post', (int)($input['post_id'] ?? 0))`, and pointed the ability at it. Regression test in `tests/abilities-integration.php` pins: Contributor denied (`rest_forbidden`, AI never invoked) on another author's draft; allowed on own. Suite 85 → 94 assertions, 0 fail.

### 🔧 LOW — Broken-links health probe follows redirects without the v4.14.1 metadata block (plugin `inc/health-checks.php:359` & `:372`)
- **Source→Sink:** author-controlled `<a href>` from published `post_content` → `wp_remote_head($url, ['redirection'=>5])` / `wp_remote_get($url, ['redirection'=>5])`. First-hop host filter constrains the original URL only; up to 5 redirects then followed with no re-validation.
- **Why real (3/3, conditional):** a same-host open redirect → `30x Location: http://169.254.169.254/...` is followed to cloud-metadata; the status code lands in the finding note (blind/limited SSRF). LOW because the same-host-open-redirect precondition isn't satisfiable from this plugin alone, and the trigger is admin-only (nonce-gated Health scan, 24h cached).
- **Fix (queued):** set `'redirection' => 0` on both calls to match the four v4.14.1-hardened peers (`webhooks`/`uptime-heartbeat`/`plausible-api`/`rss-plausible-tracker`), or add the explicit `wp_http_validate_url` + `169.254.0.0/16` reject.

### 🔧 LOW — Webhook HMAC signing secrets stored in an autoloaded option (plugin `inc/webhooks.php:169`, `:213`, `:231`)
- **Why real (3/3, conditional):** `update_option(SN_WEBHOOKS_OPTION, $all)` defaults `autoload=true`, so every per-webhook 48-char HMAC secret loads into `wp_load_alloptions()` on **every** request, though only needed admin/cron-side. The plugin's own convention passes `autoload=false` for every other credential (Plausible/CF/Spotify/Muso tokens + this module's own log option) — the config option is the outlier. Exploit needs a separate alloptions-disclosure bug (→ LOW), but a held secret forges a valid `X-SN-Signature`.
- **Fix (queued):** `update_option(SN_WEBHOOKS_OPTION, $all, false)` in create/update/delete + a one-time migration re-saving the existing option non-autoloaded. Read only in admin/cron, so zero functional cost.

### 🔧 LOW — Masked credential reveals the whole value for short secrets (plugin `inc/admin-forms/music.php:30`; siblings `cloudflare-purge.php:233`, `plausible-admin.php:135`, `webhooks-admin.php:110`)
- **Why real (3/3):** mask is unconditionally `'••••' . substr($value,-4)`; for a stored secret ≤4 chars, `substr($v,-4)` returns the **whole** value into an `<input value>`. The last-4 reveal also discloses a real fragment of every token. `manage_options` bounds the audience to admins but doesn't suppress the render. Realistic exposure: a mis-pasted/short Spotify Client Secret or Plausible Stats key; shoulder-surf / screen-share.
- **Fix (queued):** length-aware mask — `strlen($v) <= 8 ? str_repeat('•', strlen($v)) : '••••'.substr($v,-4)` — applied to all four sites.

### 🔧 LOW — Login-hide allowlist uses substring matching (plugin `inc/login-hide.php:264`, also `:159-166`)
- **Why real (3/3):** allowlist needles (`admin-ajax.php`, `/feed`, …) matched with `strpos()` over the **entire** `REQUEST_URI` incl. query string. `GET /wp-admin/?x=/feed` matches `/feed` → early return **before** the unauth-`/wp-admin` decoy-404, falling through to WP's normal login redirect → confirms the install is WordPress where the bare path would 404. Weakens the obscurity layer; **not** an auth bypass (core still enforces auth).
- **Fix (queued):** match `wp_parse_url($request_uri, PHP_URL_PATH)` with prefix/exact comparison (reuse the parsed path already at `:178-179`); same for the `plugins_loaded` intercept.

### ℹ️ INFO — `get-active-template-structure` existence oracle (theme `inc/abilities-diagnostics.php:38`)
Read-cap ability resolves arbitrary `post_id`/`slug` ignoring status → a 200-vs-404 existence + page-vs-non-page oracle for any logged-in subscriber. **No post content leaks** — only `get_block_template()->content` (theme file) is returned. Optional hardening: `is_post_publicly_viewable()` / `current_user_can('read_post', …)` check after resolution.

### ℹ️ INFO — `roles[]` not tag-sanitized on discography store-write (plugin `inc/discography-store.php:75`)
Muso-API `roles[]` stored after `trim()` only, while sibling fields (`title`/`artist`/`id`/…) get `sanitize_text_field`. **No live sink** — render escapes via `esc_attr`/`esc_html`, JS uses `textContent`, roles never enter JSON-LD. Defense-in-depth parity fix: `sanitize_text_field` the roles at the boundary.

### ℹ️ INFO (positive verification) — Credential masked-save round-trip + constant-lock are clean (plugin `inc/admin-post-actions.php:95`)
Verified across all 3 lenses: the `0 !== strpos($new,'••••')` masked-save guard correctly avoids clobbering real tokens (the v4.13.1 fix holds); constant-lock short-circuits precede every write; dispatcher enforces `manage_options` + nonce + page allowlist. **No action — recorded for the audit trail.**

---

## Needs human review → adjudicated

### 🔧 MEDIUM→INFO — JSON-LD `@graph` emitted without `JSON_HEX_TAG` (plugin `inc/seo-schema.php:467`)
**Verifier tally:** 1 confirmed / 2 refuted (reachability confirmed the live ungated sink; exploitability + mitigation refuted the *stored-XSS via term names*).

**Adjudication (verified by hand):** the refutation is correct — `keywords`/`articleSection` derive from `wp_get_post_terms(...,'names')`, and WP core runs term names through `sanitize_text_field` at storage (`sanitize_term` → `pre_term_name`) plus the REST terms controller's `sanitize_callback`, so a literal `</script>` cannot persist in a term name. **Not a reachable stored-XSS → downgrade to INFO.**

**But apply the hardening anyway (queued):** the encode is `wp_json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)` — no `JSON_HEX_TAG`, unlike the sibling emitters (`seo-schema-music.php`, `command-palette.php:106`). The `@graph` also carries admin-set `sn_setting()` identity fields that are **not** term-sanitized (admin-self-XSS surface). Adding `JSON_HEX_TAG` escapes `<`/`>` to `<`/`>` — zero behavioral change for JSON-LD consumers, closes the admin-self-XSS, and removes the encoder inconsistency. One-line fix.

---

## Coverage / completeness critic
The 12 dimensions + 124-file examined list provide **effectively complete** coverage. The critic independently triaged 17 unexamined PHP files (9 theme, 8 plugin) and found no uncovered sink. Out-of-band checks verified clean: JS DOM-XSS (all `assets/*.js` build via `createElement`+`textContent`, `post=(\d+)` digits-only, no `innerHTML`/`eval`); reflected XSS via `add_query_arg` (all `esc_url()`-wrapped); dynamic includes (constant paths); zero `eval`/`extract`/`assert`/`create_function`/`shell_exec`; seed-content HTML clean. **No coverage gaps identified.** One non-blocking hygiene note: `inc/plausible-widget.php:222` concatenates a trusted `$status` into an `echo` — no current injection path; `wp_kses_post($status)` belt-and-suspenders if it ever takes user/API data.

---

## Recommended next actions
1. ✅ **MEDIUM IDOR — DONE** (theme v9.15.3, this branch). Review + release.
2. 🔧 **Plugin LOW batch (one patch):** `health-checks` `redirection=>0`; `webhooks` `autoload=false` + migration; length-aware mask (×4 sites); `login-hide` path-only allowlist (×2).
3. 🔧 **Fold `JSON_HEX_TAG` into the plugin patch** (`seo-schema.php:467`) — cheap, behavior-neutral.
4. ℹ️ **Optional INFO hardening:** per-post read check on `get-active-template-structure`; `sanitize_text_field` parity for discography `roles[]`; `wp_kses_post` on `plausible-widget.php:222`.
5. **No action — verified clean:** credential masked-save round-trip + constant-lock (`admin-post-actions.php:95`).

> All fixes match an existing project convention, so regression risk is low. The plugin batch is a single patch release; the theme IDOR is its own patch (v9.15.3).
