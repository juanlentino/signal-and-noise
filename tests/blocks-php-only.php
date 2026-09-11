<?php
/**
 * Tests: the nine PHP-only blocks (WordPress 7.0 supports.autoRegister) —
 * registered as declared, each render === its shortcode's output on the same
 * post (THE PARITY PIN), a differing block-context id is honoured.
 * @since theme v13.1.0
 */
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', '/' ); }
$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }

// ── WP stubs ──
$GLOBALS['__blocks'] = array(); $GLOBALS['__init'] = array();
function register_block_type( $name, $args = array() ) { $GLOBALS['__blocks'][ $name ] = $args; return (object) $args; }
function add_action( $h, $cb, $p = 10, $a = 1 ) { if ( 'init' === $h ) { $GLOBALS['__init'][] = $cb; } return true; }
function add_filter( $h, $cb, $p = 10, $a = 1 ) { return true; }
function __( $s, $d = null ) { return $s; }
$GLOBALS['__the_id'] = 7; $GLOBALS['__queried'] = 7; $GLOBALS['__setup'] = array();
function get_the_ID() { return $GLOBALS['__the_id']; }
function get_queried_object_id() { return $GLOBALS['__queried']; }
function get_post( $id = null ) { return (object) array( 'ID' => null === $id ? $GLOBALS['__the_id'] : (int) $id ); }
function setup_postdata( $p ) { $GLOBALS['__setup'][] = 'setup:' . $p->ID; $GLOBALS['__the_id'] = (int) $p->ID; return true; }
function wp_reset_postdata() { $GLOBALS['__setup'][] = 'reset'; $GLOBALS['__the_id'] = 7; }
// The renderers, stubbed to echo which post they saw — parity is about the WRAPPER.
function sn_prov_chip_shortcode()      { return '<chip:' . get_the_ID() . '>'; }
function sn_prov_panel_shortcode()     { return '<panel:' . get_the_ID() . '>'; }
function sn_related_notes_shortcode()  { return '<related:' . get_queried_object_id() . '>'; }
function sn_cited_by_shortcode()       { return '<cited:' . get_queried_object_id() . '>'; }
function sn_note_share_shortcode()     { return '<share:' . get_queried_object_id() . '>'; }
function sn_note_reply_shortcode()     { return '<reply:' . get_queried_object_id() . '>'; }
function sn_updated_date_shortcode()   { return '<updated:' . get_post()->ID . '>'; }
function sn_post_pillar_shortcode()    { return '<pillar:' . get_the_ID() . '>'; }
function sn_dark_mode_toggle_markup( $atts = array() ) { return '<toggle:' . ( $atts['placement'] ?? 'footer' ) . '>'; }

require_once __DIR__ . '/../inc/blocks-php-only.php';
foreach ( $GLOBALS['__init'] as $cb ) { $cb(); }

$expected = array( 'prov-chip', 'prov-panel', 'related-notes', 'cited-by', 'note-share', 'note-reply', 'updated-date', 'post-pillar', 'theme-toggle' );
echo "Group: registration\n";
foreach ( $expected as $slug ) {
	$b = $GLOBALS['__blocks'][ 'signal-noise/' . $slug ] ?? null;
	ok( is_array( $b ) && true === ( $b['supports']['autoRegister'] ?? null ) && is_callable( $b['render_callback'] ?? null ) && 3 === ( $b['api_version'] ?? 0 ) && 'signal-noise' === ( $b['category'] ?? '' ), "signal-noise/$slug registered PHP-only (autoRegister, render_callback, api_version 3, category signal-noise)" );
}
ok( 9 === count( array_filter( array_keys( $GLOBALS['__blocks'] ), static fn( $n ) => str_starts_with( $n, 'signal-noise/' ) ) ), 'exactly nine blocks registered by this module' );
foreach ( array( 'prov-chip', 'prov-panel', 'related-notes', 'cited-by', 'note-share', 'note-reply', 'updated-date', 'post-pillar' ) as $slug ) {
	ok( false === ( $GLOBALS['__blocks'][ 'signal-noise/' . $slug ]['supports']['multiple'] ?? true ) && array( 'postId' ) === ( $GLOBALS['__blocks'][ 'signal-noise/' . $slug ]['uses_context'] ?? array() ), "$slug is single-instance and uses postId context" );
}
ok( ! isset( $GLOBALS['__blocks']['signal-noise/theme-toggle']['supports']['multiple'] ), 'theme-toggle allows two instances (header and footer)' );
ok( array( 'placement' ) === array_keys( $GLOBALS['__blocks']['signal-noise/theme-toggle']['attributes'] ?? array() ) && array( 'header', 'footer' ) === ( $GLOBALS['__blocks']['signal-noise/theme-toggle']['attributes']['placement']['enum'] ?? array() ), 'theme-toggle has one attribute, placement enum header|footer' );
foreach ( $expected as $slug ) { ok( false === ( $GLOBALS['__blocks'][ 'signal-noise/' . $slug ]['supports']['html'] ?? true ), "$slug: html editing off" ); }

echo "\nGroup: THE PARITY PIN — block output === shortcode output on the same post\n";
$blk = static function ( $slug, $ctx = array(), $attrs = array() ) { return ( $GLOBALS['__blocks'][ 'signal-noise/' . $slug ]['render_callback'] )( $attrs, '', (object) array( 'context' => $ctx ) ); };
$pairs = array( 'prov-chip' => 'sn_prov_chip_shortcode', 'prov-panel' => 'sn_prov_panel_shortcode', 'related-notes' => 'sn_related_notes_shortcode', 'cited-by' => 'sn_cited_by_shortcode', 'note-share' => 'sn_note_share_shortcode', 'note-reply' => 'sn_note_reply_shortcode', 'updated-date' => 'sn_updated_date_shortcode', 'post-pillar' => 'sn_post_pillar_shortcode' );
foreach ( $pairs as $slug => $fn ) {
	ok( $blk( $slug, array( 'postId' => 7 ) ) === $fn(), "$slug === {$fn}() when context is the queried post" );
	ok( $blk( $slug ) === $fn(), "$slug === {$fn}() with no context at all (a template outside any loop)" );
}
ok( $blk( 'theme-toggle', array(), array( 'placement' => 'header' ) ) === sn_dark_mode_toggle_markup( array( 'placement' => 'header' ) ), 'theme-toggle passes placement through' );
ok( $blk( 'theme-toggle' ) === sn_dark_mode_toggle_markup(), 'theme-toggle with no attribute renders the footer form' );
ok( $blk( 'theme-toggle', array(), array( 'placement' => 'sidebar' ) ) === sn_dark_mode_toggle_markup(), 'an unknown placement falls back to footer, never passes through' );

echo "\nGroup: a differing context id is honoured (a future Query Loop placement)\n";
$GLOBALS['__setup'] = array();
$out = $blk( 'prov-chip', array( 'postId' => 42 ) );
ok( '<chip:42>' === $out, 'the chip rendered for the CONTEXT post, not the global' );
ok( array( 'setup:42', 'reset' ) === $GLOBALS['__setup'], 'setup_postdata/wp_reset_postdata bracketed the render' );
ok( 7 === get_the_ID(), 'and the global is restored afterwards' );
$GLOBALS['__setup'] = array();
$blk( 'prov-chip', array( 'postId' => 7 ) );
ok( array() === $GLOBALS['__setup'], 'a context equal to the global does NOT re-setup (no churn on single)' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
