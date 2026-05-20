# Cron Dashboard Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship a Cron Dashboard feature in the Signal & Noise Tools plugin (v3.0.0) — surfaces all WP-Cron events with next-run, recurrence, last-fired, args, and a guarded "Run now" button. Read-only views exposed via the WP 7.0 Abilities API + desktop-mode ⌘K Copilot; run-now stays wp-admin REST only.

**Architecture:** 4-surface dispatch (wp-admin form / legacy REST / Abilities API / desktop-mode ⌘K) converging on a single `snt_cron_*_impl()` pure function set. Universal last-fired tracking via DOING_CRON-gated PHP_INT_MAX listeners with a single named callback. Synchronous run-now via `do_action_ref_array()` with DOING_CRON spoof, Throwable catch, 30s time limit, and JS confirm() prompt. ~455 LOC across 3 new files + 7 modified files in the plugin repo.

**Tech Stack:** WordPress 7.0 (Abilities API, AI Client), WordPress/desktop-mode plugin (⌘K commands, `aiCallable: true`), PHP 8.0+, vanilla JS (`wp.apiFetch`, no build step), bare-PHP standalone fixture tests (no PHPUnit — matches `tests/bot-detection.php` precedent).

**Source spec:** [`docs/superpowers/specs/2026-05-20-cron-dashboard-design.md`](../specs/2026-05-20-cron-dashboard-design.md) (commit `df6f726`)

**Working directory for ALL implementation tasks:** `/Users/juanlentino/projects/signal-and-noise-tools` (plugin repo). The plan file lives in the theme repo's `docs/superpowers/plans/` per the project's convention of co-locating planning artifacts with the parent theme.

---

## File Structure (locked decisions)

### New files (3 + 1 test)

| Path | Purpose | LOC est. |
|---|---|---|
| `inc/cron-dashboard.php` | Pure impl functions + last-fired tracker + named callback + module bootstrap | ~110 |
| `inc/cron-dashboard-admin.php` | Cron tab renderer hooked to `sn_admin_cron_tab` action | ~110 |
| `assets/cron-dashboard.js` | Run-now click handler (with `confirm()`), live filter input, inline row updates | ~75 |
| `tests/cron-dashboard.php` | Standalone fixture tests (WP-stubbed) — runnable as `php tests/cron-dashboard.php` | ~180 |

### Modified files (7)

| Path | Change |
|---|---|
| `signal-and-noise-tools.php` | `Version: 3.0.0`, `SNT_VERSION = '3.0.0'`, `require_once` for both new inc/ files |
| `inc/admin-page.php` | Append 9th entry to `sn_admin_pages()`: `sn-cron` slug / `cron` tab / "Cron" label, plus dispatch in tab switch |
| `inc/abilities-registration.php` | Append `signal-noise/list-cron-events` + `signal-noise/get-cron-event` abilities under `diagnostics` category |
| `inc/rest-api.php` | Append POST `/cron/run` route + `snt_rest_cron_run` callback |
| `inc/desktop-mode-integration.php` | Append 2 entries to the `$commands` array + register `pages.cron` in `snDesktopData` + register `cronSummary` |
| `assets/desktop-mode.js` | Append `sn-cmd-cron-health` + `sn-cmd-cron-list` registrations with `aiCallable: true` |
| `CHANGELOG.md` | v3.0.0 entry at top with minor-cap-rollover note |

### Naming conventions (locked)

- Impl functions: `snt_cron_<verb>_impl()` (matches `snt_ai_*_impl` Phase 14 pattern)
- Named tracker callback: `snt_cron_track_last_fired_cb` (top-level, not closure, so add_action dedupe works)
- REST callback: `snt_rest_cron_run` (matches existing `sn_rest_*` namespace for REST, but uses `snt_*` prefix for Phase 14+ code)
- Option key format: `snt_cron_last_fired_<md5($hook)>` (32-char hex; safe for any hook name)
- Ability namespace: `signal-noise/list-cron-events`, `signal-noise/get-cron-event`
- Command slugs: `sn-cmd-cron-health`, `sn-cmd-cron-list`
- The 3 SN-owned hooks (from existing constants): `sn_plausible_refresh_dashboard`, `sn_plausible_refresh_realtime`, `sn_rss_tracker_daily_prune`

---

## Process discipline (from memory, applies to every task)

Before writing any code:
1. **Read the relevant section of the spec** at `docs/superpowers/specs/2026-05-20-cron-dashboard-design.md` — don't reason from memory of the brainstorming conversation.
2. **Read the actual existing pattern in the file you're modifying** — don't guess. The plugin's 8-tab structure, REST handler signature, ability schema shape, and JS toast helper conventions are all locked patterns.
3. **No `// TODO: handle edge case` comments.** Every error path explicitly handled per spec § 8.

After writing code, before committing:
1. Run the standalone test if applicable: `php tests/cron-dashboard.php`
2. Lint syntax: `php -l <file>` for each PHP file touched
3. For JS: visually confirm no obvious syntax errors (no linter in this repo)

---

## Task 0: Setup the implementation context

**Files:** None (workspace prep)

- [ ] **Step 1: Switch to the plugin repo and confirm clean state**

```bash
cd /Users/juanlentino/projects/signal-and-noise-tools
git status
git log --oneline -3
```

Expected: clean tree, last commit is the v2.5.5 release commit (`3ce60a6` or thereabouts).

- [ ] **Step 2: Confirm we're on main and up-to-date**

```bash
cd /Users/juanlentino/projects/signal-and-noise-tools
git branch --show-current
git pull --ff-only origin main
```

Expected: branch is `main`, `pull` reports "Already up to date" or fast-forwards cleanly.

- [ ] **Step 3: Create implementation branch (optional but recommended)**

```bash
cd /Users/juanlentino/projects/signal-and-noise-tools
git checkout -b feat/cron-dashboard-v3.0.0
```

If user prefers to work directly on main (per their fast-moving style), skip this step and commit directly to main. Either path is acceptable.

---

## Task 1: Create the impl module skeleton

**Files:**
- Create: `/Users/juanlentino/projects/signal-and-noise-tools/inc/cron-dashboard.php`

- [ ] **Step 1: Create the file with header + the 3 SN-owned hook list + namespace check**

```php
<?php
/**
 * Signal & Noise Tools — Cron Dashboard module.
 *
 * Surfaces WP-Cron health in the wp-admin under the Cron tab. For every
 * scheduled cron event, shows next-run, recurrence, last-fired, args,
 * and provides a Run-now button gated by manage_options + confirm().
 *
 * 4-surface dispatch (per the plugin's established Phase 14 pattern):
 *   - wp-admin form (Cron tab → Run-now button)
 *   - REST POST signal-noise/v1/cron/run
 *   - Abilities API: signal-noise/list-cron-events + get-cron-event
 *   - desktop-mode ⌘K: sn-cmd-cron-health + sn-cmd-cron-list (read-only)
 *
 * All 4 surfaces converge on the snt_cron_*_impl() pure functions below.
 * Run-now is NOT exposed to AI per spec § 6 / Q4 decision.
 *
 * Last-fired tracking: WordPress core does not track last-fired natively.
 * We register snt_cron_track_last_fired_cb() at PHP_INT_MAX for every
 * unique cron hook during DOING_CRON requests, gated at wp_loaded so
 * non-cron requests pay zero cost.
 *
 * @package SignalNoiseTools
 * @since 3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hook names owned by this plugin. Used by snt_cron_is_sn_owned() to
 * pin SN-owned events at the top of the dashboard table.
 *
 * Kept as a string array (not constants) because the constants live in
 * the modules that schedule them and we want to avoid a require_once
 * cycle. If the list grows, consider exposing via a filter.
 */
function snt_cron_sn_owned_hooks() {
	return array(
		'sn_plausible_refresh_dashboard',
		'sn_plausible_refresh_realtime',
		'sn_rss_tracker_daily_prune',
	);
}

function snt_cron_is_sn_owned( $hook ) {
	return in_array( $hook, snt_cron_sn_owned_hooks(), true );
}
```

- [ ] **Step 2: Run PHP syntax lint**

```bash
cd /Users/juanlentino/projects/signal-and-noise-tools
php -l inc/cron-dashboard.php
```

Expected output: `No syntax errors detected in inc/cron-dashboard.php`

- [ ] **Step 3: Commit**

Use the commit-commands:commit skill if available, or the following inline command:

```bash
cd /Users/juanlentino/projects/signal-and-noise-tools
git add inc/cron-dashboard.php
git commit -m "feat(cron-dashboard): create module skeleton + SN-owned hook helpers"
```

---

## Task 2: Add last-fired storage helpers

**Files:**
- Modify: `/Users/juanlentino/projects/signal-and-noise-tools/inc/cron-dashboard.php` — append to end of file

- [ ] **Step 1: Append the storage helpers**

```php

/**
 * Last-fired storage: write helper.
 *
 * Key format: snt_cron_last_fired_<md5(hook)>. md5 avoids the
 * varchar(191) wp_options key column limit for long hook names like
 * 'action_scheduler_run_queue' and handles hook names with slashes.
 *
 * Stored as integer unix timestamp. autoload=false so it doesn't
 * bloat the autoloaded options cache.
 */
function snt_cron_record_last_fired( $hook ) {
	if ( ! is_string( $hook ) || '' === $hook ) {
		return;
	}
	update_option( 'snt_cron_last_fired_' . md5( $hook ), time(), false );
}

/**
 * Last-fired storage: read helper. Returns int|null.
 */
function snt_cron_last_fired_for( $hook ) {
	if ( ! is_string( $hook ) || '' === $hook ) {
		return null;
	}
	$value = get_option( 'snt_cron_last_fired_' . md5( $hook ), null );
	if ( null === $value || '' === $value ) {
		return null;
	}
	return (int) $value;
}

/**
 * Named callback referenced by both wp_loaded path (registered for each
 * cron hook during DOING_CRON requests) and the synchronous run-now
 * path (registered ad-hoc in snt_cron_run_event_impl). Uses
 * current_action() so one function works for every hook.
 */
function snt_cron_track_last_fired_cb() {
	snt_cron_record_last_fired( current_action() );
}
```

