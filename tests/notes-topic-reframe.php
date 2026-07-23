<?php
/**
 * Standalone fixture tests for the /notes time→topic reframe (v10.15.0).
 *
 * Covers the new render-file helpers (sticky-driven Start-here card,
 * tag-archive query filter, Topics list, pagination base) and the
 * template-file tag-archive routing matcher + document title.
 *
 * Every new WP-function touch in the implementation is function_exists-
 * guarded; this fixture stubs them so the pure helpers run without a WP
 * load, and flips controllable globals to drive browse / search / tag
 * modes. Mirrors tests/notes-pagination.php + tests/page-index.php shape.
 *
 * @since theme v10.15.0
 */

// SECURITY: CLI-only fixture, not a runtime module.
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', '/' ); }

// ── Controllable stub state ───────────────────────────────────────────
$GLOBALS['__filters']     = array(); // filter name => return value
$GLOBALS['__query_vars']  = array(); // query var => value
$GLOBALS['__opt_sticky']  = array(); // get_option('sticky_posts')
$GLOBALS['__is_tag']      = false;   // is_tag()
$GLOBALS['__queried']     = null;    // get_queried_object()
$GLOBALS['__term_links']  = array(); // term_id => url for get_term_link()
$GLOBALS['__post_status'] = array(); // id => status
$GLOBALS['__post_type']   = array(); // id => type
$GLOBALS['__wpquery_args']= null;    // last WP_Query args
$GLOBALS['__status']      = 0;       // status_header()

