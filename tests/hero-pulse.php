<?php
/**
 * The home hero's pulse: the last 24 hours of the site's own traffic drawn as
 * a waveform under the hero (inc/hero-pulse.php).
 *
 * WHY THESE GROUPS. The owner's rules for it: real data only, so no synthetic
 * curve ever fills a gap; shape only, so no count reaches the markup; and
 * nothing at all when there is nothing honest to draw. Each group pins one of
 * those, plus the CSS contract (motion gated, forced colors drop it, the
 * pseudo has no content until the data arrives, so a missing series can never
 * paint a solid block).
 *
 * Run: php tests/hero-pulse.php
 */

if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) {
	http_response_code( 404 );
	exit;
}

define( 'ABSPATH', '/' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MINUTE_IN_SECONDS', 60 );
function add_action( $tag, $cb, $p = 10, $a = 1 ) {}

require __DIR__ . '/../inc/hero-pulse.php';

$pass = 0;
$fail = 0;
function ok( $c, $m ) {
	global $pass, $fail;
	if ( $c ) { ++$pass; echo "PASS: $m\n"; } else { ++$fail; echo "FAIL: $m\n"; }
}

$root  = dirname( __DIR__ );
$strip = static function ( $f ) use ( $root ) { return preg_replace( '~/\*.*?\*/~s', '', (string) file_get_contents( $root . '/' . $f ) ); };

// 2026-10-16 14:37 UTC: the last full hour is 13:00, so the window is
// 2026-10-15 14:00 through 2026-10-16 13:00.
$now = gmmktime( 14, 37, 0, 10, 16, 2026 );

echo "Group 1: rows to the 24 completed hours\n";
$rows = array(
	array( 'day' => '2026-10-15', 'bucket' => '13', 'views' => 999999 ), // one hour too old.
	array( 'day' => '2026-10-15', 'bucket' => '14', 'views' => 7919 ),   // the first hour.
	array( 'day' => '2026-10-15', 'bucket' => '23', 'views' => 104729 ),
	array( 'day' => '2026-10-16', 'bucket' => '00', 'views' => 5000 ),
	array( 'day' => '2026-10-16', 'bucket' => '00', 'views' => 2000 ),   // a second class, same hour: summed.
	array( 'day' => '2026-10-16', 'bucket' => '13', 'views' => 3001 ),   // the last full hour.
	array( 'day' => '2026-10-16', 'bucket' => '14', 'views' => 888888 ), // the partial hour now running.
	array( 'day' => 'garbage', 'bucket' => 'x', 'views' => 5 ),
);
$hours = sn_hero_pulse_hours( $rows, $now );
ok( 24 === count( $hours ), 'always 24 points' );
ok( 7919 === $hours[0], 'the first point is 24 hours before the last full hour' );
ok( 104729 === $hours[9], 'hours land in order across midnight' );
ok( 7000 === $hours[10], 'classes in the same hour are summed (the server pulse, every class)' );
ok( 3001 === $hours[23], 'the last point is the last FULL hour' );
ok( ! in_array( 999999, $hours, true ) && ! in_array( 888888, $hours, true ), 'an hour outside the window, or the partial hour now running, is left out' );
ok( 0 === $hours[1], 'an hour with no row is zero, never filled in' );
ok( array( 'day' => '2026-10-15', 'last' => '2026-10-16' ) === sn_hero_pulse_days( $now ), 'the read asks for exactly yesterday and today (UTC)' );

echo "\nGroup 2: the series to a waveform\n";
$svg = sn_hero_pulse_svg( $hours );
ok( 0 === strpos( $svg, '<svg ' ), 'a series with shape draws an SVG' );
ok( 96 === substr_count( $svg, '<rect ' ), 'four hairlines per hour, 96 in all: every line is a real reading, none interpolated' );
preg_match_all( "/<rect x='([\d.]+)' y='([\d.]+)' width='([\d.]+)' height='([\d.]+)'\/>/", $svg, $m );
$heights = array_map( 'floatval', $m[4] );
ok( 96 === count( $heights ), 'every rect has the expected attribute shape' );
ok( 100.0 === max( $heights ), 'the loudest hour fills the box' );
ok( $heights[36] === $heights[37] && $heights[37] === $heights[38] && $heights[38] === $heights[39], 'the four lines of one hour share its height' );
ok( $heights[36] > $heights[0], 'louder hours draw taller' );
ok( $heights[4] > 0 && $heights[4] < 5, 'a silent hour is a short stub on the center line, not a gap' );
ok( $svg === sn_hero_pulse_svg( $hours ), 'deterministic: the same series draws the same bytes' );
foreach ( array( '7919', '104729', '3001', '7000' ) as $count ) {
	ok( false === strpos( $svg, $count ), "no count leaks into the markup ($count)" );
}
ok( 0 === preg_match( '/<(text|title|desc)\b/', $svg ), 'no labels, titles or timestamps: shape only' );

echo "\nGroup 3: nothing honest to draw, nothing drawn\n";
ok( '' === sn_hero_pulse_svg( array() ), 'no data: nothing' );
ok( '' === sn_hero_pulse_svg( array_fill( 0, 24, 0 ) ), 'all zero: nothing' );
ok( '' === sn_hero_pulse_svg( array_fill( 0, 24, 412 ) ), 'flat: nothing (normalized, it would be a solid block, not a waveform)' );
ok( '' === sn_hero_pulse_svg( array_fill( 0, 23, 5 ) ), 'not 24 points: nothing' );
ok( '' === sn_hero_pulse_css( '' ), 'no SVG, no rule' );
ok( null === sn_hero_pulse_rows( 0 ), 'without the companion plugin the read returns null and never touches the database' );

echo "\nGroup 4: the inline rule\n";
$css = sn_hero_pulse_css( $svg );
ok( 0 === strpos( $css, '.sn-hero::after{content:"";' ), 'the inline rule is what gives the pseudo its content' );
ok( false !== strpos( $css, '.sn-hero::before{content:""}' ), 'the same rule lights the playhead, so it never shows without a pulse' );
ok( 2 === substr_count( $css, 'url("data:image/svg+xml,' ), 'mask-image and its -webkit- twin both carry the waveform' );
ok( false === strpos( $css, '<' ) && false !== strpos( $css, 'data:image/svg+xml,%3Csvg' ), 'the SVG is URL-encoded, so nothing in it can close the style element' );
ok( false === strpos( $css, '</style' ), 'cannot break out of <style>' );

echo "\nGroup 5: the static CSS contract\n";
$crit = $strip( 'assets/css/critical.css' );
$rule = preg_match( '/\.sn-hero::after\s*\{([^}]*)\}/', $crit, $r ) ? $r[1] : '';
ok( '' !== $rule, 'critical.css positions the pulse (the hero is above the fold)' );
ok( false === strpos( $rule, 'content' ), '...without content: only the inline rule, printed when there is data, makes it render' );
ok( 1 === preg_match( '/position:\s*absolute/', $rule ), '...absolutely, so it never moves the hero (the hero is a flex column)' );
ok( false !== strpos( $rule, 'var(--sn-pulse, var(--wp--preset--color--concrete))' ), '...in its own token, falling back to concrete (light keeps concrete)' );
preg_match_all( '/:root\\[data-theme="dark"\\]\\s*\\{[^}]*\\}|:root:not\\(\\[data-theme="light"\\]\\)\\s*\\{[^}]*\\}/', $crit, $dark );
ok( 2 === count( $dark[0] ) && 2 === count( preg_grep( '/--sn-pulse:\\s*#565656/', $dark[0] ) ), 'both dark blocks set the pulse to #565656: 2.70:1 on void, matching light (concrete #383838 was 1.69:1)' );
ok( 0 === preg_match( '/(^|\\})\\s*:root\\s*\\{[^}]*--sn-pulse/', $crit ), 'light mode defines no pulse token, so it stays on concrete' );
ok( 1 === preg_match( '/@media\s*\(prefers-reduced-motion:\s*no-preference\)\s*\{[^{}]*\.sn-hero::after\s*\{[^}]*animation:\s*sn-pulse-draw[^}]*steps\(/s', $crit ), 'the one-time draw-in is stepped and gated on prefers-reduced-motion: no-preference' );
ok( 1 === preg_match( '/@keyframes\s+sn-pulse-draw\s*\{[^}]*clip-path/s', $crit ), 'the draw-in animates clip-path only' );
ok( 1 === preg_match( '/@media\s*\(forced-colors:\s*active\)\s*\{[^{}]*\.sn-hero::after[^{]*\{[^}]*display:\s*none/s', $crit ), 'forced colors drop it' );

echo "\nGroup 6: wiring\n";
$src = (string) file_get_contents( $root . '/inc/hero-pulse.php' );
ok( 1 === preg_match( "/add_action\(\s*'wp_head',\s*'sn_hero_pulse_print',\s*51\s*\)/", $src ), 'printed in <head> right after the inline critical CSS (priority 50)' );
ok( false !== strpos( $src, "set_transient( SN_HERO_PULSE_TRANSIENT" ) && false !== strpos( $src, '10 * MINUTE_IN_SECONDS' ), 'the computed rule is cached for ten minutes, the empty answer too' );
ok( false !== strpos( $src, "function_exists( 'sn_analytics_hour_dow_grid' )" ), 'every plugin read is guarded on the plugin being loaded' );
ok( 1 === preg_match( "/require_once __DIR__ \. '\/inc\/hero-pulse\.php'/", (string) file_get_contents( $root . '/functions.php' ) ), 'functions.php loads it' );
ok( false === strpos( (string) file_get_contents( $root . '/patterns/home-hero.php' ), 'pulse' ), 'the home page content is untouched: nothing in the hero pattern' );

echo "\n$pass passed, $fail failed\n";
exit( $fail > 0 ? 1 : 0 );
