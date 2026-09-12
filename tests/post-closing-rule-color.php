<?php
/**
 * Test: the closing rule (`.sn-post-closing__rule`) keeps its own color
 * against the generic separator override (issue #320).
 *
 * `article.css` sets `.sn-post-closing__rule { border-top: 1px solid bone }`,
 * no `!important`. `components.css` sets
 * `.wp-block-separator { border-color: concrete !important; opacity: .6 }`,
 * same (0,1,0) specificity as the expanded longhand, WITH `!important` — an
 * `!important` declaration always beats a non-`!important` one regardless of
 * specificity, so the closing rule rendered 1px concrete at 60% instead of
 * bone at full opacity. The markup carries both classes
 * (parts/post-closing.html): `<hr class="wp-block-separator ...
 * sn-post-closing__rule"/>`.
 *
 * Run: php tests/post-closing-rule-color.php
 */

if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }

$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }

$root    = dirname( __DIR__ );
$article = (string) file_get_contents( $root . '/assets/css/article.css' );
$components = (string) file_get_contents( $root . '/assets/css/components.css' );

ok( '' !== $article && '' !== $components, 'article.css and components.css are readable' );

ok(
	(bool) preg_match( '/\.wp-block-separator\s*\{[^}]*border-color:\s*var\(--wp--preset--color--concrete\)\s*!important[^}]*opacity:\s*0?\.6/s', $components ),
	'the generic separator override is still concrete at 60% opacity (unchanged)'
);

// #320: the closing rule must win against that !important override. Since an
// !important declaration only loses to a LATER !important declaration of
// equal-or-higher specificity, the fix must itself carry !important.
ok(
	(bool) preg_match( '/\.sn-post-closing__rule\s*\{[^}]*border-top-color:\s*var\(--wp--preset--color--bone\)\s*!important/s', $article ),
	'#320: .sn-post-closing__rule sets border-top-color to bone with !important'
);
ok(
	(bool) preg_match( '/\.sn-post-closing__rule\s*\{[^}]*opacity:\s*1\b/s', $article ),
	'#320: .sn-post-closing__rule resets opacity to 1, undoing the separator\'s 60%'
);

// article.css must still load AFTER components.css so an equal-!important,
// equal-specificity tie resolves in the closing rule's favor by source order.
$assets_frontend = (string) file_get_contents( $root . '/inc/assets-frontend.php' );
ok(
	strpos( $assets_frontend, "'sn-article" ) !== false && strpos( $assets_frontend, "array( 'sn-responsive' )" ) !== false,
	'article.css still depends on (and therefore loads after) the components stylesheet chain'
);

echo sprintf( "\nResult: %d passed, %d failed.\n", $pass, $fail );
exit( $fail > 0 ? 1 : 0 );
