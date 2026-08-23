<?php
/**
 * Standalone fixture tests for the /notes/subscribe/ route (v11.9.4).
 *
 * The load-bearing assertion is the PATH MATCH. This route runs on
 * template_redirect for every front-end request, so a loose match does not
 * merely fail — it HIJACKS other URLs and serves them the subscribe page.
 * Every near-miss below must be false.
 *
 * @since theme v11.9.4
 */
if ( PHP_SAPI !== 'cli' && ! defined( 'WP_CLI' ) ) { http_response_code( 404 ); exit; }
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', '/' ); }
define( 'SN_SUBSCRIBE_TEST', true );
$pass = 0; $fail = 0;
function ok( $c, $m ) { global $pass, $fail; if ( $c ) { $pass++; echo "PASS: $m\n"; } else { $fail++; echo "FAIL: $m\n"; } }

function add_action( $h, $cb, $p = 10, $a = 1 ) { return true; }
function trailingslashit( $s ) { return rtrim( (string) $s, '/\\' ) . '/'; }
function wp_parse_url( $u, $c = -1 ) { return parse_url( (string) $u, $c ); }
function wp_unslash( $v ) { return is_string( $v ) ? stripslashes( $v ) : $v; }
function home_url( $p = '' ) { return 'https://x.test' . $p; }
function get_bloginfo( $k = 'name' ) { return 'Signal & Noise'; }

define( 'SN_FEED_JSON_TEST', true ); // suppress its wiring; we want the accessors
require __DIR__ . '/../inc/feed-json.php'; // the REAL producer, not a stub
$_SERVER['REQUEST_URI'] = '/';
require __DIR__ . '/../inc/feed-subscribe-page.php';

echo "/notes/subscribe/ route (v11.9.4)\n\n";

// MATCHES
foreach ( array(
	'/notes/subscribe/'                 => 'canonical path',
	'/notes/subscribe'                  => 'no trailing slash',
	'/notes/subscribe/?utm_source=x'    => 'query string ignored',
	'https://x.test/notes/subscribe/'   => 'absolute URL form',
) as $uri => $label ) {
	ok( true === sn_subscribe_is_request( $uri ), "matches: $label" );
}

// MUST NOT MATCH — this hook runs on EVERY front-end request.
foreach ( array(
	'/notes/'                    => 'the notes index itself',
	'/notes/feed/'               => 'the feed (would break subscription)',
	'/notes/subscribe/extra/'    => 'a deeper path',
	'/notes/subscribed/'         => 'a longer slug sharing the prefix',
	'/notes/start-here/'         => 'another note under /notes/',
	'/subscribe/'                => 'same slug at the root',
	'/'                          => 'the home page',
	'/notes/subscribe-to-this/'  => 'a note whose slug begins with the word',
) as $uri => $label ) {
	ok( false === sn_subscribe_is_request( $uri ), "does NOT match: $label" );
}

// The taught URL is the real feed, not the page itself.
ok( 'https://x.test/notes/feed/' === sn_subscribe_feed_url(), 'the page teaches the REAL feed URL' );
ok( sn_subscribe_feed_url() !== 'https://x.test' . SN_SUBSCRIBE_PATH, 'feed URL is not the subscribe page' );

// --- v12.2.0: the page carries BOTH channels, and the terms it never restates ---
// The markup lives in a render function that emits a document and exits, so it
// is pinned at SOURCE level — the technique tests/notes-hero-structure.php uses
// for the same reason.
$src = file_get_contents( __DIR__ . '/../inc/feed-subscribe-page.php' );

// The JSON accessor is the REAL one, required across the file boundary: a stub
// here would pin this suite's own invention instead of the shipped contract.
ok( 'https://x.test/feed/json/' === sn_feed_json_pretty_url(), 'the real JSON accessor yields the pretty path' );
ok( sn_feed_json_pretty_url() !== sn_subscribe_feed_url(), 'the two channels are different URLs' );

ok( false !== strpos( $src, 'sn_feed_json_pretty_url()' ), 'page reads the JSON URL from the accessor' );
ok( false === strpos( $src, '/feed/json' ), 'page hardcodes NO JSON feed path (that is how the two drift)' );
ok( false !== strpos( $src, 'sn_subscribe_feed_url()' ), 'page still reads the RSS URL from its accessor' );
ok( false !== strpos( $src, 'JSON Feed' ), 'page names the JSON channel in words, not just a URL' );

// The terms. Subscribing IS redistribution, so the one page that hands someone
// the words links the policy.
ok( false !== strpos( $src, '/tdm-policy/' ), 'page links the terms at /tdm-policy/' );

// THE PIN THAT MATTERS MOST. sn-rights-signals-worker owns /tdm-policy/ and
// sn-provenance-worker OTS-anchors it hourly; theme pages are NOT anchored.
// Restating a term here creates an unanchored copy of anchored text, free to
// drift from the canonical with nothing detecting it. The other pins fail
// loudly on their own; this one guards a slow forking.
foreach ( array( 'CC BY', 'Creative Commons', 'ShareAlike', 'NonCommercial', 'NoDerivatives', 'licen' ) as $term ) {
	ok( false === stripos( $src, $term ), "page does NOT restate the terms: '$term'" );
}

