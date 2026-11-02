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
 * No href-exclude list is needed: pagination and search are both
 * query-string URLs, and under pretty permalinks core already excludes
 * every URL with a query string (`/*\?(.+)`, see
 * wp-includes/speculative-loading.php). `/notes` paginates with `?paged=N`
 * — a query string, already covered — and in URLPattern syntax an
 * unescaped `?` is the optional modifier, not a literal, so a hand-rolled
 * `/notes/page/*` or `/notes/?s=*` entry would be either redundant or wrong.
 *
 * One page adds a rule of its own: /resume prerenders its other lane
 * (sn_speculation_resume_lanes() below), so a lane switch is a full page that
 * is already there when it is clicked.
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

/**
 * wp_load_speculation_rules: on the English /resume (the only page with two
 * lanes, the condition inc/cms-page-styles.php loads resume-switch.css under),
 * prerender the lane not on screen, from the switch or a lane link in a hunk.
 *
 * A rule of its own, beside core's 'main': core's query-string exclusion
 * (`/*\?(.+)`) sits inside main's own `where`, so it does not reach this rule
 * and /resume/?lane=operations is prerendered. The current lane's link carries
 * aria-current (the plugin's render filter), so the page never prerenders
 * itself. Only fires when speculative loading is on at all (logged out,
 * pretty permalinks, not disabled by the plugin's setting).
 *
 * @param WP_Speculation_Rules $rules The rules core will print.
 * @return void
 */
function sn_speculation_resume_lanes( $rules ) {
	if ( ! is_page( 'resume' ) ) {
		return;
	}
	$rules->add_rule(
		'prerender',
		'sn-resume-lanes',
		array(
			'source'    => 'document',
			// Core's own opt-outs, mirrored from its main rule: a link a plugin
			// marks rel=nofollow, .no-prerender or .no-prefetch is never prerendered.
			'where'     => array(
				'and' => array(
					array( 'selector_matches' => '.sn-resume-switch a[data-lane]:not([aria-current]), .sn-resume-hunk a[data-lane]' ),
					array( 'not' => array( 'selector_matches' => 'a[rel~="nofollow"]' ) ),
					array( 'not' => array( 'selector_matches' => '.no-prerender, .no-prerender a' ) ),
					array( 'not' => array( 'selector_matches' => '.no-prefetch, .no-prefetch a' ) ),
				),
			),
			'eagerness' => 'moderate',
		)
	);
}
add_action( 'wp_load_speculation_rules', 'sn_speculation_resume_lanes' );
