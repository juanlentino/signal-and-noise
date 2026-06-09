# Anthropic Tooling Adoption — Design Spec

**Date:** 2026-06-08
**Status:** Design approved (brainstorm complete). Awaiting spec → writing-plans.
**Type:** Dev-process + CI adoption (no shipped theme/plugin code; no version bump). Spans both repos.
**Repos:** `juanlentino/signal-and-noise` (theme) + `juanlentino/signal-and-noise-tools` (plugin) — both **PUBLIC**, default branch `main`, each running a `ci.yml` (`lint → phpcs → tests → changelog`).

## 1. Problem / motivation

A multi-source audit of `github.com/anthropics` (76 active repos) surfaced a small set of tools that plug into the dev loop + CI we already run, each mapping to a class of bug that has actually bitten this project:

- The **v9.11.2 single-notes fatal** — a misspelled WP function (`get_the_queried_object_id()` vs `get_queried_object_id()`) that 500'd every single note. Neither phpcs nor a test stub of the bad symbol caught it; a PHP LSP catches it before runtime.
- The **webhook-SSRF (v4.5.2)** and the standing **never-commit-PAT** rule — the injection/SSRF/secret class, catchable at edit + commit time and on PR diffs.
- Recurring review misses our mechanical `WordPress.Security` phpcs ruleset structurally cannot reason about (missing nonce, bypassable capability check, IDOR on REST/Abilities callbacks) and house-rule drift (the `sn_settings`-subtree clobber bitten 4×, the 150-line ceiling, Mimestream CHANGELOG categories).

The goal is to layer **AI-assisted security + review** onto the existing pipeline — not to adopt new architecture — plus a time-boxed evaluation of the official PHP SDK for out-of-band use.

## 2. Decisions made during brainstorm

| Decision | Choice | Why |
|---|---|---|
| Scope | Full: dev-loop + CI review + SDK spike (phased) | User chose broadest scope |
| Plugin settings tab for this? | **No** | Dev/CI tooling has zero WordPress runtime footprint; nothing for a wp-admin tab to read/write. Home is `CLAUDE.md`, not a tab. Category error. |
| New repo for this? | **Not yet** for CI configs (YAGNI at 2 repos); **the SDK spike's graduation is the real new-repo trigger** | A standalone Claude-calling worker is its own deployable → its own repo; CI configs duplicate cheaply with a documented centralization trigger |
| SDK in the WP plugin? | **No** | Competes with the standardized `wp-ai-client`/`ai`-plugin path; forces vendoring a PSR-18 client into a no-build deploy. SDK value is out-of-band PHP only. |
| Versioning | **No theme/plugin bump** | CI/dev tooling is not shipped theme/plugin code (matches how the original `ci.yml` shipped) |

