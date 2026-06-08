# Settings-Hygiene Batch A — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Expose 7 hardcoded values from recently-added features as plugin settings, routed through a standalone-safe theme↔plugin filter contract, and fix the dead `$limit` param in the related-notes shortcode.

**Architecture:** Plugin owns the settings UI (new `theme` subtree in the existing `sn_settings` option, rendered on a new "Front-End" sub-tab under Tools, saved via a dedicated `save_theme` action through `sn_setting_update()`). The plugin registers `add_filter` callbacks that supply the configured value to filters the theme/plugin already (or newly) expose. The theme calls `apply_filters('sn_x', <default>)` at each source site; defaults equal today's hardcoded values, so nothing changes until the owner opts in. No Settings API migration (handbook-validated — the hand-rolled dispatcher already provides nonce/cap/sanitize).

**Tech Stack:** PHP, WordPress (FSE theme + companion plugin), standalone CLI fixture tests (`tests/*.php`, no PHPUnit), `phpcs` (WPCS).

**Spec:** `docs/superpowers/specs/2026-06-08-settings-hygiene-batch-a-design.md`

**Repos:**
- THEME: this worktree (`/Users/juanlentino/Projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551`), on `main`, ships **v9.12.0**.
- PLUGIN: `/Users/juanlentino/Projects/signal-and-noise-tools`, **origin/main is v4.11.0** (local non-worktree checkout is stale at v4.7.0). Work in a fresh worktree off `origin/main`; ships next minor (**v4.12.0**).

**Settings (default = current value):** related_count 3 · palette_recent_count 8 · palette_enabled true · json_feed_items 20 · updated_threshold_days 14 · reading_wpm 225 · ai_model `claude-sonnet-4-6`.

---

## Task 0: Pre-implementation verification (no code)

**Files:** none (read-only confirmation + plugin worktree creation).

- [ ] **Step 1: Create a fresh plugin worktree off current origin/main**

```bash
cd /Users/juanlentino/Projects/signal-and-noise-tools
git fetch origin --quiet
git worktree add .claude/worktrees/settings-batch-a origin/main -b claude/settings-batch-a
cd .claude/worktrees/settings-batch-a
grep -m1 'Version:' signal-and-noise-tools.php   # expect 4.11.0
```
Expected: a worktree at v4.11.0. **All plugin edits below happen in this worktree.** Let `PLUGINWT` = its absolute path.

- [ ] **Step 2: Confirm the plugin anchors exist (grep, current main)**

```bash
cd "$PLUGINWT"
grep -nE "function sn_settings_defaults|function sn_setting_update" inc/settings.php
grep -nE "function sn_admin_post_handlers" inc/admin-post-handler.php
grep -n "'reading-time'" inc/admin-tabs-data.php        # the Tools sub_tabs array
grep -nE "function sn_admin_flash|sn_admin_flash_messages|flash_messages" inc/admin-flash-messages.php
ls inc/admin-forms/                                       # confirm identity-and-seo.php pattern dir
grep -n "SN_READING_TIME_DEFAULT_WPM" inc/reading-time.php
```
Expected: each returns a hit. Record the exact function names + the Tools `sub_tabs` array shape + the flash-registration function name + the `SN_READING_TIME_DEFAULT_WPM` value (confirm it is `225`). If any anchor differs from this plan, adapt the insertion point but keep the new code identical.

- [ ] **Step 3: Confirm the AI model allowlist ids the provider exposes**

Read `inc/ai-bootstrap.php` around line 208 and the `claude-api` skill. Confirm the default `claude-sonnet-4-6` is correct, and the exact id strings for the costlier/cheaper options. Use the verified set in `sn_theme_ai_models()` (Task P2). If only `claude-sonnet-4-6` is confirmed-valid, ship the select with just the confirmed ids — never an unvalidated id.

- [ ] **Step 4: Confirm the theme source sites (this worktree)**

```bash
cd /Users/juanlentino/Projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551
grep -n "sn_related_notes_query( \$post_id, 3 )" inc/related-notes.php   # the dead-literal bug site
grep -n "'posts_per_page'      => 8" inc/command-palette.php
grep -n "'posts_per_page'      => 20" inc/feed-json.php
grep -n "function sn_cmdk_enqueue" inc/command-palette.php
```
Expected: each returns a hit. These are the theme `apply_filters` insertion points.

---

# PART 1 — THEME (this worktree, ships v9.12.0)

## Task T1: Related-notes count filter + dead-param bug fix

**Files:**
- Modify: `inc/related-notes.php` (the shortcode call site)
- Test: `tests/related-notes.php`

- [ ] **Step 1: Add an `apply_filters` stub to the test harness (enables filter-injection)**

In `tests/related-notes.php`, in the WP-stub block (near the other `if ( ! function_exists(...) )` stubs), add:

