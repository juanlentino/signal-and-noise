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
$GLOBALS['__qargs'] = null;
class WP_Query {
	public $posts;
	public function __construct( $args ) {
		$GLOBALS['__qargs'] = $args;
		$this->posts = array( (object) array( 'ID' => 1 ), (object) array( 'ID' => 2 ) );
	}
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

echo "Result: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
