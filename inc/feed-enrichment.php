<?php
/**
 * Signal & Noise — RSS item enrichment.
 *
 * Adds the Media RSS namespace + <media:content> (featured image) and a
 * reading-time element to RSS2 items. Reading time is plugin-owned
 * (sn_get_reading_time) — function_exists-guarded so the feed degrades when
 * the plugin is absent. Core already emits <category> tags; we do not.
 *
 * Both `rss2_ns` and `rss2_item` are core do_action() hooks (see
 * wp-includes/feed-rss2.php) — we register with add_action and echo directly;
 * neither is a value-returning filter.
 *
 * @package SignalNoise
 * @since 9.11.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * rss2_ns action: declare the namespaces our items reference. The Media RSS
 * namespace backs <media:content>; the sn: namespace backs the reading-time
 * element so the feed stays well-formed XML.
 */
function sn_rss_media_ns() {
	echo 'xmlns:media="http://search.yahoo.com/mrss/"' . "\n";
	echo 'xmlns:sn="https://juanlentino.com/ns/feed"' . "\n";
}

/**
 * rss2_item action: emit per-item enrichment for the current loop post —
 * the featured image as <media:content> and (plugin-permitting) reading time.
 */
function sn_rss_item_enrich() {
	$id    = get_the_ID();
	$thumb = get_post_thumbnail_id( $id );
	if ( $thumb ) {
		$url = wp_get_attachment_image_url( $thumb, 'full' );
		if ( $url ) {
			echo '<media:content url="' . esc_url( $url ) . '" medium="image" />' . "\n";
		}
	}
	if ( function_exists( 'sn_get_reading_time' ) ) {
		$mins = (int) sn_get_reading_time( $id );
		if ( $mins >= 1 ) {
			echo '<sn:readingTimeMinutes>' . esc_html( (string) $mins ) . '</sn:readingTimeMinutes>' . "\n";
		}
	}
	// v10.48.0: mirror the Note uid under the sn: namespace sn_rss_media_ns()
	// already declares, so RSS subscribers can resolve the /verify docket
	// without a second fetch. No uid, no element. v10.49.0: read via the
	// canonical normalized helper (inc/note-uid.php).
	$uid = function_exists( 'sn_theme_note_uid' ) ? sn_theme_note_uid( $id ) : '';
	if ( '' !== $uid ) {
		echo '<sn:noteUid>' . esc_html( $uid ) . '</sn:noteUid>' . "\n";
	}
}

if ( ! defined( 'SN_FEED_ENRICH_TEST' ) || ! SN_FEED_ENRICH_TEST ) {
	add_action( 'rss2_ns', 'sn_rss_media_ns' );
	add_action( 'rss2_item', 'sn_rss_item_enrich' );
}