```php
if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $tag, $value ) {
		return array_key_exists( $tag, $GLOBALS['__filters'] ?? array() )
			? $GLOBALS['__filters'][ $tag ]
			: $value;
	}
}
$GLOBALS['__filters'] = array();
```

- [ ] **Step 2: Write the failing test (bug regression + filter contract)**

Append before the `Result:` line in `tests/related-notes.php`:

```php
// v9.12.0: the shortcode must honor sn_related_count, not a dead literal 3.
$GLOBALS['POSTS'] = array();
mk_post( 1, array( 10 ), 5000, 'Current', 'https://x/notes/1/' );
mk_post( 2, array( 10 ), 4000, 'Sib2', 'https://x/notes/2/' );
mk_post( 3, array( 10 ), 3000, 'Sib3', 'https://x/notes/3/' );
mk_post( 4, array( 10 ), 2000, 'Sib4', 'https://x/notes/4/' );
mk_post( 5, array( 10 ), 1000, 'Sib5', 'https://x/notes/5/' );
$GLOBALS['__queried_id'] = 1;
$GLOBALS['__filters']['sn_related_count'] = 5;
sn_related_notes_shortcode();
ok( (int) $GLOBALS['__last_qargs']['posts_per_page'] === 5, 'related: shortcode honors sn_related_count=5 (not the dead literal 3)' );
$GLOBALS['__filters'] = array();
sn_related_notes_shortcode();
ok( (int) $GLOBALS['__last_qargs']['posts_per_page'] === 3, 'related: default count is 3 when unfiltered' );
```

- [ ] **Step 3: Run the test — verify it FAILS**

Run: `php tests/related-notes.php`
Expected: FAIL on "honors sn_related_count=5" (today the call site passes literal `3`, so `posts_per_page` is `3`, not `5`).

- [ ] **Step 4: Fix the call site (the bug + the filter)**

In `inc/related-notes.php`, in `sn_related_notes_shortcode()`, change:

```php
	$related = sn_related_notes_query( $post_id, 3 );
```
to:
```php
	/**
	 * Related-notes count. Default 3; the companion plugin supplies the
	 * configured value via sn_setting('theme.related_count'). Fixing the
	 * prior dead literal that bypassed the $limit param.
	 */
	$related = sn_related_notes_query( $post_id, (int) apply_filters( 'sn_related_count', 3 ) );
```

- [ ] **Step 5: Run the test — verify it PASSES**

Run: `php tests/related-notes.php`
Expected: `Result: N passed, 0 failed.` (N = prior 22 + 2).

- [ ] **Step 6: Commit**

```bash
git add inc/related-notes.php tests/related-notes.php
git commit -m "feat(notes): related-notes count via sn_related_count filter (+ fix dead \$limit literal)"
```

## Task T2: Command-palette recent-count filter

**Files:**
- Modify: `inc/command-palette.php` (the recent query)
- Test: `tests/command-palette.php`

- [ ] **Step 1: Add `apply_filters` stub to the test harness**

In `tests/command-palette.php`, near the existing `function add_action(...)` stub, add:

```php
if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $tag, $value ) {
		return array_key_exists( $tag, $GLOBALS['__filters'] ?? array() ) ? $GLOBALS['__filters'][ $tag ] : $value;
	}
}
$GLOBALS['__filters'] = array();
```

- [ ] **Step 2: Write the failing test**

Append before the `Result:` line:

```php
// v9.12.0: recent count honors sn_palette_recent_count (default 8).
$GLOBALS['__filters']['sn_palette_recent_count'] = 4;
sn_cmdk_build_data();
ok( (int) $GLOBALS['__qargs']['posts_per_page'] === 4, 'palette: recent query honors sn_palette_recent_count=4' );
$GLOBALS['__filters'] = array();
sn_cmdk_build_data();
ok( (int) $GLOBALS['__qargs']['posts_per_page'] === 8, 'palette: default recent count is 8' );
```

- [ ] **Step 3: Run — verify FAIL**

Run: `php tests/command-palette.php`
Expected: FAIL on "honors sn_palette_recent_count=4" (today `posts_per_page` is the literal `8`).

- [ ] **Step 4: Implement**

In `inc/command-palette.php`, in `sn_cmdk_build_data()`, change:

```php
		'posts_per_page'      => 8,
```
to:
```php
		'posts_per_page'      => (int) apply_filters( 'sn_palette_recent_count', 8 ),
```

- [ ] **Step 5: Run — verify PASS**

Run: `php tests/command-palette.php`
Expected: `Result: N passed, 0 failed.`

- [ ] **Step 6: Commit**

```bash
git add inc/command-palette.php tests/command-palette.php
git commit -m "feat(palette): recent-count via sn_palette_recent_count filter"
```

