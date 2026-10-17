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
 * 'refuse' (an explicit no-store), 'nocache' (kept out of the edge) or ''.
 * PURE over the context sn_edge_cache_template_redirect() builds
 * (inc/cache-headers-hooks.php).
 *
 * A LISTED feed (sn_edge_cache_feed_paths()) replaces a `no-cache` but never
 * a no-store or private. HTML is sent only when nothing has set a
 * Cache-Control yet: nocache_headers() callers keep theirs.
 *
 * 'nocache' (2026-10-17): an anonymous 200 the theme will not cache (an
 * unlisted feed, a search, a password form) that left with NO Cache-Control
 * was stored by the edge's zone rule, with no tag to purge it. The theme used
 * to count on Breeze's `no-cache` there; Breeze has been inactive since
 * 2026-10-13. Only when nothing has set a header: anything already there stays.
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
			// A comment, tag or search feed, or an odd query form: no tag can purge it.
			return '' === $existing ? 'nocache' : '';
		}
		return ( false !== strpos( $existing, 'no-store' ) || false !== strpos( $existing, 'private' ) ) ? '' : 'machine';
	}
	if ( ! empty( $ctx['admin'] ) ) {
		return '';
	}
	foreach ( array( 'preview', 'search', 'not_found', 'password', 'non_html' ) as $refusal ) {
		if ( ! empty( $ctx[ $refusal ] ) ) {
			return '' === $existing ? 'nocache' : '';
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

/**
 * The Cache-Tag value for a response. PURE. Every cached response keeps the
 * site tag; a single note adds its own; a list page, a feed or a machine
 * file adds the list tag.
 *
 * @param string $kind    'html' or 'machine'.
 * @param int    $note_id The note this page is, 0 when it is not one.
 * @param bool   $lists   Whether the page lists notes.
 * @return string
 */
function sn_edge_cache_tags( $kind, $note_id, $lists ) {
	$tags = array( SN_EDGE_CACHE_TAG );
	if ( (int) $note_id > 0 ) {
		$tags[] = sn_edge_cache_note_tag( (int) $note_id );
	}
	if ( 'machine' === $kind || $lists ) {
		$tags[] = SN_EDGE_CACHE_LIST_TAG;
	}
	return implode( ',', $tags );
}

/**
 * Is this path a page that lists notes? PURE over the path (no query
 * string): home, /notes/ and its pages, /notes/tags/, /index/, /provenance/,
 * and the tag and category archives.
 *
 * @param string $uri Request URI.
 * @return bool
 */
function sn_edge_cache_is_list_path( $uri ) {
	$path = '/' . trim( (string) strtok( (string) $uri, '?' ), '/' );
	return in_array( $path, array( '/', '/notes', '/notes/tags', '/index', '/provenance' ), true )
		|| 1 === preg_match( '#^/(notes/page/\d+|tag/[^/]+(/page/\d+)?|category/[^/]+(/page/\d+)?)$#', $path );
}
