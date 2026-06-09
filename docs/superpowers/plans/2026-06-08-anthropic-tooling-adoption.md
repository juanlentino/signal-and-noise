# Anthropic Tooling Adoption Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Layer AI-assisted security + review onto the existing dev loop and CI of both repos (local LSP/security plugins + two fork-guarded PR review workflows), and run a time-boxed evaluation of the official PHP SDK for out-of-band use.

**Architecture:** Phase 1 documents local Claude Code plugins in `CLAUDE.md` (owner installs them). Phase 2 adds two **separate, additive** workflow files per repo — `claude-code-security-review` (semantic security on PR diffs) and `claude-code-action` (a house-rules reviewer reading a committed prompt file) — fork-guarded, SHA-pinned, path-scoped, Sonnet-pinned. Phase 3 probes `anthropic-sdk-php` in a throwaway dir with a go/no-go decision gate. No theme/plugin version bump.

**Tech Stack:** GitHub Actions (reusable from `anthropics/*`), `gh` CLI, Markdown (`CLAUDE.md`, prompt file), Composer/PHP (spike only). Spec: `docs/superpowers/specs/2026-06-08-anthropic-tooling-adoption-design.md`.

**Repos:** `juanlentino/signal-and-noise` (theme), `juanlentino/signal-and-noise-tools` (plugin) — both PUBLIC, default `main`.

