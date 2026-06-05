# On-site Search (FSE `search.html`) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking. When applying block markup (Tasks 2–3), also invoke the `gutenberg-block-authoring` skill and confirm no block-recovery/validation errors on the live front end (the search-aware results only render on the front end, not in the Site Editor — see Task 1 caveat).

**Goal:** Add an on-site search experience — an FSE `templates/search.html` that splits `/?s=` results into a **Notes** group (posts) and a **Pages** group, plus a header search trigger — backed by one tested PHP filter that makes the two custom Query Loops search-aware.

**Architecture:** Approach A (FSE-first). `search.html` holds two `wp:query` blocks with `inherit:false` (one `postType:post`, one `postType:page`). Core's `query_loop_block_query_vars` filter (verified to run only on the non-inherit path) injects the search term into each, guarded by `is_search()` + a post-type discriminator so it never bleeds `s` into unrelated custom queries. The header trigger is a pure-FSE `core/search` block in icon-expand mode. Only one new PHP file; everything else is block templates + CSS.

**Tech Stack:** PHP 8.0+, WordPress 6.4+ (FSE block templates, `query_loop_block_query_vars` since 6.1), core blocks (`query`, `post-template`, `query-no-results`, `query-title type:search`, `search` `buttonPosition:button-only`). No build step. Tests: `php tests/search-query.php` (CLI standalone fixture, WP functions stubbed — matches `tests/notes-pagination.php`).

