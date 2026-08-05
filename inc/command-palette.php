<?php
/**
 * Signal & Noise — reader-facing Notes-scoped command palette.
 *
 * ⌘/Ctrl-K or "/" (outside form fields) opens an accessible overlay: search
 * notes (→ /notes/?s=), jump to recent notes, jump to pillar pages. Front-end
 * only — distinct from the plugin's wp-admin @wordpress/commands palette (they
 * never coexist on one document). The data island uses JSON_HEX_TAG so a note
 * titled "</script>" can't break out of the inline tag.
 *
 * @package SignalNoise
 * @since 9.11.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Build the JS data island. Pure + testable.
 *
 * Recent notes come from a bounded publish-only WP_Query (8 items, no_found_rows);
 * pillars from the theme's own sn_theme_pillar_descriptors() (function_exists-
 * guarded so the palette degrades to search + recent when it is absent).
 *
 * v11.2.0: `notes` carries ALL published notes as {t,u} so the JS can rank
 * client-side with plain token arithmetic (no model ever ships to the reader's
 * browser — the site's standing commitment). Hard-clamped at 200 titles+URLs
 * (~3-4 KB inline at the current ~34-note corpus); date DESC so the JS gets a
 * stable recency tiebreak for equal scores.
 *
 * @return array{notesUrl:string, recent:array<int,array{t:string,u:string}>, pillars:array<int,array{t:string,u:string}>, notes:array<int,array{t:string,u:string}>}
 */
function sn_cmdk_build_data() {
	$recent = array();
	$q      = new WP_Query( array(
		'post_type'           => 'post',
		'post_status'         => 'publish',
		'posts_per_page'      => (int) apply_filters( 'sn_palette_recent_count', 8 ),
		'orderby'             => 'date',
		'order'               => 'DESC',
		'no_found_rows'       => true,
		'ignore_sticky_posts' => true,
		// v11.4.6: protected posts never ride a bulk corpus surface. Only titles +
		// permalinks are emitted here (WP treats both as public), so this closes no
		// live leak — it states the convention at the query so a future change that
		// starts emitting excerpts cannot silently widen it. CMA audit INFO-3.
		'has_password'        => false,
	) );
	foreach ( $q->posts as $p ) {
		$recent[] = array(
			't' => html_entity_decode( get_the_title( $p ), ENT_QUOTES ),
			'u' => get_permalink( $p ),
		);
	}
	wp_reset_postdata();

	$pillars = array();
	if ( function_exists( 'sn_theme_pillar_descriptors' ) ) {
		foreach ( sn_theme_pillar_descriptors() as $d ) {
			$pillars[] = array(
				// v11.4.6: decode like recent/notes. A pillar title that had been
				// wptexturized would otherwise render its entity literally in the
				// palette (the JS writes labels via textContent, so this was only
				// ever cosmetic — never an injection). CMA audit 2026-08-05 INFO-1.
				't' => html_entity_decode( (string) $d['title'], ENT_QUOTES ),
				'u' => home_url( '/' . $d['slug'] . '/' ),
			);
		}
	}

	// Full corpus for the JS ranker: every published note as {t,u}, bounded.
	$notes = array();
	$nq    = new WP_Query( array(
		'post_type'              => 'post',
		'post_status'            => 'publish',
		'posts_per_page'         => min( 200, max( 1, (int) apply_filters( 'sn_palette_notes_cap', 200 ) ) ),
		'orderby'                => 'date',
		'order'                  => 'DESC',
		'no_found_rows'          => true,
		'ignore_sticky_posts'    => true,
		'update_post_meta_cache' => false,
		'update_post_term_cache' => false,
		'has_password'           => false, // v11.4.6: see the recent query above.
	) );
	foreach ( $nq->posts as $p ) {
		$notes[] = array(
			't' => html_entity_decode( get_the_title( $p ), ENT_QUOTES ),
			'u' => get_permalink( $p ),
		);
	}
	wp_reset_postdata();

	return array(
		'notesUrl' => home_url( '/notes/' ),
		'recent'   => $recent,
		'pillars'  => $pillars,
		'notes'    => $notes,
	);
}

/**
 * Whether the reader command palette is active. Default true; the companion
 * plugin supplies sn_setting('theme.palette_enabled') via this filter.
 */
function sn_cmdk_enabled() {
	return (bool) apply_filters( 'sn_palette_enabled', true );
}

/**
 * Add sn-cmdk-off to <body> when the palette is disabled, so the static footer
 * trigger (parts/footer.html) is hidden via the combined stylesheet (the rule
 * moved from critical.css to assets/css/article.css in v10.49.0) even though the palette
 * stylesheet is not enqueued.
 *
 * @param string[] $classes Body classes.
 * @return string[]
 */
function sn_cmdk_body_class( $classes ) {
	if ( ! sn_cmdk_enabled() ) {
		$classes[] = 'sn-cmdk-off';
	}
	return $classes;
}

/**
 * Enqueue palette CSS + JS site-wide (no is_singular guard — palette is global).
 * The data island is injected before the deferred module via wp_add_inline_script.
 * Skipped entirely when the palette is disabled (sn_palette_enabled=false).
 */
function sn_cmdk_enqueue() {
	if ( ! sn_cmdk_enabled() ) {
		return;
	}
	// v10.21.6: the combined stylesheet (inc/asset-combine.php) already
	// carries command-palette.css; only enqueue the separate file in the
	// combiner's fail-open fallback mode (where the sn-components
	// dependency handle also exists again).
	$combined = function_exists( 'sn_css_ensure_combined' ) ? sn_css_ensure_combined() : null;
	if ( null === $combined ) {
		wp_enqueue_style(
			'sn-command-palette',
			get_theme_file_uri( 'assets/css/command-palette.css' ),
			array( 'sn-components' ),
			sn_asset_ver( 'assets/css/command-palette.css' )
		);
	}
	wp_enqueue_script(
		'sn-command-palette',
		get_theme_file_uri( 'assets/js/command-palette.js' ),
		array(),
		sn_asset_ver( 'assets/js/command-palette.js' ),
		array( 'in_footer' => true, 'strategy' => 'defer' )
	);
	$json = wp_json_encode( sn_cmdk_build_data(), JSON_HEX_TAG | JSON_UNESCAPED_SLASHES );
	wp_add_inline_script( 'sn-command-palette', 'window.SN_CMDK=' . $json . ';', 'before' );
}

// The visible trigger button is server-rendered in the footer utility bar
// (parts/footer.html, wp:html block) rather than injected as a position:fixed
// wp_footer overlay — the floating button collided with the footer colophon
// (v9.11.3). command-palette.js binds it by the .sn-cmdk-trigger class, so its
// location is immaterial to the wiring. ⌘K / Ctrl-K / "/" still open it globally.

if ( ! defined( 'SN_CMDK_TEST' ) || ! SN_CMDK_TEST ) {
	add_action( 'wp_enqueue_scripts', 'sn_cmdk_enqueue', 30 );
	add_filter( 'body_class', 'sn_cmdk_body_class' );
}
