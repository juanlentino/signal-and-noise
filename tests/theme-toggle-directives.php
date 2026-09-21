<?php
/**
 * The theme toggle's Interactivity API markup, run through CORE's own
 * server-side directive processor (#384, step two).
 *
 * The toggle used to be a classic IIFE that queried every `.sn-theme-toggle`
 * and wrote aria-pressed, aria-label and the label text by hand. It is now a
 * store (assets/js/dark-mode-toggle.js) and the binding is declared on the
 * markup in sn_dark_mode_toggle_markup(). Two things must hold, and this
 * suite pins both with core's real code, not a re-derivation:
 *
 *  1. The server-rendered bytes are the button's resting shape: pressed
 *     "false", "Switch to dark theme", "Light", hidden. A null directive
 *     value would blank the label (`data-wp-text`) or drop an attribute
 *     (`data-wp-bind`), which is why the server state carries the strings.
 *  2. Under the state the module sets after init (ready, dark or light), the
 *     same processor renders exactly what the browser will show, so the
 *     directive paths, the `!` negation and the boolean-on-aria rule are
 *     exercised where they run.
 *
 * Needs wp-includes/html-api and wp-includes/interactivity-api from a
 * WordPress 7.1 checkout (tests/lib/wp-html-api.php; CI fetches both).
 *
 * Run: php tests/theme-toggle-directives.php
 */
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', '/' ); }

$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }

require __DIR__ . '/lib/wp-html-api.php';
snt_require_wp_interactivity_api();

// The theme module's needs.
function add_action() {}
function add_filter() {}
function add_shortcode() {}
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr__( $s ) { return esc_attr( $s ); }
function esc_html__( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_js( $s ) { return $s; }
function esc_url( $s ) { return $s; }
function get_theme_file_uri( $p = '' ) { return 'https://x.test/' . $p; }
function shortcode_atts( $d, $a ) { return array_merge( $d, (array) $a ); }
function wp_json_encode( $v, $f = 0 ) { return json_encode( $v, $f ); }
function apply_filters( $h, $v ) { return $v; }
function wp_register_script_module() {}
function sn_asset_ver() { return '1'; }
require __DIR__ . '/../inc/dark-mode.php';

$root = dirname( __DIR__ );
$html = sn_dark_mode_toggle_markup( array( 'placement' => 'header' ) );

echo "theme-toggle-directives — #384 step two\n\nGroup 1: the markup declares the binding\n";
ok( str_contains( $html, 'data-wp-interactive="signal-noise/theme-toggle"' ), 'the button is an interactive region in the theme-toggle namespace' );
foreach ( array( 'data-wp-init="callbacks.init"', 'data-wp-on--click="actions.toggle"', 'data-wp-bind--hidden="!state.ready"', 'data-wp-bind--aria-pressed="state.isDark"', 'data-wp-bind--aria-label="state.ariaLabel"', 'data-wp-text="state.label"' ) as $d ) {
	ok( str_contains( $html, $d ), "carries $d" );
}
ok( ! str_contains( $html, 'data-label-light' ) && ! str_contains( $html, 'data-label-dark' ), 'the data-label-* attributes the IIFE read are gone (the strings ride the server state)' );

$js = (string) file_get_contents( "$root/assets/js/dark-mode-toggle.js" );
ok( str_contains( $js, "import { store } from '@wordpress/interactivity'" ) && str_contains( $js, "store( 'signal-noise/theme-toggle'" ), 'the module imports store() and registers the same namespace' );
$js_code = (string) preg_replace( '#//[^\n]*|/\*.*?\*/#s', '', $js );
ok( ! str_contains( $js_code, 'querySelectorAll( \'.sn-theme-toggle\' )' ) && ! str_contains( $js_code, 'addEventListener( \'click\'' ) && ! str_contains( $js_code, 'getElementById' ),
	'it queries no buttons and binds no click handler by hand — the directives do' );

echo "\nGroup 2: core's processor, the server state the theme set\n";
$p = wp_interactivity();
$server = wp_interactivity_state( 'signal-noise/theme-toggle' );
ok( array( 'isDark' => false, 'ready' => false, 'label' => 'Light', 'ariaLabel' => 'Switch to dark theme' ) === array_intersect_key( $server, array_flip( array( 'isDark', 'ready', 'label', 'ariaLabel' ) ) ),
	'the render set the resting state: not dark, not ready, "Light", "Switch to dark theme"' );
ok( isset( $server['labels']['light'], $server['labels']['dark'], $server['names']['toDark'], $server['names']['toLight'] ), 'both label strings and both action names ride the state (translation stays in PHP)' );

$rendered = $p->process_directives( $html );
$expected = '<button type="button" class="sn-theme-toggle sn-theme-toggle--header" hidden'
	. ' data-wp-interactive="signal-noise/theme-toggle" data-wp-init="callbacks.init" data-wp-on--click="actions.toggle"'
	. ' data-wp-bind--hidden="!state.ready" data-wp-bind--aria-pressed="state.isDark" data-wp-bind--aria-label="state.ariaLabel"'
	. ' aria-pressed="false" aria-label="Switch to dark theme">'
	. '<span class="sn-theme-toggle__dot" aria-hidden="true"></span>'
	. '<span class="sn-theme-toggle__label" data-wp-text="state.label">Light</span></button>';
ok( $rendered === $expected, 'resting render is byte for byte the button the IIFE shipped, plus the directives' . ( $rendered === $expected ? '' : "\n   got: $rendered" ) );
ok( preg_match( '/<button[^>]* hidden[ >]/', $rendered ) === 1, 'hidden survives the server pass (`!state.ready` is true, so a slow module degrades to no toggle, never a dead control)' );
ok( str_contains( $rendered, 'aria-pressed="false"' ), 'a boolean false on an aria- attribute becomes the string "false", not a dropped attribute' );

echo "\nGroup 3: the states the module reaches, rendered by the same processor\n";
$tp = new WP_HTML_Tag_Processor( '' );
foreach ( array(
	'dark'  => array( 'isDark' => true,  'ready' => true, 'label' => 'Dark',  'ariaLabel' => 'Switch to light theme' ),
	'light' => array( 'isDark' => false, 'ready' => true, 'label' => 'Light', 'ariaLabel' => 'Switch to dark theme' ),
) as $name => $state ) {
	wp_interactivity_state( 'signal-noise/theme-toggle', $state );
	$out = $p->process_directives( $html );
	$t   = new WP_HTML_Tag_Processor( $out );
	$t->next_tag( 'button' );
	ok( null === $t->get_attribute( 'hidden' ), "$name: ready removes hidden" );
	ok( ( $state['isDark'] ? 'true' : 'false' ) === $t->get_attribute( 'aria-pressed' ), "$name: aria-pressed is \"" . ( $state['isDark'] ? 'true' : 'false' ) . '"' );
	ok( $state['ariaLabel'] === $t->get_attribute( 'aria-label' ), "$name: the accessible name states the ACTION ({$state['ariaLabel']})" );
	ok( str_contains( $out, '>' . $state['label'] . '</span>' ), "$name: the visible label states the STATE ({$state['label']})" );
}

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
