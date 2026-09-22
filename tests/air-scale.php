<?php
/**
 * Guard: fluid spacing and fluid type are tokens, or they say why not.
 *
 * 13.8.0 named the fluid values the theme repeats. theme.json carries seven
 * spacing steps in settings.custom.air (emitted as --wp--custom--air--*) and
 * four page type sizes in settings.custom.fontSize (pageHeadline, pageDek,
 * pageSectionLabel, pageItem). They were chosen as the EXACT values that
 * already recurred, so nothing on the site moved. The owner declined a
 * rate-based scale that would have moved 48 of 84 sites by up to 36px.
 *
 * What this pins, over every stylesheet under assets/css (blocks/ included):
 *
 *   1. Every var(--wp--custom--air--*) and page font-size var names a token that
 *      exists. A typo'd var resolves to nothing and the property silently drops.
 *   2. No clamp() re-types a token's value. Writing the literal back is how the
 *      scale erodes: it renders the same today and drifts the day the token moves.
 *   3. Every other clamp(rem, vw, rem) carries a `/* not a step: <reason> *\/`
 *      comment on its declaration. 28 one-offs do today, each naming the nearest
 *      token and how far it would move.
 *   4. Font-size clamps keep max <= 2.5x min, in CSS and in theme.json. Beyond
 *      that, text driven by vw stops honouring browser zoom (WCAG 1.4.4; the
 *      modern-web-guidance fluid-scaling guide states the same bound).
 *
 * Comments are stripped across the WHOLE file before any match. Several
 * stylesheets quote old clamp values inside multi-line comments, and a
 * line-by-line strip reads that history as code.
 *
 * Run: php tests/air-scale.php
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

/** Parse clamp(Arem, Bvw, Crem) into floats, or null. */
function as_clamp( $s ) {
	return preg_match( '/^clamp\(\s*([0-9.]+)rem\s*,\s*([0-9.]+)vw\s*,\s*([0-9.]+)rem\s*\)$/', trim( $s ), $m )
		? array( (float) $m[1], (float) $m[2], (float) $m[3] )
		: null;
}
function kebab( $k ) {
	return strtolower( (string) preg_replace( '/([A-Z])/', '-$1', $k ) );
}

echo "air-scale\n\nGroup 1: the tokens exist in theme.json\n";
$custom = json_decode( (string) file_get_contents( $root . '/theme.json' ), true )['settings']['custom'] ?? array();
$air    = (array) ( $custom['air'] ?? array() );
$type   = array_filter( (array) ( $custom['fontSize'] ?? array() ), static fn( $k ) => str_starts_with( $k, 'page' ), ARRAY_FILTER_USE_KEY );
ok( 7 === count( $air ), 'settings.custom.air declares 7 steps (' . implode( ', ', array_keys( $air ) ) . ')' );
ok( 4 === count( $type ), 'settings.custom.fontSize declares the 4 page type sizes (' . implode( ', ', array_keys( $type ) ) . ')' );

$token_vals = array(); // "1|2|1.5" => var name
foreach ( $air as $k => $v ) {
	$c = as_clamp( $v );
	ok( null !== $c, "air.$k is a clamp(rem, vw, rem): $v" );
	if ( $c ) {
		$token_vals[ implode( '|', $c ) ] = "--wp--custom--air--$k";
	}
}
foreach ( $type as $k => $v ) {
	$c = as_clamp( $v );
	ok( null !== $c && $c[2] <= 2.5 * $c[0], sprintf( 'fontSize.%s keeps max within 2.5x min (%s)', $k, $v ) );
	if ( $c ) {
		$token_vals[ implode( '|', $c ) ] = '--wp--custom--font-size--' . kebab( $k );
	}
}
foreach ( (array) ( $custom['fontSize'] ?? array() ) as $k => $v ) {
	$c = as_clamp( (string) $v );
	if ( $c && ! str_starts_with( $k, 'page' ) ) {
		ok( $c[2] <= 2.5 * $c[0], sprintf( 'fontSize.%s keeps max within 2.5x min (%.2fx)', $k, $c[2] / $c[0] ) );
	}
}
$known_vars = array_values( $token_vals );

echo "\nGroup 2: every stylesheet, comments stripped across the whole file\n";
$files = glob( $root . '/assets/css/{,blocks/}*.css', GLOB_BRACE );
ok( count( $files ) > 10 && (bool) array_filter( $files, static fn( $f ) => str_contains( $f, '/blocks/' ) ), 'scanned ' . count( $files ) . ' stylesheets, blocks/ included (guard: an empty glob asserts nothing)' );

