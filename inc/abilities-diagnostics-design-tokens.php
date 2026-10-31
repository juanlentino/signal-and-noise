<?php
/**
 * Abilities API: the get-design-tokens callback.
 *
 * Moved verbatim out of inc/abilities-diagnostics.php, which loads it.
 *
 * @package SignalNoise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Execute callback: signal-and-noise/get-design-tokens.
 *
 * @since 9.1.0
 * @return array|WP_Error
 */
function sn_theme_ability_design_tokens() {
	try {
		if ( ! function_exists( 'wp_get_global_settings' ) ) {
			return new WP_Error(
				'theme_dependency_missing',
				'wp_get_global_settings() not available — requires WP 5.9+.',
				array( 'status' => 503 )
			);
		}

		$settings = wp_get_global_settings();

		$colors      = array();
		$palette_raw = isset( $settings['color']['palette'] ) ? $settings['color']['palette'] : array();
		foreach ( sn_theme_flatten_preset_origins( $palette_raw ) as $entry ) {
			if ( is_array( $entry ) && isset( $entry['slug'], $entry['color'] ) ) {
				$colors[ (string) $entry['slug'] ] = (string) $entry['color'];
			}
		}

		$typography = isset( $settings['typography'] ) ? (array) $settings['typography'] : array();
		$spacing    = isset( $settings['spacing'] )    ? (array) $settings['spacing']    : array();

		// spacingScale is a single resolved SETTING VALUE, not an append-merge
		// preset list — origins override it outright rather than appending, so
		// wp_get_global_settings() already hands back one value. No origin
		// unwrap needed here; pass through as before.
		$spacing_scale = isset( $spacing['spacingScale'] ) ? (array) $spacing['spacingScale'] : array();

		$theme   = function_exists( 'wp_get_theme' ) ? wp_get_theme() : null;
		$version = $theme && method_exists( $theme, 'get' ) ? (string) $theme->get( 'Version' ) : '';

		// ── BREAKING (v12.0.0): `colors` IS KEYED BY PALETTE IDENTITY ─────
		// Was a flat slug => hex map. The site has never served one palette —
		// theme.json is the default, styles/high-contrast.json is a shipped
		// variation (and the one the live site actually runs), and v12.0.0 adds
		// a dark token layer. This field described exactly one of the three.
		//
		// It is not cosmetic: these values are embedded into AI prompts by the
		// formatters below, so the flat map was telling a model the ground is
		// #ffffff while High Contrast readers saw #e0e0e0 asphalt and dark
		// readers saw a #0a0a0a ground.
		//
		// KEYED BY IDENTITY, NOT BY SCHEME. `{light, dark}` was the obvious
		// shape and it is a trap: High Contrast is itself a LIGHT palette, so it
		// has nowhere to go, and adding it later would be a SECOND break to the
		// same field. Identity keys make every future palette additive. Each
		// entry carries its own `scheme`, which is the axis a key name cannot
		// express — and the two axes are genuinely orthogonal, because the dark
		// layer overrides :root and therefore REPLACES whatever variation is
		// active rather than sitting beside it.
		//
		// The flat map is REMOVED, not aliased. An alias would let every
		// existing consumer keep reading the half-truth and never learn there
		// were other palettes — the wrong call has to stop being expressible.
		$palettes = function_exists( 'sn_theme_all_palettes' ) ? sn_theme_all_palettes() : array();
		$served   = function_exists( 'sn_theme_served_palette_id' ) ? sn_theme_served_palette_id() : '';

		// `resolved` is what WordPress hands back for THIS request — the
		// activated variation's database copy, not theme.json. Kept as its own
		// key because it is the only entry that answers "what is live right
		// now", and `served` names which shipped palette it matched.
		$tokens = array(
			'colors'     => array(
				'served'   => $served,
				'resolved' => $colors,
				'palettes' => $palettes,
			),
			'typography' => array(
				'fontFamilies' => sn_theme_flatten_preset_origins( isset( $typography['fontFamilies'] ) ? $typography['fontFamilies'] : array() ),
				'fontSizes'    => sn_theme_flatten_preset_origins( isset( $typography['fontSizes'] )    ? $typography['fontSizes']    : array() ),
			),
			'spacing'    => array(
				'spacingScale' => $spacing_scale,
				'spacingSizes' => sn_theme_flatten_preset_origins( isset( $spacing['spacingSizes'] ) ? $spacing['spacingSizes'] : array() ),
			),
			'version'    => $version,
		);

		// The read must fail LOUDLY, not hand back a plausible-looking empty
		// token set: a genuinely hollow theme.json (or a future regression of
		// the origin unwrap above) is a read failure, not an empty design
		// system. Dies at the source so every consumer — get-design-tokens
		// callers AND get-design-system-summary — sees the same signal.
		if ( ! sn_theme_design_tokens_has_content( $tokens ) ) {
			return new WP_Error(
				'design_tokens_empty',
				'Design tokens read as empty: theme.json presets (color.palette, typography.fontFamilies, typography.fontSizes) resolved to zero real entries. Treating this as a read failure rather than an empty design system.',
				array( 'status' => 500 )
			);
		}

		return $tokens;
	} catch ( \Throwable $e ) {
		error_log( 'SN theme ability error in get-design-tokens: ' . $e->getMessage() );
		return new WP_Error(
			'theme_ability_error',
			sprintf( 'Theme ability failed: %s', $e->getMessage() ),
			array( 'status' => 500 )
		);
	}
}
