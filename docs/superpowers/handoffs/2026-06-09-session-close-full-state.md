# Handoff — Session close (full state): /music → tooling adoption → security audit → SSRF fix → branch protection

**Date:** 2026-06-09
**Status:** Everything closeable is shipped or sitting on a reviewed PR. The remaining items are **owner-gated on one thing — the `ANTHROPIC_API_KEY`** — plus installs. Nothing is half-done or blocked on code.

> Companion handoffs (same session, more detail): `2026-06-09-security-audit-and-branch-protection.md`. Specs/plans referenced below are all on `main` under `docs/superpowers/`.

## TL;DR resume points
1. **Set `ANTHROPIC_API_KEY`** in both repos → merge the 2 open PRs → install. That activates the review CI + the release-notes drafter. Commands in §"Owner-only remaining".
2. **Install** theme **v9.15.2** + plugin **v4.14.1** (wp-admin → Updates) if not done.
3. Tiny: **Monitoring → Music → Sync now** to dedupe the `/music` gallery (11 → 10; the v4.14.0 "Fin del Mundo" collapse needs one sync).

## Shipped + live (on `main`, tagged)
- **Theme v9.15.1** — `[sn_discography]` wired into `templates/page-music.html` so the `/music` cover-grid gallery renders structurally (the original "new /music page" ask; was stuck because the shortcode was never in the page body). **Live + verified** (11 cards rendering).
- **Theme v9.15.2** — **two-track width system**: every page on a `760px` reading track or `1400px` wide track (theme.json + 7 templates). Guard test `tests/layout-width-system.php`. *Eyeball after install:* the about-bio + front-page hero were the two sections pushed out to 1400.
- **Plugin v4.14.1** — **SSRF hardening** (from the back-audit). All four outbound modules (`plausible-api`, `rss-plausible-tracker`, `webhooks` ×2, `uptime-heartbeat`) now validate identically: `wp_http_validate_url()` + https-only + explicit `169.254.0.0/16` cloud-metadata block (WP core omits it) + `redirection => 0`. `tests/ssrf-url-validation.php` (13 assertions, honest stub), suite 63/63, falsified-clean, two adversarial passes. **Merged + tagged `v4.14.1` (`ba3a142`); owner installs.**

## Open PRs — REVIEWED, waiting on the API key (red CI is *expected* until keyed)
- **plugin #1** + **theme #2** — `ci: AI review tooling` (the Anthropic-tooling adoption, wave 1+2): `security-review.yml` (claude-code-security-review on PR diffs) + `claude-review.yml` (claude-code-action house-rules reviewer, reads `.github/claude-review-prompt.md`) + `release-notes.yml` (drafts Mimestream-style notes into a **draft GitHub Release** on `v*` tag push via a direct Anthropic API call — no SDK) + a `CLAUDE.md` dev-loop note (plugin got a new CLAUDE.md). All fork-guarded, SHA-pinned, path-scoped, Sonnet-pinned. Adversarially reviewed (security-clean). **No version bump** (CI/dev tooling).
- Self-validated already: existing CI green, `security-review` red only because no key, `claude-review` correctly path-skipped.

## Specs + plans on `main` (for resume / the open PRs)
- `specs/2026-06-08-anthropic-tooling-adoption-design.md` + `plans/2026-06-08-anthropic-tooling-adoption.md` — wave-1 review CI + wave-2 + the **SDK decision: PARKED** (stay on `wp-ai-client`; revisit only for a concrete PHP-out-of-band bulk need).
- `specs/2026-06-08-release-notes-drafter-design.md` + `plans/2026-06-08-release-notes-drafter.md` — the drafter (in the PRs).
- `specs/2026-06-08-music-redesign-design.md` — the shipped cover-grid (context).

## Branch protection (both repos — live)
- **Tier-1 "Protect main"** (rulesets: theme `17430820`, plugin `17430821`) — `non_fast_forward` + `deletion`, **no bypass**. Force-push + branch-delete blocked for everyone incl. owner.
- **Tier-2 "Require PR on main"** (theme `17434570`, plugin `17434571`) — `pull_request` rule, **repository-admin bypass (`always`)**. PRs are the default merge path; owner keeps `git push origin HEAD:main` (proven by a live direct push). Separate ruleset so it does NOT weaken tier-1.
- **Solo-repo honesty:** this is a *nudge*, not a hard gate (owner bypasses; non-collaborators can't push regardless). To make it a true gate, **drop the bypass** on the tier-2 ruleset → routes releases through PRs (a deliberate workflow change; would need a CLAUDE.md update).

## Owner-only remaining
```bash
# 1. Activate the AI tooling (both repos)
printf '%s' "$ANTHROPIC_API_KEY" | gh secret set ANTHROPIC_API_KEY --repo juanlentino/signal-and-noise
printf '%s' "$ANTHROPIC_API_KEY" | gh secret set ANTHROPIC_API_KEY --repo juanlentino/signal-and-noise-tools
# then re-run the red security-review check; merge plugin #1 + theme #2; install.
# validate: deliberate-bug PR (echo $_GET['x']; in inc/) is caught; and
gh workflow run "Release Notes" --repo juanlentino/signal-and-noise -f tag=v9.15.2   # → a draft Release appears
```
- **Install** theme v9.15.2 + plugin v4.14.1 (wp-admin → Updates). Tag pushes do NOT auto-deploy for either repo.
- **`/music` dedupe:** Monitoring → Music → **Sync now** (11 → 10 releases).

## Deferred / your-call (not blocking)
- Tier-2 **hard gate** (drop the bypass).
- On-site **/releases page** fed by the draft Releases the drafter produces.
- The `defending-code-reference-harness` audit skills (`/vuln-scan`, `/triage`, `/patch`) — the methodology found the SSRF gap the diff-only CI never would; worth pulling in (local skills, clone `anthropics/defending-code-reference-harness/.claude/skills/`) for periodic back-audits.
- SSRF accepted non-goal: decimal/octal IP encodings + DNS-rebinding (a per-call DNS lookup isn't worth it).

## Worktrees
Active: `signal-and-noise/.claude/worktrees/{v9.16.0 (on main, used for docs), anthropic-tooling (PR theme #2)}`; `signal-and-noise-tools/.claude/worktrees/anthropic-tooling (PR plugin #1)`. The `ssrf-fix` worktree was removed after merge. The session's own `nice-goldstine-063551` theme worktree is pinned at a stale pre-v9.13 commit — do not build from it.
