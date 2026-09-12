<?php
/**
 * Test: the phone-width footer-clearance reserve reaches block templates too
 * (issue #316).
 *
 * layout.css sets a permanent bottom reserve so the fixed footer never covers
 * page content: `main.wp-block-group { padding-bottom: 140px !important }`
 * (0,1,1). responsive.css shrinks that reserve at <= 480px, but its rule was
 * `main { ... }` (0,0,1) — lower specificity, so it lost to layout.css's rule
 * on every block-template page, leaving 140px of dead scroll below the
 * static footer. `/notes` (element `main.sn-notes-page`, no `.wp-block-group`)
 * was measured clean and the block-template case went unnoticed.
 *
 * Run: php tests/footer-reserve-block-templates.php
 */

if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }

$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }

$root      = dirname( __DIR__ );
$layout    = (string) file_get_contents( $root . '/assets/css/layout.css' );
$responsive = (string) file_get_contents( $root . '/assets/css/responsive.css' );

ok( '' !== $layout && '' !== $responsive, 'layout.css and responsive.css are readable' );

// The permanent reserve layout.css sets on `main.wp-block-group`.
ok(
	(bool) preg_match( '/main\.wp-block-group\s*\{[^}]*padding-bottom:\s*140px\s*!important/s', $layout ),
	'layout.css still sets the 140px reserve on main.wp-block-group'
);

// The phone-width override at <= 480px must be able to win against that
// (0,1,1) rule — either by matching the same class, or the plain element
// alone loses the specificity fight. `main, main.wp-block-group { ... }` is
// the minimal shape; anything giving `main.wp-block-group` its own
// padding-bottom inside the <= 480px block also satisfies the contract.
if ( preg_match( '/@media\s*\(\s*max-width:\s*480px\s*\)\s*\{(.*)\}\s*$/s', $responsive, $block_m )
	|| preg_match( '/@media\s*\(\s*max-width:\s*480px\s*\)\s*\{/', $responsive )
) {
	// Isolate just the <= 480px block by brace-depth, mirroring how the
	// ios-form-zoom-guard test extracts a media block.
	$start = strpos( $responsive, '@media (max-width: 480px)' );
	ok( false !== $start, 'a `@media (max-width: 480px)` block exists in responsive.css' );
	$block = '';
	if ( false !== $start ) {
		$open  = strpos( $responsive, '{', $start );
		$i     = $open + 1;
		$depth = 1;
		$len   = strlen( $responsive );
		while ( $i < $len && $depth > 0 ) {
			if ( '{' === $responsive[ $i ] ) {
				++$depth;
			} elseif ( '}' === $responsive[ $i ] ) {
				--$depth;
			}
			++$i;
		}
		$block = substr( $responsive, $open + 1, $i - $open - 2 );
	}
	ok(
		(bool) preg_match( '/(^|[,\s])main\s*,\s*main\.wp-block-group\s*\{[^}]*padding-bottom:/s', $block )
			|| (bool) preg_match( '/(^|[,\s])main\.wp-block-group\s*,\s*main\s*\{[^}]*padding-bottom:/s', $block ),
		'#316: the <= 480px block-level padding-bottom rule\'s selector list includes main.wp-block-group, not just main'
	);
} else {
	ok( false, 'a `@media (max-width: 480px)` block exists in responsive.css' );
}

echo sprintf( "\nResult: %d passed, %d failed.\n", $pass, $fail );
exit( $fail > 0 ? 1 : 0 );
