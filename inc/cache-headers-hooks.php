<?php
/**
 * Signal & Noise: the hooks that send the cache headers.
 *
 * The lifetimes and the rules are in inc/cache-headers.php; this file reads
 * the request and writes the two headers.
 *
 * A Breeze page-cache hit is answered from advanced-cache.php before
 * WordPress loads, so it leaves without them. Breeze's
 * `breeze_custom_headers_allow` list cannot carry them either: it names
 * headers whose values Breeze snapshots once, from a HEAD of the home page
 * through the edge with a `?no-cache=` query var, and Cloudflare does not pass
 * Cloudflare-CDN-Cache-Control downstream. Stale serving applies to origin
 * misses only; a purge empties Breeze too, so the copy the edge stores after
 * one is a fresh render.
 *
 * @package SignalNoise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Is the current request anonymous? Reads the request; the rule is above.
 *
 * @return bool
 */
function sn_edge_cache_request_is_anonymous() {
	$method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : 'GET';
	return sn_edge_cache_is_anonymous( $method, array_keys( $_COOKIE ), is_user_logged_in() );
}

/**
 * Send both headers and the cache tag for a kind, replacing what is there. Expires and Pragma
 * go with them: WordPress commits both for a postless path, and a 1984
 * Expires beside a public Cache-Control is a contradiction to an HTTP/1.0 cache.
 *
 * @param string $kind 'html' or 'machine'.
 * @return void
 */
function sn_edge_cache_emit( $kind ) {
	$cdn = sn_edge_cdn_cache_control( $kind );
	if ( '' === $cdn || headers_sent() ) {
		return;
	}
	header_remove( 'Expires' );
	header_remove( 'Pragma' );
	header( 'Cache-Control: ' . SN_EDGE_BROWSER_CACHE_CONTROL );
	header( 'Cloudflare-CDN-Cache-Control: ' . $cdn );
	header( 'Cache-Tag: ' . SN_EDGE_CACHE_TAG );
	header_register_callback( 'sn_edge_cache_recheck' );
}

/**
 * Runs as PHP is about to send the headers (the rule: sn_edge_cache_still_ours()).
 *
 * @return void
 */
function sn_edge_cache_recheck() {
	$code = http_response_code();
	if ( ! sn_edge_cache_still_ours( sn_edge_cache_existing( headers_list() ), is_int( $code ) ? $code : 200 ) ) {
		header_remove( 'Cloudflare-CDN-Cache-Control' );
		header_remove( 'Cache-Tag' );
	}
}

/**
 * For the virtual machine files, which answer at template_redirect priority 0
 * with WordPress's 404 nocache headers already committed: replace them, for an
 * anonymous read only.
 *
 * @return void
 */
function sn_edge_cache_send_machine() {
	if ( sn_edge_cache_request_is_anonymous() ) {
		sn_edge_cache_emit( 'machine' );
	}
}

/**
 * HTML pages and feeds. Priority 100: after every redirect handler has left
 * and after the virtual routes have answered; REST, AJAX and cron never get
 * here. A nocache_headers() call later in the template replaces the
 * Cache-Control, and sn_edge_cache_recheck() then takes the edge header back.
 *
 * @return void
 */
function sn_edge_cache_template_redirect() {
	$code = http_response_code();
	$kind = sn_edge_cache_kind_for(
		array(
			'anonymous' => sn_edge_cache_request_is_anonymous(),
			'status'    => is_404() ? 404 : ( is_int( $code ) ? $code : 200 ),
			'existing'  => sn_edge_cache_existing( headers_list() ),
			'feed'      => is_feed(),
			'listed_feed' => sn_edge_cache_is_listed_feed( isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '', sn_edge_cache_feed_paths() ),
			'admin'     => is_admin(),
			'preview'   => is_preview() || is_customize_preview(),
			'search'    => is_search(),
			'not_found' => is_404(),
			'password'  => is_singular() && post_password_required(),
			'non_html'  => is_robots() || is_favicon() || is_trackback(),
		)
	);
	if ( '' !== $kind ) {
		sn_edge_cache_emit( $kind );
	}
}
if ( ! defined( 'SN_CACHE_HEADERS_TEST' ) ) {
	add_action( 'template_redirect', 'sn_edge_cache_template_redirect', 100 );
}
