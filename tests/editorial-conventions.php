<?php
/**
 * Standalone test: the editorial convention registry (inc/editorial-conventions.php).
 *
 * The registry is data so it cannot go stale silently; this suite is what
 * makes that true. Every entry's selector must be styled by the theme's own
 * CSS (or be a block style the theme registers), every named pattern must be
 * a pattern file the theme ships, every exemplar must carry its own selector,
 * and every author-facing class the patterns use must be in the registry.
 *
 * Run: php tests/editorial-conventions.php
 *
 * @since 13.2.0
 */

if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
define( 'ABSPATH', '/' );
$GLOBALS['__actions'] = array(); $GLOBALS['__registered'] = array();
function add_action( $h, $c, $p = 10, $a = 1 ) { $GLOBALS['__actions'][ $h ][] = $c; }
function wp_register_ability( $slug, $args ) { $GLOBALS['__registered'][ $slug ] = $args; return true; }
function sn_theme_perm_read() { return true; }
require_once __DIR__ . '/../inc/editorial-conventions.php';

$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { ++$pass; echo "PASS: $m\n"; } else { ++$fail; echo "FAIL: $m\n"; } }

$root = dirname( __DIR__ );
$css  = '';
foreach ( glob( $root . '/assets/css/*.css' ) as $f ) { $css .= file_get_contents( $f ); }
$css .= file_get_contents( $root . '/style.css' );
// Comments stripped BEFORE scanning: a selector named in a comment is not a
// selector that paints, and the lead's own comment names `.sn-lead`.
$css = preg_replace( '~/\*.*?\*/~s', '', $css );
// The block styles are theme.json partials (styles/blocks/<slug>.json, #390):
// a convention's is-style- selector must name a partial whose slug matches.
$block_style_slugs = array();
foreach ( glob( $root . '/styles/blocks/*.json' ) as $f ) {
	$v = json_decode( (string) file_get_contents( $f ), true );
	if ( is_array( $v ) && ! empty( $v['blockTypes'] ) ) { $block_style_slugs[] = (string) ( $v['slug'] ?? basename( $f, '.json' ) ); }
}
$pattern_src  = '';
$pattern_slugs = array();
foreach ( glob( $root . '/patterns/*.php' ) as $f ) {
	$src = file_get_contents( $f ); $pattern_src .= $src;
	if ( preg_match( '/Slug:\s*([a-z0-9\/-]+)/', $src, $m ) ) { $pattern_slugs[] = $m[1]; }
}
$rows = sn_theme_editorial_conventions();

echo "Group 1: shape\n";
$required = array( 'id', 'kind', 'block', 'selector', 'pattern', 'label', 'exemplar', 'when', 'placement', 'anchor_reachable', 'unused' );
$ids = array_column( $rows, 'id' );
ok( count( $rows ) >= 8 && count( $ids ) === count( array_unique( $ids ) ), count( $rows ) . ' entries, ids unique' );
foreach ( $rows as $r ) {
	$missing = array_diff( $required, array_keys( $r ) );
	ok( array() === $missing && in_array( $r['kind'], SN_EDITORIAL_CONVENTION_KINDS, true ) && is_bool( $r['anchor_reachable'] ) && is_bool( $r['unused'] ), $r['id'] . ': every field present, kind in the enum, booleans are booleans' . ( $missing ? ' (missing ' . implode( ',', $missing ) . ')' : '' ) );
}

