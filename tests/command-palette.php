<?php
/**
 * Standalone fixture tests for the reader-facing command palette (v9.11.0).
 *
 * Stubs the WP primitives the data-island builder touches so the pure helper
 * in inc/command-palette.php runs without a WP load. The SN_CMDK_TEST sentinel
 * suppresses the hook wiring on require. Mirrors tests/notes-search.php.
 *
 * @since theme v9.11.0
 */

// SECURITY: Prevent web access. Test fixture, not a runtime module.
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) {
	http_response_code( 404 );
	exit;
}
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/' );
}
define( 'SN_CMDK_TEST', true );
$pass = 0; $fail = 0;
function ok( $c, $m ) {
	global $pass, $fail;
	if ( $c ) { $pass++; echo "PASS: $m\n"; }
	else { $fail++; echo "FAIL: $m\n"; }
}

function add_action( $h, $cb, $p = 10, $a = 1 ) { return true; }
function add_filter( $h, $cb, $p = 10, $a = 1 ) { return true; }
$GLOBALS['__filters'] = array();
function apply_filters( $tag, $value ) {
	return array_key_exists( $tag, $GLOBALS['__filters'] ?? array() ) ? $GLOBALS['__filters'][ $tag ] : $value;
}
function home_url( $p = '' ) { return 'https://x.test' . $p; }
function get_permalink( $p = null ) { return 'https://x.test/notes/n' . ( is_object( $p ) ? $p->ID : $p ) . '/'; }
function get_the_title( $p = null ) { return 'A &amp; B'; }
function sn_theme_pillar_descriptors() {
	return array(
		array( 'slug' => 'provenance/over-detection', 'title' => 'Provenance Over Detection' ),
		array( 'slug' => 'provenance/as-substrate', 'title' => 'Provenance As Substrate' ),
	);
}
if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $v, $f = 0, $d = 512 ) { return json_encode( $v, $f, $d ); }
}
$GLOBALS['__qargs']     = null;    // Args of the FIRST (recent) query per build.
$GLOBALS['__qargs_all'] = array(); // Full history: [0] recent, [1] notes.
class WP_Query {
	public $posts;
	public function __construct( $args ) {
		if ( null === $GLOBALS['__qargs'] ) {
			$GLOBALS['__qargs'] = $args;
		}
		$GLOBALS['__qargs_all'][] = $args;
		$this->posts = array( (object) array( 'ID' => 1 ), (object) array( 'ID' => 2 ) );
	}
}
function reset_qargs() {
	$GLOBALS['__qargs']     = null;
	$GLOBALS['__qargs_all'] = array();
}
function wp_reset_postdata() {}

require __DIR__ . '/../inc/command-palette.php';

$data = sn_cmdk_build_data();
ok( isset( $data['notesUrl'], $data['recent'], $data['pillars'] ), 'data island has notesUrl/recent/pillars' );
ok( $data['notesUrl'] === 'https://x.test/notes/', 'notesUrl is /notes/' );
ok( count( $data['pillars'] ) === 2, 'pillars from descriptors' );
ok( $data['pillars'][0]['u'] === 'https://x.test/provenance/over-detection/', 'pillar slug → home_url(/slug/)' );
ok( $data['recent'][0]['t'] === 'A & B', 'recent titles HTML-decoded' );
ok( $GLOBALS['__qargs']['posts_per_page'] === 8, 'recent query bounded to 8' );
ok( $GLOBALS['__qargs']['no_found_rows'] === true, 'recent query uses no_found_rows' );
ok( $GLOBALS['__qargs']['post_status'] === 'publish', 'recent query is publish-only' );

// XSS contract: JSON_HEX_TAG neutralizes a </script> in a title.
$enc = wp_json_encode( array( 't' => '</script>' ), JSON_HEX_TAG | JSON_UNESCAPED_SLASHES );
ok( strpos( $enc, '</script>' ) === false, 'JSON_HEX_TAG escapes a closing script tag' );

// v9.11.3: the visible trigger lives in the footer utility bar, not as a
// position:fixed overlay that collided with the colophon. Guard the move.
$footer = file_get_contents( __DIR__ . '/../parts/footer.html' );
ok( strpos( $footer, 'class="sn-cmdk-trigger"' ) !== false, 'footer template renders the command-palette trigger' );
ok( strpos( $footer, 'aria-keyshortcuts' ) !== false, 'footer trigger keeps aria-keyshortcuts a11y' );
$cmdk_src = file_get_contents( __DIR__ . '/../inc/command-palette.php' );
ok( strpos( $cmdk_src, "add_action( 'wp_footer'" ) === false, 'trigger is no longer injected as a floating wp_footer button' );
$pal_css = file_get_contents( __DIR__ . '/../assets/css/command-palette.css' );
ok( ! preg_match( '/\.sn-cmdk-trigger\s*\{[^}]*position\s*:\s*fixed/s', $pal_css ), 'trigger is no longer position:fixed' );