### Verified facts (grounded 2026-06-08)
- Both repos are **PUBLIC** → AI jobs must be **fork-guarded** (a stranger's PR must not trigger token spend) and actions **SHA-pinned** (supply-chain).
- The relevant Anthropic repos are official + actively maintained: `claude-plugins-official` (Apache-2.0), `claude-code-security-review` (5.1k★, `action.yml`), `claude-code-action` (v1, 7.9k★), `anthropic-sdk-php` (`anthropic-ai/sdk`, MIT, **v0.27.0** 2026-06-06).
- GitHub **reusable workflows** are callable cross-repo (`owner/repo/.github/workflows/file.yml@ref`) with explicit secret-passing (`secrets: { ANTHROPIC_API_KEY: ${{ secrets.ANTHROPIC_API_KEY }} }`); no special `.github` repo required. (Verified for the future-centralization path; **not used in this iteration**.)

## 3. Architecture — three phases

```
Phase 1  Local dev-loop (Claude Code, your machine)     → catches bugs BEFORE commit
   php-lsp/Intelephense + security-guidance plugins, documented in both CLAUDE.md

Phase 2  AI review in CI (both repos, .github/workflows) → catches bugs ON PR
   security-review.yml  (semantic security on diffs)
   claude-review.yml    (house-rules reviewer, reads .github/claude-review-prompt.md)
   fork-guarded · SHA-pinned · least-priv · PR-only · path-scoped · model-pinned

Phase 3  anthropic-sdk-php spike (throwaway, time-boxed) → decision gate
   probe out-of-band PHP → graduate (new worker repo + own spec) OR park (stay on wp-ai-client)
```

Each phase is independently valuable and gated; Phase 2 is fully additive (delete two files to revert; zero touch to `ci.yml`/`deploy.yml`).

## 4. Phase 1 — Local dev-loop hardening

**Where:** the user's Claude Code (not committed to the repos). **Documented** in both `CLAUDE.md`s as the decided home for dev-tooling (explicitly *not* a plugin tab).

- Install `php-lsp` (`/plugin install php-lsp@claude-plugins-official` + `npm i -g intelephense`) and `security-guidance` (`/plugin install security-guidance@claude-plugins-official`).
- Scope the LLM-backed security hooks to **commit/PR moments**, not every keystroke (token cost).
- **`CLAUDE.md` addition (both repos):** a short "Dev-loop tooling" section — what's installed, why (the v9.11.2 + SSRF/PAT classes), the keystroke-cost caveat, and that this is dev-tooling not a wp-admin surface.

**Validation:** Intelephense flags an unknown-symbol on a deliberately-misspelled WP function in the **theme's** `inc/related-notes.php` (the v9.11.2 file); `security-guidance` flags an unescaped-`echo`/hardcoded-secret probe.

## 5. Phase 2 — AI review in CI

**Where:** two new, **separate** workflow files per repo (so an AI-review timeout never blocks `lint/phpcs/tests/changelog`).

### 5.1 `.github/workflows/security-review.yml`
- Action: `anthropics/claude-code-security-review`, **SHA-pinned**.
- Trigger: `pull_request`; **fork-guarded** `if: github.event.pull_request.head.repo.full_name == github.repository`.
- `permissions: { pull-requests: write, contents: read }`; checkout `fetch-depth: 2` (diff).
- `comment-pr: true`; `claude-model` pinned to **`claude-sonnet-4-6`**.
- Semantic security pass (auth-bypass / IDOR / SSRF / data-exposure reasoning) phpcs can't do. Diff-only — whole-file audits of existing v9.x/v4.x code use the `/security-review` slash command locally.

### 5.2 `.github/workflows/claude-review.yml`
- Action: `anthropics/claude-code-action`, **SHA-pinned** (`@v1` resolved to a SHA).
- Trigger: `pull_request`; **fork-guarded**; **path-scoped** to `inc/**.php`, `**/render.php`, `templates/**`, `*.php` (doc-only PRs don't pay).
- Reads the house-rules prompt from a committed **`.github/claude-review-prompt.md`** (single source per repo; the future-centralization seam).
- `claude_args`: append `--model claude-sonnet-4-6 --max-turns 15` to the template's existing `--allowedTools` line.
- **House-rules checklist** (the prompt): `sn_settings`-subtree preservation in `sn_settings_save()`; 150-line file ceiling; Mimestream CHANGELOG categories; escaping / `WordPress.Security`; version-bump correctness (patch vs minor vs major per `docs/VERSIONING.md`).

### 5.3 Cross-cutting safeguards
- **Secret:** `gh secret set ANTHROPIC_API_KEY --repo <repo>` (pipe from **stdin**, drop `--body` — per the project's own gotcha) in both repos.
- **Cost controls:** PR-only (not every push), path-scoped, model pinned to Sonnet (not Opus), `--max-turns` capped, fork PRs excluded.
- **Supply-chain:** every `uses:` pinned to a full commit SHA (public repos).
- **Centralization seam:** workflows duplicated per repo for now; the prompt lives in a file so a later move to a reusable workflow is clean. **Trigger to centralize** (→ reusable workflow or shared repo): a 3rd repo joins, OR the prompt diverges between copies, OR the SDK spike graduates.

**Validation:** a throwaway PR with a deliberate escaping bug fires both reviewers with inline comments; a docs-only PR fires neither (path-gating proven). Run `/security-review` locally on a recent REST/webhook diff first for free signal before wiring CI.

## 6. Phase 3 — `anthropic-sdk-php` spike

**Where:** a throwaway directory — **never** the plugin.

- `composer require "anthropic-ai/sdk:^0.27.0" guzzlehttp/guzzle nyholm/psr7`; run `examples/messages.php` with `ANTHROPIC_API_KEY`; inspect `/vendor` size + the surface (`countTokens` for spend pre-estimate, Batches for 50%-cheaper bulk).
- **Decision gate:** is there a concrete *out-of-band* use case — a Railway worker, a CLI, or a GHA step that calls Claude **without booting WordPress**?
  - **Graduate →** the use case gets its **own dedicated repo** (`juanlentino/<name>-worker`) and **its own spec**. Not a plugin tab; not crammed into `signal-and-noise-tools`.
  - **Park →** document "evaluated `v0.27.0`, parked, stay on `wp-ai-client` in-WP" and stop.
- Explicitly **not** adopting the SDK inside the WP plugin.

## 7. Surface topology (the analysis the user asked for)

- **Plugin settings tab: No.** Dev/CI tooling has no WordPress runtime config; `CLAUDE.md` is the home. The only future tab case is an in-WP runtime AI feature, which is out of scope (and parked).
- **New repo: Not yet** for CI configs (duplicate the two small files; YAGNI at 2 repos). **Yes, conditionally**, as Phase 3's graduation outcome (a standalone worker is its own deployable).
- **Centralization path** is pre-seamed (prompt-as-file) with explicit triggers, so deferring costs nothing later.

## 8. Cost & security

- **Token cost** bounded by: PR-only + path-scoped + fork-guarded + Sonnet-pinned + `--max-turns 15`. No per-push spend; doc PRs free; external PRs blocked.
- **Security:** SHA-pinned actions; least-privilege `permissions`; fork-guard prevents API-key abuse on a public repo; `ANTHROPIC_API_KEY` via stdin `gh secret set` (never inlined).

## 9. Versioning

**No theme/plugin version bump.** Phase 1 is local tooling; Phase 2 is CI workflow files + `CLAUDE.md` docs; Phase 3 is a throwaway spike. Per `docs/VERSIONING.md`, CI/dev tooling and docs do not bump (matches how the original `ci.yml` shipped). Commits are doc/CI commits pushed to `main`; no tag, no deploy.

## 10. Non-goals (YAGNI)

- No new plugin settings tab.
- No new dedicated repo for the CI configs (only for an SDK-spike graduation).
- No reusable-workflow centralization in this iteration (pre-seamed only).
- No SDK adoption inside the WordPress plugin.
- No whole-history security re-audit in CI (diff-only; slash command for back-audits).
- No `claude-code-action` on every push (PR + path-gated only).

## 11. Success criteria

- **P1:** both tools installed; the misspelled-WP-fn probe flags pre-runtime; documented in both `CLAUDE.md`s.
- **P2:** both workflows live in both repos; a deliberate-bug PR is caught by both reviewers; a docs-only PR triggers neither; `lint/phpcs/tests/changelog` unaffected; no token spend on push or fork PRs.
- **P3:** a written go/no-go on the SDK with a one-line rationale; if go, a follow-up spec for the worker repo; if no-go, a "parked" note.

## 12. Cross-references

- Bug classes this targets: the single-notes fatal (theme v9.11.2, `get_the_queried_object_id` typo), the webhook-SSRF (plugin v4.5.2), the `sn_settings`-subtree clobber (bitten 4×).
- Constraints honored: no new wp-admin dashboard widgets/surfaces; wp-admin stays native (no tooling tab); `gh secret set` via stdin (drop `--body`).
- Versioning: `docs/VERSIONING.md` (CI/dev tooling doesn't bump).
