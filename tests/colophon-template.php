<?php
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', '/' ); }
$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }

$root = __DIR__ . '/..';
$theme = json_decode( file_get_contents( "$root/theme.json" ), true );
$ct = $theme['customTemplates'] ?? array();
$colophon = array_values( array_filter( $ct, fn( $t ) => ( $t['name'] ?? '' ) === 'page-colophon' ) );
ok( count( $colophon ) === 1, 'theme.json customTemplates has a page-colophon entry' );
ok( ( $colophon[0]['title'] ?? '' ) === 'Colophon', 'colophon template title is "Colophon"' );
ok( in_array( 'page', $colophon[0]['postTypes'] ?? array(), true ), 'colophon template applies to pages' );

ok( file_exists( "$root/templates/page-colophon.html" ), 'page-colophon.html template exists' );
ok( file_exists( "$root/patterns/colophon.php" ), 'colophon pattern exists' );

$tpl = file_get_contents( "$root/templates/page-colophon.html" );
ok( strpos( $tpl, 'wp:template-part' ) !== false && strpos( $tpl, 'header' ) !== false, 'template pulls the header part' );
ok( strpos( $tpl, 'signal-noise/colophon' ) !== false || strpos( $tpl, 'wp:pattern' ) !== false, 'template references the colophon pattern' );

$footer = file_get_contents( "$root/parts/footer.html" );
ok( strpos( $footer, '/colophon' ) !== false, 'footer links to /colophon' );

$pat = file_get_contents( "$root/patterns/colophon.php" );
ok( strpos( $pat, 'Slug: signal-noise/colophon' ) !== false, 'colophon pattern declares its slug' );

echo "Result: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
