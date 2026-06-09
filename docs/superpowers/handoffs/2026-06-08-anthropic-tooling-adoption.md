# Handoff — Anthropic tooling adoption (spec + plan + CI PRs)

**Date:** 2026-06-08
**Status:** Spec + plan on `main`. Phase 2 CI files built + reviewed on **two open PR branches** (theme + plugin). SDK spike (Phase 3) scaffolded + surface-confirmed; decision pending owner. Phase 1 + activation are owner-only.

## Canonical docs (on theme `main`)
- **Spec:** `docs/superpowers/specs/2026-06-08-anthropic-tooling-adoption-design.md` (`804b6e1`)
- **Plan:** `docs/superpowers/plans/2026-06-08-anthropic-tooling-adoption.md` (`8c7590d`)

## What was built (subagent-driven, on PR branches `claude/anthropic-tooling`)

Both repos, branch `claude/anthropic-tooling` (PRs opened, **not merged**):
- `.github/workflows/security-review.yml` — `anthropics/claude-code-security-review` on PR diffs; fork-guarded (`head.repo.full_name == github.repository`), SHA-pinned, `claude-model: claude-sonnet-4-6`, `run-every-commit: false`, least-priv perms.
- `.github/workflows/claude-review.yml` — `anthropics/claude-code-action` house-rules reviewer; reads the prompt from a committed `.github/claude-review-prompt.md`; path-scoped; `claude_args: --model claude-sonnet-4-6 --max-turns 15`; fork-guarded; SHA-pinned.
- `.github/claude-review-prompt.md` — house rules (theme: standalone-safety + `source:html` dynamic-block rule; plugin: `sn_settings` subtree clobber — both + escaping/150-line/versioning/CHANGELOG).
- `CLAUDE.md` — theme: appended a "Dev-loop tooling" section; **plugin: NEW CLAUDE.md created** (it had none) with the same section + a companion-to-theme header.

**Pin SHAs:** `actions/checkout@34e1148…`, `claude-code-security-review@0c6a49f…`, `claude-code-action@593d7a5…`.

**Review (adversarial, public-repo CI):** APPROVED. Security clean — safe `pull_request` trigger (not `pull_request_target`), fork-guard blocks fork-PR secret access, no script-injection vector (the `run:` step only `cat`s a trusted in-repo file into `$GITHUB_OUTPUT`), least-privilege perms, real 40-char SHA pins. One LOW finding fixed: theme path scope `functions.php` → `*.php` (`1d41b16`). No theme/plugin version bump (CI/dev tooling).

## SDK spike (Phase 3) — surface confirmed, decision PENDING

Throwaway `/tmp/sdk-php-spike` (not committed). `anthropic-ai/sdk` v0.27.0, vendor 9.5 MB, `php -l` clean. Confirmed from vendored source: `new \Anthropic\Client(apiKey:)` (PSR-18 auto-discovered via php-http/discovery), `messages->create/createStream/countTokens`, `messages->batches->*`, tool-use, vision, structured output, Beta surface, Bedrock/Vertex sub-clients. Candidate out-of-band uses (NOT in-WP): (1) Railway worker bulk-generating SEO meta off a queue; (2) GHA release-notes/changelog drafter on tag push; (3) content-audit CLI via cron. **Owner decides graduate (→ own `juanlentino/<name>-worker` repo + spec) vs park (stay on wp-ai-client).** `/tmp/sdk-php-spike/NOTES.md` has the decision template.

## ⚠️ Owner-only to ACTIVATE (Phase 1 + Task 0 + Task 5)

1. **API key into both repos' secrets** (stdin, no `--body`): `printf '%s' "$ANTHROPIC_API_KEY" | gh secret set ANTHROPIC_API_KEY --repo juanlentino/<repo>` for both. **Until this is set, the new `security-review` check on the adoption PRs is RED (missing key) — that red IS the first validation; it goes green on re-run once keyed.** (`claude-review` is path-gated and won't fire on the adoption PRs, which only touch `.github/` + `CLAUDE.md` — proving the gate.)
2. **Install the local plugins** in Claude Code: `/plugin install php-lsp@claude-plugins-official` (+ `npm i -g intelephense`), `/plugin install security-guidance@claude-plugins-official`.
3. **Validation (plan Task 5):** after the key is set, a throwaway PR with a deliberate `echo $_GET['x'];` in `inc/` should be caught by both reviewers; a docs-only PR should fire neither.
4. **Merge the two adoption PRs** once the security-review check is green.

## Next session
Pick up the SDK graduate/park decision; if graduate, brainstorm → spec the worker in its own repo. Nothing is blocked on code — the CI half is review-clean and waiting on the key + merge.
