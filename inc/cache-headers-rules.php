<?php
/**
 * Signal & Noise: who gets the cache headers. Pure rules, no request reads.
 *
 * The lifetimes and the feed list are in inc/cache-headers.php; the hooks
 * that build the context these rules judge are in inc/cache-headers-hooks.php.
 *
 * @package SignalNoise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Is this an anonymous read? PURE. A session, post-password or commenter
 * cookie makes the response personal, whatever the login check says.
 *
 * @param string   $method       Request method.
 * @param string[] $cookie_names Names of the cookies on the request.
 * @param bool     $logged_in    is_user_logged_in().
 * @return bool
 */
function sn_edge_cache_is_anonymous( $method, array $cookie_names, $logged_in ) {
	if ( $logged_in || ! in_array( strtoupper( (string) $method ), array( 'GET', 'HEAD' ), true ) ) {
		return false;
	}
	foreach ( $cookie_names as $name ) {
		if ( preg_match( '/^(wordpress_logged_in_|wp-postpass_|comment_author_)/', (string) $name ) ) {
			return false;
		}
	}
	return true;
}

/**
 * The Cache-Control value already set on this response, lowercased. PURE.
 *
 * @param string[] $headers headers_list().
 * @return string '' when none is set.
 */
function sn_edge_cache_existing( array $headers ) {
	$found = '';
	foreach ( $headers as $line ) {
		if ( 0 === stripos( (string) $line, 'cache-control:' ) ) {
			$found .= ' ' . strtolower( trim( substr( (string) $line, 14 ) ) );
		}
	}
	return trim( $found );
}

/**
 * Is this request URI one of the listed feeds? PURE over the list. The path
 * may come without its trailing slash; a query string must match exactly.
 *
 * @param string   $request_uri REQUEST_URI.
 * @param string[] $paths       sn_edge_cache_feed_paths().
 * @return bool
 */
function sn_edge_cache_is_listed_feed( $request_uri, array $paths ) {
	$parts = explode( '?', (string) $request_uri, 2 );
	$uri   = rtrim( $parts[0], '/' ) . '/' . ( isset( $parts[1] ) ? '?' . $parts[1] : '' );
	return in_array( $uri, $paths, true );
}

/**
 * Which header a front-end response gets: 'html', 'machine' (a feed),
 * 'refuse' (an explicit no-store) or ''.
 * PURE over the context sn_edge_cache_template_redirect() builds
 * (inc/cache-headers-hooks.php).
 *
 * A LISTED feed (sn_edge_cache_feed_paths()) replaces Breeze's `no-cache`
 * (Breeze sets it on every feed it declines to store) but never a no-store or
 * private; any other feed keeps what it has. HTML is sent only when
 * nothing has set a Cache-Control yet: nocache_headers() callers, and Breeze's
 * `must-revalidate, max-age=0` on a URL with an unknown query var, keep theirs.
 *
 * @param array<string,mixed> $ctx anonymous, status, existing, feed, and the refusals.
 * @return string
 */
function sn_edge_cache_kind_for( array $ctx ) {
	if ( empty( $ctx['anonymous'] ) || 200 !== (int) ( $ctx['status'] ?? 0 ) ) {
		return '';
	}
	$existing = (string) ( $ctx['existing'] ?? '' );
	if ( ! empty( $ctx['feed'] ) ) {
		if ( empty( $ctx['listed_feed'] ) ) {
			return ''; // a comment, tag or search feed, or an odd query form: Breeze's no-cache stays.
		}
		return ( false !== strpos( $existing, 'no-store' ) || false !== strpos( $existing, 'private' ) ) ? '' : 'machine';
	}
	foreach ( array( 'admin', 'preview', 'search', 'not_found', 'password', 'non_html' ) as $refusal ) {
		if ( ! empty( $ctx[ $refusal ] ) ) {
			return '';
		}
	}
	if ( '' !== $existing ) {
		return '';
	}
	// 15.2.1: a render made while the companion plugin is not loaded (the gap
	// in the middle of its own update) is missing routes and styles. It must
	// not be stored, and saying nothing would leave it to the edge's default.
	return empty( $ctx['companion_missing'] ) ? 'html' : 'refuse';
}

/**
 * Is a finished HTML body big enough to be a page? PURE. An empty render (a
 * 200 with nothing in it) has reached the edge three times, each after a full
 * purge; the smallest real page here is over 100 KB.
 *
 * @param int $bytes Body length.
 * @return bool
 */
function sn_edge_cache_body_ok( $bytes ) {
	return (int) $bytes >= SN_EDGE_BODY_FLOOR_BYTES;
}

/**
 * Just before the headers leave: is the response still the one the edge
 * header was written for? PURE. Cloudflare reads Cloudflare-CDN-Cache-Control
 * INSTEAD of Cache-Control, so a nocache_headers() call made later in the
 * template (or a late error status) would be ignored at the edge and a page
 * that said no-store would be stored. If either moved, the edge header goes.
 *
 * @param string $existing The Cache-Control now set, lowercased (sn_edge_cache_existing()).
 * @param int    $status   The response status now set.
 * @return bool
 */
function sn_edge_cache_still_ours( $existing, $status ) {
	return SN_EDGE_BROWSER_CACHE_CONTROL === (string) $existing && 200 === (int) $status;
}
