# Versioning

How releases work for Signal & Noise. Read this before opening a PR that changes shippable code, and before cutting a release. Short version: **a PR adds an `## [Unreleased]` bullet and closes an issue; only a release stamps `Version:`.**

## The WordPress shape (since 2026-09-11)

`X.Y.0` is a **release**, `X.Y.Z` is a **fix** to it, and `X` rolls by itself
when `Y` would reach 10 (`13.9.0 → 14.0.0`). This is how WordPress core
numbers (4.9 → 5.0) and how WooCommerce, Jetpack, Yoast and ACF number, so a
WordPress reader already knows how to read it. Three components always
(`13.0.0`, the WooCommerce form — not core's `6.8`: `version_compare( '13.0',
'13.0.0' )` calls the two-part form *older*, and every tool here parses three
integers).

| Component | Means | Moves when |
|---|---|---|
| `X` | the tens digit of the release count | `Y` would become 10 |
| `Y` | a **release** — one arc of merged work, features or fixes | `tools/cut-release.sh release` |
| `Z` | a **fix** to a shipped release | `tools/cut-release.sh fix` |

**`X` is not a breaking-change flag.** A release that breaks something —
removes or renames a public hook, changes a settings schema without a
migration, shifts behaviour so that a site has to act — says **BREAKING** in
its CHANGELOG headline and release title, on whatever number it happens to
get. There is no `major` argument; the script refuses it and says why.

**Cadence is the other half.** Each component stays two digits only if
releases are cut per **arc**, not per merged fix. A `docs/`, `tools/`,
`.github/`-only merge never gets its own cut.

### Why this replaced SemVer (2026-09-11)

The rule until this date was strict SemVer with no caps. The plugin's minor
reached **110** in seventeen days because it counted merged fixes at a dozen a
day; its MAJOR moved once, because nothing broke — a signal with no receiver,
since the theme is the plugin's only integrator and both are owned here.
Owner: "I'm not liking the three digits" → "I'd go WordPress since it's a
WordPress plugin." The survey of what the Linux kernel, Apple, Ubuntu and
CalVer do about a number getting big is in the plugin repo,
`docs/proposals/2026-09-11-versioning-scheme.md`. The theme's first release
under the shape is **13.0.0** — the roll, not a break.

### Historical context (2026-05-26 — caps dropped; 2026-09-11 — SemVer dropped)

Until 2026-05-26 this project had cap overrides (7 per minor, 5 per major)
that forced fictional majors; the v4.4.x audit removed them and the project
ran strict SemVer for three and a half months. The WordPress shape is not a
cap: `X` rolling at `Y = 10` claims nothing about the release, which is the
difference that made the old caps dishonest and this rule not.

## Worked example: why v12.0.0 was a major (and dark mode was not the reason)

Recorded 2026-08-20 because it is the cleanest case of the rule the cap policy
above exists to protect, and because the obvious answer was wrong.

v12.0.0 shipped **dark mode**. That is a big, visible, user-facing capability,
and it is a **MINOR**. It removes nothing, renames nothing, moves no floor, and
requires no user action: it follows the OS, degrades to the light palette, and
writes nothing to the database. "Feels large" is not a SemVer criterion.

The major was earned by something the dark mode *exposed*. The site has never
served one palette (`theme.json`, the shipped `high-contrast` variation, and now
`dark`), and `get-design-tokens` had always returned `colors` as a flat
`slug => hex` map describing exactly one of them. Reshaping that field into a
struct is a **removed/renamed public response on the Abilities surface**, with
the old shape deleted rather than aliased. That is the break.

Every manufactured alternative was checked and rejected first:

| Candidate | Verdict |
|---|---|
| Deprecation-ladder removals | Empty. No `@deprecated`, no `_deprecated_function` anywhere. |
| WP floor 7.0 to 7.1 | Manufactured. Tested to 7.1, but nothing depends on a 7.1 feature. |
| PHP floor 8.3 to 8.4 | Same. Cloudways runs 8.4; no 8.4 syntax is used. |
| theme.json v3 to v4 | WP has not shipped a v4 schema. Nothing to migrate. |
| Back-compat shims | The one in `abilities-registration.php` is documented "for tests only". Not public. |

**The test to apply:** would you still make this change if the version number
were not in question? If yes, it is earned. If the change exists to justify the
number, it is the fictional major the caps were dropped to prevent.

The five follow-on releases (v12.0.1 through v12.0.4) were all PATCH. One of
them fixed a live outage and another rewrote 68 CSS rules, and neither touches
the public contract, which is the only thing MAJOR measures.

## What is shippable (and therefore needs an Unreleased bullet)

A PR never stamps a version, so the question is no longer "does this bump?" but
"does this change what a visitor or editor gets?" If yes, the PR adds a bullet
under `## [Unreleased]`; the version digit is decided later, once, at the cut.

