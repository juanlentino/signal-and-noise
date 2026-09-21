<?php
/**
 * The five hand-written clamp() presets, measured against core's own
 * fluid { min, max } (#387).
 *
 * WHY THIS EXISTS. theme.json turns fluid typography on and then hand-writes
 * `clamp(min, Xvw, max)` on the five presets that exist to scale (xx-large,
 * xxx-large, display-sm, display-md, display-lg). Core never touches a size
 * that is already a clamp() (class-wp-theme-json.php: "values that already
 * have a clamp() function will not pass the test"), so the `"fluid": false`
 * that sat beside three of them did nothing. The native shape is a bare size
 * plus `fluid: { min, max }`, and core computes the clamp() from one GLOBAL
 * viewport pair (settings.typography.fluid.minViewportWidth /
 * maxViewportWidth, defaulting to 320px and layout.wideSize).
 *
 * THE GEOMETRY. A hand-written `Xvw` is a line through the origin; core draws
 * a line from (minViewportWidth, min) to (maxViewportWidth, max). The two
 * coincide only when the pair is that preset's own floor and cap crossing
 * (xxx-large: 640px and 1440px; display-md: 800px and 1120px), and each
 * preset's crossing is different. One pair can therefore reproduce at most
 * one of the five, and every pair other than today's effective one moves the
 * seven bare presets (medium, large, x-large, prose, caption, nav, prose-lg)
 * that core already fluidises. Under the effective pair none of the five
 * renders the same at 768 or 1024. So the five stay literal until the owner
 * picks a pair, and this suite is the instrument that says when a port would
 * render the same px at every tested width. Never byte-identical: core's
 * middle term is `min + ((1vw - offset) * factor)` and the hand line is
 * `Xvw`, so the strings differ even where the lines coincide.
 *
 * THE INSTRUMENT IS CORE'S OWN CODE. tests/fixtures/core-block-supports-
 * typography-7.1.php is a verbatim copy of wp-includes/block-supports/
 * typography.php from WordPress 7.1, loaded behind the six stubs it needs.
 * No formula is re-derived here. Refresh it from a WordPress download:
 *   cp <wp>/wp-includes/block-supports/typography.php \
 *      tests/fixtures/core-block-supports-typography-<ver>.php
 * and point CORE_TYPOGRAPHY at it. Section 1 proves the harness reproduces
 * the strings the live site emitted on 2026-09-20 byte for byte before any
 * conclusion is drawn from it.
 *
 * Run: php tests/fluid-type-presets.php
 *
 * @since theme v13.4.1
 */

if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) {
	http_response_code( 404 );
	exit;
}

const CORE_TYPOGRAPHY = __DIR__ . '/fixtures/core-block-supports-typography-7.1.php';

$pass = 0;
$fail = 0;
function ok( $cond, $label ) {
	global $pass, $fail;
	if ( $cond ) {
		++$pass;
		echo "PASS: $label\n";
	} else {
		++$fail;
		echo "  FAIL: $label\n";
	}
}

// What core's file needs at load (the block-support registration at its
// foot) and at call. wp_get_global_settings() hands back whatever the test
// sets, so the pair under test is exactly what production would read.
class WP_Block_Supports {
	public static function get_instance() {
		return new self();
	}
	public function register() {}
}
function wp_parse_args( $args, $defaults = array() ) {
	return array_merge( (array) $defaults, (array) $args );
}
function __( $text ) {
	return $text;
}
function _deprecated_argument() {}
function _doing_it_wrong() {}
if ( ! function_exists( 'clamp' ) ) {
	// wp-includes/compat.php polyfills this on PHP without a native clamp().
	function clamp( $value, $min, $max ) {
		return max( $min, min( $max, $value ) );
	}
}
$GLOBALS['snt_fluid_settings'] = array();
function wp_get_global_settings( $path = array(), $context = array() ) {
	return $GLOBALS['snt_fluid_settings'];
}

require CORE_TYPOGRAPHY;
ok( function_exists( 'wp_get_typography_font_size_value' ), 'core fixture loaded: wp_get_typography_font_size_value() is core 7.1, not a twin' );

$theme_root = realpath( __DIR__ . '/..' );
$theme      = json_decode( (string) file_get_contents( "$theme_root/theme.json" ), true );
ok( is_array( $theme ), 'theme.json parses' );

