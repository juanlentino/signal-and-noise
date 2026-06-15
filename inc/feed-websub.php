<?php
/**
 * Signal & Noise — WebSub (PubSubHubbub) feed advertisement (D4).
 *
 * Advertises a WebSub hub in the RSS2 + Atom feeds so feed readers can subscribe
 * for push instead of polling: an <atom:link rel="hub"> in RSS2 (WP's rss2 feed
 * declares the atom: namespace) and a bare <link rel="hub"> in Atom. WP core
 * already emits the rel="self" topic link in both feeds; this adds the missing
 * rel="hub". The companion plugin (signal-and-noise-tools v6.17.0+) does the
 * other half — pinging the hub on publish.
 *
 * CONTRACT. The hub is read from the `sn_websub_hub` filter with the public
 * default `https://pubsubhubbub.appspot.com/`. That SAME filter tag + identical
 * default literal is used by the plugin's ping, so one add_filter('sn_websub_hub')
 * keeps the advertised hub and the pinged hub in sync. They are deliberately NOT
 * a shared constant: theme and plugin are separate codebases loaded in one
 * runtime, so a shared `SN_WEBSUB_*` const would redeclare-fatal — hence the
 * theme-prefixed function names + the inline default literal here.
 *
 * STANDALONE-SAFE. Works with the plugin absent (purely declarative). Filtering
 * the hub to '' advertises nothing (opt-out). The href is esc_url()'d at output.
 *
 * @package SignalNoise
 * @since 10.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The WebSub hub to advertise. Public default; overridable via `sn_websub_hub`
 * (the same filter the plugin's ping reads). '' disables advertising.
 *
 * NOTE: this default literal MUST match the plugin's SN_WEBSUB_DEFAULT_HUB.
 *
 * @return string
 */
function sn_feed_websub_hub() {
	return trim( (string) apply_filters( 'sn_websub_hub', 'https://pubsubhubbub.appspot.com/' ) );
}

/** Advertise the hub in the RSS2 feed (atom: namespace prefix). */
function sn_feed_websub_rss2_head() {
	$hub = sn_feed_websub_hub();
	if ( '' === $hub ) {
		return;
	}
	printf( '<atom:link rel="hub" href="%s" />' . "\n", esc_url( $hub ) );
}

/** Advertise the hub in the Atom feed (bare <link>). */
function sn_feed_websub_atom_head() {
	$hub = sn_feed_websub_hub();
	if ( '' === $hub ) {
		return;
	}
	printf( '<link rel="hub" href="%s" />' . "\n", esc_url( $hub ) );
}

if ( ! defined( 'SN_FEED_WEBSUB_TEST' ) || ! SN_FEED_WEBSUB_TEST ) {
	add_action( 'rss2_head', 'sn_feed_websub_rss2_head' );
	add_action( 'atom_head', 'sn_feed_websub_atom_head' );
}