| Change | Shippable? |
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

A "content-only template edit" is changing the words inside a `<!-- wp:paragraph -->` block when the change is purely editorial (typo fix, copy refinement, factual correction). It is NOT shippable because:

1. The deploy mechanism is the same (theme update via WP self-updater).
2. The CSS, JS, and structural HTML are unchanged.
3. The user-perceptible change is words, not capability.

**Edge case:** if a copy edit accompanies a structural change (e.g. you rewrite a paragraph AND change its block structure), the structural part is shippable and takes a bullet.

## Workflow

**A pull request does not bump `Version` and does not tag.** A version marks a
release. It stopped meaning that when every session stamped one, and the cost of
that habit is an 8,000-line CHANGELOG and a number that records sittings rather
than shipments.

### What a PR does

1. **Make the change.** Edit `inc/`, `templates/`, `assets/`, etc.
2. **Close an issue.** Every PR carries `Fixes #N`. No issue, no PR.
3. **Add an `## [Unreleased]` bullet** in [CHANGELOG.md](../CHANGELOG.md) if the
   change is shippable (see the table above). One line under `### Added`,
   `### Changed`, `### Fixed` or `### Removed`. Not a memoir — the reasoning
   belongs in the PR body, which is where a reviewer is already looking.
4. **Leave `Version:` alone.** In `style.css`, in `readme.txt`, everywhere.

### What a release does

A release is a deliberate, separate act: `tools/cut-release.sh`.

- Stamps `Version:` in `style.css` **and** `Stable tag:` in `readme.txt`, which
  must never drift apart.
- Promotes `## [Unreleased]` to `## [X.Y.Z] - YYYY-MM-DD - headline` and archives
  the previous cut into `docs/changelog/`.
- Creates or moves the GitHub milestone.
- **Prints** the tag command rather than running it, unless given `--tag`.
- Refuses a dirty worktree, and refuses an empty `Unreleased` — nothing to cut
  is not a release.

### Choosing the digit, once, at the cut

| Argument | Means |
|---|---|
| **release** | An arc closed: `Y + 1` (or `X + 1`, `Y = 0` when `Y` was 9). Features and fixes alike. |
| **fix** | Something that must reach sites before the next arc: `Z + 1`. |

A cut is a release unless it is a hotfix to the release that just shipped.
If the arc contains a breaking change, the headline starts with `BREAKING:`.

## CHANGELOG conventions

[CHANGELOG.md](../CHANGELOG.md) follows roughly the [Keep a Changelog](https://keepachangelog.com/) format with project-specific tweaks:

- **Newest at top.**
- **One section per RELEASE.** Heading: `## [X.Y.Z] - YYYY-MM-DD - one-line summary`, written by `cut-release.sh`, not by hand.
- **Body sections** under each version, when relevant: `### Added`, `### Changed`, `### Fixed`, `### Removed`, `### Deprecated`. Inline-link to source files (`[inc/foo.php](inc/foo.php)`) so the entry is browseable from GitHub.
- **Long-form rationale is welcome.** This isn't a public-facing changelog — future-you reads it more than anyone else. Document the WHY, especially for non-obvious decisions (defensive migrations, design reverts, accessibility gaps closed).
- **`## [Unreleased]`** is the working log. It accumulates across PRs for as long as it takes, and is emptied only by a release. The root file holds Unreleased plus the current cut; everything older lives in `docs/changelog/`.

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

- **Add an Unreleased bullet.** "When in doubt, bump it" is **REVOKED**. The cost
  of an extra version is not zero: it is a heading nobody will read, a tag that
  marks nothing, and one more line between a reader and the release they were
  looking for. The cost of an extra Unreleased bullet is one line, and a bullet
  that turns out not to matter is deleted at the cut for free.
- **Document the reasoning in the PR.** The CHANGELOG records *what* shipped;
  the PR records *why*, next to the diff that shows it.
- **Do not stamp a version to mark that you did something.** A session is not a
  release. If the work is worth recording, it is worth an issue and a bullet.

## See also

- Global versioning rules: `~/.claude/CLAUDE.md` (the SemVer line there is overridden for these two repos by this file)
- Project overrides: CLAUDE.md (`CLAUDE.md`, local-only)
- Release script: [tools/cut-release.sh](../tools/cut-release.sh) — the only thing that stamps a version
- Changelog archive: [docs/changelog/](changelog/) — everything older than the current cut
- Smoke test workflow: [.github/workflows/smoke-test.yml](../.github/workflows/smoke-test.yml)
- Self-updater: [inc/wp-update-integration.php](../inc/wp-update-integration.php) (how WP polls origin/main and offers updates)
