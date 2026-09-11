<?php
/**
 * Signal & Noise — Block Hooks API enforcement for the single template.
 *
 * `templates/single.html` already places `<!-- wp:signal-noise/prov-chip /-->`
 * after the title (v13.1.0, inc/blocks-php-only.php's chip block) and
 * `<!-- wp:template-part {"slug":"post-closing"} /-->` after the content —
 * both explicitly, by hand. This module makes the same placement a RULE via
 * the core Block Hooks API (`hooked_block_types` + the per-block-type
 * filter), scoped strictly to the `single` wp_template. WordPress records
 * `ignoredHookedBlocks` metadata on a template that already places a hooked
 * block, so nothing duplicates on the shipped template — the rule only fires
 * for a `single` template that lacks the explicit placement (a fresh export,
 * a reset, a future edit that drops the block).
 *
 * Scope is deliberately narrow: `$context` must be a WP_Block_Template whose
 * `type` is `wp_template` and `slug` is `single` — never a template PART
 * (type `wp_template_part`; WordPress core has no separate
 * WP_Block_Template_Part class), never a WP_Post (block editor iframe /
 * post content), never an array (a pattern).
 *
 * @package SignalNoise
 * @since 13.1.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Is $context the single wp_template (never a part, post, or pattern)?
 *
 * @param mixed $context WP_Block_Template|WP_Post|array|null.
 * @return bool
 */
function sn_is_single_template( $context ) {
	return $context instanceof WP_Block_Template
		&& 'wp_template' === $context->type
		&& 'single' === $context->slug;
}

/**
 * hooked_block_types — declare what hooks where on the single template.
 *
 * @param string[]           $hooked_block_types Block types already hooked at this position.
 * @param string             $relative_position  'before'|'after'|'first_child'|'last_child'.
 * @param string             $anchor_block_type  The block type being hooked onto.
 * @param WP_Block_Template|WP_Post|array|null $context Template, post, or pattern.
 * @return string[]
 */
function sn_hooked_block_types( $hooked_block_types, $relative_position, $anchor_block_type, $context ) {
	if ( ! sn_is_single_template( $context ) || 'after' !== $relative_position ) {
		return $hooked_block_types;
	}

	if ( 'core/post-title' === $anchor_block_type ) {
		$hooked_block_types[] = 'signal-noise/prov-chip';
	} elseif ( 'core/post-content' === $anchor_block_type ) {
		$hooked_block_types[] = 'core/template-part';
	}

	return $hooked_block_types;
}
add_filter( 'hooked_block_types', 'sn_hooked_block_types', 10, 4 );

/**
 * hooked_block_core/template-part — shape the hooked template-part block
 * that `sn_hooked_block_types()` declared after core/post-content, so it
 * resolves to parts/post-closing.html (mirrors the explicit placement in
 * templates/single.html, which carries no `theme` attribute).
 *
 * @param array|null         $parsed_hooked_block The block, in parsed array format, or null to skip it.
 * @param string             $hooked_block_type   Always 'core/template-part' here.
 * @param string             $relative_position   'before'|'after'|'first_child'|'last_child'.
 * @param array              $parsed_anchor_block  The anchor block.
 * @param WP_Block_Template|WP_Post|array|null $context Template, post, or pattern.
 * @return array|null
 */
function sn_hooked_block_post_closing( $parsed_hooked_block, $hooked_block_type, $relative_position, $parsed_anchor_block, $context ) {
	if ( null === $parsed_hooked_block ) {
		return null;
	}

	if ( ! sn_is_single_template( $context ) || 'after' !== $relative_position ) {
		return $parsed_hooked_block;
	}

	if ( 'core/post-content' !== ( $parsed_anchor_block['blockName'] ?? null ) ) {
		return $parsed_hooked_block;
	}

	$parsed_hooked_block['attrs']['slug'] = 'post-closing';
	$parsed_hooked_block['attrs']['area'] = 'article';

	return $parsed_hooked_block;
}
add_filter( 'hooked_block_core/template-part', 'sn_hooked_block_post_closing', 10, 5 );
