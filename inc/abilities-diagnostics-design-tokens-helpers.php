<?php
/**
 * Abilities API: design-token content check and preset-origin flattening.
 *
 * Moved verbatim out of inc/abilities-diagnostics.php, which loads it.
 *
 * @package SignalNoise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * True when a design-tokens payload has enough real content to be usable.
 *
 * Guards BOTH sn_theme_ability_design_tokens() (dies at the source) and
 * sn_theme_ability_design_system_summary() (refuses to format a hollow
 * document) against fabricating a plausible-looking empty result when the
 * underlying theme.json read comes back with nothing real — a genuinely
 * tokenless settings tree, or the origin-bucket misread the reader's own
 * fix guards against.
 *
 * @since 10.42.1
 * @param array $tokens The sn_theme_ability_design_tokens() output shape.
 * @return bool
 */
function sn_theme_design_tokens_has_content( $tokens ) {
	// v12.0.0: `colors` is a STRUCT now, so a non-empty outer array is no
	// longer evidence of content — array('served'=>'','resolved'=>array(),
	// 'palettes'=>array()) is truthy and carries nothing. The check has to
	// reach actual colours.
	// ONLY `resolved` counts, deliberately. `palettes` is read from theme.json,
	// styles/*.json and critical.css — files that are always present — so it is
	// non-empty even when the WordPress read this function exists to validate
	// returned nothing. Letting it vouch for content would defeat the whole
	// guarantee: a hollow read would come back as a plausible-looking token set
	// carrying three palettes and no live data. Caught by the hollow-read
	// assertions in tests/design-tokens.php while shipping v12.0.0.
	if ( ! empty( $tokens['colors']['resolved'] ) ) {
		return true;
	}
	$font_families = isset( $tokens['typography']['fontFamilies'] ) ? (array) $tokens['typography']['fontFamilies'] : array();
	foreach ( $font_families as $ff ) {
		if ( is_array( $ff ) && ! empty( $ff['slug'] ) ) {
			return true;
		}
	}
	$font_sizes = isset( $tokens['typography']['fontSizes'] ) ? (array) $tokens['typography']['fontSizes'] : array();
	foreach ( $font_sizes as $fs ) {
		if ( is_array( $fs ) && ! empty( $fs['slug'] ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Merges a theme.json preset array across WP core's origin buckets.
 *
 * wp_get_global_settings() presets that use the "append" merge strategy
 * (color.palette, typography.fontFamilies, typography.fontSizes,
 * spacing.spacingSizes) come back keyed by ORIGIN — 'default' (core),
 * 'theme' (theme.json), 'custom' (user Global Styles) — not as a flat
 * list of entries. Reading a bucket as if it were a single preset entry
 * is how this ability shipped born-broken: colors silently dropped to
 * zero (a bucket has no 'slug'/'color' pair of its own) and fontFamilies/
 * fontSizes/spacingSizes reported a count equal to the number of origin
 * keys present, never the number of real presets.
 *
 * Core's own precedence is default -> theme -> custom: a later origin's
 * entry overrides an earlier one of the SAME slug (a theme.json palette
 * color overrides core's default of that slug; a user's Global Styles
 * pick overrides the theme's).
 *
 * Tolerates an ALREADY-flat entry list (no recognized origin key present)
 * by passing it through unchanged — defensive for callers/fixtures that
 * hand back a pre-resolved list directly.
 *
 * @since 10.42.1
 * @param mixed $value Raw preset value from wp_get_global_settings().
 * @return array<int,array> Flat list of preset entries.
 */
function sn_theme_flatten_preset_origins( $value ) {
	$value = (array) $value;
	if ( empty( $value ) ) {
		return array();
	}

	$origin_order    = array( 'default', 'theme', 'custom' );
	$is_origin_keyed = false;
	foreach ( $origin_order as $origin ) {
		if ( array_key_exists( $origin, $value ) ) {
			$is_origin_keyed = true;
			break;
		}
	}

	if ( ! $is_origin_keyed ) {
		return array_values( $value );
	}

	$merged = array();
	foreach ( $origin_order as $origin ) {
		if ( ! isset( $value[ $origin ] ) || ! is_array( $value[ $origin ] ) ) {
			continue;
		}
		foreach ( $value[ $origin ] as $entry ) {
			if ( is_array( $entry ) && isset( $entry['slug'] ) ) {
				$merged[ (string) $entry['slug'] ] = $entry;
			}
		}
	}
	return array_values( $merged );
}