echo "\nGroup 2: parity with the CSS, the block styles and the pattern files (the anti-stale test)\n";
foreach ( $rows as $r ) {
	$sel = (string) $r['selector'];
	if ( '' === $sel ) { continue; }
	if ( 0 === strpos( $sel, 'is-style-' ) ) {
		$name = substr( $sel, 9 );
		ok( in_array( $name, $block_style_slugs, true ), $r['id'] . ': block style "' . $name . '" is a partial in styles/blocks/' );
	} elseif ( 0 === strpos( $sel, 'sn-' ) ) {
		ok( 1 === preg_match( '/\.' . preg_quote( $sel, '/' ) . '(?![\w-])/', $css ), $r['id'] . ': class .' . $sel . ' is styled by the theme CSS (whole token, not a prefix)' );
	} else {
		ok( 1 === preg_match( '/^(svg|h[1-6])/', $sel ), $r['id'] . ': a non-class selector names an element (' . $sel . ')' );
	}
	if ( '' !== (string) $r['pattern'] ) {
		ok( in_array( $r['pattern'], $pattern_slugs, true ), $r['id'] . ': pattern ' . $r['pattern'] . ' is a file the theme ships' );
	}
	// A dynamic block's class is painted by its render callback, not carried in
	// the serialized delimiter, so the exemplar cannot contain it by design.
	if ( '' !== (string) $r['exemplar'] && '' !== $sel && 'dynamic_block' !== $r['kind'] && 0 !== strpos( $sel, 'svg' ) && 0 !== strpos( $sel, 'h2' ) ) {
		ok( false !== strpos( $r['exemplar'], $sel ), $r['id'] . ': the exemplar carries its own selector' );
	}
}
// Reverse: every author-facing class a shipped pattern uses is in the registry
// (as a selector, or inside an exemplar). A pattern class nobody registered
// is the "convention discoverable only by reading a post" failure, again.
$pattern_classes = array();
preg_match_all( '/"className":"([^"]+)"/', $pattern_src, $pm );
foreach ( $pm[1] as $cls ) { foreach ( explode( ' ', $cls ) as $c ) { $pattern_classes[ $c ] = true; } }
$known = '';
foreach ( $rows as $r ) { $known .= ' ' . $r['selector'] . ' ' . $r['exemplar']; }
$orphans = array();
foreach ( array_keys( $pattern_classes ) as $c ) { if ( false === strpos( $known, $c ) ) { $orphans[] = $c; } }
ok( array() === $orphans, 'every className the pattern files use is in the registry (selector or exemplar)' . ( $orphans ? ' — MISSING: ' . implode( ', ', $orphans ) : '' ) );

echo "\nGroup 3: the decisions the audit made\n";
$by = array_column( $rows, null, 'id' );
ok( isset( $by['lead'] ) && 'sn-lead' === $by['lead']['selector'] && 1 === preg_match( '/\.sn-lead(?![\w-])/', $css ), 'lead: the standfirst gets a class and the CSS styles it' );
ok( isset( $by['correction'] ) && false !== stripos( $by['correction']['placement'], 'last block' ), 'correction: placement says LAST block' );
ok( isset( $by['svg-figure'] ) && false !== strpos( $by['svg-figure']['exemplar'], 'currentColor' ) && false !== strpos( $by['svg-figure']['exemplar'], '<desc' ) && false !== strpos( $by['svg-figure']['exemplar'], 'var(--wp--preset--color--blood' ) && false === strpos( $by['svg-figure']['exemplar'], 'font-family' ), 'svg-figure: exemplar uses currentColor, a <desc>, the blood token for the accent, and no font-family' );
ok( isset( $by['sidenote'] ) && false === $by['sidenote']['anchor_reachable'] && false === $by['svg-figure']['anchor_reachable'], 'sidenote and svg-figure are the two anchor-unreachable conventions' );
$unused = array_keys( array_filter( $by, static function ( $r ) { return ! empty( $r['unused'] ); } ) );
sort( $unused );
ok( array( 'epigraph', 'hairline', 'hero-dossier', 'pull-quote', 'section-constrained', 'signal-quote' ) === $unused, 'the six registered-but-unused forms are in, flagged unused (' . implode( ', ', $unused ) . ')' );
ok( 'references' === $rows[0]['id'] && 'correction' === $rows[1]['id'], 'ordered by corpus frequency: references (10 notes) first, correction (3) second' );
ok( false === strpos( wp_json_encode_stub( $rows ), "\u{2014}" ), 'no em dash anywhere in the registry copy' );
function wp_json_encode_stub( $v ) { return json_encode( $v, JSON_UNESCAPED_UNICODE ); }

echo "\nGroup 4: the ability\n";
sn_theme_register_editorial_conventions_ability();
$reg = $GLOBALS['__registered']['signal-and-noise/get-editorial-conventions'] ?? null;
ok( is_array( $reg ) && 'sn_theme_perm_read' === $reg['permission_callback'] && true === $reg['meta']['annotations']['readonly'], 'get-editorial-conventions registered, read permission, readonly' );
$out = sn_theme_ability_get_editorial_conventions();
ok( $out['count'] === count( $rows ) && $out['in_use'] === count( $rows ) - 6 && false !== strpos( $out['note'], 'BEFORE composing' ), 'the ability returns the registry, the counts, and a note that says when to call it' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
