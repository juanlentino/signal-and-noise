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
$GLOBALS['__the_id'] = 7; $GLOBALS['__queried'] = 7;
function get_the_ID() { return $GLOBALS['__the_id']; }
function get_queried_object_id() { return $GLOBALS['__queried']; }
function get_post( $id = null ) { return (object) array( 'ID' => null === $id ? $GLOBALS['__the_id'] : (int) $id ); }
// wpautop stub — mirrors the shape of core's real wpautop() enough to prove the
// WRAPPER is applied: empty in → empty out, otherwise wrapped in a marker tag.
if ( ! function_exists( 'wp_is_serving_rest_request' ) ) { function wp_is_serving_rest_request() { return ! empty( $GLOBALS['__rest'] ); } }
if ( ! function_exists( 'esc_html' ) ) { function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); } }
if ( ! function_exists( 'wpautop' ) ) {
	function wpautop( $s ) { return '' === (string) $s ? '' : '<autop>' . $s . '</autop>'; }
}
// The renderers, stubbed to echo which post they saw — parity is about the WRAPPER.
function sn_prov_chip_shortcode()      { return '<chip:' . get_the_ID() . '>'; }
function sn_prov_panel_shortcode()     { return '<panel:' . get_the_ID() . '>'; }
function sn_related_notes_shortcode()  { return '<related:' . get_queried_object_id() . '>'; }
function sn_cited_by_shortcode()       { return '<cited:' . get_queried_object_id() . '>'; }
function sn_note_share_shortcode()     { return '<share:' . get_queried_object_id() . '>'; }
function sn_note_reply_shortcode()     { return '<reply:' . get_queried_object_id() . '>'; }
function sn_updated_date_shortcode()   { return ! empty( $GLOBALS['__empty'] ) ? '' : '<updated:' . get_post()->ID . '>'; }
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
	ok( false === ( $GLOBALS['__blocks'][ 'signal-noise/' . $slug ]['supports']['multiple'] ?? true ), "$slug is single-instance" );
}
ok( ! isset( $GLOBALS['__blocks']['signal-noise/theme-toggle']['supports']['multiple'] ), 'theme-toggle allows two instances (header and footer)' );
ok( array( 'placement' ) === array_keys( $GLOBALS['__blocks']['signal-noise/theme-toggle']['attributes'] ?? array() ) && array( 'header', 'footer' ) === ( $GLOBALS['__blocks']['signal-noise/theme-toggle']['attributes']['placement']['enum'] ?? array() ), 'theme-toggle has one attribute, placement enum header|footer' );
foreach ( $expected as $slug ) { ok( false === ( $GLOBALS['__blocks'][ 'signal-noise/' . $slug ]['supports']['html'] ?? true ), "$slug: html editing off" ); }

echo "\nGroup: THE PARITY PIN — block output === wpautop( shortcode output ) on the same post\n";
$blk = static function ( $slug, $ctx = array(), $attrs = array() ) { return ( $GLOBALS['__blocks'][ 'signal-noise/' . $slug ]['render_callback'] )( $attrs, '', (object) array( 'context' => $ctx ) ); };
$pairs = array( 'prov-chip' => 'sn_prov_chip_shortcode', 'prov-panel' => 'sn_prov_panel_shortcode', 'related-notes' => 'sn_related_notes_shortcode', 'cited-by' => 'sn_cited_by_shortcode', 'note-share' => 'sn_note_share_shortcode', 'note-reply' => 'sn_note_reply_shortcode', 'updated-date' => 'sn_updated_date_shortcode', 'post-pillar' => 'sn_post_pillar_shortcode' );
foreach ( $pairs as $slug => $fn ) {
	ok( $blk( $slug, array( 'postId' => 7 ) ) === wpautop( $fn() ), "$slug === wpautop( {$fn}() ) when context is the queried post" );
	ok( $blk( $slug ) === wpautop( $fn() ), "$slug === wpautop( {$fn}() ) with no context at all (a template outside any loop)" );
}
// The pin that would have caught the missing wrapper: the block is NOT the bare shortcode output.
ok( $blk( 'prov-chip' ) !== sn_prov_chip_shortcode(), 'prov-chip is NOT the bare shortcode output — it is wpautop-wrapped' );
ok( $blk( 'theme-toggle', array(), array( 'placement' => 'header' ) ) === sn_dark_mode_toggle_markup( array( 'placement' => 'header' ) ), 'theme-toggle passes placement through, NOT autop-wrapped (it was wp:html, never autop\'d)' );
ok( $blk( 'theme-toggle' ) === sn_dark_mode_toggle_markup(), 'theme-toggle with no attribute renders the footer form' );
ok( $blk( 'theme-toggle', array(), array( 'placement' => 'sidebar' ) ) === sn_dark_mode_toggle_markup(), 'an unknown placement falls back to footer, never passes through' );
ok( false === strpos( $blk( 'theme-toggle' ), '<autop>' ), 'theme-toggle render carries no <autop> wrapper' );
// An empty renderer output must stay empty after wpautop, not become a stray wrapper.
$GLOBALS['__empty'] = true;
ok( '' === $blk( 'updated-date' ), 'an empty renderer output (updated-date with nothing to show) stays empty through wpautop' );
unset( $GLOBALS['__empty'] );