**Legend:** 🧑 = owner-only action (needs the real API key / the owner's Claude Code client). 🤖 = agent-executable.

---

## File structure

**Per repo (both theme + plugin):**
- Create `.github/workflows/security-review.yml` — semantic security review on PR diffs.
- Create `.github/workflows/claude-review.yml` — house-rules reviewer; reads the prompt file.
- Create `.github/claude-review-prompt.md` — the house-rules checklist (single source; future-centralization seam).
- Modify `CLAUDE.md` — add a "Dev-loop tooling" section (the decided home; not a wp-admin tab).

**Spike (throwaway, NOT committed):**
- `/tmp/sdk-php-spike/{composer.json, probe.php, NOTES.md}`.

---

## Task 0: Owner prerequisites

**Files:** none. 🧑 owner-only.

- [ ] **Step 1 🧑: Put the API key in both repos' Actions secrets** (stdin, drop `--body` — project gotcha)

```bash
printf '%s' "$ANTHROPIC_API_KEY" | gh secret set ANTHROPIC_API_KEY --repo juanlentino/signal-and-noise-tools
printf '%s' "$ANTHROPIC_API_KEY" | gh secret set ANTHROPIC_API_KEY --repo juanlentino/signal-and-noise
```
Verify: `gh secret list --repo juanlentino/signal-and-noise-tools` shows `ANTHROPIC_API_KEY`.

- [ ] **Step 2 🧑: Install the local Claude Code plugins** (in Claude Code)

```
/plugin install php-lsp@claude-plugins-official
/plugin install security-guidance@claude-plugins-official
```
Then `npm i -g intelephense`. (These configure the owner's Claude Code client; they are not committed.)

---

## Task 1: Document the dev-loop tooling in both CLAUDE.md

**Files:** Modify `CLAUDE.md` (theme) + `CLAUDE.md` (plugin). 🤖

- [ ] **Step 1: Add the section to the theme `CLAUDE.md`**

Append after the "WordPress reference" section:

```markdown
## Dev-loop tooling (local Claude Code — not a wp-admin surface)

Local Claude Code plugins harden the dev loop *before* commit (dev/CI tooling lives
here in CLAUDE.md, never as a plugin settings tab — it has no WordPress runtime config):

- **php-lsp / Intelephense** (`/plugin install php-lsp@claude-plugins-official` + `npm i -g intelephense`)
  — real PHP diagnostics on the primary language. Catches unknown-symbol fatals before
  runtime (the class behind the v9.11.2 single-notes incident: `get_the_queried_object_id()`
  vs `get_queried_object_id()`), which neither phpcs nor a test stub of the bad symbol catch.
- **security-guidance** (`/plugin install security-guidance@claude-plugins-official`)
  — pattern + LLM review for injection/XSS/SSRF/hardcoded-secrets at edit + commit time
  (the webhook-SSRF + never-commit-PAT classes). Scope its LLM hooks to commit/PR moments,
  not every keystroke, to control token spend.

CI mirrors this on PRs — see `.github/workflows/security-review.yml` + `claude-review.yml`.
```

- [ ] **Step 2: Add the equivalent section to the plugin `CLAUDE.md`** (same content; the v9.11.2 file reference stays — it is a theme file, named as the cross-repo example).

- [ ] **Step 3: Commit (both repos)**

```bash
git add CLAUDE.md && git commit -m "docs(claude): dev-loop tooling — php-lsp + security-guidance (local, not a wp-admin tab)"
```

---

## Task 2: Resolve the pin SHAs (supply-chain) 🤖

**Files:** none (produces values used in Tasks 3–4).

- [ ] **Step 1: Resolve each action to a full commit SHA** and record them

```bash
# security-review has no releases → pin to current main:
gh api repos/anthropics/claude-code-security-review/commits/main --jq .sha          # → SHA_SR
# claude-code-action v1 (moving tag) → its commit:
gh api repos/anthropics/claude-code-action/commits/v1 --jq .sha                      # → SHA_CCA
# first-party checkout, pin too:
gh api repos/actions/checkout/commits/v4 --jq .sha                                   # → SHA_CHK
```
Use these literal 40-char SHAs in the `uses:` lines below (replace `SHA_SR` / `SHA_CCA` / `SHA_CHK`). Re-run to re-pin on update.

- [ ] **Step 2: Confirm the model id is accepted.** The security-review example shows a dated id (`claude-sonnet-4-20250514`); this plan uses the current alias `claude-sonnet-4-6`. If a run errors on an unknown model, replace `claude-sonnet-4-6` with the current dated Sonnet id from the Claude API docs.

---

## Task 3: Security-review workflow (plugin first, then theme)

**Files:** Create `.github/workflows/security-review.yml`. 🤖

- [ ] **Step 1: Create the file in `signal-and-noise-tools`**

```yaml
name: Security Review
on:
  pull_request:
jobs:
  security:
    # Fork-guard: same-repo PRs only (public-repo fork PRs get no secrets anyway).
    if: github.event.pull_request.head.repo.full_name == github.repository
    runs-on: ubuntu-latest
    permissions:
      pull-requests: write
      contents: read
    steps:
      - uses: actions/checkout@SHA_CHK
        with:
          fetch-depth: 2
      - uses: anthropics/claude-code-security-review@SHA_SR
        with:
          claude-api-key: ${{ secrets.ANTHROPIC_API_KEY }}
          claude-model: claude-sonnet-4-6
          comment-pr: 'true'
          run-every-commit: 'false'
          exclude-directories: 'vendor,node_modules,tests'
```

- [ ] **Step 2: Validate YAML locally**

Run: `python3 -c "import yaml,sys; yaml.safe_load(open('.github/workflows/security-review.yml'))" && echo OK`
Expected: `OK` (no parse error).

- [ ] **Step 3: Commit**

```bash
git add .github/workflows/security-review.yml
git commit -m "ci(security): claude-code-security-review on PR diffs (fork-guarded, SHA-pinned, Sonnet)"
```

- [ ] **Step 4: Create the same file in `signal-and-noise` (theme)** — byte-identical to Step 1 except `exclude-directories` stays `'vendor,node_modules,tests'`. Validate (Step 2) + commit (Step 3) in the theme repo.

---

## Task 4: House-rules reviewer (prompt file + workflow)

**Files:** Create `.github/claude-review-prompt.md` + `.github/workflows/claude-review.yml`. 🤖

- [ ] **Step 1: Create `.github/claude-review-prompt.md` in `signal-and-noise-tools`**

```markdown
You are reviewing a pull request for the Signal & Noise Tools WordPress plugin.
Comment inline ONLY on real issues in the changed lines. Check, in priority order:

1. **sn_settings subtree clobber (CRITICAL, bitten 4×):** `sn_settings_save()` whole-option
   replace must re-include every settings subtree. A new subtree not re-included is silently
   wiped when Identity is saved. Flag any new `sn_settings` subtree not preserved in the save handler.
2. **Escaping / WordPress.Security:** unescaped output (`echo`/interpolation without esc_*),
   unsanitized input ($_GET/$_POST/$_REQUEST), missing nonce or capability check on a state-changing
   REST/Abilities/admin-post handler, SSRF on outbound requests built from user input.
3. **150-line file ceiling:** flag a new or modified file exceeding ~150 lines; suggest a split.
4. **Versioning correctness:** if `signal-and-noise-tools.php` `Version:` changed, confirm patch/minor/
   major matches the change per docs/VERSIONING.md (majors gate on real breaking changes only).
5. **CHANGELOG:** a code change should add a top CHANGELOG.md entry using the Mimestream-style
   `### New / Improvements / Fixed / Cleanup / Removed / Deprecated` headers.

Be terse. No praise, no summaries of unchanged code. If nothing qualifies, say so in one line.
```

- [ ] **Step 2: Create `.github/workflows/claude-review.yml` in `signal-and-noise-tools`**

> Reference of record for the action's automation mode (a `prompt` on a non-comment event runs the review automatically, no `@claude` needed): `anthropics/claude-code-action` → `examples/pr-review-comprehensive.yml`. If a run no-ops, cross-check that example for the current `prompt`/`claude_args` shape and adjust.

```yaml
name: Claude Review
on:
  pull_request:
    paths:
      - 'inc/**.php'
      - '**/render.php'
      - 'signal-and-noise-tools.php'
jobs:
  review:
    if: github.event.pull_request.head.repo.full_name == github.repository
    runs-on: ubuntu-latest
    permissions:
      pull-requests: write
      contents: read
    steps:
      - uses: actions/checkout@SHA_CHK
        with:
          fetch-depth: 1
      - id: prompt
        run: |
          {
            echo 'content<<PROMPT_EOF'
            cat .github/claude-review-prompt.md
            echo 'PROMPT_EOF'
          } >> "$GITHUB_OUTPUT"
      - uses: anthropics/claude-code-action@SHA_CCA
        with:
          anthropic_api_key: ${{ secrets.ANTHROPIC_API_KEY }}
          prompt: ${{ steps.prompt.outputs.content }}
          claude_args: |
            --model claude-sonnet-4-6
            --max-turns 15
```

- [ ] **Step 3: Validate YAML**

Run: `python3 -c "import yaml; yaml.safe_load(open('.github/workflows/claude-review.yml'))" && echo OK`
Expected: `OK`.

- [ ] **Step 4: Commit (plugin)**

```bash
git add .github/claude-review-prompt.md .github/workflows/claude-review.yml
git commit -m "ci(review): claude-code-action house-rules reviewer (prompt-as-file, path-scoped, fork-guarded)"
```

- [ ] **Step 5: Create both files in `signal-and-noise` (theme)** with these theme-specific changes, then validate + commit:
  - Prompt file: replace item 1 text with the theme's concerns — *"Standalone-safety: a theme shortcode reading a `sn_*` filter must degrade to `''` when the plugin is absent; dynamic block render.php must not read `source:html` attrs (they arrive empty server-side)."* Keep items 2–5; in item 4 reference `style.css` `Version:` + `readme.txt` `Stable tag:`.
  - Workflow `paths:`:
    ```yaml
        paths:
          - 'inc/**.php'
          - '**/render.php'
          - 'templates/**'
          - 'parts/**'
          - 'functions.php'
          - 'theme.json'
    ```

---

## Task 5: Validation PRs (prove caught + prove gated)

**Files:** throwaway branches. 🧑 owner opens PRs (needs the key live); 🤖 may open them via `gh` once Task 0 is done.

- [ ] **Step 1: Deliberate-bug PR (must be CAUGHT).** On a branch in `signal-and-noise-tools`, add to a file under `inc/`: `echo $_GET['x'];` (unescaped output + unsanitized input + no nonce). Open a PR.
  Expected: `security-review` comments a finding AND `claude-review` flags the escaping. Both jobs run (not skipped).

- [ ] **Step 2: Docs-only PR (must be IGNORED by claude-review).** On another branch, edit only `README`/`docs/`. Open a PR.
  Expected: `claude-review` does NOT run (path-gated). `security-review` may run but finds nothing. `lint/phpcs/tests/changelog` unaffected in both PRs.

- [ ] **Step 3: Confirm no spend on push / forks.** Push a commit directly to a branch without a PR → neither AI job runs. (Fork PRs are already blocked by the `if` guard.)

- [ ] **Step 4: Close the throwaway PRs.** Delete the test branches.

---

## Task 6: anthropic-sdk-php spike (throwaway, decision-gated)

**Files:** `/tmp/sdk-php-spike/*` (NOT committed). 🤖 scaffolds; 🧑 runs with the key.

- [ ] **Step 1 🤖: Scaffold the probe**

```bash
mkdir -p /tmp/sdk-php-spike && cd /tmp/sdk-php-spike
composer require "anthropic-ai/sdk:^0.27.0" guzzlehttp/guzzle nyholm/psr7
```
Write `/tmp/sdk-php-spike/probe.php`: a minimal Messages call + a `countTokens` call, printing the response and token estimate (mirror the repo's `examples/messages.php`). Write `NOTES.md` with the decision template (below).

- [ ] **Step 2 🧑: Run it**

```bash
cd /tmp/sdk-php-spike && ANTHROPIC_API_KEY=<key> php probe.php
du -sh vendor   # inspect dependency weight for a no-build deploy
```

- [ ] **Step 3 🤖+🧑: Record the go/no-go in `NOTES.md`** using this template, then act:

```markdown
## Decision (anthropic-sdk-php v0.27.0)
- vendor size: <N MB>; surface confirmed: Messages / streaming / tool-use / vision / countTokens / Batches.
- Out-of-band use case found? (Railway worker / CLI / GHA step calling Claude WITHOUT WordPress): YES/NO — <which>.
- GRADUATE → write a follow-up spec `docs/superpowers/specs/<date>-<name>-worker.md`; the worker gets its OWN repo `juanlentino/<name>-worker`. NOT a plugin tab; NOT inside signal-and-noise-tools.
- PARK → "evaluated v0.27.0, parked; stay on wp-ai-client in-WP."
```

- [ ] **Step 4 🤖: Clean up** `rm -rf /tmp/sdk-php-spike` after the decision is recorded (copy the decision into the wrap-up handoff first).

---

## Task 7: Wrap-up

**Files:** `docs/superpowers/handoffs/2026-06-08-anthropic-tooling-adoption.md` (theme). 🤖

- [ ] **Step 1: Write a short handoff** — what landed (both CLAUDE.md sections, 4 workflow files + 2 prompt files across 2 repos), the validation-PR results, and the SDK decision (graduate/park). No version bump (CI/dev tooling). No tag, no deploy.

- [ ] **Step 2: Commit + push** the handoff to theme `main`.

---

## Self-review (coverage)

- Spec §4 (Phase 1 local dev-loop + CLAUDE.md home, not a tab) → Task 0 Step 2 + Task 1. ✓
- Spec §5 (Phase 2: security-review + claude-review, fork-guard/SHA-pin/path-scope/Sonnet/cost, prompt-as-file, both repos) → Tasks 2–4. ✓
- Spec §5.3 secret via stdin → Task 0 Step 1. ✓
- Spec §6 (Phase 3 SDK spike + graduate→own-repo / park; not in plugin) → Task 6. ✓
- Spec §7 (no tab; no new repo for CI; SDK-graduation = the new-repo case) → encoded in Task 1 doc + Task 6 Step 3 decision template. ✓
- Spec §9 (no version bump) → stated in header + Task 7. ✓
- Spec §11 success criteria → Task 5 (P2 caught/ignored/no-spend), Task 1 (P1 documented; owner probe), Task 6 (P3 written decision). ✓
- Owner-only steps (🧑) explicitly separated from agent steps (🤖) so an executor never blocks on the API key or the owner's Claude Code client.
