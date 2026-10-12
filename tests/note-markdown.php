<?php
/**
 * Standalone tests for the note's Markdown twin advertisement (inc/note-markdown.php):
 * the <head> alternate prints on a published, unprotected note only, its href is
 * the exact /notes/<slug>.md URL the edge Worker gates on, and the manifest names
 * the convention.
 */
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', '/' ); }
define( 'SN_NOTE_MARKDOWN_TEST', true );

$GLOBALS['__view'] = array();
function sn_nm_view( $over = array() ) {
	$GLOBALS['__view'] = array_merge(
		array( 'singular' => 'post', 'front' => false, 'protected' => false, 'status' => 'publish', 'permalink' => 'https://juanlentino.com/notes/some-note/' ),
		$over
	);
}
if ( ! function_exists( 'is_singular' ) ) { function is_singular( $post_types = '' ) { return $GLOBALS['__view']['singular'] === $post_types; } }
if ( ! function_exists( 'is_front_page' ) ) { function is_front_page() { return $GLOBALS['__view']['front']; } }
if ( ! function_exists( 'post_password_required' ) ) { function post_password_required( $post = null ) { return $GLOBALS['__view']['protected']; } }
if ( ! function_exists( 'get_post_status' ) ) { function get_post_status( $post = null ) { return $GLOBALS['__view']['status']; } }
if ( ! function_exists( 'get_permalink' ) ) { function get_permalink( $post = 0, $leavename = false ) { return $GLOBALS['__view']['permalink']; } }
if ( ! function_exists( 'esc_url' ) ) { function esc_url( $url ) { return str_replace( array( '"', '<', '>' ), '', (string) $url ); } }
if ( ! function_exists( 'home_url' ) ) { function home_url( $path = '' ) { return 'https://juanlentino.com' . $path; } }
if ( ! function_exists( 'add_action' ) ) { function add_action() { return true; } }
if ( ! function_exists( 'add_filter' ) ) { function add_filter() { return true; } }

require __DIR__ . '/../inc/note-markdown.php';

$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }
function sn_nm_head() { ob_start(); sn_note_markdown_head_link(); return ob_get_clean(); }
echo "note markdown twin\n\n";

sn_nm_view();
$link = sn_nm_head();
// The Worker converts only when the page names this EXACT string, so the shape is the contract.
ok( '<link rel="alternate" type="text/markdown" href="https://juanlentino.com/notes/some-note.md" title="This note as Markdown">' . "\n" === $link, 'a published note advertises /notes/<slug>.md as a text/markdown alternate' );

sn_nm_view( array( 'singular' => 'page' ) );
ok( '' === sn_nm_head(), 'a page gets no Markdown link (notes only)' );
sn_nm_view( array( 'front' => true ) );
ok( '' === sn_nm_head(), 'the home page gets no Markdown link, even if it were ever a post' );
sn_nm_view( array( 'protected' => true ) );
ok( '' === sn_nm_head(), 'a password-protected note gets no Markdown link (its text must not leave as Markdown)' );
sn_nm_view( array( 'status' => 'draft' ) );
ok( '' === sn_nm_head(), 'a draft seen in preview gets no Markdown link' );
sn_nm_view( array( 'permalink' => false ) );
ok( '' === sn_nm_head(), 'no permalink, no link' );

ok( 'https://juanlentino.com/notes/x.md' === sn_note_markdown_url( 'https://juanlentino.com/notes/x/' ), 'the .md URL drops the trailing slash' );

$s = sn_note_markdown_advertise_surface( array() );
ok( 1 === count( $s ) && 'note-markdown' === $s[0]['type'] && 'text/markdown' === $s[0]['format'], 'the discovery manifest names the Markdown convention' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
