# Phase 3 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Move three theme-coupled modules (`og-image.php`, `reading-time.php`, `notes-and-provenance.php`) into the plugin where they belong as operational/content-surface concerns. Theme keeps only `page-notes-*` (theme-specific defenses + render). Plugin grows by 5 new modules + `seed-content/`. One new cross-package filter (`sn_og_font_paths`) lets theme own brand fonts while plugin owns OG-card rendering.

**Architecture:** Two-phase staging — plugin code changes commit FIRST (no tag), then theme code changes commit + theme tags v8.4.0 (auto-deploys, ~30s), then plugin tags v1.3.0 (auto-deploys). Theme-first release order is required because plugin v1.3.0 would PHP-fatal at parse time if the theme still ships its copies of those modules (function-redeclare). Plugin v1.3.0 also ships guard #3 (pre-flight file_exists check) as defense-in-depth.

**Tech Stack:** WordPress FSE (PHP), GitHub Actions, Cloudways auto-deploy, Cloudflare REST API, WP Application Passwords.

**Spec:** [docs/superpowers/specs/2026-05-16-phase-3-theme-coupled-moves-design.md](../specs/2026-05-16-phase-3-theme-coupled-moves-design.md) (commit `13cf1cd`).

**Working directories:**
- Theme (this worktree): `/Users/juanlentino/projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551`. Push: `git push origin HEAD:main`.
- Plugin: `/Users/juanlentino/Projects/signal-and-noise-tools` (branch `main`). Push: `git push origin main`.

**Preconditions verified:**
- Both repos auto-deploy on tag push (Phase 2a for theme, Phase 2c for plugin).
- No test infrastructure. Verification = hand-execution + smoke-test.
- Source file line references match commit `13cf1cd` snapshot (theme inc/ at v8.3.0).

---

## STAGING PHASE — Plugin code (no tag yet)

### Task 1: Add `inc/content-rendering-helpers.php` to plugin

Smallest module — extract pure markup-generator helpers first since migrations depend on them.

**Files:**
- Create: `/Users/juanlentino/Projects/signal-and-noise-tools/inc/content-rendering-helpers.php`

- [ ] **Step 1: Extract the three helper functions from theme source**

Source file: `/Users/juanlentino/projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551/inc/notes-and-provenance.php`.

Extract these functions verbatim (including their docblocks):
- `sn_provenance_byline_reading_time_markup()` at line 395
- `sn_provenance_toc_block_markup()` at line 408
- `sn_provenance_papers_index_markup()` at line 568

Each function is pure (no side effects, no DB writes, no hooks) — returns a string of Gutenberg block markup. Read each function fully (including its trailing `}`) and copy into the new file.

- [ ] **Step 2: Write the new file with proper bootstrap**

File contents (header + the 3 functions):

```php
<?php
/**
 * Signal & Noise Tools — content rendering helpers.
 *
 * Pure Gutenberg block-markup generators called from content
 * migrations. Stateless string builders; no hooks, no DB writes.
 *
 * Moved from theme inc/notes-and-provenance.php in Phase 3
 * (theme v8.4.0 / plugin v1.3.0, 2026-05-16).
 *
 * @package SignalNoiseTools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── [INSERT sn_provenance_byline_reading_time_markup() here from theme line 395] ──

// ── [INSERT sn_provenance_toc_block_markup() here from theme line 408] ──

// ── [INSERT sn_provenance_papers_index_markup() here from theme line 568] ──
```

Replace the `[INSERT ...]` comments with the actual function code from the source file.

- [ ] **Step 3: Verify file is valid PHP**

```bash
cd /Users/juanlentino/Projects/signal-and-noise-tools && \
php -l inc/content-rendering-helpers.php 2>&1 || \
  echo "(php binary unavailable — skip syntax check, rely on WP-side verification)"
```

Expected: `No syntax errors detected` OR the fallback message (php not on PATH locally is fine; the live server will catch syntax errors at deploy time).

- [ ] **Step 4: Commit**

```bash
cd /Users/juanlentino/Projects/signal-and-noise-tools && \
git add inc/content-rendering-helpers.php && \
git commit -m "content-rendering-helpers: extract block markup generators

Pure functions extracted from theme inc/notes-and-provenance.php as part
of Phase 3 split. Three helpers: byline_reading_time, toc, papers_index.
Stateless; consumed by content-migrations.php (next commit)."
```

---

### Task 2: Add `inc/content-surfaces.php` to plugin

Surface definitions (constants, category/page seeding, permalinks).

**Files:**
- Create: `/Users/juanlentino/Projects/signal-and-noise-tools/inc/content-surfaces.php`

- [ ] **Step 1: Extract sections from theme source**

From theme `inc/notes-and-provenance.php`, extract these contiguous sections:
- **Constants block** (approximately lines 13-40): `SN_NOTES_CATEGORY_SLUG`, `SN_NOTES_PAGE_SLUG`, `SN_PROVENANCE_SLUG`, `SN_OVER_DETECTION_SLUG`, `SN_AS_SUBSTRATE_SLUG`, `SN_PERMALINK_STRUCTURE`, and the 9 `SN_*_MIGR_OPT` flags.
- `add_action( 'after_switch_theme', 'sn_seed_content_surfaces' );` (line 54)
- `add_action( 'admin_init', 'sn_seed_content_surfaces' );` (line 61)
- `function sn_seed_content_surfaces()` (line 63)
- `function sn_ensure_notes_category()` (line 79)
- `function sn_ensure_notes_page()` (line 94)
- `function sn_ensure_provenance_page()` (line 121)
- `function sn_ensure_over_detection_page()` (line 146)
- `function sn_ensure_as_substrate_page()` (line 173)
- `function sn_ensure_permalink_structure()` (line 422)
- `add_action( 'admin_init', 'sn_sync_default_category' );` (line 454)
- `function sn_sync_default_category()` (line 456)
- `add_filter( 'query_loop_block_query_vars', function...` (line 472)

Read each function in full (until its closing `}`). Some are longer than others — read carefully to capture the full body.

