<?php
/**
 * Headings promoted from h3 to h2 keep their h3 size on phones.
 *
 * WHY (2026.10): /services/ and /provenance/objections/ jumped from h1 to h3,
 * so their h3s are staged as h2s (revisions 3625 and 3624). The owner's
 * condition was that nothing looks different. On phones (max-width 480px) the
 * h3s were shrunk to 1.3rem (20.8px) by a rule keyed on `h3`, so the same rule
 * has to reach the promoted h2s:
 *   - /services/: h2 inside a service-image column;
 *   - /provenance/objections/: h2 carrying the x-large preset (the h3 size at
 *     desktop), inside a group. `.has-x-large-font-size` is !important, so the
 *     selector must out-rank it (it does: two classes and an element).
 *
 * Run: php tests/heading-level-phone-size.php
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

$css = (string) preg_replace( '~/\*.*?\*/~s', '', (string) file_get_contents( dirname( __DIR__ ) . '/assets/css/responsive.css' ) );

// The rule that shrinks h3 to 1.3rem, wherever it sits; then its @media.
$rule = null;
if ( preg_match_all( '/([^{}]*)\{([^{}]*)\}/', $css, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) ) {
	foreach ( $m as $r ) {
		$sels = array_map( 'trim', explode( ',', $r[1][0] ) );
		if ( in_array( '.wp-block-group h3', $sels, true ) && preg_match( '/font-size:\s*1\.3rem\s*!important/', $r[2][0] ) ) {
			$rule = array( 'sels' => $sels, 'at' => $r[0][1] );
		}
	}
}
ok( null !== $rule, 'found the phone rule that shrinks .wp-block-group h3 to 1.3rem' );
$before = substr( $css, 0, $rule['at'] ?? 0 );
$media  = strrpos( $before, '@media' );
ok( false !== $media && false !== strpos( substr( $before, $media, 40 ), 'max-width: 480px' ), 'the rule lives in the phone (max-width: 480px) query' );
ok( in_array( '.wp-block-column:has(.sn-service-image) h2', $rule['sels'] ?? array(), true ), '/services/: a service heading as h2 shrinks like the h3 it was' );
ok( in_array( '.wp-block-group h2.has-x-large-font-size', $rule['sels'] ?? array(), true ), '/provenance/objections/: an x-large h2 shrinks like the h3 it was' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
