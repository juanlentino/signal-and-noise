<?php
/**
 * Standalone fixture tests for inc/frontend-filters.php (v10.49.0).
 *
 * The module's anonymous closures are now named functions, so its
 * behavior-bearing seams are pinned: the Spotify oEmbed rewrite (through
 * WP_HTML_Tag_Processor since #383, loaded here by tests/lib/wp-html-api.php),
 * the core/social-link path-relative URL shim (WORDPRESS-REFERENCE §1.1 /
 * gotcha #1: core's render callback turns "/notes/feed/" into
 * "https:///notes/feed/"), and the hook wiring. The site-wide output-buffer
 * generator rewrite this file used to pin is GONE (#383): this file now pins
 * its absence, because that buffer was the one callback that could send an
 * empty 200.
 *
 * @since theme v10.49.0
 */

// SECURITY: CLI-only fixture.
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) {
	http_response_code( 404 );
	exit;
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/' );
}

// ── Stub hook registries (cross-package-listeners pattern) ──
$GLOBALS['__test_filters'] = array();
$GLOBALS['__test_actions'] = array();

function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['__test_filters'][ $hook ][] = $callback;
	return true;
}
function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['__test_actions'][ $hook ][] = $callback;
	return true;
}
function remove_action( $hook, $callback, $priority = 10 ) { return true; }
function home_url( $path = '' ) { return 'https://example.com' . $path; }
function __return_empty_string() { return ''; }

// Deliberately NOT defining SN_FRONTEND_FILTERS_TEST: the wiring must run
// against the stub registries so the registrations themselves are pinned.
require_once __DIR__ . '/lib/wp-html-api.php';
snt_require_wp_html_api();
require __DIR__ . '/../inc/frontend-filters.php';

$pass = 0; $fail = 0;
function ok( $cond, $msg ) { global $pass, $fail; if ( $cond ) { $pass++; echo "PASS: $msg\n"; } else { $fail++; echo "FAIL: $msg\n"; } }

// ── 1. Wiring: every closure is now a NAMED function on its hook ──
ok( in_array( 'sn_skip_link', $GLOBALS['__test_actions']['wp_body_open'] ?? array(), true ), 'wp_body_open → sn_skip_link (named)' );
ok( in_array( 'sn_spotify_embed_dark', $GLOBALS['__test_filters']['embed_oembed_html'] ?? array(), true ), 'embed_oembed_html → sn_spotify_embed_dark (named)' );
ok( in_array( 'sn_social_link_relative_url', $GLOBALS['__test_filters']['render_block_data'] ?? array(), true ), 'render_block_data → sn_social_link_relative_url (named)' );
ok( in_array( '__return_empty_string', $GLOBALS['__test_filters']['the_generator'] ?? array(), true ), 'the_generator → __return_empty_string' );

// ── 2. #383: the page-wide generator buffer is GONE ──
// It stripped nothing (core's tag already goes through remove_action +
// the_generator above; no active plugin emits one; /notes/ never ran it) and
// an output-buffer callback whose rewrite fails sends an empty 200, which
// Cloudflare cached for /provenance/ twice. The theme now has NO callback on a
// page-wide buffer, so that class of fault has nowhere to happen.
ok( ! function_exists( 'sn_strip_generator_meta' ), '#383: sn_strip_generator_meta() no longer exists' );
ok( ! function_exists( 'sn_generator_meta_buffer_start' ) && ! function_exists( 'sn_emit_header' ) && ! defined( 'SN_PAGE_BODY_FLOOR_BYTES' ), '#383: the buffer starter, the header seam and the 4 KB floor went with it' );
ok( ! isset( $GLOBALS['__test_actions']['template_redirect'] ), '#383: this module registers nothing on template_redirect: no ob_start on the page' );
$ff_src = (string) file_get_contents( __DIR__ . '/../inc/frontend-filters.php' );
ok( false === strpos( $ff_src, 'ob_start' ) && 0 === preg_match( '/preg_replace(?:_callback)?\s*\(|substr_replace\s*\(/', $ff_src ), '#383: inc/frontend-filters.php opens no output buffer and rewrites no markup with preg_replace or a byte splice' );
ok( false !== strpos( $ff_src, "remove_action( 'wp_head', 'wp_generator' )" ) && false !== strpos( $ff_src, "add_filter( 'the_generator', '__return_empty_string' )" ), 'the documented strip stays: remove_action + the_generator filter' );

