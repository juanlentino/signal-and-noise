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

// v10.49.0: the pure helpers moved to their own module (page-notes-render.php
// is render-path only now; the SN_NOTES_RENDER_TEST sentinel is retired).
require __DIR__ . '/../inc/notes-index-helpers.php';

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
ok( $GLOBALS['__wpquery_args']['post_type'] === array( 'post', 'page' ), 'v10.51.0: search mode spans the corpus (post + page), owner-decided 2026-07-28' );
ok( $GLOBALS['__wpquery_args']['post_status'] === 'publish', 'still publish-only when searching' );
$GLOBALS['__query_vars'] = array();

// ── sn_notes_pagination_add_args() ──
ok( sn_notes_pagination_add_args( '' ) === array(), 'no add_args when browsing' );
ok( sn_notes_pagination_add_args( 'provenance' ) === array( 's' => 'provenance' ), 'add_args carries s when searching' );

// ── v11.4.7: the /notes section label carries no em-dash ──
// House style is no em-dashes in reader-facing copy. These three labels are the only
// em-dashes the THEME itself emits into a reader's page (everything else on the live
// site is CMS content). They are one family and change together: a crawl of the default
// /notes view only ever sees "Index", so fixing that alone would leave the Search and
// Tag states inconsistent the moment someone searches. Source-assertion pattern per
// tests/keyboard-nav.php.
$sn_render_src = file_get_contents( __DIR__ . '/../inc/page-notes-render.php' );
preg_match_all( '/<p class="sn-notes-section-label"[^>]*>(.*?)<\/p>/s', $sn_render_src, $sn_labels );
$sn_label_bodies = $sn_labels[1] ?? array();
ok( 3 === count( $sn_label_bodies ), 'all three section-label states are present (Index, Search, Tag)' );
foreach ( $sn_label_bodies as $i => $body ) {
	ok( false === strpos( $body, '&mdash;' ), "section label #$i emits no &mdash; entity" );
	ok( false === strpos( $body, '—' ), "section label #$i emits no literal em-dash" );
}
ok( false !== strpos( $sn_render_src, '>Notes: Index<' ), 'index label reads "Notes: Index"' );
ok( false !== strpos( $sn_render_src, '>Notes: Search &middot;' ), 'search label reads "Notes: Search &middot; …" (middot kept as the secondary separator)' );
ok( false !== strpos( $sn_render_src, '>Notes: Tag &middot;' ), 'tag label reads "Notes: Tag &middot; …"' );

// The empty-search state is the other string this template renders to a reader. A crawl
// of /notes never reaches it (it needs a search that matches nothing), which is why it
// was missed by the live-site pass and is pinned here instead.
preg_match( '/<p class="sn-notes-empty">Nothing matches.*?<\/p>/s', $sn_render_src, $sn_empty );
ok( ! empty( $sn_empty[0] ), 'the empty-search state is present' );
ok( false === strpos( $sn_empty[0] ?? '', '&mdash;' ), 'empty-search state emits no &mdash;' );
ok( false !== strpos( $sn_render_src, '&rdquo;. Notes, essays, and pages all searched.' ), 'empty-search state reads as two sentences' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