// --- v12.2.1: the two defects the page shipped with in v11.9.4 ---

// DEFECT 1 — the title. This route 200s, but WP's query never resolved to a
// post, so wp_get_document_title() fell through to "Page not found" on a live,
// indexable page. Same fix the other synthetic routes use (page-index-template,
// page-notes-template): short-circuit pre_get_document_title. NOT a wp-admin
// Page — sn_subscribe_render() exits on template_redirect, so a real Page at
// this path would never render, and its title would live outside the repo.
$_SERVER['REQUEST_URI'] = '/notes/subscribe/';
ok( 'Subscribe — Signal & Noise' === sn_subscribe_document_title( 'Page not found — Signal & Noise' ), 'title is replaced on the subscribe route' );
$_SERVER['REQUEST_URI'] = '/notes/';
ok( 'untouched' === sn_subscribe_document_title( 'untouched' ), 'title passes through on every other route' );
ok( false !== strpos( $src, "add_filter( 'pre_get_document_title'" ), 'the title filter is registered' );
ok( false !== strpos( $src, ', 999 )' ), 'registered at 999, matching the other synthetic routes' );

// DEFECT 2 — no container. .sn-notes-page carries the width, gutter and
// footer clearance, and it is declared in page-notes-render.php's INLINE style,
// which does not load on this route. The page rendered flush against the
// viewport edge with no design at all.
ok( 1 === preg_match( '/\.sn-notes-page\.sn-subscribe\s*\{/', $src ), 'the route declares its own container rule' );

// And the two must not drift. Both rules are extracted and compared
// declaration-for-declaration, so a change to the /notes/ container that is not
// mirrored here fails rather than silently re-orphaning this page.
function sn_css_rule( $css, $selector ) {
	$q = preg_quote( $selector, '/' );
	if ( ! preg_match( '/' . $q . '\s*\{([^}]*)\}/s', $css, $m ) ) { return null; }
	$out = array();
	foreach ( explode( ';', $m[1] ) as $d ) {
		$d = trim( preg_replace( '/\s+/', ' ', $d ) );
		if ( '' !== $d ) { $out[] = $d; }
	}
	sort( $out );
	return $out;
}
// v12.4.1: the canonical /notes container rule now lives in its own stylesheet.
$notes_css = file_get_contents( __DIR__ . '/../assets/css/notes.css' );
$canonical = sn_css_rule( $notes_css, '.sn-notes-page' );
$local     = sn_css_rule( $src, '.sn-notes-page.sn-subscribe' );
ok( is_array( $canonical ) && $canonical, 'the canonical /notes/ container rule was found (guard: the regex still matches)' );
ok( $canonical === $local, 'the subscribe container matches the /notes/ container declaration-for-declaration' );

// --- v12.2.2: the page must build its own document ---
// This is a BLOCK theme: there is no header.php, so get_header() fell through
// to core's theme-compat fallback (wp-includes/theme-compat/header.php), which
// renders a generic site-title-and-tagline header. That is the giant flush-left
// wordmark, the missing nav, and the missing block-layout CSS — not a styling
// bug but a whole chrome that was never ours.
ok( false === strpos( $src, 'get_header()' ), 'does NOT call get_header() (theme-compat fallback in a block theme)' );
ok( false === strpos( $src, 'get_footer()' ), 'does NOT call get_footer()' );
ok( false !== strpos( $src, '"slug":"header","area":"header"' ), 'renders the real header template part' );
ok( false !== strpos( $src, '"slug":"footer","area":"footer"' ), 'renders the real footer template part' );
ok( false !== strpos( $src, 'wp_head()' ), 'emits wp_head()' );
ok( false !== strpos( $src, 'wp_body_open()' ), 'emits wp_body_open()' );
ok( false !== strpos( $src, 'wp_footer()' ), 'emits wp_footer()' );
ok( false !== strpos( $src, 'body_class(' ), 'emits body_class()' );

// THE LOAD-BEARING PIN: the two-pass. Both template parts must be rendered
// BEFORE wp_head(), or their block-layout CSS is queued after the stylesheet
// has already printed and lands nowhere — the header nav packs left instead of
// right. page-notes-render.php carries the same constraint in a 20-line comment.
$p_header_part = strpos( $src, '"slug":"header","area":"header"' );
$p_footer_part = strpos( $src, '"slug":"footer","area":"footer"' );
$p_wp_head     = strpos( $src, 'wp_head()' );
ok( $p_header_part < $p_wp_head, 'header template part is pre-rendered BEFORE wp_head()' );
ok( $p_footer_part < $p_wp_head, 'footer template part is pre-rendered BEFORE wp_head()' );

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