$bad_var = array();
$retyped = array();
$bare    = array();
$zoom    = array();
$uses    = 0;
$clamps  = 0;
foreach ( $files as $file ) {
	$src  = (string) file_get_contents( $file );
	$name = str_replace( $root . '/assets/css/', '', $file );
	// Blank every comment EXCEPT the reason markers, keeping offsets, so a line
	// number still maps back to the source.
	$code = (string) preg_replace_callback( '#/\*.*?\*/#s', static function ( $m ) {
		// Keep the marker, blank what it says: a reason comment usually QUOTES the
		// clamp it explains, and that quote must not be read as a second clamp.
		$marker = '/* not a step:';
		if ( str_starts_with( $m[0], $marker ) ) {
			return $marker . preg_replace( '/[^\n]/', ' ', substr( $m[0], strlen( $marker ) ) );
		}
		return preg_replace( '/[^\n]/', ' ', $m[0] );
	}, $src );

	if ( preg_match_all( '/var\((--wp--custom--(?:air--[a-z-]+|font-size--page-[a-z-]+))\)/', $code, $vm ) ) {
		foreach ( $vm[1] as $v ) {
			++$uses;
			if ( ! in_array( $v, $known_vars, true ) ) {
				$bad_var[] = "$name: $v";
			}
		}
	}

	if ( preg_match_all( '/clamp\(\s*[0-9.]+rem\s*,\s*[0-9.]+vw\s*,\s*[0-9.]+rem\s*\)/', $code, $cm, PREG_OFFSET_CAPTURE ) ) {
		foreach ( $cm[0] as $hit ) {
			++$clamps;
			$c    = as_clamp( $hit[0] );
			$line = substr_count( $code, "\n", 0, $hit[1] ) + 1;
			$at   = "$name:$line " . $hit[0];
			// The declaration this clamp belongs to: from the last ; { } to the next ;
			$from = max( strrpos( substr( $code, 0, $hit[1] ), ';' ) ?: 0, strrpos( substr( $code, 0, $hit[1] ), '{' ) ?: 0, strrpos( substr( $code, 0, $hit[1] ), '}' ) ?: 0 );
			$to   = strpos( $code, ';', $hit[1] );
			$decl = substr( $code, $from, false === $to ? null : $to - $from + 1 );
			$prop = trim( strtok( ltrim( $decl, ';{} ' ), ':' ) );
			$tail = false === $to ? '' : substr( $code, $to, 400 );
			$tail = substr( $tail, 0, (int) ( strpos( $tail, "\n" ) ?: strlen( $tail ) ) );

			if ( isset( $token_vals[ implode( '|', $c ) ] ) ) {
				$retyped[] = "$at  (use var({$token_vals[ implode( '|', $c ) ]}))";
			} elseif ( ! str_contains( $tail, '/* not a step:' ) ) {
				$bare[] = $at;
			}
			if ( 'font-size' === $prop && $c[2] > 2.5 * $c[0] ) {
				$zoom[] = sprintf( '%s  (%.2fx)', $at, $c[2] / $c[0] );
			}
		}
	}
}
ok( $uses >= 30, "$uses declarations read a scale token (guard: the migration has consumers)" );
ok( $clamps > 0, "$clamps literal clamp() one-offs were inspected (guard: the scan saw real CSS)" );

echo "\nGroup 3: the rules\n";
foreach ( array( 'bad_var' => $bad_var, 'retyped' => $retyped, 'bare' => $bare, 'zoom' => $zoom ) as $k => $list ) {
	foreach ( array_slice( $list, 0, 8 ) as $x ) {
		echo "   -> [$k] $x\n";
	}
}
ok( empty( $bad_var ), 'every scale var names a token that exists' . ( $bad_var ? ' (' . count( $bad_var ) . ')' : '' ) );
ok( empty( $retyped ), 'no stylesheet re-types a token\'s value as a literal clamp()' . ( $retyped ? ' (' . count( $retyped ) . ')' : '' ) );
ok( empty( $bare ), 'every other clamp(rem, vw, rem) says why it is not a step' . ( $bare ? ' (' . count( $bare ) . ')' : '' ) );
ok( empty( $zoom ), 'every font-size clamp keeps max within 2.5x min, so it still honours browser zoom' . ( $zoom ? ' (' . count( $zoom ) . ')' : '' ) );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