- [ ] **Step 2: Write the new file**

```php
<?php
/**
 * Signal & Noise Tools — content surfaces.
 *
 * Defines the canonical content structure: Notes category + permalink,
 * /notes index Page, /provenance + /over-detection + /as-substrate
 * Pages, and the query-loop scoping filter. Idempotent — seed
 * functions check for existence before creating, so safe on every
 * admin pageload.
 *
 * Moved from theme inc/notes-and-provenance.php in Phase 3
 * (theme v8.4.0 / plugin v1.3.0, 2026-05-16).
 *
 * @package SignalNoiseTools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── [INSERT all SN_* constants here, exactly as in theme lines 13-40] ──

// ── [INSERT add_action(after_switch_theme...) + add_action(admin_init...) for sn_seed_content_surfaces] ──

// ── [INSERT sn_seed_content_surfaces() function] ──

// ── [INSERT sn_ensure_notes_category() function] ──

// ── [INSERT sn_ensure_notes_page() function] ──

// ── [INSERT sn_ensure_provenance_page() function] ──

// ── [INSERT sn_ensure_over_detection_page() function] ──

// ── [INSERT sn_ensure_as_substrate_page() function] ──

// ── [INSERT sn_ensure_permalink_structure() function] ──

// ── [INSERT add_action(admin_init, sn_sync_default_category) + function] ──

// ── [INSERT add_filter(query_loop_block_query_vars, ...) ] ──
```

Replace each `[INSERT ...]` comment with the actual code from the source file. Preserve all docblocks.

- [ ] **Step 3: Verify file size + syntax**

```bash
cd /Users/juanlentino/Projects/signal-and-noise-tools && \
wc -l inc/content-surfaces.php && \
php -l inc/content-surfaces.php 2>&1 || echo "(php unavailable)"
```

Expected: ~220-280 lines, no syntax errors.

- [ ] **Step 4: Commit**

```bash
cd /Users/juanlentino/Projects/signal-and-noise-tools && \
git add inc/content-surfaces.php && \
git commit -m "content-surfaces: extract notes/provenance content structure

Notes category + /notes Page + /provenance + /over-detection +
/as-substrate Pages + permalink structure + query loop scoping.
Constants and idempotent seed functions extracted from theme's
inc/notes-and-provenance.php as part of Phase 3 split."
```

---

### Task 3: Add `inc/content-migrations.php` to plugin

Largest extraction — body loaders + 11 one-shot migration functions.

**Files:**
- Create: `/Users/juanlentino/Projects/signal-and-noise-tools/inc/content-migrations.php`

- [ ] **Step 1: Extract sections from theme source**

From theme `inc/notes-and-provenance.php`, extract:

**Body loaders** (~lines 199-232):
- `function sn_load_provenance_body()` (line 199)
- `function sn_load_over_detection_body()` (line 208)
- `function sn_load_as_substrate_body()` (line 217)

**Migration functions** with their `add_action('admin_init', ...)` registrations directly above each:
- `sn_migrate_provenance_body` at line 236 (with add_action at line 234)
- `sn_migrate_provenance_refinements` at line 286 (add_action at line 284)
- `sn_migrate_provenance_byline_reading_time` at line 347 (add_action at line 345)
- `sn_migrate_provenance_split` at line 508 (add_action at line 506)
- `sn_migrate_as_substrate_seed` at line 667 (add_action at line 665)
- `sn_migrate_provenance_card2_longform` at line 708 (add_action at line 706)
- `sn_migrate_provenance_card_readtimes_dynamic` at line 766 (add_action at line 764)
- `sn_migrate_provenance_catalog_numbers` at line 819 (add_action at line 817)
- `sn_migrate_as_substrate_post_date_displaytype` at line 875 (add_action at line 873)
- `sn_migrate_over_detection_eyebrow_dynamic` at line 937 (add_action at line 935)
- `sn_migrate_clear_notes_template_override` at line 1015 (add_action at line 1013)

11 migrations total. Each one is gated by checking its corresponding `SN_*_MIGR_OPT` option flag (defined in `content-surfaces.php`).

- [ ] **Step 2: Write the new file**

```php
<?php
/**
 * Signal & Noise Tools — content seed migrations.
 *
 * One-shot DB seed scripts for the Provenance pillar and Notes
 * content surface. Each migration is gated by an SN_*_MIGR_OPT
 * option flag (defined in content-surfaces.php). Migrations run
 * exactly once per environment; idempotent re-runs are no-ops.
 *
 * Body loaders read HTML from inc/seed-content/ — moved from theme
 * to plugin alongside this file in Phase 3.
 *
 * Moved from theme inc/notes-and-provenance.php in Phase 3
 * (theme v8.4.0 / plugin v1.3.0, 2026-05-16). Original ordering preserved.
 *
 * @package SignalNoiseTools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── BODY LOADERS ─────────────────────────────────────────────────

// ── [INSERT sn_load_provenance_body() from theme line 199] ──

// ── [INSERT sn_load_over_detection_body() from theme line 208] ──

// ── [INSERT sn_load_as_substrate_body() from theme line 217] ──

// ── MIGRATIONS (one-shot, idempotent per SN_*_MIGR_OPT flag) ───────

// ── [INSERT add_action + sn_migrate_provenance_body from lines 234-282] ──

// ── [INSERT add_action + sn_migrate_provenance_refinements from lines 284-343] ──

// ── [INSERT add_action + sn_migrate_provenance_byline_reading_time from lines 345-394] ──

// ── [INSERT add_action + sn_migrate_provenance_split from lines 506-566] ──

// ── [INSERT add_action + sn_migrate_as_substrate_seed from lines 665-705] ──

// ── [INSERT add_action + sn_migrate_provenance_card2_longform from lines 706-762] ──

// ── [INSERT add_action + sn_migrate_provenance_card_readtimes_dynamic from lines 764-815] ──

// ── [INSERT add_action + sn_migrate_provenance_catalog_numbers from lines 817-871] ──

// ── [INSERT add_action + sn_migrate_as_substrate_post_date_displaytype from lines 873-933] ──

// ── [INSERT add_action + sn_migrate_over_detection_eyebrow_dynamic from lines 935-1011] ──

// ── [INSERT add_action + sn_migrate_clear_notes_template_override from lines 1013-1057] ──
```

