# Handoff — Security back-audit shipped (theme v9.15.3 + plugin v4.14.2)

**Date:** 2026-06-09 (later session; follows `2026-06-09-session-close-full-state.md`)
**Status:** A whole-codebase security back-audit ran, produced two paired releases, both **merged to `main` + tagged**. Owner installed; re-check Updates (tags were created after the install attempt). The pre-existing AI-tooling thread is unchanged and still owner-gated on the API key.

## What shipped (merged + tagged)
- **Theme `v9.15.3`** (`874f5ab`, tag pushed) — **MEDIUM IDOR fix.** The `ai-generate-page-note-summary` WP ability gated on the blanket `edit_posts` cap, so any **Contributor** could summarize → read any draft/private post by enumerating `post_id`. Added `sn_theme_perm_edit_post($input)` (per-post `current_user_can('edit_post', …)`, mirroring the plugin's `snt_ability_perm_edit_post`) and repointed the ability. TDD: RED reproduced the leak → GREEN; `tests/abilities-integration.php` 85→94, full theme sweep 30/0.
- **Plugin `v4.14.2`** (`8ad929e`, tag pushed) — **4 LOW + 1 JSON-LD hardening**, each closing a convention-drift gap: (1) `health-checks` broken-link probe `redirection=>0` (redirect-to-`169.254` SSRF); (2) `webhooks` `sn_webhooks` written `autoload=false` + sentinel-guarded `admin_init` migration (`wp_set_option_autoload`, WP 6.4+) — HMAC secrets out of alloptions; (3) shared length-aware `sn_mask_secret()` in `inc/settings.php` (short secrets no longer render in cleartext), routed through Music/Cloudflare/Plausible/Webhooks fields; (4) `login-hide` shared `sn_login_request_is_allowlisted()` matches the parsed **path** (closes `/wp-admin/?x=/feed` decoy bypass); (5) `seo-schema` JSON-LD `JSON_HEX_TAG` (defense-in-depth; the term-name breakout was core-sanitized/not reachable). New `tests/credential-mask.php` + extensions to login-intercept/webhooks/health-checks/seo-schema; full plugin sweep **63/0**.

## Audit artifact
`docs/superpowers/audits/2026-06-09-security-back-audit.md` (on theme `main`) — 12-dimension whole-codebase finder sweep + 3-lens adversarial verification (exploitability / mitigation / reachability). 8 confirmed (1 MEDIUM + 4 LOW + 3 INFO), 1 split-verdict (the JSON-LD one, adjudicated → INFO + hardening), **0 false positives**. Completeness critic found no uncovered surface. The 3 INFO items (diagnostics existence-oracle, discography `roles[]` sanitize-on-write, the already-clean masked-save round-trip) are documented but **not fixed** — optional fold-ins.

## Process lessons (now in memory)
- **Both repos' PHPCS rulesets exclude cosmetic sniffs** (theme = security+API-hygiene+i18n+compat; plugin = security-only). A whitespace probe yields 0 findings that looks like a false-green but isn't — **falsify with a SECURITY violation** (`echo $_GET['x']`). See `reference_theme_phpcs_claude_trap_fixed` / `reference_plugin_phpcs_ruleset_security_only`.
- **A `php tests/X.php | tail -1` local sweep is a false-green machine** — a fatal mid-output (after an echo'd HTML dump) isn't on the last line. CI's "every suite must emit `N passed, M failed`" gate caught a silent fatal mine missed (the plugin's `sn_mask_secret` cross-file dep in `discography-admin-render.php`). Mirror CI's summary-line gate locally. See `reference_test_sweep_summary_line_gate`. (The fatal was real, caught on the PR, fixed before merge.)

## Still pending (unchanged from the earlier handoff — separate thread)
1. **`ANTHROPIC_API_KEY`** not set in either repo → the two **AI-tooling PRs** (plugin #1, theme #2) remain open, waiting on it. Set it → merge those → validate (deliberate-bug PR + a `Release Notes` draft).
2. **Installs / Sync:** re-check wp-admin → Updates for v9.15.3 / v4.14.2 (tags now exist); run **Monitoring → Music → Sync now** to dedupe `/music` 11→10. Tag pushes do NOT auto-deploy.
3. Optional INFO hardening from the audit (above); the deferred items from the prior handoff (tier-2 hard gate, `/releases` page, audit-harness skills).

## Worktrees
The `sec-audit` worktrees (created for the audit + fixes) were removed; both feature branches merged + deleted. No in-flight branches from this session.
