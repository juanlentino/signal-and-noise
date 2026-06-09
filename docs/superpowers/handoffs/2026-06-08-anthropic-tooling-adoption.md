# Handoff — Anthropic tooling adoption (spec + plan + CI PRs)

**Date:** 2026-06-08
**Status:** Spec + plan on `main`. Phase 2 CI files built + reviewed on **two open PR branches** (theme + plugin). SDK spike (Phase 3) evaluated → **PARKED**. Phase 1 + activation are owner-only (gated on the API key). `main` now has tier-1 branch protection (force-push + deletion blocked) on both repos.

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

## SDK spike (Phase 3) — PARKED

Evaluated `anthropic-ai/sdk` v0.27.0 (throwaway `/tmp/sdk-php-spike`, now removed). Surface confirmed from vendored source: `new \Anthropic\Client(apiKey:)` (PSR-18 auto-discovered via php-http/discovery), `messages->create/createStream/countTokens`, `messages->batches->*`, tool-use, vision, structured output, Beta surface, Bedrock/Vertex sub-clients. Vendor 9.5 MB, `php -l` clean.

**Decision: PARK** — stay on `wp-ai-client` in-WP; do not adopt the SDK now. Rationale: (1) in-WP AI is served by the standardized `wp-ai-client` (the SDK is explicitly *not* for the plugin — competes with it + forces vendoring a PSR-18 client into a no-build deploy); (2) none of the candidate out-of-band uses *require* PHP-outside-WP, the SDK's only distinct niche — the most appealing (a release-notes drafter) is better served by `claude-code-action` (just adopted); the others (a Railway SEO-meta worker, a content-audit CLI) are nice-to-haves that don't justify a new repo + maintenance for a solo dev. **Revisit only** if a concrete PHP-specific bulk/out-of-band need emerges — then `countTokens` (spend pre-estimate) + Batches (50% cheaper) make it worthwhile, and it graduates to its own `juanlentino/<name>-worker` repo with its own spec (never a plugin tab, never inside `signal-and-noise-tools`).

## Wave 2 — release-notes drafter (BUILT, on the same PRs)

The "what replaces the parked SDK" answer — delivered **without** the SDK or the `claude-code-action` agent. Spec/plan: `docs/superpowers/{specs,plans}/2026-06-08-release-notes-drafter*`. Added `.github/workflows/release-notes.yml` to BOTH `claude/anthropic-tooling` PR branches (theme `04fc078`, plugin `cd7c379`; byte-identical). On a `v*` tag push (or `workflow_dispatch` with a `tag` input), it: checks out the tag → `awk`-extracts that version's `## [X.Y.Z]` CHANGELOG block → transforms it into Mimestream-style **user-facing** notes via a **direct Anthropic Messages API call** (`curl` + `jq`, no SDK, deterministic) → creates a **draft** GitHub Release (you review + publish). Owner-only trigger (no fork vector), `contents: write`, same `ANTHROPIC_API_KEY`, one Sonnet call/release, fails loud on a missing CHANGELOG entry. `awk` extraction TDD'd; YAML + jq-payload validated; security self-reviewed (env-passing → no injection, draft = human gate).

## ⚠️ Owner-only to ACTIVATE

1. **API key into both repos' secrets** (stdin, no `--body`): `printf '%s' "$ANTHROPIC_API_KEY" | gh secret set ANTHROPIC_API_KEY --repo juanlentino/<repo>` for both. **Until this is set, the new `security-review` check on the adoption PRs is RED (missing key) — that red IS the first validation; it goes green on re-run once keyed.** (`claude-review` is path-gated and won't fire on the adoption PRs, which only touch `.github/` + `CLAUDE.md` — proving the gate.)
2. **Install the local plugins** in Claude Code: `/plugin install php-lsp@claude-plugins-official` (+ `npm i -g intelephense`), `/plugin install security-guidance@claude-plugins-official`.
3. **Validation (plan Task 5):** after the key is set, a throwaway PR with a deliberate `echo $_GET['x'];` in `inc/` should be caught by both reviewers; a docs-only PR should fire neither.
4. **Release-notes validation:** after the key is set + the PRs merged, `gh workflow run "Release Notes" --repo juanlentino/signal-and-noise -f tag=v9.15.2` → a **draft** Release should appear with friendly, categorized, jargon-free notes; review + publish. A bogus tag (`-f tag=v0.0.0`) should fail loud with no empty draft.
5. **Merge the two adoption PRs** once the security-review check is green (each PR now carries wave-1 review tooling **+** wave-2 release-notes drafter).

## Next session
The agent-doable adoption is **complete** (wave-1 review CI + wave-2 release-notes drafter, both on the two open PRs; SDK parked). Everything left is owner activation: set the `ANTHROPIC_API_KEY`, install the two local plugins, run the two validations (review-catch + release-notes draft), merge. `main` is tier-1 protected on both repos (force-push + deletion blocked); tier-2 (require-PR + checks) is still an open decision — the verification workflow's findings + exact commands are pending.