- [ ] **Step 2: Syntax lint**

```bash
cd /Users/juanlentino/projects/signal-and-noise-tools
php -l inc/cron-dashboard.php
```

Expected: `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
cd /Users/juanlentino/projects/signal-and-noise-tools
git add inc/cron-dashboard.php
git commit -m "feat(cron-dashboard): last-fired read/write helpers + named callback"
```

---

## Task 3: Wire the DOING_CRON-gated tracker registration

**Files:**
- Modify: `/Users/juanlentino/projects/signal-and-noise-tools/inc/cron-dashboard.php` — append to end

- [ ] **Step 1: Append the wp_loaded gate + per-hook listener registration**

```php

/**
 * During DOING_CRON requests, register snt_cron_track_last_fired_cb at
 * PHP_INT_MAX for every unique cron hook. This way it fires AFTER the
 * real handler completes, capturing last-fired exactly once per event
 * firing.
 *
 * Gated at wp_loaded priority 1 so non-cron requests pay only one
 * defined() check. Pre-walks _get_cron_array() to register named
 * (not closure) listeners so WordPress's internal callback dedupe
 * works if multiple plugins do this trick.
 *
 * _get_cron_array() is underscore-prefixed (technically private) but
 * stable since WP 2.1 (2007). It's the only way to enumerate all
 * scheduled events; documented in spec § 7.4 as accepted API risk.
 */
add_action( 'wp_loaded', function() {
	if ( ! ( defined( 'DOING_CRON' ) && DOING_CRON ) ) {
		return;
	}
	if ( ! function_exists( '_get_cron_array' ) ) {
		return;
	}
	$crons = _get_cron_array();
	if ( empty( $crons ) ) {
		return;
	}
	$seen = array();
	foreach ( $crons as $events ) {
		foreach ( $events as $hook => $_ ) {
			if ( isset( $seen[ $hook ] ) ) {
				continue;
			}
			$seen[ $hook ] = true;
			add_action( $hook, 'snt_cron_track_last_fired_cb', PHP_INT_MAX );
		}
	}
}, 1 );
```

- [ ] **Step 2: Syntax lint**

```bash
cd /Users/juanlentino/projects/signal-and-noise-tools
php -l inc/cron-dashboard.php
```

Expected: `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
cd /Users/juanlentino/projects/signal-and-noise-tools
git add inc/cron-dashboard.php
git commit -m "feat(cron-dashboard): DOING_CRON-gated tracker registration at wp_loaded"
```

---

## Task 4: Implement `snt_cron_get_events_impl` and `snt_cron_get_event_impl`

**Files:**
- Modify: `/Users/juanlentino/projects/signal-and-noise-tools/inc/cron-dashboard.php` — append

- [ ] **Step 1: Append the read-events impl functions**

```php

/**
 * Walks _get_cron_array() and returns a flat list of event rows.
 *
 * Each row has 9 keys per spec § 4.1.
 *
 * args_signature is the md5 key the cron array uses to disambiguate
 * multiple scheduled instances of the same hook with different args
 * (e.g., wp_version_check can be scheduled twice with different args).
 *
 * Sort: SN-owned hooks first, then by next_run_ts ascending.
 *
 * @param bool $sn_only If true, filter to the 3 SN-owned hooks only.
 * @return array Flat array of event rows (empty array if cron empty).
 */
function snt_cron_get_events_impl( $sn_only = false ) {
	if ( ! function_exists( '_get_cron_array' ) ) {
		return array();
	}
	$crons = _get_cron_array();
	if ( empty( $crons ) ) {
		return array();
	}

	$rows = array();
	foreach ( $crons as $ts => $hooks ) {
		foreach ( $hooks as $hook => $events ) {
			$is_sn = snt_cron_is_sn_owned( $hook );
			if ( $sn_only && ! $is_sn ) {
				continue;
			}
			foreach ( $events as $signature => $data ) {
				$rows[] = array(
					'hook'           => $hook,
					'args_signature' => (string) $signature,
					'next_run_ts'    => (int) $ts,
					'schedule'       => isset( $data['schedule'] ) ? $data['schedule'] : false,
					'interval_s'     => isset( $data['interval'] ) ? (int) $data['interval'] : null,
					'args'           => isset( $data['args'] ) ? (array) $data['args'] : array(),
					'last_fired_ts'  => snt_cron_last_fired_for( $hook ),
					'has_handler'    => has_action( $hook ) !== false,
					'is_sn_owned'    => $is_sn,
				);
			}
		}
	}

	// Sort: SN-owned first, then by next_run_ts ascending.
	usort( $rows, function( $a, $b ) {
		if ( $a['is_sn_owned'] !== $b['is_sn_owned'] ) {
			return $a['is_sn_owned'] ? -1 : 1;
		}
		return $a['next_run_ts'] - $b['next_run_ts'];
	} );

	return $rows;
}

/**
 * Single-event variant. Returns the row matching hook+signature, or
 * null if no match. Useful for the get-cron-event ability.
 */
function snt_cron_get_event_impl( $hook, $args_signature ) {
	foreach ( snt_cron_get_events_impl() as $row ) {
		if ( $row['hook'] === $hook && $row['args_signature'] === $args_signature ) {
			return $row;
		}
	}
	return null;
}
```

- [ ] **Step 2: Syntax lint**

```bash
cd /Users/juanlentino/projects/signal-and-noise-tools
php -l inc/cron-dashboard.php
```

Expected: `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
cd /Users/juanlentino/projects/signal-and-noise-tools
git add inc/cron-dashboard.php
git commit -m "feat(cron-dashboard): snt_cron_get_events_impl + snt_cron_get_event_impl"
```

---

## Task 5: Implement `snt_cron_run_event_impl` (synchronous run-now with safety guards)

**Files:**
- Modify: `/Users/juanlentino/projects/signal-and-noise-tools/inc/cron-dashboard.php` — append

- [ ] **Step 1: Append the run-now impl function**

```php

/**
 * Synchronously dispatch a cron event. The 4 safety guards:
 *
 *   1. manage_options gate (defense in depth; REST also gates)
 *   2. has_action() pre-flight — orphan hooks return WP_Error rather
 *      than dispatching to nothing
 *   3. DOING_CRON spoof — handlers that guard on wp_doing_cron() (e.g.,
 *      Action Scheduler) will actually execute. Standard pattern from
 *      WP-Crontrol since 2012.
 *   4. Throwable catch — PHP 7+ throws fatals as Error subclasses, so
 *      Throwable covers Exception, TypeError, ParseError, OutOfMemory*,
 *      ArgumentCountError, etc. Only truly unrecoverable cases (segfault,
 *      hard OOM) bypass it; those return 502 to the browser.
 *
 * Time limit: @set_time_limit(30) is best-effort (some hosts disable).
 * If exceeded, PHP kills the process → browser sees 502/timeout. The
 * Cron tab is the recovery surface — refresh, check last-fired column.
 *
 * Note on the ad-hoc tracker registration here: wp_loaded already fired
 * by the time this REST request reaches us. The DOING_CRON gate at
 * wp_loaded didn't register listeners. We register one manually for
 * just this hook so the synchronous dispatch updates last-fired too.
 */
function snt_cron_run_event_impl( $hook, $args = array() ) {
	if ( ! current_user_can( 'manage_options' ) ) {
		return new WP_Error(
			'snt_cron_forbidden',
			'Insufficient permissions.',
			array( 'status' => 403 )
		);
	}
	if ( ! is_string( $hook ) || '' === $hook ) {
		return new WP_Error(
			'snt_cron_invalid_hook',
			'Hook name must be a non-empty string.',
			array( 'status' => 400 )
		);
	}
	if ( false === has_action( $hook ) ) {
		return new WP_Error(
			'snt_cron_no_handler',
			sprintf( 'No handler registered for "%s" — this is an orphan event.', $hook ),
			array( 'status' => 400 )
		);
	}

	// DOING_CRON spoof — makes wp_doing_cron() return true for guarded handlers.
	if ( ! defined( 'DOING_CRON' ) ) {
		define( 'DOING_CRON', true );
	}

	// Best-effort time limit. Some shared hosts disable set_time_limit.
	@set_time_limit( 30 );

	// Register the last-fired tracker ad-hoc (wp_loaded already fired).
	add_action( $hook, 'snt_cron_track_last_fired_cb', PHP_INT_MAX );

	$start = microtime( true );
	$success = true;
	$error = null;

	try {
		do_action_ref_array( $hook, is_array( $args ) ? $args : array() );
	} catch ( Throwable $e ) {
		$success = false;
		$error = $e->getMessage();
	}

	$elapsed_ms = ( microtime( true ) - $start ) * 1000;

	return array(
		'success'       => $success,
		'elapsed_ms'    => $elapsed_ms,
		'error'         => $error,
		'last_fired_ts' => snt_cron_last_fired_for( $hook ),
		'hook'          => $hook,
	);
}
```

- [ ] **Step 2: Syntax lint**

```bash
cd /Users/juanlentino/projects/signal-and-noise-tools
php -l inc/cron-dashboard.php
```

Expected: `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
cd /Users/juanlentino/projects/signal-and-noise-tools
git add inc/cron-dashboard.php
git commit -m "feat(cron-dashboard): snt_cron_run_event_impl with 4 safety guards"
```

---

## Task 6: Bootstrap the module in main file

**Files:**
- Modify: `/Users/juanlentino/projects/signal-and-noise-tools/signal-and-noise-tools.php`

