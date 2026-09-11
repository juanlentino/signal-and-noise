<?php
/**
 * Tests: the search query carries sn_notes_search, and a search row renders
 * the plugin's snippet (kses'd, mark only) while a browse row does not.
 * Companion to signal-and-noise-tools v14.0.0 (search served by the kernel).
 */
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', '/' ); }
$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "  ok  - $m\n"; } else { $fail++; echo "  FAIL - $m\n"; } }

// ── stubs (mirror tests/notes-index-row.php, plus the two this suite needs) ──
$GLOBALS['__filters'] = array(); $GLOBALS['__query_vars'] = array(); $GLOBALS['__get'] = array();
function apply_filters( $h, $v ) { foreach ( $GLOBALS['__filters'][ $h ] ?? array() as $cb ) { $v = $cb( $v, ...array_slice( func_get_args(), 2 ) ); } return $v; }
function add_filter( $h, $cb, $p = 10, $a = 1 ) { $GLOBALS['__filters'][ $h ][] = $cb; return true; }
function add_action() { return true; }
function get_query_var( $k, $d = '' ) { return $GLOBALS['__query_vars'][ $k ] ?? $d; }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function wp_unslash( $s ) { return $s; }
function get_option( $k, $d = false ) { return $d; }
function get_post_meta( $id, $key, $single = false ) { return $single ? '' : array(); }
function get_the_tags( $id ) { return false; }
function get_the_excerpt( $p ) { return $p->excerpt ?? ''; }
function get_permalink( $p ) { return 'https://x.test/notes/' . ( $p->slug ?? 'x' ) . '/'; }
function get_the_title( $p ) { return $p->title ?? ''; }
function get_the_date( $fmt, $p ) { return gmdate( $fmt, strtotime( $p->post_date ) ); }
function get_the_time( $fmt, $p ) { return gmdate( $fmt, strtotime( $p->post_date ) ); }
function wp_date( $fmt, $ts ) { return gmdate( $fmt, (int) $ts ); }
function date_i18n( $f, $ts ) { return gmdate( $f, (int) $ts ); }
function number_format_i18n( $n ) { return (string) $n; }
function get_post_type( $p ) { return 'post'; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $s ) { return esc_html( $s ); }
function esc_url( $s ) { return $s; }
function esc_html__( $s, $d = null ) { return esc_html( $s ); }
function esc_attr__( $s, $d = null ) { return esc_html( $s ); }
function __( $s, $d = null ) { return $s; }
function wp_strip_all_tags( $s ) { return strip_tags( (string) $s ); }
function force_balance_tags( $s ) { return (string) $s; } // stub: balancing is core's; the pin below guards the allowlist, not the balancer
function wp_kses( $s, $allowed ) { $keep = implode( '|', array_map( 'preg_quote', array_keys( (array) $allowed ) ) ); return preg_replace( '#</?(?!(?:' . $keep . ')\b)[a-z][^>]*>#i', '', (string) $s ); }
class WP_Query { public $args; public $posts = array(); public $found_posts = 0; public $max_num_pages = 0; public function __construct( $a ) { $this->args = $a; } public function have_posts() { return false; } }
$GLOBALS['__last_query'] = null;
// The helpers call `new WP_Query( $args )`; capture the args.
require_once __DIR__ . '/../inc/notes-index-helpers.php';
require_once __DIR__ . '/../inc/notes-index-row.php';

echo "Group: the query var\n";
$GLOBALS['__query_vars'] = array( 's' => 'ledger anchor' );
$q = sn_notes_query_posts();
ok( $q instanceof WP_Query && true === ( $q->args['sn_notes_search'] ?? null ), 'search mode sets sn_notes_search => true' );
ok( 'ledger anchor' === ( $q->args['s'] ?? '' ), 'and still passes s (the LIKE floor stays)' );
$GLOBALS['__query_vars'] = array();
$q2 = sn_notes_query_posts();
ok( ! array_key_exists( 'sn_notes_search', $q2->args ), 'browse mode does not set it' );

echo "\nGroup: the row\n";
$p = (object) array( 'ID' => 11, 'title' => 'T', 'slug' => 't', 'post_date' => '2026-05-01 00:00:00', 'excerpt' => 'Plain excerpt & co' );
add_filter( 'sn_notes_search_snippet', function ( $excerpt, $id, $term ) { return 'The <mark>ledger</mark> line <script>x</script>'; } );
$GLOBALS['__query_vars'] = array( 's' => 'ledger' );
ob_start(); sn_notes_render_row( $p ); $html = ob_get_clean();
ok( false !== strpos( $html, '<p class="sn-notes-row-excerpt">The <mark>ledger</mark> line x</p>' ), 'a search row renders the snippet with <mark> kept and every other tag dropped' );
$GLOBALS['__query_vars'] = array();
ob_start(); sn_notes_render_row( $p ); $html2 = ob_get_clean();
ok( false !== strpos( $html2, 'Plain excerpt &amp; co' ) && false === strpos( $html2, '<mark>' ), 'a browse row renders the escaped excerpt and never calls the snippet filter' );
$GLOBALS['__filters'] = array();
$GLOBALS['__query_vars'] = array( 's' => 'ledger' );
ob_start(); sn_notes_render_row( $p ); $html3 = ob_get_clean();
ok( false !== strpos( $html3, 'Plain excerpt &amp; co' ), 'with no plugin filter registered, a search row still renders the escaped excerpt (plugin-off parity)' );

echo "\nGroup: the style\n";
$css = (string) file_get_contents( __DIR__ . '/../assets/css/notes.css' );
ok( 1 === preg_match( '/\.sn-notes-row-excerpt\s+mark\s*\{/', $css ), 'notes.css styles mark inside the excerpt' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