For each `[INSERT ...]` block, read the EXACT line range from the source file and copy verbatim. Preserve all docblocks.

- [ ] **Step 3: Verify file size + dependencies**

```bash
cd /Users/juanlentino/Projects/signal-and-noise-tools && \
wc -l inc/content-migrations.php && \
php -l inc/content-migrations.php 2>&1 || echo "(php unavailable)" && \
echo "--- check helper function references ---" && \
grep -nE 'sn_provenance_byline_reading_time_markup|sn_provenance_toc_block_markup|sn_provenance_papers_index_markup' inc/content-migrations.php | head -10
```

Expected: ~650-750 lines, no syntax errors. The 3 helper-function references appear (these resolve to content-rendering-helpers.php at runtime; require_once order in signal-and-noise-tools.php ensures helpers load first).

- [ ] **Step 4: Commit**

```bash
cd /Users/juanlentino/Projects/signal-and-noise-tools && \
git add inc/content-migrations.php && \
git commit -m "content-migrations: extract 11 one-shot DB seed migrations

Body loaders + sn_migrate_* functions (provenance_body, refinements,
byline_reading_time, split, as_substrate_seed, card2_longform,
card_readtimes_dynamic, catalog_numbers, post_date_displaytype,
eyebrow_dynamic, clear_notes_template_override). Each migration is
gated by an SN_*_MIGR_OPT option flag from content-surfaces.php.
Extracted from theme inc/notes-and-provenance.php for Phase 3."
```

---

### Task 4: Move `seed-content/` from theme to plugin

HTML files consumed by content migrations.

**Files:**
- Modify: `/Users/juanlentino/Projects/signal-and-noise-tools/inc/seed-content/` (new dir)
- Modify: `/Users/juanlentino/projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551/inc/seed-content/` (delete in theme phase later)

- [ ] **Step 1: Copy seed-content/ from theme to plugin**

```bash
cp -r /Users/juanlentino/projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551/inc/seed-content \
      /Users/juanlentino/Projects/signal-and-noise-tools/inc/seed-content && \
ls /Users/juanlentino/Projects/signal-and-noise-tools/inc/seed-content/
```

Expected: directory listing shows the same HTML files as theme's seed-content/.

- [ ] **Step 2: Verify body loaders find their files**

```bash
cd /Users/juanlentino/Projects/signal-and-noise-tools && \
grep -nE 'seed-content|__DIR__' inc/content-migrations.php | head -10
```

Expected: paths like `__DIR__ . '/seed-content/provenance.html'`. Since `__DIR__` resolves to the file's containing directory, this will correctly point at `/inc/seed-content/` once `content-migrations.php` lives in `inc/`.

- [ ] **Step 3: Commit**

```bash
cd /Users/juanlentino/Projects/signal-and-noise-tools && \
git add inc/seed-content/ && \
git commit -m "seed-content: move HTML bodies from theme to plugin

These files are content data (Provenance pillar body, refinements,
AS substrate seed, etc.) consumed by content-migrations.php. Moving
alongside the migrations that consume them. Theme will drop its copy
in v8.4.0. __DIR__ resolution keeps loader paths working — same
relative path (inc/seed-content/) inside the new owning package."
```

---

### Task 5: Add `inc/og-card-generator.php` to plugin

OG/Twitter card PHP GD generation, moved from theme with one font-path modification.

**Files:**
- Create: `/Users/juanlentino/Projects/signal-and-noise-tools/inc/og-card-generator.php`

- [ ] **Step 1: Copy theme's og-image.php as starting point**

```bash
cp /Users/juanlentino/projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551/inc/og-image.php \
   /Users/juanlentino/Projects/signal-and-noise-tools/inc/og-card-generator.php
```

- [ ] **Step 2: Modify font path resolution**

In `inc/og-card-generator.php`, find this block (was at theme line 160-161):

```php
$bebas_path  = get_theme_file_path( 'assets/fonts/og/BebasNeue-Regular.ttf' );
$dmmono_path = get_theme_file_path( 'assets/fonts/og/DMMono-Light.ttf' );
```

Replace with:

```php
// Fonts are theme-owned (the brand owns the typography). Theme registers
// a listener on this filter that returns absolute paths to its TTF files.
// If no listener present, generator falls through to imagettftext error
// handling (returns false; caller falls back to featured image).
$font_paths  = (array) apply_filters( 'sn_og_font_paths', array() );
$bebas_path  = $font_paths['bebas'] ?? '';
$dmmono_path = $font_paths['dmmono'] ?? '';

if ( ! $bebas_path || ! file_exists( $bebas_path ) || ! $dmmono_path || ! file_exists( $dmmono_path ) ) {
	// No theme-registered fonts — bail. Yoast falls back to featured image.
	return false;
}
```

- [ ] **Step 3: Update file-level docblock**

Change the docblock's first paragraph (was about the theme's typeface choice) to:

```php
<?php
/**
 * Signal & Noise Tools — per-post OG/Twitter card generator.
 *
 * Renders a 1200×630 brutalist text card per post/page using PHP GD.
 * The active theme owns the typography (Bebas Neue Regular for the
 * title, DM Mono Light for the eyebrow/dek/footer) and registers
 * font paths via the `sn_og_font_paths` filter. Cards cache as PNG
 * files in `wp-content/uploads/sn-og/` and rebuild on every save
 * via `wp_after_insert_post`.
 *
 * Moved from theme inc/og-image.php in Phase 3 (theme v8.4.0 /
 * plugin v1.3.0, 2026-05-16).
 *
 * @package SignalNoiseTools
 */
```

Leave the rest of the file (functions, hooks, Yoast filters) unchanged.

