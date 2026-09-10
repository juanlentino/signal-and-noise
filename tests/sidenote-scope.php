<?php
/**
 * The sidenote must work on every template that renders prose, not only notes.
 *
 * Until v12.20.4 BOTH sidenote rules — the >=1280px float and the narrower
 * inline fallback — were scoped to `.single-post`. A sidenote inserted on a
 * PAGE therefore rendered with NO styling whatsoever: no float, no margin
 * escape, no hairline, no mono face. Not a degraded sidenote; an invisible
 * one, indistinguishable from the paragraph it annotates. Nothing anywhere
 * pinned either rule, so nothing said so.
 *
 * The scope is now `.wp-block-post-content`, which is what the device actually
 * requires — a constrained prose column with room beside it — and is the same
 * scope the pull-quote already used, which is exactly why THAT block worked
 * everywhere and this one did not.
 *
 * Run: php tests/sidenote-scope.php
 *
 * @since theme v12.20.4
 */

if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }

$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }

$raw = (string) file_get_contents( __DIR__ . '/../assets/css/article.css' );
// COMMENT-STRIPPED: the block comment above these rules EXPLAINS the old
// `.single-post` scope and therefore contains it. A raw scan reports the bug as
// still present in the fixed file — three instances of that shape on
// 2026-09-09 alone.
$css = (string) preg_replace( '#/\*.*?\*/#s', '', $raw );
ok( $css !== $raw, 'VACUITY: article.css carries comments, so stripping them is doing work' );

// Every rule that styles a sidenote.
preg_match_all( '/([^{}]*\.sn-sidenote[^{}]*)\{([^}]*)\}/s', $css, $m, PREG_SET_ORDER );
ok( count( $m ) >= 2, 'both sidenote rules were located (float + narrow fallback), found ' . count( $m ) );

$selectors = array_map( static fn( $r ) => trim( preg_replace( '/\s+/', ' ', $r[1] ) ), $m );

// THE PROPERTY. Not "the selector reads X" — that would re-break the moment
// someone picks a different correct scope. What must hold is that NO sidenote
// rule is confined to a single post type.
foreach ( $selectors as $sel ) {
	ok( false === strpos( $sel, 'single-post' ), "no sidenote rule is confined to .single-post — got: $sel" );
}

// And that each one is reachable from a prose column, so widening did not
// simply delete the scope and leak the float into headers, footers or widgets.
foreach ( $selectors as $sel ) {
	ok(
		false !== strpos( $sel, 'wp-block-post-content' ) || false !== strpos( $sel, 'entry-content' ),
		"and each is scoped to the prose column rather than unscoped — got: $sel"
	);
}

// BOTH breakpoints. The narrow rule is the one that would silently strand a
// sidenote as bare body text on a phone, which is the harder failure to see.
$has_float    = (bool) preg_match( '/@media\s*\(\s*min-width:\s*1280px\s*\)\s*\{[^}]*\.sn-sidenote[^}]*float:\s*right/s', $css );
$has_fallback = (bool) preg_match( '/@media\s*\(\s*max-width:\s*1279[^)]*\)\s*\{[^}]*\.sn-sidenote[^}]*float:\s*none/s', $css );
ok( $has_float, 'the >=1280px float rule survives the rescope' );
ok( $has_fallback, 'and so does the narrower inline fallback' );

// The margin escape is load-bearing and its importance is not decorative: core
// sets margin-right:auto !important on children of .is-layout-constrained, and
// auto on a float resolves to 0. Equal importance plus higher specificity wins.
ok(
	(bool) preg_match( '/\.sn-sidenote\s*\{[^}]*margin-right:\s*-\d+px\s*!important/s', $css ),
	'the negative margin keeps its !important — without it the escape is silently dead'
);

// Specificity must stay at two classes, which is what beats core's (0,1,0).
foreach ( $selectors as $sel ) {
	ok( 2 === substr_count( $sel, '.' ), "selector keeps two-class specificity to outrank core's layout rule — got: $sel" );
}

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
