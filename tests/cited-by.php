<?php
/**
 * Standalone fixture tests for the Cited-by footer (v10.21.0).
 *
 * inc/cited-by.php: [sn_cited_by] — the reverse of related-notes: published
 * notes whose post_content links to the current note at /notes/<post_name>.
 * SQL LIKE prefilter + PHP boundary regex (so /notes/craft-two never counts
 * as citing /notes/craft). Empty result renders '' (no chrome).
 *
 * @since theme v10.21.0
 */
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', '/' ); }
if ( ! defined( 'ARRAY_A' ) ) { define( 'ARRAY_A', 'ARRAY_A' ); }
define( 'SN_CITED_BY_TEST', true );

$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }

// ── WP stubs ──
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_url( $u ) { $u = (string) $u; $u = str_replace( array( '"', "'", '<', '>', ' ' ), '', $u ); return str_replace( '&', '&amp;', $u ); }
function apply_filters( $tag, $value ) { return $value; }
function add_shortcode() {}
function add_filter() {}
function do_shortcode( $s ) { return $s; }
function shortcode_unautop( $s ) { return $s; }

$GLOBALS['POSTS'] = array();
function mk_post( $id, $title, $name, $content ) {
	$p = new stdClass();
	$p->ID = $id; $p->post_title = $title; $p->post_name = $name; $p->post_content = $content;
	$GLOBALS['POSTS'][ $id ] = $p;
	return $p;
}
function get_post( $id ) { return $GLOBALS['POSTS'][ (int) $id ] ?? null; }
function get_the_title( $id ) { return $GLOBALS['POSTS'][ (int) $id ]->post_title ?? ''; }
function get_permalink( $id ) { return 'https://x.test/notes/' . ( $GLOBALS['POSTS'][ (int) $id ]->post_name ?? $id ) . '/'; }
function get_the_date( $fmt, $id ) { return 'c' === $fmt ? '2026-07-01T00:00:00+00:00' : '2026.07.01'; }
$GLOBALS['__queried_id'] = 0;
function get_queried_object_id() { return $GLOBALS['__queried_id']; }

// wpdb stub: captures the prepared SQL, returns configured rows.
$GLOBALS['__rows'] = array();
$GLOBALS['__last_sql'] = '';
class SnCitedByWpdb {
	public $posts = 'wp_posts';
	public function esc_like( $s ) { return addcslashes( (string) $s, '_%\\' ); }
	public function prepare( $sql, ...$args ) {
		foreach ( $args as $a ) {
			if ( is_string( $a ) ) {
				$sql = preg_replace( '/%s/', "'" . $a . "'", $sql, 1 );
			} else {
				$sql = preg_replace( '/%d/', (string) (int) $a, $sql, 1 );
			}
		}
		$GLOBALS['__last_sql'] = $sql;
		return $sql;
	}
	public function get_results( $sql, $out = null ) { return $GLOBALS['__rows']; }
}
$GLOBALS['wpdb'] = new SnCitedByWpdb();

require __DIR__ . '/../inc/cited-by.php';

echo "Cited-by suite — theme v10.21.0\n\n";

// ── boundary pattern ──
ok( 1 === preg_match( sn_cited_by_link_pattern( 'craft' ), '<a href="/notes/craft/">x</a>' ), 'pattern matches /notes/craft/' );
ok( 1 === preg_match( sn_cited_by_link_pattern( 'craft' ), 'href="https://x.test/notes/craft"' ), 'pattern matches quote-terminated absolute link' );
ok( 0 === preg_match( sn_cited_by_link_pattern( 'craft' ), '<a href="/notes/craft-two/">x</a>' ), 'prefix collision rejected' );

// ── query: SQL shape + PHP boundary filter + cap ──
mk_post( 10, 'Target note', 'craft', '<p>me</p>' );
$GLOBALS['__rows'] = array(
	array( 'ID' => 1, 'post_content' => '<a href="/notes/craft/">real citer</a>' ),
	array( 'ID' => 2, 'post_content' => '<a href="/notes/craft-two/">collision, not a citer</a>' ),
	array( 'ID' => 3, 'post_content' => 'href="https://x.test/notes/craft"' ),
);
$ids = sn_cited_by_query( 10, 5 );
ok( array( 1, 3 ) === $ids, 'boundary filter keeps real citers, drops the prefix collision' );
ok( false !== strpos( $GLOBALS['__last_sql'], "post_status = 'publish'" ), 'SQL filters to published' );
ok( false !== strpos( $GLOBALS['__last_sql'], 'ID != 10' ), 'SQL excludes self' );
ok( false !== strpos( $GLOBALS['__last_sql'], '/notes/craft' ), 'SQL LIKE carries the esc_like needle' );

// cap honored.
$GLOBALS['__rows'] = array();
for ( $i = 1; $i <= 8; $i++ ) { $GLOBALS['__rows'][] = array( 'ID' => $i, 'post_content' => '<a href="/notes/craft/">x</a>' ); }
ok( 5 === count( sn_cited_by_query( 10, 5 ) ), 'cap of 5 honored' );

// missing post / empty slug → [].
ok( array() === sn_cited_by_query( 999, 5 ), 'unknown post → empty' );

// ── shortcode render ──
$GLOBALS['__queried_id'] = 10;
mk_post( 1, 'A citing <note>', 'citer-one', '' );
mk_post( 3, 'Another & citer', 'citer-three', '' );
$GLOBALS['__rows'] = array(
	array( 'ID' => 1, 'post_content' => '<a href="/notes/craft/">x</a>' ),
	array( 'ID' => 3, 'post_content' => '<a href="/notes/craft/">x</a>' ),
);
$html = sn_cited_by_shortcode();
ok( false !== strpos( $html, 'class="sn-cited-by"' ) && false !== strpos( $html, 'aria-label="Cited by"' ), 'footer chrome present' );
ok( false !== strpos( $html, 'sn-cited-by__label' ), 'label element present' );
ok( false !== strpos( $html, 'A citing &lt;note&gt;' ), 'title escaped at the sink' );
ok( false !== strpos( $html, 'https://x.test/notes/citer-one/' ), 'row links the citer permalink' );

// empty renders '' — no chrome.
$GLOBALS['__rows'] = array();
ok( '' === sn_cited_by_shortcode(), 'zero citers → empty string' );
$GLOBALS['__queried_id'] = 0;
ok( '' === sn_cited_by_shortcode(), 'no queried post → empty string' );

// ── mount + wiring contracts ──
$tpl = file_get_contents( __DIR__ . '/../templates/single.html' );
ok( false !== strpos( $tpl, '[sn_cited_by]' ), 'single.html mounts [sn_cited_by]' );
$fn = file_get_contents( __DIR__ . '/../functions.php' );
ok( false !== strpos( $fn, 'inc/cited-by.php' ), 'functions.php requires inc/cited-by.php' );
$css = file_get_contents( __DIR__ . '/../assets/css/components.css' );
ok( false !== strpos( $css, '.sn-cited-by' ), 'components.css styles .sn-cited-by' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
