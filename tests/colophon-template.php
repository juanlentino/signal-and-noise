<?php
/**
 * Tombstone tests for the removed /colophon theme template (v11.1.11).
 *
 * Since plugin v10.13.0 the /colophon page is CMS-owned: a Site Editor
 * wp_template override of page-colophon renders the plugin's [sn_colophon]
 * shortcode. The theme's slug-bound template file was permanently shadowed
 * dead code that would have resurrected stale hardcoded content if the DB
 * override were ever deleted — so it was removed, along with the pattern
 * that existed only to serve it and its theme.json customTemplates entry.
 *
 * These tests guard against resurrection.
 */
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', '/' ); }
$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }

$root = __DIR__ . '/..';

// The slug-bound template file and its pattern are GONE (CMS owns /colophon).
ok( ! file_exists( "$root/templates/page-colophon.html" ), 'page-colophon.html template is removed (CMS-owned via Site Editor override)' );
ok( ! file_exists( "$root/patterns/colophon.php" ), 'colophon pattern is removed (its only consumer was the removed template)' );

// theme.json no longer registers the dead template.
$theme = json_decode( file_get_contents( "$root/theme.json" ), true );
$ct = $theme['customTemplates'] ?? array();
$colophon = array_values( array_filter( $ct, fn( $t ) => ( $t['name'] ?? '' ) === 'page-colophon' ) );
ok( count( $colophon ) === 0, 'theme.json customTemplates has no page-colophon entry' );
ok( is_array( $ct ) && count( $ct ) > 0, 'other customTemplates entries survive the removal' );

// The route itself still exists site-side: the footer keeps linking /colophon.
$footer = file_get_contents( "$root/parts/footer.html" );
ok( strpos( $footer, '/colophon' ) !== false, 'footer links to /colophon (the CMS-owned page)' );

// No stray wp:pattern reference to the removed slug anywhere in templates/parts.
$stray = false;
foreach ( array_merge( glob( "$root/templates/*.html" ), glob( "$root/parts/*.html" ) ) as $f ) {
	if ( strpos( (string) file_get_contents( $f ), 'signal-noise/colophon' ) !== false ) { $stray = true; }
}
ok( ! $stray, 'no template or part references the removed signal-noise/colophon pattern' );

echo "Result: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