$sizes = array();
foreach ( $theme['settings']['typography']['fontSizes'] ?? array() as $fs ) {
	$sizes[ $fs['slug'] ] = $fs;
}

/** Run core with a given global fluid setting (true, false, or the pair object). */
function snt_with_pair( $fluid, $theme ) {
	$GLOBALS['snt_fluid_settings'] = array(
		'typography' => array( 'fluid' => $fluid ),
		'layout'     => $theme['settings']['layout'] ?? array(),
	);
}

/**
 * The px a clamp() renders at a viewport width. Reads both shapes this suite
 * meets: the hand-written `clamp(A, Xvw, B)` and core's
 * `clamp(A, Crem + ((1vw - O) * F), B)`. Lengths are rem, px or vw; root 16px.
 * Null when the string is neither, so a parse miss cannot pass as a match.
 */
function snt_px_at( $clamp, $width ) {
	$len = static function ( $s ) use ( $width ) {
		if ( ! preg_match( '/^([0-9.]+)(rem|px|vw)$/', trim( $s ), $m ) ) {
			return null;
		}
		$v = (float) $m[1];
		return 'rem' === $m[2] ? $v * 16 : ( 'vw' === $m[2] ? $v * $width / 100 : $v );
	};
	if ( ! preg_match( '/^clamp\((.+?), (.+), (.+?)\)$/', trim( $clamp ), $m ) ) {
		return null;
	}
	$lo  = $len( $m[1] );
	$hi  = $len( $m[3] );
	$mid = $len( $m[2] );
	if ( null === $mid && preg_match( '/^([0-9.]+rem) \+ \(\(1vw - ([0-9.]+(?:rem|px))\) \* ([0-9.]+)\)$/', $m[2], $c ) ) {
		$mid = $len( $c[1] ) + ( $width / 100 - $len( $c[2] ) ) * (float) $c[3];
	}
	if ( null === $lo || null === $hi || null === $mid ) {
		return null;
	}
	return round( max( $lo, min( $hi, $mid ) ), 6 );
}

$widths = array( 320, 768, 1024, 1440 );
$row    = static function ( $clamp ) use ( $widths ) {
	return implode( ' / ', array_map( static fn( $w ) => rtrim( rtrim( number_format( (float) snt_px_at( $clamp, $w ), 3, '.', '' ), '0' ), '.' ), $widths ) );
};
/** True when two clamp() strings render the same px at every tested width; a parse miss counts as different. */
$same_everywhere = static function ( $hand, $core ) use ( $widths ) {
	foreach ( $widths as $w ) {
		$h = snt_px_at( $hand, $w );
		if ( null === $h || $h !== snt_px_at( $core, $w ) ) {
			return false;
		}
	}
	return true;
};

// 1. THE INSTRUMENT, BEFORE ANY READING. The seven bare presets core
//    fluidises must come out exactly as https://juanlentino.com/ emitted them
//    on 2026-09-20 (curl, `--wp--preset--font-size--*`). A harness that
//    cannot reproduce production has no standing to say what production
//    would do with a different shape. Goes red when the fixture, the theme's
//    pair, or a bare preset moves, which is the point.
echo "\n1. Harness reproduces the live site's bare presets\n";
$live = array(
	'medium'   => 'clamp(14px, 0.875rem + ((1vw - 3.2px) * 0.556), 20px)',
	'large'    => 'clamp(22.041px, 1.378rem + ((1vw - 3.2px) * 1.293), 36px)',
	'x-large'  => 'clamp(25.014px, 1.563rem + ((1vw - 3.2px) * 1.573), 42px)',
	'prose'    => 'clamp(0.875rem, 0.875rem + ((1vw - 0.2rem) * 0.185), 1rem)',
	'caption'  => 'clamp(0.875rem, 0.875rem + ((1vw - 0.2rem) * 0.037), 0.9rem)',
	'nav'      => 'clamp(0.875rem, 0.875rem + ((1vw - 0.2rem) * 0.37), 1.125rem)',
	'prose-lg' => 'clamp(0.875rem, 0.875rem + ((1vw - 0.2rem) * 0.407), 1.15rem)',
);
snt_with_pair( $theme['settings']['typography']['fluid'] ?? false, $theme );
foreach ( $live as $slug => $expected ) {
	$got = isset( $sizes[ $slug ] ) ? wp_get_typography_font_size_value( $sizes[ $slug ] ) : null;
	ok( $got === $expected, "live twin: $slug => $expected" . ( $got === $expected ? '' : " (got $got)" ) );
}
ok( wp_get_typography_font_size_value( array( 'size' => '0.75rem', 'fluid' => false ) ) === '0.75rem', 'a preset with fluid:false comes back verbatim' );
ok( wp_get_typography_font_size_value( array( 'size' => 'clamp(2rem, 4vw, 2.8rem)' ) ) === 'clamp(2rem, 4vw, 2.8rem)'
	&& wp_get_typography_font_size_value( array( 'size' => 'clamp(2rem, 4vw, 2.8rem)', 'fluid' => false ) ) === 'clamp(2rem, 4vw, 2.8rem)',
	'a clamp() size comes back verbatim with or without fluid:false, so the flag beside a clamp() is inert' );

