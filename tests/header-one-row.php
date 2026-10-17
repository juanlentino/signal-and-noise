<?php
/**
 * Standalone test: the header stays one row at every width from 375px up.
 *
 * The EN | ES switch (#540) made the header wrap. Measured live 2026-10-16:
 * 123px tall at 320-430 (the menu button dropped to a second row) and 141-142px
 * at 782-800 (the desktop nav wrapped under the logo), against a 68-104px one
 * row elsewhere. Every offset keyed to --sn-chrome-top (body padding, anchor
 * jumps, the /music rail) sat under the header at those widths.
 *
 * The owner's fix: the menu button runs to 899px; on phones the theme toggle
 * shows its dot only (its spoken name stays); the header's gaps tighten under
 * 400px; and below 375px EN | ES moves into the menu, as a copy inside
 * the menu dialog (inc/header-lang-menu.php), where focus can reach it.
 *
 * Run: php tests/header-one-row.php
 */

if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
define( 'ABSPATH', '/' );
$GLOBALS['__filters'] = array();
function add_filter( $h, $c, $p = 10, $a = 1 ) { $GLOBALS['__filters'][ $h ][] = $c; }
require_once __DIR__ . '/lib/wp-html-api.php';
snt_require_wp_html_api();

$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { ++$pass; echo "PASS: $m\n"; } else { ++$fail; echo "FAIL: $m\n"; } }

$root = dirname( __DIR__ );
$crit = preg_replace( '~/\*.*?\*/~s', '', (string) file_get_contents( $root . '/assets/css/critical.css' ) );

echo "Group 1: CSS\n";
ok( 1 === preg_match( '/@media \(min-width: 600px\) and \(max-width: 899px\)\s*\{[^@]*responsive-container-open[^}]*display:\s*flex/s', $crit ), 'the menu button shows up to 899px (was 781px)' );
ok( false === strpos( $crit, '@media (min-width: 600px) and (max-width: 781px)' ), 'the old 781px menu-button query is gone' );
$at = static function ( $q ) use ( $crit ) {
	$i = strpos( $crit, '@media (max-width: ' . $q . ')' );
	return false === $i ? '' : (string) substr( $crit, $i, 900 );
};
$m480 = $at( '480px' );
ok( 1 === preg_match( '/\.sn-header \.sn-theme-toggle__label\s*\{[^}]*clip-path:\s*inset\(50%\)/', $m480 ), 'at 480px and below the toggle word is visually hidden (not display:none, so the button keeps its name)' );
ok( 0 === preg_match( '/\.sn-theme-toggle__label\s*\{[^}]*display:\s*none/', $crit ), '...and never display:none' );
ok( 1 === preg_match( '/\.wp-block-group\.sn-header\s*\{[^}]*column-gap:\s*8px/', $at( '400px' ) ), 'under 400px the header gaps tighten to 8px' );
$m360 = $at( '374px' );
ok( 1 === preg_match( '/\.sn-header > \.sn-lang-switch\s*\{[^}]*display:\s*none/', $m360 ), 'below 375px (the first width the row fits) the header switch hides' );
ok( 1 === preg_match( '/\.sn-lang-switch--menu\s*\{[^}]*display:\s*inline-flex/', $m360 ), '...and the copy in the menu shows' );
ok( 1 === preg_match( '/^\.sn-lang-switch--menu\s*\{[^}]*display:\s*none/m', $crit ), 'from 375px up the menu copy is display:none, so only one switch is ever in the accessibility tree' );

echo "\nGroup 2: the copy inside the menu\n";
$GLOBALS['__lang'] = '<nav class="sn-lang-switch" aria-label="Language"><span class="sn-lang-switch__seg is-current" aria-current="true">EN</span><a class="sn-lang-switch__seg" href="https://x.test/es/" hreflang="es" lang="es-AR" aria-label="Español (ES)">ES</a></nav>';
function sn_lang_toggle_markup() { return $GLOBALS['__lang']; }
require_once __DIR__ . '/../inc/header-lang-menu.php';
ok( in_array( 'sn_header_lang_menu', $GLOBALS['__filters']['render_block_core/navigation'] ?? array(), true ), 'hooked on render_block_core/navigation' );
$nav  = '<nav class="wp-block-navigation" aria-label="Main navigation"><button class="wp-block-navigation__responsive-container-open">Open</button><div class="wp-block-navigation__responsive-container" id="m"><div class="wp-block-navigation__responsive-close"><div class="wp-block-navigation__responsive-dialog"><button class="wp-block-navigation__responsive-container-close">Close</button><div class="wp-block-navigation__responsive-container-content" id="m-content"><ul class="wp-block-navigation__container"><li>Home</li></ul></div></div></div></div></nav>';
$main = array( 'blockName' => 'core/navigation', 'attrs' => array( 'ariaLabel' => 'Main navigation', 'overlayMenu' => 'mobile' ) );
$out  = sn_header_lang_menu( $nav, $main );
ok( false !== strpos( $out, '<div class="wp-block-navigation__responsive-container-content" id="m-content"><nav class="sn-lang-switch sn-lang-switch--menu"' ), 'the switch is the first thing inside the menu dialog\'s content, where the dialog\'s focus trap can reach it' );
ok( 1 === substr_count( $out, '<nav class="sn-lang-switch' ) && 1 === substr_count( $out, 'sn-lang-switch--menu' ), 'exactly one copy added' );
ok( strip_tags( str_replace( array( 'EN', 'ES' ), '', $out ) ) === strip_tags( $nav ), 'nothing else in the nav changes' );
ok( false === strpos( $out, 'data-sn-lang-slot' ), 'the insertion marker does not survive' );
ok( $nav === sn_header_lang_menu( $nav, array( 'blockName' => 'core/navigation', 'attrs' => array( 'ariaLabel' => 'Footer' ) ) ), 'another navigation (not the main one) is left alone' );
ok( $nav === sn_header_lang_menu( $nav, array( 'blockName' => 'core/navigation', 'attrs' => array( 'ariaLabel' => 'Main navigation', 'overlayMenu' => 'never' ) ) ), 'a main navigation with no overlay is left alone' );
$GLOBALS['__lang'] = '';
ok( $nav === sn_header_lang_menu( $nav, $main ), 'no switch on this page (no Spanish pair, plugin absent): nothing added' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
