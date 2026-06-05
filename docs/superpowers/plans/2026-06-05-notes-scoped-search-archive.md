# Notes-scoped Search & Archive Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Move on-site search out of the header and into `/notes`, scoped to Notes only (no Pages), as a searchable archive — removing the v9.7.0 header "black blob" and the global posts+pages search at the root.

**Architecture:** `/notes` is PHP-authoritative (`inc/page-notes-render.php`, short-circuited by `inc/page-notes-template.php`). We add a hand-rolled search `<form>` (no `core/search`, so no `.wp-element-button` chrome) and a `?s=` term injected into the existing `post_type=post` `WP_Query`. Two states on one page: browse (today's layout + field) and search (hero stays, pillars hide, catalog rows show matches + count + Clear). A `template_redirect` funnel sends any stray `/?s=` to `/notes/?s=`. The old global search template, its query-vars filter, and the header trigger are deleted.

**Tech Stack:** PHP, WordPress (`WP_Query`, `paginate_links` `add_args`, `template_redirect`, `wp_safe_redirect`), standalone CLI PHP test fixtures (no WP/DB), PHPCS/WPCS.

**Spec:** `docs/superpowers/specs/2026-06-05-notes-scoped-search-archive-design.md`

---

## File Structure

- **Modify** `inc/page-notes-render.php` — new pure helpers `sn_notes_search_term()`, `sn_notes_pagination_add_args()`; `s` injection in `sn_notes_query_posts()`; render-body two-state wiring; inlined CSS for the search field.
- **Modify** `inc/page-notes-template.php` — new pure helper `sn_notes_search_redirect_target()`; `template_redirect` funnel; build-marker bump.
- **Create** `tests/notes-search.php` — fixtures for the renderer search helpers.
- **Create** `tests/notes-redirect.php` — fixtures for the funnel helper.
- **Delete** `templates/search.html`, `inc/search-query.php`, `tests/search-query.php`.
- **Modify** `functions.php` — drop the `search-query.php` require + its docblock line.
- **Modify** `parts/header.html` — remove `core/search` + the `sn-header-actions` wrapper (nav becomes a direct child of `.sn-header`).
- **Modify** `assets/css/components.css` — remove `.sn-header-actions` / `.sn-header-search` rules.
- **Modify** `style.css` (version) + `CHANGELOG.md`.

---

## Task 1: Renderer search helpers (TDD)

**Files:**
- Test: `tests/notes-search.php` (create)
- Modify: `inc/page-notes-render.php` (add helpers above the `SN_NOTES_RENDER_TEST` guard at line 141; update `sn_notes_query_posts()` at lines 124-134)

- [ ] **Step 1: Write the failing test** — create `tests/notes-search.php`:

```php
<?php
/**
 * Standalone fixture tests for /notes search helpers (v9.8.0).
 *
 * Stubs the WP functions the search helpers touch so the pure helpers
 * in inc/page-notes-render.php run without a WP load. Mirrors the
 * pattern in tests/notes-pagination.php.
 *
 * @since theme v9.8.0
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
$GLOBALS['__filters']    = array();
$GLOBALS['__query_vars'] = array();

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
if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $v ) {
		return is_string( $v ) ? stripslashes( $v ) : $v;
	}
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $s ) {
		$s = (string) $s;
		$s = preg_replace( '/<[^>]*>/', '', $s );      // strip tags
		$s = preg_replace( '/[\r\n\t]+/', ' ', $s );   // collapse newlines/tabs
		return trim( preg_replace( '/ {2,}/', ' ', $s ) );
	}
}

$GLOBALS['__wpquery_args'] = null;
if ( ! class_exists( 'WP_Query' ) ) {
	class WP_Query {
		public $args;
		public function __construct( $args = array() ) {
			$GLOBALS['__wpquery_args'] = $args;
			$this->args = $args;
		}
	}
}

define( 'SN_NOTES_RENDER_TEST', true );
require __DIR__ . '/../inc/page-notes-render.php';

$pass = 0; $fail = 0;
function ok( $cond, $label ) {
	global $pass, $fail;
	if ( $cond ) { $pass++; echo "PASS: $label\n"; }
	else { $fail++; echo "FAIL: $label\n"; }
}

// ── sn_notes_search_term() ──
$GLOBALS['__query_vars'] = array(); unset( $_GET['s'] );
ok( sn_notes_search_term() === '', 'no term when nothing set' );

$GLOBALS['__query_vars'] = array( 's' => 'provenance' ); unset( $_GET['s'] );
ok( sn_notes_search_term() === 'provenance', 'reads get_query_var(s)' );

$GLOBALS['__query_vars'] = array(); $_GET['s'] = 'fingerprints';
ok( sn_notes_search_term() === 'fingerprints', 'falls back to $_GET[s]' );

$GLOBALS['__query_vars'] = array(); $_GET['s'] = '   ';
ok( sn_notes_search_term() === '', 'whitespace-only term -> empty (browse)' );

$GLOBALS['__query_vars'] = array(); $_GET['s'] = '  hello world  ';
ok( sn_notes_search_term() === 'hello world', 'trims surrounding whitespace, keeps inner space' );

$GLOBALS['__query_vars'] = array(); $_GET['s'] = '<b>x</b>';
ok( sn_notes_search_term() === 'x', 'strips tags via sanitize_text_field' );
unset( $_GET['s'] );

// ── sn_notes_query_posts(): s injection (Notes-only by construction) ──
$GLOBALS['__filters'] = array(); $GLOBALS['__query_vars'] = array(); unset( $_GET['s'], $_GET['paged'] );
sn_notes_query_posts();
ok( ! isset( $GLOBALS['__wpquery_args']['s'] ), 'no s in query args when browsing' );
ok( $GLOBALS['__wpquery_args']['post_type'] === 'post', 'post_type=post when browsing' );

$GLOBALS['__query_vars'] = array( 's' => 'provenance' );
sn_notes_query_posts();
ok( ( $GLOBALS['__wpquery_args']['s'] ?? null ) === 'provenance', 's injected when searching' );
ok( $GLOBALS['__wpquery_args']['post_type'] === 'post', 'still post_type=post (Notes-only)' );
ok( $GLOBALS['__wpquery_args']['post_status'] === 'publish', 'still publish-only when searching' );
$GLOBALS['__query_vars'] = array();

// ── sn_notes_pagination_add_args() ──
ok( sn_notes_pagination_add_args( '' ) === array(), 'no add_args when browsing' );
ok( sn_notes_pagination_add_args( 'provenance' ) === array( 's' => 'provenance' ), 'add_args carries s when searching' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/notes-search.php`
Expected: FATAL / FAIL — `Call to undefined function sn_notes_search_term()` (helpers not yet defined).

- [ ] **Step 3: Add the helpers** — in `inc/page-notes-render.php`, insert AFTER `sn_notes_query_posts()` (after the closing `}` at line 134) and BEFORE the `// Under test …` comment at line 136:

```php
/**
 * Resolve the current Notes search term, if any. Mirrors
 * sn_notes_current_page(): reads WP's `s` query var, falling back to the
 * raw ?s= query-string param (the short-circuit router may not populate
 * the query var cleanly). Unslashed, tag-stripped, trimmed. Returns ''
 * when absent or whitespace-only (= browse mode). The empty short-circuit
 * means sanitize_text_field/wp_unslash are only touched when a term
 * exists — keeping the pagination fixtures (which don't stub them) green.
 */
function sn_notes_search_term() {
	$term = (string) get_query_var( 's' );
	if ( '' === $term && isset( $_GET['s'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only search index, no state change.
		$term = (string) wp_unslash( $_GET['s'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}
	if ( '' === $term ) {
		return '';
	}
	return trim( sanitize_text_field( $term ) );
}

/**
 * The extra query args paginate_links() must carry so search-result page
 * 2+ stays inside the search. Empty array when browsing.
 */
function sn_notes_pagination_add_args( $term = '' ) {
	return ( '' !== $term ) ? array( 's' => $term ) : array();
}
```

- [ ] **Step 4: Inject the term into the query** — replace `sn_notes_query_posts()` (lines 124-134) with:

```php
function sn_notes_query_posts() {
	$args = array(
		'post_type'      => 'post',
		'post_status'    => 'publish',
		'posts_per_page' => sn_notes_per_page(),
		'paged'          => sn_notes_current_page(),
		'orderby'        => 'date',
		'order'          => 'DESC',
		'no_found_rows'  => false, // pagination needs found_posts / max_num_pages
	);
	// Notes-only by construction (post_type=post = the whole Notes corpus;
	// Pages are never queried here). Add the search term only when present.
	$term = sn_notes_search_term();
	if ( '' !== $term ) {
		$args['s'] = $term;
	}
	return new WP_Query( $args );
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php tests/notes-search.php`
Expected: PASS — `Result: 12 passed, 0 failed.`

- [ ] **Step 6: Verify no regression in the pagination fixture**

Run: `php tests/notes-pagination.php`
Expected: PASS — still `13 passed, 0 failed` (browse term short-circuits before `sanitize_text_field`, so the un-stubbed fixture is unaffected).

- [ ] **Step 7: Commit**

```bash
git add tests/notes-search.php inc/page-notes-render.php
git commit -m "feat(notes): Notes-scoped search query helpers (term + add_args)"
```

---

## Task 2: `/?s=` → `/notes/?s=` funnel (TDD) + build marker

**Files:**
- Test: `tests/notes-redirect.php` (create)
- Modify: `inc/page-notes-template.php` (bump marker at line 55; add helper + hook)

- [ ] **Step 1: Write the failing test** — create `tests/notes-redirect.php`:

```php
<?php
/**
 * Standalone fixture tests for the /?s= -> /notes/?s= search funnel
 * helper in inc/page-notes-template.php (v9.8.0).
 *
 * inc/page-notes-template.php registers hooks at load, so we stub the
 * registrars (add_action/add_filter) as no-ops and exercise the pure
 * URL-builder directly.
 *
 * @since theme v9.8.0
 */

// SECURITY: Prevent web access. Test fixture, not a runtime module.
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) {
	http_response_code( 404 );
	exit;
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/' );
}

if ( ! function_exists( 'add_action' ) ) { function add_action() { return true; } }
if ( ! function_exists( 'add_filter' ) ) { function add_filter() { return true; } }
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $s ) { return trim( preg_replace( '/<[^>]*>/', '', (string) $s ) ); }
}
if ( ! function_exists( 'home_url' ) ) {
	function home_url( $path = '' ) { return 'https://example.test' . $path; }
}
if ( ! function_exists( 'add_query_arg' ) ) {
	// Minimal stub: WP's real add_query_arg does NOT urlencode values
	// (urlencode=false), so the helper pre-encodes with rawurlencode().
	function add_query_arg( $key, $value, $url ) {
		$sep = ( strpos( $url, '?' ) === false ) ? '?' : '&';
		return $url . $sep . $key . '=' . $value;
	}
}

require __DIR__ . '/../inc/page-notes-template.php';

$pass = 0; $fail = 0;
function ok( $cond, $label ) {
	global $pass, $fail;
	if ( $cond ) { $pass++; echo "PASS: $label\n"; }
	else { $fail++; echo "FAIL: $label\n"; }
}

ok( sn_notes_search_redirect_target( '' ) === 'https://example.test/notes/', 'empty term -> bare /notes/' );
ok( sn_notes_search_redirect_target( 'provenance' ) === 'https://example.test/notes/?s=provenance', 'term -> /notes/?s=term' );
ok( strpos( sn_notes_search_redirect_target( 'hello world' ), 's=hello%20world' ) !== false, 'multi-word term is url-encoded' );
ok( strpos( sn_notes_search_redirect_target( '<b>x</b>' ), 's=x' ) !== false, 'tags stripped before redirect' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/notes-redirect.php`
Expected: FATAL / FAIL — `Call to undefined function sn_notes_search_redirect_target()`.

- [ ] **Step 3: Bump the build marker** — in `inc/page-notes-template.php`, change line 55:

```php
const SN_NOTES_OVERRIDE_BUILD = '2026-06-05-notes-search-v11';
```

- [ ] **Step 4: Add the helper + funnel hook** — in `inc/page-notes-template.php`, insert AFTER the `pre_get_document_title` filter block (after its closing `}, 999 );` at line 164) and BEFORE the `admin_init` wp_template sweep (line 166):

```php
/**
 * Build the canonical Notes-search URL for a term. Empty term -> /notes/.
 * rawurlencode the term because WP's add_query_arg() does NOT urlencode
 * values (urlencode=false), so a multi-word term would otherwise produce
 * an invalid URL.
 */
function sn_notes_search_redirect_target( $term ) {
	$term = trim( sanitize_text_field( (string) $term ) );
	$url  = home_url( '/notes/' );
	if ( '' !== $term ) {
		$url = add_query_arg( 's', rawurlencode( $term ), $url );
	}
	return $url;
}

/**
 * Funnel any site-wide search (?s=) to the single Notes search surface
 * (v9.8.0). There is exactly one search UI on the site — the /notes
 * archive — so a raw ?s= anywhere (old bookmarks, browser search
 * providers, a leftover templates/search.html URL) is redirected there.
 *
 * Priority 1: AFTER the notes-render short-circuit (priority 0, which
 * already exits for the /notes index) and BEFORE redirect_canonical
 * (priority 10). Skips admin, REST, feeds, and the /notes index itself
 * (defensive — that branch has already exited) so there is no loop.
 */
add_action( 'template_redirect', function() {
	if ( is_admin() || is_feed() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return;
	}
	if ( ! is_search() ) {
		return;
	}
	if ( sn_notes_is_index_request() ) {
		return; // already the /notes surface; its renderer handles ?s=.
	}
	wp_safe_redirect( sn_notes_search_redirect_target( get_search_query( false ) ), 302 );
	exit;
}, 1 );
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php tests/notes-redirect.php`
Expected: PASS — `Result: 4 passed, 0 failed.`

- [ ] **Step 6: Commit**

```bash
git add tests/notes-redirect.php inc/page-notes-template.php
git commit -m "feat(notes): funnel site-wide /?s= to the /notes search surface"
```

---

## Task 3: `/notes` render-body two-state wiring + search-field CSS

No new unit tests (markup/CSS — verified live in Task 7). Uses the Task 1 helpers.

**Files:**
- Modify: `inc/page-notes-render.php` (inlined `<style>`; `<main>` body)

- [ ] **Step 1: Add the search-field CSS** — in `inc/page-notes-render.php`, insert this block immediately AFTER the `.sn-notes-empty { … }` rule (ends at line 525) and BEFORE the `/* SUBSCRIBE NOTE … */` comment:

```css
	/* SEARCH FORM ─────────────────────────────────────────────────
	   Hand-rolled (no core/search → no .wp-element-button chrome, so no
	   black-pill "blob"). Thin underline field in the catalog vocabulary:
	   bone text, rust uppercase placeholder, blood on submit hover. */
	.sn-notes-search {
		display: flex;
		align-items: center;
		gap: 0.5rem;
		margin-bottom: clamp(1.5rem, 3vw, 2.25rem);
		border-bottom: 1px solid var(--wp--preset--color--concrete, #d9d9d9);
		transition: border-color 0.2s ease;
	}
	.sn-notes-search:focus-within {
		border-bottom-color: var(--wp--preset--color--bone, #000);
	}
	.sn-notes-search input[type="search"] {
		flex: 1 1 auto;
		min-width: 0;
		border: 0;
		background: transparent;
		padding: 0.65rem 0;
		font-family: 'DM Mono', 'Courier New', monospace;
		font-size: max(0.9rem, 12px);
		letter-spacing: 0.04em;
		color: var(--wp--preset--color--bone, #000);
		-webkit-appearance: none;
		appearance: none;
	}
	.sn-notes-search input[type="search"]:focus { outline: none; }
	.sn-notes-search input[type="search"]::placeholder {
		color: var(--wp--preset--color--rust, #666);
		text-transform: uppercase;
		letter-spacing: 0.16em;
		font-size: 0.78em;
	}
	.sn-notes-search button {
		flex: 0 0 auto;
		border: 0;
		background: transparent;
		padding: 0 0.4rem;
		cursor: pointer;
		color: var(--wp--preset--color--bone, #000);
		font-family: 'DM Mono', 'Courier New', monospace;
		font-size: 1.15rem;
		line-height: 1;
		transition: color 0.2s ease, transform 0.2s ease;
	}
	.sn-notes-search button:hover,
	.sn-notes-search button:focus {
		color: var(--wp--preset--color--blood, #e00404);
		transform: translateX(2px);
	}

	/* Search state: hero spans full width (pillars hidden). Specificity
	   (0,2,0) beats the .sn-notes-top media rule (0,1,0) at all widths. */
	.sn-notes-top.is-search { grid-template-columns: 1fr; }

	/* Clear link replaces the count in the section header during search. */
	.sn-notes-section-clear {
		font-family: 'DM Mono', 'Courier New', monospace;
		font-size: max(0.7rem, 11px);
		letter-spacing: 0.18em;
		text-transform: uppercase;
		color: var(--wp--preset--color--rust, #666);
		text-decoration: none;
		transition: color 0.2s ease;
	}
	.sn-notes-section-clear:hover,
	.sn-notes-section-clear:focus {
		color: var(--wp--preset--color--blood, #e00404);
	}

	/* Result-count line under the search header. */
	.sn-notes-search-summary {
		font-family: 'DM Mono', 'Courier New', monospace;
		font-size: max(0.7rem, 11px);
		letter-spacing: 0.18em;
		text-transform: uppercase;
		color: var(--wp--preset--color--rust, #666);
		margin: 0 0 clamp(1.25rem, 2.5vw, 1.75rem);
	}

	/* Visually-hidden label. Scoped + self-contained — /notes inlines all
	   its own CSS, so we don't rely on a global .screen-reader-text. */
	.sn-notes-page .screen-reader-text {
		position: absolute !important;
		width: 1px;
		height: 1px;
		padding: 0;
		margin: -1px;
		overflow: hidden;
		clip: rect(0, 0, 0, 0);
		white-space: nowrap;
		border: 0;
	}
```

- [ ] **Step 2: Disable the new motion under prefers-reduced-motion** — in the existing `@media (prefers-reduced-motion: reduce)` block (lines 581-587), add two lines before its closing `}`:

```css
		.sn-notes-search { transition: none; }
		.sn-notes-search button { transition: none; transform: none; }
```

- [ ] **Step 3: Compute the search state** — in `inc/page-notes-render.php`, find `$query = sn_notes_query_posts();` (line 146) and insert two lines right after it:

```php
$query = sn_notes_query_posts();
$sn_term      = sn_notes_search_term();
$sn_searching = ( '' !== $sn_term );
$entry_count = (int) $query->post_count;
```

- [ ] **Step 4: Make the hero full-width + hide pillars during search** — replace the `.sn-notes-top` opening + the pillars section + the rule (lines 638-689, from `<div class="sn-notes-top">` through `<hr class="sn-notes-rule" aria-hidden="true">`) with:

```php
		<div class="sn-notes-top<?php echo $sn_searching ? ' is-search' : ''; ?>">

			<header class="sn-notes-hero">
				<p class="sn-notes-eyebrow">Index &middot; Vol. 01 &middot; <?php echo esc_html( wp_date( 'Y' ) ); ?></p>
				<h1 class="sn-notes-headline">Notes.</h1>
				<p class="sn-notes-dek">Working notes on music, AI, and the infrastructure underneath. Written when there&rsquo;s something worth writing.</p>
				<p class="sn-notes-meta">
					<span><?php echo esc_html( sprintf( _n( '%d entry', '%d entries', $entry_count, 'signal-noise' ), $entry_count ) ); ?></span>
					<?php if ( $latest_date ) : ?>
						<span class="sn-notes-meta-bullet" aria-hidden="true">&middot;</span>
						<span>Last updated <?php echo esc_html( $latest_date ); ?></span>
					<?php endif; ?>
				</p>
				<p class="sn-notes-subscribe">
					No subscription form. No schedule. Notes via <a href="/notes/feed/">RSS</a>, or via email through <a href="https://blogtrottr.com/" target="_blank" rel="noopener noreferrer">Blogtrottr</a> or <a href="https://www.feedrabbit.com/" target="_blank" rel="noopener noreferrer">Feedrabbit</a>.<span class="sn-notes-cursor" aria-hidden="true"></span>
				</p>
			</header>

			<?php if ( ! $sn_searching ) : ?>
			<section class="sn-notes-pillars-section" aria-labelledby="sn-pillars-heading">
				<div class="sn-notes-section-wrap">
					<p class="sn-notes-section-label" id="sn-pillars-heading">Pillar Essays &mdash; Featured</p>
					<span class="sn-notes-section-count">02 / 02</span>
				</div>

				<div class="sn-notes-pillars">

					<article class="sn-notes-pillar">
						<span class="sn-notes-pillar-number" aria-hidden="true">&#8470; 01</span>
						<div class="sn-notes-pillar-body">
							<p class="sn-notes-pillar-eyebrow">Pillar Essay &middot; March 2026 &middot; <?php echo esc_html( sn_notes_reading_time_for_slug( 'provenance/over-detection' ) ); ?></p>
							<h2 class="sn-notes-pillar-title">Provenance Over Detection</h2>
							<p class="sn-notes-pillar-dek">Detection chases what isn&rsquo;t. Provenance proves what is.</p>
							<a class="sn-notes-pillar-cta" href="/provenance/over-detection/">Read essay</a>
						</div>
					</article>

					<article class="sn-notes-pillar">
						<span class="sn-notes-pillar-number" aria-hidden="true">&#8470; 02</span>
						<div class="sn-notes-pillar-body">
							<p class="sn-notes-pillar-eyebrow">Pillar Essay &middot; May 2026 &middot; <?php echo esc_html( sn_notes_reading_time_for_slug( 'provenance/as-substrate' ) ); ?></p>
							<h2 class="sn-notes-pillar-title">Provenance as Substrate</h2>
							<p class="sn-notes-pillar-dek">Music files need fingerprints, not name tags.</p>
							<a class="sn-notes-pillar-cta" href="/provenance/as-substrate/">Read essay</a>
						</div>
					</article>

				</div>
			</section>
			<?php endif; ?>

		</div>

		<?php if ( ! $sn_searching ) : ?>
		<hr class="sn-notes-rule" aria-hidden="true">
		<?php endif; ?>
```

- [ ] **Step 5: Add the form + branch the index section** — replace the entire `<section class="sn-notes-index-section">…</section>` block (lines 691-743) with:

```php
		<section class="sn-notes-index-section" aria-labelledby="sn-index-heading">

			<form class="sn-notes-search" role="search" method="get" action="<?php echo esc_url( home_url( '/notes/' ) ); ?>">
				<label class="screen-reader-text" for="sn-notes-q">Search notes</label>
				<input type="search" id="sn-notes-q" name="s" value="<?php echo esc_attr( $sn_term ); ?>" placeholder="Search notes" autocomplete="off" />
				<button type="submit" aria-label="Search"><span aria-hidden="true">&rarr;</span></button>
			</form>

			<div class="sn-notes-section-wrap">
				<?php if ( $sn_searching ) : ?>
					<p class="sn-notes-section-label" id="sn-index-heading">Notes &mdash; Search &middot; &ldquo;<?php echo esc_html( $sn_term ); ?>&rdquo;</p>
					<a class="sn-notes-section-clear" href="<?php echo esc_url( home_url( '/notes/' ) ); ?>">Clear &times;</a>
				<?php else : ?>
					<p class="sn-notes-section-label" id="sn-index-heading">Notes &mdash; Index</p>
					<span class="sn-notes-section-count"><?php echo esc_html( sprintf( '%02d', (int) $query->found_posts ) ); ?></span>
				<?php endif; ?>
			</div>

			<?php if ( $sn_searching ) : ?>
				<p class="sn-notes-search-summary"><?php echo esc_html( sprintf( _n( '%d note found', '%d notes found', (int) $query->found_posts, 'signal-noise' ), (int) $query->found_posts ) ); ?></p>
			<?php endif; ?>

			<?php if ( $query->have_posts() ) : ?>
				<ol class="sn-notes-index-list">
				<?php while ( $query->have_posts() ) : $query->the_post(); $p = get_post(); ?>
					<li class="sn-notes-row">
						<div class="sn-notes-row-spec" aria-hidden="false">
							<time class="sn-notes-row-date" datetime="<?php echo esc_attr( get_the_date( 'c', $p ) ); ?>"><?php echo sn_notes_render_date( $p ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper returns esc_html()'d output (see sn_notes_render_date); escaping again would double-encode. ?></time>
							<span class="sn-notes-row-rt"><?php echo esc_html( sn_notes_render_reading_time( $p->ID ) ); ?></span>
						</div>
						<div class="sn-notes-row-content">
							<h3 class="sn-notes-row-title"><a href="<?php the_permalink(); ?>"><?php echo esc_html( get_the_title() ); ?></a></h3>
							<?php $excerpt = get_the_excerpt(); if ( $excerpt ) : ?>
								<p class="sn-notes-row-excerpt"><?php echo esc_html( wp_strip_all_tags( $excerpt ) ); ?></p>
							<?php endif; ?>
						</div>
					</li>
				<?php endwhile; wp_reset_postdata(); ?>
				</ol>
			<?php elseif ( $sn_searching ) : ?>
				<p class="sn-notes-empty">No notes match &ldquo;<?php echo esc_html( $sn_term ); ?>&rdquo;.</p>
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
						'add_args'  => sn_notes_pagination_add_args( $sn_term ),
						'type'      => 'array',
						'prev_text' => '&larr;',
						'next_text' => '&rarr;',
						'mid_size'  => 2,
						'end_size'  => 1,
					) );
					if ( is_array( $sn_notes_links ) ) {
						foreach ( $sn_notes_links as $sn_link ) {
							// paginate_links() returns pre-escaped, controlled
							// <a>/<span> markup (WP core helper). Echo as-is.
							echo $sn_link; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- paginate_links() output is trusted WP-core-generated markup.
						}
					}
					?>
				</nav>
			<?php endif; ?>
		</section>
```

- [ ] **Step 6: Lint the changed file**

Run: `composer run lint -- inc/page-notes-render.php`
Expected: no errors (0 violations). Fix any (yoda conditions, escaping, spacing) before committing.

- [ ] **Step 7: PHP syntax check**

Run: `php -l inc/page-notes-render.php`
Expected: `No syntax errors detected`.

- [ ] **Step 8: Commit**

```bash
git add inc/page-notes-render.php
git commit -m "feat(notes): two-state /notes — browse + Notes-scoped search archive"
```

---

## Task 4: Remove the global posts+pages search surfaces

**Files:**
- Delete: `templates/search.html`, `inc/search-query.php`, `tests/search-query.php`
- Modify: `functions.php` (remove line 48 require + line 12 docblock)

- [ ] **Step 1: Delete the three files**

```bash
git rm templates/search.html inc/search-query.php tests/search-query.php
```

- [ ] **Step 2: Remove the require from `functions.php`** — delete line 48 exactly:

```php
require_once __DIR__ . '/inc/search-query.php';
```

- [ ] **Step 3: Remove the docblock line from `functions.php`** — delete line 12 exactly:

```php
 *   inc/search-query.php         — injects ?s= into the grouped /search Query Loops (v9.7.0)
```

- [ ] **Step 4: Confirm no dangling references**

Run: `grep -rn "search-query\|templates/search\|sn_search_inject_term\|sn_is_search_loop" --include="*.php" .`
Expected: no matches (all references removed).

- [ ] **Step 5: PHP syntax + full test sweep**

Run: `php -l functions.php && for f in tests/*.php; do php "$f" >/dev/null && echo "OK $f" || echo "FAIL $f"; done`
Expected: `No syntax errors detected`; every `tests/*.php` prints `OK` (note `tests/search-query.php` is gone).

- [ ] **Step 6: Commit**

```bash
git add functions.php
git commit -m "refactor(search): remove global posts+pages search (Notes-only now)"
```

---

## Task 5: Revert the v9.7.0 header search trigger

**Files:**
- Modify: `parts/header.html` (remove `core/search` + `sn-header-actions` wrapper)
- Modify: `assets/css/components.css` (remove `.sn-header-actions` / `.sn-header-search` rules, lines 597-611)

- [ ] **Step 1: Restore the pre-v9.7.0 header** — replace the entire contents of `parts/header.html` with:

```html
<!-- wp:group {"className":"sn-header","style":{"spacing":{"padding":{"top":"var:preset|spacing|20","bottom":"var:preset|spacing|20","left":"var:preset|spacing|40","right":"var:preset|spacing|40"}}},"backgroundColor":"void","layout":{"type":"flex","justifyContent":"space-between","flexWrap":"wrap"}} -->
<div class="wp-block-group sn-header has-void-background-color has-background" style="padding-top:var(--wp--preset--spacing--20);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--20);padding-left:var(--wp--preset--spacing--40)">

	<!-- wp:group {"className":"sn-site-title","layout":{"type":"flex","flexWrap":"nowrap","verticalAlignment":"center"}} -->
	<div class="wp-block-group sn-site-title">
		<!-- wp:html -->
		<a href="/" class="sn-logo-link" aria-label="Juan Lentino — Home">
			<img class="sn-logo-img" src="/wp-content/uploads/2026/02/cropped-jl_logo-min-150x150.png" srcset="/wp-content/uploads/2026/02/cropped-jl_logo-min-150x150.png 1x, /wp-content/uploads/2026/02/cropped-jl_logo-min-300x300.png 2x" alt="Juan Lentino logo" width="80" height="80" loading="eager" fetchpriority="high">
		</a>
		<!-- /wp:html -->
	</div>
	<!-- /wp:group -->

	<!-- wp:navigation {"overlayMenu":"mobile","style":{"typography":{"fontStyle":"normal","fontWeight":"400","letterSpacing":"0.14em","textTransform":"uppercase","fontSize":"1.125rem"},"spacing":{"blockGap":"var:preset|spacing|40"}},"fontFamily":"heading","layout":{"type":"flex"}} -->
		<!-- wp:navigation-link {"label":"Home","url":"/","kind":"custom","isTopLevelLink":true} /-->
		<!-- wp:navigation-link {"label":"About","url":"/about","kind":"custom","isTopLevelLink":true} /-->
		<!-- wp:navigation-link {"label":"Services","url":"/services","kind":"custom","isTopLevelLink":true} /-->
		<!-- wp:navigation-link {"label":"Music","url":"/music","kind":"custom","isTopLevelLink":true} /-->
		<!-- wp:navigation-link {"label":"Resume","url":"/resume","kind":"custom","isTopLevelLink":true} /-->
		<!-- wp:navigation-link {"label":"Notes","url":"/notes","kind":"custom","isTopLevelLink":true} /-->
		<!-- wp:navigation-link {"label":"Contact","url":"/contact","kind":"custom","isTopLevelLink":true} /-->
	<!-- /wp:navigation -->

</div>
<!-- /wp:group -->
```

- [ ] **Step 2: Remove the header search CSS** — in `assets/css/components.css`, delete the block at lines 597-611 (from `.sn-header-actions {` through the closing `}` of the `@media (prefers-reduced-motion: reduce)` rule that targets `.sn-header-search`). The exact current block to remove:

```css
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

- [ ] **Step 3: Confirm no dangling references**

Run: `grep -rn "sn-header-search\|sn-header-actions" --include="*.html" --include="*.css" --include="*.php" .`
Expected: no matches.

- [ ] **Step 4: Commit**

```bash
git add parts/header.html assets/css/components.css
git commit -m "revert(header): drop v9.7.0 core/search trigger + sn-header-* CSS (black blob)"
```

---

## Task 6: Version bump v9.8.0 + CHANGELOG

**Files:**
- Modify: `style.css` (line `Version: 9.7.0`)
- Modify: `CHANGELOG.md` (new top entry)

- [ ] **Step 1: Bump the theme version** — in `style.css`, change:

```
Version: 9.7.0
```
to:
```
Version: 9.8.0
```

- [ ] **Step 2: Add the CHANGELOG entry** — in `CHANGELOG.md`, insert AFTER the header line `All notable changes to Signal & Noise are documented here.` (line 3) and BEFORE `## [9.7.0]`:

```markdown
## [9.8.0] - 2026-06-05 — Notes-scoped search & archive

**Released:** 2026-06-05.

**Headline:** Search now lives inside the Notes archive, scoped to Notes only — no more header search icon. The v9.7.0 header `core/search` trigger rendered as a solid black "blob" (it dragged the theme's `.wp-element-button` chrome — a black icon on a black pill) and, more fundamentally, search didn't belong in the header. This release removes that trigger and the global Notes+Pages results template, and rebuilds search as a hand-rolled field on `/notes`: the index page becomes a searchable archive. `/notes/?s=term` runs a `post_type=post` query (Notes only — Pages excluded by construction), shows results in the existing catalog rows with a count and a Clear link, hides the pillar essays to focus, and paginates within the search. Any stray site-wide `/?s=` is funnelled to `/notes/?s=` so there is exactly one search surface.

### Added

- **Notes archive search** — a hand-rolled `<form role="search">` on `/notes` (no `core/search` block, so no `.wp-element-button` blob). Browse state shows the field above the index; search state hides the pillar essays + divider, echoes the query, shows a result count + Clear link, and renders matches in catalog rows with a branded empty state. (`inc/page-notes-render.php`)
- **`sn_notes_search_term()` / `sn_notes_pagination_add_args()`** — pure, unit-tested helpers; `sn_notes_query_posts()` now injects `s` when a term is present. (`inc/page-notes-render.php`)
- **`/?s=` → `/notes/?s=` funnel** — `sn_notes_search_redirect_target()` + a `template_redirect` (priority 1) redirect so all search lands on the single Notes surface. (`inc/page-notes-template.php`)
- **`tests/notes-search.php`, `tests/notes-redirect.php`** — standalone fixtures for the new helpers.

### Removed

- **Header search trigger** — the `core/search` block + the `sn-header-actions` wrapper in `parts/header.html` (nav returns to a direct child of `.sn-header`); the `.sn-header-search` / `.sn-header-actions` CSS in `assets/css/components.css`.
- **Global posts+pages search** — `templates/search.html`, `inc/search-query.php` (+ its `functions.php` require and `tests/search-query.php`). Search is Notes-only now.

### Fixed

- **Header "black blob"** — the v9.7.0 search icon rendered as a solid black pill (black icon on the `.wp-element-button` black background). Removed at the root by dropping the block entirely.
```

- [ ] **Step 3: Commit**

```bash
git add style.css CHANGELOG.md
git commit -m "v9.8.0: Notes-scoped search & archive; remove header blob + global search"
```

---

## Task 7: Verification (headless + live render)

No code changes — this is the gate. The v9.7.0 blob passed every headless check and still broke live, so live render is **mandatory**.

- [ ] **Step 1: Full headless test sweep** (mirrors CI)

Run:
```bash
fail=0; for f in tests/*.php; do
  out=$(php "$f" 2>&1)
  echo "$out" | grep -qE "[0-9]+ passed, 0 failed" && echo "OK  $f" || { echo "FAIL $f"; echo "$out" | tail -3; fail=1; }
done; echo "sweep exit: $fail"
```
Expected: every file `OK`, `sweep exit: 0`. (`tests/notes-search.php` 12/0, `tests/notes-redirect.php` 4/0, `tests/notes-pagination.php` 13/0, all others unchanged.)

- [ ] **Step 2: PHPCS lint (both changed PHP files + repo)**

Run: `composer run lint`
Expected: 0 errors / 0 warnings. (Falsification check per [[feedback_falsification_test_before_trusting_clean]]: confirm phpcs.xml.dist still scans `inc/` + `tests/` — not silently excluding via a path glob.)

- [ ] **Step 3: PHP syntax check all touched files**

Run: `php -l inc/page-notes-render.php && php -l inc/page-notes-template.php && php -l functions.php`
Expected: `No syntax errors detected` for each.

- [ ] **Step 4: LIVE render verification** (Studio local site if available; else `Claude_in_Chrome` / `curl` against the deploy). Confirm with eyes/markup, not just tests:
  - `/notes/` (browse): search field renders as a **thin underline field, NO black pill**; catalog layout intact; field placeholder "Search notes".
  - `/notes/?s=provenance` (known match): hero present, **pillars hidden**, heading `Notes — Search · "provenance"`, a result count, a working **Clear ×** link back to `/notes/`, matches in catalog rows.
  - `/notes/?s=zzzznotarealterm`: branded empty state `No notes match "…".`
  - `/notes/?s=` (empty): renders the browse state (no "0 results" noise).
  - `/?s=provenance` (any non-notes URL): **302 redirects** to `/notes/?s=provenance`.
  - Header on `/` and a single post: **no search blob**, nav right-aligned (logo left).
  - If the archive has >20 matches: page 2 link carries `&s=` and stays in the search.
  - Capture a screenshot or the relevant rendered HTML as evidence before declaring done.

- [ ] **Step 5: Stage the release (gated on user go)**

Do NOT tag/push/deploy without the user's go (space-out-releases). When approved:
```bash
git push origin HEAD:main
git tag -a v9.8.0 -m "v9.8.0 — Notes-scoped search & archive; remove header blob + global search"
git push origin v9.8.0
```
Then install via wp-admin → Dashboard → Updates → "Update theme" (canonical), or `gh workflow run deploy.yml --repo juanlentino/signal-and-noise --ref v9.8.0` (emergency).

---

## Self-Review

**Spec coverage:**
- Two-state `/notes` (browse/search) → Task 3 ✓
- Hand-rolled form, no `core/search` (blob killed at root) → Task 3 ✓
- `sn_notes_search_term()`, `s` injection, Notes-only → Task 1 ✓
- Pagination carries `s` (`add_args`) → Task 1 (helper) + Task 3 (wiring) ✓
- `/?s=` → `/notes/?s=` funnel → Task 2 ✓
- Remove `templates/search.html`, `inc/search-query.php`, test, `functions.php` require → Task 4 ✓
- Header revert + CSS removal → Task 5 ✓
- Hero stays / pillars hide during search → Task 3 ✓
- Accessibility (role=search, hidden label, aria-label submit) → Task 3 ✓
- Edge cases (empty/whitespace term, XSS escaping, no-match state) → Tasks 1 + 3 ✓
- Build marker bump → Task 2 ✓
- Tests (term, injection, add_args, redirect target) → Tasks 1 + 2 ✓
- Live render verification → Task 7 ✓
- v9.8.0 + CHANGELOG → Task 6 ✓

**Placeholder scan:** No TBD/TODO; every code step shows complete code; commands have expected output. ✓

**Type/name consistency:** `sn_notes_search_term`, `sn_notes_pagination_add_args`, `sn_notes_search_redirect_target`, `$sn_term`, `$sn_searching` used identically across Tasks 1–3. The `.is-search` class + `.sn-notes-search` / `.sn-notes-section-clear` / `.sn-notes-search-summary` classes defined in Task 3 CSS match the markup in Task 3 body. ✓