// The evaluator can go red: a known line, and a string it must refuse.
ok( 70.0 === snt_px_at( 'clamp(3rem, 7vw, 6rem)', 1000 ), 'evaluator: 7vw at 1000px is 70px' );
ok( 100.0 === snt_px_at( 'clamp(4rem, 4rem + ((1vw - 0.4rem) * 10), 9rem)', 1000 ), 'evaluator: core\'s 640px/1440px line for xxx-large is 10vw at 1000px' );
ok( 16.49088 === snt_px_at( $live['medium'], 768 ) && 14.0 === snt_px_at( $live['medium'], 320 ) && 20.0 === snt_px_at( $live['medium'], 1440 ),
	'evaluator: medium renders 14 / 16.49088 / 20 at 320 / 768 / 1440' );
ok( null === snt_px_at( 'clamp(3rem, calc(1vw + 1rem), 6rem)', 1000 ) && null === snt_px_at( '2rem', 1000 ),
	'evaluator: an unreadable middle term or a bare length is null, never a false match' );

// 2. THE FIVE, PORTED (#387, owner decision 2026-09-21: the DEFAULT pair).
//    Each is now a bare `size` (the cap) plus `fluid: { min, max }` holding
//    the old hand line's floor and cap, and core computes the clamp() under
//    the default pair (320px to layout.wideSize, 1400px). The pair stayed the
//    default on purpose: the tuned pair the issue floated (680px/1370px) keeps
//    the headings within ~4px of the old lines but re-scales the SEVEN BARE
//    presets too, and `medium` is the global body size (styles.typography),
//    `x-large` is h3 and `large` is h4, so it would have shrunk body copy by
//    ~1.7px and h3/h4 by 4-5px at every tablet width, on every page. Under
//    the default pair section 1 stays byte-identical, which is the guard
//    that nothing outside the five moved. What DID move is pinned here as a
//    table, not a tolerance: the exact string core emits and the px at each
//    tested width, next to the old hand line for the record. Floor and cap
//    are identical to the old line; the middle is larger between ~640 and
//    ~1300px (post h1 77 -> 97px at 768).
echo "\n2. The five ported presets under the effective pair\n";
$five = array(
	// slug => old hand line, min, max, what core emits under 320px/1400px, px at 320/768/1024/1440
	'xx-large'   => array( 'clamp(3rem, 7vw, 6rem)', '3rem', '6rem', 'clamp(3rem, 3rem + ((1vw - 0.2rem) * 4.444), 6rem)', array( 48.0, 67.909, 79.286, 96.0 ) ),
	'xxx-large'  => array( 'clamp(4rem, 10vw, 9rem)', '4rem', '9rem', 'clamp(4rem, 4rem + ((1vw - 0.2rem) * 7.407), 9rem)', array( 64.0, 97.183, 116.145, 144.0 ) ),
	'display-sm' => array( 'clamp(1.8rem, 3vw, 2.5rem)', '1.8rem', '2.5rem', 'clamp(1.8rem, 1.8rem + ((1vw - 0.2rem) * 1.037), 2.5rem)', array( 28.8, 33.446, 36.1, 40.0 ) ),
	'display-md' => array( 'clamp(2rem, 4vw, 2.8rem)', '2rem', '2.8rem', 'clamp(2rem, 2rem + ((1vw - 0.2rem) * 1.185), 2.8rem)', array( 32.0, 37.309, 40.342, 44.8 ) ),
	'display-lg' => array( 'clamp(2.5rem, 6vw, 5rem)', '2.5rem', '5rem', 'clamp(2.5rem, 2.5rem + ((1vw - 0.2rem) * 3.704), 5rem)', array( 40.0, 56.594, 66.076, 80.0 ) ),
);
$fluid_setting = $theme['settings']['typography']['fluid'] ?? false;
$pair_min      = is_array( $fluid_setting ) && isset( $fluid_setting['minViewportWidth'] ) ? $fluid_setting['minViewportWidth'] : '320px';
$pair_max      = is_array( $fluid_setting ) && isset( $fluid_setting['maxViewportWidth'] ) ? $fluid_setting['maxViewportWidth'] : ( $theme['settings']['layout']['wideSize'] ?? '1600px' );
ok( true === $fluid_setting, 'settings.typography.fluid is the bare `true` (the default pair; a pair object here re-scales body, h3 and h4 too)' );
ok( '320px' === $pair_min && '1400px' === $pair_max, "effective pair is 320px/1400px (got $pair_min/$pair_max)" );
echo "   effective pair: $pair_min to $pair_max (widths " . implode( ' / ', $widths ) . ")\n";

