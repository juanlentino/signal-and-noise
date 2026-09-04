<?php
/**
 * Standalone fixture tests for the <head> sweep (A4, theme v10.5.0).
 *
 * Two behaviours:
 *   1. inc/frontend-filters.php strips the legacy xmlrpc-era head links
 *      (rsd_link / wlwmanifest_link) via file-scope remove_action() — same
 *      mechanism as the existing wp_generator strip.
 *   2. inc/dark-mode.php emits a PAIR of <meta name="theme-color"> tags in a
 *      priority-1 wp_head closure, one per colour scheme, each pinned to the
 *      ground of the palette it describes so the two can never drift apart.
 *      (v11.13.0: was a single brand-red value emitted by
 *      inc/assets-frontend.php. Red painted the mobile browser bar the accent
 *      colour regardless of the page beneath it, which read as a notification
 *      rather than as chrome — and with a dark palette shipping, one value
 *      cannot describe two grounds.)
 *
 * Both files register at file scope (closures / bare remove_action), so the
 * harness records add_action/remove_action args and invokes the captured
 * wp_head closure directly rather than booting WP. Mirrors tests/print-styles.php.
 *
 * @since theme v10.5.0
 */

// SECURITY: Prevent web access. Test fixture, not a runtime module.
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) {
	http_response_code( 404 );
	exit;
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/' );
}

// ── Recording stubs ──
$GLOBALS['__actions'] = array(); // each: [ 'hook' => , 'cb' => , 'pri' => ]
$GLOBALS['__removed'] = array(); // each: [ 'hook' => , 'cb' => ] (cb is a callable name)

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $cb = null, $pri = 10, $args = 1 ) {
		$GLOBALS['__actions'][] = array( 'hook' => $hook, 'cb' => $cb, 'pri' => $pri );
		return true;
	}
}
if ( ! function_exists( 'add_filter' ) ) {
	function add_filter() { return true; }
}
if ( ! function_exists( 'remove_action' ) ) {
	function remove_action( $hook, $cb, $pri = 10 ) {
		$GLOBALS['__removed'][] = array( 'hook' => $hook, 'cb' => $cb );
		return true;
	}
}
if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $u ) { return str_replace( array( '"', ' ' ), array( '', '' ), (string) $u ); }
}
if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $a ) { return htmlspecialchars( (string) $a, ENT_QUOTES ); }
}
if ( ! function_exists( 'get_theme_file_uri' ) ) {
	function get_theme_file_uri( $path = '' ) {
		return 'https://example.test/wp-content/themes/signal-and-noise/' . ltrim( $path, '/' );
	}
}
if ( ! function_exists( 'get_theme_file_path' ) ) {
	function get_theme_file_path( $path = '' ) {
		return realpath( __DIR__ . '/..' ) . '/' . ltrim( $path, '/' );
	}
}
if ( ! function_exists( 'wp_get_theme' ) ) {
	function wp_get_theme() {
		return new class {
			public function get( $key ) { return '10.5.0'; }
		};
	}
}
if ( ! function_exists( 'wp_enqueue_style' ) ) {
	function wp_enqueue_style() {}
}
if ( ! function_exists( 'wp_enqueue_script' ) ) {
	function wp_enqueue_script() {}
}
// v11.13.0: inc/dark-mode.php registers a shortcode and enqueues at file scope.
if ( ! function_exists( 'add_shortcode' ) ) {
	function add_shortcode( $tag, $cb ) { $GLOBALS['__shortcodes'][ $tag ] = $cb; }
}
if ( ! function_exists( 'esc_js' ) ) {
	function esc_js( $s ) { return $s; }
}
if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $s, $d = '' ) { return $s; }
}
if ( ! function_exists( 'esc_attr__' ) ) {
	function esc_attr__( $s, $d = '' ) { return $s; }
}
if ( ! function_exists( 'is_page' ) ) {
	function is_page() { return false; }
}
if ( ! function_exists( 'is_singular' ) ) {
	function is_singular() { return false; }
}

$pass = 0; $fail = 0;
function ok( $cond, $label ) {
	global $pass, $fail;
	if ( $cond ) { $pass++; echo "PASS: $label\n"; }
	else { $fail++; echo "FAIL: $label\n"; }
}

/** True if a [hook, cb] removal was recorded. */
function removed_has( $hook, $cb ) {
	foreach ( $GLOBALS['__removed'] as $r ) {
		if ( $r['hook'] === $hook && $r['cb'] === $cb ) {
			return true;
		}
	}
	return false;
}

