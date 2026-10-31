<?php
/**
 * Tests: the visible formats line under the Cite line (owner-approved,
 * 2026-10-31): "Also as Markdown · JSON · Print".
 *
 * Every note already had a Markdown twin (/notes/<slug>.md) and a JSON twin
 * (/notes/<slug>.json), but only as <head> alternates no reader sees. The line
 * shows them where the head advertises them: a published, unprotected single
 * note. Print is a button the share-row script draws, so it is never dead.
 *
 * Run: php tests/note-formats.php
 */
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
define( 'ABSPATH', '/' );
$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { ++$pass; echo "PASS: $m\n"; } else { ++$fail; echo "FAIL: $m\n"; } }
$GLOBALS['__singular'] = true; $GLOBALS['__front'] = false; $GLOBALS['__password'] = ''; $GLOBALS['__status'] = 'publish';
function is_singular( $t = '' ) { return 'post' === $t && $GLOBALS['__singular']; }
function is_front_page() { return $GLOBALS['__front']; }
function get_post_field( $f ) { return 'post_password' === $f ? $GLOBALS['__password'] : ''; }
function get_post_status() { return $GLOBALS['__status']; }
function get_permalink() { return 'https://juanlentino.com/notes/a-note/'; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $s ) { return (string) $s; }
function esc_html__( $s, $d = null ) { return esc_html( $s ); }
function esc_attr__( $s, $d = null ) { return esc_html( $s ); }
function add_action() {}
function add_filter() {}
function home_url( $p = '' ) { return 'https://juanlentino.com' . $p; }
$root = dirname( __DIR__ );
require $root . '/inc/note-markdown.php';
require $root . '/inc/note-formats.php';

$out = sn_note_formats_html();
ok( 0 === strpos( $out, '<p class="sn-note-formats">' ), 'a line of its own under the Cite line' );
ok( false !== strpos( $out, '>Also as</span>' ), 'the approved words: Also as' );
ok( false !== strpos( $out, '<a href="https://juanlentino.com/notes/a-note.md" type="text/markdown">Markdown</a>' ), 'Markdown links the twin the head advertises (sn_note_markdown_url)' );
ok( false !== strpos( $out, '<a href="https://juanlentino.com/notes/a-note.json" type="application/json">JSON</a>' ), 'JSON links the .json twin' );
ok( 1 === substr_count( $out, 'aria-hidden="true">' ) && false !== strpos( $out, '·' ), 'the dot between them is decoration, hidden from screen readers' );
ok( false === strpos( $out, '<button' ) && false === strpos( $out, 'Print' ), 'no Print in the markup: the script draws it, so a reader without script never meets a dead button' );
ok( false === strpos( $out, "\u{2014}" ), 'no em dash' );
foreach ( array( '__singular' => false, '__front' => true, '__password' => 'pw', '__status' => 'draft' ) as $k => $v ) {
	$keep = $GLOBALS[ $k ]; $GLOBALS[ $k ] = $v;
	ok( '' === sn_note_formats_html(), "nothing where the head advertises no Markdown twin ($k)" );
	$GLOBALS[ $k ] = $keep;
}
$render = (string) file_get_contents( $root . '/blocks/note-cite/render.php' );
ok( false !== strpos( $render, 'sn_note_cite_html() . sn_note_formats_html()' ), 'printed by the Cite block, right under the citation, and on its own when the plugin gives no citation' );
$print = (string) file_get_contents( $root . '/assets/css/print.css' );
ok( false !== strpos( $print, '.sn-note-formats,' ), 'not printed: links and a Print button mean nothing on paper' );

$fn = (string) file_get_contents( $root . '/functions.php' );
ok( 1 === preg_match( "#require_once[^;]*'/inc/note-formats\.php'#", $fn ), 'functions.php requires inc/note-formats.php (the Cite block calls it unguarded)' );
$css = (string) file_get_contents( $root . '/assets/css/components-07-note-share-reply.css' );
$hit = preg_match( '/\.sn-note-formats a,\s*\.sn-note-formats__print\s*\{([^}]*)\}/', $css, $hm ) ? $hm[1] : '';
ok( 1 === preg_match( '/padding:\s*0\.35rem/', $hit ) && 1 === preg_match( '/margin:\s*-0\.35rem 0/', $hit ), 'a 32px hit area that does not move the line' );
ok( 1 === preg_match( '/text-decoration:\s*underline/', $hit ), 'underlined: the cue that they are links, not more label' );
ok( 1 === preg_match( '/\.sn-note-formats__print:focus-visible\s*\{[^}]*color:\s*var\(--wp--preset--color--blood\)/', $css ), 'a designed keyboard focus, in blood' );
ok( 1 === preg_match( '/@media \(hover: hover\)\s*\{\s*\.sn-note-formats a:hover,\s*\.sn-note-formats__print:hover\s*\{[^}]*color:\s*var\(--wp--preset--color--blood\)/', $css ), 'hover only where there is hover, in blood' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
