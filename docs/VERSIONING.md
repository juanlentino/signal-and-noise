# Versioning

How releases work for Signal & Noise. Read this whenever you're about to bump the `Version:` header in [style.css](../style.css) or write a CHANGELOG entry.

## SemVer baseline

We follow [Semantic Versioning](https://semver.org/) strictly. No caps on minor or patch numbers — they grow as needed. Format: `MAJOR.MINOR.PATCH`.

| Bump | When | Examples on this project |
|---|---|---|
| **MAJOR** | Breaking change | Removed/renamed public API, settings schema migration, behavioural shift requiring user action. v6 → v7.0.0 was an architectural shift + (then-active) cap rollover — under current rules, a major requires an actual breaking change. |
| **MINOR** | New user-visible capability | New page, new admin tool, new module like the catalog vocabulary rollout (v7.1.0). |
| **PATCH** | Fix / perf / calibration / refactor | Bug fix, accessibility fix, visual spacing tweak, CSS cleanup, internal refactor with no user-visible change. |

**Definition of "breaking" on this project:** removed or renamed public API, settings schema change without a migration, or a behavioural shift that requires user action (e.g. clearing cache, re-seeding content, manual file edits). A pure bug fix is not breaking even if it changes observable output.

## Cap policy: none

This project follows the global versioning rule from `~/.claude/CLAUDE.md`: **no caps on minor or patch numbers.** They grow as needed. Majors gate on actual breaking changes per SemVer (the table above) — never on counter math.

### Historical context (2026-05-26 — caps dropped)

This project previously overrode the global rule with `7 patches per minor` + `5 minors per major` caps. The caps were a discipline scaffolding — they forced periodic strategic thinking about majors when nothing else was triggering it.

The caps were dropped 2026-05-26 after the v4.4.x deep audit revealed they were producing fictional majors. v5.0.0 was scoped to "1 REMOVE (orphaned option) + counter reset" — not an actual breaking change by SemVer's definition. The cap was forcing a major version that wasn't earned by the codebase's actual semantic state.

What replaced the caps:

- **Roadmap brainstorm-checkpoints** — explicit deliberation moments before each new minor or major (see [docs/superpowers/specs/2026-05-26-roadmap-to-v5-and-v10-design.md](superpowers/specs/2026-05-26-roadmap-to-v5-and-v10-design.md))
- **Audit findings docs** — surface actual issues that warrant patches / minors / majors based on what the code is doing, not what the counter says
- **Post-ship cycle template** (QA → Bugfix → UI/UX → Gate) — structured response to live findings
- **Pre-major audit / scope docs** (e.g., [v5.0.0-scope.md](https://github.com/juanlentino/signal-and-noise-tools/blob/main/docs/superpowers/specs/2026-05-26-v5.0.0-scope.md)) — concrete inventory of what a major would actually need to break

Together these provide stronger triggers for version transitions than the cap rule did. The cap is gone; the discipline isn't.

### Practical impact

- `v4.4.0` → `v4.5.0` → `v4.6.0` → ... is valid indefinitely as new minors ship; no forced rollover to `v5.0.0`.
- `v4.4.0` → `v4.4.1` → `v4.4.2` → ... → `v4.4.20` is valid; patches accumulate without forcing a minor.
- `v5.0.0` happens when actual breaking changes accumulate enough to warrant it, OR when a coherent architectural milestone deliberately batches them.

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

Mention any project-specific notes (defensive migrations, schema
shims, etc).

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
- **Long-form rationale is welcome.** This isn't a public-facing changelog — future-you reads it more than anyone else. Document the WHY, especially for non-obvious decisions (defensive migrations, design reverts, accessibility gaps closed).
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
```

(Historical note: this entry pre-dates the 2026-05-26 cap removal. The original entry included a "Why MINOR (rolling over from .7)" paragraph explaining the cap-driven rollover; that rationale no longer applies under the current "no caps" policy.)

## When in doubt

- **Bump it.** The cost of an extra version is essentially zero (it's just a tag + a CHANGELOG line). The cost of forgetting to bump is harder to recover from later.
- **Document the reasoning.** Especially for rollovers and edge cases. The CHANGELOG is the durable record of "why did we choose this number?"
- **End-of-session bump.** If a session contains many small changes, you can ship them under a single version at the end. The intermediate commits don't all need version bumps; the final commit + tag captures the session's net delta.

## See also

- Global versioning rules: `~/.claude/CLAUDE.md`
- Project overrides: [CLAUDE.md](../CLAUDE.md)
- Smoke test workflow: [.github/workflows/smoke-test.yml](../.github/workflows/smoke-test.yml)
- Self-updater: [inc/updater.php](../inc/updater.php) (how WP polls origin/main and offers updates)
