<?php
/**
 * Standalone fixture tests for the /accessibility statement page (v10.21.0).
 *
 * inc/page-accessibility-template.php registers a postless /accessibility
 * virtual route (template_redirect short-circuit + template_include fallback
 * + document title); inc/page-accessibility-render.php emits a full HTML
 * document from the static sn_a11y_sections() content model (no data file —
 * the statement is render code, owner copy-reviewed at the release gate).
 * Like every postless route it MUST force HTTP 200 (WORDPRESS-REFERENCE
 * gotcha #40). Mirrors tests/page-now.php.
 *
 * @since theme v10.21.0
 */

// SECURITY: CLI-only fixture.
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', '/' ); }
define( 'SN_A11Y_TEST', true );        // suppress template_redirect/wp_head/enqueue wiring on require
define( 'SN_A11Y_RENDER_TEST', true ); // suppress the render file's page output on require

ob_start();

$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }

// ── WP primitive stubs ────────────────────────────────────────────────
function add_action( $h, $cb, $p = 10, $a = 1 ) { return true; }
function add_filter( $h, $cb, $p = 10, $a = 1 ) { return true; }
function apply_filters( $tag, $value ) { return $value; }
$GLOBALS['__status'] = 0;
function status_header( $code ) { $GLOBALS['__status'] = (int) $code; }
function get_bloginfo( $k = '' ) { return 'name' === $k ? 'Signal & Noise' : ''; }
function get_theme_file_uri( $p = '' ) { return 'https://x.test/wp-content/themes/sn/' . $p; }
function get_theme_file_path( $p = '' ) { return __DIR__ . '/../' . $p; }
function sn_asset_ver( $p = '' ) { return '123'; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_url( $u ) { $u = (string) $u; $u = str_replace( array( '"', "'", '<', '>', ' ' ), '', $u ); return str_replace( '&', '&amp;', $u ); }
function home_url( $p = '' ) { return 'https://x.test' . $p; }

require __DIR__ . '/../inc/page-accessibility-template.php';

echo "Accessibility page suite — theme v10.21.0\n\n";

// ── Route matcher (pure helper) ──────────────────────────────────────
ok( function_exists( 'sn_a11y_is_request' ), 'sn_a11y_is_request() is defined' );
ok( sn_a11y_is_request( '/accessibility' ) === true, 'matches /accessibility' );
ok( sn_a11y_is_request( '/accessibility/' ) === true, 'matches /accessibility/ (trailing slash)' );
ok( sn_a11y_is_request( 'accessibility' ) === true, 'matches bare accessibility (no leading slash)' );
ok( sn_a11y_is_request( '/accessibility?src=footer' ) === true, 'matches with a query string' );
ok( sn_a11y_is_request( '/accessible' ) === false, 'rejects /accessible' );
ok( sn_a11y_is_request( '/about/accessibility' ) === false, 'rejects nested path' );
ok( sn_a11y_is_request( '/' ) === false, 'rejects site root' );
ok( sn_a11y_is_request( '/accessibility.html' ) === false, 'rejects near-miss /accessibility.html' );

// ── Document title ────────────────────────────────────────────────────
ok( function_exists( 'sn_a11y_title' ), 'sn_a11y_title() is defined' );
ok( sn_a11y_title() === 'Accessibility — Signal & Noise', 'title is "Accessibility — <site>"' );

// ── Render-file: forced 200 + content model + section renderer ───────
require __DIR__ . '/../inc/page-accessibility-render.php';
ok( 200 === $GLOBALS['__status'], 'render forces HTTP 200 for the postless virtual route (gotcha #40)' );

ok( function_exists( 'sn_a11y_sections' ), 'sn_a11y_sections() is defined' );
$sections = sn_a11y_sections();
ok( is_array( $sections ) && count( $sections ) >= 5, 'statement has at least 5 sections' );
$shape_ok = true;
$all_text = '';
foreach ( $sections as $s ) {
	if ( ! is_array( $s ) || '' === (string) ( $s['label'] ?? '' ) || empty( $s['paragraphs'] ) || ! is_array( $s['paragraphs'] ) ) { $shape_ok = false; break; }
	$all_text .= $s['label'] . ' ' . implode( ' ', $s['paragraphs'] ) . ' ';
}
ok( $shape_ok, 'every section has a label + non-empty paragraphs' );

// Content contract: the statement names its target, honors-reduced-motion,
// forced-colors support, and the feedback channel. Claims map to shipped work.
ok( false !== strpos( $all_text, 'WCAG 2.1 AA' ), 'statement names the WCAG 2.1 AA target' );
ok( false !== stripos( $all_text, 'prefers-reduced-motion' ), 'statement mentions reduced-motion support' );
ok( false !== stripos( $all_text, 'forced-colors' ) || false !== stripos( $all_text, 'High Contrast' ), 'statement mentions forced-colors / high-contrast support' );
ok( false !== strpos( $all_text, '/contact' ), 'statement points feedback at /contact' );
ok( 1 === preg_match( '/\d{4}-\d{2}-\d{2}/', $all_text ), 'statement carries a date' );

ok( function_exists( 'sn_a11y_render_section' ), 'sn_a11y_render_section() is defined' );
$html = sn_a11y_render_section( 'Hostile <label>', array( 'Para & one', 'Para <b>two</b>' ), 3 );
ok( strpos( $html, 'sn-a11y-section' ) !== false, 'section carries the .sn-a11y-section class' );
ok( strpos( $html, 'id="sn-a11y-h-3"' ) !== false && strpos( $html, 'aria-labelledby="sn-a11y-h-3"' ) !== false, 'section heading is the aria anchor' );
ok( strpos( $html, '<label>' ) === false && strpos( $html, '<b>two</b>' ) === false, 'label + paragraphs escaped at the sink' );
ok( strpos( $html, 'Para &amp; one' ) !== false, 'ampersand escaped' );

// The Feedback section renders /contact as a real link (built in code, not
// from the data string).
ok( function_exists( 'sn_a11y_render_feedback_link' ), 'sn_a11y_render_feedback_link() is defined' );
$link = sn_a11y_render_feedback_link();
ok( strpos( $link, '<a href="https://x.test/contact/"' ) !== false, 'feedback link targets home_url(/contact/)' );

// ── CSS contract ──────────────────────────────────────────────────────
$css = file_get_contents( __DIR__ . '/../assets/css/accessibility.css' );
ok( is_string( $css ) && '' !== $css, 'accessibility.css exists + non-empty' );
ok( strpos( $css, '.sn-a11y-page' ) !== false, 'accessibility.css is scoped under .sn-a11y-page' );
ok( strpos( $css, '--wp--preset--color--' ) !== false, 'accessibility.css uses theme preset color tokens' );

// ── functions.php wires the module ────────────────────────────────────
$fn = file_get_contents( __DIR__ . '/../functions.php' );
ok( strpos( $fn, 'inc/page-accessibility-template.php' ) !== false, 'functions.php requires inc/page-accessibility-template.php' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
