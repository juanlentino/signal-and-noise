<?php
/**
 * The /notes stylesheet lives in a file, not inside the renderer (v12.4.1).
 *
 * WHY: inc/page-notes-render.php was 1,007 lines, 702 of them a single inline
 * <style> block (23.1 KB) shipped inside every /notes/ response. It was also
 * the direct cause of a shipped bug — `.sn-notes-page`, the container rule,
 * was declared ONLY in that block, so /notes/subscribe/ (same class) rendered
 * flush against the viewport edge from v11.9.4 to v12.2.1.
 *
 * /notes was the only route-specific CSS in the theme that was not a
 * route-scoped file; index/resume/uses/now/accessibility/keyboard-nav all are.
 *
 * Run from theme root:  php tests/notes-css-extraction.php
 *
 * @since theme v12.4.1
 */

if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) {
	http_response_code( 404 );
	exit;
}

$pass = 0; $fail = 0;
function ok( $cond, $msg ) {
	global $pass, $fail;
	if ( $cond ) { $pass++; echo "PASS: $msg\n"; } else { $fail++; echo "FAIL: $msg\n"; }
}

echo "/notes stylesheet extraction (v12.4.1)\n\n";

$render = file_get_contents( __DIR__ . '/../inc/page-notes-render.php' );
$css_p  = __DIR__ . '/../assets/css/notes.css';

// 1. The renderer is a renderer.
ok( false === strpos( $render, '<style>' ), 'the renderer carries NO inline <style> block' );

// 2. The stylesheet exists and carries a real payload. A move that produced a
//    near-empty file would otherwise pass every other pin here.
ok( file_exists( $css_p ), 'assets/css/notes.css exists' );
$css = file_exists( $css_p ) ? file_get_contents( $css_p ) : '';
ok( strlen( $css ) > 10240, 'notes.css is > 10 KB (it is ~23 KB — a stub would mean the payload was dropped)' );

// 3. The load-bearing rules survived. Not all 60 selectors: a permanent test
//    cannot know what used to be there, and a hardcoded inventory breaks on the
//    first legitimate rename. These are the ones whose absence has already
//    caused a shipped bug or would break the page outright.
foreach ( array(
	'.sn-notes-page'       => 'the container (its absence WAS the v12.2.1 bug)',
	'.sn-notes-hero'       => 'the hero',
	'.sn-notes-row'        => 'the index row',
	'.sn-notes-row-title'  => 'the row title',
	'.sn-notes-pagination' => 'pagination',
	'.sn-notes-search'     => 'the search field',
) as $sel => $why ) {
	ok( false !== strpos( $css, $sel ), "notes.css declares $sel — $why" );
}

// 4. The container still carries all three declarations. The bug was not a
//    missing selector but missing geometry.
ok( 1 === preg_match( '/\.sn-notes-page\s*\{[^}]*max-width:\s*1320px/s', $css ), 'the container keeps max-width: 1320px' );
ok( 1 === preg_match( '/\.sn-notes-page\s*\{[^}]*margin:\s*0 auto/s', $css ), 'the container keeps margin: 0 auto' );
ok( 1 === preg_match( '/\.sn-notes-page\s*\{[^}]*160px/s', $css ), 'the container keeps the 160px fixed-footer clearance' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