/** Invoke every wp_head closure at a given priority, return concatenated output. */
function render_wp_head_at( $priority ) {
	$out = '';
	foreach ( $GLOBALS['__actions'] as $a ) {
		if ( 'wp_head' === $a['hook'] && (int) $a['pri'] === (int) $priority && is_callable( $a['cb'] ) ) {
			ob_start();
			call_user_func( $a['cb'] );
			$out .= ob_get_clean();
		}
	}
	return $out;
}

// ── 1. Head-link strip (inc/frontend-filters.php) ──
require __DIR__ . '/../inc/frontend-filters.php';

ok( removed_has( 'wp_head', 'wp_generator' ), 'existing wp_generator strip still present (sanity)' );
ok( removed_has( 'wp_head', 'rsd_link' ), 'A4: rsd_link (EditURI/RSD) removed from wp_head' );
ok( removed_has( 'wp_head', 'wlwmanifest_link' ), 'A4: wlwmanifest_link removed from wp_head' );

// ── 2. theme-color meta (inc/assets-frontend.php) ──
require __DIR__ . '/../inc/assets-frontend.php';

require __DIR__ . '/../inc/dark-mode.php';

$head1 = strtolower( render_wp_head_at( 1 ) );
ok( strpos( $head1, 'name="theme-color"' ) !== false, 'A4: <meta name="theme-color"> emitted in priority-1 wp_head closure' );
ok( substr_count( $head1, 'name="theme-color"' ) === 2, 'A4: TWO theme-color metas — one per colour scheme' );
ok( strpos( $head1, 'media="(prefers-color-scheme: light)"' ) !== false, 'A4: the light variant is scheme-scoped' );
ok( strpos( $head1, 'media="(prefers-color-scheme: dark)"' ) !== false, 'A4: the dark variant is scheme-scoped' );

// The favicon links moved with it and got the same treatment: a #171718 mark
// disappears into a dark tab strip exactly as it would into a dark page.
ok( strpos( $head1, 'favicon-32-dark.png' ) !== false, 'A4: a dark favicon variant is offered' );
// v12.18.6: asserted by ROLE, not by filename. This pinned the literal
// `favicon-180-dark.png`, and when the apple-touch-icon moved to an OPAQUE file
// (iOS renders home-screen transparency as black — that file is 69% transparent
// pixels) the pin went red on a change that preserved exactly the property it
// was written to protect. A dark variant is still offered; it is a different
// file, and the pin should not have cared which.
ok( (bool) preg_match( '/rel="apple-touch-icon"[^>]*media="\(prefers-color-scheme: dark\)"/', $head1 )
	|| (bool) preg_match( '/media="\(prefers-color-scheme: dark\)"[^>]*rel="apple-touch-icon"/', $head1 ),
	'A4: a dark apple-touch-icon variant is offered' );
ok( strpos( $head1, 'app-icon-180-dark.png' ) !== false,
	'A4: and it is the OPAQUE one — a transparent home-screen icon renders as a black tile on iOS' );

// ── 3. Drift guard: each meta MUST equal the GROUND of its own palette ──
// Pinned to the RELATIONSHIP, not to a literal. A hardcoded '#0a0a0a' here
// would freeze today's value as correct and keep passing after the palette
// moved — the failure mode this repo has hit before with a pinned URL.
$theme_json = json_decode( (string) file_get_contents( realpath( __DIR__ . '/..' ) . '/theme.json' ), true );
$light_ground = '';
foreach ( ( $theme_json['settings']['color']['palette'] ?? array() ) as $c ) {
	if ( 'void' === ( $c['slug'] ?? '' ) ) {
		$light_ground = strtolower( $c['color'] );
	}
}
ok( '' !== $light_ground, 'theme.json `void` slug resolves (the light ground)' );
ok( strpos( $head1, 'content="' . $light_ground . '" media="(prefers-color-scheme: light)"' ) !== false,
	'the light theme-color IS theme.json `void` — the page ground, not the accent' );

// The dark ground comes from the shipped CSS, for the same reason.
$crit = (string) preg_replace( '#/\*.*?\*/#s', '', (string) file_get_contents( realpath( __DIR__ . '/..' ) . '/assets/css/critical.css' ) );
$dark_ground = '';
if ( preg_match( '/:root\[data-theme="dark"\]\s*\{[^}]*?--wp--preset--color--void\s*:\s*(#[0-9a-f]{3,6})/is', $crit, $m ) ) {
	$dark_ground = strtolower( $m[1] );
}
ok( '' !== $dark_ground, 'the dark `void` token resolves from critical.css' );
ok( strpos( $head1, 'content="' . $dark_ground . '" media="(prefers-color-scheme: dark)"' ) !== false,
	'the dark theme-color IS the dark `void` token — browser chrome matches the page under it' );
ok( $light_ground !== $dark_ground, 'the two grounds actually differ (a copy-paste slip would make both metas identical)' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
