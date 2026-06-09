# Release-Notes Drafter — Design Spec

**Date:** 2026-06-08
**Status:** Design approved (brainstorm complete). Awaiting spec → writing-plans.
**Type:** CI/dev tooling, cross-repo (theme + plugin). No shipped theme/plugin code; **no version bump**.
**Repos:** `juanlentino/signal-and-noise` + `juanlentino/signal-and-noise-tools` (both PUBLIC, default `main`).
**Wave 2 of:** `docs/superpowers/specs/2026-06-08-anthropic-tooling-adoption-design.md` (the "what replaces the parked SDK" answer).

## 1. Problem / motivation

Releases ship with a **technical** CHANGELOG (file paths, commit SHAs, internal symbols, escaping jargon) and **no human-facing release notes**. There is a standing project aspiration for **Mimestream-style** notes — categorized (`New / Improvements / Fixed`), visual, friendly, **separate from the technical CHANGELOG**. This was the parked PHP SDK's most-appealing out-of-band use case; it is deliverable without the SDK via a single direct API call in a GitHub Actions workflow.

The CHANGELOG already uses the Mimestream category headers, so the transform is: **rewrite each technical bullet into user-benefit prose** (drop paths/SHAs/jargon), keeping the categories — turning `## [X.Y.Z] - date — summary` + `### New …` into reader-facing notes.

## 2. Decisions made during brainstorm

| Decision | Choice | Why |
|---|---|---|
| Transform mechanism | **Direct Anthropic Messages API call** (`curl` + `jq`) | A single deterministic text transform. Cheaper + deterministic vs the `claude-code-action` agent (overkill, non-deterministic, more tokens); dependency-free vs the SDK (no vendoring). Proves the SDK-park decision. |
| Output target | **Draft GitHub Release** per tag | Natural home for release notes; **draft = human reviews/edits/publishes** (AI never auto-publishes public prose). Clean: new tags have no existing Release to conflict with (verified — theme's Releases stop at ~v7.0.0; plugin has none; tags don't auto-create Releases). |
| Trigger | **`push: tags: ['v*']`** + `workflow_dispatch` (tag input for backfill/re-run) | The canonical "a release happened" event; owner-only (no fork/PR token-abuse vector). |
| Scope | One `release-notes.yml` per repo, **duplicated** | YAGNI at 2 repos; same centralization trigger as the review workflows (3rd repo / drift). |
| Versioning | **No bump** | CI/dev tooling, not shipped theme/plugin code. |

## 3. Architecture (per repo)

```
push tag v* (or workflow_dispatch with tag input)
  └─ .github/workflows/release-notes.yml
       1. actions/checkout @SHA  (ref: the tag)
       2. extract CHANGELOG.md entry for the tag's version  (awk: `## [X.Y.Z]` → next `## [`)
       3. transform: jq builds the Messages payload (safe escaping) → curl POST api.anthropic.com/v1/messages → jq parses .content[0].text
       4. gh release create <tag> --draft --title "<tag> — <headline>" --notes-file <drafted.md>
```

No request-time anything in WordPress; this is purely a CI step touching the GitHub API + the Anthropic API.

### Components
- **`.github/workflows/release-notes.yml`** (each repo) — the workflow.
- **CHANGELOG extraction** (a `run:` step, `awk`) — pulls the `## [X.Y.Z]` section up to the next `## [`. The version = the tag minus the leading `v`. Fails loudly if the section is empty/absent.
- **Transform step** — `jq -n --arg system "<rules>" --arg changelog "<entry>" '{model:"claude-sonnet-4-6", max_tokens:2000, system:$system, messages:[{role:"user",content:$changelog}]}'` → `curl -sS https://api.anthropic.com/v1/messages -H "x-api-key: $ANTHROPIC_API_KEY" -H "anthropic-version: 2023-06-01" -H "content-type: application/json" -d @-` → `jq -r '.content[0].text'`. Fails loudly on non-200 or missing `.content`.
- **Draft-release step** — `gh release create "$TAG" --draft --title … --notes-file notes.md` (uses the auto-provided `GITHUB_TOKEN`).
- **System prompt (the transform rules)** — committed inline in the workflow (or a `.github/release-notes-prompt.md` read like the review prompt; **inline for v1** — it's short and single-purpose): "Rewrite this technical CHANGELOG entry as user-facing release notes for juanlentino.com. Keep the `### New / Improvements / Fixed / Cleanup / Removed / Deprecated` categories that apply. Lead with a one-line plain-language headline. Rewrite each bullet as a benefit to a reader — drop file paths, commit SHAs, function/symbol names, and internal jargon. Be concise and warm, not salesy. Output GitHub-flavored markdown only, no preamble."

## 4. Security & cost

- **No fork/PR vector:** the trigger is a tag push, which only the owner can do; there is no untrusted-PR path to the API key (unlike the review workflows, which are additionally fork-guarded).
- **Permissions:** `contents: write` (to create the Release). No other scopes.
- **Secret:** the same `ANTHROPIC_API_KEY` repo secret the review tooling uses — so this activates together with Phase 2.
- **Injection:** the CHANGELOG entry is repo-owned (trusted) and passed to `jq --arg` (safe quoting) → it is API *content*, never shell-interpolated. No script-injection surface.
- **Cost:** one `claude-sonnet-4-6` Messages call per release (~hundreds of output tokens). Negligible.
- **Human gate:** the Release is created as a **draft** — nothing is public until the owner edits + publishes.

## 5. Failure modes

- **No CHANGELOG entry for the tag** → the extraction step exits non-zero; the job fails; **no empty draft** is created.
- **API non-200 / missing `.content`** → the transform step fails the job; no draft.
- **Key absent** (before Phase-2 activation) → the call fails; the job is red (same benign "set the key" state as the review workflows). The drafter only does useful work once the key is set.
- **Re-run on an existing draft** → `gh release create` errors if a release for the tag exists; the workflow uses `gh release create … || gh release edit … --draft` semantics so a re-run updates the existing draft rather than failing (detail finalized in the plan).

## 6. Non-goals (YAGNI)

- No auto-publish — drafts only (human reviews public-facing prose).
- No on-site `/releases` page yet — the draft Release is v1; the notes *could* later feed a site page (separate future spec). The Mimestream aspiration's page form is explicitly deferred.
- No PHP SDK; not `claude-code-action` (agent overkill for a transform).
- No reusable-workflow centralization (duplicate per repo; same trigger to revisit as the review workflows).
- No version bump.

## 7. Success criteria

- Pushing a `vX.Y.Z` tag (with the key set) produces a **draft GitHub Release** for that tag whose body is friendly, categorized, Mimestream-style notes derived from that version's CHANGELOG entry — no file paths/SHAs/jargon.
- A docs-only/no-CHANGELOG tag (shouldn't happen) fails loudly rather than drafting an empty release.
- `workflow_dispatch` can backfill notes for an arbitrary existing tag.
- Existing `ci.yml` / `deploy.yml` / the review workflows are untouched; no version bump.

## 8. Cross-references

- Wave 1 (CI review tooling): the adoption spec/plan; shares the `ANTHROPIC_API_KEY` secret + the duplicate-per-repo / centralization-trigger pattern.
- Mimestream-style release-notes aspiration (categorized, human-readable, separate from the technical CHANGELOG).
- CHANGELOG format: Mimestream categories already in use in both repos' `CHANGELOG.md`.
