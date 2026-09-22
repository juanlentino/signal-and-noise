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
$mw_allowed = array( '1400px', '760px', '1100px', '80ch', '72ch', '60ch', '46ch', '100%', 'none' );
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
	'notes.css'         => array( '.sn-notes-page', '1400px' ),
	'uses.css'          => array( '.sn-uses-page', '760px' ),
	'now.css'           => array( '.sn-now-page', '760px' ),
	'index.css'         => array( '.sn-index-page', '760px' ),
	'accessibility.css' => array( '.sn-a11y-page', '760px' ),
);
foreach ( $mw_pages as $f => $pair ) {
	list( $sel, $want ) = $pair;
	$css = (string) preg_replace( '#/\*.*?\*/#s', '', (string) file_get_contents( "$root/assets/css/$f" ) );
	$got = preg_match( '/' . preg_quote( $sel, '/' ) . '\s*\{[^}]*max-width:\s*([^;\s]+)/s', $css, $pm ) ? $pm[1] : '(none)';
	ok( $got === $want, "$sel sits on the " . ( '1400px' === $want ? 'wide' : 'reading' ) . " track ($want; got $got)" );
}

// ── The text pages start under the mark (owner, 2026-09-22) ──
//
// On a wide screen a 760px column centres unless told otherwise, which moved the
// four text pages' left edge from under the JL mark to ~292px. They stay left:
// margin 0, and their side padding is --sn-gutter, the same variable the header
// pads with. Measured on the live /uses with these rules: text and mark both at
// 36px (1440, 1024, 800), 20px (700), 16px (375).
echo "\ngutter: the text pages start under the mark\n";
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
$gpad = json_decode( (string) file_get_contents( "$root/theme.json" ), true )['settings']['custom']['padPage'] ?? '';
ok( str_contains( $gpad, 'var(--sn-gutter' ), "theme.json padPage takes its sides from --sn-gutter ($gpad)" );
foreach ( array( 'uses.css' => '.sn-uses-page', 'now.css' => '.sn-now-page', 'index.css' => '.sn-index-page', 'accessibility.css' => '.sn-a11y-page' ) as $f => $sel ) {
	$css = (string) preg_replace( '#/\*.*?\*/#s', '', (string) file_get_contents( "$root/assets/css/$f" ) );
	ok( 1 === preg_match( '/' . preg_quote( $sel, '/' ) . '\s*\{[^}]*margin:\s*0\s*;/s', $css ), "$sel sits at the left (margin: 0), not centred" );
}

echo "Result: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