- [ ] **Step 1: Find the existing require_once chain**

```bash
cd /Users/juanlentino/projects/signal-and-noise-tools
grep -n "require_once SNT_PATH" signal-and-noise-tools.php
```

Expected: list of `require_once SNT_PATH . 'inc/*.php';` lines. Note the line range so the cron-dashboard require_once goes in alphabetic order with siblings.

- [ ] **Step 2: Add the require_once line**

Open `signal-and-noise-tools.php`. Find the require_once block. Insert this line in alphabetic order (after `command-palette.php`, before `desktop-mode-integration.php`):

```php
require_once SNT_PATH . 'inc/cron-dashboard.php';
```

(The second new file `inc/cron-dashboard-admin.php` will be added in Task 10. For now just the foundation.)

- [ ] **Step 3: Syntax lint**

```bash
cd /Users/juanlentino/projects/signal-and-noise-tools
php -l signal-and-noise-tools.php
```

Expected: `No syntax errors detected`

- [ ] **Step 4: Commit**

```bash
cd /Users/juanlentino/projects/signal-and-noise-tools
git add signal-and-noise-tools.php
git commit -m "feat(cron-dashboard): bootstrap inc/cron-dashboard.php in main file"
```

---

## Task 7: Create the standalone test harness

**Files:**
- Create: `/Users/juanlentino/projects/signal-and-noise-tools/tests/cron-dashboard.php`

- [ ] **Step 1: Create the test file with WP stubs + initial assertion harness**

```php
<?php
/**
 * Standalone fixture tests for inc/cron-dashboard.php.
 *
 * Matches the bot-detection.php precedent: bare-PHP, no PHPUnit, no
 * composer. Runnable as:
 *
 *     php tests/cron-dashboard.php
 *
 * Exits 0 on all-pass, 1 on any failure.
 *
 * Stubs only the WP functions the impl module actually calls. Tests
 * pure logic — REST + abilities + admin render layers get exercised
 * by the manual smoke test on live (per spec § 10.2).
 *
 * @since plugin v3.0.0
 */

// ─── WP stubs ─────────────────────────────────────────────────────────
define( 'ABSPATH', '/' );

// In-memory option store the stubs read/write.
$GLOBALS['__test_options'] = array();
$GLOBALS['__test_actions'] = array(); // hook => bool (has_action)
$GLOBALS['__test_cron_array'] = array();
$GLOBALS['__test_current_user_can'] = true;
$GLOBALS['__test_current_action'] = '';
$GLOBALS['__test_action_callbacks'] = array();

function add_action( $hook, $cb = null, $priority = 10, $accepted_args = 1 ) {
	// No-op for module load; specific tests can override via globals.
}

function _get_cron_array() {
	return $GLOBALS['__test_cron_array'];
}

function has_action( $hook ) {
	return isset( $GLOBALS['__test_actions'][ $hook ] ) && $GLOBALS['__test_actions'][ $hook ];
}

function get_option( $key, $default = false ) {
	return isset( $GLOBALS['__test_options'][ $key ] ) ? $GLOBALS['__test_options'][ $key ] : $default;
}

function update_option( $key, $value, $autoload = null ) {
	$GLOBALS['__test_options'][ $key ] = $value;
	return true;
}

function current_user_can( $cap ) {
	return $GLOBALS['__test_current_user_can'];
}

function current_action() {
	return $GLOBALS['__test_current_action'];
}

function do_action_ref_array( $hook, $args ) {
	// Test stub: invoke a registered callable if present.
	if ( isset( $GLOBALS['__test_action_callbacks'][ $hook ] ) ) {
		call_user_func_array( $GLOBALS['__test_action_callbacks'][ $hook ], $args );
	}
}

class WP_Error {
	public $code;
	public $message;
	public $data;
	public function __construct( $code = '', $message = '', $data = array() ) {
		$this->code    = $code;
		$this->message = $message;
		$this->data    = $data;
	}
	public function get_error_message() { return $this->message; }
}

require_once __DIR__ . '/../inc/cron-dashboard.php';

// ─── Test harness ─────────────────────────────────────────────────────
$pass = 0;
$fail = 0;

function assert_eq( $expected, $actual, $msg ) {
	global $pass, $fail;
	if ( $expected === $actual ) {
		$pass++;
		echo "  PASS: $msg\n";
	} else {
		$fail++;
		echo "  FAIL: $msg\n";
		echo "    Expected: " . var_export( $expected, true ) . "\n";
		echo "    Actual:   " . var_export( $actual, true ) . "\n";
	}
}

function assert_true( $cond, $msg ) {
	global $pass, $fail;
	if ( $cond ) {
		$pass++;
		echo "  PASS: $msg\n";
	} else {
		$fail++;
		echo "  FAIL: $msg\n";
	}
}

// Tests will be appended in Task 8.
echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
```

- [ ] **Step 2: Run the empty harness to verify stubs load**

```bash
cd /Users/juanlentino/projects/signal-and-noise-tools
php tests/cron-dashboard.php
```

Expected output:
```
Result: 0 passed, 0 failed.
```

Exit code: 0

- [ ] **Step 3: Commit**

```bash
cd /Users/juanlentino/projects/signal-and-noise-tools
git add tests/cron-dashboard.php
git commit -m "test(cron-dashboard): standalone fixture harness with WP stubs"
```

---

## Task 8: Add test assertions for impl functions

**Files:**
- Modify: `/Users/juanlentino/projects/signal-and-noise-tools/tests/cron-dashboard.php`

- [ ] **Step 1: Replace the line `// Tests will be appended in Task 8.` and the placeholder echo+exit lines that follow it with the full assertion block**

In `tests/cron-dashboard.php`, find these 3 lines near the end of the file:

```php
// Tests will be appended in Task 8.
echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
```

Replace them with:

```php
// ─── Test 1: snt_cron_is_sn_owned ───────────────────────────────────
echo "\nTest 1: snt_cron_is_sn_owned\n";
assert_true( snt_cron_is_sn_owned( 'sn_plausible_refresh_dashboard' ), 'SN-owned dashboard hook recognized' );
assert_true( snt_cron_is_sn_owned( 'sn_rss_tracker_daily_prune' ), 'SN-owned RSS hook recognized' );
assert_eq( false, snt_cron_is_sn_owned( 'wp_version_check' ), 'WP core hook is not SN-owned' );
assert_eq( false, snt_cron_is_sn_owned( '' ), 'Empty string is not SN-owned' );

// ─── Test 2: last-fired round trip ───────────────────────────────────
echo "\nTest 2: last-fired round trip\n";
$GLOBALS['__test_options'] = array(); // reset
$now = time();
snt_cron_record_last_fired( 'my_test_hook' );
$got = snt_cron_last_fired_for( 'my_test_hook' );
assert_true( is_int( $got ) && $got >= $now, 'record + read round-trips an int >= now' );
assert_eq( null, snt_cron_last_fired_for( 'never_fired_hook' ), 'unknown hook returns null' );
assert_eq( null, snt_cron_last_fired_for( '' ), 'empty hook name returns null' );

// ─── Test 3: snt_cron_get_events_impl flat structure ─────────────────
echo "\nTest 3: snt_cron_get_events_impl flat structure\n";
$GLOBALS['__test_cron_array'] = array(
	1747936800 => array(
		'wp_version_check' => array(
			'sig_wp_version_check_a' => array( 'schedule' => 'twicedaily', 'args' => array(), 'interval' => 43200 ),
		),
	),
	1747940000 => array(
		'sn_rss_tracker_daily_prune' => array(
			'sig_sn_rss_a' => array( 'schedule' => 'daily', 'args' => array(), 'interval' => 86400 ),
		),
	),
);
$GLOBALS['__test_actions'] = array( 'wp_version_check' => true, 'sn_rss_tracker_daily_prune' => true );

$rows = snt_cron_get_events_impl();
assert_eq( 2, count( $rows ), 'returns 2 rows from 2-event fixture' );

// ─── Test 4: SN-owned events sort first ──────────────────────────────
echo "\nTest 4: SN-owned events sort first\n";
assert_eq( 'sn_rss_tracker_daily_prune', $rows[0]['hook'], 'SN-owned row sorts before wp_version_check despite later next_run_ts' );
assert_eq( true, $rows[0]['is_sn_owned'], 'first row is_sn_owned=true' );
assert_eq( false, $rows[1]['is_sn_owned'], 'second row is_sn_owned=false' );

// ─── Test 5: row schema ──────────────────────────────────────────────
echo "\nTest 5: row schema\n";
$row = $rows[0];
$required_keys = array( 'hook', 'args_signature', 'next_run_ts', 'schedule', 'interval_s', 'args', 'last_fired_ts', 'has_handler', 'is_sn_owned' );
foreach ( $required_keys as $k ) {
	assert_true( array_key_exists( $k, $row ), "row has '$k' key" );
}
assert_eq( true, $row['has_handler'], 'has_handler reflects has_action()' );

// ─── Test 6: sn_only filter ──────────────────────────────────────────
echo "\nTest 6: sn_only filter\n";
$sn_rows = snt_cron_get_events_impl( true );
assert_eq( 1, count( $sn_rows ), 'sn_only=true filters to 1 SN-owned row' );
assert_eq( 'sn_rss_tracker_daily_prune', $sn_rows[0]['hook'], 'filtered row is the SN hook' );

// ─── Test 7: empty cron array ────────────────────────────────────────
echo "\nTest 7: empty cron array\n";
$GLOBALS['__test_cron_array'] = array();
$empty = snt_cron_get_events_impl();
assert_eq( array(), $empty, 'empty cron returns empty array' );

// ─── Test 8: snt_cron_run_event_impl permission gate ─────────────────
echo "\nTest 8: snt_cron_run_event_impl permission gate\n";
$GLOBALS['__test_current_user_can'] = false;
$res = snt_cron_run_event_impl( 'any_hook' );
assert_true( $res instanceof WP_Error, 'non-admin gets WP_Error' );
assert_eq( 'snt_cron_forbidden', $res->code, 'error code is snt_cron_forbidden' );
$GLOBALS['__test_current_user_can'] = true;

// ─── Test 9: snt_cron_run_event_impl orphan-hook rejection ───────────
echo "\nTest 9: snt_cron_run_event_impl orphan-hook rejection\n";
$GLOBALS['__test_actions'] = array(); // no actions registered
$res = snt_cron_run_event_impl( 'no_such_handler_hook' );
assert_true( $res instanceof WP_Error, 'orphan hook gets WP_Error' );
assert_eq( 'snt_cron_no_handler', $res->code, 'error code is snt_cron_no_handler' );

// ─── Test 10: snt_cron_run_event_impl successful dispatch ────────────
echo "\nTest 10: snt_cron_run_event_impl successful dispatch\n";
$GLOBALS['__test_actions'] = array( 'sn_rss_tracker_daily_prune' => true );
$fired = false;
$GLOBALS['__test_action_callbacks']['sn_rss_tracker_daily_prune'] = function() use ( &$fired ) { $fired = true; };
$res = snt_cron_run_event_impl( 'sn_rss_tracker_daily_prune' );
assert_true( $fired, 'handler was invoked' );
assert_eq( true, $res['success'], 'success=true' );
assert_eq( 'sn_rss_tracker_daily_prune', $res['hook'], 'hook echoed back' );
assert_true( is_float( $res['elapsed_ms'] ) || is_int( $res['elapsed_ms'] ), 'elapsed_ms is numeric' );

// ─── Test 11: snt_cron_run_event_impl catches Throwable ──────────────
echo "\nTest 11: snt_cron_run_event_impl catches Throwable\n";
$GLOBALS['__test_actions'] = array( 'boom_hook' => true );
$GLOBALS['__test_action_callbacks']['boom_hook'] = function() { throw new RuntimeException( 'simulated handler failure' ); };
$res = snt_cron_run_event_impl( 'boom_hook' );
assert_eq( false, $res['success'], 'success=false on Throwable' );
assert_true( strpos( (string) $res['error'], 'simulated handler failure' ) !== false, 'error message captured' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
```

