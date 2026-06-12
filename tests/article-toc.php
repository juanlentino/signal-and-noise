<?php
/**
 * Standalone fixture tests for the in-article TOC builder.
 *
 * Stubs the WP primitives inc/article-toc.php touches (sanitize_title,
 * wp_strip_all_tags, escaping, is_singular/in_the_loop/is_main_query) so the
 * pure helpers run without a WordPress load. Mirrors tests/post-share.php.
 * The JS layer (assets/js/article-toc.js) is progressive enhancement, verified
 * by manual UAT — these tests cover the server-rendered markup contract only.
 *
 * @since theme (TOC feature)
 */

// SECURITY: Prevent web access. Test fixture, not a runtime module.
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) {
	http_response_code( 404 );
	exit;
}

// Controllable stub state for the filter guard.
$GLOBALS['__is_singular_post'] = false;
$GLOBALS['__in_the_loop']      = false;
$GLOBALS['__is_main_query']    = false;

if ( ! function_exists( 'is_singular' ) ) {
	function is_singular( $types = '' ) { return (bool) $GLOBALS['__is_singular_post']; }
}
if ( ! function_exists( 'in_the_loop' ) ) {
	function in_the_loop() { return (bool) $GLOBALS['__in_the_loop']; }
}
if ( ! function_exists( 'is_main_query' ) ) {
	function is_main_query() { return (bool) $GLOBALS['__is_main_query']; }
}
if ( ! function_exists( 'sanitize_title' ) ) {
	// Faithful enough: lowercase, non-alphanumerics → single hyphen, trimmed.
	function sanitize_title( $t ) {
		$t = strtolower( (string) $t );
		$t = preg_replace( '/[^a-z0-9]+/', '-', $t );
		return trim( (string) $t, '-' );
	}
}
if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $s ) {
		return trim( (string) preg_replace( '/<[^>]*>/', '', (string) $s ) );
	}
}
if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
}
if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
}
if ( ! function_exists( 'esc_attr__' ) ) {
	function esc_attr__( $s, $d = 'default' ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
}
if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $s, $d = 'default' ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
}

define( 'SN_ARTICLE_TOC_TEST', true );
require __DIR__ . '/../inc/article-toc.php';

$pass = 0; $fail = 0;
function ok( $cond, $label ) {
	global $pass, $fail;
	if ( $cond ) { $pass++; echo "PASS: $label\n"; }
	else { $fail++; echo "FAIL: $label\n"; }
}

// Helpers to build rendered-content fixtures.
function h2( $text, $attrs = ' class="wp-block-heading"' ) { return "<h2$attrs>$text</h2>"; }
$p = '<p>Body text.</p>';

// ── Below threshold (2 H2s) → unchanged ───────────────────────────────
$two = h2( 'One' ) . $p . h2( 'Two' ) . $p;
ok( sn_article_toc_apply( $two ) === $two, '<3 H2s: content returned unchanged' );

// ── 3 H2s → TOC prepended, ids injected, links match ──────────────────
$three = h2( 'Intro' ) . $p . h2( 'Method' ) . $p . h2( 'Result' ) . $p;
$out = sn_article_toc_apply( $three );
ok( strpos( $out, '<nav class="sn-article-toc"' ) === 0, 'TOC nav is prepended at the very start' );
ok( strpos( $out, 'aria-label="Table of contents"' ) !== false, 'nav has the aria-label' );
ok( strpos( $out, '<ol class="sn-article-toc__list">' ) !== false, 'ordered list present' );
ok( substr_count( $out, '<li>' ) === 3, 'three TOC items' );
ok( strpos( $out, '<a href="#intro">Intro</a>' ) !== false, 'links to #intro' );
ok( strpos( $out, '<a href="#method">Method</a>' ) !== false, 'links to #method' );
ok( strpos( $out, '<h2 class="wp-block-heading" id="intro">Intro</h2>' ) !== false, 'id injected into the Intro heading' );
ok( strpos( $out, '<h2 class="wp-block-heading" id="result">Result</h2>' ) !== false, 'id injected into the Result heading' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
