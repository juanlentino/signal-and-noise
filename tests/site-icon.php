<?php
/**
 * Tests: core's Site Icon is printed, with the theme's SVG first; with no Site
 * Icon set, the theme's own set still prints (inc/dark-mode.php, 2026-11-10).
 *
 * Both branches run core's real wp_site_icon() from 7.1.4 against the theme's
 * real filter and wp_head callback.
 *
 * Run: php tests/site-icon.php
 */
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', '/' ); }

$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }

$GLOBALS['__a'] = array(); $GLOBALS['__f'] = array();
function add_action( $h, $cb = null, $p = 10, $n = 1 ) { $GLOBALS['__a'][] = array( $h, $cb, $p ); return true; }
function add_filter( $h, $cb = null, $p = 10, $n = 1 ) { $GLOBALS['__f'][ $h ][] = $cb; return true; }
function apply_filters( $h, $v ) { foreach ( $GLOBALS['__f'][ $h ] ?? array() as $cb ) { $v = $cb( $v ); } return $v; }
function add_shortcode() { return true; }
function esc_js( $s ) { return (string) $s; }
function esc_url( $u ) { return (string) $u; }
function esc_attr( $a ) { return htmlspecialchars( (string) $a, ENT_QUOTES ); }
function get_theme_file_uri( $p = '' ) { return 'https://x.test/t/' . $p; }
function sn_asset_ver( $p ) { return '9'; }
function add_query_arg( $k, $v, $u ) { return $u . '?' . $k . '=' . $v; }
function is_customize_preview() { return false; }
function has_site_icon() { return ! empty( $GLOBALS['__icon'] ); }
function get_site_icon_url( $size ) { return empty( $GLOBALS['__icon'] ) ? '' : "https://x.test/up/icon-$size.png"; }

require __DIR__ . '/../inc/dark-mode.php';

// core's wp_site_icon(), verbatim from wp-includes/general-template.php (7.1.4).
function core_wp_site_icon() {
	if ( ! has_site_icon() && ! is_customize_preview() ) {
		return;
	}
	$meta_tags = array();
	$icon_32   = get_site_icon_url( 32 );
	if ( $icon_32 ) {
		$meta_tags[] = sprintf( '<link rel="icon" href="%s" sizes="32x32" />', esc_url( $icon_32 ) );
	}
	$icon_192 = get_site_icon_url( 192 );
	if ( $icon_192 ) {
		$meta_tags[] = sprintf( '<link rel="icon" href="%s" sizes="192x192" />', esc_url( $icon_192 ) );
	}
	$icon_180 = get_site_icon_url( 180 );
	if ( $icon_180 ) {
		$meta_tags[] = sprintf( '<link rel="apple-touch-icon" href="%s" />', esc_url( $icon_180 ) );
	}
	$icon_270 = get_site_icon_url( 270 );
	if ( $icon_270 ) {
		$meta_tags[] = sprintf( '<meta name="msapplication-TileImage" content="%s" />', esc_url( $icon_270 ) );
	}
	$meta_tags = apply_filters( 'site_icon_meta_tags', $meta_tags );
	$meta_tags = array_filter( $meta_tags );
	foreach ( $meta_tags as $meta_tag ) {
		echo "$meta_tag\n";
	}
}

function head() {
	ob_start();
	foreach ( $GLOBALS['__a'] as $a ) {
		if ( 'wp_head' === $a[0] && 1 === (int) $a[2] && is_callable( $a[1] ) ) { call_user_func( $a[1] ); }
	}
	core_wp_site_icon(); // wp_head priority 99
	return (string) ob_get_clean();
}

$GLOBALS['__icon'] = true;
$h = head();
ok( 1 === substr_count( $h, 'favicon.svg' ) && 1 === preg_match_all( '/<link rel="icon"[^>]*type="image\/svg\+xml"/', $h ), 'Site Icon set: exactly one SVG icon' );
ok( strpos( $h, 'favicon.svg?ver=9' ) < strpos( $h, 'icon-32.png' ), 'and it comes first in core\'s list, versioned' );
ok( 1 === substr_count( $h, 'rel="apple-touch-icon"' ) && false !== strpos( $h, 'up/icon-180.png' ), 'one apple-touch-icon, core\'s, from the uploaded Site Icon' );
ok( false === strpos( $h, 'favicon.ico' ) && false === strpos( $h, 'assets/brand/apple-touch-icon.png' ), 'the theme prints no second .ico or touch icon' );
ok( false !== strpos( $h, 'msapplication-TileImage' ) && 2 === substr_count( $h, 'name="theme-color"' ), "core's other tags and the theme-color metas stay" );

$GLOBALS['__icon'] = false;
$h = head();
ok( 1 === preg_match_all( '/<link rel="icon"[^>]*type="image\/svg\+xml"/', $h ) && false !== strpos( $h, 'assets/brand/favicon.ico?ver=9' ), 'no Site Icon: the theme set prints, SVG and .ico' );
ok( 1 === substr_count( $h, 'rel="apple-touch-icon"' ) && false !== strpos( $h, 'assets/brand/apple-touch-icon.png' ), 'and its one opaque touch icon' );
ok( false === strpos( $h, 'up/icon-' ), 'core prints nothing without a Site Icon' );

echo "Result: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
