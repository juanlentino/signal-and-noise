<?php
/**
 * Standalone fixture tests for the Provenance surface (v10.30.0).
 *
 * Black-box + deterministic. The theme module inc/provenance-surface.php only
 * places two plugin-owned render helpers on single Notes via shortcodes, so the
 * tests stub the WP primitives it touches (add_shortcode / add_filter /
 * get_the_ID / do_shortcode / shortcode_unautop) and a FAKE
 * sn_prov_render_chip / sn_prov_render_panel to stand in for the companion
 * plugin. Mirrors tests/404-recovery.php + tests/related-notes.php.
 *
 * Unlike the related-notes/404 tests, this one does NOT define the
 * SN_PROVENANCE_SURFACE_TEST guard: it WANTS the module's registration block to
 * run so the stubbed add_shortcode / add_filter can capture what got wired.
 *
 * @since theme v10.30.0
 */

// SECURITY: Prevent web access. Test fixture, not a runtime module.
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) {
	http_response_code( 404 );
	exit;
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/' );
}

// ── Controllable stub state ──────────────────────────────────────────
$GLOBALS['__shortcodes']       = array(); // tag => callback captured by add_shortcode.
$GLOBALS['__filters_added']    = array(); // [hook, cb, prio, args] captured by add_filter.
$GLOBALS['shortcode_tags']     = array(); // read by shortcode_unautop().
$GLOBALS['__the_id']           = 0;       // get_the_ID() return.
$GLOBALS['__do_shortcode_ran'] = false;

// add_shortcode / add_filter capture registrations (module is NOT test-guarded
// here, so its registration block runs and feeds these stubs).
if ( ! function_exists( 'add_shortcode' ) ) {
	function add_shortcode( $tag, $callback ) {
		$GLOBALS['__shortcodes'][ $tag ]   = $callback;
		$GLOBALS['shortcode_tags'][ $tag ] = $callback; // so shortcode_unautop() recognises the token.
	}
}
if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['__filters_added'][] = array( $hook, $callback, $priority, $accepted_args );
		return true;
	}
}
if ( ! function_exists( 'get_the_ID' ) ) {
	function get_the_ID() {
		return (int) $GLOBALS['__the_id'];
	}
}
if ( ! function_exists( 'wp_spaces_regexp' ) ) {
	function wp_spaces_regexp() {
		return '[\r\n\t ]|\xC2\xA0|&nbsp;';
	}
}
// Real-shaped shortcode_unautop from WP trunk — strips a <p> wrapper around a
// REGISTERED shortcode token (reads global $shortcode_tags, populated above).
if ( ! function_exists( 'shortcode_unautop' ) ) {
	function shortcode_unautop( $text ) {
		global $shortcode_tags;
		if ( empty( $shortcode_tags ) || ! is_array( $shortcode_tags ) ) {
			return $text;
		}
		$tagregexp = implode( '|', array_map( 'preg_quote', array_keys( $shortcode_tags ) ) );
		$spaces    = wp_spaces_regexp();
		$pattern   = '/<p>(?:' . $spaces . ')*+(\\[(' . $tagregexp . ')(?![\\w-])[^\\]\\/]*(?:\\/(?!\\])[^\\]\\/]*)*?(?:\\/\\]|\\](?:[^\\[]*+(?:\\[(?!\\/\\2\\])[^\\[]*+)*+\\[\\/\\2\\])?))(?:' . $spaces . ')*+<\\/p>/';
		return preg_replace( $pattern, '$1', $text );
	}
}
// Faithful do_shortcode: resolves the registered tokens by calling the REAL
// theme shortcode callbacks (so the block-level panel output is what gets
// substituted). A naive str_replace of [token]→literal could never expose the
// <p>-wrap bug the shortcode_unautop() step exists to prevent.
if ( ! function_exists( 'do_shortcode' ) ) {
	function do_shortcode( $content ) {
		$GLOBALS['__do_shortcode_ran'] = true;
		if ( false !== strpos( $content, '[sn_prov_chip]' ) ) {
			$content = str_replace( '[sn_prov_chip]', sn_prov_chip_shortcode(), $content );
		}
		if ( false !== strpos( $content, '[sn_prov_panel]' ) ) {
			$content = str_replace( '[sn_prov_panel]', sn_prov_panel_shortcode(), $content );
		}
		return $content;
	}
}

require __DIR__ . '/../inc/provenance-surface.php';

$pass = 0; $fail = 0;
function ok( $cond, $label ) {
	global $pass, $fail;
	if ( $cond ) { $pass++; echo "PASS: $label\n"; }
	else { $fail++; echo "FAIL: $label\n"; }
}

