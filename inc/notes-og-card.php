<?php
/**
 * Signal & Noise - bespoke OG share card for the /notes index.
 *
 * The companion plugin owns og:image emission. Since plugin v9.25.4 it resolves
 * the image per view: a SINGULAR view (a single Note/Page, or the static front
 * page) uses that post's generated 1200x630 card; a NON-singular view (the
 * /notes index, tag archives, search) falls through to the site-default OG
 * image, which is a small square logo. A shared /notes link therefore previewed
 * with a low-detail logo instead of a real card.
 *
 * This module gives the /notes INDEX its own 1200x630 share card, baked in the
 * same design language as the plugin's per-post cards (red tick, JUANLENTINO.COM
 * eyebrow, Bebas title, DM Mono dek, blood-red footer). It plugs into the
 * plugin's `sn_og_image_url` filter seam at priority 20 - after the plugin's own
 * filter (default priority 10) has returned the site default - so on the notes
 * index the theme's card wins, and every other view passes through unchanged.
 * The plugin declares the correct 1200x630 dimensions and
 * twitter:card=summary_large_image on its own (it measures the local file, and
 * its dimension fallback is 1200x630), so no dimensions filter is needed here.
 *
 * Static asset by design: the /notes identity is stable, so a committed PNG is
 * simpler than a generation path for one route. Re-bake it only if the card copy
 * changes.
 *
 * @package SignalNoise
 * @since 10.39.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * URL of the committed bespoke /notes card asset (1200x630 PNG).
 *
 * @return string
 */
function sn_notes_og_card_url() {
	return get_theme_file_uri( 'assets/images/og-notes-card.png' );
}

/**
 * On the /notes index only, replace the resolved og:image with the bespoke
 * card. Guarded on sn_notes_is_index_request() (the same matcher that owns the
 * /notes render), so single Notes, /notes tag archives, search results, and
 * every other view pass through untouched.
 *
 * @param string $url og:image URL resolved so far (the plugin's site default on
 *                    a non-singular view).
 * @return string
 */
function sn_notes_og_card_image( $url ) {
	if ( function_exists( 'sn_notes_is_index_request' ) && sn_notes_is_index_request() ) {
		$card = sn_notes_og_card_url();
		if ( '' !== (string) $card ) {
			return $card;
		}
	}
	return $url;
}

if ( ! defined( 'SN_NOTES_OG_CARD_TEST' ) || ! SN_NOTES_OG_CARD_TEST ) {
	// Priority 20: run after the plugin's own sn_og_image_url filter (10) so the
	// notes-index card overrides the site default it returns.
	add_filter( 'sn_og_image_url', 'sn_notes_og_card_image', 20 );
}