## Task T3: Command-palette enable/disable kill-switch

**Files:**
- Modify: `inc/command-palette.php` (add `sn_cmdk_enabled()`; gate enqueue; add body-class)
- Modify: `assets/css/critical.css` (hide trigger when off)
- Test: `tests/command-palette.php`

- [ ] **Step 1: Write the failing test**

Append before the `Result:` line in `tests/command-palette.php`:

```php
// v9.12.0: palette enable kill-switch (default on).
$GLOBALS['__filters'] = array();
ok( sn_cmdk_enabled() === true, 'palette: enabled by default' );
$GLOBALS['__filters']['sn_palette_enabled'] = false;
ok( sn_cmdk_enabled() === false, 'palette: sn_palette_enabled=false disables it' );
ok( in_array( 'sn-cmdk-off', sn_cmdk_body_class( array( 'home' ) ), true ), 'palette: body class sn-cmdk-off added when disabled' );
$GLOBALS['__filters'] = array();
ok( ! in_array( 'sn-cmdk-off', sn_cmdk_body_class( array( 'home' ) ), true ), 'palette: no sn-cmdk-off class when enabled' );
```

- [ ] **Step 2: Run — verify FAIL**

Run: `php tests/command-palette.php`
Expected: FAIL with "call to undefined function sn_cmdk_enabled()".

- [ ] **Step 3: Implement the gate + body-class + enqueue guard**

In `inc/command-palette.php`, add (above `sn_cmdk_enqueue()`):

```php
/**
 * Whether the reader command palette is active. Default true; the companion
 * plugin supplies sn_setting('theme.palette_enabled') via this filter.
 */
function sn_cmdk_enabled() {
	return (bool) apply_filters( 'sn_palette_enabled', true );
}

/**
 * Add sn-cmdk-off to <body> when the palette is disabled, so the static
 * footer trigger (parts/footer.html) is hidden via critical.css even though
 * the palette stylesheet is not enqueued.
 *
 * @param string[] $classes Body classes.
 * @return string[]
 */
function sn_cmdk_body_class( $classes ) {
	if ( ! sn_cmdk_enabled() ) {
		$classes[] = 'sn-cmdk-off';
	}
	return $classes;
}
```

In `sn_cmdk_enqueue()`, add as the first line of the function body:

```php
	if ( ! sn_cmdk_enabled() ) {
		return;
	}
```

In the bottom hook-registration block (the `if ( ! defined( 'SN_CMDK_TEST' ) ...` guard), add the body-class filter next to the enqueue action:

```php
	add_filter( 'body_class', 'sn_cmdk_body_class' );
```

- [ ] **Step 4: Run — verify PASS**

Run: `php tests/command-palette.php`
Expected: `Result: N passed, 0 failed.`

- [ ] **Step 5: Add the hide rule to always-loaded CSS**

In `assets/css/critical.css`, append:

```css
/* v9.12.0: command-palette kill-switch (sn_palette_enabled=false → body.sn-cmdk-off). */
.sn-cmdk-off .sn-cmdk-trigger { display: none; }
```

- [ ] **Step 6: Commit**

```bash
git add inc/command-palette.php tests/command-palette.php assets/css/critical.css
git commit -m "feat(palette): enable/disable kill-switch via sn_palette_enabled (enqueue gate + body-class hide)"
```

## Task T4: JSON-feed item-count filter

**Files:**
- Modify: `inc/feed-json.php`
- Test: `tests/feed-json.php`

- [ ] **Step 1: Add `apply_filters` stub if absent + write the failing test**

In `tests/feed-json.php`, ensure an `apply_filters` stub exists (add the same stub as Task T1 Step 1 if not present), then append before the `Result:` line:

```php
// v9.12.0: feed item count honors sn_json_feed_items (default 20).
$GLOBALS['__filters']['sn_json_feed_items'] = 5;
sn_json_feed_query_args();   // see Step 3 — extract the args builder if not already a function
ok( (int) $GLOBALS['__last_feed_qargs']['posts_per_page'] === 5, 'json-feed: honors sn_json_feed_items=5' );
$GLOBALS['__filters'] = array();
sn_json_feed_query_args();
ok( (int) $GLOBALS['__last_feed_qargs']['posts_per_page'] === 20, 'json-feed: default item count is 20' );
```

> If `inc/feed-json.php` builds the `WP_Query` args inline (not via a testable function), first extract the args array into a pure helper `sn_json_feed_query_args()` returning the array, and have the render path consume it. Mirror how `tests/related-notes.php` captures `__last_qargs` by stubbing `WP_Query`/recording args in `__last_feed_qargs`. Keep the extraction minimal.

- [ ] **Step 2: Run — verify FAIL**

