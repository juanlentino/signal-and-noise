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
function home_url( $p = '' ) { return 'https://x.test' . $p; }

define( 'SN_FEED_JSON_TEST', true ); // suppress its wiring; we want the accessors
require __DIR__ . '/../inc/feed-json.php'; // the REAL producer, not a stub
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

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
