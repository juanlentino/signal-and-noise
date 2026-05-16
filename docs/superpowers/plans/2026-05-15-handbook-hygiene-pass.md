# Handbook Hygiene Pass Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Land five mechanical hygiene items (strip i18n bootstrap, unwrap 25 textdomain-tagged calls, drop stale `dark` tag, bump `Tested up to` 6.8 → 6.9, bump `theme.json` `$schema` 6.7 → 6.9) as v8.1.1.

**Architecture:** No code architecture changes — all edits are header / metadata / string-literal substitutions. The companion plugin split and inline-styles refactor are explicitly deferred per [the spec](../specs/2026-05-15-handbook-hygiene-pass-design.md).

**Tech Stack:** PHP (WordPress block theme), JSON (theme.json), CSS header (style.css), Markdown (CHANGELOG.md).

**Commit policy:** Per [CLAUDE.md](../../../CLAUDE.md), the project's release pattern is one `vX.Y.Z:` commit per release containing all code changes + version bump + CHANGELOG entry. Tasks 1–6 stage edits without committing; Task 7 produces the single release commit. The annotated tag happens at session end per CLAUDE.md.

---

### Task 1: Strip the i18n bootstrap from inc/setup.php

**Files:**
- Modify: [inc/setup.php:15-38](../../../inc/setup.php:15)

**Rationale:** `load_theme_textdomain()` points at a non-existent `languages/` directory and serves no one (single-author tool, no translations will be produced). The function `signal_noise_after_setup_theme()` keeps its `add_editor_style()` call — only the i18n line and its now-misleading docblock paragraph are removed.

- [ ] **Step 1: Read the current state of the function**

Use the Read tool on [inc/setup.php](../../../inc/setup.php) with `offset=14, limit=25`.
Expected: shows the docblock starting at line 15, the `load_theme_textdomain` call at line 25, and the `add_editor_style` block ending around line 37.

- [ ] **Step 2: Replace the function + its docblock**

Replace lines 15-37 of [inc/setup.php](../../../inc/setup.php) using Edit.

OLD:

```php
/**
 * Theme setup: register the text domain and editor styles together at
 * after_setup_theme. load_theme_textdomain() points at the languages/
 * directory we'll create when (if) translations are produced. Even
 * with no translation files present this call is harmless and makes
 * subsequent __() / esc_html__() calls behave consistently with WPCS
 * conventions; without it, sprinkled translation calls work by
 * fall-through rather than by registered intent.
 */
function signal_noise_after_setup_theme() {
	load_theme_textdomain( 'signal-noise', get_theme_file_path( 'languages' ) );

	// Editor styles — same five modular stylesheets used on the public
	// side, in the same cascade order. Keep this list in sync with the
	// wp_enqueue chain in inc/assets-frontend.php.
	add_editor_style( array(
		'assets/css/base.css',
		'assets/css/layout.css',
		'assets/css/components.css',
		'assets/css/forms.css',
		'assets/css/responsive.css',
	) );
}
```

NEW:

```php
/**
 * Theme setup: register editor styles to match the frontend cascade.
 *
 * i18n bootstrap intentionally absent — see v8.1.1 hygiene pass:
 * single-author surface, no translation files will ever be produced,
 * and the prior `load_theme_textdomain()` call pointed at a non-existent
 * directory. The `Text Domain: signal-noise` header in style.css is
 * retained as passive metadata.
 */
function signal_noise_after_setup_theme() {
	// Editor styles — same five modular stylesheets used on the public
	// side, in the same cascade order. Keep this list in sync with the
	// wp_enqueue chain in inc/assets-frontend.php.
	add_editor_style( array(
		'assets/css/base.css',
		'assets/css/layout.css',
		'assets/css/components.css',
		'assets/css/forms.css',
		'assets/css/responsive.css',
	) );
}
```

- [ ] **Step 3: Verify the bootstrap is gone**