Run: `php tests/feed-json.php`
Expected: FAIL on "honors sn_json_feed_items=5".

- [ ] **Step 3: Implement**

In `inc/feed-json.php`, change:

```php
		'posts_per_page'      => 20,
```
to:
```php
		'posts_per_page'      => (int) apply_filters( 'sn_json_feed_items', 20 ),
```
(plus the `sn_json_feed_query_args()` extraction if needed per Step 1.)

- [ ] **Step 4: Run — verify PASS**

Run: `php tests/feed-json.php`
Expected: `Result: N passed, 0 failed.`

- [ ] **Step 5: Commit**

```bash
git add inc/feed-json.php tests/feed-json.php
git commit -m "feat(feed): JSON-feed item count via sn_json_feed_items filter"
```

## Task T5: Theme full-suite + lint + release v9.12.0

- [ ] **Step 1: Run the full suite + falsification**

```bash
for f in tests/*.php; do php "$f" 2>&1 | grep -iE "fail|fatal" && echo "  ^in $f"; done; echo "swept"
vendor/bin/phpcs --standard=phpcs.xml.dist inc/related-notes.php inc/command-palette.php inc/feed-json.php
```
Expected: zero failures; phpcs clean. (Falsify one assertion by hand first per `feedback_falsification_test_before_trusting_clean`.)

- [ ] **Step 2: Bump version + CHANGELOG**

`style.css`: `Version: 9.11.3` → `Version: 9.12.0`. `readme.txt`: `Stable tag: 9.11.3` → `9.12.0`. Prepend a CHANGELOG entry (Mimestream `### New` / `### Fixed`): new `sn_related_count` / `sn_palette_recent_count` / `sn_palette_enabled` / `sn_json_feed_items` filters (configurable from the companion plugin); fixed the related-notes dead-`$limit` literal; palette kill-switch.

- [ ] **Step 3: Commit + push + tag**

```bash
git add style.css readme.txt CHANGELOG.md
git commit -m "v9.12.0: configurable related/palette/feed counts + palette kill-switch (plugin-driven filters)"
git push origin HEAD:main
git tag -a v9.12.0 -m "v9.12.0 — front-end filter hooks for plugin-driven settings"
git push origin v9.12.0
```

---

# PART 2 — PLUGIN (worktree off origin/main, ships v4.12.0)

> All paths below are relative to `PLUGINWT` (the worktree from Task 0).

## Task P1: Add the `theme` subtree to settings defaults

**Files:**
- Modify: `inc/settings.php` (`sn_settings_defaults()`)
- Test: `tests/settings-theme.php` (new)

- [ ] **Step 1: Write the failing test (new file)**

Create `tests/settings-theme.php` modeled on an existing plugin settings test (CLI guard, `ok()`, stub `get_option`/`update_option` with an in-memory `$GLOBALS['__opt']`), then:

```php
require __DIR__ . '/../inc/settings.php';
$d = sn_settings_defaults();
ok( isset( $d['theme'] ), 'defaults: theme subtree present' );
ok( $d['theme']['related_count'] === 3, 'defaults: related_count 3' );
ok( $d['theme']['palette_recent_count'] === 8, 'defaults: palette_recent_count 8' );
ok( $d['theme']['palette_enabled'] === true, 'defaults: palette_enabled true' );
ok( $d['theme']['json_feed_items'] === 20, 'defaults: json_feed_items 20' );
ok( $d['theme']['updated_threshold_days'] === 14, 'defaults: updated_threshold_days 14' );
ok( $d['theme']['reading_wpm'] === 225, 'defaults: reading_wpm 225' );
ok( $d['theme']['ai_model'] === 'claude-sonnet-4-6', 'defaults: ai_model sonnet-4-6' );
echo "Result: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
```

- [ ] **Step 2: Run — verify FAIL**

Run: `php tests/settings-theme.php`
Expected: FAIL on "theme subtree present".

- [ ] **Step 3: Implement**

In `inc/settings.php`, in `sn_settings_defaults()`, add (alongside the `og` block):

```php
		'theme' => array(
			'related_count'         => 3,
			'palette_recent_count'  => 8,
			'palette_enabled'       => true,
			'json_feed_items'       => 20,
			'updated_threshold_days' => 14,
			'reading_wpm'           => 225,
			'ai_model'              => 'claude-sonnet-4-6',
		),
```

- [ ] **Step 4: Run — verify PASS** → `php tests/settings-theme.php` → all pass.

- [ ] **Step 5: Commit**

```bash
git add inc/settings.php tests/settings-theme.php
git commit -m "feat(settings): add theme subtree defaults (front-end render knobs)"
```

## Task P2: AI-model allowlist + save handler (clamps + validation)

**Files:**
- Modify: `inc/admin-post-actions.php` (`sn_theme_ai_models()` + `sn_handle_save_theme()`)
- Test: `tests/settings-theme.php`

