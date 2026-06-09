# Handoff — INFO hardening shipped, AI reviewer fixed, tier-2 checks required

**Date:** 2026-06-09 (latest; follows `2026-06-09-security-back-audit-shipped.md`)
**Status:** All back-audit remediation shipped (theme **v9.15.4** + plugin **v4.14.3**, merged + tagged). The AI house-rules reviewer was broken-on-merge and is now **fixed + validated**. Tier-2 branch protection now **requires the deterministic CI checks** (closes the "merge-unstable" gap). **0 open PRs** in either repo. One item is **in-progress, not done**: the defending-code-reference-harness install (details below).

## Shipped this session (all merged + tagged)
- **theme v9.15.3** — MEDIUM IDOR fix (per-post `edit_post` gate on `ai-generate-page-note-summary`).
- **plugin v4.14.2** — 4 LOW + JSON-LD hardening (link-checker `redirection=>0`; webhook secret `autoload=false` + migration; length-aware credential mask; login-hide path-only allowlist; `JSON_HEX_TAG`).
- **theme v9.15.4** — INFO: closed the `get-active-template-structure` existence/post_type oracle (unreadable post → same `post_not_found` as missing). *(Owner merged PR #4 directly.)*
- **plugin v4.14.3** — INFO: discography `roles[]` `sanitize_text_field` on write; `plausible-widget.php` footer `$status` → `wp_kses_post`.
- **Audit report:** `docs/superpowers/audits/2026-06-09-security-back-audit.md`.
- **Test status:** theme 30 suites / 0 fail; plugin 63 suites / 0 fail. PHPCS falsification-verified both repos.

## AI tooling — now live + the reviewer is fixed
- `ANTHROPIC_API_KEY` set in both repos; tooling PRs #1 (plugin) + #2 (theme) merged (security-review + claude-review + release-notes drafter). Drafter validated — draft Releases for v9.15.3/v4.14.2 exist (unpublished).
- **`claude-review` was broken on every code PR** (surfaced by INFO PR #4's first real run). Two causes, both fixed: (1) `claude-review.yml` lacked `id-token: write` → `claude-code-action` OIDC fetch failed; (2) `claude-code-action` requires the PR-branch workflow to **byte-match the default branch**, so the fix had to land on `main` (CI-fix PRs #5, merged). Now validated working (green 2m49s run on plugin #4). The `tsconfig`/fd-4 "internal error" the action logs is benign ("you don't need to do anything").

## Tier-2 branch protection (changed this session)
- Added a `required_status_checks` rule to **both** tier-2 rulesets (theme `17434570`, plugin `17434571`) requiring the 4 **deterministic** checks: `CHANGELOG entry present`, `PHP syntax check`, `Test suite`, `WordPress Coding Standards`.
- **AI checks (`review`/`security`) are intentionally NOT required:** `claude-review` is path-scoped (skips docs-only PRs) → requiring it would **deadlock** PRs that never trigger it; AI checks can also flake.
- **Bypass kept** (`RepositoryRole/always`): owner direct-push to `main` still works; `--admin` override available for genuine flakes. Net: a red *deterministic* check → PR `BLOCKED` → `gh pr merge` refuses the casual merge (the accident that triggered this). **NOT dropped:** the bypass (the heavier "tier-2 hard gate" — would break direct-push; deferred, owner's call).
- ⚠️ The earlier `security-back-audit-shipped` handoff's branch-protection description is now partially superseded by this required-checks addition.

## Memory written this session
- `feedback_never_merge_unstable_pr` — never merge a red/UNSTABLE PR even if the failing check is non-required; fix to green first. (Set after I proposed merging an UNSTABLE INFO PR; owner stopped it.)
- `reference_test_sweep_summary_line_gate` — local test sweeps must gate on the `N passed, M failed` summary line, not `tail -1` (a mid-output fatal isn't on the last line; CI caught one mine missed).
- Updated `reference_theme_phpcs_claude_trap_fixed` — the theme ruleset excludes cosmetic sniffs; falsify with a SECURITY violation, not whitespace.

## PENDING / next session
1. **defending-code-reference-harness install — IN PROGRESS, NOT DONE.** Cloned to `/tmp/dcrh` (ephemeral — re-clone `github.com/anthropics/defending-code-reference-harness`). It ships `.claude/skills/`: `vuln-scan`, `triage`, `patch`, `threat-model`, `customize`, `quickstart`, `_lib`. **Install gotcha:** the skills invoke `python3 .claude/skills/_lib/checkpoint.py` via **relative paths from the project root** → install the WHOLE `.claude/skills/` tree (incl. `_lib`) at a project root's `.claude/skills/`, not cherry-picked into `~/.claude/skills/`. **Decision needed:** which project (theme? both? a dedicated audit clone?) + committed vs gitignored. Then `/quickstart` to orient; these power periodic whole-codebase back-audits (the methodology that found this session's findings).
2. **Owner installs:** theme **v9.15.4** + plugin **v4.14.3** via wp-admin → Updates (latest INFO releases; tags exist). Tag pushes don't auto-deploy.
3. **Optional/deferred:** publish the draft Releases (v9.15.3/v4.14.2 + now v9.15.4/v4.14.3 once a tag-push drafts them); tier-2 **drop-bypass** hard gate (owner's call — breaks direct-push); on-site `/releases` page.

## Worktrees
`sec-audit`, `ci-review-oidc`, and both `info-hardening` worktrees removed. No in-flight branches; both repos at their tagged HEADs.
