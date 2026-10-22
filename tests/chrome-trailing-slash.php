<?php
/**
 * Guard: every internal link the theme's chrome writes (header nav, footer
 * meta-nav, templates, parts, block renders) is the canonical trailing-slash
 * permalink. "/about" is a 301 to "/about/", so a slashless link sends every
 * reader and crawler through a redirect (2026-10-22: 108 of 109 inbound links
 * to /about/ were the header's "/about").
 *
 * patterns/home-hero.php is the home page's content (Page 383 is seeded from
 * it), which the owner keeps off-limits, so it is not scanned here.
 *
 * Run: php tests/chrome-trailing-slash.php
 */
if ( PHP_SAPI !== 'cli' ) { exit; }
$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }

$root  = dirname( __DIR__ );
$files = array_merge(
	glob( $root . '/parts/*.html' ),
	glob( $root . '/templates/*.html' ),
	glob( $root . '/blocks/*/render.php' ),
	glob( $root . '/inc/lang-chrome*.php' )
);
ok( count( $files ) > 10, 'scans the parts, templates, block renders and lang chrome (' . count( $files ) . ' files)' );

$seen = 0;
foreach ( $files as $file ) {
	$src = (string) file_get_contents( $file );
	// href="/x" in markup and "url":"/x" in block attributes; root-relative only.
	preg_match_all( '#(?:href=\\\\?"|"url":")(/[^"\\\\]*)#', $src, $m );
	foreach ( $m[1] as $path ) {
		$bare = (string) preg_replace( '/[?#].*$/', '', $path );
		if ( '/' === $bare || '//' === substr( $path, 0, 2 ) || false !== strpos( basename( $bare ), '.' ) ) {
			continue; // home, protocol-relative, or a file (feed.xml, humans.txt).
		}
		$seen++;
		ok( '/' === substr( $bare, -1 ), basename( dirname( $file ) ) . '/' . basename( $file ) . ': ' . $path . ' ends with "/"' );
	}
}
ok( $seen >= 10, 'found the chrome links to check (' . $seen . ')' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