- [ ] **Step 4: Verify the filter call + no remaining get_theme_file_path()**

```bash
cd /Users/juanlentino/Projects/signal-and-noise-tools && \
grep -nE 'sn_og_font_paths|get_theme_file_path' inc/og-card-generator.php
```

Expected: one match for `sn_og_font_paths` (the apply_filters call). Zero matches for `get_theme_file_path` (we replaced both of them).

- [ ] **Step 5: Commit**

```bash
cd /Users/juanlentino/Projects/signal-and-noise-tools && \
git add inc/og-card-generator.php && \
git commit -m "og-card-generator: PHP GD OG card rendering

Moved from theme inc/og-image.php. The only structural change is
font path resolution: instead of get_theme_file_path(), the
generator calls apply_filters('sn_og_font_paths', []) and reads
the resulting array. The theme owns the brand typography and
will register a listener (via inc/og-fonts.php) in v8.4.0."
```

---

### Task 6: Add `inc/reading-time.php` to plugin

Calculation + caching + shortcode + render_block bridge.

**Files:**
- Create: `/Users/juanlentino/Projects/signal-and-noise-tools/inc/reading-time.php`

- [ ] **Step 1: Copy theme's reading-time.php as starting point**

```bash
cp /Users/juanlentino/projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551/inc/reading-time.php \
   /Users/juanlentino/Projects/signal-and-noise-tools/inc/reading-time.php
```

- [ ] **Step 2: Update file-level docblock**

Replace the file-header docblock with:

```php
<?php
/**
 * Signal & Noise Tools — reading time calculation, caching, and legacy cleanup.
 *
 * Owns three concerns:
 *   1. Calculation. `sn_calculate_reading_time()` strips Gutenberg
 *      block comments, shortcodes, and HTML, then divides word count
 *      by a filterable WPM (default 225). One-minute floor.
 *   2. Caching. Result stored in `_sn_reading_time_minutes` post
 *      meta. `[sn_reading_time]` shortcode reads from this cache,
 *      populating lazily on first render. Rebuilt on wp_after_insert_post.
 *   3. Legacy cleanup admin tab (hooks plugin-side
 *      `sn_admin_reading_time_tab` action — was a cross-package
 *      hook in v1.2.0, now intra-plugin after Phase 3).
 *
 * Moved from theme inc/reading-time.php in Phase 3 (theme v8.4.0 /
 * plugin v1.3.0, 2026-05-16). Function names unchanged.
 *
 * @package SignalNoiseTools
 */
```

Leave everything else identical (functions, shortcode registration, hooks).

- [ ] **Step 3: Verify no theme-path deps remain**

```bash
cd /Users/juanlentino/Projects/signal-and-noise-tools && \
grep -nE 'get_theme_file_path|get_stylesheet_directory|get_template_directory' inc/reading-time.php
```

Expected: zero matches.

- [ ] **Step 4: Commit**

```bash
cd /Users/juanlentino/Projects/signal-and-noise-tools && \
git add inc/reading-time.php && \
git commit -m "reading-time: calculation, caching, shortcode, render_block bridge

Moved from theme inc/reading-time.php. Same 396 LOC, same function
names — no API changes. The sn_admin_reading_time_tab action hook
was a cross-package contract in v1.2.0 (theme listening for plugin-
fired action); after this move it's intra-plugin (both sides in
the plugin), eliminating one of the awkward cross-package vectors."
```

---

### Task 7: Add pre-flight guard #3 + update require_once chain in plugin bootstrap

**Files:**
- Modify: `/Users/juanlentino/Projects/signal-and-noise-tools/signal-and-noise-tools.php`

- [ ] **Step 1: Read current bootstrap structure**

```bash
cat /Users/juanlentino/Projects/signal-and-noise-tools/signal-and-noise-tools.php | head -80
```

Identify (a) the version field line, (b) the existing guards #1 and #2, (c) the `require_once` chain.

- [ ] **Step 2: Add guard #3 BEFORE the require_once chain**

Find the comment marking the existing guards (something like `// Guard #2 …`). Immediately after the LAST guard but BEFORE the first `require_once __DIR__ . '/inc/...';`, insert:

```php
// ── Guard #3 (v1.3.0): function-redeclare defense ──────────────────
//
// Phase 3 moved og-image.php, reading-time.php, and notes-and-provenance.php
// from theme to plugin. If the theme still ships those files (i.e. user
// installed plugin v1.3.0 before theme v8.4.0), our require_once chain
// would PHP-fatal at parse time with "Cannot redeclare function." Bail
// with a clear admin notice instead of a white-screen-of-death.
$theme_dir = get_template_directory();
$retired_in_theme = array(
	$theme_dir . '/inc/og-image.php',
	$theme_dir . '/inc/reading-time.php',
	$theme_dir . '/inc/notes-and-provenance.php',
);
foreach ( $retired_in_theme as $sn_phase3_legacy_file ) {
	if ( file_exists( $sn_phase3_legacy_file ) ) {
		add_action( 'admin_notices', function() use ( $sn_phase3_legacy_file ) {
			$rel = str_replace( ABSPATH, '', $sn_phase3_legacy_file );
			echo '<div class="notice notice-error"><p><strong>Signal & Noise Tools v1.3.0:</strong> theme still ships <code>' . esc_html( $rel ) . '</code>. Update theme to v8.4.0+ first to avoid function-redeclare fatals. Plugin require chain skipped.</p></div>';
		} );
		return; // Skip the require_once chain entirely.
	}
}
unset( $theme_dir, $retired_in_theme, $sn_phase3_legacy_file );
```

- [ ] **Step 3: Update require_once chain**

After the existing `require_once` lines (whatever they are — `inc/seo.php`, `inc/security-headers.php`, etc.), ADD these 5 lines in this exact order (content-surfaces FIRST because it defines constants used by migrations):

```php
require_once __DIR__ . '/inc/content-rendering-helpers.php';
require_once __DIR__ . '/inc/content-surfaces.php';
require_once __DIR__ . '/inc/content-migrations.php';
require_once __DIR__ . '/inc/og-card-generator.php';
require_once __DIR__ . '/inc/reading-time.php';
```