- [ ] **Step 2: Run the tests**

```bash
cd /Users/juanlentino/projects/signal-and-noise-tools
php tests/cron-dashboard.php
```

Expected output (approximately):
```
Test 1: snt_cron_is_sn_owned
  PASS: SN-owned dashboard hook recognized
  PASS: SN-owned RSS hook recognized
  PASS: WP core hook is not SN-owned
  PASS: Empty string is not SN-owned

[... similar blocks for tests 2-11 ...]

Result: 25 passed, 0 failed.
```

Exit code: 0

If any test fails, read the FAIL line + Expected/Actual diff. Fix the impl, NOT the test. Re-run.

- [ ] **Step 3: Commit**

```bash
cd /Users/juanlentino/projects/signal-and-noise-tools
git add tests/cron-dashboard.php
git commit -m "test(cron-dashboard): assertions for 11 impl behaviors"
```

---

## Task 9: Create the admin tab renderer

**Files:**
- Create: `/Users/juanlentino/projects/signal-and-noise-tools/inc/cron-dashboard-admin.php`

- [ ] **Step 1: Create the file**

```php
<?php
/**
 * Signal & Noise Tools — Cron Dashboard admin tab renderer.
 *
 * Hooks into the sn_admin_cron_tab action (dispatched by
 * inc/admin-page.php when ?tab=cron) and renders the cron events
 * table with the live filter input + Run-now buttons.
 *
 * @package SignalNoiseTools
 * @since 3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'sn_admin_cron_tab', 'snt_cron_render_admin_tab' );

add_action( 'admin_enqueue_scripts', function( $hook_suffix ) {
	// $hook_suffix for the cron page is like 'signal-noise_page_sn-cron'.
	// Match by 'sn-cron' substring so the JS only loads on this tab.
	if ( strpos( (string) $hook_suffix, 'sn-cron' ) === false ) {
		return;
	}
	wp_enqueue_script(
		'sn-cron-dashboard',
		plugins_url( 'assets/cron-dashboard.js', SNT_PATH . 'signal-and-noise-tools.php' ),
		array( 'wp-api-fetch', 'wp-data' ),
		SNT_VERSION,
		true
	);
} );

function snt_cron_render_admin_tab() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to view this page.', 'signal-noise-tools' ) );
	}

	$rows = function_exists( 'snt_cron_get_events_impl' )
		? snt_cron_get_events_impl()
		: array();

	echo '<div class="sn-cron-dashboard">';

	if ( empty( $rows ) ) {
		echo '<div class="sn-card"><h3>No scheduled events.</h3>';
		echo '<p>This is unusual — WordPress core typically schedules <code>wp_version_check</code>, <code>wp_update_plugins</code>, <code>wp_update_themes</code>, and <code>wp_scheduled_delete</code> at install. If your cron is empty, something has cleared it. Check your hosting provider\'s cron configuration.</p></div></div>';
		return;
	}

	echo '<p class="sn-field-helper">' . count( $rows ) . ' scheduled event(s). Signal &amp; Noise–owned events pinned at top.</p>';

	echo '<p><input type="search" id="sn-cron-filter" placeholder="Filter by hook name..." style="width: 320px; padding: 6px 10px;" /></p>';

	echo '<table class="widefat striped" id="sn-cron-table">';
	echo '<thead><tr>';
	echo '<th>Hook</th>';
	echo '<th>Next run</th>';
	echo '<th>Recurrence</th>';
	echo '<th>Last fired</th>';
	echo '<th>Args</th>';
	echo '<th>Actions</th>';
	echo '</tr></thead><tbody>';

	foreach ( $rows as $row ) {
		$row_class = $row['is_sn_owned'] ? 'sn-cron-row sn-cron-owned' : 'sn-cron-row';
		echo '<tr class="' . esc_attr( $row_class ) . '" data-hook="' . esc_attr( $row['hook'] ) . '" data-sig="' . esc_attr( $row['args_signature'] ) . '">';

		// Hook
		echo '<td><code>' . esc_html( $row['hook'] ) . '</code>';
		if ( $row['is_sn_owned'] ) {
			echo ' <span class="sn-badge">SN</span>';
		}
		if ( ! $row['has_handler'] ) {
			echo ' <span class="sn-badge sn-badge-warn">orphan</span>';
		}
		echo '</td>';

		// Next run
		$next_str = wp_date( 'Y-m-d H:i:s', $row['next_run_ts'] );
		$next_rel = human_time_diff( time(), $row['next_run_ts'] );
		echo '<td>' . esc_html( $next_str ) . '<br><small>in ' . esc_html( $next_rel ) . '</small></td>';

		// Recurrence
		if ( $row['schedule'] ) {
			echo '<td>' . esc_html( $row['schedule'] );
			if ( $row['interval_s'] ) {
				echo '<br><small>' . esc_html( human_time_diff( 0, $row['interval_s'] ) ) . '</small>';
			}
			echo '</td>';
		} else {
			echo '<td><small>single event</small></td>';
		}

		// Last fired
		if ( $row['last_fired_ts'] ) {
			$last_str = wp_date( 'Y-m-d H:i:s', $row['last_fired_ts'] );
			$last_rel = human_time_diff( $row['last_fired_ts'], time() );
			echo '<td class="sn-cron-last-fired">' . esc_html( $last_str ) . '<br><small>' . esc_html( $last_rel ) . ' ago</small></td>';
		} else {
			echo '<td class="sn-cron-last-fired">&mdash;</td>';
		}

		// Args
		if ( ! empty( $row['args'] ) ) {
			echo '<td><code>' . esc_html( wp_json_encode( $row['args'] ) ) . '</code></td>';
		} else {
			echo '<td><small>&mdash;</small></td>';
		}

		// Actions
		echo '<td>';
		if ( $row['has_handler'] ) {
			echo '<button class="button button-small sn-cron-run-now" type="button">Run now</button>';
		} else {
			echo '<button class="button button-small" type="button" disabled title="No handler registered">Run now</button>';
		}
		echo '</td>';

		echo '</tr>';
	}

	echo '</tbody></table>';
	echo '</div>';
}
```

- [ ] **Step 2: Syntax lint**

```bash
cd /Users/juanlentino/projects/signal-and-noise-tools
php -l inc/cron-dashboard-admin.php
```

Expected: `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
cd /Users/juanlentino/projects/signal-and-noise-tools
git add inc/cron-dashboard-admin.php
git commit -m "feat(cron-dashboard): admin tab renderer with filter + Run-now buttons + script enqueue"
```

---

## Task 10: Wire the Cron tab into admin-page.php + bootstrap renderer

**Files:**
- Modify: `/Users/juanlentino/projects/signal-and-noise-tools/inc/admin-page.php`
- Modify: `/Users/juanlentino/projects/signal-and-noise-tools/signal-and-noise-tools.php`

- [ ] **Step 1: Find sn_admin_pages() in admin-page.php**

```bash
cd /Users/juanlentino/projects/signal-and-noise-tools
grep -n "sn-reading-time\|sn-links" inc/admin-page.php
```

Note the line where Reading Time and Links are defined in `sn_admin_pages()`. The Cron entry goes BETWEEN them.

- [ ] **Step 2: Add the 9th admin page entry**

Open `inc/admin-page.php`. Find the line defining the Reading Time page. Insert a new line AFTER it (before the Links entry):

```php
		array( 'slug' => 'sn-cron',          'tab' => 'cron',         'label' => 'Cron',          'title' => 'Signal & Noise — Cron',          'subtitle' => 'Scheduled jobs — next run, recurrence, last fired, manual trigger.' ),
```

