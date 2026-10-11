# Versioning

How releases work for Signal & Noise. Read this before opening a PR that changes shippable code, and before cutting a release. Short version: **a PR adds an `## [Unreleased]` bullet and closes an issue; only a release stamps `Version:`.**

## By date (since 2026-10-11)

A version is `YYYY.M.N`: the year, the month, and the cut's place in that
month. `2026.10.1`, `2026.10.2`, then `2026.11.1`. The plugin uses the same
scheme from the same day.

This theme is private. One site installs it, it is not on WordPress.org and
never will be. A version number has one reader, that site (and the agents that
work on it), and three jobs: always go up, say at a glance how old an install
is, and name a point to roll back to. A date does all three.

The two schemes before it assumed a reader who does not exist. SemVer (to
2026-09-11) warns API consumers about breakage, and there are none. The
WordPress shape (2026-09-11 to 2026-10-11: `X.Y.0` a release, `X.Y.Z` a fix,
`X` rolling when `Y` would reach 10) is how WordPress.org themes number, so
their readers can read it; it took this theme from 13.0.0 to 15.8.2 and the
plugin from 14.0.0 to 24.0.0 in a month. The history of both, with the
research behind the WordPress shape, is in the plugin's
`docs/proposals/2026-09-11-versioning-scheme.md`.

Three integers always: PHP's `version_compare` orders `2026.10.1` above
`15.8.2` and `2026.10.12` above `2026.10.9`, and the GitHub tags API lists the
highest version first. `tools/cut-release.sh` refuses a next version that
would not sort above the current one.

**Release, fix, BREAKING.** The number no longer says whether a cut adds or
repairs; the CHANGELOG does. `release` is the only kind (`fix`, `patch` and
`minor` are accepted and mean the same). A cut that breaks something says
**BREAKING** in its headline. There is no `major` argument.

**Cadence.** Merge freely. Cut when there is something to install: an arc that
closed, a regression the site should not keep, or a change the owner wants to
see. At most once a day unless a regression cannot wait. A `docs/`, `tools/`,
`.github/`-only merge rides the next cut. Show a visible change on live data
before it merges, so a problem found after install does not cost a cut.

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
- Promotes `## [Unreleased]` to `## [YYYY.M.N] - YYYY-MM-DD - headline` and archives
  the previous cut into `docs/changelog/`.
- Creates or moves the GitHub milestone.
- **Prints** the tag command rather than running it, unless given `--tag`.
- Refuses a dirty worktree, and refuses an empty `Unreleased` — nothing to cut
  is not a release.

Once the cut PR is squash-merged, tagging is one dispatch (since 2026-09-24,
ported from the plugin):

```bash
gh workflow run release.yml -f version=YYYY.M.N
```

`.github/workflows/release.yml` refuses unless main's `style.css` `Version:`
and `readme.txt` `Stable tag:` both equal the input and the tag does not exist
yet. It tags the commit that set the version (the squash commit, never main's
head). It creates no GitHub release: the updater reads tags, and nothing
reads a release (2026-10-11). It exists
so a session that can dispatch a workflow but cannot push a tag can still
finish a release. Checked against v14.1.1 through v14.3.0: each resolves to
the commit its tag already points at.

### One kind of cut

`tools/cut-release.sh release "headline"`. The script computes the date
number; a headline that starts with `BREAKING:` is how a cut says it breaks
something.

## CHANGELOG conventions

[CHANGELOG.md](../CHANGELOG.md) follows roughly the [Keep a Changelog](https://keepachangelog.com/) format with project-specific tweaks:

- **Newest at top.**
- **One section per RELEASE.** Heading: `## [YYYY.M.N] - YYYY-MM-DD - one-line summary`, written by `cut-release.sh`, not by hand.
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

- Global versioning rules: `~/.claude/CLAUDE.md` (its SemVer default is overridden for this repo and the plugin by this file)
- Project overrides: CLAUDE.md (`CLAUDE.md`, local-only)
- Release script: [tools/cut-release.sh](../tools/cut-release.sh) — the only thing that stamps a version
- Changelog archive: [docs/changelog/](changelog/) — everything older than the current cut
- Smoke test workflow: [.github/workflows/smoke-test.yml](../.github/workflows/smoke-test.yml)
- Self-updater: [inc/wp-update-integration.php](../inc/wp-update-integration.php) (how WP polls origin/main and offers updates)