// ── 3. Social-link shim: path-relative in, everything else out ──
$b = sn_social_link_relative_url( array( 'blockName' => 'core/social-link', 'attrs' => array( 'url' => '/notes/feed/' ) ) );
ok( 'https://example.com/notes/feed/' === ( $b['attrs']['url'] ?? '' ), 'IN: path-relative URL swapped for home_url()' );
foreach ( array(
	'absolute'          => 'https://mastodon.example/@me',
	'protocol-relative' => '//cdn.example/x',
	'fragment'          => '#top',
	'empty'             => '',
) as $label => $url ) {
	$out = sn_social_link_relative_url( array( 'blockName' => 'core/social-link', 'attrs' => array( 'url' => $url ) ) );
	ok( $url === ( $out['attrs']['url'] ?? null ), "OUT: $label URL passes through unchanged" );
}
$other = array( 'blockName' => 'core/paragraph', 'attrs' => array( 'url' => '/notes/' ) );
ok( $other === sn_social_link_relative_url( $other ), 'OUT: non-social-link block passes through unchanged' );

// ── 4. Spotify oEmbed dark theme + square corners (through the HTML API, #383) ──
$sp_in  = '<iframe src="https://open.spotify.com/embed/album/xyz" style="border-radius: 12px"></iframe>';
$sp_out = sn_spotify_embed_dark( $sp_in, 'https://open.spotify.com/album/xyz' );
// set_attribute() runs src through esc_url(), whose display step writes a
// bare '&' as '&#038;'. The bytes moved from '&theme=0' (origin/main's regex);
// the request a browser makes did not, and the decoded value below says so.
ok( false !== strpos( $sp_out, 'src="https://open.spotify.com/embed/album/xyz&#038;theme=0"' ), '#383: Spotify src gains theme=0 (dark), serialised as &#038;theme=0 by esc_url' );
ok( false !== strpos( $sp_out, 'border-radius: 0' ) && false === strpos( $sp_out, 'border-radius: 12px' ), 'Spotify inline border-radius squared off' );
$yt = '<iframe src="https://www.youtube.com/embed/xyz"></iframe>';
ok( $yt === sn_spotify_embed_dark( $yt, 'https://www.youtube.com/watch?v=xyz' ), 'non-Spotify embeds pass through unchanged' );
// The identical-output proof: origin/main's bytes for the same fixtures
// (captured 2026-09-20), compared as what a browser builds (decoded src,
// decoded style), plus the real Spotify embed markup so the pin covers the
// attribute order and the query string the live site sees.
$real_in  = '<iframe style="border-radius: 12px" src="https://open.spotify.com/embed/album/4aawyAB9vmqN3uQ7FjRGTy?utm_source=generator" width="100%" height="352" frameBorder="0" allowfullscreen="" allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture" loading="lazy"></iframe>';
$real_out = sn_spotify_embed_dark( $real_in, 'https://open.spotify.com/album/4aawyAB9vmqN3uQ7FjRGTy' );
foreach ( array(
	'fixture' => array( $sp_out, '<iframe src="https://open.spotify.com/embed/album/xyz&theme=0" style="border-radius: 0"></iframe>' ),
	'real'    => array( $real_out, '<iframe style="border-radius: 0" src="https://open.spotify.com/embed/album/4aawyAB9vmqN3uQ7FjRGTy?utm_source=generator&theme=0" width="100%" height="352" frameBorder="0" allowfullscreen="" allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture" loading="lazy"></iframe>' ),
) as $name => $pair ) {
	ok( snt_html_shape( $pair[1] ) === snt_html_shape( $pair[0] ), "#383 $name: the port paints the iframe origin/main's regex painted (same decoded src, same style, same everything else)" );
}
ok( false !== strpos( $real_out, 'src="https://open.spotify.com/embed/album/4aawyAB9vmqN3uQ7FjRGTy?utm_source=generator&#038;theme=0"' ) && false !== strpos( $real_out, 'style="border-radius: 0"' ), '#383: the live embed shape, bytes as shipped' );
ok( false !== strpos( $ff_src, "next_tag( 'IFRAME' )" ) && false !== strpos( $ff_src, "set_attribute( 'src'" ), '#383: the iframe is found and written through WP_HTML_Tag_Processor' );

// ── 5. Skip link ──
ob_start();
sn_skip_link();
$link = ob_get_clean();
ok( false !== strpos( $link, 'class="sn-skip-link"' ) && false !== strpos( $link, 'href="#wp--skip-link--target"' ), 'skip link renders with brand class + skip target' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
