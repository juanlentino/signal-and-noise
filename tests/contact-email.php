<?php
/**
 * Standalone fixture tests for [sn_email] scraper-resistant contact aliases (v10.16.0).
 *
 * The whole point of the feature is that the contiguous user@domain string
 * never appears in the markup, so the assertions are largely LEAK GUARDS:
 * the generated HTML must not contain "user@domain", any "@", "mailto", or the
 * contiguous domain. The address is split (user/domain) + base64-encoded into
 * data-* attributes with an "[at]/[dot]" fallback; assets/js/contact-aliases.js
 * assembles the clean form client-side as plain text (no anchor).
 *
 * Mirrors tests/print-styles.php (is_page-gated enqueue) + the shortcode shape
 * in tests/related-notes.php.
 *
 * @since theme v10.16.0
 */

// SECURITY: CLI-only fixture.
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', '/' ); }

// ── stubs ─────────────────────────────────────────────────────────────
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function shortcode_atts( $defaults, $atts, $shortcode = '' ) {
	$atts = (array) $atts;
	$out  = array();
	foreach ( $defaults as $k => $v ) {
		$out[ $k ] = array_key_exists( $k, $atts ) ? $atts[ $k ] : $v;
	}
	return $out;
}
$GLOBALS['__do_shortcode_calls'] = 0;
function do_shortcode( $content ) { $GLOBALS['__do_shortcode_calls']++; return '[RESOLVED]' . $content; }
function add_shortcode( $tag, $cb ) { return true; }
function add_filter( $h, $cb, $p = 10, $a = 1 ) { return true; }
function add_action( $h, $cb, $p = 10, $a = 1 ) { return true; }
function sn_asset_ver( $p ) { return '123'; }
function get_theme_file_uri( $p = '' ) { return 'https://example.test/wp-content/themes/signal-and-noise/' . ltrim( $p, '/' ); }
$GLOBALS['__page_slug']        = '';
$GLOBALS['__enqueued_scripts'] = array();
function is_page( $page = '' ) {
	if ( '' === $page ) { return '' !== $GLOBALS['__page_slug']; }
	return (string) $page === $GLOBALS['__page_slug'];
}
function wp_enqueue_script( $handle, $src = '', $deps = array(), $ver = false, $args = false ) {
	$GLOBALS['__enqueued_scripts'][ $handle ] = array( 'src' => $src, 'deps' => $deps, 'ver' => $ver, 'args' => $args );
}

define( 'SN_CONTACT_EMAIL_TEST', true ); // skip add_shortcode/add_filter/add_action wiring on require
require __DIR__ . '/../inc/contact-email.php';

$pass = 0; $fail = 0;
function ok( $cond, $label ) { global $pass, $fail; if ( $cond ) { $pass++; echo "PASS: $label\n"; } else { $fail++; echo "FAIL: $label\n"; } }

echo "Contact-email [sn_email] suite — theme v10.16.0\n\n";

// ── sn_email_markup(): the core generator + LEAK GUARDS ───────────────
ok( function_exists( 'sn_email_markup' ), 'sn_email_markup() is defined' );
$m = sn_email_markup( 'research', 'juanlentino.com' );

ok( strpos( $m, 'class="sn-email"' ) !== false, 'markup carries the .sn-email hook class' );
ok( strpos( $m, 'data-eu="' . base64_encode( 'research' ) . '"' ) !== false, 'user is base64 in data-eu' );
ok( strpos( $m, 'data-ed="' . base64_encode( 'juanlentino.com' ) . '"' ) !== false, 'domain is base64 in data-ed' );
ok( strpos( $m, 'research [at] juanlentino [dot] com' ) !== false, 'visible fallback is the [at]/[dot] form' );

// THE leak guards — none of these may appear anywhere in the markup.
ok( strpos( $m, 'research@juanlentino.com' ) === false, 'LEAK GUARD: no contiguous user@domain' );
ok( strpos( $m, '@' ) === false, 'LEAK GUARD: no @ anywhere (assembled client-side only)' );
ok( strpos( $m, 'juanlentino.com' ) === false, 'LEAK GUARD: no contiguous domain (fallback uses [dot])' );
ok( stripos( $m, 'mailto' ) === false, 'LEAK GUARD: no mailto' );
ok( strpos( $m, '<a ' ) === false, 'LEAK GUARD: not an anchor' );

// Multi-label domain reads naturally (last-dot split).
$m2 = sn_email_markup( 'x', 'sub.example.co.uk' );
ok( strpos( $m2, 'x [at] sub.example.co [dot] uk' ) !== false, 'multi-label domain splits on the LAST dot' );
ok( strpos( $m2, 'sub.example.co.uk' ) === false, 'multi-label leak guard: no contiguous domain' );

// Malformed → empty (no broken/empty element leaks).
ok( sn_email_markup( '', 'juanlentino.com' ) === '', 'empty user → empty string' );
ok( sn_email_markup( 'research', '' ) === '', 'empty domain → empty string' );

