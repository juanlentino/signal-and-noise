<?php
/**
 * Static guard: no stylesheet declares a font-size under the 11px floor.
 *
 * WHY. The 11px floor is an owner rule, and before this file it was pinned only
 * per template (tests/template-typography.php). The 2026-10-12 audit measured
 * two live breaks that no gate saw: the search type chip at 0.625rem (10px,
 * components.css) and the compact pillar eyebrow at max(0.65rem, 10px)
 * (blocks/pillar-essays/style.css), an explicit floor below the house one.
 *
 * WHAT IT RESOLVES, at the theme's 16px root:
 *   - a bare px or rem font-size;
 *   - max(a, b, ...): the largest argument it can resolve, since the computed
 *     size is at least that;
 *   - clamp(MIN, ...): MIN.
 * WHAT IT CANNOT SEE: em and % sizes (they depend on the parent, so only a
 * rendered page knows), custom-property values, and the `font` shorthand. The
 * em-sized footnote marker in article.css is pinned by name below for that
 * reason.
 *
 * Exceptions must be decorative or not text at all, each with its reason.
 *
 * @since 2026.10
 */

if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) {
	http_response_code( 404 );
	exit;
}

$pass = 0;
$fail = 0;
function ok( $c, $m ) {
	global $pass, $fail;
	if ( $c ) { ++$pass; echo "PASS: $m\n"; } else { ++$fail; echo "FAIL: $m\n"; }
}

const FLOOR_PX = 11.0;
const ROOT_PX  = 16.0;

/** Resolve one length to px, or null when it cannot be resolved statically. */
function tf_px( $len ) {
	$len = trim( $len );
	if ( preg_match( '/^(-?[\d.]+)px$/i', $len, $m ) ) {
		return (float) $m[1];
	}
	if ( preg_match( '/^(-?[\d.]+)rem$/i', $len, $m ) ) {
		return (float) $m[1] * ROOT_PX;
	}
	if ( '0' === $len ) {
		return 0.0;
	}
	return null;
}

/** Split a function's arguments on top-level commas. */
function tf_args( $inner ) {
	$out   = array();
	$depth = 0;
	$cur   = '';
	foreach ( str_split( $inner ) as $ch ) {
		if ( '(' === $ch ) { ++$depth; }
		if ( ')' === $ch ) { --$depth; }
		if ( ',' === $ch && 0 === $depth ) { $out[] = $cur; $cur = ''; continue; }
		$cur .= $ch;
	}
	$out[] = $cur;
	return $out;
}

/** The smallest px the value can compute to, or null when unknowable. */
function tf_min_px( $value ) {
	$value = trim( preg_replace( '/\s*!important\s*$/i', '', $value ) );
	if ( preg_match( '/^max\((.*)\)$/is', $value, $m ) ) {
		$best = null;
		foreach ( tf_args( $m[1] ) as $a ) {
			$px = tf_px( $a );
			if ( null !== $px && ( null === $best || $px > $best ) ) { $best = $px; }
		}
		return $best;
	}
	if ( preg_match( '/^clamp\((.*)\)$/is', $value, $m ) ) {
		return tf_px( tf_args( $m[1] )[0] );
	}
	return tf_px( $value );
}

/** Every font-size under the floor in one CSS string: [ "line: value" ]. */
function tf_scan( $css ) {
	$css  = preg_replace_callback( '~/\*.*?\*/~s', static fn( $m ) => preg_replace( '/[^\n]/', ' ', $m[0] ), $css );
	$hits = array();
	if ( preg_match_all( '/(?<![-\w])font-size\s*:\s*([^;}]+)/i', $css, $all, PREG_OFFSET_CAPTURE ) ) {
		foreach ( $all[1] as $match ) {
			$px = tf_min_px( $match[0] );
			if ( null !== $px && $px < FLOOR_PX ) {
				$hits[] = ( substr_count( substr( $css, 0, $match[1] ), "\n" ) + 1 ) . ': ' . trim( $match[0] );
			}
		}
	}
	return $hits;
}

// Decorative or non-text declarations allowed under the floor, by "file:value".
$exceptions = array(
	// 'assets/css/x.css:0' => 'font-size: 0 collapses whitespace between inline-block children; no text renders at that size.',
);

// ── The scanner can fail (falsification) ──────────────────────────────
ok( array() !== tf_scan( '.a { font-size: 0.625rem; }' ), 'scanner flags a 10px rem literal' );
ok( array() !== tf_scan( '.a { font-size: max(0.65rem, 10px); }' ), 'scanner flags a max() whose floor is under 11px' );
ok( array() !== tf_scan( '.a { font-size: clamp(9px, 2vw, 14px); }' ), 'scanner flags a clamp() minimum under 11px' );
ok( array() !== tf_scan( '.a { font-size: 10px !important; }' ), 'scanner flags an !important px literal' );
ok( array() === tf_scan( '.a { font-size: max(0.6875rem, 11px); --x-font-size: 2px; }' ), 'the house idiom passes; a custom property named *font-size is not a declaration' );
ok( array() === tf_scan( '/* font-size: 4px */ .a { font-size: 0.75em; }' ), 'comments are ignored and em is left to the renderer' );

// ── Every reader-facing stylesheet in the theme ───────────────────────
$root  = dirname( __DIR__ );
$files = array_merge(
	glob( $root . '/assets/css/*.css' ),
	glob( $root . '/assets/css/blocks/*.css' ),
	glob( $root . '/blocks/*/style.css' )
);
ok( count( $files ) > 10, 'found the theme stylesheets (' . count( $files ) . ')' );
foreach ( $files as $file ) {
	$rel  = substr( $file, strlen( $root ) + 1 );
	$hits = array();
	foreach ( tf_scan( (string) file_get_contents( $file ) ) as $hit ) {
		$value = substr( $hit, strpos( $hit, ': ' ) + 2 );
		if ( ! isset( $exceptions[ $rel . ':' . $value ] ) ) {
			$hits[] = $hit;
		}
	}
	ok( array() === $hits, $rel . ' has no font-size under 11px' . ( $hits ? ' (line ' . implode( '; line ', $hits ) . ')' : '' ) );
}

// ── The em-sized footnote marker the scanner cannot resolve ───────────
$article = (string) file_get_contents( $root . '/assets/css/article.css' );
ok( (bool) preg_match( '/sup\[data-fn\] a\s*\{[^}]*font-size:\s*max\([^)]*11px\)/', $article ), 'the footnote marker (sup a, em-sized) carries the 11px floor in a max()' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
