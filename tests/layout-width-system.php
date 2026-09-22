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
$wide         = '1600px'; // 14.1.0: one wider shared page track (owner, 2026-09-22)
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
// page-about/services/music/resume are excluded here: their wide track (1400px)
// now lives in the Page post_content (moved by the pages-to-CMS flip), not in the
// template file, so the bare frame carries no contentSize override to check.
foreach ( array( 'front-page' ) as $page ) {
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

// ── 13.9.0: every max-width in the stylesheets is a track, a tier, or says why ──
//
// The template sweep above only sees block markup. The page containers and the
// reading measures live in assets/css, and that is where the scatter was: 1320px
// on three pages, 60rem on two, 52ch / 62ch / 48ch beside 46ch / 60ch / 72ch.
// Allowed without comment: the two tracks, the hero frame, the four measure
// tiers, 100% and none. Anything else must carry `/* not a track: <reason> */`
// on its line. @media conditions are skipped: `(max-width: 781px)` is a
// breakpoint, not a width.
echo "\nmax-width sweep over assets/css (blocks/ included)\n";
$mw_allowed = array( 'var(--wp--custom--page-track)', '1600px', '760px', '80ch', '72ch', '60ch', '46ch', '100%', 'none' );
$mw_files   = glob( "$root/assets/css/{,blocks/}*.css", GLOB_BRACE );
ok( count( $mw_files ) > 10, 'scanned ' . count( $mw_files ) . ' stylesheets (guard: an empty glob asserts nothing)' );
$mw_seen = 0;
$mw_bad  = array();
foreach ( $mw_files as $file ) {
	$src  = (string) file_get_contents( $file );
	// Blank comments but keep the marker, so a reason that QUOTES a width is not
	// read as one, and line numbers still map to the source.
	$code = (string) preg_replace_callback( '#/\*.*?\*/#s', static function ( $m ) {
		$mk = '/* not a track:';
		return str_starts_with( $m[0], $mk ) ? $mk . preg_replace( '/[^\n]/', ' ', substr( $m[0], strlen( $mk ) ) ) : preg_replace( '/[^\n]/', ' ', $m[0] );
	}, $src );
	if ( ! preg_match_all( '/(?<![(\w-])max-width\s*:\s*([^;!}\s]+)/', $code, $mm, PREG_OFFSET_CAPTURE ) ) {
		continue;
	}
	foreach ( $mm[1] as $hit ) {
		++$mw_seen;
		$val  = $hit[0];
		$line = substr_count( $code, "\n", 0, $hit[1] ) + 1;
		$eol  = strpos( $code, "\n", $hit[1] );
		$rest = substr( $code, $hit[1], ( false === $eol ? strlen( $code ) : $eol ) - $hit[1] );
		if ( ! in_array( $val, $mw_allowed, true ) && ! str_contains( $rest, '/* not a track:' ) ) {
			$mw_bad[] = basename( $file ) . ":$line max-width: $val";
		}
	}
}
ok( $mw_seen > 20, "$mw_seen max-width declarations inspected (guard: the sweep saw real CSS, not only @media conditions)" );
foreach ( array_slice( $mw_bad, 0, 10 ) as $b ) {
	echo "   -> $b\n";
}
ok( empty( $mw_bad ), 'every max-width is a track, a measure tier, or says why it is not' . ( $mw_bad ? ' (' . count( $mw_bad ) . ')' : '' ) );

// The five page containers the brief moved, by the rule that a page earns the
// wide track only with a real multi-track grid on its own content.
$mw_pages = array(
	'notes.css'         => array( '.sn-notes-page', 'var(--wp--custom--page-track)' ),
	'uses.css'          => array( '.sn-uses-page', 'var(--wp--custom--page-track)' ),
	'now.css'           => array( '.sn-now-page', 'var(--wp--custom--page-track)' ),
	'index.css'         => array( '.sn-index-page', '60rem' ),
	'accessibility.css' => array( '.sn-a11y-page', '60rem' ),
);
foreach ( $mw_pages as $f => $pair ) {
	list( $sel, $want ) = $pair;
	$css = (string) preg_replace( '#/\*.*?\*/#s', '', (string) file_get_contents( "$root/assets/css/$f" ) );
	$got = preg_match( '/' . preg_quote( $sel, '/' ) . '\s*\{[^}]*max-width:\s*([^;\s]+)/s', $css, $pm ) ? $pm[1] : '(none)';
	ok( $got === $want, "$sel sits on " . ( '60rem' === $want ? 'its 60rem reading width' : 'the shared page track' ) . " ($want; got $got)" );
}

// ── Every page is centred on one frame (owner, 2026-09-22) ──
//
// 13.9.0 moved the four text pages to the left under the mark and 13.9.1/14.0.0
// did the same to /notes; on a wide screen that left an empty band on one side,
// and holding both edges meant stretching or cropping content. The owner's call:
// everything as it was, centred, with a wider shared track (1600px) so big
// screens carry less empty space. --sn-gutter stays, for the header only.
echo "\ngutter: the header's side padding\n";
$gcrit = (string) preg_replace( '#/\*.*?\*/#s', '', (string) file_get_contents( "$root/assets/css/critical.css" ) );
$hdr   = (string) file_get_contents( "$root/parts/header.html" );
$hslug = preg_match( '/"padding":\{[^}]*"left":"var:preset\|spacing\|(\d+)"/', $hdr, $hs ) ? $hs[1] : '';
ok( '' !== $hslug && 1 === preg_match( '/--sn-gutter:\s*var\(--wp--preset--spacing--' . $hslug . '\)/', $gcrit ), "desktop --sn-gutter is the header part's own side padding (spacing-$hslug), not a copy of its value" );
foreach ( array( '781' => '1.25rem', '480' => '1rem' ) as $bp => $v ) {
	ok( 1 === preg_match( '/@media\s*\(max-width:\s*' . $bp . 'px\)\s*\{(?:[^{}]*\{[^{}]*\})*?[^{}]*:root\s*\{[^}]*--sn-gutter:\s*' . preg_quote( $v, '/' ) . '/s', $gcrit ), "critical.css overrides --sn-gutter to $v at $bp" . 'px' );
}
foreach ( array( 'critical.css', 'responsive.css' ) as $f ) {
	$css = (string) preg_replace( '#/\*.*?\*/#s', '', (string) file_get_contents( "$root/assets/css/$f" ) );
	preg_match_all( '/\.wp-block-group\.sn-header\.has-background\s*\{([^}]*)\}/', $css, $hr );
	$typed = array_filter( $hr[1], static fn( $b ) => preg_match( '/padding-(left|right):\s*[0-9.]+(rem|px)/', $b ) );
	ok( count( $hr[1] ) >= 2 && empty( $typed ), "$f: every header breakpoint rule pads its sides with var(--sn-gutter), none types a number (" . count( $hr[1] ) . ' rules)' );
}
foreach ( array( 'uses.css' => '.sn-uses-page', 'now.css' => '.sn-now-page', 'index.css' => '.sn-index-page', 'accessibility.css' => '.sn-a11y-page', 'notes.css' => '.sn-notes-page' ) as $f => $sel ) {
	$css = (string) preg_replace( '#/\*.*?\*/#s', '', (string) file_get_contents( "$root/assets/css/$f" ) );
	ok( 1 === preg_match( '/' . preg_quote( $sel, '/' ) . '\s*\{[^}]*margin:\s*0 auto\s*;/s', $css ), "$sel is centred (margin: 0 auto)" );
}
$gpad = json_decode( (string) file_get_contents( "$root/theme.json" ), true )['settings']['custom']['padPage'] ?? '';
ok( ! str_contains( $gpad, '--sn-gutter' ), "theme.json padPage has its own sides again, not the header gutter ($gpad)" );
$pcrit = (string) preg_replace( '#/\*.*?\*/#s', '', (string) file_get_contents( "$root/assets/css/critical.css" ) );
ok( 1 === preg_match( '/body\.page main \.entry-content > \.is-layout-constrained > :where\(:not\(\.alignleft\):not\(\.alignright\):not\(\.alignfull\)\)\s*\{\s*max-width:\s*var\(--wp--custom--page-track\)\s*!important;/', $pcrit ), 'critical.css lifts the editor-built pages\' own 1320px to the shared track, scoped to body.page' );
ok( ! preg_match( '/body\.page[^{]*\{[^}]*margin-(left|right):\s*0/', $pcrit ), 'and never pins them left: core keeps centring them' );
// custom.pageTrack widens the frame only where the screen can afford it: exactly the
// old 1320px up to a 1720px screen (so every laptop keeps its margins), then growing
// to wideSize. Evaluated here at real widths, not trusted as a string.
$ptrack = (string) ( json_decode( (string) file_get_contents( "$root/theme.json" ), true )['settings']['custom']['pageTrack'] ?? '' );
$pwide  = (float) $wide;
$track_at = static function ( $vw ) use ( $ptrack, $pwide ) {
	return preg_match( '/^max\((\d+)px, min\(var\(--wp--style--global--wide-size\), 100vw - (\d+)px\)\)$/', $ptrack, $m ) ? max( (float) $m[1], min( $pwide, $vw - (float) $m[2] ) ) : null;
};
ok( null !== $track_at( 1440 ), "custom.pageTrack has the expected shape ($ptrack)" );
foreach ( array( 1024 => 1320, 1440 => 1320, 1720 => 1320, 1800 => 1400, 2000 => 1600, 2560 => 1600 ) as $vw => $want ) {
	ok( $want === (int) $track_at( $vw ), "page track at a {$vw}px screen is {$want}px (got " . var_export( $track_at( $vw ), true ) . ')' );
}

// 14.1.1: /resume uses the frame. The summary and the credentials box get a
// real gutter (core's was 10.7px), and from 1440px each entry's bullets set in
// two columns instead of one 80ch column that left a third of the row empty.
echo "\n/resume uses the frame\n";
$rcss = (string) preg_replace( '#/\*.*?\*/#s', '', (string) file_get_contents( "$root/assets/css/resume.css" ) );
ok( 1 === preg_match( '/\.wp-block-columns\.sn-resume-hero-split\s*\{[^}]*column-gap:\s*var\(--wp--custom--air--[a-z]+\)/', $rcss ), 'the resume hero has an air-step gutter between summary and credentials' );
ok( 1 === preg_match( '/@media\s*\(min-width:\s*1440px\)\s*\{\s*\.sn-resume-list\s*\{[^}]*columns:\s*2;[^}]*\}\s*\.sn-resume-list li\s*\{[^}]*max-width:\s*none;[^}]*break-inside:\s*avoid;/s', $rcss ), 'from 1440px each entry\'s bullets set in two columns, items never split' );

// 14.1.2: the home hero sits on the shared page frame, so its headline starts on
// the same edge as every other page (it had its own 1100px: 110px further in at
// 1440, 250px at 2000). Both copies, critical and deferred.
foreach ( array( 'critical.css', 'layout.css' ) as $f ) {
	$css = (string) preg_replace( '#/\*.*?\*/#s', '', (string) file_get_contents( "$root/assets/css/$f" ) );
	ok( 1 === preg_match( '/\.sn-hero-inner\s*\{[^}]*max-width:\s*var\(--wp--custom--page-track\)\s*;/', $css ), "$f: the home hero is on the shared page frame" );
}

echo "Result: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
