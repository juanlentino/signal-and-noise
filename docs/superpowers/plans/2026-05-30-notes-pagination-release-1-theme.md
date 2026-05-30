# /notes Pagination — Release 1 (Theme) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add query-string pagination (`/notes/?paged=N`) to the `/notes` index at a default of 20 notes per page, overridable via a new `sn_notes_per_page` filter, rendered with a styled `paginate_links()` control — theme-only, no plugin dependency.

**Architecture:** The `/notes` index is rendered by `inc/page-notes-render.php` (a PHP file `include`-d + `exit`-ed by the `template_redirect` short-circuit in `inc/page-notes-template.php`). Two new pure helper functions (`sn_notes_per_page()`, `sn_notes_current_page()`) carry the testable logic; `sn_notes_query_posts()` consumes them; the render body gains a thin `paginate_links()` control + a corrected total-count display. Pure helpers are unit-tested headlessly (the render body, being inline markup in an `include`-d file, is not).

**Tech Stack:** PHP 8.0+, WordPress 6.4+, `WP_Query`, `paginate_links()`. No JS. No build step. Tests run via `php tests/notes-pagination.php` (CLI standalone fixture, WP functions stubbed — matches `tests/post-frontmatter.php`).

**Spec:** [`docs/superpowers/specs/2026-05-30-notes-pagination-design.md`](../specs/2026-05-30-notes-pagination-design.md) (Release 1 sections only).

**Scope note:** This plan is Release 1 (theme) ONLY. Release 2 (plugin `sn_notes_per_page` setting + Site-tab section refactor + paged-SEO) is parked for its own plan at its own BC. At 13 published notes (< 20/page), the pagination UI stays latent until published notes exceed the per-page value — this is expected; the feature is correct and dormant, not broken.

---

## File structure

| File | Responsibility | Change |
|---|---|---|
| `inc/page-notes-render.php` | /notes index render | Modify: add 2 helpers, rewrite `sn_notes_query_posts()`, add control + count fix, add CSS |
| `inc/page-notes-template.php` | /notes routing + build marker | Modify: bump `SN_NOTES_OVERRIDE_BUILD` |
| `tests/notes-pagination.php` | Unit tests for the helpers + query args | Create |
| `style.css` | theme version | Modify at ship (version bump — decide patch vs minor at release) |
| `CHANGELOG.md`, `readme.txt` | release records | Modify at ship |

**Helper boundaries (the testable seams):**
- `sn_notes_per_page(): int` — returns `apply_filters('sn_notes_per_page', 20)` clamped to [1,100]. Pure; no globals.
- `sn_notes_current_page(): int` — resolves the current page from `get_query_var('paged')` with a `$_GET['paged']` fallback, floored at 1. Reads superglobal/WP state; isolated so the template body stays thin.
- `sn_notes_query_posts(): WP_Query` — consumes both helpers; the only behavior change is its args.

---

## Task 1: `sn_notes_per_page()` helper (default + clamp)

**Files:**
- Modify: `inc/page-notes-render.php` (add helper above `sn_notes_query_posts()`, ~line 98)
- Test: `tests/notes-pagination.php` (create)

- [ ] **Step 1: Write the failing test**

Create `tests/notes-pagination.php`:

```php
<?php
/**
 * Standalone fixture tests for /notes pagination helpers (Release 1).
 *
 * Stubs apply_filters + get_query_var so the pure helpers in
 * inc/page-notes-render.php can be exercised without a WP load.
 *
 * @since theme v9.6.0
 */

// SECURITY: Prevent web access. Test fixture, not a runtime module.
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) {
    http_response_code( 404 );
    exit;
}

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', '/' );
}

// ── Controllable stub state ──
$GLOBALS['__filters']    = array(); // filter name => return value
$GLOBALS['__query_vars'] = array(); // var name => value

if ( ! function_exists( 'apply_filters' ) ) {
    function apply_filters( $hook, $value ) {
        return array_key_exists( $hook, $GLOBALS['__filters'] )
            ? $GLOBALS['__filters'][ $hook ]
            : $value;
    }
}
if ( ! function_exists( 'get_query_var' ) ) {
    function get_query_var( $var, $default = '' ) {
        return $GLOBALS['__query_vars'][ $var ] ?? $default;
    }
}

// Pull in ONLY the helper functions. page-notes-render.php is a full
// render file that echoes HTML + calls WP_Query at load, so we cannot
// require it directly. Instead, the helpers live in a guarded block at
// the top of that file that returns early when SN_NOTES_RENDER_TEST is
// defined (see Task 1 Step 3). Define the sentinel, then require.
define( 'SN_NOTES_RENDER_TEST', true );
require __DIR__ . '/../inc/page-notes-render.php';

$pass = 0; $fail = 0;
function ok( $cond, $label ) {
    global $pass, $fail;
    if ( $cond ) { $pass++; echo "PASS: $label\n"; }
    else { $fail++; echo "FAIL: $label\n"; }
}

// ── sn_notes_per_page(): default + clamp ──
$GLOBALS['__filters'] = array();
ok( sn_notes_per_page() === 20, 'default per-page is 20 when no filter' );

$GLOBALS['__filters'] = array( 'sn_notes_per_page' => 5 );
ok( sn_notes_per_page() === 5, 'filter override respected (5)' );

$GLOBALS['__filters'] = array( 'sn_notes_per_page' => 0 );
ok( sn_notes_per_page() === 1, 'clamp floor: 0 -> 1' );

$GLOBALS['__filters'] = array( 'sn_notes_per_page' => 999 );
ok( sn_notes_per_page() === 100, 'clamp ceiling: 999 -> 100' );

$GLOBALS['__filters'] = array( 'sn_notes_per_page' => '15abc' );
ok( sn_notes_per_page() === 15, 'cast non-int return to int (15abc -> 15)' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/notes-pagination.php`
Expected: FAIL — fatal "Call to undefined function sn_notes_per_page()" (the sentinel guard + helper don't exist yet).

- [ ] **Step 3: Add the helper, THEN the test sentinel guard (order is load-bearing)**

**Critical ordering (verified empirically — do not reorder):** PHP does NOT hoist a `function` declaration that sits *below* a file-scope `return` that executes — the parser stops at the `return`, so anything after it is never declared. Therefore the helper declarations MUST come BEFORE the test-sentinel `return`, and the render body (which echoes HTML + runs `WP_Query`) comes AFTER it. Structure of `inc/page-notes-render.php` after this step:

```
<?php  ... file docblock ...
if ( ! defined( 'ABSPATH' ) ) { exit; }

const SN_NOTES_OVERRIDE_BUILD = ...   // (existing constants stay)

// ── HELPERS (declared first, so they exist even under test) ──
function sn_notes_per_page() { ... }
function sn_notes_current_page() { ... }   // Task 2
function sn_notes_query_posts() { ... }     // Task 3 rewrites this

// ── TEST SENTINEL: stop before the render body ──
if ( defined( 'SN_NOTES_RENDER_TEST' ) && SN_NOTES_RENDER_TEST ) {
    return; // helpers above are defined; render body below is skipped under test
}

// ── RENDER BODY (only runs for real requests) ──
$query = sn_notes_query_posts();
... all the echo/HTML ...
```

Concretely for THIS step: find the existing `sn_notes_query_posts()` (~line 99). Insert this helper ABOVE it (keeping it within the helpers cluster, all before the render body):

```php
/**
 * Notes per page for the /notes index. Default 20; overridable by the
 * plugin via the sn_notes_per_page filter (Release 2). Clamped [1,100]
 * to defend against a bad filter return.
 */
function sn_notes_per_page() {
    $n = (int) apply_filters( 'sn_notes_per_page', 20 );
    return max( 1, min( 100, $n ) );
}
```

Then add the test sentinel `return` on the line IMMEDIATELY BEFORE the render body begins — i.e. right before `$query = sn_notes_query_posts();` (currently line 111), NOT near the top. This guarantees all three helper `function` declarations are above it:

```php
// Under test (tests/notes-pagination.php), the helper functions above are
// now declared; stop here so the render body (which echoes HTML + runs
// WP_Query) doesn't execute. Placement matters: this return MUST be below
// every helper declaration (PHP does not declare a function written after
// a return that runs). Verified empirically during plan authoring.
if ( defined( 'SN_NOTES_RENDER_TEST' ) && SN_NOTES_RENDER_TEST ) {
    return;
}

// ── BEGIN PAGE OUTPUT ──  (existing line ~110, unchanged below here)
$query = sn_notes_query_posts();
```

**Note for Tasks 2 & 3:** they add `sn_notes_current_page()` and rewrite `sn_notes_query_posts()` — both must also stay ABOVE this sentinel `return`. Since the existing `sn_notes_query_posts()` is already above line 111, and Task 2's helper goes next to `sn_notes_per_page()`, this holds naturally. Just confirm placement when editing.

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/notes-pagination.php`
Expected: PASS for all 5 `sn_notes_per_page` assertions.

- [ ] **Step 5: Commit**

```bash
git add inc/page-notes-render.php tests/notes-pagination.php
git commit -m "feat(notes): sn_notes_per_page() helper (default 20, sn_notes_per_page filter, clamp 1-100)"
```

---

## Task 2: `sn_notes_current_page()` helper (paged resolution)

**Files:**
- Modify: `inc/page-notes-render.php` (add helper next to `sn_notes_per_page()`)
- Test: `tests/notes-pagination.php` (extend)

- [ ] **Step 1: Write the failing test**

Append to `tests/notes-pagination.php` BEFORE the `Result:` echo:

```php
// ── sn_notes_current_page(): query var + $_GET fallback + floor ──
$GLOBALS['__query_vars'] = array(); unset( $_GET['paged'] );
ok( sn_notes_current_page() === 1, 'default page is 1 (nothing set)' );

$GLOBALS['__query_vars'] = array( 'paged' => 3 ); unset( $_GET['paged'] );
ok( sn_notes_current_page() === 3, 'reads get_query_var(paged)=3' );

$GLOBALS['__query_vars'] = array(); $_GET['paged'] = '2';
ok( sn_notes_current_page() === 2, 'falls back to $_GET[paged]=2 when query var is 0/empty' );

$GLOBALS['__query_vars'] = array( 'paged' => 0 ); $_GET['paged'] = '4';
ok( sn_notes_current_page() === 4, 'query var 0 -> uses $_GET fallback (4)' );

$GLOBALS['__query_vars'] = array(); $_GET['paged'] = '-5';
ok( sn_notes_current_page() === 1, 'floor at 1 for negative $_GET' );
unset( $_GET['paged'] );
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/notes-pagination.php`
Expected: FAIL — "Call to undefined function sn_notes_current_page()".

- [ ] **Step 3: Add the helper**

In `inc/page-notes-render.php`, directly below `sn_notes_per_page()`:

```php
/**
 * Resolve the current page number for the /notes index. Reads WP's
 * `paged` query var, falling back to the raw ?paged= query-string
 * param — the short-circuit router (inc/page-notes-template.php) may
 * not populate the query var cleanly, and the paginate_links() output
 * carries ?paged=N. Floored at 1.
 */
function sn_notes_current_page() {
    $paged = (int) get_query_var( 'paged' );
    if ( $paged < 1 && isset( $_GET['paged'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only pagination index, no state change.
        $paged = (int) $_GET['paged']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    }
    return max( 1, $paged );
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/notes-pagination.php`
Expected: PASS — all `sn_notes_per_page` + `sn_notes_current_page` assertions.

- [ ] **Step 5: Commit**

```bash
git add inc/page-notes-render.php tests/notes-pagination.php
git commit -m "feat(notes): sn_notes_current_page() helper (paged query var + \$_GET fallback, floor 1)"
```

---

## Task 3: Rewire `sn_notes_query_posts()` to paginate

**Files:**
- Modify: `inc/page-notes-render.php:99-108` (the `sn_notes_query_posts()` body)
- Test: `tests/notes-pagination.php` (extend with a WP_Query capture stub)

- [ ] **Step 1: Write the failing test**

In `tests/notes-pagination.php`, add a `WP_Query` capture stub near the other stubs (top, after `get_query_var`):

```php
// Capture the args WP_Query is constructed with.
$GLOBALS['__wpquery_args'] = null;
if ( ! class_exists( 'WP_Query' ) ) {
    class WP_Query {
        public $args;
        public function __construct( $args = array() ) {
            $args['_constructed'] = true;
            $GLOBALS['__wpquery_args'] = $args;
            $this->args = $args;
        }
    }
}
```

Then append these assertions before the `Result:` echo:

```php
// ── sn_notes_query_posts(): pagination args ──
$GLOBALS['__filters'] = array(); $GLOBALS['__query_vars'] = array( 'paged' => 2 ); unset( $_GET['paged'] );
$q = sn_notes_query_posts();
$a = $GLOBALS['__wpquery_args'];
ok( $a['posts_per_page'] === 20, 'query uses default per-page 20' );
ok( $a['paged'] === 2,           'query passes paged=2 from query var' );
ok( $a['no_found_rows'] === false, 'no_found_rows is false (pagination needs found_posts)' );
ok( $a['post_status'] === 'publish', 'still publish-only (scheduled excluded)' );
ok( $a['post_type'] === 'post',  'still post_type=post' );

$GLOBALS['__filters'] = array( 'sn_notes_per_page' => 10 ); $GLOBALS['__query_vars'] = array();
sn_notes_query_posts();
ok( $GLOBALS['__wpquery_args']['posts_per_page'] === 10, 'filter override flows into query (10)' );
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/notes-pagination.php`
Expected: FAIL — current `sn_notes_query_posts()` has `posts_per_page=50`, no `paged`, `no_found_rows=true`. Assertions on 20 / paged / false fail.

- [ ] **Step 3: Rewrite the query function**

Replace `inc/page-notes-render.php:99-108` (the whole `sn_notes_query_posts()` body) with:

```php
function sn_notes_query_posts() {
    return new WP_Query( array(
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'posts_per_page' => sn_notes_per_page(),
        'paged'          => sn_notes_current_page(),
        'orderby'        => 'date',
        'order'          => 'DESC',
        'no_found_rows'  => false, // pagination needs found_posts / max_num_pages
    ) );
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/notes-pagination.php`
Expected: PASS — all assertions across Tasks 1–3.

- [ ] **Step 5: Commit**

```bash
git add inc/page-notes-render.php tests/notes-pagination.php
git commit -m "feat(notes): paginate sn_notes_query_posts() — per-page + paged, no_found_rows=false"
```

---

## Task 4: Render the pagination control + fix the count display

**Files:**
- Modify: `inc/page-notes-render.php` — count header (line 617), after-`</ol>` control (after line 636), CSS (before line 546 `</style>`)

This task is render-body markup (not unit-testable headlessly) — verified by reading + a render smoke check. Keep the `paginate_links()` call thin; the logic already lives in tested helpers.

- [ ] **Step 1: Fix the count display to show the grand total**

`inc/page-notes-render.php:617` currently:

```php
<span class="sn-notes-section-count"><?php echo esc_html( sprintf( '%02d / %02d', $entry_count, $entry_count ) ); ?></span>
```

Replace with (uses `found_posts`, now available since `no_found_rows=false`):

```php
<span class="sn-notes-section-count"><?php echo esc_html( sprintf( '%02d', (int) $query->found_posts ) ); ?></span>
```

(`$query` is in scope at line 617 — it's assigned at line 111 `$query = sn_notes_query_posts();`. `$entry_count` stays untouched for the hero meta at line 568.)

- [ ] **Step 2: Add the pagination control after the index `</ol>`**

`inc/page-notes-render.php:636-639` currently:

```php
			</ol>
			<?php else : ?>
				<p class="sn-notes-empty">No notes published yet. Check back soon.</p>
			<?php endif; ?>
```

Replace with (control rendered only when there's more than one page; placed AFTER the `endif` so it sits below the list, inside the section):

```php
			</ol>
			<?php else : ?>
				<p class="sn-notes-empty">No notes published yet. Check back soon.</p>
			<?php endif; ?>

			<?php if ( $query->max_num_pages > 1 ) : ?>
				<nav class="sn-notes-pagination" aria-label="Notes pages">
					<?php
					$sn_notes_links = paginate_links( array(
						'base'      => esc_url_raw( add_query_arg( 'paged', '%#%', home_url( '/notes/' ) ) ),
						'format'    => '',
						'current'   => sn_notes_current_page(),
						'total'     => (int) $query->max_num_pages,
						'type'      => 'array',
						'prev_text' => '&larr;',
						'next_text' => '&rarr;',
						'mid_size'  => 2,
						'end_size'  => 1,
					) );
					if ( is_array( $sn_notes_links ) ) {
						foreach ( $sn_notes_links as $sn_link ) {
							// paginate_links() returns pre-escaped, controlled
							// <a>/<span> markup (WP core helper). Echo as-is;
							// wrapping in esc_html would mangle the anchors.
							echo $sn_link; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- paginate_links() output is trusted WP-core-generated markup.
						}
					}
					?>
				</nav>
			<?php endif; ?>
```

- [ ] **Step 3: Add the `.sn-notes-pagination` CSS**

In `inc/page-notes-render.php`, inside the inlined `<style>` block, insert BEFORE the `</style>` at line 546 (place it after the `.sn-notes-section-count` rule region so it reads in context). Match the existing brand vocabulary (DM Mono numerals, rust grey, 11px floor):

```css
.sn-notes-pagination {
	display: flex;
	flex-wrap: wrap;
	gap: 0.75rem;
	align-items: center;
	justify-content: center;
	margin-top: clamp(2rem, 5vw, 3.5rem);
	font-family: 'DM Mono', 'Courier New', monospace;
	font-size: max(0.8rem, 12px);
	letter-spacing: 0.1em;
}
.sn-notes-pagination a,
.sn-notes-pagination span {
	color: var(--wp--preset--color--rust, #666);
	text-decoration: none;
	padding: 0.25rem 0.5rem;
	min-width: 1.5rem;
	text-align: center;
}
.sn-notes-pagination a:hover,
.sn-notes-pagination a:focus {
	color: var(--wp--preset--color--void, #0a0a0a);
	text-decoration: underline;
	text-underline-offset: 0.25em;
}
.sn-notes-pagination .current {
	color: var(--wp--preset--color--void, #0a0a0a);
	font-weight: 700;
}
```

(No animated transitions → nothing to gate under `prefers-reduced-motion`; consistent with the file's static aesthetic.)

- [ ] **Step 4: Lint + run the full theme suite**

Run:
```bash
php -l inc/page-notes-render.php
for f in tests/*.php; do php "$f" 2>&1 | tail -1; done
```
Expected: "No syntax errors"; every suite `N passed, 0 failed` (notes-pagination among them). Aggregate should be the prior 361 + the new notes-pagination assertions.

- [ ] **Step 5: Verify PHPCS stays 0/0 (the file is in the EscapeOutput exclusion, but verify scanner is alive)**

Run:
```bash
composer install >/dev/null 2>&1
composer run lint
```
Expected: exit 0, 0/0. (Falsification-test if in doubt: temporarily add `echo $_GET['x'];` to a NON-excluded file, confirm phpcs reports it, then revert.)

- [ ] **Step 6: Commit**

```bash
git add inc/page-notes-render.php
git commit -m "feat(notes): paginate_links() control + grand-total count display + pagination CSS"
```

---

## Task 5: Bump the build marker

**Files:**
- Modify: `inc/page-notes-template.php:55`

- [ ] **Step 1: Bump `SN_NOTES_OVERRIDE_BUILD`**

`inc/page-notes-template.php:55` currently:

```php
const SN_NOTES_OVERRIDE_BUILD = '2026-05-08-compact-pillars-v9';
```

Change to:

```php
const SN_NOTES_OVERRIDE_BUILD = '2026-05-30-pagination-v10';
```

(This marker is the deploy-verification token — bumped on every commit touching /notes rendering, so `curl … | grep sn-notes-build` confirms the deploy took.)

- [ ] **Step 2: Lint**

Run: `php -l inc/page-notes-template.php`
Expected: "No syntax errors detected".

- [ ] **Step 3: Commit**

```bash
git add inc/page-notes-template.php
git commit -m "chore(notes): bump build marker for pagination deploy verification"
```

---

## Task 6: Release (version + CHANGELOG + readme) — DO NOT auto-ship

> **Gate:** Per the user's space-out-releases preference, do NOT bump/tag/push as part of execution unless explicitly told to ship. This task documents the release steps for when the user gives the go.

**Version decision:** new user-visible capability → **MINOR** per SemVer. Theme is at 9.5.2 → **v9.6.0**. (If the user prefers to fold this into the already-planned v9.6.0 prep-minor, coordinate — there is an existing `docs/superpowers/plans/2026-05-27-v9.6.0.md`. Resolve at ship time.)

- [ ] **Step 1: Bump `style.css` `Version:` 9.5.2 → 9.6.0**
- [ ] **Step 2: `readme.txt` `Stable tag: 9.5.2` → `9.6.0`** (the readme-drift class fixed twice this cycle — don't forget it).
- [ ] **Step 3: CHANGELOG entry** under `## [9.6.0] - <date> — /notes pagination`, Mimestream `### Added` / `### Changed` headers (Added: pagination + `sn_notes_per_page` filter; Changed: count display shows grand total, `no_found_rows` flipped).
- [ ] **Step 4: Verify triple alignment** (style.css == readme == CHANGELOG top) + tests green + PHPCS 0/0.
- [ ] **Step 5: Commit `vX.Y.Z: …`, push `origin HEAD:main`, annotated tag, push tag** — ONLY on the user's go. Tag push does NOT auto-deploy; install via wp-admin → Updates.

---

## Self-review notes (coverage vs spec Release 1)

- Spec 1A (query change) → Tasks 1–3. ✓
- Spec 1B (control) → Task 4 Step 2–3. ✓
- Spec 1C (count fix) → Task 4 Step 1. ✓
- Spec 1D (tests) → Tasks 1–3 (helpers + query args). Render-body markup is not headlessly testable — covered by lint + full-suite + manual render check (acknowledged limitation, stated in Architecture). ✓
- Spec 1E (build marker) → Task 5. ✓
- Spec Release 2 (plugin setting, section refactor, paged-SEO) → intentionally OUT of this plan. ✓

**Manual verification after Task 4** (since the control isn't unit-tested): with the default 20/page and 13 published, the control should NOT appear (max_num_pages = 1). To see it live before more notes publish, temporarily set the filter to a small value — e.g. `add_filter('sn_notes_per_page', fn() => 5);` in a scratch mu-plugin — load `/notes` (page 1 + control), `/notes/?paged=2`, confirm older notes + working links, then remove the scratch filter. This is also what Release 2's setting will drive.
