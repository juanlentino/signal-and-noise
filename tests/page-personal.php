<?php
/**
 * Standalone fixture tests for the /contact/personal page (theme v10.12.0).
 *
 * inc/page-personal-template.php registers a postless /contact/personal virtual
 * route (template_redirect short-circuit + template_include fallback + document
 * title) — the child of the /contact page, mirroring how /about/uses is a child
 * of /about. inc/page-personal-render.php emits a full HTML document whose main
 * content comes from the pure sn_personal_content_blocks() string (block markup
 * rendered through do_blocks). Like every postless route it MUST force HTTP 200
 * (WORDPRESS-REFERENCE gotcha #40). Mirrors tests/page-uses.php.
 *
 * The content contract is unit-tested here: the Personal page body carries
 * EXACTLY ONE hyperlink (LinkedIn), and the Casey Neistat credit footnote uses
 * the theme's smallest font-size preset + muted colour preset (no inline values).
 *
 * @since theme v10.12.0
 */

// SECURITY: CLI-only fixture.
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', '/' ); }
define( 'SN_PERSONAL_TEST', true );        // suppress template_redirect/title wiring on require
define( 'SN_PERSONAL_RENDER_TEST', true ); // suppress the render file's page output on require

ob_start();

$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }

// ── WP primitive stubs ────────────────────────────────────────────────
function add_action( $h, $cb, $p = 10, $a = 1 ) { return true; }
function add_filter( $h, $cb, $p = 10, $a = 1 ) { return true; }
$GLOBALS['__status'] = 0;
function status_header( $code ) { $GLOBALS['__status'] = (int) $code; }
function get_bloginfo( $k = '' ) { return 'name' === $k ? 'Signal & Noise' : ''; }
function get_theme_file_path( $p = '' ) { return __DIR__ . '/../' . $p; }
function get_theme_file_uri( $p = '' ) { return 'https://x.test/wp-content/themes/sn/' . $p; }
function sn_asset_ver( $p = '' ) { return '123'; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }

require __DIR__ . '/../inc/page-personal-template.php';

echo "Personal contact-routing page suite — theme v10.12.0\n\n";

// ── Route matcher (pure helper) — /contact/personal is a child of /contact ──
ok( function_exists( 'sn_personal_is_request' ), 'sn_personal_is_request() is defined' );
ok( sn_personal_is_request( '/contact/personal' ) === true, 'matches /contact/personal' );
ok( sn_personal_is_request( '/contact/personal/' ) === true, 'matches /contact/personal/ (trailing slash)' );
ok( sn_personal_is_request( 'contact/personal' ) === true, 'matches bare contact/personal (no leading slash)' );
ok( sn_personal_is_request( '/contact/personal?ref=footer' ) === true, 'matches /contact/personal with a query string' );
ok( sn_personal_is_request( '/contact' ) === false, 'rejects the parent /contact' );
ok( sn_personal_is_request( '/personal' ) === false, 'rejects bare /personal (it lives under /contact)' );
ok( sn_personal_is_request( '/contact/personalize' ) === false, 'rejects near-miss /contact/personalize' );
ok( sn_personal_is_request( '/contact/personal.bak' ) === false, 'rejects near-miss /contact/personal.bak' );
ok( sn_personal_is_request( '/contact/person' ) === false, 'rejects /contact/person' );
ok( sn_personal_is_request( '/about/uses' ) === false, 'rejects an unrelated sibling route' );
ok( sn_personal_is_request( '/' ) === false, 'rejects site root' );

// ── Document title ────────────────────────────────────────────────────
ok( function_exists( 'sn_personal_title' ), 'sn_personal_title() is defined' );
ok( sn_personal_title() === 'Personal — Signal & Noise', 'title is "Personal — <site>"' );

// ── Render-file: forced 200 + content contract ────────────────────────
require __DIR__ . '/../inc/page-personal-render.php';
ok( 200 === $GLOBALS['__status'], 'render forces HTTP 200 for the postless virtual route (gotcha #40)' );

ok( function_exists( 'sn_personal_content_blocks' ), 'sn_personal_content_blocks() is defined' );
$body = sn_personal_content_blocks();
ok( is_string( $body ) && '' !== $body, 'sn_personal_content_blocks() returns a non-empty string' );

// EXACTLY ONE hyperlink in the page content (LinkedIn) — the deliberate friction.
$anchor_count = substr_count( $body, '<a ' );
ok( 1 === $anchor_count, 'page content carries EXACTLY ONE anchor (got ' . $anchor_count . ')' );
ok( strpos( $body, 'https://www.linkedin.com/in/juanlentino/' ) !== false, 'the one link points to the LinkedIn profile' );
ok( strpos( $body, '>LinkedIn</a>' ) !== false, 'the linked text is the word "LinkedIn"' );

// Footnote uses theme presets (smallest font-size + muted colour) — NOT inline values.
ok( strpos( $body, 'has-small-font-size' ) !== false, 'footnote uses the small font-size preset (smallest type step)' );
ok( strpos( $body, 'has-rust-color' ) !== false, 'footnote uses the rust colour preset (muted)' );
ok( strpos( $body, "Casey Neistat" ) !== false, 'the Casey Neistat credit is present' );
ok( strpos( $body, 'He worked out the honest version of this first.' ) !== false, 'credit sentence is verbatim' );

// A spacer separates the body from the footnote.
ok( strpos( $body, 'wp-block-spacer' ) !== false, 'a spacer separates the body from the credit footnote' );

// Body copy fidelity — distinctive phrases from each provided paragraph.
ok( strpos( $body, 'the answer is no' ) !== false, 'paragraph 2 ("the answer is no") is present' );
ok( strpos( $body, "That doesn't change the answer." ) !== false, 'paragraph 3 is present' );
ok( strpos( $body, 'finishing an MBA in August 2026' ) !== false, 'paragraph 4 (MBA, Aug 2026) is present' );
ok( strpos( $body, "Yes doesn't fit in the week." ) !== false, 'paragraph 4 closes verbatim' );

// Masthead present + accessible (an H1 the page would otherwise lack).
ok( strpos( $body, '<h1' ) !== false, 'the page has an H1 (masthead)' );

// ── functions.php wires the module ────────────────────────────────────
$fn = file_get_contents( __DIR__ . '/../functions.php' );
ok( strpos( $fn, 'inc/page-personal-template.php' ) !== false, 'functions.php requires inc/page-personal-template.php' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
