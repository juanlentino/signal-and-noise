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

echo "\nResult: $pass passed, $fail failed.\n";
exit( $fail > 0 ? 1 : 0 );
