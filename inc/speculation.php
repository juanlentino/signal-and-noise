<?php
/**
 * Signal & Noise — speculative loading tuning (WP 6.8+ Speculation Rules API).
 *
 * Core defaults to a conservative 'prefetch' when speculative loading is
 * enabled at all (pretty permalinks, logged-out — see
 * wp_get_speculation_rules_configuration() in
 * wp-includes/speculative-loading.php). That is raised here to
 * 'prerender'/'moderate' so a note hovered on the index is rendered before
 * the click, not just prefetched.
 *
 * `null` must be passed through unchanged: it means speculative loading is
 * disabled (logged-in user, no pretty permalinks, or another filter callback
 * already opted the site out), and this module has no business turning it
 * back on.
 *
 * Paginated note listings are excluded: /notes/page/N is a query over the
 * same page, not a distinct page, and is rarely the one the visitor lands
 * on. Search is NOT listed here: under pretty permalinks core already
 * excludes every URL with a query string (`/*\?(.+)`), and in URLPattern
 * syntax an unescaped `?` is the optional modifier, not a literal — a
 * `/notes/?s=*` entry would be both redundant and wrong. Paths mirror core's
 * own exclude list shape (plain, unprefixed patterns — core prefixes them
 * itself via WP_URL_Pattern_Prefixer before merging with its base
 * exclusions).
 *
 * @package SignalNoise
 * @since 13.110.0
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

/**
 * wp_speculation_rules_href_exclude_paths — search and paginated notes are
 * queries, not pages.
 *
 * @param string[] $paths Additional path patterns to disable speculative loading for.
 * @return string[]
 */
function sn_speculation_exclude( $paths ) {
	$paths[] = '/notes/page/*';

	return $paths;
}
add_filter( 'wp_speculation_rules_href_exclude_paths', 'sn_speculation_exclude' );
