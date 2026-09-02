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
// v12.13.1: the page is retired and the URL now 301s to the index. The PATH
// MATCH above is unchanged and stays the load-bearing assertion — this route
// still runs on template_redirect for every front-end request, so a loose match
// would hijack other URLs, and now it would REDIRECT them rather than merely
// mis-serve them. Every near-miss above must still be false.
ok( 'https://x.test/notes/' === sn_subscribe_redirect_target(), 'the retired URL sends a reader to the index, whose hero now carries both feed links' );
ok( sn_subscribe_redirect_target() !== 'https://x.test' . SN_SUBSCRIBE_PATH, 'and never to itself — that is the loop' );
ok( ! function_exists( 'sn_subscribe_render' ), 'the 241-word page renderer is gone, not merely unreachable' );

// The hero line it folded into: both feeds linked directly, email dropped.
$sn_hero = (string) file_get_contents( __DIR__ . '/../inc/page-notes-render.php' );
// Comment-stripped, and the vacuity guard says why: the hero's own comment
// NAMES the retired page to explain what it replaced. A raw scan reads that
// explanation as a live link and fails the fix it is describing — the third
// time this exact shape has bitten tonight, after a CSS scan and a wording one.
$sn_hero_markup = (string) preg_replace( '#/\*.*?\*/|//[^\n]*#s', '', $sn_hero );
ok( false !== strpos( $sn_hero, '/notes/subscribe/' ), 'VACUITY: the retired path IS named in the file (in a comment), so a comment-stripped scan is doing real work' );
ok( false === strpos( $sn_hero_markup, '/notes/subscribe/' ), 'nothing in the theme still LINKS the retired page' );
ok( false !== strpos( $sn_hero, 'nothing about you is collected' ), 'the privacy sentence survived the fold — it is the point, not a footnote' );

// A class with no rule renders unstyled, and a 20-word sentence in the
// subscribe line's uppercase 0.18em register would be a wall. Pin the rule.
$sn_css = (string) file_get_contents( __DIR__ . '/../assets/css/notes.css' );
ok( 1 === preg_match( '/\.sn-notes-subscribe-privacy\s*\{/', $sn_css ), 'the privacy line has a rule of its own — not an invented class' );
ok( 0 === preg_match( '/\.sn-notes-subscribe-privacy\s*\{[^}]*text-transform:\s*uppercase/s', $sn_css ), 'and it is NOT uppercase: that register is right for six words, unreadable at twenty' );

// --- v12.2.0: the page carries BOTH channels, and the terms it never restates ---
// The markup lives in a render function that emits a document and exits, so it
// is pinned at SOURCE level — the technique tests/notes-hero-structure.php uses
// for the same reason.
$src = file_get_contents( __DIR__ . '/../inc/feed-subscribe-page.php' );

// The JSON accessor is the REAL one, required across the file boundary: a stub
// here would pin this suite's own invention instead of the shipped contract.
ok( 'https://x.test/feed/json/' === sn_feed_json_pretty_url(), 'the real JSON accessor yields the pretty path' );
ok( 'https://x.test/notes/feed/' === sn_subscribe_feed_url(), 'the RSS accessor survived the page it was written for' );
ok( sn_feed_json_pretty_url() !== sn_subscribe_feed_url(), 'the two channels are different URLs' );

// THE READER MOVED, THE RULE DID NOT. This suite's sharpest assertion was that
// nothing hardcodes a feed path — two copies are two things to keep in step.
// The retired page was the reader; the /notes hero is the reader now, so the
// assertion follows it there rather than retiring with the page.
ok( false !== strpos( $sn_hero, 'sn_feed_json_pretty_url()' ), 'the hero reads the JSON URL from the accessor' );
ok( false !== strpos( $sn_hero, 'sn_subscribe_feed_url()' ), 'and the RSS URL from its accessor' );
ok( 0 === preg_match( '#href="/feed/json/"#', $sn_hero_markup ), 'no literal JSON path in the markup (that is how the two drift)' );
ok( 0 === preg_match( '#href="/notes/feed/"#', $sn_hero_markup ), 'no literal RSS path either' );
// ── everything below this line described the retired PAGE ──────────────────
// Its title filter, its container-CSS parity with /notes/, its header/footer
// template-part pre-render, wp_head/wp_body_open/body_class ordering: all
// assertions about a 241-word document that no longer exists. Deleted with it
// rather than left asserting over a redirect — a suite that keeps testing a
// removed surface is how a file grows a section nobody can explain.
//
// What SURVIVED is above, and it is the part that was never about the page:
// the path match (a loose one would now redirect other URLs, not merely
// mis-serve them) and the no-hardcoded-feed-path rule, which followed its
// reader to the /notes hero.

echo "\n$pass passed, $fail failed\n";
exit( $fail > 0 ? 1 : 0 );
