<?php
/**
 * Guard: /resume prints as a two-page Letter document (docs/RESUME-PDF.md, Phase 1).
 *
 * Measured with headless Chrome print-to-PDF on the live page: 7 pages before
 * these rules, 2 after, and a Note printed byte-identical text with and without
 * them. What this pins is the set of rules each of those measurements needed:
 *
 *   - A NAMED page. @page cannot be scoped by a selector, so an unnamed rule
 *     would re-size every printed page on the site.
 *   - Every /resume rule scoped to body:has(.sn-resume-hero-split), the hook the
 *     plugin's resume engine emits only on /resume.
 *   - Chrome keeps a closed <details> in ::details-content, which `details > *`
 *     never reached: the early career was silently missing from every print.
 *   - `columns: 1` is still a multicol container, and Chrome fragments one badly
 *     (a page holding a single bullet); the list must be no multicol at all.
 *   - main's fixed-footer reserve (layout.css, 140px !important) printed a blank
 *     trailing page.
 *   - Ligatures and wide tracking corrupt the PDF text layer ("workow",
 *     "VOT ING"), which is what an applicant tracking system reads.
 *
 * Run: php tests/resume-print.php
 */

if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) {
	http_response_code( 404 );
	exit;
}

$pass = 0;
$fail = 0;
function ok( $cond, $label ) {
	global $pass, $fail;
	if ( $cond ) {
		++$pass;
		echo "PASS: $label\n";
	} else {
		++$fail;
		echo "FAIL: $label\n";
	}
}

$root = dirname( __DIR__ );
$src  = (string) file_get_contents( $root . '/assets/css/print.css' );
$css  = (string) preg_replace( '#/\*.*?\*/#s', '', $src );
$hook = 'body:has(.sn-resume-hero-split)';
$h    = preg_quote( $hook, '/' );

echo "resume-print\n\nGroup 1: the page\n";
ok( 1 === preg_match( '/@page\s+resume\s*\{[^}]*size:\s*letter/', $css ), 'a named @page resume sets Letter' );
ok( 1 === preg_match( '/' . $h . '\s*\{\s*page:\s*resume;\s*\}/', $css ), '/resume opts into the named page' );
preg_match_all( '/@page(\s+[a-z-]+)?\s*\{([^}]*)\}/', $css, $pages, PREG_SET_ORDER );
$unnamed = array_filter( $pages, static fn( $p ) => '' === trim( $p[1] ) );
$bad     = array_filter( $unnamed, static fn( $p ) => str_contains( $p[2], 'size' ) || str_contains( $p[2], 'in;' ) || str_contains( $p[2], 'in ' ) );
ok( count( $pages ) >= 2 && empty( $bad ), 'the site-wide @page keeps its own margins; no unnamed rule carries the resume size' );

echo "\nGroup 2: chrome and ground\n";
ok( 1 === preg_match( '/html,\s*body\s*\{[^}]*background:\s*#fff/', $css ), 'light ground' );
ok( 1 === preg_match( '/' . $h . ' \.sn-header > :not\(\.sn-site-title\)\s*\{\s*display:\s*none/', $css ), 'the header prints only its wordmark: nav and toggles hidden' );
ok( 1 === preg_match( '/\.sn-footer,/', $css ) && 0 === preg_match( '/' . $h . ' footer/', $css ), 'the footer is hidden by the global .sn-footer rule; /resume adds no bare footer selector (#318 keeps article footers)' );
ok( 1 === preg_match( '/' . $h . ' main \.wp-block-columns\.sn-resume-stats,/', $css ) && 1 === preg_match( '/\.sn-resume-stats,[^{]*\{\s*display:\s*none/', $css ), 'the stats band is hidden, at a specificity above the column flattening' );
ok( 1 === preg_match( '/\.sn-resume-download\s*\{\s*display:\s*none/', $css ), 'the Download button is hidden' );
ok( 1 === preg_match( '/' . $h . ' \.sn-resume-chips\s*\{\s*display:\s*none/', $css ), 'the credential chips (a duplicate of Credentials) are hidden' );
ok( 1 === preg_match( '/\.wp-block-post-content a\[href\^="http"\]::after\s*\{\s*content:\s*" \(" attr\(href\) "\)"/', $css ), 'links print their URL in full' );

echo "\nGroup 3: the measured failures\n";
ok( 1 === preg_match( '/' . $h . ' details::details-content\s*\{[^}]*content-visibility:\s*visible[^}]*display:\s*contents/', $css ), 'the closed early-career fold prints (::details-content opened, no box)' );
ok( 1 === preg_match( '/' . $h . ' \.sn-resume-list\s*\{\s*columns:\s*auto\s*!important;\s*column-count:\s*auto/', $css ), 'bullet lists are not a multicol container in print' );
ok( 1 === preg_match( '/' . $h . ' main\.wp-block-group\s*\{[^}]*padding-bottom:\s*0\s*!important/', $css ), 'the fixed-footer reserve is cleared, so no blank trailing page' );
ok( 1 === preg_match( '/font-variant-ligatures:\s*none/', $css ) && 1 === preg_match( '/letter-spacing:\s*0\.01em\s*!important/', $css ), 'the PDF text layer stays readable: no ligatures, no wide tracking' );
ok( 1 === preg_match( '/' . $h . ' \.sn-resume-pub,\s*' . $h . ' \.sn-resume-skills tr\s*\{\s*break-inside:\s*avoid/', $css ), 'a publication and a skills row never split across pages' );

echo "\nGroup 4: scope\n";
$start = strpos( $src, '/* ════════' );
$block = false === $start ? '' : (string) preg_replace( array( '#/\*.*?\*/#s', '/@page[^{]*\{[^}]*\}/' ), '', substr( $src, $start ) );
preg_match_all( '/(^|\})\s*([^{}@][^{]*)\{/', $block, $sel );
$loose = array();
foreach ( $sel[2] as $group ) {
	foreach ( array_map( 'trim', explode( ',', $group ) ) as $one ) {
		if ( '' !== $one && ! str_starts_with( $one, $hook ) ) {
			$loose[] = $one;
		}
	}
}
ok( '' !== $block && count( $sel[2] ) > 20, 'the /resume block was found and parsed (' . count( $sel[2] ) . ' rules)' );
ok( empty( $loose ), 'every /resume rule is scoped to ' . $hook . ( $loose ? ' (unscoped: ' . implode( ' | ', array_slice( $loose, 0, 3 ) ) . ')' : '' ) );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