Run: `grep -n "load_theme_textdomain" inc/setup.php functions.php`
Expected: zero hits.

- [ ] **Step 4: Stage the file (do not commit yet)**

```bash
git add inc/setup.php
```

---

### Task 2: Unwrap textdomain calls in inc/rest-api.php

**Files:**
- Modify: [inc/rest-api.php](../../../inc/rest-api.php) — 22 call sites

**Rationale:** All 22 calls feed JSON-encoded REST responses (`WP_Error` messages, `sn_rest_ok` success messages, some inside `sprintf` for placeholder substitution). HTML escape is not applicable; plain string literals are the correct unwrap target.

- [ ] **Step 1: Enumerate the call sites for the engineer's reference**

Run: `grep -nE "__\(.*'signal-noise'" inc/rest-api.php`
Expected: 22 lines printed.

- [ ] **Step 2: Unwrap each `__()` call to a plain string literal**

The mechanical pattern is `__( 'STRING', 'signal-noise' )` → `'STRING'`. The 22 instances (transformations identical, sprintf wrappers preserved):

| Line | Before | After |
| --- | --- | --- |
| 83 | `__( 'You do not have permission to perform this action.', 'signal-noise' )` | `'You do not have permission to perform this action.'` |
| 168 | `__( 'Cache purge module not loaded.', 'signal-noise' )` | `'Cache purge module not loaded.'` |
| 171 | `__( 'All caches purged.', 'signal-noise' )` | `'All caches purged.'` |
| 180 | `__( 'Template override module not loaded.', 'signal-noise' )` | `'Template override module not loaded.'` |
| 185 | `__( '%d database override(s) cleared.', 'signal-noise' )` | `'%d database override(s) cleared.'` |
| 197 | `__( 'Self-heal module not loaded.', 'signal-noise' )` | `'Self-heal module not loaded.'` |
| 204 | `__( 'Self-heal: re-synced %d template file(s) from GitHub.', 'signal-noise' )` | `'Self-heal: re-synced %d template file(s) from GitHub.'` |
| 205 | `__( 'Self-heal: all monitored files already match GitHub.', 'signal-noise' )` | `'Self-heal: all monitored files already match GitHub.'` |
| 211 | `__( 'Self-heal: drift detected but write failed for %d file(s).', 'signal-noise' )` | `'Self-heal: drift detected but write failed for %d file(s).'` |
| 234 | `__( 'Cache purge module not loaded.', 'signal-noise' )` | `'Cache purge module not loaded.'` |
| 240 | `__( 'Full reset: %d override(s) cleared and all caches purged.', 'signal-noise' )` | `'Full reset: %d override(s) cleared and all caches purged.'` |
| 252 | `__( 'Self-updater module not loaded.', 'signal-noise' )` | `'Self-updater module not loaded.'` |
| 284 | `__( 'Update check complete.', 'signal-noise' )` | `'Update check complete.'` |
| 301 | `__( 'Plausible module not loaded.', 'signal-noise' )` | `'Plausible module not loaded.'` |
| 305 | `__( 'Plausible is not configured (missing domain or token).', 'signal-noise' )` | `'Plausible is not configured (missing domain or token).'` |
| 308 | `__( 'Plausible 7-day stats.', 'signal-noise' )` | `'Plausible 7-day stats.'` |
| 319 | `__( 'Plausible module not loaded.', 'signal-noise' )` | `'Plausible module not loaded.'` |
| 323 | `__( 'Plausible realtime visitors.', 'signal-noise' )` | `'Plausible realtime visitors.'` |
| 337 | `__( 'Plausible module not loaded.', 'signal-noise' )` | `'Plausible module not loaded.'` |
| 341 | `__( 'Plausible is not configured (missing domain or token).', 'signal-noise' )` | `'Plausible is not configured (missing domain or token).'` |
| 349 | `__( 'Plausible API call succeeded — %d visitor(s) in last 7 days.', 'signal-noise' )` | `'Plausible API call succeeded — %d visitor(s) in last 7 days.'` |
| 357 | `__( 'Plausible API call failed.', 'signal-noise' )` | `'Plausible API call failed.'` |

