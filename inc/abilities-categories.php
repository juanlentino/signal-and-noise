<?php
/**
 * Signal & Noise — Abilities API category registrations.
 *
 * Extracted from inc/abilities-registration.php by the v9.1.7 split (B-11
 * theme-side, companion to plugin v4.1.3). Registers the 3 categories
 * theme abilities cite (diagnostics, content, ai-generation).
 *
 * Per upstream source at class-wp-ability-categories-registry.php:57-67,
 * calling wp_register_ability_category() on an already-registered slug
 * fires _doing_it_wrong. The plugin (signal-and-noise-tools v2.0.4+)
 * also registers the same 3 slugs — wp_has_ability_category() guards
 * make both sides idempotent and tolerate any install configuration
 * (theme-only, plugin-only, both).
 *
 * @package SignalNoise
 * @since 9.1.7 (content from 9.1.0)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the 3 ability categories defensively.
 *
 * @since 9.1.0
 */
function sn_theme_register_ability_categories() {
	if ( ! function_exists( 'wp_register_ability_category' ) ) {
		return;
	}
	if ( ! function_exists( 'wp_has_ability_category' ) ) {
		return;
	}

	if ( ! wp_has_ability_category( 'diagnostics' ) ) {
		wp_register_ability_category( 'diagnostics', array(
			'label'       => 'Diagnostics',
			'description' => "Read-only inspection of the theme + plugin pair's state.",
		) );
	}
	if ( ! wp_has_ability_category( 'content' ) ) {
		wp_register_ability_category( 'content', array(
			'label'       => 'Content',
			'description' => 'Per-post content artifacts (OG cards, schema, etc.).',
		) );
	}
	if ( ! wp_has_ability_category( 'ai-generation' ) ) {
		wp_register_ability_category( 'ai-generation', array(
			'label'       => 'AI Generation',
			'description' => 'AI-generated content artifacts (meta descriptions, summaries, etc.).',
		) );
	}
}
add_action( 'wp_abilities_api_categories_init', 'sn_theme_register_ability_categories' );
