# Versioning

How releases work for Signal & Noise. Read this whenever you're about to bump the `Version:` header in [style.css](../style.css) or write a CHANGELOG entry.

## SemVer baseline

We follow [Semantic Versioning](https://semver.org/) with project-specific caps (see below). Format: `MAJOR.MINOR.PATCH`.

| Bump | When | Examples on this project |
|---|---|---|
| **MAJOR** | Breaking change | Removed/renamed public API; settings schema change without a migration; behavioural shift requiring user action. v6 → v7.0.0 was driven by an architectural shift + an accumulated minor cap, not a literal API break — that's also valid: a coherent milestone where the project's own minor-cap rule rolled at the natural boundary. |
| **MINOR** | New user-visible capability | New page, new admin tool, new module like the catalog vocabulary rollout (v7.1.0). Also the rollover destination when the patch cap fires (see below). |
| **PATCH** | Fix / perf / calibration / refactor | Bug fix, accessibility fix, visual spacing tweak, CSS cleanup, internal refactor with no user-visible change. |

**Definition of "breaking" on this project:** removed or renamed public API, settings schema change without a migration, or a behavioural shift that requires user action (e.g. clearing cache, re-seeding content, manual file edits). A pure bug fix is not breaking even if it changes observable output.

## Project caps (override the global rule)

This project's [CLAUDE.md](../CLAUDE.md) overrides the global "Apple-style 3-patch" rule with these caps:

- **Patch cap: 7 per minor.** `7.1.0` through `7.1.7` are all valid before the next change rolls to `7.2.0`. The cap is meant to nudge accumulated patches into a coherent minor at a natural breakpoint, rather than letting the patch number grow indefinitely.
- **Minor cap: 5 per major.** `7.0` through `7.5` are all valid before the next change rolls to `8.0.0`.

### Rollover semantics

When the cap fires, the version digit answers _what kind of change_ NEXT and _that we hit the cap_, not what kind of change THIS is. So:

```
v7.1.7 (patch cap reached)
  ↓ small calibration that would normally be a patch
v7.2.0  ← rolls over to next minor because cap is full
```

The change itself is still a calibration. The rollover just changes the version number it ships under. **Document the rollover in the CHANGELOG entry** so future-you can see it wasn't a "new capability" semantically:

```markdown
## [7.2.0] — /services № markers — breathing room

[...]

Why MINOR (rolling over from .7): the project's patch cap is 7 per
minor. v7.1.0 through v7.1.7 used the full cap, so this calibration
rolls to v7.2.0 even though it would normally be a patch.
```

## What does and does NOT bump version

| Change | Bump? |
|---|---|
| Code change in `inc/`, `templates/`, or `assets/` | **Yes** |
| New CSS rule, removed CSS rule, layout change | **Yes** |
| Migration logic added or changed | **Yes** |
| Block-template markup change that affects rendering | **Yes** |
| Content-only template edit (copy change in a static block) | **No** — see "Content edits" below |
| `docs/` updates (README, this file, MONITORING.md, etc.) | **No** |
| `CLAUDE.md` update | **No** |
| `.github/workflows/` update | **Yes** if it changes deploy/test behaviour, else **No** |
| `CHANGELOG.md` only | **No** (CHANGELOG is paired with whatever change it documents) |

### Content edits

A "content-only template edit" is changing the words inside a `<!-- wp:paragraph -->` block when the change is purely editorial (typo fix, copy refinement, factual correction). It does NOT bump version because:

1. The deploy mechanism is the same (theme update via WP self-updater).
2. The CSS, JS, and structural HTML are unchanged.
3. The user-perceptible change is words, not capability.

**Edge case:** if a copy edit accompanies a structural change (e.g. you rewrite a paragraph AND change its block structure), bump for the structural part.

## Workflow

The full release workflow for a single change:

1. **Make the change.** Edit `inc/`, `templates/`, `assets/`, etc.
2. **Bump `Version:` in [style.css](../style.css)** following the rules above. Mid-session it's fine to bump on each commit; per the global rule, "version bumps and tags only at END of session" applies to the FINAL bump that gets tagged.
3. **Add a CHANGELOG entry** at the top of [CHANGELOG.md](../CHANGELOG.md). Format: `## [X.Y.Z] — One-line summary`. Body should explain WHAT changed, WHY, and (when relevant) WHY this version digit.
4. **Commit** with a message starting with `vX.Y.Z: brief description`. Body should mirror the CHANGELOG entry's content but in commit-message form (paragraphs, not bullet-heavy).
5. **Push** to `origin/main` (this project ships from `main` via the WP self-updater).
6. **Tag** with `git tag -a vX.Y.Z -m "vX.Y.Z — One-line summary"` then `git push origin vX.Y.Z`. Tags happen at end of session for the final version reached, not per commit.
7. **Smoke test** runs automatically on every push to main ([.github/workflows/smoke-test.yml](../.github/workflows/smoke-test.yml)).
8. **Click Update** in WP Admin to deploy to Cloudways.

### Commit message format

```
vX.Y.Z: one-line summary in the commit subject

Body paragraph explaining what changed and why. Reference the
CHANGELOG entry's reasoning if that's where the rationale lives.

Mention any project-specific notes (cap rollover, defensive
migrations, etc).

Co-Authored-By: <if AI assisted>
```

### Tag format

```bash
git tag -a v7.2.0 -m "v7.2.0 — /services № markers breathing room"
git push origin v7.2.0
```

- Lowercase `v` prefix.
- Annotated tag (`-a`), not lightweight.
- Tag message is the same one-line summary as the CHANGELOG entry's heading.

## CHANGELOG conventions

[CHANGELOG.md](../CHANGELOG.md) follows roughly the [Keep a Changelog](https://keepachangelog.com/) format with project-specific tweaks:

- **Newest at top.**
- **One section per tagged version.** Heading: `## [X.Y.Z] — One-line summary`.
- **Body sections** under each version, when relevant: `### Added`, `### Changed`, `### Fixed`, `### Removed`, `### Deprecated`. Inline-link to source files (`[inc/foo.php](inc/foo.php)`) so the entry is browseable from GitHub.
- **Long-form rationale is welcome.** This isn't a public-facing changelog — future-you reads it more than anyone else. Document the WHY, especially for non-obvious decisions (cap rollovers, defensive migrations, design reverts, accessibility gaps closed).
- **`[Unreleased]`** section at the top accumulates changes during a long session before the final tag.

### Example entry

```markdown
## [7.2.0] — /services № markers — breathing room

The catalog-number markers (`№ 01` through `№ 06`) on the /services
cards rendered with only a 4px gap between the number and the card
heading. The number read as part of the heading rather than as an
eyebrow above it.

Fix: bumped each number's `margin-bottom` from `0` to `var:preset|spacing|10`
(8px) and removed the `0.25rem margin-top` from each heading.

Why MINOR (rolling over from .7): the project's patch cap is 7
per minor. v7.1.0 through v7.1.7 used the full cap.
```

## When in doubt

- **Bump it.** The cost of an extra version is essentially zero (it's just a tag + a CHANGELOG line). The cost of forgetting to bump is harder to recover from later.
- **Document the reasoning.** Especially for rollovers and edge cases. The CHANGELOG is the durable record of "why did we choose this number?"
- **End-of-session bump.** If a session contains many small changes, you can ship them under a single version at the end. The intermediate commits don't all need version bumps; the final commit + tag captures the session's net delta.

## See also

- Global versioning rules: `~/.claude/CLAUDE.md`
- Project overrides: [CLAUDE.md](../CLAUDE.md)
- Smoke test workflow: [.github/workflows/smoke-test.yml](../.github/workflows/smoke-test.yml)
- Self-updater: [inc/updater.php](../inc/updater.php) (how WP polls origin/main and offers updates)