snt_with_pair( $fluid_setting, $theme );
foreach ( $five as $slug => list( $hand, $min, $max, $core_expected, $px ) ) {
	$fs = $sizes[ $slug ] ?? array();
	ok( ( $fs['size'] ?? '' ) === $max, "preset '$slug' size is the bare cap $max (no clamp() literal)" );
	ok( ( $fs['fluid'] ?? null ) === array( 'min' => $min, 'max' => $max ), "preset '$slug' carries fluid { $min, $max }, the old line's floor and cap" );
	$core = wp_get_typography_font_size_value( $fs );
	ok( $core === $core_expected, "core emits $core_expected for '$slug'" . ( $core === $core_expected ? '' : " (got $core)" ) );
	echo "   $slug  was: " . $row( $hand ) . "  now: " . $row( $core ) . "\n";
	foreach ( array( 320, 1440 ) as $w ) {
		ok( snt_px_at( $hand, $w ) === snt_px_at( $core, $w ), "'$slug' at {$w}px: floor or cap, identical to the old line (" . snt_px_at( $hand, $w ) . 'px)' );
	}
	foreach ( $widths as $i => $w ) {
		ok( round( (float) snt_px_at( $core, $w ), 3 ) === $px[ $i ], "'$slug' renders {$px[$i]}px at {$w}px" );
	}
}

// 3. WHY THE PAIR STAYED DEFAULT. One pair reproduces at most one of the
//    old hand lines, and any pair other than the default rewrites `medium`
//    (the body size) and the other six bare presets. The two crossings that
//    are exact in rem are run against the OLD lines as the record: each
//    makes its own preset identical at every tested width, leaves the other
//    four different, and moves `medium`.
echo "\n3. A preset's own crossing as the global pair\n";
foreach ( array( 'xxx-large' => array( '640px', '1440px' ), 'display-md' => array( '800px', '1120px' ) ) as $own => list( $a, $b ) ) {
	snt_with_pair( array( 'minViewportWidth' => $a, 'maxViewportWidth' => $b ), $theme );
	foreach ( $five as $slug => list( $hand, $min, $max ) ) {
		$core = wp_get_typography_font_size_value( array( 'slug' => $slug, 'size' => $max, 'fluid' => array( 'min' => $min, 'max' => $max ) ) );
		$same = $same_everywhere( $hand, $core );
		ok( $same === ( $slug === $own ), "pair $a/$b: '$slug' " . ( $slug === $own ? 'identical at every width' : 'differs' ) . ' (core: ' . $row( $core ) . ')' );
	}
	$medium = wp_get_typography_font_size_value( $sizes['medium'] );
	ok( $medium !== $live['medium'], "pair $a/$b moves the bare preset medium: $medium" );
}

echo "\n$pass passed, $fail failed\n";
exit( $fail > 0 ? 1 : 0 );