// ── Registration: both shortcodes + the render_block bridge are wired ──
ok( array_key_exists( 'sn_prov_chip', $GLOBALS['__shortcodes'] ), 'register: [sn_prov_chip] shortcode registered' );
ok( array_key_exists( 'sn_prov_panel', $GLOBALS['__shortcodes'] ), 'register: [sn_prov_panel] shortcode registered' );
ok(
	( $GLOBALS['__shortcodes']['sn_prov_chip'] ?? null ) === 'sn_prov_chip_shortcode'
	&& is_callable( $GLOBALS['__shortcodes']['sn_prov_chip'] ),
	'register: [sn_prov_chip] → sn_prov_chip_shortcode (callable)'
);
ok(
	( $GLOBALS['__shortcodes']['sn_prov_panel'] ?? null ) === 'sn_prov_panel_shortcode'
	&& is_callable( $GLOBALS['__shortcodes']['sn_prov_panel'] ),
	'register: [sn_prov_panel] → sn_prov_panel_shortcode (callable)'
);
$bridge_added = false;
foreach ( $GLOBALS['__filters_added'] as $f ) {
	if ( 'render_block' === $f[0] && 'sn_provenance_surface_render_block_bridge' === $f[1] ) {
		// Same signature/priority as the related-notes + 404 bridges: prio 10, 2 args.
		ok( 10 === (int) $f[2] && 2 === (int) $f[3], 'register: bridge on render_block at priority 10, accepts 2 args' );
		$bridge_added = true;
	}
}
ok( $bridge_added, 'register: render_block bridge filter registered' );

// ── function_exists guard: plugin ABSENT → both shortcodes render '' ──
// The plugin helpers are not defined yet, so the ternary short-circuits to ''
// (and never reaches get_the_ID). This proves graceful degradation when the
// companion plugin is absent.
ok( ! function_exists( 'sn_prov_render_chip' ), 'guard: sn_prov_render_chip undefined at this point (precondition)' );
ok( ! function_exists( 'sn_prov_render_panel' ), 'guard: sn_prov_render_panel undefined at this point (precondition)' );
ok( sn_prov_chip_shortcode() === '', 'guard: chip shortcode returns "" when plugin fn absent' );
ok( sn_prov_panel_shortcode() === '', 'guard: panel shortcode returns "" when plugin fn absent' );

// ── Plugin PRESENT → shortcode returns the plugin helper's output ─────
// Fake plugin helpers echo the received post_id so the test can prove BOTH
// that the shortcode returns the helper output AND that get_the_ID() flowed
// through as the argument. Declared CONDITIONALLY (not at top level) so PHP
// does NOT hoist them at compile time — they come into existence only here, at
// runtime, which is what makes the "plugin absent" assertions above valid.
if ( ! function_exists( 'sn_prov_render_chip' ) ) {
	function sn_prov_render_chip( $post_id ) {
		return '<span class="sn-prov-chip" data-id="' . (int) $post_id . '">verified</span>';
	}
}
if ( ! function_exists( 'sn_prov_render_panel' ) ) {
	function sn_prov_render_panel( $post_id ) {
		return '<details class="sn-prov-panel" data-id="' . (int) $post_id . '"><summary>Provenance</summary></details>';
	}
}

$GLOBALS['__the_id'] = 42;
ok(
	sn_prov_chip_shortcode() === '<span class="sn-prov-chip" data-id="42">verified</span>',
	'present: chip shortcode returns sn_prov_render_chip(get_the_ID()) verbatim'
);
ok(
	sn_prov_panel_shortcode() === '<details class="sn-prov-panel" data-id="42"><summary>Provenance</summary></details>',
	'present: panel shortcode returns sn_prov_render_panel(get_the_ID()) verbatim'
);

// ── BRIDGE: resolves ONLY blocks carrying a token; no-op otherwise ────
// Chip token (wpautop-shaped): shortcode_unautop() strips the <p> BEFORE
// do_shortcode, so the pill is not left wrapped in an invalid <p>.
$GLOBALS['__do_shortcode_ran'] = false;
$chip_out = sn_provenance_surface_render_block_bridge( '<p>[sn_prov_chip]</p>', array() );
ok( true === $GLOBALS['__do_shortcode_ran'], 'bridge: do_shortcode runs when [sn_prov_chip] present' );
ok( strpos( $chip_out, 'class="sn-prov-chip"' ) !== false, 'bridge: chip token resolved to the plugin markup' );
ok( strpos( $chip_out, '[sn_prov_chip]' ) === false, 'bridge: raw chip token gone after resolution' );
ok( strpos( $chip_out, '<p>' ) === false, 'bridge: no leftover <p> wrapping the resolved chip' );

// Panel token (wpautop-shaped, block-level output): must NOT end up <p>-wrapped.
$GLOBALS['__do_shortcode_ran'] = false;
$panel_out = sn_provenance_surface_render_block_bridge( '<p>[sn_prov_panel]</p>', array() );
ok( true === $GLOBALS['__do_shortcode_ran'], 'bridge: do_shortcode runs when [sn_prov_panel] present' );
ok( strpos( $panel_out, '<details class="sn-prov-panel"' ) !== false, 'bridge: panel token resolved to the block-level <details>' );
ok( strpos( $panel_out, '[sn_prov_panel]' ) === false, 'bridge: raw panel token gone after resolution' );
ok( strpos( $panel_out, '<p><details' ) === false, 'bridge: block-level panel not directly wrapped in <p>' );
ok( strpos( $panel_out, '<p>' ) === false, 'bridge: no leftover <p> around the block-level panel' );

// No token → bridge is a pure no-op (do_shortcode NEVER runs, content verbatim).
$GLOBALS['__do_shortcode_ran'] = false;
$untouched = sn_provenance_surface_render_block_bridge( '<p>no token here</p>', array() );
ok( false === $GLOBALS['__do_shortcode_ran'], 'bridge: do_shortcode NOT run when neither token present' );
ok( $untouched === '<p>no token here</p>', 'bridge: content returned unchanged when neither token present' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
