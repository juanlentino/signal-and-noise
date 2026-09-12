<?php
/**
 * Test: the touch hover-reset for plain links no longer outranks a link's
 * own deliberate color (issue #321).
 *
 * `:root a:not(.wp-element-button):not(.wp-block-button__link):hover` is
 * (0,3,1) — high enough to beat every component-level link rule in the
 * codebase (`.sn-article-toc__list a` is (0,1,1), same as most others). On
 * touch, tapping ANY styled link (not just a bare default one) forced it to
 * blood/no-underline and stuck there until the next tap elsewhere — the
 * exact sticky-hover bug this reset exists to prevent, now landing on the
 * opposite population.
 *
 * Fix: wrap the whole selector in `:where()`, which always contributes zero
 * specificity. That keeps it winning against WordPress's own zero-specificity
 * `:root :where(a:hover)` (both zero, later source order wins) for a truly
 * bare default link, while any component rule with real specificity
 * (0,1,1)+ — every custom-colored link in the codebase — now wins on its own
 * terms instead of being overridden.
 *
 * Run: php tests/touch-link-reset-scope.php
 */

if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }

$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }

$css = (string) file_get_contents( dirname( __DIR__ ) . '/assets/css/components.css' );
ok( '' !== $css, 'components.css is readable' );

// #321: the link reset selector must be wrapped in :where() so it carries
// zero specificity and can no longer outrank a link's own color rule.
ok(
	(bool) preg_match(
		'/@media\s*\(\s*hover:\s*none\s*\)\s*\{.*?:where\(\s*:root a:not\(\.wp-element-button\):not\(\.wp-block-button__link\):hover\s*\)\s*\{\s*[^}]*color:\s*var\(--wp--preset--color--blood\)/s',
		$css
	),
	'#321: the plain-link touch reset is scoped inside :where(), zeroing its specificity'
);

// The button reset is a different bug surface (issue only concerns links) —
// it must stay exactly as-is, still winning without !important.
ok(
	(bool) preg_match( '/:root \.wp-block-button__link:hover,\s*\n?\s*:root \.wp-element-button:hover\s*\{/', $css ),
	'the button touch reset is untouched'
);

// A component with its own explicit link color (0,1,1) must be able to win
// a tie against the now zero-specificity reset by ordinary cascade rules —
// sanity that its rule is still present, unmodified by this fix.
ok(
	(bool) preg_match( '/\.sn-article-toc__list a\s*\{[^}]*color:\s*var\(--wp--preset--color--bone\)/s', $css ),
	'.sn-article-toc__list a keeps its own bone color rule, unmodified'
);

echo sprintf( "\nResult: %d passed, %d failed.\n", $pass, $fail );
exit( $fail > 0 ? 1 : 0 );
