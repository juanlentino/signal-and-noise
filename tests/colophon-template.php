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
$pat = file_get_contents( "$root/patterns/colophon.php" );
ok( strpos( $tpl, 'wp:template-part' ) !== false && strpos( $tpl, 'header' ) !== false, 'template pulls the header part' );

$footer = file_get_contents( "$root/parts/footer.html" );
ok( strpos( $footer, '/colophon' ) !== false, 'footer links to /colophon' );

ok( strpos( $pat, 'Slug: signal-noise/colophon' ) !== false, 'colophon pattern declares its slug' );

// COL-1: cross-validate the slug round-trips between the template's wp:pattern
// reference and the pattern file's declared Slug. A one-char typo in the template
// slug renders a BLANK page (render_block_core_pattern returns '' for an
// unregistered slug) — an OR'd "any wp:pattern block exists" test wouldn't catch it.
preg_match( '/wp:pattern\s*\{[^}]*"slug":"([^"]+)"/', $tpl, $tpl_slug_m );
preg_match( '/Slug:\s*(\S+)/', $pat, $pat_slug_m );
$tpl_slug = $tpl_slug_m[1] ?? '';
$pat_slug = $pat_slug_m[1] ?? '';
ok( '' !== $tpl_slug && $tpl_slug === $pat_slug, "template wp:pattern slug ($tpl_slug) round-trips to the pattern Slug ($pat_slug)" );

echo "Result: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