Edit each in [inc/rest-api.php](../../../inc/rest-api.php) using Edit. Some strings (`'Cache purge module not loaded.'`, `'Plausible module not loaded.'`, `'Plausible is not configured (missing domain or token).'`) appear multiple times. Use `replace_all=true` on the Edit tool for those, OR edit each occurrence with enough surrounding context to make it unique.

**Note:** `sprintf( __( 'X %d Y', 'signal-noise' ), $n )` becomes `sprintf( 'X %d Y', $n )` — the `sprintf` wrapper stays because it has runtime placeholder substitution to do.

- [ ] **Step 3: Verify zero remaining textdomain-tagged calls in this file**

Run: `grep -cE "__\(.*'signal-noise'" inc/rest-api.php`
Expected: `0`.

- [ ] **Step 4: Smoke-test PHP syntax**

Run: `php -l inc/rest-api.php`
Expected: `No syntax errors detected in inc/rest-api.php`.

- [ ] **Step 5: Stage the file**

```bash
git add inc/rest-api.php
```

---

### Task 3: Unwrap textdomain calls in inc/patterns.php

**Files:**
- Modify: [inc/patterns.php:32-33](../../../inc/patterns.php:32) — 2 call sites

**Rationale:** Both calls are passed to `register_block_pattern_category()`. The `label` appears in the block editor's Patterns inserter sidebar. Unwrapping means the editor always shows English — fine for a single-author tool.

- [ ] **Step 1: Edit the two strings**

Use Edit on [inc/patterns.php](../../../inc/patterns.php):

OLD:

```php
register_block_pattern_category( 'signal-noise', array(
	'label'       => __( 'Signal & Noise', 'signal-noise' ),
	'description' => __( 'Patterns specific to the Signal & Noise theme.', 'signal-noise' ),
) );
```

NEW:

```php
register_block_pattern_category( 'signal-noise', array(
	'label'       => 'Signal & Noise',
	'description' => 'Patterns specific to the Signal & Noise theme.',
) );
```

- [ ] **Step 2: Verify**

Run: `grep -nE "__\(.*'signal-noise'" inc/patterns.php`
Expected: zero hits.

- [ ] **Step 3: Smoke-test PHP syntax**

Run: `php -l inc/patterns.php`
Expected: `No syntax errors detected in inc/patterns.php`.

- [ ] **Step 4: Stage the file**

```bash
git add inc/patterns.php
```

---

### Task 4: Unwrap the textdomain call in inc/admin-page.php

**Files:**
- Modify: [inc/admin-page.php:49](../../../inc/admin-page.php:49) — 1 call site (`esc_html__`)

**Rationale:** The original code chose `esc_html__()` (not plain `__`) deliberately — the message lands inside `wp_die()` and could in principle include HTML. To preserve that intent, unwrap to `esc_html( '...' )`, not a bare string literal.

- [ ] **Step 1: Edit the one line**

Use Edit on [inc/admin-page.php](../../../inc/admin-page.php):

OLD:

```php
		wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'signal-noise' ) );
```

NEW:

```php
		wp_die( esc_html( 'You do not have sufficient permissions to access this page.' ) );
```

- [ ] **Step 2: Verify both textdomain forms are gone**

Run: `grep -nE "__\(.*'signal-noise'|esc_html__\(.*'signal-noise'" inc/admin-page.php`
Expected: zero hits.

- [ ] **Step 3: Verify the escape function still wraps the string**

Run: `grep -n "wp_die( esc_html(" inc/admin-page.php`
Expected: shows line 49 with `wp_die( esc_html( 'You do not have sufficient permissions...' ) )`.

- [ ] **Step 4: Smoke-test PHP syntax**

