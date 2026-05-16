# Handbook hygiene pass — v8.1.1

**Date:** 2026-05-15
**Status:** Approved
**Release:** v8.1.1 (patch)
**Scope:** ~30–60 min mechanical work

## Context

The theme has been audited against the [WordPress Theme Developer Handbook](https://developer.wordpress.org/themes/). Two categories of divergence were identified:

1. **Deliberate divergences** that block WP.org directory submission — custom self-updater, external HTTP from theme code, business logic in `inc/`, `mu-plugins/` shipped from the theme repo. These are intentional and stay (we are explicitly not pursuing directory submission).
2. **Hygiene items** — universal best practices the handbook expects, where we currently slack. This spec covers a tight, low-cost subset of those.

Items deliberately deferred from this pass:
- **Inline-styles refactor.** 124 instances across 6 files. Cosmetic on admin; public-side already runs against accepted-risk CSP `unsafe-inline`. No operational payoff. Skipped.
- **Companion plugin split** (move business logic to `signal-and-noise-tools/` plugin). Architectural initiative deserving its own discuss/plan/execute phase. Deferred to a future session.
- **Full i18n coverage.** Several hours of mechanical wrapping for zero practical value on a single-author tool. Rejected.

## Items in scope

### 1. Strip the i18n bootstrap

The `load_theme_textdomain()` call in [inc/setup.php:25](../../inc/setup.php:25) wires up translation infrastructure that points at a non-existent `languages/` directory. Only ~27 strings in the codebase are wrapped (almost entirely in [inc/rest-api.php](../../inc/rest-api.php)); hundreds of admin UI strings are hardcoded English. The honest match-to-reality is to remove the bootstrap.

**Changes:**
- Remove `load_theme_textdomain( 'signal-noise', get_theme_file_path( 'languages' ) );` from [inc/setup.php:25](../../inc/setup.php:25).
- Remove the docblock paragraph (lines 16–22) explaining the call; leave the surrounding docblock for `signal_noise_after_setup_theme()` with just the editor-styles description.
- Leave `Text Domain: signal-noise` in [style.css:13](../../style.css:13) untouched. It's passive metadata that costs nothing; removing it would itself be a header change.

### 2. Unwrap textdomain-tagged i18n calls

**25 call sites** across three files use `__('...', 'signal-noise')` or `esc_html__('...', 'signal-noise')`. With the textdomain bootstrap removed, these become plain string literals — and even with it present, no one was ever going to translate this single-author surface.

| File | Count | Surface |
| --- | --- | --- |
| [inc/rest-api.php](../../inc/rest-api.php) | 22 | REST JSON responses (`WP_Error`, `sn_rest_ok`, some inside `sprintf`) |
| [inc/patterns.php](../../inc/patterns.php) | 2 | `register_block_pattern_category()` label + description (visible in block editor's Patterns inserter) |
| [inc/admin-page.php](../../inc/admin-page.php) | 1 | `wp_die()` for the permission-denied admin gate |

**Changes:**
- Each `__('...', 'signal-noise')` → plain string literal.
- Each `esc_html__('...', 'signal-noise')` → plain string literal (the calling context's existing escaping is preserved; if any call was load-bearing for HTML escape, wrap in plain `esc_html()` instead).
- `sprintf( __( '...', 'signal-noise' ), $arg )` → `sprintf( '...', $arg )`. The `sprintf` stays because the placeholder substitution stays.

**Security check:** unwrapping `esc_html__()` requires preserving the escape, since the original code chose `esc_html__` (not plain `__`) for a reason. The single instance at [inc/admin-page.php:49](../../inc/admin-page.php:49) becomes `esc_html( '...' )` rather than a plain string — same runtime behavior as before. The 22 REST handler `__()` calls feed JSON-encoded responses where HTML escape isn't applicable; they unwrap to plain string literals. The 2 `inc/patterns.php` calls are passed to `register_block_pattern_category()`, which is responsible for its own rendering; they unwrap to plain strings.

### 3. Drop the stale `dark` tag

[style.css:14](../../style.css:14) declares `Tags: full-site-editing, block-themes, dark, music, one-column, ...`. The `dark` tag is legacy from an earlier exploration; memory and project intent confirm the theme is white-first by design with no dark mode planned. Drop the tag for honest signaling.

**Change:** `Tags: full-site-editing, block-themes, dark, music, ...` → `Tags: full-site-editing, block-themes, music, ...`.

### 4. Bump `Tested up to: 6.8` → `6.9`

Current WordPress release is 6.9.4. [style.css:9](../../style.css:9) reports `Tested up to: 6.8`. Bumping to `6.9` matches the latest minor.

### 5. Bump theme.json `$schema` to 6.9

[theme.json:2](../../theme.json:2) references `https://schemas.wp.org/wp/6.7/theme.json`. The newer schema (6.9) reflects current FSE schema additions and gives editors / IDEs accurate completion for the latest theme.json features.

**Change:** `"$schema": "https://schemas.wp.org/wp/6.7/theme.json"` → `"$schema": "https://schemas.wp.org/wp/6.9/theme.json"`.

## Versioning

- **Bump:** `8.1.0` → `8.1.1` (patch). All five items are code/header changes that affect runtime or static metadata; per [CLAUDE.md](../../CLAUDE.md), that's a patch bump.
- **Cap status:** first patch in the 8.1 line; well within the 7-per-minor cap.
- **Commit message:** `v8.1.1: handbook hygiene pass — strip i18n, refresh headers`.
- **CHANGELOG entry:** new section at the top of [CHANGELOG.md](../../CHANGELOG.md) listing the five items and noting deferred work (inline styles, companion plugin) so future readers understand the scope decision.
- **Tag:** annotated `v8.1.1` at session end per CLAUDE.md release workflow.

## Verification

Each item has a single-command verification:

| Item | Verification |
| --- | --- |
| i18n stripped | `grep -rE "load_theme_textdomain\|__\(.*'signal-noise'\)\|esc_html__\(.*'signal-noise'\)" inc/ functions.php` returns zero hits |
| Tested up to bumped | `grep "Tested up to" style.css` shows `6.9` |
| theme.json schema bumped | `grep "schemas.wp.org" theme.json` shows `6.9` |
| `dark` tag dropped | `grep "Tags:" style.css` does not contain `dark` |
| Version header | `grep "^Version:" style.css` shows `8.1.1` |
| CHANGELOG | `head CHANGELOG.md` shows `[8.1.1]` as top entry |

Beyond grep-level verification, the runtime smoke test:
- Admin pageview renders without PHP notices.
- *Appearance → Signal &amp; Noise* options page loads and all four tabs render.
- One REST endpoint (e.g., `GET /wp-json/signal-noise/v1/plausible/stats`) returns expected shape, confirming unwrapped strings still flow correctly through JSON responses.

## Risks and rollback

- **Low risk overall.** All five changes are mechanical and reversible via single-file edits.
- **Highest-touch file:** [inc/rest-api.php](../../inc/rest-api.php) (27 unwrap operations). Risk mitigated by per-call review of escape context.
- **Rollback path:** `git revert <commit-sha>` produces a working v8.1.0 state.

## Out of scope (explicit)

These were considered and deferred or rejected:

- **Move business logic to companion plugin** — deferred to its own future GSD phase. Real scope: boundary decisions, plugin bootstrap, settings ownership migration, plugin update mechanism, install order. Multi-session work.
- **Inline-styles → external CSS** — 124 instances. Skipped this pass; cosmetic with no operational payoff.
- **Wrap all admin UI strings + ship `.pot`** — rejected; zero practical value for a single-author tool.
- **Remove `Text Domain:` header line from style.css** — deliberately kept; passive metadata, harmless.
- **Move `mu-plugins/` out of the theme repo** — deferred; part of the companion plugin discussion.