- [ ] **Step 3: Find the tab dispatch switch**

```bash
cd /Users/juanlentino/projects/signal-and-noise-tools
grep -n "do_action.*sn_admin_.*_tab\|sn_admin_reading_time_tab\|sn_admin_links_tab" inc/admin-page.php
```

Note where the existing `do_action( 'sn_admin_*_tab' )` calls live.

- [ ] **Step 4: Add the cron tab dispatch case**

Find the existing tab dispatch block (search for `do_action( 'sn_admin_reading_time_tab' );`). Insert the cron dispatch in the matching style. If the surrounding code is an if/elseif chain matching `$active_tab`:

```php
	} elseif ( 'reading-time' === $active_tab ) {
		do_action( 'sn_admin_reading_time_tab' );
	} elseif ( 'links' === $active_tab ) {
```

Insert between them:

```php
	} elseif ( 'cron' === $active_tab ) {
		do_action( 'sn_admin_cron_tab' );
```

If the dispatch is a `do_action()` with the tab name interpolated (different pattern), match that pattern instead. **Read the actual surrounding code before inserting** per the process discipline rule.

- [ ] **Step 5: Bootstrap the admin renderer module in main file**

Open `signal-and-noise-tools.php`. Find the line you added in Task 6 (`require_once SNT_PATH . 'inc/cron-dashboard.php';`). Add immediately after it:

```php
require_once SNT_PATH . 'inc/cron-dashboard-admin.php';
```

- [ ] **Step 6: Syntax lint both files**

```bash
cd /Users/juanlentino/projects/signal-and-noise-tools
php -l inc/admin-page.php && php -l signal-and-noise-tools.php
```

Expected: `No syntax errors detected` for both.

- [ ] **Step 7: Commit**

```bash
cd /Users/juanlentino/projects/signal-and-noise-tools
git add inc/admin-page.php signal-and-noise-tools.php
git commit -m "feat(cron-dashboard): wire 9th admin page + tab dispatch + bootstrap renderer"
```

---

## Task 11: Add the REST `/cron/run` endpoint

**Files:**
- Modify: `/Users/juanlentino/projects/signal-and-noise-tools/inc/rest-api.php`

- [ ] **Step 1: Find the existing `rest_api_init` action block**

```bash
cd /Users/juanlentino/projects/signal-and-noise-tools
grep -n "add_action.*rest_api_init\|register_rest_route" inc/rest-api.php
```

Note the line range where the existing routes are registered.

- [ ] **Step 2: Append the cron route inside the existing `rest_api_init` callback**

Open `inc/rest-api.php`. Find the closing `} );` of the `add_action( 'rest_api_init', function() {` block (the last `register_rest_route` is for plausible/test). Insert immediately before that closing `} );`:

```php

	// ── Cron Dashboard endpoint (POST, mutating) ─────────────────────

	register_rest_route( SN_REST_NAMESPACE, '/cron/run', array(
		'methods'             => WP_REST_Server::CREATABLE,
		'permission_callback' => 'sn_rest_can_manage',
		'callback'            => 'snt_rest_cron_run',
		'args'                => array(
			'hook' => array(
				'required' => true,
				'type'     => 'string',
			),
			'args' => array(
				'type'    => 'array',
				'default' => array(),
			),
		),
	) );
```

- [ ] **Step 3: Append the REST callback function at the bottom of rest-api.php**

Find the end of the file. Append:

```php

/**
 * POST /cron/run — synchronously dispatch a cron event by hook name.
 *
 * Defers all logic to snt_cron_run_event_impl. This callback is the
 * REST surface only; the impl module owns the safety guards
 * (DOING_CRON spoof, has_action pre-flight, Throwable catch).
 *
 * Returns the impl's array payload as a WP_REST_Response.
 *
 * @since plugin v3.0.0
 */
function snt_rest_cron_run( WP_REST_Request $request ) {
	if ( ! function_exists( 'snt_cron_run_event_impl' ) ) {
		return new WP_Error(
			'snt_cron_unavailable',
			'Cron dashboard module not loaded.',
			array( 'status' => 500 )
		);
	}
	$hook = (string) $request->get_param( 'hook' );
	$args = $request->get_param( 'args' );
	$result = snt_cron_run_event_impl( $hook, is_array( $args ) ? $args : array() );

	if ( $result instanceof WP_Error ) {
		return $result;
	}

	return rest_ensure_response( $result );
}
```

- [ ] **Step 4: Syntax lint**

```bash
cd /Users/juanlentino/projects/signal-and-noise-tools
php -l inc/rest-api.php
```

Expected: `No syntax errors detected`

- [ ] **Step 5: Commit**

```bash
cd /Users/juanlentino/projects/signal-and-noise-tools
git add inc/rest-api.php
git commit -m "feat(cron-dashboard): POST /signal-noise/v1/cron/run REST endpoint"
```

---

## Task 12: Create the admin JS (filter + Run-now)

**Files:**
- Create: `/Users/juanlentino/projects/signal-and-noise-tools/assets/cron-dashboard.js`

- [ ] **Step 1: Create the JS file**

The script uses `textContent` and `createElement` for the inline cell update (NOT innerHTML) to keep the XSS surface zero even though the data is server-derived. Matches the safe-DOM pattern.

```js
/**
 * Signal & Noise Tools — Cron Dashboard admin script.
 *
 * Loaded only on the wp-admin Cron tab. Wires:
 *   - Live filter input → hides rows whose hook doesn't match.
 *   - Run-now buttons → confirm() prompt, then POST /signal-noise/v1/
 *     cron/run via wp.apiFetch, then update the row's last-fired cell
 *     inline + show a toast.
 *
 * Toast falls back through wp.data dispatch → console.log if no
 * standard notice channel is available. Matches the toast helper in
 * assets/desktop-mode.js for consistency.
 *
 * Inline DOM updates use textContent + createElement (NOT innerHTML)
 * to keep the XSS surface zero even though the data is server-derived.
 *
 * @since plugin v3.0.0
 */
( function() {
	'use strict';

	if ( typeof document === 'undefined' ) {
		return;
	}

	function toast( msg, type ) {
		type = type || 'success';
		if ( window.wp && window.wp.data && window.wp.data.dispatch( 'core/notices' ) ) {
			window.wp.data.dispatch( 'core/notices' ).createNotice( type, msg, { isDismissible: true } );
			return;
		}
		// eslint-disable-next-line no-console
		console.log( '[SN cron]', msg );
	}

	function formatTimestamp( unixSeconds ) {
		var d = new Date( unixSeconds * 1000 );
		// "YYYY-MM-DD HH:MM:SS" — matches wp_date( 'Y-m-d H:i:s' ) server-side.
		return d.toISOString().replace( 'T', ' ' ).replace( /\..+/, '' );
	}

	function updateLastFiredCell( tr, lastFiredTs ) {
		var cell = tr.querySelector( '.sn-cron-last-fired' );
		if ( ! cell || ! lastFiredTs ) {
			return;
		}
		// Safe DOM construction — clear, then append text + br + small.
		while ( cell.firstChild ) {
			cell.removeChild( cell.firstChild );
		}
		cell.appendChild( document.createTextNode( formatTimestamp( lastFiredTs ) ) );
		cell.appendChild( document.createElement( 'br' ) );
		var sm = document.createElement( 'small' );
		sm.textContent = 'just now';
		cell.appendChild( sm );
	}

	function wireFilter() {
		var input = document.getElementById( 'sn-cron-filter' );
		var table = document.getElementById( 'sn-cron-table' );
		if ( ! input || ! table ) {
			return;
		}
		input.addEventListener( 'input', function() {
			var needle = input.value.toLowerCase();
			var rows = table.querySelectorAll( 'tbody tr.sn-cron-row' );
			rows.forEach( function( tr ) {
				var hook = ( tr.getAttribute( 'data-hook' ) || '' ).toLowerCase();
				tr.style.display = hook.indexOf( needle ) === -1 ? 'none' : '';
			} );
		} );
	}

	function wireRunNow() {
		var buttons = document.querySelectorAll( '.sn-cron-run-now' );
		buttons.forEach( function( btn ) {
			btn.addEventListener( 'click', function( e ) {
				e.preventDefault();
				var tr = btn.closest( 'tr.sn-cron-row' );
				if ( ! tr ) {
					return;
				}
				var hook = tr.getAttribute( 'data-hook' );
				if ( ! window.confirm( "Run cron event '" + hook + "' now?" ) ) {
					return;
				}
				if ( ! window.wp || ! window.wp.apiFetch ) {
					toast( 'wp.apiFetch unavailable — cannot dispatch.', 'error' );
					return;
				}
				btn.disabled = true;
				btn.textContent = 'Running…';
				window.wp.apiFetch( {
					path: '/signal-noise/v1/cron/run',
					method: 'POST',
					data: { hook: hook }
				} ).then( function( res ) {
					if ( res && res.success ) {
						updateLastFiredCell( tr, res.last_fired_ts );
						toast( hook + ' fired in ' + Math.round( res.elapsed_ms ) + 'ms', 'success' );
					} else {
						toast( 'Run failed: ' + ( ( res && res.error ) || 'unknown error' ), 'error' );
					}
				} ).catch( function( err ) {
					toast( 'Run failed: ' + ( err.message || err ), 'error' );
				} ).finally( function() {
					btn.disabled = false;
					btn.textContent = 'Run now';
				} );
			} );
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', function() {
			wireFilter();
			wireRunNow();
		} );
	} else {
		wireFilter();
		wireRunNow();
	}
} )();
```

- [ ] **Step 2: Spot-check JS syntax**

```bash
cd /Users/juanlentino/projects/signal-and-noise-tools
node -c assets/cron-dashboard.js 2>/dev/null && echo "JS syntax OK" || echo "node not available; will rely on browser console for JS errors"
```

