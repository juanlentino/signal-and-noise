<?php
/**
 * Guard: the site uses a two-track width system. Every constrained-layout width
 * in every page template sits at exactly the reading track (760px) or the wide
 * track (1400px), and theme.json declares those two as the global contentSize /
 * wideSize. Single-column prose stays at the 760px reading measure; galleries,
 * card grids, credibility strips, timelines, multi-column media and the hero use
 * the 1400px wide track.
 *
 * Fails if an ad-hoc width (the old 600/680/700/720/800/900/1000/1100 scatter)
 * creeps back in — the whole point of the system is that there are only two.
 *
 * @since theme v9.15.2
 */

if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) {
	http_response_code( 404 );
	exit;
}
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/' );
}

$pass = 0;
$fail = 0;
function ok( $c, $m ) {
	global $pass, $fail;
	if ( $c ) {
		++$pass;
		echo "PASS: $m\n";
	} else {
		++$fail;
		echo "FAIL: $m\n";
	}
}

$root         = __DIR__ . '/..';
$reading      = '760px';
$wide         = '1400px';
$allowed      = array( $reading, $wide );

// ── theme.json declares the two tracks ───────────────────────────────
$tj     = json_decode( (string) file_get_contents( "$root/theme.json" ), true );
$layout = $tj['settings']['layout'] ?? array();
ok( ( $layout['contentSize'] ?? '' ) === $reading, "theme.json contentSize is the reading track ($reading)" );
ok( ( $layout['wideSize'] ?? '' ) === $wide, "theme.json wideSize is the wide track ($wide)" );

// ── Every template contentSize override is on a track ────────────────
$offenders = array();
foreach ( glob( "$root/templates/*.html" ) as $t ) {
	$html = (string) file_get_contents( $t );
	if ( preg_match_all( '/"contentSize":"([^"]+)"/', $html, $m ) ) {
		foreach ( $m[1] as $w ) {
			if ( ! in_array( $w, $allowed, true ) ) {
				$offenders[] = basename( $t ) . ' → ' . $w;
			}
		}
	}
}
ok( empty( $offenders ), 'every template contentSize is on a track' . ( $offenders ? ' (offenders: ' . implode( ', ', $offenders ) . ')' : '' ) );

// ── The wide-content pages actually carry the wide track ─────────────
// page-about is excluded here: its body now lives in the About Page's
// post_content (rendered via wp:post-content), not in the template file, so
// the template itself carries no contentSize override to check.
foreach ( array( 'page-music', 'page-services', 'page-resume', 'front-page' ) as $page ) {
	$html = (string) file_get_contents( "$root/templates/$page.html" );
	ok( strpos( $html, '"contentSize":"' . $wide . '"' ) !== false, "$page carries the wide track ($wide)" );
}

// ── Prose pages keep a readable measure (no width wider than the wide
//    track, and the text-forward pages never exceed the reading track) ─
foreach ( array( 'page-contact' ) as $page ) {
	$html = (string) file_get_contents( "$root/templates/$page.html" );
	ok(
		preg_match( '/"contentSize":"(?!' . preg_quote( $reading, '/' ) . ')/', $html ) !== 1,
		"$page (prose/form) stays on the reading track only"
	);
}

echo "Result: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
