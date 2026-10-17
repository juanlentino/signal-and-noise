<?php
/**
 * Every font file the theme ships is one it registers, and every one it
 * registers ships.
 *
 * WHY (2026.10, theme audit P3-3): four latin-ext .woff2 files rode every
 * release zip for months with nothing referencing them: no theme.json
 * fontFace, no CSS, no PHP. A file nothing loads is weight with no reader.
 *
 * Run: php tests/font-files.php
 */

if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) {
	http_response_code( 404 );
	exit;
}

$pass = 0;
$fail = 0;
function ok( $c, $m ) {
	global $pass, $fail;
	if ( $c ) { ++$pass; echo "PASS: $m\n"; } else { ++$fail; echo "FAIL: $m\n"; }
}

$root  = dirname( __DIR__ );
$theme = json_decode( (string) file_get_contents( $root . '/theme.json' ), true );
$registered = array();
foreach ( $theme['settings']['typography']['fontFamilies'] ?? array() as $family ) {
	foreach ( $family['fontFace'] ?? array() as $face ) {
		foreach ( (array) ( $face['src'] ?? array() ) as $src ) {
			$registered[] = basename( str_replace( 'file:./', '', $src ) );
		}
	}
}
ok( count( $registered ) > 0, 'theme.json registers font faces (' . count( $registered ) . ')' );

$shipped = array_map( 'basename', glob( $root . '/assets/fonts/*.{woff,woff2}', GLOB_BRACE ) );
foreach ( $shipped as $file ) {
	ok( in_array( $file, $registered, true ), "assets/fonts/$file is registered in theme.json" );
}
foreach ( $registered as $file ) {
	ok( in_array( $file, $shipped, true ), "registered font $file exists in assets/fonts/" );
}

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