Either confirms syntax or notes node isn't available (browser console will catch it during smoke test).

- [ ] **Step 3: Commit**

```bash
cd /Users/juanlentino/projects/signal-and-noise-tools
git add assets/cron-dashboard.js
git commit -m "feat(cron-dashboard): admin JS — filter + Run-now with safe-DOM updates"
```

---

## Task 13: Register the 2 read-only abilities

**Files:**
- Modify: `/Users/juanlentino/projects/signal-and-noise-tools/inc/abilities-registration.php`

- [ ] **Step 1: Find the end of the `wp_abilities_api_init` action block**

```bash
cd /Users/juanlentino/projects/signal-and-noise-tools
grep -n "wp_abilities_api_init\|wp_register_ability" inc/abilities-registration.php | tail -20
```

Find the last `wp_register_ability(` call (currently the 11th — `signal-noise/ai-generate-excerpt`). The new abilities go AFTER it but BEFORE the closing `} );` of the `add_action( 'wp_abilities_api_init', ...)` callback.

- [ ] **Step 2: Append the two new ability registrations**

Open `inc/abilities-registration.php`. Find the closing `} );` of `wp_register_ability( 'signal-noise/ai-generate-excerpt', array( ... ) );` and insert immediately after:

```php

	// ── Cron Dashboard abilities (v3.0.0) ─────────────────────────────

	wp_register_ability( 'signal-noise/list-cron-events', array(
		'label'               => 'List Cron Events',
		'description'         => 'Returns all scheduled WP-Cron events with next-run, recurrence, last-fired, args, has_handler flag, and is_sn_owned flag.',
		'category'            => 'diagnostics',
		'permission_callback' => function() {
			return current_user_can( 'manage_options' );
		},
		'execute_callback'    => function( $input ) {
			if ( ! function_exists( 'snt_cron_get_events_impl' ) ) {
				return new WP_Error( 'snt_cron_unavailable', 'Cron dashboard module not loaded.', array( 'status' => 500 ) );
			}
			$sn_only = is_array( $input ) && ! empty( $input['sn_only'] );
			return snt_cron_get_events_impl( $sn_only );
		},
		'input_schema'        => array(
			'type'       => array( 'object', 'null' ),
			'properties' => array(
				'sn_only' => array(
					'type'        => 'boolean',
					'default'     => false,
					'description' => 'If true, filter to the 3 SN-owned hooks only.',
				),
			),
		),
		'output_schema'       => array(
			'type'  => 'array',
			'items' => array(
				'type'       => 'object',
				'properties' => array(
					'hook'           => array( 'type' => 'string' ),
					'args_signature' => array( 'type' => 'string' ),
					'next_run_ts'    => array( 'type' => 'integer' ),
					'schedule'       => array( 'type' => array( 'string', 'boolean' ) ),
					'interval_s'     => array( 'type' => array( 'integer', 'null' ) ),
					'args'           => array( 'type' => 'array' ),
					'last_fired_ts'  => array( 'type' => array( 'integer', 'null' ) ),
					'has_handler'    => array( 'type' => 'boolean' ),
					'is_sn_owned'    => array( 'type' => 'boolean' ),
				),
			),
		),
		'meta'                => array(
			'show_in_rest' => true,
			'annotations'  => array(
				'readonly'        => true,
				'idempotent'      => true,
				'open_world_hint' => false,
			),
		),
	) );

	wp_register_ability( 'signal-noise/get-cron-event', array(
		'label'               => 'Get Cron Event Details',
		'description'         => 'Returns details for a single scheduled cron event identified by hook + args_signature. Returns null if no match.',
		'category'            => 'diagnostics',
		'permission_callback' => function() {
			return current_user_can( 'manage_options' );
		},
		'execute_callback'    => function( $input ) {
			if ( ! function_exists( 'snt_cron_get_event_impl' ) ) {
				return new WP_Error( 'snt_cron_unavailable', 'Cron dashboard module not loaded.', array( 'status' => 500 ) );
			}
			return snt_cron_get_event_impl(
				(string) $input['hook'],
				(string) $input['args_signature']
			);
		},
		'input_schema'        => array(
			'type'       => 'object',
			'required'   => array( 'hook', 'args_signature' ),
			'properties' => array(
				'hook'           => array(
					'type'        => 'string',
					'description' => 'The cron hook name.',
					'minLength'   => 1,
				),
				'args_signature' => array(
					'type'        => 'string',
					'description' => 'The md5 args signature from list-cron-events.',
					'minLength'   => 1,
				),
			),
		),
		'output_schema'       => array(
			'type'       => array( 'object', 'null' ),
			'properties' => array(
				'hook'           => array( 'type' => 'string' ),
				'args_signature' => array( 'type' => 'string' ),
				'next_run_ts'    => array( 'type' => 'integer' ),
				'schedule'       => array( 'type' => array( 'string', 'boolean' ) ),
				'interval_s'     => array( 'type' => array( 'integer', 'null' ) ),
				'args'           => array( 'type' => 'array' ),
				'last_fired_ts'  => array( 'type' => array( 'integer', 'null' ) ),
				'has_handler'    => array( 'type' => 'boolean' ),
				'is_sn_owned'    => array( 'type' => 'boolean' ),
			),
		),
		'meta'                => array(
			'show_in_rest' => true,
			'annotations'  => array(
				'readonly'        => true,
				'idempotent'      => true,
				'open_world_hint' => false,
			),
		),
	) );
```

- [ ] **Step 3: Syntax lint**

```bash
cd /Users/juanlentino/projects/signal-and-noise-tools
php -l inc/abilities-registration.php
```

Expected: `No syntax errors detected`

- [ ] **Step 4: Commit**

```bash
cd /Users/juanlentino/projects/signal-and-noise-tools
git add inc/abilities-registration.php
git commit -m "feat(cron-dashboard): register list-cron-events + get-cron-event abilities"
```

---

## Task 14: Register the 2 ⌘K commands (PHP side) + cronSummary localize

**Files:**
- Modify: `/Users/juanlentino/projects/signal-and-noise-tools/inc/desktop-mode-integration.php`
- Modify: `/Users/juanlentino/projects/signal-and-noise-tools/inc/cron-dashboard.php`

- [ ] **Step 1: Add the `snt_cron_summary_for_localize` helper**

Open `inc/cron-dashboard.php`. Append at the end:

```php

/**
 * Compact summary for desktop-mode wp_localize_script. Avoids serializing
 * the full event list into snDesktopData on every admin page load.
 */
function snt_cron_summary_for_localize() {
	$rows = snt_cron_get_events_impl();
	$total = count( $rows );
	$sn_count = 0;
	$orphans = 0;
	foreach ( $rows as $row ) {
		if ( $row['is_sn_owned'] ) {
			$sn_count++;
		}
		if ( ! $row['has_handler'] ) {
			$orphans++;
		}
	}
	return array(
		'total'    => $total,
		'sn_count' => $sn_count,
		'orphans'  => $orphans,
	);
}
```

- [ ] **Step 2: Find the `$commands` array and the `pages` array in desktop-mode-integration.php**

```bash
cd /Users/juanlentino/projects/signal-and-noise-tools
grep -n "sn-cmd-version-plugin\|'pages'" inc/desktop-mode-integration.php
```

Note the line numbers of: (a) the last entry in `$commands` (sn-cmd-version-plugin), (b) the `'pages' => array(` block.

- [ ] **Step 3: Add two entries to the `$commands` array**

Open `inc/desktop-mode-integration.php`. Find the `$commands = array(` block. After the `sn-cmd-version-plugin` line and BEFORE the closing `);` of `$commands`, insert:

```php

		// Cron Dashboard (v3.0.0).
		array( 'slug' => 'sn-cmd-cron-health', 'label' => 'SN: Cron health overview',    'description' => 'Toast a summary of scheduled events + navigate to the Cron tab.',     'icon' => 'dashicons-clock' ),
		array( 'slug' => 'sn-cmd-cron-list',   'label' => 'SN: Open Cron tab',           'description' => 'Navigate directly to the SN Cron tab in wp-admin.',                  'icon' => 'dashicons-list-view' ),
```

- [ ] **Step 4: Add `pages.cron` to `snDesktopData`**

Find the `'pages' => array(` block (~line 100 inside the `admin_enqueue_scripts` callback that does `wp_localize_script`). Insert a new line in alphabetic order (after `cloudflare`, before `dashboard`):

```php
			'cron'         => admin_url( 'admin.php?page=sn-cron' ),
```

- [ ] **Step 5: Add `cronSummary` to `snDesktopData`**

In the same `$shared = array(...)` block, after `'plugin' => $plugin,`:

```php
		'cronSummary'   => function_exists( 'snt_cron_summary_for_localize' ) ? snt_cron_summary_for_localize() : array(),
```

- [ ] **Step 6: Syntax lint both files**

```bash
cd /Users/juanlentino/projects/signal-and-noise-tools
php -l inc/desktop-mode-integration.php && php -l inc/cron-dashboard.php
```

Expected: `No syntax errors detected` for both.

- [ ] **Step 7: Commit**

```bash
cd /Users/juanlentino/projects/signal-and-noise-tools
git add inc/desktop-mode-integration.php inc/cron-dashboard.php
git commit -m "feat(cron-dashboard): register 2 ⌘K commands (PHP side) + cronSummary localize"
```

---

## Task 15: Wire the 2 ⌘K commands (JS side, with aiCallable: true)

**Files:**
- Modify: `/Users/juanlentino/projects/signal-and-noise-tools/assets/desktop-mode.js`

- [ ] **Step 1: Find the existing command registrations**

```bash
cd /Users/juanlentino/projects/signal-and-noise-tools
grep -n "sn-cmd-version-plugin\|sn-cmd-nav-reading-time" assets/desktop-mode.js
```

