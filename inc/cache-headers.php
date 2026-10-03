<?php
/**
 * Signal & Noise: cache lifetimes for the edge, and who gets them.
 *
 * Until now public HTML sent no Cache-Control at all, so the edge kept it for
 * Cloudflare's default two hours and had nothing that let it answer from a stale
 * copy: when the origin returned 503, readers got the 503. The machine files
 * (/llms.txt, /llms-full.txt, /.well-known/agents.json, /opensearch.xml) left
 * with the nocache headers WordPress commits for a postless path, so every
 * read went to PHP (about 0.4 s), and the feeds left with Breeze's `no-cache`.
 *
 * TWO HEADERS, because two caches sit in front of WordPress and only one of
 * them should keep the page:
 *
 *   Cache-Control: public, max-age=0
 *     What browsers and Varnish read. No s-maxage, ever: Varnish passes HTML
 *     through today and its only reliable purge is the manual one, so a shared
 *     lifetime here would have it holding pages no save clears. s-maxage also
 *     switches stale serving OFF at Cloudflare (it implies proxy-revalidate).
 *
 *   Cloudflare-CDN-Cache-Control: max-age=N, stale-while-revalidate=M, stale-if-error=K
 *     What Cloudflare alone reads, in place of Cache-Control, and does not
 *     pass on. The edge keeps the copy, refreshes it in the background and
 *     serves it when the origin answers 5xx.
 *
 * This file is the lifetimes and the feed list. The pure rules (who gets a
 * header) are in inc/cache-headers-rules.php; the hooks that read the request
 * and send the headers are in inc/cache-headers-hooks.php.
 *
 * @package SignalNoise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 15.2.0: the one tag every cached response carries (pages, the listed feeds,
 * the machine files). The plugin purges it on a save with a single call, in
 * place of a URL list that had to name every address a change could touch.
 * One tag, not one per post: every page embeds the site-wide notes index, so
 * a save changes them all. Cloudflare strips the header before the visitor.
 */
const SN_EDGE_CACHE_TAG = 'sn-render';

/**
 * 15.2.1: an HTML body under this is not a page and is never stored. The guard
 * theme 13.3.1 had lived in an output buffer that #400 removed; the empty
 * /provenance/ of 2026-10-03 was cached for 21 minutes without it.
 */
const SN_EDGE_BODY_FLOOR_BYTES = 4096;

/** The Cache-Control every cached kind sends: browsers revalidate, Varnish stores nothing. */
const SN_EDGE_BROWSER_CACHE_CONTROL = 'public, max-age=0';

// HTML at the edge. Two hours because that is what the edge does today: the
// live Cache Rule uses the origin's header when present and Cloudflare's
// default TTL when not, pages sent no header, and the documented default for
// a 200 is 120 minutes. The header must not change that. A day of
// background refresh covers a quiet page between purges; seven days of
// stale-if-error outlasts any origin outage this site has had.
const SN_EDGE_HTML_MAX_AGE = 7200;
const SN_EDGE_HTML_SWR     = 86400;
const SN_EDGE_HTML_SIE     = 604800;

// Machine files and feeds at the edge. Five minutes: a reader polls these for
// what is new, and a save purges them anyway (the plugin's per-post list).
const SN_EDGE_MACHINE_MAX_AGE = 300;
const SN_EDGE_MACHINE_SWR     = 3600;
const SN_EDGE_MACHINE_SIE     = 86400;

/**
 * The Cloudflare-CDN-Cache-Control value for a kind of response, '' to send
 * neither header. Filter `sn_edge_cache_lifetimes` changes the seconds; an
 * empty return or a zero max_age switches that kind off.
 *
 * @param string $kind 'html' or 'machine'.
 * @return string
 */
function sn_edge_cdn_cache_control( $kind ) {
	$t = 'machine' === $kind
		? array( 'max_age' => SN_EDGE_MACHINE_MAX_AGE, 'swr' => SN_EDGE_MACHINE_SWR, 'sie' => SN_EDGE_MACHINE_SIE )
		: array( 'max_age' => SN_EDGE_HTML_MAX_AGE, 'swr' => SN_EDGE_HTML_SWR, 'sie' => SN_EDGE_HTML_SIE );
	$t = apply_filters( 'sn_edge_cache_lifetimes', $t, $kind );
	if ( ! is_array( $t ) || empty( $t['max_age'] ) || (int) $t['max_age'] < 1 ) {
		return '';
	}
	return sprintf( 'max-age=%d, stale-while-revalidate=%d, stale-if-error=%d', (int) $t['max_age'], max( 0, (int) ( $t['swr'] ?? 0 ) ), max( 0, (int) ( $t['sie'] ?? 0 ) ) );
}

/**
 * The feed addresses the edge may hold: the posts feed in each core format
 * plus JSON, at both bases, and the query form the JSON feed advertises as
 * its own URL. A CLOSED LIST on purpose: the plugin purges exactly these on a
 * save, and a feed address outside it (comments, a tag, ?feed=rdf) cannot be
 * purged by name, so it is never cached.
 *
 * @return string[] Root-relative, as REQUEST_URI spells them.
 */
function sn_edge_cache_feed_paths() {
	$paths = array( '/?feed=json' );
	foreach ( array( '/feed/', '/notes/feed/' ) as $base ) {
		foreach ( array( '', 'rss2/', 'rss/', 'rdf/', 'atom/', 'json/' ) as $format ) {
			$paths[] = $base . $format;
		}
	}
	return array_values( array_filter( (array) apply_filters( 'sn_edge_cache_feed_paths', $paths ), 'is_string' ) );
}