- [ ] **Step 1: Write the failing test**

Append to `tests/settings-theme.php` (before the `Result:` line). Stub `sn_setting_update` to write into `$GLOBALS['__opt']` and `wp_unslash`/`sanitize_text_field` as identity helpers if not already defined:

```php
require __DIR__ . '/../inc/admin-post-actions.php';
$models = sn_theme_ai_models();
ok( ! empty( $models ) && array_key_exists( 'claude-sonnet-4-6', $models ), 'ai models: list non-empty + contains default' );

sn_handle_save_theme( array( 'theme_related_count' => '99', 'theme_palette_recent_count' => '-3', 'theme_json_feed_items' => '0', 'theme_palette_enabled' => '1', 'theme_ai_model' => 'totally-fake-model', 'theme_updated_threshold_days' => '500', 'theme_reading_wpm' => '5' ) );
ok( (int) sn_setting( 'theme.related_count' ) === 12, 'save: related_count clamps to max 12' );
ok( (int) sn_setting( 'theme.palette_recent_count' ) === 0, 'save: palette_recent_count clamps to min 0' );
ok( (int) sn_setting( 'theme.json_feed_items' ) === 1, 'save: json_feed_items clamps to min 1' );
ok( (int) sn_setting( 'theme.updated_threshold_days' ) === 90, 'save: updated_threshold clamps to max 90' );
ok( (int) sn_setting( 'theme.reading_wpm' ) === 100, 'save: reading_wpm clamps to min 100' );
ok( sn_setting( 'theme.ai_model' ) === 'claude-sonnet-4-6', 'save: off-list ai_model rejected → keeps default' );
ok( sn_setting( 'theme.palette_enabled' ) === true, 'save: palette_enabled true when checkbox present' );

sn_handle_save_theme( array( 'theme_palette_enabled' => '', 'theme_ai_model' => array_keys( sn_theme_ai_models() )[0] ) ); // checkbox absent path uses '' here
ok( sn_setting( 'theme.palette_enabled' ) === false, 'save: palette_enabled false when checkbox absent/empty' );
```

- [ ] **Step 2: Run — verify FAIL** → `php tests/settings-theme.php` → FAIL "undefined function sn_theme_ai_models".

- [ ] **Step 3: Implement**

In `inc/admin-post-actions.php` add:

```php
/**
 * The AI text-generation model allowlist (single source for render + validate).
 * Keys are model ids passed to snt_ai_model_preference; values are UI labels.
 * Confirm ids against wp-ai-client / the claude-api skill (Task 0 Step 3).
 */
function sn_theme_ai_models() {
	return array(
		'claude-sonnet-4-6' => 'Claude Sonnet 4.6 (balanced — default)',
		'claude-opus-4-8'   => 'Claude Opus 4.8 (best — ~5x cost)',
		'claude-haiku-4-5'  => 'Claude Haiku 4.5 (fastest, cheapest)',
	);
}

/**
 * Persist the Front-End settings form. Sparse writes via sn_setting_update()
 * so the sibling sn_settings subtrees are never clobbered. Clamps ints and
 * validates the model select against the allowlist (validation > sanitization).
 *
 * @param array $post Raw $_POST.
 * @return string Flash code.
 */
function sn_handle_save_theme( $post ) {
	$ok  = sn_setting_update( 'theme.related_count',          max( 1, min( 12, (int) ( $post['theme_related_count'] ?? 3 ) ) ) );
	$ok &= sn_setting_update( 'theme.palette_recent_count',   max( 0, min( 20, (int) ( $post['theme_palette_recent_count'] ?? 8 ) ) ) );
	$ok &= sn_setting_update( 'theme.palette_enabled',        ! empty( $post['theme_palette_enabled'] ) );
	$ok &= sn_setting_update( 'theme.json_feed_items',        max( 1, min( 50, (int) ( $post['theme_json_feed_items'] ?? 20 ) ) ) );
	$ok &= sn_setting_update( 'theme.updated_threshold_days', max( 1, min( 90, (int) ( $post['theme_updated_threshold_days'] ?? 14 ) ) ) );
	$ok &= sn_setting_update( 'theme.reading_wpm',            max( 100, min( 400, (int) ( $post['theme_reading_wpm'] ?? 225 ) ) ) );

	$allowed = array_keys( sn_theme_ai_models() );
	$model   = isset( $post['theme_ai_model'] ) ? sanitize_text_field( wp_unslash( $post['theme_ai_model'] ) ) : '';
	$ok     &= sn_setting_update( 'theme.ai_model', in_array( $model, $allowed, true ) ? $model : (string) sn_setting( 'theme.ai_model', $allowed[0] ) );

	return $ok ? 'theme_saved' : 'theme_unchanged';
}
```

