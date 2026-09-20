<?php
/**
 * Tests: the three block view scripts load through block.json `viewScript`,
 * not through a page gate (#384).
 *
 * Before this pin blocks/note-share, blocks/note-reply and blocks/theme-toggle
 * declared no script. Each file was enqueued by its own `wp_enqueue_scripts`
 * callback that repeated the renderer's predicate: `is_singular( 'post' )`
 * twice (inc/assets-frontend.php, inc/note-reply.php), "every page" once
 * (inc/dark-mode.php). Now each manifest names a handle that is REGISTERED at
 * `init`, and core enqueues it when the block renders: WP 7.1
 * wp-includes/blocks.php register_block_script_handle() returns a bare handle
 * verbatim (the shape blocks/sidenote already uses for editorScript), and
 * wp-includes/class-wp-block.php render() calls wp_enqueue_script() on every
 * view_script_handles entry. The page gate is core's job.
 *
 * THE FIXTURE PIN. The registration arguments asserted in Group 2 are the
 * enqueue arguments origin/main passed, captured 2026-09-20 by running that
 * code in this same harness (src, deps, footer/strategy args; the version is
 * the same sn_asset_ver() expression). A handle registered with the same
 * arguments and enqueued by core prints the same <script> tag, so the served
 * page is byte-identical; only the place the dependency is declared moved.
 *
 * Group 3 is the pin that goes red against the unfixed code: fired as a
 * single note, no `wp_enqueue_scripts` callback enqueues any of the three
 * handles any more. Two controls keep that reading honest: a sibling
 * singular-gated handle IS enqueued on the same request (the harness sees
 * enqueues), and the /contact enqueue of the shared alias handle, which the
 * issue keeps (that page is not a block), still fires.
 *
 * Group 4 pins the template shape the reach reading rests on: core enqueues
 * a view script whenever the block renders, before and regardless of what
 * the render callback returns, so the two closing-row scripts print on every
 * singular templates/single.html serves. That is notes and, with no
 * attachment.html, attachment pages, where the callbacks return '' and the
 * scripts no-op. The old gates did not load them there; the CHANGELOG says
 * so, and this group keeps that sentence tied to the files.
 *
 * @since theme v13.4.1
 */

if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', '/' ); }

$theme_root = realpath( __DIR__ . '/..' );

$pass = 0; $fail = 0;
function ok( $cond, $label ) {
	global $pass, $fail;
	if ( $cond ) { $pass++; echo "PASS: $label\n"; }
	else { $fail++; echo "FAIL: $label\n"; }
}

// ── WP stubs ──────────────────────────────────────────────────────────────
$GLOBALS['__actions'] = array();  // hook => list of callbacks
$GLOBALS['__reg_js']  = array();  // wp_register_script: handle => args
$GLOBALS['__enq_js']  = array();  // wp_enqueue_script:  handle => args
$GLOBALS['__singular'] = false;   // is_singular( 'post' )
$GLOBALS['__page']     = '';      // is_page( slug )

function add_action( $hook, $cb, $priority = 10, $accepted_args = 1 ) { $GLOBALS['__actions'][ $hook ][] = $cb; }
function add_filter( $hook, $cb, $priority = 10, $accepted_args = 1 ) {}
function add_shortcode( $tag, $cb ) {}
function wp_register_script( $handle, $src = '', $deps = array(), $ver = false, $args = array() ) {
	$GLOBALS['__reg_js'][ $handle ] = array( 'src' => $src, 'deps' => $deps, 'ver' => $ver, 'args' => $args );
}
function wp_enqueue_script( $handle, $src = '', $deps = array(), $ver = false, $args = array() ) {
	$GLOBALS['__enq_js'][ $handle ] = array( 'src' => $src, 'deps' => $deps, 'ver' => $ver, 'args' => $args );
}
function wp_enqueue_style() {}
function wp_register_style() {}
function wp_dequeue_style() {}
function wp_dequeue_script() {}
function get_theme_file_uri( $path = '' ) { return 'https://example.test/wp-content/themes/signal-and-noise/' . ltrim( $path, '/' ); }
function get_theme_file_path( $path = '' ) { return realpath( __DIR__ . '/..' ) . '/' . ltrim( $path, '/' ); }
function wp_get_theme() { return new class { public function get( $key ) { return '13.4.1'; } }; }
function is_page( $page = '' ) { return '' !== $GLOBALS['__page'] && $page === $GLOBALS['__page']; }
function is_singular( $types = '' ) { return $GLOBALS['__singular'] && ( 'post' === $types || ( is_array( $types ) && in_array( 'post', $types, true ) ) ); }
function esc_js( $s ) { return $s; }
function esc_attr( $s ) { return $s; }
function esc_html( $s ) { return $s; }
function esc_url( $s ) { return $s; }
function esc_html__( $s, $d = '' ) { return $s; }
function esc_attr__( $s, $d = '' ) { return $s; }
function __( $s, $d = '' ) { return $s; }
function apply_filters( $h, $v ) { return $v; }

require $theme_root . '/inc/assets-frontend.php';
require $theme_root . '/inc/contact-email.php';
require $theme_root . '/inc/note-reply.php';
require $theme_root . '/inc/dark-mode.php';

function fire( $hook ) {
	foreach ( $GLOBALS['__actions'][ $hook ] ?? array() as $cb ) { call_user_func( $cb ); }
}

