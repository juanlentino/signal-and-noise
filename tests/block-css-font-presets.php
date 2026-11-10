<?php
/**
 * Guard: block stylesheets name the theme.json font presets, never retype the
 * stacks (2026-11-10). A retyped stack drifts silently when theme.json changes.
 *
 * Run: php tests/block-css-font-presets.php
 */
if ( PHP_SAPI !== 'cli' ) { exit; }
$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }
$root  = dirname( __DIR__ );
$theme = json_decode( (string) file_get_contents( "$root/theme.json" ), true );
$fams  = array();
foreach ( $theme['settings']['typography']['fontFamilies'] as $f ) { $fams[ $f['slug'] ] = $f['fontFamily']; }
ok( isset( $fams['body'], $fams['heading'] ), 'theme.json defines the body and heading stacks' );
$files = (array) glob( "$root/blocks/*/style.css" );
ok( count( $files ) >= 2, 'block stylesheets found (' . count( $files ) . ')' );
$uses = 0;
foreach ( $files as $f ) {
	$css = (string) file_get_contents( $f );
	foreach ( $fams as $slug => $stack ) {
		ok( false === strpos( $css, $stack ), basename( dirname( $f ) ) . "/style.css does not retype the $slug stack" );
	}
	$uses += preg_match_all( '/font-family:\s*var\(--wp--preset--font-family--(?:body|heading)\)/', $css );
}
ok( $uses >= 9, "the block stylesheets use the presets ($uses)" );
echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
