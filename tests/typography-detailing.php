<?php
/**
 * Static-guard tests for typographic detailing (C1, theme v10.5.0).
 *
 * C1 is pure CSS, verified visually by headless-Chrome computed style. This
 * fixture is the in-repo regression guard (mirrors tests/view-transitions.php
 * and tests/style-variations.php): it asserts the declarations are present in
 * assets/css/article.css (v10.49.0: moved verbatim from critical.css's back
 * half into the combined cascade) and that the hanging-punctuation rule was
 * broadened beyond the old single-post-only scope.
 *
 * @since theme v10.5.0
 */

// SECURITY: CLI-only fixture.
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }

$pass = 0; $fail = 0;
function ok( $cond, $msg ) { global $pass, $fail; if ( $cond ) { $pass++; echo "PASS: $msg\n"; } else { $fail++; echo "FAIL: $msg\n"; } }

$css = (string) file_get_contents( realpath( __DIR__ . '/..' ) . '/assets/css/article.css' );
ok( '' !== $css, 'article.css is readable' );

// Hanging punctuation present + broadened to the pull-quote prose root.
ok( strpos( $css, 'hanging-punctuation:' ) !== false, 'hanging-punctuation declared' );
ok(
	preg_match( '/\.wp-block-post-content,\s*\.sn-pull-quote__body\s*\{\s*hanging-punctuation:/', $css ) === 1,
	'hanging-punctuation broadened to .wp-block-post-content + .sn-pull-quote__body (not single-post only)'
);

// Tabular figures on numeric columns (forward-compat intent on the monospace body).
ok( strpos( $css, 'font-variant-numeric: tabular-nums' ) !== false, 'tabular-nums declared' );
ok( strpos( $css, '.sn-notes-pagination' ) !== false, 'tabular-nums targets a real number column (.sn-notes-pagination)' );
ok( strpos( $css, '.sn-disco-count' ) !== false, 'tabular-nums targets discography counts' );

// Opt-in diagonal-fractions utility (DM Mono carries `frac`).
ok( strpos( $css, '.sn-frac' ) !== false, '.sn-frac utility present' );
ok( strpos( $css, 'font-variant-numeric: diagonal-fractions' ) !== false, 'diagonal-fractions declared on the opt-in utility' );


// ── TEXT-WRAP (v11.13.0) ────────────────────────────────────────────────────
// The theme's identity is Bebas Neue display type at clamp(3rem, 8vw, 7rem).
// At that size a one-word last line is the most visible typographic defect on
// the site, and it is the exact defect `text-wrap: balance` exists to remove.
// Headings get `balance` (browser-capped at a handful of lines, so the cost is
// bounded); prose gets `pretty`, which only rescues the last line and is safe
// to run over a whole article body.
echo "\nGroup: text-wrap\n";

$base = (string) file_get_contents( realpath( __DIR__ . '/..' ) . '/assets/css/base.css' );
ok( '' !== $base, 'base.css is readable' );
ok( strpos( $base, 'text-wrap: balance' ) !== false, 'display headings declare text-wrap: balance' );

// Balance is for HEADINGS ONLY. Applied to a long body it is either ignored
// (past the browser's line cap) or a layout cost paid for nothing, so the
// selector list is asserted rather than left to drift onto prose later.
ok(
	preg_match( '/h1,\s*h2,\s*h3,/', $base ) === 1,
	'balance targets the heading elements, not a blanket selector'
);
foreach ( array( '.sn-index-headline', '.sn-notes-row-title', '.sn-notes-hero-title' ) as $sel ) {
	ok( strpos( $base, $sel ) !== false, "balance reaches the hand-rolled display class $sel" );
}

// Prose gets `pretty` — orphan control only, no re-balancing of every line.
ok( strpos( $css, 'text-wrap: pretty' ) !== false, 'prose declares text-wrap: pretty' );
ok(
	preg_match( '/text-wrap: pretty/', $css ) === 1 && strpos( $css, 'text-wrap: balance' ) === false,
	'article.css carries pretty for prose and does NOT balance body copy'
);

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
