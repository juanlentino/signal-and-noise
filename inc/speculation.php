<?php
/**
 * Signal & Noise — speculative loading tuning (WP 6.8+ Speculation Rules API).
 *
 * When speculative loading is enabled at all (pretty permalinks, logged
 * out), core sets mode and eagerness to 'auto'
 * (wp_get_speculation_rules_configuration() in
 * wp-includes/speculative-loading.php). 'auto' resolves to prefetch and
 * conservative, unless the host sets WP_SPECULATIVE_LOADING_DEFAULT_MODE or
 * WP_SPECULATIVE_LOADING_DEFAULT_EAGERNESS in wp-config. This filter sets
 * 'prerender'/'moderate' explicitly, which core says takes precedence over
 * those host defaults, so a note hovered on the index is rendered before the
 * click, not just prefetched. The constants are a host knob outside this repo.
 *
 * `null` must be passed through unchanged: it means speculative loading is
 * disabled (logged-in user, no pretty permalinks, or another filter callback
 * already opted the site out), and this module has no business turning it
 * back on.
 *
 * No href-exclude list is needed: pagination and search are both
 * query-string URLs, and under pretty permalinks core already excludes
 * every URL with a query string (`/*\?(.+)`, see
 * wp-includes/speculative-loading.php). `/notes` paginates with `?paged=N`
 * — a query string, already covered — and in URLPattern syntax an
 * unescaped `?` is the optional modifier, not a literal, so a hand-rolled
 * `/notes/page/*` or `/notes/?s=*` entry would be either redundant or wrong.
 *
 * @package SignalNoise
 * @since 13.1.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * wp_speculation_rules_configuration — prefetch/conservative → prerender/moderate.
 *
 * @param array<string, string>|null $config Associative array with 'mode' and
 *                                            'eagerness' keys, or null if
 *                                            speculative loading is disabled.
 * @return array<string, string>|null
 */
function sn_speculation_config( $config ) {
	if ( ! is_array( $config ) ) {
		return $config;
	}

	$config['mode']      = 'prerender';
	$config['eagerness'] = 'moderate';

	return $config;
}
add_filter( 'wp_speculation_rules_configuration', 'sn_speculation_config' );
