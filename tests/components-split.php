<?php
/**
 * The component layer as ordered parts (2026-10-31): assets/css/components.css
 * was cut at top-level rule boundaries into assets/css/components-NN-*.css.
 *
 * Properties: (1) every part the theme lists exists, and every
 * components-*.css file on disk is listed (a new part cannot load nowhere, or
 * load without being listed); (2) the list is in numeric order, which is the
 * cascade; (3) the bundle, the editor and the tests' joined source all take
 * the parts in that order, where components.css stood (after layout, before
 * responsive); (4) each part starts at the top level (a part that opened
 * inside a rule or an @media block would change the cascade when split).
 *
 * Run: php tests/components-split.php
 */
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
define( 'ABSPATH', '/' );
$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }
function get_theme_file_path( $rel ) { return dirname( __DIR__ ) . '/' . $rel; }
require dirname( __DIR__ ) . '/inc/components-css.php';
require dirname( __DIR__ ) . '/inc/asset-combine.php';
require __DIR__ . '/lib/components-source.php';

$root  = dirname( __DIR__ );
$parts = sn_components_css_parts();

echo "Group: the list and the disk agree\n";
ok( count( $parts ) >= 2, count( $parts ) . ' parts listed' );
ok( array() === array_filter( $parts, static fn( $p ) => ! is_file( "$root/$p" ) ), 'every listed part exists' );
$disk = array_map( static fn( $f ) => 'assets/css/' . basename( $f ), glob( "$root/assets/css/components-*.css" ) );
sort( $disk );
$listed = $parts;
sort( $listed );
ok( $disk === $listed, 'every components-*.css on disk is listed, and nothing else' );
ok( ! is_file( "$root/assets/css/components.css" ), 'components.css itself is gone (no second copy of the layer)' );
$nums = array_map( static fn( $p ) => (int) substr( basename( $p ), 11, 2 ), $parts );
ok( range( 1, count( $parts ) ) === $nums, 'the list runs 01, 02, ... in order: the order IS the cascade' );

echo "\nGroup: every consumer takes the parts in order, where components.css stood\n";
$src = sn_css_combine_sources();
$at  = array_search( $parts[0], $src, true );
ok( false !== $at && array_slice( $src, $at, count( $parts ) ) === $parts, 'the bundle lists the parts contiguously, in order' );
ok( 'assets/css/layout.css' === ( $src[ $at - 1 ] ?? '' ) && 'assets/css/responsive.css' === ( $src[ $at + count( $parts ) ] ?? '' ), 'between layout.css and responsive.css, where components.css was' );
ok( array( 'assets/css/components-*' ) === array_values( array_unique( array_map( 'sn_css_combine_label', $parts ) ) ), 'all parts combine under one label, as one unit' );
ok( snt_components_parts() === $parts, 'the tests\' joined source reads the same list' );
$setup = (string) file_get_contents( "$root/inc/setup.php" );
ok( 1 === preg_match( "/'assets\\/css\\/layout\\.css',\\s*\\),\\s*sn_components_css_parts\\(\\),\\s*array\\(\\s*'assets\\/css\\/critical\\.css'/", $setup ), 'the editor loads the parts after layout.css, before critical.css, as it loaded components.css' );

echo "\nGroup: every part starts at the top level\n";
$bad = array();
$joined = '';
foreach ( $parts as $p ) {
	$css   = (string) file_get_contents( "$root/$p" );
	$plain = (string) preg_replace( '~/\*.*?\*/~s', '', $css );
	$plain = (string) preg_replace( '~"[^"]*"|\'[^\']*\'~', '""', $plain );
	// Balanced blocks, no comment left open, and the last thing in the part
	// (comments aside) closes a rule: a cut inside a comment, or between a
	// selector and its brace, breaks the per-file fallback even when the
	// bundle (which joins the parts first) still reads right (review 2026-10-31).
	$open_comments = substr_count( $css, '/*' ) !== substr_count( $css, '*/' );
	if ( $open_comments || substr_count( $plain, '{' ) !== substr_count( $plain, '}' ) || '}' !== substr( rtrim( $plain ), -1 ) ) {
		$bad[] = basename( $p );
	}
	$joined .= $css;
}
ok( array() === $bad, 'each part closes its own blocks and comments and ends on a closed rule' . ( $bad ? ': ' . implode( ', ', $bad ) : '' ) );
ok( $joined === snt_components_source(), 'the joined source is the parts with nothing between them' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