// v9.12.0: recent-notes count honors sn_palette_recent_count (default 8).
reset_qargs();
$GLOBALS['__filters']['sn_palette_recent_count'] = 4;
sn_cmdk_build_data();
ok( (int) $GLOBALS['__qargs']['posts_per_page'] === 4, 'palette: recent query honors sn_palette_recent_count=4' );
reset_qargs();
$GLOBALS['__filters'] = array();
sn_cmdk_build_data();
ok( (int) $GLOBALS['__qargs']['posts_per_page'] === 8, 'palette: default recent count is 8' );

// v9.12.0: enable/disable kill-switch (default on).
$GLOBALS['__filters'] = array();
ok( sn_cmdk_enabled() === true, 'palette: enabled by default' );
$GLOBALS['__filters']['sn_palette_enabled'] = false;
ok( sn_cmdk_enabled() === false, 'palette: sn_palette_enabled=false disables it' );
ok( in_array( 'sn-cmdk-off', sn_cmdk_body_class( array( 'home' ) ), true ), 'palette: body class sn-cmdk-off added when disabled' );
$GLOBALS['__filters'] = array();
ok( ! in_array( 'sn-cmdk-off', sn_cmdk_body_class( array( 'home' ) ), true ), 'palette: no sn-cmdk-off class when enabled' );

// ── v11.2.0: the `notes` corpus key — ALL published notes for ⌘K ranked search ──
reset_qargs();
$GLOBALS['__filters'] = array();
$data = sn_cmdk_build_data();
$notes = ( isset( $data['notes'] ) && is_array( $data['notes'] ) ) ? $data['notes'] : array();
ok( isset( $data['notes'] ) && is_array( $data['notes'] ), 'notes: data island carries a notes key' );
ok( count( $notes ) === 2, 'notes: one entry per returned post' );
ok( isset( $notes[0]['t'], $notes[0]['u'] ) && count( $notes[0] ) === 2, 'notes: entries are exactly {t,u}' );
ok( ( $notes[0]['t'] ?? '' ) === 'A & B', 'notes: titles HTML-decoded like recent' );
ok( ( $notes[0]['u'] ?? '' ) === 'https://x.test/notes/n1/', 'notes: u is the permalink' );
ok( count( $GLOBALS['__qargs_all'] ) === 2, 'notes: exactly two queries per build (recent + notes)' );
$nq = $GLOBALS['__qargs_all'][1] ?? array();
ok( (int) ( $nq['posts_per_page'] ?? 0 ) === 200, 'notes: query bounded to 200' );
ok( ( $nq['no_found_rows'] ?? false ) === true, 'notes: query uses no_found_rows' );
ok( ( $nq['post_status'] ?? '' ) === 'publish', 'notes: query is publish-only' );
ok( ( $nq['orderby'] ?? '' ) === 'date' && ( $nq['order'] ?? '' ) === 'DESC', 'notes: date DESC (stable tiebreak order for the JS)' );

// The cap honors sn_palette_notes_cap but can never exceed the 200 bound.
reset_qargs();
$GLOBALS['__filters']['sn_palette_notes_cap'] = 50;
sn_cmdk_build_data();
ok( (int) ( $GLOBALS['__qargs_all'][1]['posts_per_page'] ?? 0 ) === 50, 'notes: cap honors sn_palette_notes_cap=50' );
reset_qargs();
$GLOBALS['__filters']['sn_palette_notes_cap'] = 10000;
sn_cmdk_build_data();
ok( (int) ( $GLOBALS['__qargs_all'][1]['posts_per_page'] ?? 0 ) === 200, 'notes: cap is hard-clamped at 200 even when filtered higher' );
$GLOBALS['__filters'] = array();

// ── v11.2.0: JS source contract — vanilla ranking, XSS discipline intact ──
// (Source-assertion pattern per tests/keyboard-nav.php.)
$js = file_get_contents( __DIR__ . '/../assets/js/command-palette.js' );
ok( (bool) preg_match( '/data\.notes\b(?!Url)/', $js ), 'js: ranking reads the notes corpus (data.notes, not just notesUrl)' );
ok( strpos( $js, 'scoreNote' ) !== false, 'js: scoreNote token scorer present' );
ok( strpos( $js, 'MAX_RANKED' ) !== false, 'js: ranked list is capped (MAX_RANKED)' );
ok( ! preg_match( '/\.innerHTML\s*=|insertAdjacentHTML/', $js ), 'js: textContent discipline holds — no innerHTML/insertAdjacentHTML writes' );
ok( strpos( $js, "type: 'search'" ) !== false, 'js: the /notes/?s= search action row survives as fallback' );
// The no-model-in-browser commitment: plain arithmetic only — no network, no
// eval, no WASM, no workers smuggling a model in.
ok( ! preg_match( '/\bfetch\s*\(|XMLHttpRequest|WebAssembly|importScripts|\beval\s*\(/', $js ), 'js: no network/eval/WASM — vanilla arithmetic only (no-model commitment)' );

echo "Result: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