// handle => [ block slug, script path, registration args ]. The args are the
// fixture: what origin/main's wp_enqueue_script() call passed for each.
$expected = array(
	'sn-note-share'       => array( 'note-share',   'assets/js/note-share.js',       true ),
	'sn-contact-aliases'  => array( 'note-reply',   'assets/js/contact-aliases.js',  array( 'in_footer' => true, 'strategy' => 'defer' ) ),
	'sn-dark-mode-toggle' => array( 'theme-toggle', 'assets/js/dark-mode-toggle.js', array( 'in_footer' => true, 'strategy' => 'defer' ) ),
);

echo "Group 1: the manifests declare the script\n";
foreach ( $expected as $handle => list( $slug ) ) {
	$json = json_decode( (string) file_get_contents( "$theme_root/blocks/$slug/block.json" ), true );
	ok( is_array( $json ) && ( $json['viewScript'] ?? null ) === $handle, "$slug/block.json viewScript is the handle $handle" );
	ok( is_string( $json['viewScript'] ?? null ) && ! str_starts_with( $json['viewScript'], 'file:' ), "$slug viewScript is a registered handle, not a file: path (no .asset.php sidecar in a no-build theme; the sidenote editorScript shape)" );
	ok( ! isset( $json['viewScriptModule'] ), "$slug names viewScript, not viewScriptModule (the file is a classic IIFE, not an ES module)" );
}

echo "\nGroup 2: each handle is registered at init with origin/main's enqueue arguments (the fixture)\n";
fire( 'init' );
foreach ( $expected as $handle => list( $slug, $path, $args ) ) {
	$reg = $GLOBALS['__reg_js'][ $handle ] ?? null;
	ok( is_array( $reg ), "$handle is registered after init (core's wp_enqueue_script() at block render needs it)" );
	ok( ( $reg['src'] ?? '' ) === get_theme_file_uri( $path ), "$handle src is $path" );
	ok( file_exists( "$theme_root/$path" ), "$path exists on disk" );
	ok( array() === ( $reg['deps'] ?? null ), "$handle has no dependencies" );
	ok( ( $reg['ver'] ?? null ) === sn_asset_ver( $path ), "$handle ver is sn_asset_ver( $path ), the same cache-bust token as before" );
	ok( ( $reg['args'] ?? null ) === $args, "$handle footer/strategy args match origin/main's enqueue: " . json_encode( $args ) );
}
ok( array() === $GLOBALS['__enq_js'], 'init registers, it enqueues nothing (control: registration is not an enqueue in disguise)' );

echo "\nGroup 3: no page gate enqueues them any more; core does, when the block renders\n";
$GLOBALS['__singular'] = true; $GLOBALS['__page'] = ''; $GLOBALS['__enq_js'] = array();
fire( 'wp_enqueue_scripts' );
foreach ( $expected as $handle => list( $slug ) ) {
	ok( ! isset( $GLOBALS['__enq_js'][ $handle ] ), "on a single note no wp_enqueue_scripts callback enqueues $handle (the $slug block does)" );
}
ok( isset( $GLOBALS['__enq_js']['sn-article-toc'] ), 'control: a sibling singular-gated handle (sn-article-toc) IS enqueued on the same request, so the absence above is a reading, not a blind harness' );

$GLOBALS['__singular'] = false; $GLOBALS['__page'] = 'contact'; $GLOBALS['__enq_js'] = array();
fire( 'wp_enqueue_scripts' );
ok( isset( $GLOBALS['__enq_js']['sn-contact-aliases'] ), 'control: the /contact enqueue of sn-contact-aliases stays (that page is not a block; the shared handle means the two paths can never double-load)' );
ok( ! isset( $GLOBALS['__enq_js']['sn-note-share'] ) && ! isset( $GLOBALS['__enq_js']['sn-dark-mode-toggle'] ), 'on /contact neither of the other two handles is enqueued by a gate' );

$GLOBALS['__singular'] = false; $GLOBALS['__page'] = ''; $GLOBALS['__enq_js'] = array();
fire( 'wp_enqueue_scripts' );
ok( ! isset( $GLOBALS['__enq_js']['sn-dark-mode-toggle'] ), 'the toggle has no unconditional enqueue left: on a plain page no callback enqueues sn-dark-mode-toggle (the header and footer parts carry the block, so every page still gets it, from the manifest)' );

echo "\nGroup 4: the template shape behind the reach reading (single.html serves attachments too)\n";
$closing = (string) file_get_contents( "$theme_root/parts/post-closing.html" );
ok( str_contains( $closing, '<!-- wp:signal-noise/note-share /-->' ) && str_contains( $closing, '<!-- wp:signal-noise/note-reply /-->' ), 'parts/post-closing.html carries the note-share and note-reply blocks' );
$includes = array();
foreach ( glob( "$theme_root/templates/*.html" ) as $tpl ) {
	if ( str_contains( (string) file_get_contents( $tpl ), '"slug":"post-closing"' ) ) { $includes[] = basename( $tpl ); }
}
ok( array( 'single.html' ) === $includes, 'templates/single.html is the only template that includes post-closing (got: ' . implode( ', ', $includes ) . ')' );
ok( ! file_exists( "$theme_root/templates/attachment.html" ) && ! file_exists( "$theme_root/templates/single-attachment.html" ), 'no attachment template: attachment singulars fall to single.html, so its closing-row scripts print there too (stated in the CHANGELOG; the scripts no-op without their row)' );
foreach ( array( 'assets/js/note-share.js', 'assets/js/contact-aliases.js' ) as $js ) {
	ok( str_contains( (string) file_get_contents( "$theme_root/$js" ), 'querySelectorAll' ), "$js finds its rows with querySelectorAll and so no-ops on a page without them" );
}

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