The new commands go at the bottom of the file, after the existing Info commands.

- [ ] **Step 2: Append the 2 new command registrations**

Open `assets/desktop-mode.js`. Find the last `window.wp.desktop.registerCommand` call (currently `sn-cmd-version-plugin`). Append immediately after it (still inside the outer IIFE, before the closing `} )();`):

```js

	// Cron Dashboard (v3.0.0) — both aiCallable, read-only.
	window.wp.desktop.registerCommand( {
		slug: 'sn-cmd-cron-health',
		aiCallable: true,
		run: function() {
			var summary = data.cronSummary || {};
			toast(
				'Cron: ' + ( summary.total || 0 ) + ' events, ' +
				( summary.sn_count || 0 ) + ' SN-owned, ' +
				( summary.orphans || 0 ) + ' orphan' + ( summary.orphans === 1 ? '' : 's' ),
				'info'
			);
			navigate( pages.cron );
		}
	} );

	window.wp.desktop.registerCommand( {
		slug: 'sn-cmd-cron-list',
		aiCallable: true,
		run: function() {
			navigate( pages.cron );
		}
	} );
```

- [ ] **Step 3: Spot-check JS syntax**

```bash
cd /Users/juanlentino/projects/signal-and-noise-tools
node -c assets/desktop-mode.js 2>/dev/null && echo "JS syntax OK" || echo "node not available (manual browser check needed)"
```

- [ ] **Step 4: Commit**

```bash
cd /Users/juanlentino/projects/signal-and-noise-tools
git add assets/desktop-mode.js
git commit -m "feat(cron-dashboard): wire 2 ⌘K commands with aiCallable: true"
```

---

## Task 16: Manual smoke test on the live site

**Files:** None (verification only)

This is the canonical gate per spec § 10.2 and the v2.5.5 handoff convention. Standalone tests (Task 8) cover the pure logic; the integration paths (REST, abilities, ⌘K) are exercised here.

⚠️ **Do NOT proceed to Task 17 (version bump + release) until every step below passes.** If any step fails, debug + fix + re-commit before continuing.

- [ ] **Step 1: Push the implementation branch (or main) to origin**

```bash
cd /Users/juanlentino/projects/signal-and-noise-tools
git push origin HEAD
```

If on `feat/cron-dashboard-v3.0.0`: opens the door for a manual deploy if needed. If on `main`: the WP-UI Updates path will pick up the next tag.

- [ ] **Step 2: Cut an RC tag for smoke testing on live**

This release is pre-v3.0.0 (no tag yet), so the WP-UI Updates path won't see it. For the smoke test, cut a release-candidate tag:

```bash
cd /Users/juanlentino/projects/signal-and-noise-tools
git tag -a v3.0.0-rc.1 -m "v3.0.0-rc.1 — Cron Dashboard pre-release for smoke test"
git push origin v3.0.0-rc.1
gh workflow run deploy.yml --repo juanlentino/signal-and-noise-tools --ref v3.0.0-rc.1
```

Watch for the deploy completing:

```bash
gh run watch --repo juanlentino/signal-and-noise-tools
```

If the workflow fails with "git checkout: local changes would be overwritten" (a known issue from `feedback_plugin_deploy_is_manual`), SSH into the Cloudways app as `sn-plugin` user and run `git reset --hard && git clean -fd` then re-trigger.

- [ ] **Step 3: Smoke test the admin tab**

1. Visit `https://juanlentino.com/wp-admin/admin.php?page=sn-cron`
2. Verify: 9th sidebar entry "Cron" appears between Reading Time and Links
3. Table renders with 20-30 rows (typical WP install)
4. SN-owned hooks (`sn_plausible_refresh_dashboard`, `sn_plausible_refresh_realtime`, `sn_rss_tracker_daily_prune`) appear at the top with the "SN" badge
5. Any orphan hooks (rare but possible) show the "orphan" badge with disabled Run-now button
6. Filter input: type `wp_` → only WordPress core hooks show
7. Filter input: clear → all rows visible again

- [ ] **Step 4: Smoke test Run-now on an SN-owned hook**

1. Click "Run now" on the row for `sn_rss_tracker_daily_prune`
2. Confirm prompt appears: "Run cron event 'sn_rss_tracker_daily_prune' now?"
3. Click OK
4. Button changes to "Running…" then back to "Run now"
5. Toast appears: "sn_rss_tracker_daily_prune fired in <N>ms"
6. The "Last fired" cell in that row updates inline to show the current timestamp + "just now"
7. Refresh the page → the last-fired timestamp persists (option write succeeded)

- [ ] **Step 5: Smoke test Run-now error handling**

1. Open browser console on the Cron tab
2. Manually POST to `/wp-json/signal-noise/v1/cron/run` with a fake hook:

```js
wp.apiFetch({ path: '/signal-noise/v1/cron/run', method: 'POST', data: { hook: 'non_existent_hook_xyz' } })
  .catch(console.error)
```

3. Expected: a 400 response with `code: 'snt_cron_no_handler'`

- [ ] **Step 6: Smoke test ⌘K commands**