- [ ] **Step 4: Run — verify PASS** → `php tests/settings-theme.php` → all pass.

- [ ] **Step 5: Commit**

```bash
git add inc/admin-post-actions.php tests/settings-theme.php
git commit -m "feat(settings): save_theme handler — clamped ints + model allowlist validation"
```

## Task P3: Register the action + flash messages

**Files:**
- Modify: `inc/admin-post-handler.php` (`sn_admin_post_handlers()`)
- Modify: `inc/admin-flash-messages.php`

- [ ] **Step 1: Register the handler**

In `sn_admin_post_handlers()` (the dispatch map), add:

```php
		'save_theme' => 'sn_handle_save_theme',
```

- [ ] **Step 2: Add the flash strings**

In `inc/admin-flash-messages.php`, add to the message map (match the existing success/notice convention):

```php
		'theme_saved'     => array( 'type' => 'success', 'text' => __( 'Front-end settings saved.', 'signal-and-noise-tools' ) ),
		'theme_unchanged' => array( 'type' => 'info',    'text' => __( 'No front-end settings changed.', 'signal-and-noise-tools' ) ),
```
(Confirm the exact array shape/keys against an existing entry in this file during Task 0.)

- [ ] **Step 3: Commit**

```bash
git add inc/admin-post-handler.php inc/admin-flash-messages.php
git commit -m "feat(settings): wire save_theme into the dispatch map + flash messages"
```

## Task P4: Front-End sub-tab + form

**Files:**
- Create: `inc/admin-forms/front-end.php`
- Modify: `inc/admin-tabs-data.php` (Tools `sub_tabs`)
- Modify: the Tools sub-tab render dispatch (where `cloudflare`/`reading-time` sub-tabs render — confirm file in Task 0; likely `inc/admin-page.php`)

- [ ] **Step 1: Add the sub-tab to the Tools top-tab**

In `inc/admin-tabs-data.php`, in the Tools tab's `sub_tabs` array (next to `reading-time`), add:

```php
				'front-end' => array( 'label' => 'Front-End' ),
```

- [ ] **Step 2: Create the form file**

Create `inc/admin-forms/front-end.php` (model exactly on `inc/admin-forms/identity-and-seo.php`: same `.sn-field`/`.sn-field-label`/`.sn-field-w-*` classes, the `<form method="post">` wrapper, `wp_nonce_field('sn_theme_options_nonce')`, hidden `sn_action`, and the savebar):

```php
<?php
/**
 * Front-End settings form — render knobs the companion theme reads via filters.
 *
 * @package SignalAndNoiseTools
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function sn_admin_render_front_end_form() {
	$related = (int) sn_setting( 'theme.related_count', 3 );
	$precent = (int) sn_setting( 'theme.palette_recent_count', 8 );
	$penab   = (bool) sn_setting( 'theme.palette_enabled', true );
	$jfeed   = (int) sn_setting( 'theme.json_feed_items', 20 );
	$uthr    = (int) sn_setting( 'theme.updated_threshold_days', 14 );
	$wpm     = (int) sn_setting( 'theme.reading_wpm', 225 );
	$model   = (string) sn_setting( 'theme.ai_model', 'claude-sonnet-4-6' );

	echo '<form method="post">';
	wp_nonce_field( 'sn_theme_options_nonce' );
	echo '<input type="hidden" name="sn_action" value="save_theme">';

	echo '<div class="sn-field sn-field-w-xs"><label class="sn-field-label" for="sn_theme_related_count">Related notes shown</label>';
	echo '<input type="number" min="1" max="12" id="sn_theme_related_count" name="theme_related_count" value="' . esc_attr( $related ) . '"></div>';

	echo '<div class="sn-field sn-field-w-xs"><label class="sn-field-label" for="sn_theme_palette_recent_count">Command-palette recent notes</label>';
	echo '<input type="number" min="0" max="20" id="sn_theme_palette_recent_count" name="theme_palette_recent_count" value="' . esc_attr( $precent ) . '"></div>';

	echo '<div class="sn-field"><label class="sn-field-label" for="sn_theme_palette_enabled"><input type="checkbox" id="sn_theme_palette_enabled" name="theme_palette_enabled" value="1"' . checked( $penab, true, false ) . '> Enable reader command palette (⌘K)</label></div>';

	echo '<div class="sn-field sn-field-w-xs"><label class="sn-field-label" for="sn_theme_json_feed_items">JSON feed items</label>';
	echo '<input type="number" min="1" max="50" id="sn_theme_json_feed_items" name="theme_json_feed_items" value="' . esc_attr( $jfeed ) . '"></div>';

	echo '<div class="sn-field sn-field-w-xs"><label class="sn-field-label" for="sn_theme_updated_threshold_days">"Updated" badge after (days)</label>';
	echo '<input type="number" min="1" max="90" id="sn_theme_updated_threshold_days" name="theme_updated_threshold_days" value="' . esc_attr( $uthr ) . '"></div>';

	echo '<div class="sn-field sn-field-w-xs"><label class="sn-field-label" for="sn_theme_reading_wpm">Reading speed (words/min)</label>';
	echo '<input type="number" min="100" max="400" id="sn_theme_reading_wpm" name="theme_reading_wpm" value="' . esc_attr( $wpm ) . '"></div>';

	echo '<div class="sn-field sn-field-w-md"><label class="sn-field-label" for="sn_theme_ai_model">AI model</label><select id="sn_theme_ai_model" name="theme_ai_model">';
	foreach ( sn_theme_ai_models() as $id => $label ) {
		echo '<option value="' . esc_attr( $id ) . '"' . selected( $model, $id, false ) . '>' . esc_html( $label ) . '</option>';
	}
	echo '</select></div>';

	submit_button( __( 'Save front-end settings', 'signal-and-noise-tools' ) );
	echo '</form>';
}
```