// ── sn_email_shortcode(): atts + domain default ───────────────────────
ok( function_exists( 'sn_email_shortcode' ), 'sn_email_shortcode() is defined' );
$s = sn_email_shortcode( array( 'user' => 'press' ) );
ok( strpos( $s, 'data-eu="' . base64_encode( 'press' ) . '"' ) !== false, 'shortcode passes user through' );
ok( strpos( $s, 'data-ed="' . base64_encode( 'juanlentino.com' ) . '"' ) !== false, 'shortcode defaults domain to juanlentino.com' );
ok( strpos( $s, 'press@juanlentino.com' ) === false, 'shortcode LEAK GUARD: no contiguous address' );
$s2 = sn_email_shortcode( array( 'user' => 'a', 'domain' => 'b.org' ) );
ok( strpos( $s2, 'data-ed="' . base64_encode( 'b.org' ) . '"' ) !== false, 'shortcode honors an explicit domain' );

// ── render_block bridge: resolves [sn_email] inside block content ─────
ok( function_exists( 'sn_email_render_block_bridge' ), 'sn_email_render_block_bridge() is defined' );
$GLOBALS['__do_shortcode_calls'] = 0;
$out = sn_email_render_block_bridge( '<p>email [sn_email user="research"] with the team</p>', array() );
ok( $GLOBALS['__do_shortcode_calls'] === 1, 'bridge runs do_shortcode when the token is present' );
$GLOBALS['__do_shortcode_calls'] = 0;
$pass_through = sn_email_render_block_bridge( '<p>no token here</p>', array() );
ok( $GLOBALS['__do_shortcode_calls'] === 0, 'bridge is a no-op (strpos-guarded) when the token is absent' );
ok( $pass_through === '<p>no token here</p>', 'bridge returns untouched content when no token' );

// ── enqueue gating (mirrors sn_enqueue_discography) ───────────────────
ok( function_exists( 'sn_enqueue_contact_aliases' ), 'sn_enqueue_contact_aliases() is defined' );
$GLOBALS['__enqueued_scripts'] = array(); $GLOBALS['__page_slug'] = 'contact';
sn_enqueue_contact_aliases();
ok( isset( $GLOBALS['__enqueued_scripts']['sn-contact-aliases'] ), 'sn-contact-aliases enqueued on /contact' );
$enq = $GLOBALS['__enqueued_scripts']['sn-contact-aliases'] ?? array();
ok( strpos( (string) ( $enq['src'] ?? '' ), 'assets/js/contact-aliases.js' ) !== false, 'enqueue src points at assets/js/contact-aliases.js' );
ok( is_array( $enq['args'] ?? null ) && ! empty( $enq['args']['in_footer'] ) && ( $enq['args']['strategy'] ?? '' ) === 'defer', 'enqueued in footer + deferred' );
ok( ( $enq['ver'] ?? null ) !== false, 'enqueue carries a cache-bust version' );
$GLOBALS['__enqueued_scripts'] = array(); $GLOBALS['__page_slug'] = 'music';
sn_enqueue_contact_aliases();
ok( ! isset( $GLOBALS['__enqueued_scripts']['sn-contact-aliases'] ), 'NOT enqueued off the /contact page' );

// ── JS contract: client-side assembly into a CLICKABLE mailto link ────
// The scraper invariant lives in the SOURCE (the markup + template guards
// above/below). The JS builds the @-joined address AND the mailto: link only
// at runtime, so a non-JS harvester over the served HTML still gets nothing.
$js = (string) @file_get_contents( realpath( __DIR__ . '/..' ) . '/assets/js/contact-aliases.js' );
ok( '' !== $js, 'contact-aliases.js is readable' );
ok( strpos( $js, '.sn-email' ) !== false, 'JS targets .sn-email' );
ok( strpos( $js, 'atob' ) !== false, 'JS base64-decodes the parts' );
ok( strpos( $js, 'DOMContentLoaded' ) !== false, 'JS assembles on DOMContentLoaded' );
ok( strpos( $js, "createElement('a')" ) !== false, 'JS builds a clickable anchor at runtime' );
ok( strpos( $js, "'mailto:'" ) !== false, 'JS sets a mailto: href (runtime only)' );
ok( strpos( $js, '.href' ) !== false, 'JS assigns the href client-side' );
ok( strpos( $js, 'appendChild' ) !== false, 'JS injects the link into the .sn-email span' );

// ── TEMPLATE contract: no mailto / no contiguous alias for the five ──
// music@ is the remote-music route added when /services and /contact were
// reconciled (in-studio at Panacea BA vs remote-with-me); it is a first-class
// alias, so it must satisfy the same leak guards as the original four.
$tpl = (string) @file_get_contents( realpath( __DIR__ . '/..' ) . '/templates/page-contact.html' );
ok( '' !== $tpl, 'page-contact.html is readable' );
foreach ( array( 'research', 'press', 'speaking', 'role', 'music' ) as $u ) {
	ok( strpos( $tpl, $u . '@juanlentino.com' ) === false, "TEMPLATE LEAK GUARD: no $u@juanlentino.com in source" );
	ok( strpos( $tpl, '[sn_email user="' . $u . '"]' ) !== false, "template uses [sn_email user=\"$u\"]" );
}
ok( strpos( $tpl, 'mailto:research' ) === false && strpos( $tpl, 'mailto:press' ) === false && strpos( $tpl, 'mailto:speaking' ) === false && strpos( $tpl, 'mailto:role' ) === false && strpos( $tpl, 'mailto:music' ) === false, 'TEMPLATE LEAK GUARD: no mailto on the five aliases' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