1. Press ⌘K → start typing "cron health" → `SN: Cron health overview` appears in the palette
2. Select it → toast appears with summary stats → tab navigates to Cron page
3. Press ⌘K → "Open Cron tab" → navigates without toast
4. Press ⌘K → type a natural-language query like *"show me my scheduled jobs"* → AI Copilot mode → should invoke `list-cron-events` ability and respond with the list (per the aiCallable opt-in)
5. Press ⌘K → ask *"run my RSS prune job now"* → AI should NOT dispatch run-now (we didn't expose it); should respond chat-style suggesting manual button click

- [ ] **Step 7: Confirm last-fired tracking persists across actual cron firings**

1. Note the current `last_fired_ts` for `sn_plausible_refresh_dashboard` (or any frequently-firing SN hook)
2. Wait for the actual cron to fire (visit any frontend page to trigger pseudo-cron if needed) — Plausible refresh fires every 6h via single-event scheduling
3. Refresh the Cron tab
4. The last-fired column should update without you having clicked Run-now (proves the wp_loaded gate + PHP_INT_MAX tracker works)

- [ ] **Step 8: Sanity-check non-cron page performance**

1. Open Chrome DevTools → Network tab
2. Visit any frontend page (a post, the homepage)
3. Confirm the PHP response time hasn't regressed meaningfully — the wp_loaded gate should be no-op on non-cron requests

This is informal — there's no benchmark to fail against. Just gut-check.

- [ ] **Step 9: If ANY step failed, debug + fix + re-commit BEFORE Task 17**

Apply `superpowers:systematic-debugging` if needed. Per the project's hard rule: invoke skills + read source + verify with evidence. Don't guess.

---

## Task 17: Version bump + CHANGELOG entry + release commit + tag

**Files:**
- Modify: `/Users/juanlentino/projects/signal-and-noise-tools/signal-and-noise-tools.php`
- Modify: `/Users/juanlentino/projects/signal-and-noise-tools/CHANGELOG.md`

- [ ] **Step 1: Bump version in plugin header**

Open `signal-and-noise-tools.php`. Find:

```php
 * Version:     2.5.5
```

Change to:

```php
 * Version:     3.0.0
```

Find:

```php
define( 'SNT_VERSION', '2.5.5' );
```

Change to:

```php
define( 'SNT_VERSION', '3.0.0' );
```

- [ ] **Step 2: Add CHANGELOG entry at the top**

Open `CHANGELOG.md`. Below the line `All notable changes to Signal & Noise Tools are documented here.`, insert a new section above the existing `## [2.5.5] - 2026-05-20`:

```markdown

## [3.0.0] - 2026-05-20

### Added — Phase 15 net-new: Cron Dashboard

New wp-admin Cron tab (9th tab) surfaces every scheduled WP-Cron event with next-run, recurrence, last-fired, args, and a Run-now button. ~455 LOC across 3 new files + 7 modified files.

**Cap rollover note:** v3.0.0 is a minor-cap rollover, NOT a semantic breaking change. v2.x consumed 6 minors (v2.0–v2.5) which is the project's cap of 5 per major (per `CLAUDE.md` versioning rules). The next minor MUST roll to v3.0.0 even though this release ships zero breaking changes. All existing APIs, abilities, REST routes, and ⌘K commands continue to work exactly as in v2.5.5.

### Surfaces (4-surface dispatch pattern, per Phase 14+ convention)

All four routes converge on the same `snt_cron_*_impl()` pure functions:

| Surface | Read | Run-now |
|---|:---:|:---:|
| wp-admin Cron tab | ✅ | ✅ (button + confirm() prompt) |
| Legacy REST `/signal-noise/v1/cron/run` | — | ✅ (manage_options gated) |
| Abilities API `list-cron-events`, `get-cron-event` | ✅ | ❌ (no run ability — read-only AI exposure) |
| desktop-mode ⌘K `sn-cmd-cron-health`, `sn-cmd-cron-list` | ✅ (aiCallable: true) | ❌ |

Run-now stays human-only per the v2.5.5 destructive-command safety precedent.

### Safety guards on Run-now

1. **`manage_options` permission gate** — REST callback + ability permission_callback both check
2. **`has_action($hook)` pre-flight** — orphan hooks return `WP_Error('snt_cron_no_handler')` rather than dispatching to nothing
3. **`DOING_CRON` spoof** — `define('DOING_CRON', true)` before dispatch so handlers that guard on `wp_doing_cron()` (Action Scheduler, many WP core hooks) actually execute. Standard pattern from WP-Crontrol since 2012.
4. **`Throwable` catch** — covers PHP 7+ Error subclasses (TypeError, ParseError, ArgumentCountError, OutOfMemory*) in addition to Exception. Only truly unrecoverable cases (segfault, hard OOM) bypass it.
5. **JS `confirm()` prompt** — explicit human confirmation before any side-effecting POST.

### Last-fired tracking (universal)

WP-Cron does not track last-fired natively. We register `snt_cron_track_last_fired_cb` at `PHP_INT_MAX` for every unique cron hook during `DOING_CRON` requests (gated at `wp_loaded` priority 1 so non-cron requests pay only one `defined()` check). Storage: `wp_options` table, autoload `false`, keys hashed via `md5($hook)` to fit the varchar(191) limit.

### Files

- **New:** `inc/cron-dashboard.php`, `inc/cron-dashboard-admin.php`, `assets/cron-dashboard.js`, `tests/cron-dashboard.php`
- **Modified:** `signal-and-noise-tools.php`, `inc/admin-page.php`, `inc/abilities-registration.php`, `inc/rest-api.php`, `inc/desktop-mode-integration.php`, `assets/desktop-mode.js`, `CHANGELOG.md`

### Out of scope (deferred)

- Editing cron events (reschedule, change interval, unschedule)
- Adding NEW cron events from the UI
- Cron history / log of all firings (last-fired single-timestamp only)
- Per-user filter preferences
- Pagination
- Special handling for Action Scheduler

### Process

`superpowers:brainstorming` → 4 design decisions locked (all events, universal last-fired, synchronous run-now, read-only AI exposure) → safety hardening lens applied → `superpowers:writing-plans` → 18-task implementation plan → executed task-by-task per `feedback_skills_plugins_docs_always`. No source-reading skipped.

### Spec & plan

- Spec: `docs/superpowers/specs/2026-05-20-cron-dashboard-design.md` in the theme repo
- Plan: `docs/superpowers/plans/2026-05-20-cron-dashboard.md` in the theme repo
```

- [ ] **Step 3: Syntax lint main file**

```bash
cd /Users/juanlentino/projects/signal-and-noise-tools
php -l signal-and-noise-tools.php
```

Expected: `No syntax errors detected`

- [ ] **Step 4: Final standalone test run**

```bash
cd /Users/juanlentino/projects/signal-and-noise-tools
php tests/cron-dashboard.php
```

Expected: `Result: 25 passed, 0 failed.` Exit code 0.

- [ ] **Step 5: Commit the release**

```bash
cd /Users/juanlentino/projects/signal-and-noise-tools
git add signal-and-noise-tools.php CHANGELOG.md
git commit -m "v3.0.0: Cron Dashboard — Phase 15 net-new"
```

- [ ] **Step 6: Tag and push**

```bash
cd /Users/juanlentino/projects/signal-and-noise-tools
git tag -a v3.0.0 -m "v3.0.0 — Cron Dashboard (Phase 15 net-new, cap rollover from v2.5.5)"
git push origin HEAD:main
git push origin v3.0.0
```

⚠️ **Tag push does NOT auto-deploy** (per `feedback_plugin_deploy_is_manual` — plugin deploy is `workflow_dispatch` only since v1.10.1). Install via the canonical wp-admin Updates path next.

- [ ] **Step 7: Install via wp-admin Updates UI**

1. wp-admin → S&N → Dashboard → Maintenance → **"Check Now"** (the v2.5.3 button)
2. Wait ~5s for transients to clear
3. wp-admin → Updates → **"Update plugin"** for Signal & Noise Tools
4. Confirm version display now reads `3.0.0`

If the Updates UI doesn't surface v3.0.0 after Check Now (transient race), fallback:

```bash
gh workflow run deploy.yml --repo juanlentino/signal-and-noise-tools --ref v3.0.0
```

- [ ] **Step 8: Final post-deploy verification**

1. Visit `https://juanlentino.com/wp-admin/admin.php?page=sn-cron` — table renders
2. Press ⌘K → "Cron health overview" → expected toast + nav
3. wp-admin Plugins page → confirm Signal & Noise Tools shows v3.0.0

---

## Task 18: Write session handoff

**Files:**
- Create in the theme repo at `docs/superpowers/handoffs/2026-05-20-v3.0.0-cron-dashboard-handoff.md`

- [ ] **Step 1: Switch back to the theme worktree**

```bash
cd /Users/juanlentino/projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551
```

- [ ] **Step 2: Write a handoff doc following the convention of `docs/superpowers/handoffs/2026-05-20-end-of-v2.5.5-handoff.md`**

Required sections (model after the v2.5.5 handoff):
- TL;DR — current state (plugin v3.0.0 live, theme v8.5.7 still at cap)
- What shipped this session (chronological table)
- How to install v3.0.0 (Check Now → Updates UI path)
- Verification smoke test recap
- Architectural state (4-surface pattern now has 13 abilities + Cron tab as 9th admin page + 15 ⌘K commands of which 12 are aiCallable)
- Pending work — for next session (list out-of-scope items from spec § 13 as Phase 15.x candidates)
- Key files to know (update to include the 3 new cron-dashboard files)
- Process discipline (preserve the hard rule)
- Lessons captured (anything new that surfaced during execution — e.g., bot-detection.php standalone-test pattern proved durable for a more complex module)
- Where to pick up next session

- [ ] **Step 3: Commit the handoff in the theme worktree**

```bash
cd /Users/juanlentino/projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551
git add docs/superpowers/handoffs/2026-05-20-v3.0.0-cron-dashboard-handoff.md
git commit -m "docs: handoff for v3.0.0 cron-dashboard session"
```

- [ ] **Step 4: Push the handoff**

```bash
cd /Users/juanlentino/projects/signal-and-noise/.claude/worktrees/nice-goldstine-063551
git push origin HEAD:main
```

---

## Self-review (run by writing-plans skill at end)

### Spec coverage check

| Spec section | Task(s) implementing it |
|---|---|
| § 2 Architecture (4-surface dispatch) | Tasks 1-15 (entire plan) |
| § 3 File layout | All tasks; Tasks 6 + 10 bootstrap requires |
| § 4.1 Row schema | Task 4 (impl), Task 9 (renderer), Task 13 (ability schemas) |
| § 4.2 Run-now response | Task 5 (impl), Task 11 (REST), Task 12 (JS consumer) |
| § 4.3 Last-fired storage | Task 2 (helpers) |
| § 5.1 Tab render | Tasks 9 + 10 |
| § 5.2 ⌘K sn-cmd-cron-health | Task 14 (PHP) + Task 15 (JS w/ aiCallable) |
| § 5.3 ⌘K sn-cmd-cron-list | Tasks 14 + 15 |
| § 5.4 list-cron-events ability | Task 13 |
| § 5.5 get-cron-event ability | Task 13 |
| § 6.1 JS run-now click | Task 12 |
| § 6.2 Server-side run-now | Task 5 |
| § 6.3 REST registration | Task 11 |
| § 7 Universal last-fired tracking | Tasks 2 + 3 |
| § 8 Error handling matrix | Tasks 5 + 9 + 11 |
| § 9 Versioning (v3.0.0 rollover) | Task 17 |
| § 10.1 Tests | Tasks 7 + 8 (adapted to standalone fixtures per project precedent) |
| § 10.2 Manual smoke test | Task 16 |
| § 11 Explicit non-changes | Honored throughout — every modification is append-only |

All sections covered.

### Placeholder scan

Scanned for `TODO`, `TBD`, `// implement later`, `fill in details`, `add appropriate error handling`, `similar to Task N`. None present.

The "find this line" instructions (e.g., "find the closing `} );` of the `rest_api_init` block") use grep commands that produce the actual lookup, so they're executable without context.

### Type consistency

| Identifier | Definition | Reused in |
|---|---|---|
| `snt_cron_get_events_impl( $sn_only = false )` | Task 4 | Tasks 9, 13, 14 ✅ |
| `snt_cron_get_event_impl( $hook, $args_signature )` | Task 4 | Task 13 ✅ |
| `snt_cron_run_event_impl( $hook, $args = array() )` | Task 5 | Task 11 ✅ |
| `snt_cron_record_last_fired( $hook )` | Task 2 | Tasks 3, 5 (via callback) ✅ |
| `snt_cron_last_fired_for( $hook )` | Task 2 | Tasks 4, 5, 9 ✅ |
| `snt_cron_track_last_fired_cb` | Task 2 | Tasks 3, 5 ✅ |
| `snt_cron_is_sn_owned( $hook )` | Task 1 | Task 4 ✅ |
| `snt_cron_sn_owned_hooks()` | Task 1 | Task 1 (called by `snt_cron_is_sn_owned`) ✅ |
| `snt_cron_summary_for_localize()` | Task 14 | Task 14 (called by snDesktopData wiring) ✅ |
| `snt_rest_cron_run( WP_REST_Request $request )` | Task 11 | Task 11 ✅ |
| Row keys (`hook`, `args_signature`, `next_run_ts`, `schedule`, `interval_s`, `args`, `last_fired_ts`, `has_handler`, `is_sn_owned`) | Task 4 (impl) | Tasks 8 (test schema), 9 (render), 13 (output_schema) — ALL MATCH ✅ |
| Run-now payload keys (`success`, `elapsed_ms`, `error`, `last_fired_ts`, `hook`) | Task 5 (impl) | Task 12 (JS consumer) ✅ |

All identifiers consistent.

---

## Execution handoff

**Plan complete and saved to `docs/superpowers/plans/2026-05-20-cron-dashboard.md`. Two execution options:**

**1. Subagent-Driven (recommended)** — Dispatch a fresh subagent per task with `superpowers:subagent-driven-development`. Each task gets clean context; you review between tasks; fastest iteration when individual tasks are large.

**2. Inline Execution** — Execute tasks in this session via `superpowers:executing-plans`. Batch execution with checkpoints at task boundaries; preserves conversation context throughout; faster for shorter plans.

For a ~18-task plan of this size, either approach works. Subagent-Driven gives cleaner per-task focus; Inline gives you continuous context for debugging cross-task issues.

**Which approach?**