- [ ] **Step 3: Wire the render dispatch**

In the Tools sub-tab dispatch (the file confirmed in Task 0), add a branch for the `front-end` sub-tab that `require_once`s `inc/admin-forms/front-end.php` and calls `sn_admin_render_front_end_form()` — matching exactly how the `reading-time`/`cloudflare` sub-tabs are routed.

- [ ] **Step 4: Manual smoke (no unit test for markup)**

Load wp-admin → S&N Tools → Tools → Front-End. Confirm: 7 fields render with current values, native WP styling, Save persists + shows the success flash, reload reflects saved values. (Per `feedback_desktop_mode_blocks_browser_automation`, this is manual UAT.)

- [ ] **Step 5: Commit**

```bash
git add inc/admin-forms/front-end.php inc/admin-tabs-data.php inc/admin-page.php
git commit -m "feat(settings): Tools > Front-End sub-tab + bundled form"
```

## Task P5: Filter callbacks (the cross-package contract)

**Files:**
- Create: `inc/theme-filters.php`
- Modify: plugin bootstrap (`signal-and-noise-tools.php` or the inc loader) to `require_once` it
- Test: `tests/settings-theme.php`

- [ ] **Step 1: Write the failing test**

Append to `tests/settings-theme.php`:

```php
require __DIR__ . '/../inc/theme-filters.php';
// callbacks return the configured value, clamped, falling back to the supplied default.
$GLOBALS['__opt']['sn_settings']['theme']['related_count'] = 7;
ok( (int) sn_tf_related_count( 3 ) === 7, 'filter: related_count returns configured 7' );
unset( $GLOBALS['__opt']['sn_settings']['theme']['related_count'] );
ok( (int) sn_tf_related_count( 3 ) === 3, 'filter: related_count falls back to supplied default' );
ok( sn_tf_palette_enabled( true ) === true, 'filter: palette_enabled default true' );
$GLOBALS['__opt']['sn_settings']['theme']['ai_model'] = 'claude-opus-4-8';
ok( sn_tf_ai_model( 'claude-sonnet-4-6' ) === 'claude-opus-4-8', 'filter: ai_model returns configured id' );
```

- [ ] **Step 2: Run — verify FAIL** → `php tests/settings-theme.php` → FAIL "undefined function sn_tf_related_count".

- [ ] **Step 3: Implement**

Create `inc/theme-filters.php`:

```php
<?php
/**
 * Supplies configured sn_settings['theme'] values to the theme's (and plugin's)
 * filters. Named functions (not closures) so the standalone tests can call them
 * directly. Each clamps/casts on the way out (defense-in-depth vs a hand-edited
 * option) and falls back to the theme-supplied default ($d) when unset, keeping
 * both packages' defaults reconciled.
 *
 * @package SignalAndNoiseTools
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function sn_tf_related_count( $d )        { return max( 1, min( 12, (int) sn_setting( 'theme.related_count', $d ) ) ); }
function sn_tf_palette_recent_count( $d ) { return max( 0, min( 20, (int) sn_setting( 'theme.palette_recent_count', $d ) ) ); }
function sn_tf_palette_enabled( $d )      { return (bool) sn_setting( 'theme.palette_enabled', $d ); }
function sn_tf_json_feed_items( $d )      { return max( 1, min( 50, (int) sn_setting( 'theme.json_feed_items', $d ) ) ); }
function sn_tf_updated_threshold( $d )    { return max( 1, min( 90, (int) sn_setting( 'theme.updated_threshold_days', $d ) ) ); }
function sn_tf_reading_wpm( $d )          { return max( 100, min( 400, (int) sn_setting( 'theme.reading_wpm', $d ) ) ); }
function sn_tf_ai_model( $d ) {
	$id = (string) sn_setting( 'theme.ai_model', $d );
	return in_array( $id, array_keys( sn_theme_ai_models() ), true ) ? $id : (string) $d;
}

if ( ! defined( 'SN_THEME_FILTERS_TEST' ) || ! SN_THEME_FILTERS_TEST ) {
	add_filter( 'sn_related_count',              'sn_tf_related_count' );
	add_filter( 'sn_palette_recent_count',       'sn_tf_palette_recent_count' );
	add_filter( 'sn_palette_enabled',            'sn_tf_palette_enabled' );
	add_filter( 'sn_json_feed_items',            'sn_tf_json_feed_items' );
	add_filter( 'sn_updated_date_threshold_days', 'sn_tf_updated_threshold' );
	add_filter( 'sn_reading_time_wpm',           'sn_tf_reading_wpm' );
	add_filter( 'snt_ai_model_preference',       'sn_tf_ai_model' );
}
```