Run: `php -l inc/admin-page.php`
Expected: `No syntax errors detected in inc/admin-page.php`.

- [ ] **Step 5: Global verification — no textdomain-tagged i18n calls remain anywhere**

Run: `grep -rEn "__\(.*'signal-noise'|esc_html__\(.*'signal-noise'|esc_attr__\(.*'signal-noise'" inc/ functions.php`
Expected: zero hits across the entire codebase.

- [ ] **Step 6: Stage the file**

```bash
git add inc/admin-page.php
```

---

### Task 5: Update style.css header (Tags, Tested up to, Version)

**Files:**
- Modify: [style.css:7,9,14](../../../style.css:7) — three header fields

**Rationale:**
- Drop the stale `dark` tag (the theme is white-first by design; memory confirms dark mode intentionally omitted).
- Bump `Tested up to: 6.8` → `6.9`. Current WP is 6.9.4; the handbook expects `Tested up to` to reflect the latest minor the maintainer has exercised.
- Bump `Version: 8.1.0` → `8.1.1`. Patch bump per [CLAUDE.md](../../../CLAUDE.md); within the 7-per-minor cap (first patch in 8.1).

- [ ] **Step 1: Update Version**

Use Edit on [style.css](../../../style.css):

OLD: `Version: 8.1.0`
NEW: `Version: 8.1.1`

- [ ] **Step 2: Update Tested up to**

Use Edit on [style.css](../../../style.css):

OLD: `Tested up to: 6.8`
NEW: `Tested up to: 6.9`

- [ ] **Step 3: Drop `dark` from Tags**

Use Edit on [style.css](../../../style.css):

OLD: `Tags: full-site-editing, block-themes, dark, music, one-column, custom-colors, custom-menu, wide-blocks, editor-style`
NEW: `Tags: full-site-editing, block-themes, music, one-column, custom-colors, custom-menu, wide-blocks, editor-style`

- [ ] **Step 4: Verify all three fields**

```bash
grep -E "^Version:|^Tested up to:|^Tags:" style.css
```

Expected output:
```
Version: 8.1.1
Tested up to: 6.9
Tags: full-site-editing, block-themes, music, one-column, custom-colors, custom-menu, wide-blocks, editor-style
```

- [ ] **Step 5: Stage the file**

```bash
git add style.css
```

---

### Task 6: Bump theme.json `$schema` to 6.9

**Files:**
- Modify: [theme.json:2](../../../theme.json:2) — `$schema` URL

**Rationale:** Current `$schema` points at the WordPress 6.7 theme.json schema; bumping to 6.9 reflects current WP and gives IDE/editor completion for the latest theme.json fields.

- [ ] **Step 1: Edit the schema URL**

Use Edit on [theme.json](../../../theme.json):

OLD: `"$schema": "https://schemas.wp.org/wp/6.7/theme.json",`
NEW: `"$schema": "https://schemas.wp.org/wp/6.9/theme.json",`

- [ ] **Step 2: Verify**

```bash
grep "schemas.wp.org" theme.json
```

Expected: `"$schema": "https://schemas.wp.org/wp/6.9/theme.json",`.

- [ ] **Step 3: Validate JSON syntax**

```bash
python3 -c "import json; json.load(open('theme.json')); print('valid JSON')"
```

Expected: `valid JSON`.

- [ ] **Step 4: Stage the file**

```bash
git add theme.json
```

---

### Task 7: Add CHANGELOG entry + final release commit

**Files:**
- Modify: [CHANGELOG.md:1-5](../../../CHANGELOG.md:1) — insert new entry at the top below the title

**Rationale:** Standard project release pattern — every `vX.Y.Z:` commit ships with a CHANGELOG entry documenting what changed and why. Format matches recent entries (see v8.1.0 entry at line 5).

- [ ] **Step 1: Insert the new CHANGELOG entry**

