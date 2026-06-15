<?php
/**
 * Signal & Noise — rel="me" identity links (A3).
 *
 * Emits one <link rel="me" href="…"> in <head> per configured social profile.
 * This is the IndieWeb / IndieAuth / Mastodon identity handshake: a profile
 * that links back to this site with rel=me, plus this site linking out with
 * rel=me, establishes bidirectional ownership.
 *
 * DATA SOURCE + CONTRACT. The profile URLs live in the companion plugin
 * (signal-and-noise-tools) under the `sn_settings` option, subtree
 * `social.same_as`. The plugin also exposes a `sn_schema_same_as` filter — but
 * it applies that filter with its default passed INLINE (it registers no
 * standing callback that injects URLs), so calling
 * apply_filters('sn_schema_same_as', array()) returns empty even when the
 * plugin is active. The theme therefore reads the option directly and passes
 * the result THROUGH the filter, so the documented override hook still governs
 * the final list.
 *
 * STANDALONE-SAFE. The theme is independently installable. When the plugin is
 * absent the `sn_settings` option does not exist, get_option() returns the
 * default, the URL list is empty, and zero links are emitted — no fatal. (The
 * option also persists if the plugin is merely deactivated, so profiles keep
 * verifying.) Mirrors the read-and-degrade pattern in inc/discography-render.php
 * and inc/music-featured-render.php.
 *
 * @package SignalNoise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Collect the sanitized, de-duplicated list of rel=me profile URLs.
 *
 * @return string[] Profile URLs (may be empty).
 */
function sn_identity_rels_urls() {
	$settings = get_option( 'sn_settings' );

	$urls = array();
	if ( is_array( $settings )
		&& ! empty( $settings['social']['same_as'] )
		&& is_array( $settings['social']['same_as'] )
	) {
		$urls = $settings['social']['same_as'];
	}

	// Honor the plugin's documented sn_schema_same_as filter as an override hook
	// (parity with how the plugin builds its Person.sameAs schema list).
	$urls = (array) apply_filters( 'sn_schema_same_as', $urls );

	// Sanitize + de-duplicate (array keys collapse repeats; preserves order).
	$clean = array();
	foreach ( $urls as $url ) {
		$url = esc_url_raw( trim( (string) $url ) );
		if ( '' !== $url ) {
			$clean[ $url ] = true;
		}
	}

	return array_keys( $clean );
}

/**
 * Print the <link rel="me"> tags. Escapes each href at the output sink even
 * though the plugin pre-sanitizes on save — a filter override could supply
 * untrusted values, so the theme never trusts filtered data.
 */
function sn_identity_rels_head_link() {
	foreach ( sn_identity_rels_urls() as $url ) {
		printf( '<link rel="me" href="%s">' . "\n", esc_url( $url ) );
	}
}

if ( ! defined( 'SN_IDENTITY_RELS_TEST' ) || ! SN_IDENTITY_RELS_TEST ) {
	add_action( 'wp_head', 'sn_identity_rels_head_link' );
}