> Define `SN_THEME_FILTERS_TEST` true at the top of `tests/settings-theme.php` (with the other test sentinels) so the `require` doesn't call `add_filter` (which isn't stubbed for hook registration). `sn_theme_ai_models()` is already required via `admin-post-actions.php` in the test.

- [ ] **Step 4: Run — verify PASS** → `php tests/settings-theme.php` → all pass.

- [ ] **Step 5: Load the file at bootstrap**

In the plugin's inc-loader (where the other `inc/*.php` are `require_once`d), add `inc/theme-filters.php`. Confirm `sn_theme_ai_models()` (in `admin-post-actions.php`) loads on the front end too (the `snt_ai_model_preference` + `sn_tf_ai_model` path runs during AI calls, which are admin/cron — but `add_filter` registration is global). If `admin-post-actions.php` is admin-only, move `sn_theme_ai_models()` to a front-end-loaded file (e.g. into `theme-filters.php`) so `sn_tf_ai_model()` can call it everywhere.

- [ ] **Step 6: Commit**

```bash
git add inc/theme-filters.php signal-and-noise-tools.php tests/settings-theme.php
git commit -m "feat(settings): theme-filters — supply configured values to 7 theme/plugin filters"
```

## Task P6: Plugin full-suite + lint + release v4.12.0

- [ ] **Step 1: Full suite + lint**

```bash
cd "$PLUGINWT"
for f in tests/*.php; do php "$f" 2>&1 | grep -iE "fail|fatal" && echo "  ^in $f"; done; echo "swept"
composer run lint 2>&1 | tail -5   # or vendor/bin/phpcs --standard=phpcs.xml.dist inc/theme-filters.php inc/admin-post-actions.php inc/settings.php inc/admin-forms/front-end.php
```
Expected: zero failures; phpcs clean (falsify first).

- [ ] **Step 2: Version + CHANGELOG**

`signal-and-noise-tools.php`: `Version: 4.11.0` → `4.12.0`. CHANGELOG (Mimestream `### New`): Front-End settings tab — configurable related-notes count, command-palette recent count + enable, JSON-feed items, updated-badge threshold, reading-time WPM, AI model; supplies them to the theme via filters.

- [ ] **Step 3: Commit + push + tag**

```bash
git add signal-and-noise-tools.php CHANGELOG.md
git commit -m "v4.12.0: Front-End settings tab (plugin-driven theme render knobs + AI model select)"
git push origin HEAD:main
git tag -a v4.12.0 -m "v4.12.0 — Front-End settings tab"
git push origin v4.12.0
```

- [ ] **Step 4: Clean up the plugin worktree**

```bash
cd /Users/juanlentino/Projects/signal-and-noise-tools
git worktree remove .claude/worktrees/settings-batch-a
```

---

## Release / install (both repos)

- Install theme v9.12.0 and plugin v4.12.0 via wp-admin → Updates (or the emergency `gh workflow run deploy.yml`). Order is irrelevant — defaults match, so each works standalone.
- Smoke: Tools → Front-End renders + saves; change related count → front-end reflects it; toggle palette off → ⌘K trigger disappears + no palette JS loads.

## Self-review notes (coverage)

- 7 settings: T1 (related) · T2 (palette recent) · T3 (palette enable) · T4 (json feed) · P1 defaults all 7 · P2 handler all 7 · P5 filters all 7. ✓
- Bug fix: T1. ✓ Security (clamp/allowlist/escape): P2 + P4. ✓ Standalone-safety: P5 (named filters, default fallback). ✓ Kill-switch: T3. ✓
- Pre-impl unknowns (plugin line anchors, AI ids, flash shape, sub-tab dispatch file, feed-json extraction): gated in Task 0 + flagged inline.