Use Edit on [CHANGELOG.md](../../../CHANGELOG.md). Insert the new entry between line 3 (`All notable changes...`) and line 5 (`## [8.1.0] — ...`).

OLD:

```markdown
All notable changes to Signal & Noise are documented here.

## [8.1.0] — Notes subscribe info nested in hero (cap rollover from 8.0.7; not a new capability)
```

NEW:

```markdown
All notable changes to Signal & Noise are documented here.

## [8.1.1] — Handbook hygiene pass — strip i18n, refresh headers

Five mechanical hygiene items aligning the theme with the [WordPress Theme Developer Handbook](https://developer.wordpress.org/themes/) where it costs us little. The deliberate deviations (custom self-updater, external HTTP from theme code, business logic in `inc/`, `mu-plugins/` shipped from the theme repo) remain intentional and are NOT addressed here — they're documented in [docs/WORDPRESS-REFERENCE.md](docs/WORDPRESS-REFERENCE.md) §10 and accepted as the price of running a private single-site theme. The companion plugin split and inline-styles refactor are deferred to their own future phases.

### Changed

- **[`inc/setup.php`](inc/setup.php) — i18n bootstrap removed.** `load_theme_textdomain( 'signal-noise', ... )` and its docblock paragraph deleted. The function `signal_noise_after_setup_theme()` retains its `add_editor_style()` block. `Text Domain: signal-noise` in `style.css` kept as passive metadata.
- **[`inc/rest-api.php`](inc/rest-api.php) — 22 `__()` calls unwrapped.** All REST handler messages (`WP_Error` errors, `sn_rest_ok` success, sprintf placeholders) become plain string literals. JSON encoding is the rendering path; HTML escape was never applicable.
- **[`inc/patterns.php`](inc/patterns.php) — 2 `__()` calls unwrapped.** `register_block_pattern_category()` label + description become plain strings. The block editor's Patterns inserter now shows English directly.
- **[`inc/admin-page.php`](inc/admin-page.php) — 1 `esc_html__()` call unwrapped.** The permission-denied `wp_die()` message becomes `esc_html( '...' )` — escape preserved per original intent.
- **[`style.css`](style.css) — header updates.** Dropped stale `dark` tag (theme is white-first by design). Bumped `Tested up to: 6.8` → `6.9` (current WP is 6.9.4). Bumped `Version: 8.1.0` → `8.1.1`.
- **[`theme.json`](theme.json) — `$schema` bumped.** `https://schemas.wp.org/wp/6.7/theme.json` → `https://schemas.wp.org/wp/6.9/theme.json` for editor / IDE completion against current FSE schema.

### Why patch

All five items are mechanical changes to code or static metadata. No new user-visible capability, no schema migration, no breaking API change. First patch in the v8.1 line; well within the 7-per-minor cap.

### Migration

None required. Behavior is identical at runtime — string contents unchanged, function signatures unchanged, REST responses byte-identical (the `__()` calls already fell through to the source strings since no `.mo` file ever existed).

### Spec + plan

- [docs/superpowers/specs/2026-05-15-handbook-hygiene-pass-design.md](docs/superpowers/specs/2026-05-15-handbook-hygiene-pass-design.md)
- [docs/superpowers/plans/2026-05-15-handbook-hygiene-pass.md](docs/superpowers/plans/2026-05-15-handbook-hygiene-pass.md)

Authored via the `superpowers:brainstorming` → `superpowers:writing-plans` → `superpowers:executing-plans` skill chain.

## [8.1.0] — Notes subscribe info nested in hero (cap rollover from 8.0.7; not a new capability)
```

- [ ] **Step 2: Verify the top entry is now [8.1.1]**

```bash
grep -m 1 "^## \[" CHANGELOG.md
```

Expected: `## [8.1.1] — Handbook hygiene pass — strip i18n, refresh headers`.

- [ ] **Step 3: Stage the CHANGELOG**

```bash
git add CHANGELOG.md
```

