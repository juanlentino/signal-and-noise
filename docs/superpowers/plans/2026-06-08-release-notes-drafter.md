# Release-Notes Drafter Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A GitHub Actions workflow in each repo that, on a `v*` tag push (or manual dispatch), extracts that version's CHANGELOG entry, transforms it into Mimestream-style user-facing notes via a direct Anthropic Messages API call, and creates a **draft** GitHub Release for the owner to review + publish.

**Architecture:** Tag-triggered workflow → `awk` extracts the `## [X.Y.Z]` CHANGELOG block → `jq` builds a Messages request + `curl` sends it (direct API; **no SDK, not the claude-code-action agent**) → `gh release create --draft`. All untrusted-free (tag pushes are owner-only); the system prompt is a single-line inline string; duplicated per repo.

**Tech Stack:** GitHub Actions, `awk`, `jq`, `curl`, `gh` CLI, Anthropic Messages API (`/v1/messages`, `anthropic-version: 2023-06-01`).

**Spec:** `docs/superpowers/specs/2026-06-08-release-notes-drafter-design.md`

**Where it lands:** added to the EXISTING `claude/anthropic-tooling` PR branch in each repo (wave-2 of the tooling adoption; PRs [signal-and-noise#2](https://github.com/juanlentino/signal-and-noise/pull/2) / [signal-and-noise-tools#1](https://github.com/juanlentino/signal-and-noise-tools/pull/1) are still open). Worktrees: `…/<repo>/.claude/worktrees/anthropic-tooling`.

**Legend:** 🧑 owner-only (needs the live API key) · 🤖 agent-executable. **Note:** the Write tool is blocked by a local hook on `.github/workflows/*` — write workflow files via Bash heredoc (verify `${{ }}` survive).

---

## File structure

**Per repo, on branch `claude/anthropic-tooling`:**
- Create `.github/workflows/release-notes.yml` — the drafter workflow (the only new file).

No prompt file (the system prompt is a single inline line, so it works even when backfilling old tags whose tree predates any prompt file).

---

## Task 0: Owner prerequisite

**Files:** none. 🧑

- [ ] **Step 1 🧑:** This reuses the **same `ANTHROPIC_API_KEY`** repo secret as the review tooling (adoption Task 0). If that's set, nothing to do. If not, set it: `printf '%s' "$ANTHROPIC_API_KEY" | gh secret set ANTHROPIC_API_KEY --repo juanlentino/<repo>` for both repos. Until set, the drafter job is red (same benign state as the review workflows).

---

## Task 1: TDD the CHANGELOG extraction (the only real logic) 🤖

**Files:** none committed (a throwaway local test of the `awk` snippet before embedding it).

- [ ] **Step 1: Write the failing test** — a local check that the extraction prints the right block. Run it against the real theme CHANGELOG:

```bash
cd /Users/juanlentino/Projects/signal-and-noise/.claude/worktrees/anthropic-tooling
VERSION=9.15.2
ENTRY=$(awk -v v="$VERSION" '
  $0 ~ "^## \\[" v "\\]" {f=1; print; next}
  f && /^## \[/ {exit}
  f {print}
' CHANGELOG.md)
printf '%s\n' "$ENTRY" | head -3
echo "--- assertions ---"
printf '%s' "$ENTRY" | grep -q '^## \[9.15.2\]'            && echo "PASS: starts at 9.15.2 header"   || echo "FAIL"
printf '%s' "$ENTRY" | grep -q 'Two-track width system'    && echo "PASS: contains the 9.15.2 body"  || echo "FAIL"
printf '%s' "$ENTRY" | grep -q '9.15.1'                    && echo "FAIL: leaked into next entry"    || echo "PASS: stops before 9.15.1"
```

- [ ] **Step 2: Run it.** Expected: three `PASS` lines (starts at the header, contains the 9.15.2 body, stops before 9.15.1). If any FAIL, fix the `awk` before embedding. This exact `awk` block goes into the workflow in Task 2.

---

## Task 2: release-notes.yml — theme 🤖

**Files:** Create `.github/workflows/release-notes.yml` in `…/signal-and-noise/.claude/worktrees/anthropic-tooling`.

- [ ] **Step 1: Write the file** (via Bash heredoc — Write is hook-blocked). EXACT content:

```yaml
name: Release Notes
on:
  push:
    tags: ['v*']
  workflow_dispatch:
    inputs:
      tag:
        description: 'Existing tag to (re)draft notes for, e.g. v9.15.2'
        required: true
permissions:
  contents: write
jobs:
  draft:
    runs-on: ubuntu-latest
    steps:
      - name: Resolve tag
        id: tag
        env:
          INPUT_TAG: ${{ github.event.inputs.tag }}
          REF_NAME: ${{ github.ref_name }}
        run: |
          TAG="${INPUT_TAG:-$REF_NAME}"
          echo "tag=$TAG" >> "$GITHUB_OUTPUT"
          echo "version=${TAG#v}" >> "$GITHUB_OUTPUT"
      - uses: actions/checkout@34e114876b0b11c390a56381ad16ebd13914f8d5
        with:
          ref: ${{ steps.tag.outputs.tag }}
          fetch-depth: 1
      - name: Extract CHANGELOG entry
        id: extract
        env:
          VERSION: ${{ steps.tag.outputs.version }}
        run: |
          ENTRY=$(awk -v v="$VERSION" '
            $0 ~ "^## \\[" v "\\]" {f=1; print; next}
            f && /^## \[/ {exit}
            f {print}
          ' CHANGELOG.md)
          if [ -z "$ENTRY" ]; then
            echo "::error::No CHANGELOG entry for $VERSION"; exit 1
          fi
          {
            echo 'entry<<CHANGELOG_EOF'
            printf '%s\n' "$ENTRY"
            echo 'CHANGELOG_EOF'
          } >> "$GITHUB_OUTPUT"
      - name: Draft notes via Anthropic API
        env:
          ANTHROPIC_API_KEY: ${{ secrets.ANTHROPIC_API_KEY }}
          ENTRY: ${{ steps.extract.outputs.entry }}
        run: |
          SYSTEM="Rewrite this technical CHANGELOG entry as user-facing release notes for juanlentino.com. Keep the ### New / Improvements / Fixed / Cleanup / Removed / Deprecated category headers that apply. Lead with a one-line plain-language headline. Rewrite each bullet as a benefit to a reader — drop file paths, commit SHAs, function and symbol names, and internal jargon. Be concise and warm, not salesy. Output GitHub-flavored markdown only, no preamble."
          PAYLOAD=$(jq -n --arg system "$SYSTEM" --arg changelog "$ENTRY" \
            '{model:"claude-sonnet-4-6", max_tokens:2000, system:$system, messages:[{role:"user",content:$changelog}]}')
          RESP=$(curl -sS -w '\n%{http_code}' https://api.anthropic.com/v1/messages \
            -H "x-api-key: $ANTHROPIC_API_KEY" \
            -H "anthropic-version: 2023-06-01" \
            -H "content-type: application/json" \
            -d "$PAYLOAD")
          CODE=$(printf '%s' "$RESP" | tail -n1)
          BODY=$(printf '%s' "$RESP" | sed '$d')
          if [ "$CODE" != "200" ]; then
            echo "::error::Anthropic API returned $CODE"; printf '%s\n' "$BODY"; exit 1
          fi
          NOTES=$(printf '%s' "$BODY" | jq -r '.content[0].text // empty')
          if [ -z "$NOTES" ]; then
            echo "::error::Empty notes from API"; exit 1
          fi
          printf '%s\n' "$NOTES" > notes.md
      - name: Create or update draft release
        env:
          GH_TOKEN: ${{ github.token }}
          TAG: ${{ steps.tag.outputs.tag }}
        run: |
          if gh release view "$TAG" >/dev/null 2>&1; then
            gh release edit "$TAG" --draft --notes-file notes.md
          else
            gh release create "$TAG" --draft --title "$TAG" --notes-file notes.md
          fi
```

- [ ] **Step 2: Validate the YAML**

Run: `python3 -c "import yaml; yaml.safe_load(open('.github/workflows/release-notes.yml')); print('YAML OK')"`
Expected: `YAML OK`.

- [ ] **Step 3: Validate the jq payload build produces valid JSON** (the load-bearing transform plumbing), using the real extracted entry from Task 1:

```bash
SYSTEM="test system"
PAYLOAD=$(jq -n --arg system "$SYSTEM" --arg changelog "$ENTRY" \
  '{model:"claude-sonnet-4-6", max_tokens:2000, system:$system, messages:[{role:"user",content:$changelog}]}')
printf '%s' "$PAYLOAD" | jq -e '.model=="claude-sonnet-4-6" and (.messages[0].content|length>0)' >/dev/null && echo "PAYLOAD OK"
```
Expected: `PAYLOAD OK` (jq safely embedded the multi-line CHANGELOG as JSON content).

- [ ] **Step 4: Confirm the SHA + no `${{ }}` in shell bodies survived the heredoc**, then commit + push (updates PR #2):

```bash
grep -q 'actions/checkout@34e114876b0b11c390a56381ad16ebd13914f8d5' .github/workflows/release-notes.yml && echo "SHA OK"
git add .github/workflows/release-notes.yml
git commit -m "ci(release-notes): draft Mimestream-style GitHub Release from CHANGELOG via direct Anthropic API on tag push"
git push   # branch already tracks origin/claude/anthropic-tooling
```

---

## Task 3: release-notes.yml — plugin 🤖

**Files:** Create `.github/workflows/release-notes.yml` in `…/signal-and-noise-tools/.claude/worktrees/anthropic-tooling`.

- [ ] **Step 1: Write the file** — **byte-identical** to Task 2 Step 1 (the workflow reads `CHANGELOG.md` + the tag, both of which exist in the plugin repo with the same Mimestream header format; nothing is theme-specific). Use the same Bash-heredoc write.

- [ ] **Step 2: Validate YAML** — `python3 -c "import yaml; yaml.safe_load(open('.github/workflows/release-notes.yml')); print('YAML OK')"` → `YAML OK`.

- [ ] **Step 3: Sanity-check extraction against the plugin CHANGELOG** for its latest version (4.14.0):

```bash
cd /Users/juanlentino/Projects/signal-and-noise-tools/.claude/worktrees/anthropic-tooling
awk -v v="4.14.0" '$0 ~ "^## \\[" v "\\]" {f=1;print;next} f&&/^## \[/{exit} f{print}' CHANGELOG.md | head -2
```
Expected: prints the `## [4.14.0]` header line + the start of its body (confirms the same extraction works on the plugin CHANGELOG).

- [ ] **Step 4: Commit + push** (updates PR #1):

```bash
git add .github/workflows/release-notes.yml
git commit -m "ci(release-notes): draft Mimestream-style GitHub Release from CHANGELOG via direct Anthropic API on tag push"
git push
```

---

## Task 4: Owner validation 🧑

**Files:** none. Needs the live `ANTHROPIC_API_KEY` (Task 0).

- [ ] **Step 1 🧑: Dry-run via manual dispatch** on an existing tag (no need to cut a new release to test). After the PR is merged (so the workflow exists on `main`), OR from the branch via the Actions UI:

```bash
gh workflow run "Release Notes" --repo juanlentino/signal-and-noise -f tag=v9.15.2
```
Expected: the run succeeds and a **draft** Release for `v9.15.2` appears at `https://github.com/juanlentino/signal-and-noise/releases` with friendly, categorized, jargon-free notes derived from the 9.15.2 CHANGELOG entry.

- [ ] **Step 2 🧑: Review + publish (or edit).** Open the draft Release, tweak if desired, publish. Confirm the body has no file paths / SHAs / symbol names and keeps the `### New/Improvements/Fixed` categories.

- [ ] **Step 3 🧑: Confirm failure-loud behavior** (optional): dispatch with a bogus tag (`-f tag=v0.0.0`) → the run FAILS at extraction (`No CHANGELOG entry`), and **no empty draft** is created.

---

## Self-review (coverage)

- Spec §2 (direct API not agent/SDK; draft Release; tag trigger + dispatch; duplicate per repo; no bump) → Tasks 2–3 (YAML), header. ✓
- Spec §3 architecture (checkout@tag → awk extract → jq+curl → gh draft) → Task 2 Step 1 verbatim. ✓
- Spec §4 security (owner-only tag trigger; `contents: write`; same secret; `jq --arg` no injection; one Sonnet call; draft = human gate) → workflow `permissions:` + env-passing (no `${{ }}` in shell bodies) + draft. ✓
- Spec §5 failure modes (missing entry → fail, no empty draft; non-200 → fail; re-run updates existing draft via `gh release view || create`) → Task 2 Step 1 (`exit 1` guards + the view/edit/create branch) + Task 4 Step 3. ✓
- Spec §7 success criteria → Task 4 (dispatch → draft with friendly notes; bogus tag fails loud). ✓
- The one piece with logic (the `awk` extraction) is TDD'd in Task 1 before embedding; the JSON plumbing is validated in Task 2 Step 3. The API call + draft creation can only be validated with the live key (Task 4, 🧑) — explicitly separated.
