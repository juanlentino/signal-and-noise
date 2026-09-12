<?php
/**
 * Test: no `@media (hover: hover)` block is nested inside a
 * `@media (hover: none) ...` block (issue #326).
 *
 * The two conditions are mutually exclusive — a device either supports
 * hover or it doesn't — so a `(hover: hover)` block nested inside a
 * `(hover: none)` block can never match. `assets/css/responsive.css:462-485`
 * was exactly that, inert since the v12.0.3 mechanical hover-sweep wrapped
 * every `:hover` rule in `(hover: hover)`, including three that were already
 * inside a `(hover: none) and (pointer: coarse)` block.
 *
 * Run: php tests/dead-hover-media-nesting.php
 */

if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }

$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }

/**
 * True if a `(hover: hover)` @media block appears NESTED inside a
 * `(hover: none)` @media block anywhere in $css.
 *
 * @param string $css
 * @return bool
 */
function snt_has_impossible_hover_nesting( $css ) {
	if ( ! preg_match_all( '/@media[^{]*hover:\s*none[^{]*\{/i', $css, $outer, PREG_OFFSET_CAPTURE ) ) {
		return false;
	}
	$len = strlen( $css );
	foreach ( $outer[0] as $hit ) {
		$start = (int) $hit[1];
		$i     = $start + strlen( $hit[0] );
		$depth = 1;
		$outer_start_body = $i;
		while ( $i < $len && $depth > 0 ) {
			if ( '{' === $css[ $i ] ) {
				++$depth;
			} elseif ( '}' === $css[ $i ] ) {
				--$depth;
			}
			++$i;
		}
		$body = substr( $css, $outer_start_body, $i - $outer_start_body - 1 );
		if ( preg_match( '/@media[^{]*hover:\s*hover[^{]*\{/i', $body ) ) {
			return true;
		}
	}

	return false;
}

$css = (string) file_get_contents( dirname( __DIR__ ) . '/assets/css/responsive.css' );
ok( '' !== $css, 'responsive.css is readable' );

ok( snt_has_impossible_hover_nesting( '@media (hover: none) { @media (hover: hover) { a { color: red; } } }' ), 'negative control: the detector DOES find an impossible nesting' );
ok( ! snt_has_impossible_hover_nesting( '@media (hover: none) { a { color: red; } } @media (hover: hover) { a { color: blue; } }' ), 'negative control: two SIBLING blocks are not flagged' );

// #326
ok( ! snt_has_impossible_hover_nesting( $css ), '#326: responsive.css has no `(hover: hover)` block nested inside a `(hover: none)` block' );

echo sprintf( "\nResult: %d passed, %d failed.\n", $pass, $fail );
exit( $fail > 0 ? 1 : 0 );