- [ ] **Step 4: Run the full pre-commit verification matrix**

All six verifications from the spec:

```bash
echo "=== 1. i18n stripped ==="
grep -rEn "load_theme_textdomain|__\(.*'signal-noise'|esc_html__\(.*'signal-noise'" inc/ functions.php || echo "ZERO HITS ✓"

echo "=== 2. Tested up to ==="
grep "^Tested up to:" style.css

echo "=== 3. theme.json schema ==="
grep "schemas.wp.org" theme.json

echo "=== 4. dark tag dropped ==="
grep "^Tags:" style.css | grep -v "dark" && echo "NO dark tag ✓"

echo "=== 5. Version header ==="
grep "^Version:" style.css

echo "=== 6. CHANGELOG top entry ==="
grep -m 1 "^## \[" CHANGELOG.md
```

Expected: ZERO HITS for #1; `6.9` for #2; `6.9` for #3; `NO dark tag ✓` for #4; `8.1.1` for #5; `[8.1.1]` for #6.

- [ ] **Step 5: Stage the plan document**

The plan doc is currently untracked (written this session, not yet committed). Stage it so it ships in the same release commit for traceability with the `Spec + plan` pointers in the CHANGELOG entry.

```bash
git add docs/superpowers/plans/2026-05-15-handbook-hygiene-pass.md
```

- [ ] **Step 6: Confirm full staged file set**

```bash
git status --short
```

Expected output (order may vary):
```
M  CHANGELOG.md
M  inc/admin-page.php
M  inc/patterns.php
M  inc/rest-api.php
M  inc/setup.php
M  style.css
M  theme.json
A  docs/superpowers/plans/2026-05-15-handbook-hygiene-pass.md
```

- [ ] **Step 7: Create the release commit**

```bash
git commit -m "$(cat <<'EOF'
v8.1.1: handbook hygiene pass — strip i18n, refresh headers

Five mechanical hygiene items: removed load_theme_textdomain bootstrap
from inc/setup.php; unwrapped 25 textdomain-tagged __()/esc_html__()
calls across inc/rest-api.php (22), inc/patterns.php (2),
inc/admin-page.php (1); dropped stale `dark` tag from style.css Tags
header; bumped Tested up to 6.8 → 6.9 and theme.json $schema 6.7 → 6.9.

No runtime behavior change. Companion plugin split + inline-styles
refactor explicitly deferred to future phases; see spec for rationale.

Spec: docs/superpowers/specs/2026-05-15-handbook-hygiene-pass-design.md
Plan: docs/superpowers/plans/2026-05-15-handbook-hygiene-pass.md

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

- [ ] **Step 8: Verify the commit landed**

```bash
git log -1 --oneline
```

Expected: `<sha> v8.1.1: handbook hygiene pass — strip i18n, refresh headers`.

- [ ] **Step 9: Offer (but do NOT execute) the annotated tag**

Per [CLAUDE.md](../../../CLAUDE.md): tags happen at session end, not mid-execution. After the commit lands, report to the user that the work is done and the tag can be applied with:

```bash
git tag -a v8.1.1 -m "v8.1.1 — handbook hygiene pass: strip i18n, refresh headers"
git push origin v8.1.1
```

Wait for the user's confirmation to run the tag commands. Do not auto-tag.

---

## Out-of-band post-flight (user runs after merge)

These are NOT part of the implementation but are the standard post-release operations per CLAUDE.md. Listed here for the engineer's awareness:

- Push the branch to remote (`git push`).
- After PR merge to `main`, push the `v8.1.1` annotated tag.
- WordPress admin → *Themes* → click *Update* on the Signal & Noise tile to install on the live site.
- Verify on the live site: load `/wp-admin/`, confirm the *Appearance → Signal & Noise* options page renders, run one REST endpoint smoke (e.g., `curl https://juanlentino.com/wp-json/signal-noise/v1/plausible/stats` — expects a JSON shape).
