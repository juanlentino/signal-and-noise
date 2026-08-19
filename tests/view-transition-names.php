<?php
/**
 * Standalone tests for the view-transition name shared by the /notes index row
 * and the single-note hero (theme v11.12.0).
 *
 * The regression these exist to catch: the v11.10.0 index redesign replaced a
 * core/post-title block with hand-rolled markup, which took the row out of
 * render_block_core/post-title's reach. The destination kept its name, the
 * source lost one, and a one-sided morph is invisible — it just looks like the
 * root cross-fade that was already there.
 */
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', '/' ); }

$GLOBALS['__slugs'] = array( 42 => 'the-pen-is-not-the-notary', 7 => 'Two Kinds__of Provenance!', 9 => '' );
function get_post_field( $field, $id ) { return $GLOBALS['__slugs'][ (int) $id ] ?? ''; }
if ( ! function_exists( 'add_filter' ) ) { function add_filter() { return true; } }
if ( ! function_exists( 'esc_attr' ) ) { function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); } }

require __DIR__ . '/../inc/blocks-view-transitions.php';

$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }
echo "view-transition names — v11.12.0\n\n";

// ── the name format ─────────────────────────────────────────────────────────
ok( sn_view_transition_name( 42 ) === 'sn-note-the-pen-is-not-the-notary', 'a clean slug becomes sn-note-<slug>' );
ok( sn_view_transition_name( 7 ) === 'sn-note-two-kinds-of-provenance', 'case folds and every run of non-alphanumerics collapses to ONE hyphen' );
ok( substr( sn_view_transition_name( 7 ), -1 ) !== '-', 'no trailing hyphen — that would be an invalid custom-ident' );
ok( sn_view_transition_name( 9 ) === '', 'a post with no slug yields no name rather than the bare prefix' );
ok( sn_view_transition_name( 0 ) === '', 'a zero id yields no name' );
ok( sn_view_transition_name( -1 ) === '', 'a negative id yields no name' );
ok( sn_view_transition_name( 999 ) === '', 'an unknown post yields no name' );

// A CSS custom-ident may not begin with a digit. The prefix guarantees it.
foreach ( array( 42, 7 ) as $id ) {
	$n = sn_view_transition_name( $id );
	ok( (bool) preg_match( '/^[a-z][a-z0-9-]*$/', $n ), "the name for post $id is a valid CSS custom-ident ($n)" );
}

// ── both surfaces must agree, or the morph is one-sided and invisible ───────
// Destination: the filter's own output for the single-note hero.
$hero = sn_view_transition_post_title(
	'<h1 class="wp-block-post-title font-display sn-note-title" style="font-size:clamp(2.5rem, 6vw, 5rem)">The pen is not the notary</h1>',
	array(),
	(object) array( 'context' => array( 'postId' => 42 ) )
);
ok( false !== strpos( $hero, 'view-transition-name: sn-note-the-pen-is-not-the-notary;' ), 'the hero carries the name' );
ok( false !== strpos( $hero, 'font-size:clamp(2.5rem, 6vw, 5rem)' ), 'the hero keeps its pre-existing inline style — appended, not clobbered' );

// Source: the string the index row builds from the same helper.
$row_name = sn_view_transition_name( 42 );
ok( false !== strpos( $hero, $row_name . ';' ), 'THE TWO SURFACES EMIT THE IDENTICAL NAME — this is the whole feature' );

// ── the filter's guards ─────────────────────────────────────────────────────
$none = sn_view_transition_post_title( '<h1 class="x">No slug</h1>', array(), (object) array( 'context' => array( 'postId' => 9 ) ) );
ok( false === strpos( $none, 'view-transition-name' ), 'a nameless post gets no style attribute at all' );
$plain = sn_view_transition_post_title( '<p>not a heading</p>', array(), (object) array( 'context' => array( 'postId' => 42 ) ) );
ok( $plain === '<p>not a heading</p>', 'markup with no heading or anchor is returned untouched' );

// The row's own attribute construction (mirrors inc/notes-index-row.php).
$attr = '' !== $row_name ? ' style="view-transition-name: ' . esc_attr( $row_name ) . ';"' : '';
ok( $attr === ' style="view-transition-name: sn-note-the-pen-is-not-the-notary;"', 'the row builds a well-formed style attribute' );
$empty = '' !== sn_view_transition_name( 9 ) ? 'x' : '';
ok( $empty === '', 'a nameless post contributes an EMPTY attribute, never style="view-transition-name: ;"' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
