<?php
/**
 * Standalone fixture tests for the humans.txt route + maker's mark (C4, v10.5.0).
 *
 * inc/humans-txt.php serves a flat /humans.txt (IndieWeb convention) via a
 * template_redirect virtual route, advertises it with a rel=author head link,
 * and emits one dry maker's-mark comment in <head>. Owner/theme facts are read
 * from wp_get_theme() so they never drift from style.css; the social URLs +
 * stack lines are hardcoded in lockstep with parts/footer.html + the CMS-owned /colophon content.
 *
 * @since theme v10.5.0
 */

// SECURITY: CLI-only fixture.
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', '/' ); }
define( 'SN_HUMANS_TXT_TEST', true ); // suppress template_redirect/wp_head wiring on require

// Buffer all test output so the real header() call in sn_humans_txt_send() does
// not warn "headers already sent" after our PASS lines echo. PHP auto-flushes
// output buffers at termination, so the summary line still prints.
ob_start();

$pass = 0; $fail = 0;
function ok( $cond, $msg ) { global $pass, $fail; if ( $cond ) { $pass++; echo "PASS: $msg\n"; } else { $fail++; echo "FAIL: $msg\n"; } }

// --- Stub WP primitives the module touches ---
$GLOBALS['__status'] = 0;
function add_action() { return true; }
function status_header( $code ) { $GLOBALS['__status'] = (int) $code; }
function get_option( $k, $d = false ) { return 'blog_charset' === $k ? 'UTF-8' : $d; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_url( $u ) { return str_replace( array( '"', ' ' ), '', (string) $u ); }
function home_url( $p = '' ) { return 'https://juanlentino.com' . $p; }
function wp_get_theme() {
	return new class {
		public function get( $key ) {
			$map = array( 'Author' => 'Juan Lentino', 'Name' => 'Signal &amp; Noise', 'Version' => '10.5.0' );
			return $map[ $key ] ?? '';
		}
	};
}

require __DIR__ . '/../inc/humans-txt.php';

// --- Route matcher (pure helper) ---
ok( function_exists( 'sn_humans_txt_is_request' ), 'sn_humans_txt_is_request() is defined' );
ok( sn_humans_txt_is_request( 'humans' ) === true, 'matches the sn_machine=humans query var' );
ok( sn_humans_txt_is_request( '/humans.txt' ) === false, 'the REQUEST_URI path alone no longer matches (fallback removed)' );
ok( sn_humans_txt_is_request( 'security' ) === false, 'rejects another machine file' );
ok( sn_humans_txt_is_request( '' ) === false, 'rejects an empty query var' );

// --- Body (pure builder) ---
ok( function_exists( 'sn_humans_txt_body' ), 'sn_humans_txt_body() is defined' );
$body = sn_humans_txt_body();
ok( is_string( $body ) && '' !== $body, 'body is a non-empty string' );
ok( strpos( $body, 'Juan Lentino' ) !== false, 'body references the owner (Juan Lentino)' );
ok( strpos( $body, 'juanlentino.com' ) !== false, 'body references the site URL' );
ok( strpos( $body, 'WordPress' ) !== false, 'body references the platform (WordPress)' );
ok( strpos( $body, 'Signal & Noise' ) !== false, 'body references the theme name' );
ok( strpos( $body, '&amp;' ) === false, 'plain text: the theme header entity is decoded, never printed as &amp;' );
ok( strpos( $body, 'https://orcid.org/0009-0006-8151-5920' ) !== false, 'PROFILES lists the ORCID iD' );
ok( strpos( $body, '10.5.0' ) !== false, 'body carries the theme version (read from wp_get_theme, no drift)' );
ok( strpos( $body, 'open.spotify.com' ) !== false, 'body lists the Spotify profile (lockstep with footer)' );
ok( strpos( $body, 'x.com/juan_lentino' ) !== false, 'body lists the X profile (v10.13.4, lockstep with footer)' );
ok( false === strpos( $body, "\xE2\x80\x94" ), 'body uses straight ASCII, no em-dash U+2014 (v10.13.4)' );

// 2026-10-06: one source of truth. With the plugin, the TECHNOLOGY facts are
// the colophon's own (sn_colophon_plain_facts()); without it, a fallback that
// says nothing the colophon contradicts.
ok( false === strpos( $body, 'vanilla ES5' ) && false === strpos( $body, 'for SEO, search & ops' ), 'plugin absent: the fallback drops the two claims the colophon corrected (ES5, the thin plugin line)' );
ok( false !== strpos( $body, 'Code: hand-written PHP, plain JavaScript and theme.json, no build step' ), 'plugin absent: the fallback states the code fact the colophon states' );
if ( ! function_exists( 'sn_colophon_plain_facts' ) ) {
	function sn_colophon_plain_facts() {
		return array( 'Platform' => 'WordPress with Full Site Editing: FACT-FROM-PLUGIN.', 'Records' => 'each note I publish gets a fingerprint (SHA-256).' );
	}
}
$with = sn_humans_txt_body();
ok( false !== strpos( $with, "Platform: WordPress with Full Site Editing: FACT-FROM-PLUGIN.\n" ) && false !== strpos( $with, "Records: each note I publish gets a fingerprint (SHA-256).\n" ), 'plugin present: the facts are the colophon\'s, one per line, Label: text' );
ok( false === strpos( $with, 'Code: hand-written PHP, plain JavaScript and theme.json' ), 'plugin present: the fallback lines are not printed beside them' );
ok( false !== strpos( $with, 'Standards - HTML5, CSS3' ), 'the Standards line (not a colophon fact) stays' );

// --- Head links: rel=author autodiscovery + maker's mark ---
ok( function_exists( 'sn_humans_txt_head_links' ), 'sn_humans_txt_head_links() is defined' );
ob_start();
sn_humans_txt_head_links();
$head = ob_get_clean();
ok( strpos( $head, 'rel="author"' ) !== false, 'emits a rel=author autodiscovery link' );
ok( strpos( $head, '/humans.txt' ) !== false, 'autodiscovery link points at /humans.txt' );
ok( substr_count( $head, '<!--' ) === 1, 'emits exactly one maker\'s-mark comment' );
ok( strpos( $head, 'Juan Lentino' ) !== false, "maker's mark names the maker" );

// --- Serve path forces HTTP 200 (the virtual route has no backing post, so WP
//     would otherwise commit a 404 in handle_404() before template_redirect). ---
ok( function_exists( 'sn_humans_txt_send' ), 'sn_humans_txt_send() is defined' );
$GLOBALS['__status'] = 0;
ob_start();
sn_humans_txt_send();
$served = ob_get_clean();
ok( 0 === $GLOBALS['__status'], 'serve path leaves the status to core (the rewrite route resolves as 200, not a 404)' );
ok( strpos( $served, 'Juan Lentino' ) !== false, 'serve path emits the humans.txt body' );

ok( 1 === preg_match( '/function sn_humans_txt_send\(\).*?sn_edge_cache_send_machine\(\)/s', (string) file_get_contents( dirname( __DIR__ ) . '/inc/humans-txt.php' ) ), 'humans.txt sends the machine cache lifetime: since the rewrite rule serves it, core no longer sends its 404 no-cache headers' );
echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