Order rationale: `content-rendering-helpers` defines functions called by `content-migrations`. `content-surfaces` defines constants (`SN_*_MIGR_OPT`) used by `content-migrations`. So helpers + surfaces both load before migrations.

- [ ] **Step 4: Verify**

```bash
cd /Users/juanlentino/Projects/signal-and-noise-tools && \
grep -nE "Guard #|require_once.*inc/" signal-and-noise-tools.php
```

Expected: 3 guards visible (#1, #2, #3) before any of the 5 new require_once lines. Old require_once lines (for seo.php, security-headers.php, cloudflare-purge.php, plausible-*, admin-bar, admin-page, rest-api, rss-plausible-tracker) still present.

- [ ] **Step 5: Commit**

```bash
cd /Users/juanlentino/Projects/signal-and-noise-tools && \
git add signal-and-noise-tools.php && \
git commit -m "bootstrap: add guard #3 + require 5 new Phase 3 modules

Guard #3 detects if the theme still ships inc/og-image.php,
inc/reading-time.php, or inc/notes-and-provenance.php (i.e. user
installed plugin v1.3.0 before theme v8.4.0). Bails the require_once
chain with a clear admin notice instead of PHP-fataling on
function-redeclare. Defense-in-depth — should never fire in
normal operation when release order is respected.

require_once chain order: helpers → surfaces → migrations
(constants and helper functions both need to load before migrations
that reference them)."
```

---

### Task 8: Bump plugin to v1.3.0 + CHANGELOG (NO TAG YET)

**Files:**
- Modify: `/Users/juanlentino/Projects/signal-and-noise-tools/signal-and-noise-tools.php`
- Modify: `/Users/juanlentino/Projects/signal-and-noise-tools/CHANGELOG.md`

- [ ] **Step 1: Bump Version field**

In `signal-and-noise-tools.php`, change `Version: 1.2.0` to `Version: 1.3.0`. If there's a `define( 'SN_TOOLS_VERSION', ... )` line, bump that too.

- [ ] **Step 2: Prepend CHANGELOG entry**

In `CHANGELOG.md`, insert above the existing `[1.2.0]` entry:

```markdown
## [1.3.0] - 2026-05-16

### Added
- `inc/og-card-generator.php` — OG/Twitter card PHP GD generation, caching, Yoast filter integration. Fonts provided by the theme via `sn_og_font_paths` filter (new cross-package contract).
- `inc/reading-time.php` — reading time calculation, caching in `_sn_reading_time_minutes` post meta, `[sn_reading_time]` shortcode, `render_block` bridge for block-context shortcodes. The previously cross-package `sn_admin_reading_time_tab` hook is now intra-plugin.
- `inc/content-surfaces.php` — Notes category, /notes Page, /provenance + /over-detection + /as-substrate Pages, permalink structure, query loop scoping.
- `inc/content-migrations.php` — 11 one-shot content seed migrations for the Provenance pillar (body, refinements, byline reading time, split, AS substrate seed, card2 longform, card readtimes dynamic, catalog numbers, post-date displaytype, eyebrow dynamic, clear notes template override).
- `inc/content-rendering-helpers.php` — Gutenberg block-markup generators called from migrations (byline_reading_time, toc, papers_index).
- `inc/seed-content/` — HTML bodies consumed by content migrations.

### Changed
- Pre-flight guard #3 added to bootstrap: bails with admin notice if the theme still ships `inc/og-image.php`, `inc/reading-time.php`, or `inc/notes-and-provenance.php` (defends against accidental install-order inversion).

### Notes
- Requires theme v8.4.0+. If installed against an older theme, guard #3 fires; plugin loads dormant. After upgrading the theme, plugin activates normally.
- One new cross-package contract: `sn_og_font_paths` filter (theme listens, plugin dispatches).
```

- [ ] **Step 3: Commit + push (but DO NOT TAG YET)**

```bash
cd /Users/juanlentino/Projects/signal-and-noise-tools && \
git add signal-and-noise-tools.php CHANGELOG.md && \
git commit -m "v1.3.0 staging: absorb theme-coupled content surfaces + OG + reading time

Three modules moved from theme inc/ into plugin inc/, with the larger
notes-and-provenance.php (1058 LOC) split into 3 smaller files
(surfaces, migrations, rendering-helpers). One new cross-package
filter (sn_og_font_paths) lets theme own brand fonts while plugin
owns rendering. Guard #3 added for install-order safety.

NOT TAGGED YET — must wait for theme v8.4.0 to ship and remove its
copies of the three retired modules. See plan task 14." && \
git push origin main
```

NOTE: We commit + push but do **not** create the v1.3.0 tag. The tag fires the auto-deploy; we want the theme to ship first.

---

## THEME PHASE — Code changes (deletions + new og-fonts.php)

### Task 9: Add `inc/og-fonts.php` to theme

The new file that listens for `sn_og_font_paths` and returns the brand TTF paths.

**Files:**
- Create: `inc/og-fonts.php` (in theme worktree)

- [ ] **Step 1: Create the file**

```php
<?php
/**
 * Signal & Noise — OG card font paths.
 *
 * The plugin (signal-and-noise-tools v1.3.0+) owns OG card generation
 * via inc/og-card-generator.php. It calls apply_filters('sn_og_font_paths', [])
 * to learn which TTF files to embed. This file is the theme's response:
 * the brand owns the typography, so the theme provides the paths to
 * its bundled TTFs in assets/fonts/og/.
 *
 * Phase 3 (v8.4.0, 2026-05-16) — replaces the theme-side card generator
 * with a thin font-path provider.
 *
 * @package SignalNoise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'sn_og_font_paths', function( $paths ) {
	return array(
		'bebas'  => get_theme_file_path( 'assets/fonts/og/BebasNeue-Regular.ttf' ),
		'dmmono' => get_theme_file_path( 'assets/fonts/og/DMMono-Light.ttf' ),
	);
} );
```

- [ ] **Step 2: Verify fonts exist**

```bash
cd /Users/juanlentino/projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551 && \
ls -la assets/fonts/og/
```

Expected: both `BebasNeue-Regular.ttf` and `DMMono-Light.ttf` present. If missing, STOP — that's a separate issue.

- [ ] **Step 3: Commit**

```bash
cd /Users/juanlentino/projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551 && \
git add inc/og-fonts.php && \
git commit -m "og-fonts: register theme typefaces for plugin's OG card generator

Provides absolute paths to assets/fonts/og/*.ttf via the new
sn_og_font_paths filter contract. Plugin v1.3.0's inc/og-card-generator.php
reads this filter and embeds the fonts via PHP GD. Brand typography
stays in the theme; rendering pipeline lives in the plugin."
```

---

### Task 10: Delete three retired modules + update functions.php + delete seed-content/

**Files:**
- Delete: `inc/og-image.php`, `inc/reading-time.php`, `inc/notes-and-provenance.php`, `inc/seed-content/`
- Modify: `functions.php`

- [ ] **Step 1: Delete the three module files**

```bash
cd /Users/juanlentino/projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551 && \
git rm inc/og-image.php inc/reading-time.php inc/notes-and-provenance.php && \
git rm -r inc/seed-content
```

- [ ] **Step 2: Update functions.php**

Open `functions.php`, find the require_once chain. **Remove** these 3 lines:

```php
require_once __DIR__ . '/inc/notes-and-provenance.php';
require_once __DIR__ . '/inc/reading-time.php';
require_once __DIR__ . '/inc/og-image.php';
```

**Add** this 1 line in the same area (order isn't strict — anywhere in the require_once block):

```php
require_once __DIR__ . '/inc/og-fonts.php';
```

- [ ] **Step 3: Verify final state**

```bash
cd /Users/juanlentino/projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551 && \
echo "=== inc/ contents ===" && ls inc/ && \
echo && echo "=== functions.php require chain ===" && \
grep -n 'require_once' functions.php
```

Expected:
- `inc/` lists exactly: `assets-frontend.php`, `frontend-filters.php`, `og-fonts.php`, `page-notes-render.php`, `page-notes-template.php`, `patterns.php`, `setup.php`, `template-maintenance.php` (8 files, no `seed-content`, no `og-image.php`, no `reading-time.php`, no `notes-and-provenance.php`).
- `functions.php` shows 8 require_once lines (one per inc/ file).

- [ ] **Step 4: Check for stale references to deleted code**

```bash
cd /Users/juanlentino/projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551 && \
grep -rn 'og-image\|sn_generate_og_card\|sn_reading_time\|sn_seed_content_surfaces\|sn_calculate_reading_time' --include='*.php' .
```

Expected: matches inside `inc/page-notes-render.php` (which calls `sn_notes_render_reading_time` — a different function, defined locally in page-notes-render.php itself; verify by checking that function's definition is in page-notes-render.php). NO matches for any other deleted functions.

If `page-notes-render.php` has stale references to deleted functions, those need fixing. Most likely candidates: direct calls to `sn_calculate_reading_time()` or `sn_generate_og_card()`. If found, the call sites need to either (a) call the plugin's same-named function (which works once plugin v1.3.0 loads), or (b) read from `_sn_reading_time_minutes` post meta directly.

- [ ] **Step 5: Commit**

```bash
cd /Users/juanlentino/projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551 && \
git add functions.php && \
git commit -m "inc/: delete og-image, reading-time, notes-and-provenance + seed-content

Three theme-coupled modules retired in Phase 3. They now live in
the plugin (signal-and-noise-tools v1.3.0+):
- inc/og-image.php → plugin inc/og-card-generator.php (with sn_og_font_paths filter contract)
- inc/reading-time.php → plugin inc/reading-time.php
- inc/notes-and-provenance.php → plugin inc/content-surfaces.php + content-migrations.php + content-rendering-helpers.php
- inc/seed-content/ → plugin inc/seed-content/

Theme retains the new inc/og-fonts.php (~15 LOC) that registers
the sn_og_font_paths filter listener so the plugin's OG generator
can find the brand typography. functions.php updated accordingly:
removed 3 require_once lines, added 1."
```

---

### Task 11: Update `docs/WORDPRESS-REFERENCE.md` §10.0

Add `sn_og_font_paths` to the contract surface table.

**Files:**
- Modify: `docs/WORDPRESS-REFERENCE.md`

- [ ] **Step 1: Find the contract surface table in §10.0**

```bash
cd /Users/juanlentino/projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551 && \
grep -n 'sn_purge_all_caches_result\|sn_clear_template_overrides_result' docs/WORDPRESS-REFERENCE.md
```

The table currently has 2 rows. Find their location.

- [ ] **Step 2: Add new row + update count reference**

Add a 3rd row to the table:
- Hook name: `sn_og_font_paths`
- Type: `filter`
- Direction: plugin dispatches → theme listens
- Implementation pointer: theme `inc/og-fonts.php` registers; plugin `inc/og-card-generator.php` calls

The row formatting should match the existing 2 rows in the table.

If there's a sentence above or below the table saying "**2 hooks**" or similar count, update to "**3 hooks**". Also update the v8.3.0 / Phase 2b note (if it claims "now 2 hooks") to mention Phase 3 added one more.

- [ ] **Step 3: Commit**

```bash
cd /Users/juanlentino/projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551 && \
git add docs/WORDPRESS-REFERENCE.md && \
git commit -m "docs(WP-REFERENCE): add sn_og_font_paths to §10.0 contract surface

Cross-package hook count grows from 2 to 3. Plugin's OG card generator
calls apply_filters('sn_og_font_paths', []); theme's inc/og-fonts.php
returns the brand TTF paths."
```

---

### Task 12: Bump theme to v8.4.0 + CHANGELOG + COMMIT + TAG + PUSH

This is the critical commit that fires the theme auto-deploy.

**Files:**
- Modify: `style.css`
- Modify: `CHANGELOG.md`

- [ ] **Step 1: Bump style.css Version**

Change `Version: 8.3.0` to `Version: 8.4.0` in `style.css` header.

- [ ] **Step 2: Prepend CHANGELOG entry**

In `CHANGELOG.md`, insert above the existing `[8.3.0]` entry:

```markdown
## [8.4.0] - 2026-05-16

### Removed
- `inc/og-image.php` — moved to plugin `inc/og-card-generator.php`. Plugin generates OG cards via PHP GD; theme provides Bebas Neue + DM Mono TTFs through new `sn_og_font_paths` filter.
- `inc/reading-time.php` — moved to plugin `inc/reading-time.php`. Calculation + caching + `[sn_reading_time]` shortcode + `render_block` bridge all plugin-side.
- `inc/notes-and-provenance.php` (1,058 LOC) — moved to plugin and split into three smaller files: `inc/content-surfaces.php`, `inc/content-migrations.php`, `inc/content-rendering-helpers.php`.
- `inc/seed-content/` directory — moved to plugin alongside the migrations that consume it.

### Added
- `inc/og-fonts.php` — registers the theme's typefaces as the response to the plugin's `sn_og_font_paths` filter.

### Changed
- Cross-package contract surface grows from 2 hooks to 3 (added `sn_og_font_paths`).
- `docs/WORDPRESS-REFERENCE.md §10.0` updated to reflect the new contract.

### Notes
- Requires plugin v1.3.0+ for full functionality. While plugin v1.2.0 is still active (during the ~30-60s deploy gap before plugin v1.3.0 ships), the `[sn_reading_time]` shortcode renders as the literal token string in any page that uses it (notably /provenance byline). Cosmetic, recoverable on next pageload after plugin v1.3.0 lands.
```

- [ ] **Step 3: Commit + push (worktree pattern) + TAG + push tag**

```bash
cd /Users/juanlentino/projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551 && \
git add style.css CHANGELOG.md && \
git commit -m "v8.4.0: move 3 theme-coupled modules to plugin; add og-fonts shim

Phase 3 lands. Theme inc/ shrinks from 11 files to 8 — only files
that own rendering or theme-specific defenses remain. Operational
concerns (OG card generation, reading-time data, content surfaces +
migrations) absorbed by plugin v1.3.0.

One new cross-package filter: sn_og_font_paths (theme registers,
plugin dispatches). Theme keeps Bebas Neue + DM Mono TTFs in
assets/fonts/og/ and provides them via inc/og-fonts.php.

Brief breakage window (~30-60s) between this tag and plugin v1.3.0
tag: [sn_reading_time] shortcode renders as literal token on
/provenance byline. Recoverable; documented in CHANGELOG." && \
git push origin HEAD:main && \
git tag -a v8.4.0 -m "v8.4.0 — move og/reading-time/notes-and-provenance to plugin" && \
git push origin v8.4.0
```

- [ ] **Step 4: Watch theme auto-deploy**

```bash
sleep 8 && \
gh run list --repo juanlentino/signal-and-noise --workflow=deploy.yml --limit 1
```

Expected: a run in `queued` or `in_progress` status, triggered by tag `v8.4.0`. Then watch to completion:

```bash
RUN_ID=$(gh run list --repo juanlentino/signal-and-noise --workflow=deploy.yml --limit 1 --json databaseId -q '.[0].databaseId') && \
gh run watch "$RUN_ID" --repo juanlentino/signal-and-noise --exit-status 2>&1 | tail -10
```

Expected: all 3 steps green (auth, git pull, purge cache). If any step fails, STOP and diagnose before tagging plugin.

---

### Task 13: Theme deploy verification (intermediate, before plugin tag)

**Files:** N/A (verification only).

- [ ] **Step 1: Verify deleted theme files return 404**

```bash
for f in og-image.php reading-time.php notes-and-provenance.php; do
  echo -n "$f: "
  curl -sS -o /dev/null -w '%{http_code}\n' "https://juanlentino.com/wp-content/themes/signal-and-noise/inc/$f"
done && \
echo -n "seed-content/: " && \
curl -sS -o /dev/null -w '%{http_code}\n' 'https://juanlentino.com/wp-content/themes/signal-and-noise/inc/seed-content/'
```

Expected: all four return 404. (Theme `inc/` doesn't have an Index file, so a 404 on `seed-content/` confirms the directory is gone OR has no index — sufficient.)

- [ ] **Step 2: Verify theme version**

```bash
curl -sS https://juanlentino.com/wp-content/themes/signal-and-noise/style.css | grep -E '^Version:'
```

Expected: `Version: 8.4.0`.

- [ ] **Step 3: Quick sanity-check homepage still loads**

```bash
curl -sS -o /dev/null -w 'homepage HTTP %{http_code}\n' https://juanlentino.com/
```

Expected: `200`.

If any of these check fails, the plugin tag should be deferred until the theme issue is fixed.

---

### Task 14: Tag plugin v1.3.0 + push tag

Immediately after theme deploy completes. Target gap from Task 12 commit to here: < 60 seconds.

**Files:** N/A (existing commit gets tagged).

- [ ] **Step 1: Tag the staged v1.3.0 commit**

```bash
cd /Users/juanlentino/Projects/signal-and-noise-tools && \
git log --oneline -1 && \
git tag -a v1.3.0 -m "v1.3.0 — absorb og + reading-time + content-surfaces from theme" && \
git push origin v1.3.0
```

The most recent commit (HEAD on `main`) should be the "v1.3.0 staging" commit from Task 8.

- [ ] **Step 2: Watch plugin auto-deploy**

```bash
sleep 8 && \
RUN_ID=$(gh run list --repo juanlentino/signal-and-noise-tools --workflow=deploy.yml --limit 1 --json databaseId -q '.[0].databaseId') && \
echo "Run ID: $RUN_ID" && \
gh run watch "$RUN_ID" --repo juanlentino/signal-and-noise-tools --exit-status 2>&1 | tail -10
```

Expected: all 3 steps green (configure SSH, ssh git checkout, CF purge). The plugin's deploy.yml runs `git fetch && git checkout v1.3.0` on the live server, then POSTs to `/purge-cache`.

---

### Task 15: End-to-end smoke test

**Files:** N/A (verification only).

- [ ] **Step 1: Verify plugin version on live server**

```bash
ssh -i /tmp/sn-tools-deploy_ed25519 -o UserKnownHostsFile=/tmp/sn-tools-known_hosts sn-plugin@157.245.116.64 \
  'cd /home/master/applications/nffqxsrgxz/public_html && wp plugin list --name=signal-and-noise-tools' \
  2>&1 | grep -v 'WARNING\|store now\|post-quantum\|See https'
```

Expected: `signal-and-noise-tools  active  none  1.3.0` (or similar; the version column should show `1.3.0`).

NOTE: if `/tmp/sn-tools-deploy_ed25519` was wiped at end of Phase 2c, this verification has to be done via Cloudways shell-in-browser instead. Alternative: hit the REST endpoint as a black-box test (Step 4 below).

- [ ] **Step 2: Verify plugin's inc/ has the 5 new modules**

If SSH access is available:

```bash
ssh -i /tmp/sn-tools-deploy_ed25519 -o UserKnownHostsFile=/tmp/sn-tools-known_hosts sn-plugin@157.245.116.64 \
  'ls /home/master/applications/nffqxsrgxz/public_html/wp-content/plugins/signal-and-noise-tools/inc/' \
  2>&1 | grep -v 'WARNING\|store now\|post-quantum\|See https'
```

Expected: lists include `og-card-generator.php`, `reading-time.php`, `content-surfaces.php`, `content-migrations.php`, `content-rendering-helpers.php`, `seed-content/` — plus the pre-existing plugin modules (seo.php, security-headers.php, etc.).

- [ ] **Step 3: Verify /notes route still renders**

```bash
curl -sS -o /dev/null -w '/notes HTTP %{http_code}\n' https://juanlentino.com/notes/
```

Expected: `200`. The theme's `page-notes-template.php` + `page-notes-render.php` are unaffected by Phase 3 (they stayed in theme).

- [ ] **Step 4: Verify `[sn_reading_time]` shortcode renders on /provenance**

```bash
curl -sS 'https://juanlentino.com/provenance/' | grep -oE '[0-9]+ min read' | head -3
```

Expected: at least one match like `5 min read`. If you see literal `[sn_reading_time]` strings in the page source (i.e. shortcode unprocessed), the plugin's reading-time module didn't register — STOP and diagnose.

- [ ] **Step 5: Verify OG card generation**

This is harder to test from CLI without manually editing a post. Black-box check: visit a post URL, view-source, look for `<meta property="og:image" content="...sn-og/...png" />`.

```bash
# Grab a recent post URL from the homepage, then check its OG meta:
POST_URL=$(curl -sS https://juanlentino.com/ | grep -oE 'https://juanlentino\.com/notes/[a-z0-9-]+/' | head -1)
echo "Sampling: $POST_URL"
curl -sS "$POST_URL" | grep -oE '<meta property="og:image" content="[^"]+"' | head -1
```

Expected: `<meta property="og:image" content="https://juanlentino.com/wp-content/uploads/sn-og/<slug>.png"` (or similar). If the OG image URL doesn't include `/sn-og/`, Yoast is falling back to featured image — meaning the plugin's card generator isn't running. Likely cause: theme's `inc/og-fonts.php` filter isn't returning paths, or plugin's `og-card-generator.php` isn't loaded.

If diagnosing: SSH to live server (if accessible) and tail `wp-content/debug.log` for plugin load errors.

- [ ] **Step 6: Confirm CF purge worked end-to-end**

```bash
auth=$(printf '%s:%s' 'juanlentino' '<APP_PASSWORD>' | base64)
curl -sS -w '\n%{http_code}\n' -X POST 'https://juanlentino.com/wp-json/signal-noise/v1/purge-cache' \
  -H "Authorization: Basic $auth" -H 'Content-Length: 0'
```

Expected: `200` with `{"ok":true,"message":"All caches purged.","data":{"cleared":N}}`.

NOTE: replace `<APP_PASSWORD>` with the actual WP Application Password (the one stored as `WP_DEPLOY_APP_PASSWORD` GHA secret). If not accessible to the runner, this step verifies indirectly that the plugin loaded — the endpoint requires the plugin to be active.

---

## Rollback paths

**If theme v8.4.0 deploys but causes broken pages** (e.g., missing function references in page-notes-render.php): hotfix tag from v8.3.0 + tag v8.4.1 with the fix. Cloudways auto-pulls the new tag within ~30s.

**If plugin v1.3.0 fails to deploy or activate** (e.g., guard #3 fires because theme v8.4.0 didn't actually deploy): manually push plugin v1.3.1 with a fix, OR re-tag plugin pointing back at the v1.2.0 commit (`git tag -fa v1.3.0 v1.2.0_commit_sha` — destructive, avoid). Or: roll forward by force-deploying theme v8.4.0 again via workflow_dispatch.

**If the `[sn_reading_time]` shortcode breakage window stretches longer than expected**: visitors see the literal token on /provenance. Mitigate by triggering plugin v1.3.0 deploy via `gh workflow run deploy.yml --repo juanlentino/signal-and-noise-tools --ref v1.3.0` to retry the SSH/checkout step.

**If OG card generation breaks silently**: Yoast falls back to featured images. Diagnostic: check that theme `inc/og-fonts.php` is loaded (look for `add_filter('sn_og_font_paths', ...)` in code on disk via SSH).

---

## Out of scope (per spec)

- Renaming `_sn_reading_time_minutes` post meta key.
- Adding test infrastructure.
- Repointing `sn_*` function names to namespaced equivalents.
- Cleaning up orphaned `wp_options` rows from now-removed theme constants.