**Spec:** [`docs/superpowers/specs/2026-06-05-on-site-search-design.md`](../specs/2026-06-05-on-site-search-design.md). WP-core primitives verified against source 2026-06-05 (see spec's primitives table).

**Version:** theme minor → **v9.7.0** (Task 5, gated — DO NOT auto-ship). Collision: the prep-minor also targets v9.7.0; search ships first → prep-minor moves to v9.8.0. Resolve at ship.

---

## File structure

| File | Responsibility | Change |
|---|---|---|
| `inc/search-query.php` | Inject `s` into the two search Query Loops (the only new PHP; the testable seam) | Create |
| `functions.php` | Module require manifest | Modify: add one `require_once` |
| `templates/search.html` | The grouped search results template (auto-resolved by WP for `is_search()`) | Create |
| `parts/header.html` | Site header — add the search trigger | Modify: wrap nav + new `core/search` in a right-aligned actions group |
| `assets/css/components.css` | Global front-end component styles | Modify: append search result/label/no-results + header-search rules |
| `tests/search-query.php` | Unit tests for the filter + discriminator | Create |
| `style.css`, `readme.txt`, `CHANGELOG.md` | Release records | Modify at ship (Task 5, gated) |

**Helper boundaries (the testable seam):**
- `sn_is_search_loop( $block ): bool` — pure discriminator: true only when the block's query context targets `post` or `page`. No globals.
- `sn_search_inject_term( $query, $block ): array` — the filter callback: returns `$query` unchanged unless `is_search()` AND `sn_is_search_loop($block)`, in which case it sets `$query['s'] = get_search_query( false )`.

---

## Task 1: The search-aware query filter (`inc/search-query.php`)

**Files:**
- Create: `inc/search-query.php`
- Create: `tests/search-query.php`
- Modify: `functions.php` (add require)

- [ ] **Step 0: Confirm the discriminator is exclusive on search pages**

The filter fires for EVERY `inherit:false` Query Loop site-wide; we guard with `is_search()` + post-type, so the only risk is a *foreign* `inherit:false` post/page Query Loop that also renders on a search page (i.e. in the header/footer parts). Verify there is none:

Run: `grep -n '"inherit":false\|wp:query' parts/header.html parts/footer.html`
Expected: no `wp:query` with `"inherit":false` in either part (header is nav-only; footer is a static template part). If one exists, escalate — the post-type discriminator is not exclusive and a `namespace`-based discriminator is needed instead (stop and revise).

- [ ] **Step 1: Write the failing test**

Create `tests/search-query.php`:

```php
<?php
/**
 * Standalone fixture tests for on-site search query-vars injection.
 *
 * Stubs is_search() + get_search_query() + add_filter so the pure
 * functions in inc/search-query.php run without a WP load.
 *
 * @since theme v9.7.0
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
$GLOBALS['__is_search']    = false;
$GLOBALS['__search_query'] = '';

if ( ! function_exists( 'is_search' ) ) {
	function is_search() {
		return (bool) $GLOBALS['__is_search'];
	}
}
if ( ! function_exists( 'get_search_query' ) ) {
	// Real signature is get_search_query( $escaped = true ); the filter
	// calls it with false to get the raw term. Stub returns raw either way.
	function get_search_query( $escaped = true ) {
		return $GLOBALS['__search_query'];
	}
}
if ( ! function_exists( 'add_filter' ) ) {
	// No-op: the file calls add_filter() at load; we invoke the callback directly.
	function add_filter( $hook, $cb, $priority = 10, $args = 1 ) {
		return true;
	}
}

require __DIR__ . '/../inc/search-query.php';

$pass = 0; $fail = 0;
function ok( $cond, $label ) {
	global $pass, $fail;
	if ( $cond ) { $pass++; echo "PASS: $label\n"; }
	else { $fail++; echo "FAIL: $label\n"; }
}

// Build a post-template-like block carrying the query context.
function sn_mk_block( $post_type ) {
	return (object) array( 'context' => array( 'query' => array( 'postType' => $post_type ) ) );
}

$base = array( 'post_type' => 'post', 'order' => 'DESC' );

// ── discriminator: sn_is_search_loop() ──
ok( sn_is_search_loop( sn_mk_block( 'post' ) ) === true,  'discriminator true for postType post' );
ok( sn_is_search_loop( sn_mk_block( 'page' ) ) === true,  'discriminator true for postType page' );
ok( sn_is_search_loop( sn_mk_block( '' ) ) === false,     'discriminator false for empty postType' );
ok( sn_is_search_loop( sn_mk_block( 'attachment' ) ) === false, 'discriminator false for other postType' );
ok( sn_is_search_loop( 'not-a-block' ) === false,         'discriminator false for non-object' );

// ── filter: sn_search_inject_term() ──
$GLOBALS['__is_search'] = false; $GLOBALS['__search_query'] = 'provenance';
$out = sn_search_inject_term( $base, sn_mk_block( 'post' ) );
ok( ! isset( $out['s'] ), 'no s injected when not a search page' );

$GLOBALS['__is_search'] = true;
$out = sn_search_inject_term( $base, sn_mk_block( 'post' ) );
ok( ( $out['s'] ?? null ) === 'provenance', 'post loop on search page gets s' );

$out = sn_search_inject_term( $base, sn_mk_block( 'page' ) );
ok( ( $out['s'] ?? null ) === 'provenance', 'page loop on search page gets s' );

$out = sn_search_inject_term( $base, sn_mk_block( 'attachment' ) );
ok( ! isset( $out['s'] ), 'non-post/page loop on a search page is untouched' );

$out = sn_search_inject_term( $base, sn_mk_block( 'post' ) );
ok( $out['post_type'] === 'post' && $out['order'] === 'DESC', 'existing query args preserved' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/search-query.php`
Expected: FAIL — fatal "Failed opening required '…/inc/search-query.php'" (file doesn't exist yet) or "Call to undefined function sn_is_search_loop()".

- [ ] **Step 3: Create the filter module**

Create `inc/search-query.php`:

```php
<?php
/**
 * Signal & Noise — on-site search: make the grouped Query Loops search-aware.
 *
 * templates/search.html renders two core/query blocks with inherit:false
 * (postType=post "Notes", postType=page "Pages"). A non-inherited Query
 * Loop is built from block attributes and does NOT read the ?s= term, so
 * we inject it here. WordPress runs query_loop_block_query_vars ONLY on the
 * inherit:false path (the inherit:true path uses the global $wp_query and
 * never calls the builder) — verified against wp-includes/blocks.php
 * build_query_vars_from_query_block() on 2026-06-05.
 *
 * Guarded by is_search() + a post-type discriminator so we never bleed the
 * search term into an unrelated custom Query Loop elsewhere on the site.
 *
 * @package SignalNoise
 * @since 9.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Is this Post Template block one of our search-results loops?
 * True only when its query context targets posts or pages.
 *
 * @param mixed $block The WP_Block (post-template) passed to the filter.
 * @return bool
 */
function sn_is_search_loop( $block ) {
	if ( ! is_object( $block ) ) {
		return false;
	}
	$post_type = $block->context['query']['postType'] ?? '';
	return in_array( $post_type, array( 'post', 'page' ), true );
}

/**
 * Inject the current search term into our grouped, non-inherited Query
 * Loops so they return search results instead of a generic post list.
 *
 * @param array $query The WP_Query args built from the block.
 * @param mixed $block The WP_Block instance (post-template).
 * @return array
 */
function sn_search_inject_term( $query, $block ) {
	if ( ! is_search() ) {
		return $query;
	}
	if ( ! sn_is_search_loop( $block ) ) {
		return $query;
	}
	$query['s'] = get_search_query( false ); // false = raw term, not display-escaped.
	return $query;
}
add_filter( 'query_loop_block_query_vars', 'sn_search_inject_term', 10, 2 );
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/search-query.php`
Expected: PASS — all 10 assertions, `Result: 10 passed, 0 failed.`

- [ ] **Step 5: Wire the module into the require manifest**

In `functions.php`, after the line `require_once __DIR__ . '/inc/frontend-filters.php';` (currently line 44), add:

```php
require_once __DIR__ . '/inc/search-query.php';
```

- [ ] **Step 6: Lint + full theme suite**

Run:
```bash
php -l inc/search-query.php
for f in tests/*.php; do echo "$(basename "$f"): $(php "$f" 2>&1 | tail -1)"; done
```
Expected: "No syntax errors"; every suite `N passed, 0 failed` (search-query among them). Aggregate = prior 377 + 10 = **387**.

- [ ] **Step 7: PHPCS 0/0 (falsification-guarded — we run inside a `.claude/worktrees/` path)**

Run:
```bash
composer run lint
```
Expected: files scanned (`N / N (100%)`), 0 errors / 0 warnings. If in doubt it's a false-green, inject `echo $_GET['x'];` into a temp `inc/__canary.php`, confirm phpcs reports it, then `rm` it and re-run.

- [ ] **Step 8: Commit**

```bash
git add inc/search-query.php tests/search-query.php functions.php
git commit -m "feat(search): query_loop_block_query_vars filter injects ?s= into grouped search loops (is_search + post-type guard)"
```

---

## Task 2: The `search.html` template

**Files:**
- Create: `templates/search.html`

This is block markup (validated on render, not headlessly). Invoke `gutenberg-block-authoring` while applying it.

- [ ] **Step 1: Create `templates/search.html`**

```html
<!-- wp:template-part {"slug":"header","area":"header"} /-->

<!-- wp:group {"tagName":"main","style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50","left":"var:preset|spacing|40","right":"var:preset|spacing|40"}}},"layout":{"type":"constrained"}} -->
<main class="wp-block-group" style="padding-top:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--50);padding-left:var(--wp--preset--spacing--40)">

	<!-- wp:query-title {"type":"search","level":1,"style":{"typography":{"fontSize":"clamp(2rem, 5vw, 3.5rem)"}},"fontFamily":"heading"} /-->

	<!-- wp:search {"showLabel":false,"placeholder":"Search again…","buttonText":"Search","buttonPosition":"button-outside","buttonUseIcon":true,"className":"sn-search-refine"} /-->

	<!-- wp:heading {"level":2,"className":"sn-search-section-label"} -->
	<h2 class="wp-block-heading sn-search-section-label">Notes — Results</h2>
	<!-- /wp:heading -->

	<!-- wp:query {"queryId":10,"query":{"perPage":100,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","inherit":false},"namespace":"signal-noise/search","className":"sn-search-results sn-search-results-notes"} -->
	<div class="wp-block-query sn-search-results sn-search-results-notes">

		<!-- wp:post-template {"layout":{"type":"default"}} -->

			<!-- wp:group {"className":"sn-search-row","style":{"spacing":{"padding":{"bottom":"var:preset|spacing|30"},"margin":{"bottom":"var:preset|spacing|30"}}},"layout":{"type":"constrained"}} -->
			<div class="wp-block-group sn-search-row" style="margin-bottom:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--30)">

				<!-- wp:post-date {"style":{"typography":{"fontSize":"0.75rem","letterSpacing":"0.15em","textTransform":"uppercase"}},"textColor":"rust","fontFamily":"body"} /-->

				<!-- wp:post-title {"level":3,"isLink":true,"style":{"typography":{"fontSize":"clamp(1.5rem, 2.4vw, 2rem)"},"elements":{"link":{"color":{"text":"var:preset|color|bone"},":hover":{"color":{"text":"var:preset|color|blood"}}}}},"fontFamily":"heading"} /-->

				<!-- wp:post-excerpt {"excerptLength":30,"style":{"typography":{"fontSize":"0.9rem"}},"textColor":"rust"} /-->

			</div>
			<!-- /wp:group -->

		<!-- /wp:post-template -->

		<!-- wp:query-no-results -->
			<!-- wp:paragraph {"className":"sn-search-no-results"} -->
			<p class="sn-search-no-results">No notes match.</p>
			<!-- /wp:paragraph -->
		<!-- /wp:query-no-results -->

	</div>
	<!-- /wp:query -->

	<!-- wp:heading {"level":2,"className":"sn-search-section-label"} -->
	<h2 class="wp-block-heading sn-search-section-label">Pages — Results</h2>
	<!-- /wp:heading -->

	<!-- wp:query {"queryId":11,"query":{"perPage":100,"pages":0,"offset":0,"postType":"page","order":"asc","orderBy":"title","inherit":false},"namespace":"signal-noise/search","className":"sn-search-results sn-search-results-pages"} -->
	<div class="wp-block-query sn-search-results sn-search-results-pages">

		<!-- wp:post-template {"layout":{"type":"default"}} -->

			<!-- wp:group {"className":"sn-search-row","style":{"spacing":{"padding":{"bottom":"var:preset|spacing|30"},"margin":{"bottom":"var:preset|spacing|30"}}},"layout":{"type":"constrained"}} -->
			<div class="wp-block-group sn-search-row" style="margin-bottom:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--30)">

				<!-- wp:post-title {"level":3,"isLink":true,"style":{"typography":{"fontSize":"clamp(1.5rem, 2.4vw, 2rem)"},"elements":{"link":{"color":{"text":"var:preset|color|bone"},":hover":{"color":{"text":"var:preset|color|blood"}}}}},"fontFamily":"heading"} /-->

				<!-- wp:post-excerpt {"excerptLength":30,"style":{"typography":{"fontSize":"0.9rem"}},"textColor":"rust"} /-->

			</div>
			<!-- /wp:group -->

		<!-- /wp:post-template -->

		<!-- wp:query-no-results -->
			<!-- wp:paragraph {"className":"sn-search-no-results"} -->
			<p class="sn-search-no-results">No pages match.</p>
			<!-- /wp:paragraph -->
		<!-- /wp:query-no-results -->

	</div>
	<!-- /wp:query -->

</main>
<!-- /wp:group -->

<!-- wp:template-part {"slug":"footer","area":"footer"} /-->
```

- [ ] **Step 2: Verify template resolution + no block-recovery errors**

On the live/staging site, load `/?s=provenance` and confirm: the page uses `search.html` (not `index.html` — check for the "Search results for:" heading + the two group labels), both groups render matching results, and there are NO block validation/recovery warnings in the console or markup. Then load `/?s=zzzznomatch` and confirm both groups show their "No notes/pages match." messages. (Reminder: results are correct only on the FRONT END — the Site Editor preview shows empty/generic groups because the filter is front-end only.)

- [ ] **Step 3: Commit**

```bash
git add templates/search.html
git commit -m "feat(search): grouped search.html — Notes + Pages results, query-title heading, refine field, per-group no-results"
```

---

## Task 3: Header search trigger (`parts/header.html`)

**Files:**
- Modify: `parts/header.html` — wrap the existing `wp:navigation` and a new `core/search` (icon mode) in a right-aligned actions group so the header keeps its logo-left / actions-right balance.

- [ ] **Step 1: Wrap nav + add the search icon**

Replace the existing navigation block (the whole `<!-- wp:navigation … -->` … `<!-- /wp:navigation -->` block, currently lines 14–22) with the navigation wrapped in an actions group followed by the search block:

```html
	<!-- wp:group {"className":"sn-header-actions","layout":{"type":"flex","flexWrap":"nowrap"}} -->
	<div class="wp-block-group sn-header-actions">

		<!-- wp:navigation {"overlayMenu":"mobile","style":{"typography":{"fontStyle":"normal","fontWeight":"400","letterSpacing":"0.14em","textTransform":"uppercase","fontSize":"1.125rem"},"spacing":{"blockGap":"var:preset|spacing|40"}},"fontFamily":"heading","layout":{"type":"flex"}} -->
			<!-- wp:navigation-link {"label":"Home","url":"/","kind":"custom","isTopLevelLink":true} /-->
			<!-- wp:navigation-link {"label":"About","url":"/about","kind":"custom","isTopLevelLink":true} /-->
			<!-- wp:navigation-link {"label":"Services","url":"/services","kind":"custom","isTopLevelLink":true} /-->
			<!-- wp:navigation-link {"label":"Music","url":"/music","kind":"custom","isTopLevelLink":true} /-->
			<!-- wp:navigation-link {"label":"Resume","url":"/resume","kind":"custom","isTopLevelLink":true} /-->
			<!-- wp:navigation-link {"label":"Notes","url":"/notes","kind":"custom","isTopLevelLink":true} /-->
			<!-- wp:navigation-link {"label":"Contact","url":"/contact","kind":"custom","isTopLevelLink":true} /-->
		<!-- /wp:navigation -->

		<!-- wp:search {"showLabel":false,"buttonText":"Search","buttonPosition":"button-only","buttonUseIcon":true,"className":"sn-header-search"} /-->

	</div>
	<!-- /wp:group -->
```

(Keep the existing `sn-site-title` logo group above this untouched, and the parent `sn-header` group's `space-between` layout intact, so logo stays left and this actions cluster sits right. `buttonPosition:"button-only"` is what makes the icon expand to an input on click; WP auto-enqueues the search Interactivity view module. Degrades to a non-expanding icon button if that module is blocked.)

- [ ] **Step 2: Verify header on the live site**

Load any page; confirm: logo left, nav + search-icon clustered right, no layout regression at desktop AND mobile (the nav `overlayMenu:"mobile"` hamburger still works). Click the search icon → input expands → submitting routes to `/?s=…` → `search.html`. Confirm no block-recovery error on the header part.

- [ ] **Step 3: Commit**

```bash
git add parts/header.html
git commit -m "feat(search): header search icon (core/search button-only) in a right-aligned actions group"
```

---

## Task 4: Search styling (`assets/css/components.css`)

**Files:**
- Modify: `assets/css/components.css` (global, enqueued via `inc/assets-frontend.php`; `sn-components` depends on `sn-layout`).

- [ ] **Step 1: Append the search rules**

Add to the end of `assets/css/components.css`:

```css
/* ── ON-SITE SEARCH ──────────────────────────────────────────────
   templates/search.html grouped results + header trigger. Brand
   vocabulary: DM Mono labels, Bebas titles (set inline in the block),
   concrete hairlines, 11px floor. Reduced-motion safe. */
.sn-search-section-label {
	font-family: 'DM Mono', 'Courier New', monospace;
	font-size: max(0.7rem, 11px);
	letter-spacing: 0.18em;
	text-transform: uppercase;
	color: var(--wp--preset--color--rust, #666);
	margin: clamp(2rem, 4vw, 3rem) 0 clamp(1rem, 2vw, 1.5rem);
	padding-bottom: 0.5rem;
	border-bottom: 1px solid var(--wp--preset--color--concrete, #d9d9d9);
}
.sn-search-row {
	border-bottom: 1px solid var(--wp--preset--color--concrete, #d9d9d9);
}
.sn-search-no-results {
	font-family: 'DM Mono', 'Courier New', monospace;
	font-size: 0.85rem;
	letter-spacing: 0.1em;
	text-transform: uppercase;
	color: var(--wp--preset--color--rust, #666);
	padding: 1rem 0;
}
.sn-header-actions {
	align-items: center;
	gap: var(--wp--preset--spacing--40, 1.5rem);
}
.sn-header-search .wp-block-search__button svg {
	fill: var(--wp--preset--color--bone, #000);
	transition: fill 0.2s ease;
}
.sn-header-search .wp-block-search__button:hover svg,
.sn-header-search .wp-block-search__button:focus svg {
	fill: var(--wp--preset--color--blood, #e00404);
}
@media (prefers-reduced-motion: reduce) {
	.sn-header-search .wp-block-search__button svg { transition: none; }
}
```

- [ ] **Step 2: Lint the stylesheet (sanity) + visual check**

Run: `php -l functions.php` (no-op guard) — then on the live site reload `/?s=provenance`: section labels are hairline DM-Mono uppercase, result rows have bottom hairlines, the header search icon is bone and turns blood on hover/focus. Confirm 11px floor holds and reduced-motion disables the icon transition.

- [ ] **Step 3: Commit**

```bash
git add assets/css/components.css
git commit -m "feat(search): brand styling for grouped results, section labels, no-results, header search icon"
```

---

## Task 5: Release (version + CHANGELOG + readme) — DO NOT auto-ship

> **Gate:** Per space-out-releases, do NOT bump/tag/push during execution unless explicitly told to ship.

**Version:** new user-visible capability → MINOR. Theme is at 9.6.0 → **v9.7.0**. Renumber the prep-minor plan to v9.8.0 (it also claims v9.7.0).

- [ ] **Step 1:** Bump `style.css` `Version: 9.6.0` → `9.7.0`.
- [ ] **Step 2:** `readme.txt` `Stable tag: 9.6.0` → `9.7.0`.
- [ ] **Step 3:** CHANGELOG `## [9.7.0] - <date> — On-site search`, Mimestream headers — **Added**: `search.html` grouped results (Notes + Pages), header search icon, `sn_search_inject_term` filter, `tests/search-query.php` (10 assertions, suite 377→387). **Note**: search-aware results render front-end only (filter not active in Site Editor).
- [ ] **Step 4:** Verify triple alignment (style.css == readme == CHANGELOG top) + full suite green + PHPCS 0/0.
- [ ] **Step 5:** Commit `v9.7.0: …`, push `origin HEAD:main`, annotated tag `v9.7.0`, push tag — ONLY on the user's go. Install via wp-admin → Updates (tag push does NOT auto-deploy).

---

## Self-review (coverage vs spec)

- Spec §"Header trigger" → Task 3. ✓
- Spec §"search.html" (query-title, refine, Notes group, Pages group, per-group no-results) → Task 2. ✓
- Spec §"inc/search-query.php" (filter + `is_search()` + discriminator) → Task 1 (Steps 0–5). ✓ Discriminator = post-type (verified-exclusive via Step 0 footer/header check); namespace retained on the query blocks for self-documentation/future use but not relied upon (avoids the unverified namespace-propagation assumption).
- Spec §"Styling" → Task 4. ✓ (All in `components.css` rather than split into `critical.css`; header icon may have a one-frame unstyled flash on first paint — acceptable, revisit if noticeable.)
- Spec §"Tests" → Task 1 Step 1 (10 assertions, headless). ✓
- Spec defaults (no pagination, per-group empty state, reading-time omitted) → reflected in Task 2 markup (no `query-pagination` blocks; `query-no-results` per group; no reading-time block). ✓
- Spec §"Version" v9.7.0 → Task 5. ✓
- Spec §Risks (filter over-reach, view-script dependency, editor-preview blindness, excerpt quality) → mitigated in Task 1 Step 0 (over-reach), noted in Task 3 (view-script graceful degradation) and Task 2 Step 2 (editor-preview blindness, UAT excerpt check). ✓