// ── WP primitive stubs ────────────────────────────────────────────────
function apply_filters( $h, $v ) { return $GLOBALS['__filters'][ $h ] ?? $v; }
function get_query_var( $v, $d = '' ) { return $GLOBALS['__query_vars'][ $v ] ?? $d; }
function get_option( $k ) { return 'sticky_posts' === $k ? $GLOBALS['__opt_sticky'] : false; }
function is_tag() { return (bool) $GLOBALS['__is_tag']; }
function get_queried_object() { return $GLOBALS['__queried']; }
function get_term_link( $t, $tax = '' ) {
	$id = is_object( $t ) ? (int) $t->term_id : (int) $t;
	return $GLOBALS['__term_links'][ $id ] ?? new WP_Error();
}
function get_post_status( $id ) { return $GLOBALS['__post_status'][ (int) $id ] ?? false; }
function get_post_type( $id ) { return $GLOBALS['__post_type'][ (int) $id ] ?? false; }
function get_bloginfo( $k = '' ) { return 'name' === $k ? 'Signal & Noise' : ''; }
function home_url( $p = '' ) { return 'https://example.test' . $p; }
function is_wp_error( $x ) { return $x instanceof WP_Error; }
function sanitize_text_field( $s ) { return trim( (string) $s ); }
function wp_unslash( $s ) { return $s; }
function status_header( $c ) { $GLOBALS['__status'] = (int) $c; }
function add_action( $h, $cb, $p = 10, $a = 1 ) { return true; }
function add_filter( $h, $cb, $p = 10, $a = 1 ) { return true; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function wp_date( $f, $ts = null ) { return date( $f, $ts ?? 1700000000 ); }
function get_the_time( $f, $p ) { return 1700000000; }
function get_theme_file_path( $p = '' ) { return __DIR__ . '/../' . $p; }

class WP_Error {}
class WP_Query {
	public $args;
	public function __construct( $args = array() ) {
		$args['_constructed'] = true;
		$GLOBALS['__wpquery_args'] = $args;
		$this->args = $args;
	}
}

// Pull in the index helpers (their own module since v10.49.0; the render
// file is render-path only now), then the template-file routing/title helpers.
require __DIR__ . '/../inc/notes-index-helpers.php';
require __DIR__ . '/../inc/page-notes-template.php';

$pass = 0; $fail = 0;
function ok( $cond, $label ) {
	global $pass, $fail;
	if ( $cond ) { $pass++; echo "PASS: $label\n"; }
	else { $fail++; echo "FAIL: $label\n"; }
}

echo "Notes topic-reframe suite — theme v10.15.0\n\n";

// ── sn_notes_sticky_ids() ─────────────────────────────────────────────
$GLOBALS['__opt_sticky'] = array( '5', '7', 9 );
ok( sn_notes_sticky_ids() === array( 5, 7, 9 ), 'sticky ids are int-cast' );
$GLOBALS['__opt_sticky'] = 'not-an-array';
ok( sn_notes_sticky_ids() === array(), 'non-array sticky option -> []' );
$GLOBALS['__opt_sticky'] = array();

// ── sn_notes_current_tag_id() ─────────────────────────────────────────
$GLOBALS['__is_tag'] = true; $GLOBALS['__queried'] = (object) array( 'term_id' => 12, 'name' => 'AI' );
ok( sn_notes_current_tag_id() === 12, 'tag id from queried object when is_tag()' );
$GLOBALS['__is_tag'] = false; $GLOBALS['__queried'] = null;
ok( sn_notes_current_tag_id() === 0, '0 when not a tag archive' );

// ── sn_notes_start_here_id() ──────────────────────────────────────────
$GLOBALS['__query_vars'] = array(); unset( $_GET['s'] ); $GLOBALS['__is_tag'] = false;
$GLOBALS['__opt_sticky']  = array( 5, 7 );
$GLOBALS['__post_status'] = array( 5 => 'draft', 7 => 'publish' );
$GLOBALS['__post_type']   = array( 5 => 'post', 7 => 'post' );
ok( sn_notes_start_here_id() === 7, 'first PUBLISHED sticky in browse mode (skips the draft)' );

$GLOBALS['__query_vars'] = array( 's' => 'x' );
ok( sn_notes_start_here_id() === 0, '0 in search mode (card hidden)' );
$GLOBALS['__query_vars'] = array();

$GLOBALS['__is_tag'] = true; $GLOBALS['__queried'] = (object) array( 'term_id' => 3, 'name' => 'T' );
ok( sn_notes_start_here_id() === 0, '0 in tag mode (card hidden)' );
$GLOBALS['__is_tag'] = false; $GLOBALS['__queried'] = null;

$GLOBALS['__opt_sticky'] = array( 5 ); $GLOBALS['__post_status'] = array( 5 => 'draft' );
ok( sn_notes_start_here_id() === 0, '0 when the sticky is unpublished' );
$GLOBALS['__opt_sticky'] = array(); $GLOBALS['__post_status'] = array(); $GLOBALS['__post_type'] = array();

// ── sn_notes_query_posts(): additive tag/sticky branches ──────────────
$GLOBALS['__query_vars'] = array(); $GLOBALS['__opt_sticky'] = array(); $GLOBALS['__is_tag'] = false;
sn_notes_query_posts(); $a = $GLOBALS['__wpquery_args'];
ok( ( $a['ignore_sticky_posts'] ?? null ) === true, 'ignore_sticky_posts is true (sticky floated into the card, not the list)' );
ok( ! isset( $a['tax_query'] ), 'no tax_query in plain browse' );
ok( ! isset( $a['post__not_in'] ), 'no exclusion when there is no sticky' );

$GLOBALS['__opt_sticky'] = array( 7 ); $GLOBALS['__post_status'] = array( 7 => 'publish' ); $GLOBALS['__post_type'] = array( 7 => 'post' );
sn_notes_query_posts(); $a = $GLOBALS['__wpquery_args'];
ok( ( $a['post__not_in'] ?? null ) === array( 7 ), 'start-here post excluded from the list' );
ok( ! isset( $a['tax_query'] ), 'still no tax_query in browse' );
$GLOBALS['__opt_sticky'] = array(); $GLOBALS['__post_status'] = array(); $GLOBALS['__post_type'] = array();

$GLOBALS['__is_tag'] = true; $GLOBALS['__queried'] = (object) array( 'term_id' => 12, 'name' => 'AI' );
sn_notes_query_posts(); $a = $GLOBALS['__wpquery_args'];
ok( isset( $a['tax_query'] ) && $a['tax_query'][0]['taxonomy'] === 'post_tag' && $a['tax_query'][0]['terms'] === 12, 'tax_query on post_tag in tag mode' );
ok( ! isset( $a['post__not_in'] ), 'no start-here exclusion in tag mode' );
$GLOBALS['__is_tag'] = false; $GLOBALS['__queried'] = null;

// ── sn_notes_pagination_base() ────────────────────────────────────────
$GLOBALS['__is_tag'] = true; $GLOBALS['__queried'] = (object) array( 'term_id' => 12, 'name' => 'AI' );
$GLOBALS['__term_links'] = array( 12 => 'https://example.test/notes/tag/ai/' );
ok( sn_notes_pagination_base() === 'https://example.test/notes/tag/ai/', 'pagination base is the tag link in tag mode' );
$GLOBALS['__is_tag'] = false; $GLOBALS['__queried'] = null;
ok( sn_notes_pagination_base() === 'https://example.test/notes/', 'pagination base is /notes/ in browse mode' );

// ── sn_notes_is_tag_request() (template file) ─────────────────────────
$GLOBALS['__is_tag'] = true; $GLOBALS['__queried'] = (object) array( 'term_id' => 12, 'name' => 'AI' );
ok( sn_notes_is_tag_request() === true, 'tag request true with is_tag() + a real term' );
$GLOBALS['__is_tag'] = true; $GLOBALS['__queried'] = null;
ok( sn_notes_is_tag_request() === false, 'tag request false on a bogus slug (no resolved term) — lets WP 404' );
$GLOBALS['__is_tag'] = false; $GLOBALS['__queried'] = null;
ok( sn_notes_is_tag_request() === false, 'tag request false when not is_tag()' );

// ── sn_notes_tag_title() (template file) ──────────────────────────────
$GLOBALS['__is_tag'] = true; $GLOBALS['__queried'] = (object) array( 'term_id' => 12, 'name' => 'AI' );
ok( sn_notes_tag_title() === 'Notes — AI — Signal & Noise', 'tag title is "Notes — {tag} — {site}"' );
$GLOBALS['__is_tag'] = false; $GLOBALS['__queried'] = null;

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
