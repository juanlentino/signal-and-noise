<?php
/**
 * Standalone fixture tests for inc/frontend-filters.php (v10.49.0).
 *
 * The module's five anonymous closures are now named functions, so the two
 * behavior-bearing seams it has carried since v3.x/v8.0.4 are finally
 * pinned: the site-wide output-buffer generator-meta rewrite, and the
 * core/social-link path-relative URL shim (WORDPRESS-REFERENCE §1.1 /
 * gotcha #1 — core's render callback turns "/notes/feed/" into
 * "https:///notes/feed/"). No behavior change rides the naming: this
 * fixture pins in/out for BOTH behaviors plus the hook wiring.
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
require __DIR__ . '/../inc/frontend-filters.php';

$pass = 0; $fail = 0;
function ok( $cond, $msg ) { global $pass, $fail; if ( $cond ) { $pass++; echo "PASS: $msg\n"; } else { $fail++; echo "FAIL: $msg\n"; } }

// ── 1. Wiring: every closure is now a NAMED function on its hook ──
ok( in_array( 'sn_skip_link', $GLOBALS['__test_actions']['wp_body_open'] ?? array(), true ), 'wp_body_open → sn_skip_link (named)' );
ok( in_array( 'sn_spotify_embed_dark', $GLOBALS['__test_filters']['embed_oembed_html'] ?? array(), true ), 'embed_oembed_html → sn_spotify_embed_dark (named)' );
ok( in_array( 'sn_social_link_relative_url', $GLOBALS['__test_filters']['render_block_data'] ?? array(), true ), 'render_block_data → sn_social_link_relative_url (named)' );
ok( in_array( 'sn_generator_meta_buffer_start', $GLOBALS['__test_actions']['template_redirect'] ?? array(), true ), 'template_redirect → sn_generator_meta_buffer_start (named)' );
ok( in_array( '__return_empty_string', $GLOBALS['__test_filters']['the_generator'] ?? array(), true ), 'the_generator → __return_empty_string' );

// ── 2. Output-buffer rewrite: generator metas stripped, rest untouched ──
$html_in = "<head>\n<meta name=\"generator\" content=\"WordPress 6.9\">\n"
	. "<META name=\"generator\" content=\"Some Plugin 1.2\">\n"
	. "<meta name=\"viewport\" content=\"width=device-width\">\n</head>";
$html_out = sn_strip_generator_meta( $html_in );
ok( false === stripos( $html_out, 'name="generator"' ), 'IN: generator metas (any case) are stripped' );
ok( false !== strpos( $html_out, '<meta name="viewport" content="width=device-width">' ), 'IN: non-generator meta survives byte-identical' );
$clean = "<head><meta name=\"viewport\" content=\"width=device-width\"></head>";
ok( $clean === sn_strip_generator_meta( $clean ), 'OUT: generator-free markup passes through unchanged' );
// The template_redirect handler installs the callback on a fresh buffer.
$level = ob_get_level();
sn_generator_meta_buffer_start();
ok( ob_get_level() === $level + 1, 'buffer-start handler opens an output buffer' );
ob_end_clean();

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

// ── 4. Spotify oEmbed dark theme + square corners ──
$sp_in  = '<iframe src="https://open.spotify.com/embed/album/xyz" style="border-radius: 12px"></iframe>';
$sp_out = sn_spotify_embed_dark( $sp_in, 'https://open.spotify.com/album/xyz' );
ok( false !== strpos( $sp_out, '&theme=0' ), 'Spotify src gains theme=0 (dark)' );
ok( false !== strpos( $sp_out, 'border-radius: 0' ) && false === strpos( $sp_out, 'border-radius: 12px' ), 'Spotify inline border-radius squared off' );
$yt = '<iframe src="https://www.youtube.com/embed/xyz"></iframe>';
ok( $yt === sn_spotify_embed_dark( $yt, 'https://www.youtube.com/watch?v=xyz' ), 'non-Spotify embeds pass through unchanged' );

// ── 5. Skip link ──
ob_start();
sn_skip_link();
$link = ob_get_clean();
ok( false !== strpos( $link, 'class="sn-skip-link"' ) && false !== strpos( $link, 'href="#wp--skip-link--target"' ), 'skip link renders with brand class + skip target' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
