# Handoff — Security back-audit, SSRF fix (v4.14.1), branch protection

**Date:** 2026-06-09
**Status:** Audit done (clean bar one consistency gap). SSRF fix built + adversarially verified on **plugin PR #2** (not merged). Branch protection (tier-1 + tier-2) **applied + live** on both repos. This doc was pushed **directly to `main`** — proving the tier-2 bypass keeps the direct-push release flow working.

## Security back-audit (defending-code-reference-harness methodology)

Whole-codebase scan-by-class → adversarial double-skeptic triage on both repos. **Machinery verified ran** (no crashed verifiers). 10/11 vuln classes: **zero** confirmed. No critical/high/medium anywhere. The one real finding: a **defense-in-depth consistency gap** — two outbound modules dispatched to an admin/option-set host without the `wp_http_validate_url()` guard the project's own `webhooks.php`/`uptime-heartbeat.php` already applied.

## SSRF fix → plugin v4.14.1 (PR [#2](https://github.com/juanlentino/signal-and-noise-tools/pull/2), branch `claude/ssrf-url-validation`)

All **four** outbound modules now validate identically: `wp_http_validate_url()` (RFC-1918/loopback/IPv6/userinfo) + **https-only** + explicit **`169.254.0.0/16`** cloud-metadata block (which WP core omits) + `redirection => 0`.
- `inc/plausible-api.php` — Stats Bearer token no longer dispatchable to an internal/metadata host.
- `inc/rss-plausible-tracker.php` — endpoint validated before the POST that fires on unauthenticated public feed hits.
- `inc/webhooks.php` (create + update) + `inc/uptime-heartbeat.php` — extended for consistency.
- `inc/cloudflare-purge.php` — reviewed, no change (constant host).

**Honesty note (load-bearing):** the adversarial review caught that my first pass *claimed* (comment + test stub) the metadata host was blocked when WP core silently omits `169.254/16` — the fix was to make the **code** block it and rewrite the test stub to mirror real WP, so the assertion passes because the guard works, not because the test lied. Verified: `tests/ssrf-url-validation.php` 13 assertions (honest stub), plugin suite **63/63**, phpcs falsified-clean, second adversarial bypass pass (IPv6/decimal/octal/userinfo/redirect all blocked or delegated to WP core). **Accepted non-goal:** decimal/octal IP encodings + DNS-rebinding (a per-call DNS lookup isn't worth it).

## Branch protection (both repos, live)

- **Tier-1 "Protect main"** (rulesets [17430820](https://github.com/juanlentino/signal-and-noise/settings/rules) / 17430821): `non_fast_forward` + `deletion`, **no bypass** — force-push + branch-deletion blocked for everyone incl. the owner (the seatbelt).
- **Tier-2 "Require PR on main"** (17434570 / 17434571): `pull_request` rule with **repository-admin bypass (`always`)** — PRs are the default merge path, but the owner keeps `git push origin HEAD:main`. Kept as a *separate* ruleset so the bypass does **not** weaken tier-1.
- For a solo public repo this is a **nudge**, not a hard gate (the owner bypasses; non-collaborators can't push regardless). To make it a true gate (CI + review must pass before any merge, including the owner's), **drop the bypass** on the tier-2 ruleset — that routes releases through PRs, a deliberate workflow change.

## Owner-only remaining
1. Merge **plugin PR #2** + install plugin **v4.14.1** (wp-admin → Updates).
2. The earlier **anthropic-tooling PRs** ([theme #2](https://github.com/juanlentino/signal-and-noise/pull/2) / [plugin #1](https://github.com/juanlentino/signal-and-noise-tools/pull/1)) + the release-notes drafter still need the `ANTHROPIC_API_KEY` secret to activate, then merge.