echo "\nGroup: the templates place the blocks, not the shortcodes\n";
$root  = dirname( __DIR__ );
$files = array_merge( glob( $root . '/templates/*.html' ), glob( $root . '/parts/*.html' ) );
$migrated = array( 'sn_prov_chip', 'sn_prov_panel', 'sn_related_notes', 'sn_cited_by', 'sn_note_share', 'sn_note_reply', 'sn_updated_date', 'sn_post_pillar', 'sn_theme_toggle' );
$left = array(); $placed = array();
foreach ( $files as $f ) {
	$h = (string) file_get_contents( $f ); $rel = str_replace( $root . '/', '', $f );
	foreach ( $migrated as $s ) { if ( false !== strpos( $h, '[' . $s ) ) { $left[] = "$rel [$s"; } }
	if ( preg_match_all( '/<!-- wp:signal-noise\/([a-z-]+)( \{[^}]*\})? \/-->/', $h, $m ) ) { foreach ( $m[1] as $n ) { $placed[ $n ] = ( $placed[ $n ] ?? 0 ) + 1; } }
}
ok( array() === $left, 'no template or part emits a migrated shortcode' . ( $left ? ' — LEFT: ' . implode( ', ', $left ) : '' ) );
$want = array( 'prov-chip' => 1, 'prov-panel' => 1, 'related-notes' => 1, 'cited-by' => 1, 'note-share' => 1, 'note-reply' => 1, 'updated-date' => 1, 'post-pillar' => 1, 'theme-toggle' => 2 );
ksort( $want ); ksort( $placed );
ok( $want === $placed, 'each block is placed exactly where its shortcode was (toggle twice): ' . json_encode( $placed ) );
ok( false !== strpos( (string) file_get_contents( $root . '/parts/header.html' ), '<!-- wp:signal-noise/theme-toggle {"placement":"header"} /-->' ), 'the header toggle carries placement:header' );
ok( false !== strpos( (string) file_get_contents( $root . '/parts/footer.html' ), '<!-- wp:signal-noise/theme-toggle /-->' ), 'the footer toggle carries no attribute (default footer)' );
ok( false !== strpos( (string) file_get_contents( $root . '/templates/single.html' ), '[sn_reading_path]' ), 'the PLUGIN\'s [sn_reading_path] stays a shortcode — not this arc\'s' );
// Palette: every block this module registers is in the editor allowlist.
$palette = (string) file_get_contents( $root . '/inc/editor-block-palette.php' );
$missing = array_filter( $expected, static fn( $s ) => false === strpos( $palette, "'signal-noise/$s'" ) );
ok( array() === $missing, 'every PHP-only block is in the editor palette allowlist' . ( $missing ? ' — MISSING: ' . implode( ', ', $missing ) : '' ) );

echo "\nGroup: the editor canvas (v13.1.1) — an empty render under REST shows a labelled placeholder\n";
$GLOBALS['__rest'] = true; $GLOBALS['__empty'] = true;
$ph = $blk( 'updated-date' );
ok( false !== strpos( $ph, 'class="sn-block-placeholder"' ) && false !== strpos( $ph, 'Updated date' ), 'REST + empty renderer → a placeholder naming the block (the site editor otherwise shows nothing on canvas)' );
ok( $blk( 'prov-chip' ) === wpautop( sn_prov_chip_shortcode() ), 'REST + a non-empty renderer → the real output, untouched' );
$GLOBALS['__rest'] = false;
ok( '' === $blk( 'updated-date' ), 'front end + empty renderer → empty, never a placeholder (control)' );
$GLOBALS['__rest'] = true; $GLOBALS['__empty'] = false;
ok( $blk( 'updated-date' ) === wpautop( sn_updated_date_shortcode() ), 'REST + non-empty → real output (control)' );
$GLOBALS['__rest'] = false;
ok( false === strpos( $blk( 'theme-toggle' ), 'sn-block-placeholder' ), 'the toggle never needs a placeholder (it renders a button everywhere)' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
