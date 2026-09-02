<?php
/**
 * Signal & Noise — /notes/subscribe/ is gone; its URL still answers.
 *
 * v12.13.1 folded that page into one line in the /notes hero. It was 241 words
 * whose deliverable was a single URL, and whose second half re-listed the eight
 * most recent notes using the index's own row classes — the index, twice, under
 * a page about a feed address. The hero line now links /notes/feed/ and
 * /feed/json/ directly, so a reader reaches the feed in one click rather than
 * two, and the one sentence worth keeping (what the feed does NOT collect)
 * moved up with it.
 *
 * WHY A REDIRECT AND NOT A DELETION. The route answered 200 and carried no
 * noindex, so it is reachable and may well be indexed even though it never
 * appeared in the sitemap. Deleting the module outright would turn a live URL
 * into a 404 and strand whatever links to it from outside this repo. 301, so
 * the address is retired properly and hands its signal to the index.
 *
 * The path match is UNCHANGED from the page it replaces — exact path, query
 * string ignored, trailing slash optional — because the URL being retired is
 * the one the page answered, not a new guess at it.
 *
 * @package SignalNoise
 * @since 11.9.4 (retired 12.13.1)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The retired path, trailing-slashed. */
const SN_SUBSCRIBE_PATH = '/notes/subscribe/';

/**
 * Is this request the retired subscribe URL? Path-exact, query string ignored,
 * trailing slash optional. PURE, so tests/feed-subscribe-page.php can drive it
 * without a request.
 *
 * @param string $uri Request URI.
 * @return bool
 */
function sn_subscribe_is_request( $uri ) {
	$path = (string) wp_parse_url( (string) $uri, PHP_URL_PATH );
	return trailingslashit( $path ) === SN_SUBSCRIBE_PATH;
}

/**
 * The Notes RSS address, in ONE place.
 *
 * Kept when the page was retired, because the old suite's sharpest assertion
 * was that nothing may hardcode a feed path: two copies of a URL are two things
 * to keep in step, and the /notes hero is now a second reader of this fact. The
 * JSON twin lives in inc/feed-json.php as sn_feed_json_pretty_url().
 *
 * @return string
 */
function sn_subscribe_feed_url() {
	return home_url( '/notes/feed/' );
}

/**
 * Where the retired URL sends a reader: the index, whose hero now carries the
 * feed links the page existed to teach.
 *
 * @return string
 */
function sn_subscribe_redirect_target() {
	return home_url( '/notes/' );
}

/**
 * Priority 0, ahead of the /notes renderer's own template_redirect hook: that
 * one exits, so anything later never runs.
 */
function sn_subscribe_redirect() {
	if ( is_admin() || is_feed() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return;
	}
	if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
		return;
	}
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- path-compared, never echoed.
	if ( ! sn_subscribe_is_request( wp_unslash( $_SERVER['REQUEST_URI'] ) ) ) {
		return;
	}
	wp_safe_redirect( sn_subscribe_redirect_target(), 301 );
	exit;
}

if ( ! defined( 'SN_SUBSCRIBE_TEST' ) || ! SN_SUBSCRIBE_TEST ) {
	add_action( 'template_redirect', 'sn_subscribe_redirect', 0 );
}
