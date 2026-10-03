<?php
/**
 * Signal & Noise: Cache-Control for the shared caches (Cloudflare, Varnish).
 *
 * Until now public HTML sent no Cache-Control at all, so the edge kept it for
 * the Cache Rule's fallback day and had nothing that let it answer from a stale
 * copy: when the origin returned 503, readers got the 503. The machine files
 * (/llms.txt, /llms-full.txt, /.well-known/agents.json, /opensearch.xml) left
 * with the nocache headers WordPress commits for a postless path, so every
 * read went to PHP (about 0.4 s), and the feeds left with Breeze's `no-cache`.
 *
 * Two lifetimes, both `public, max-age=0`: a browser always revalidates, a
 * shared cache keeps the copy, refreshes it in the background
 * (stale-while-revalidate) and serves it when the origin errors
 * (stale-if-error). Only anonymous GET/HEAD; anything that already refuses
 * caching keeps its own header.
 *
 * Breeze replays only its own header allow-list on a page-cache hit, so a hit
 * leaves without this header and the edge falls back to the Cache Rule's day.
 * The renders the edge stores after a purge are fresh ones, which carry it.
 *
 * @package SignalNoise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// HTML. s-maxage is one day because that is what the edge does today (the
// Cache Rule's "otherwise 1 day"): the header must not shorten it. A day of
// background refresh covers a quiet page between purges; seven days of
// stale-if-error outlasts any origin outage this site has had.
const SN_EDGE_HTML_S_MAXAGE = 86400;
const SN_EDGE_HTML_SWR      = 86400;
const SN_EDGE_HTML_SIE      = 604800;

// Machine files and feeds. Five minutes: a reader polls these for what is new,
// and a save purges them anyway (the plugin's per-post list).
const SN_EDGE_MACHINE_S_MAXAGE = 300;
const SN_EDGE_MACHINE_SWR      = 3600;
const SN_EDGE_MACHINE_SIE      = 86400;

/**
 * The Cache-Control value for a kind of response, '' to send nothing.
 * Filter `sn_edge_cache_lifetimes` changes the seconds; an empty return or a
 * zero s_maxage switches the header off for that kind.
 *
 * @param string $kind 'html' or 'machine'.
 * @return string
 */
function sn_edge_cache_control( $kind ) {
	$t = 'machine' === $kind
		? array( 's_maxage' => SN_EDGE_MACHINE_S_MAXAGE, 'swr' => SN_EDGE_MACHINE_SWR, 'sie' => SN_EDGE_MACHINE_SIE )
		: array( 's_maxage' => SN_EDGE_HTML_S_MAXAGE, 'swr' => SN_EDGE_HTML_SWR, 'sie' => SN_EDGE_HTML_SIE );
	$t = apply_filters( 'sn_edge_cache_lifetimes', $t, $kind );
	if ( ! is_array( $t ) || empty( $t['s_maxage'] ) || (int) $t['s_maxage'] < 1 ) {
		return '';
	}
	return sprintf( 'public, max-age=0, s-maxage=%d, stale-while-revalidate=%d, stale-if-error=%d', (int) $t['s_maxage'], max( 0, (int) ( $t['swr'] ?? 0 ) ), max( 0, (int) ( $t['sie'] ?? 0 ) ) );
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
 * Which header a front-end response gets: 'html', 'machine' (a feed) or ''.
 * PURE over the context sn_edge_cache_template_redirect() builds.
 *
 * A feed replaces Breeze's `no-cache` (Breeze sets it on every feed it
 * declines to store) but never a no-store or private. HTML is sent only when
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
		return ( false !== strpos( $existing, 'no-store' ) || false !== strpos( $existing, 'private' ) ) ? '' : 'machine';
	}
	foreach ( array( 'admin', 'preview', 'search', 'not_found', 'password', 'non_html' ) as $refusal ) {
		if ( ! empty( $ctx[ $refusal ] ) ) {
			return '';
		}
	}
	return '' === $existing ? 'html' : '';
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
 * Send the header for a kind, replacing what is there. Expires and Pragma go
 * with it: WordPress commits both for a postless path, and a 1984 Expires
 * beside a public Cache-Control is a contradiction to an HTTP/1.0 cache.
 *
 * @param string $kind 'html' or 'machine'.
 * @return void
 */
function sn_edge_cache_emit( $kind ) {
	$value = sn_edge_cache_control( $kind );
	if ( '' === $value || headers_sent() ) {
		return;
	}
	header_remove( 'Expires' );
	header_remove( 'Pragma' );
	header( 'Cache-Control: ' . $value );
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
 * here. A nocache_headers() call later in the template replaces this header,
 * which is the order wanted.
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
