<?php
/**
 * Standalone structural test for the forced-colors / prefers-contrast a11y block
 * in assets/css/base.css (v10.20.0).
 *
 * The brutalist black-on-white base is already maximal contrast, so the block's
 * job is narrow: keep the keyboard focus ring visible (custom outline colours get
 * remapped under Windows High Contrast Mode — pin a system colour) and preserve
 * the reading-progress fill (it conveys position via colour). This guards that the
 * block ships and targets those exact elements.
 *
 * @since theme v10.20.0
 */

if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }

$pass = 0; $fail = 0;
function ok( $cond, $msg ) { global $pass, $fail; if ( $cond ) { $pass++; echo "PASS: $msg\n"; } else { $fail++; echo "FAIL: $msg\n"; } }

$css = (string) file_get_contents( __DIR__ . '/../assets/css/base.css' );

// --- forced-colors: active (Windows High Contrast Mode) ---
$pos = strpos( $css, '@media (forced-colors: active)' );
ok( false !== $pos, 'base.css has a forced-colors: active block (Windows High Contrast Mode)' );
$fc = false !== $pos ? substr( $css, $pos ) : '';
ok( strpos( $fc, ':focus-visible' ) !== false, 'forced-colors block keeps :focus-visible rings (keyboard focus stays visible)' );
ok( preg_match( '/outline:[^;}]*(Highlight|CanvasText)/', $fc ) === 1, 'focus ring pins to a system colour (Highlight/CanvasText), not a remapped custom colour' );
ok( strpos( $fc, '.sn-article-progress__fill' ) !== false, 'forced-colors block preserves the reading-progress fill (colour-conveyed UI)' );

// --- prefers-contrast: more ---
ok( strpos( $css, '@media (prefers-contrast: more)' ) !== false, 'base.css has a prefers-contrast: more block' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
