<?php
/**
 * Test: the decorative "404" digits render as a `wp:html` block, not
 * `wp:paragraph` (issue #324).
 *
 * `aria-hidden="true"` is not part of core/paragraph's serialized
 * attributes, so it is not part of what the block's save function computes.
 * Opening templates/404.html in the Site Editor re-derives the expected
 * markup from the block's JSON attributes and finds it doesn't match the
 * saved HTML, flags the block invalid, and "attempt recovery" drops the
 * attribute — silently un-hiding the decorative digits from screen readers
 * again (the v8.5.6 fix regressing). `wp:html` never validates its content
 * against JSON attributes, so raw markup like this is immune to recovery.
 *
 * Run: php tests/404-digits-block-validity.php
 */

if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }

$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }

$html = (string) file_get_contents( dirname( __DIR__ ) . '/templates/404.html' );
ok( '' !== $html, 'templates/404.html is readable' );

ok( false !== strpos( $html, 'aria-hidden="true">404</p>' ), 'the decorative 404 markup with aria-hidden is still present' );

// #324: the <p aria-hidden="true">404</p> markup must be wrapped in
// `wp:html`, not `wp:paragraph` — a block comment pair whose content is
// exactly this <p>.
ok(
	(bool) preg_match( '/<!--\s*wp:html\s*-->\s*<p class="sn-404-digits[^"]*"[^>]*aria-hidden="true">404<\/p>\s*<!--\s*\/wp:html\s*-->/s', $html ),
	'#324: the 404 digits are wrapped in a wp:html block'
);
ok(
	0 === preg_match( '/<!--\s*wp:paragraph[^>]*sn-404-digits.*?aria-hidden="true">404<\/p>\s*<!--\s*\/wp:paragraph\s*-->/s', $html ),
	'#324: the digits are no longer inside a wp:paragraph block'
);

echo sprintf( "\nResult: %d passed, %d failed.\n", $pass, $fail );
exit( $fail > 0 ? 1 : 0 );
